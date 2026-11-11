<?php
/**
 * API Backend para Creador de Informes de Monitoreo Zabbix
 * SYNAPSE / CMDB VILASECA
 * Ubicación: public/api_report_creator.php
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../src/auth.php";
require_once __DIR__ . "/../src/permissions_helper.php";
require_once __DIR__ . "/../src/zabbix_api.php";
require_once __DIR__ . "/../src/db.php";

header("Content-Type: application/json; charset=utf-8");

// Verificación de Autenticación
if (!current_user_id()) {
    echo json_encode(["success" => false, "error" => "Sesión expirada o no iniciada"]);
    exit;
}

if (!has_module_access("reports") && !has_module_access("monitoreo")) {
    echo json_encode(["success" => false, "error" => "No tiene permisos suficientes para el módulo de informes"]);
    exit;
}

$action = $_REQUEST["action"] ?? "";

try {
    switch ($action) {
        // -------------------------------------------------------------
        // 1. Obtener Grupos de Hosts de Zabbix
        // -------------------------------------------------------------
        case "get_groups":
            $resp = call_zabbix_api("hostgroup.get", [
                "output" => ["groupid", "name"],
                "sortfield" => "name",
            ]);
            if (isset($resp["error"])) {
                echo json_encode(["success" => false, "error" => $resp["error"]]);
            } else {
                echo json_encode(["success" => true, "data" => $resp["result"]]);
            }
            break;

        // -------------------------------------------------------------
        // 2. Obtener Equipos por Grupos Seleccionados
        // -------------------------------------------------------------
        case "get_hosts":
            $groupids_raw = $_REQUEST["groupids"] ?? "";
            $groupids = is_array($groupids_raw) ? $groupids_raw : array_filter(explode(",", (string)$groupids_raw));

            $params = [
                "output" => ["hostid", "host", "name", "status", "available", "snmp_available"],
                "selectInterfaces" => ["interfaceid", "ip", "dns", "useip", "available", "type", "main"],
                "selectHostGroups" => ["groupid", "name"],
                "sortfield" => "name",
            ];

            if (!empty($groupids)) {
                $params["groupids"] = $groupids;
            }

            $resp = call_zabbix_api("host.get", $params);
            if (isset($resp["error"])) {
                echo json_encode(["success" => false, "error" => $resp["error"]]);
                break;
            }

            $hosts = [];
            foreach ($resp["result"] as $h) {
                $ip = "";
                if (!empty($h["interfaces"])) {
                    foreach ($h["interfaces"] as $iface) {
                        if ($iface["main"] == 1 || empty($ip)) {
                            $ip = $iface["ip"];
                        }
                    }
                }
                $hosts[] = [
                    "hostid" => $h["hostid"],
                    "name" => $h["name"],
                    "host" => $h["host"],
                    "ip" => $ip,
                    "status" => $h["status"],
                    "available" => $h["available"] ?? "0",
                    "snmp_available" => $h["snmp_available"] ?? "0",
                    "groups" => array_column($h["hostgroups"] ?? [], "name")
                ];
            }

            echo json_encode(["success" => true, "data" => $hosts]);
            break;

        // -------------------------------------------------------------
        // 3. Obtener Interfaces de Red por Equipos Seleccionados
        // -------------------------------------------------------------
        case "get_interfaces":
            $hostids_raw = $_REQUEST["hostids"] ?? "";
            $hostids = is_array($hostids_raw) ? $hostids_raw : array_filter(explode(",", (string)$hostids_raw));

            if (empty($hostids)) {
                echo json_encode(["success" => false, "error" => "Debe indicar al menos un equipo (hostid)"]);
                break;
            }

            $resp = call_zabbix_api("item.get", [
                "hostids" => $hostids,
                "output" => ["itemid", "hostid", "name", "key_", "units", "lastvalue", "value_type"],
                "search" => ["key_" => "net.if"],
                "filter" => ["status" => 0]
            ]);

            if (isset($resp["error"])) {
                echo json_encode(["success" => false, "error" => $resp["error"]]);
                break;
            }

            $items = $resp["result"] ?? [];
            $by_host = [];

            foreach ($hostids as $hid) {
                $by_host[$hid] = [];
            }

            foreach ($items as $item) {
                $hid = $item["hostid"];
                $name = $item["name"];
                $key = $item["key_"];

                $ifname = "";
                $alias = "";
                $metric = "";

                if (preg_match("/Interface\s+([^\(\:\#]+)(?:\(([^\)]*)\))?:\s*(.+)/i", $name, $m)) {
                    $ifname = trim($m[1]);
                    $alias = trim($m[2] ?? "");
                    $metric = trim($m[3]);
                } elseif (preg_match("/(?:Incoming|Outgoing|traffic|errors|discards).*?(?:on|interface)\s+([^\s\:]+)/i", $name, $m)) {
                    $ifname = trim($m[1]);
                    $metric = $name;
                } elseif (preg_match("/\[\"?([^\"\,\]]+)\"?/", $key, $m)) {
                    $ifname = trim($m[1]);
                    $metric = $name;
                }

                if (!$ifname) {
                    continue;
                }

                if (!isset($by_host[$hid][$ifname])) {
                    $by_host[$hid][$ifname] = [
                        "ifname" => $ifname,
                        "alias" => $alias,
                        "traffic_in" => null,
                        "traffic_out" => null,
                        "errors_in" => null,
                        "errors_out" => null,
                        "discards_in" => null,
                        "discards_out" => null,
                        "speed" => null,
                        "status" => null,
                    ];
                }

                if ($alias && empty($by_host[$hid][$ifname]["alias"])) {
                    $by_host[$hid][$ifname]["alias"] = $alias;
                }

                $metric_lower = strtolower($metric);
                $key_lower = strtolower($key);

                if (strpos($metric_lower, "bits received") !== false || strpos($key_lower, "net.if.in") !== false && strpos($key_lower, "errors") === false && strpos($key_lower, "dropped") === false) {
                    $by_host[$hid][$ifname]["traffic_in"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "bits sent") !== false || strpos($key_lower, "net.if.out") !== false && strpos($key_lower, "errors") === false && strpos($key_lower, "dropped") === false) {
                    $by_host[$hid][$ifname]["traffic_out"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "inbound packets with errors") !== false || strpos($key_lower, "net.if.in.errors") !== false || (strpos($key_lower, "net.if.in[") !== false && strpos($key_lower, "errors") !== false)) {
                    $by_host[$hid][$ifname]["errors_in"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "outbound packets with errors") !== false || strpos($key_lower, "net.if.out.errors") !== false || (strpos($key_lower, "net.if.out[") !== false && strpos($key_lower, "errors") !== false)) {
                    $by_host[$hid][$ifname]["errors_out"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "inbound packets discarded") !== false) {
                    $by_host[$hid][$ifname]["discards_in"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "outbound packets discarded") !== false) {
                    $by_host[$hid][$ifname]["discards_out"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "speed") !== false) {
                    $by_host[$hid][$ifname]["speed"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                } elseif (strpos($metric_lower, "status") !== false) {
                    $by_host[$hid][$ifname]["status"] = [
                        "itemid" => $item["itemid"],
                        "name" => $item["name"],
                        "units" => $item["units"],
                        "lastvalue" => $item["lastvalue"]
                    ];
                }
            }

            $output = [];
            foreach ($by_host as $hid => $if_map) {
                $if_list = array_values($if_map);
                usort($if_list, function($a, $b) {
                    return strnatcasecmp($a["ifname"], $b["ifname"]);
                });
                $output[$hid] = $if_list;
            }

            echo json_encode(["success" => true, "data" => $output]);
            break;

        // -------------------------------------------------------------
        // 4. Configuración de Inteligencia Artificial (Gemini / Ollama)
        // -------------------------------------------------------------
        case "get_ai_config":
            $pdo = getPDO();
            $stmt = $pdo->query("SELECT * FROM app_ai_config ORDER BY id DESC LIMIT 1");
            $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cfg) {
                $cfg = [
                    "gemini_api_key" => "",
                    "gemini_model" => "gemini-1.5-flash",
                    "ollama_url" => "http://localhost:11434",
                    "ollama_model" => "llama3:latest",
                    "default_provider" => "gemini"
                ];
            }
            $masked_key = "";
            if (!empty($cfg["gemini_api_key"])) {
                $len = strlen($cfg["gemini_api_key"]);
                $masked_key = substr($cfg["gemini_api_key"], 0, 4) . str_repeat("*", max(0, $len - 8)) . substr($cfg["gemini_api_key"], -4);
            }
            $cfg["gemini_api_key_masked"] = $masked_key;
            $cfg["has_gemini_key"] = !empty($cfg["gemini_api_key"]);

            echo json_encode(["success" => true, "data" => $cfg]);
            break;

        case "save_ai_config":
            $gemini_key = trim($_POST["gemini_api_key"] ?? "");
            $gemini_model = trim($_POST["gemini_model"] ?? "gemini-1.5-flash");
            $ollama_url = trim($_POST["ollama_url"] ?? "http://localhost:11434");
            $ollama_model = trim($_POST["ollama_model"] ?? "llama3:latest");
            $default_provider = trim($_POST["default_provider"] ?? "gemini");

            $pdo = getPDO();
            $stmt = $pdo->query("SELECT id, gemini_api_key FROM app_ai_config ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (strpos($gemini_key, "****") !== false && $existing) {
                $gemini_key = $existing["gemini_api_key"];
            }

            if ($existing) {
                $up = $pdo->prepare("UPDATE app_ai_config SET gemini_api_key = :k, gemini_model = :gm, ollama_url = :ou, ollama_model = :om, default_provider = :dp WHERE id = :id");
                $up->execute([
                    ":k" => $gemini_key,
                    ":gm" => $gemini_model,
                    ":ou" => $ollama_url,
                    ":om" => $ollama_model,
                    ":dp" => $default_provider,
                    ":id" => $existing["id"]
                ]);
            } else {
                $ins = $pdo->prepare("INSERT INTO app_ai_config (gemini_api_key, gemini_model, ollama_url, ollama_model, default_provider) VALUES (:k, :gm, :ou, :om, :dp)");
                $ins->execute([
                    ":k" => $gemini_key,
                    ":gm" => $gemini_model,
                    ":ou" => $ollama_url,
                    ":om" => $ollama_model,
                    ":dp" => $default_provider
                ]);
            }

            echo json_encode(["success" => true, "message" => "Configuración de Inteligencia Artificial guardada con éxito"]);
            break;

        case "test_ai":
            $provider = $_POST["provider"] ?? "gemini";
            $api_key = trim($_POST["gemini_api_key"] ?? "");
            $model = trim($_POST["model"] ?? "");
            $endpoint = trim($_POST["ollama_url"] ?? "http://localhost:11434");

            $pdo = getPDO();
            $stmt = $pdo->query("SELECT * FROM app_ai_config ORDER BY id DESC LIMIT 1");
            $db_cfg = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($provider === "gemini") {
                if (empty($api_key) || strpos($api_key, "****") !== false) {
                    $api_key = $db_cfg["gemini_api_key"] ?? "";
                }
                if (empty($api_key)) {
                    echo json_encode(["success" => false, "error" => "No se ha configurado la API Key de Google Gemini"]);
                    break;
                }
                $model = $model ?: ($db_cfg["gemini_model"] ?? "gemini-1.5-flash");
                $test_prompt = "Responde exactamente la palabra \"CONEXION_EXITOSA\" para validar la comunicación con la plataforma SYNAPSE.";
                $result = execute_gemini_request($test_prompt, $api_key, $model);
                if ($result["success"]) {
                    echo json_encode(["success" => true, "message" => "Conexión con Google Gemini (" . $model . ") exitosa", "raw" => $result["text"]]);
                } else {
                    echo json_encode(["success" => false, "error" => $result["error"]]);
                }
            } elseif ($provider === "ollama") {
                $endpoint = $endpoint ?: ($db_cfg["ollama_url"] ?? "http://localhost:11434");
                $model = $model ?: ($db_cfg["ollama_model"] ?? "llama3:latest");
                $test_prompt = "Responde exactamente la palabra \"CONEXION_EXITOSA\" para validar la comunicación.";
                $result = execute_ollama_request($test_prompt, $endpoint, $model);
                if ($result["success"]) {
                    echo json_encode(["success" => true, "message" => "Conexión con Ollama (" . $model . ") exitosa", "raw" => $result["text"]]);
                } else {
                    echo json_encode(["success" => false, "error" => $result["error"]]);
                }
            } else {
                echo json_encode(["success" => false, "error" => "Proveedor de IA no reconocido"]);
            }
            break;

        // -------------------------------------------------------------
        // 5. GENERACIÓN COMPLETA DE INFORME
        // -------------------------------------------------------------
        case "generate_report":
            $title = trim($_POST["title"] ?? "Informe de Rendimiento y Tráfico de Red");
            $date_from_str = trim($_POST["date_from"] ?? date("Y-m-d H:i:s", strtotime("-7 days")));
            $date_to_str = trim($_POST["date_to"] ?? date("Y-m-d H:i:s"));
            
            $time_from = strtotime($date_from_str);
            $time_till = strtotime($date_to_str);

            if (!$time_from || !$time_till || $time_from >= $time_till) {
                echo json_encode(["success" => false, "error" => "El rango de fechas proporcionado es inválido"]);
                break;
            }

            $raw_selected = $_POST["selected_interfaces"] ?? [];
            if (is_string($raw_selected)) {
                $raw_selected = json_decode($raw_selected, true) ?: [];
            }

            if (empty($raw_selected)) {
                echo json_encode(["success" => false, "error" => "Debe seleccionar al menos un equipo e interfaz para generar el informe"]);
                break;
            }

            $general_obs = trim($_POST["general_observations"] ?? "");
            $raw_obs = $_POST["interface_observations"] ?? [];
            if (is_string($raw_obs)) {
                $raw_obs = json_decode($raw_obs, true) ?: [];
            }

            $ai_provider = trim($_POST["ai_provider"] ?? "none");
            $report_type = trim($_POST["report_type"] ?? "both");
            $save_report = !empty($_POST["save_report"]);

            $hostids = array_keys($raw_selected);
            $hosts_info_resp = call_zabbix_api("host.get", [
                "hostids" => $hostids,
                "output" => ["hostid", "host", "name", "status", "available", "snmp_available"],
                "selectInterfaces" => ["ip", "dns", "useip", "type", "main"],
                "selectHostGroups" => ["groupid", "name"]
            ]);

            $hosts_map = [];
            foreach ($hosts_info_resp["result"] ?? [] as $h) {
                $ip = "";
                foreach ($h["interfaces"] ?? [] as $ifc) {
                    if ($ifc["main"] == 1 || empty($ip)) {
                        $ip = $ifc["ip"];
                    }
                }
                $hosts_map[$h["hostid"]] = [
                    "hostid" => $h["hostid"],
                    "name" => $h["name"],
                    "host" => $h["host"],
                    "ip" => $ip,
                    "status" => $h["status"],
                    "status_label" => ($h["status"] == 0) ? "Monitoreado" : "No Monitoreado",
                    "available" => $h["available"] ?? "0",
                    "snmp_available" => $h["snmp_available"] ?? "0",
                    "groups" => array_column($h["hostgroups"] ?? [], "name")
                ];
            }

            $all_itemids = [];
            foreach ($raw_selected as $hid => $interfaces) {
                foreach ($interfaces as $ifname => $if_data) {
                    $metrics = ["traffic_in", "traffic_out", "errors_in", "errors_out"];
                    foreach ($metrics as $mtype) {
                        if (!empty($if_data[$mtype]["itemid"])) {
                            $iid = (string)$if_data[$mtype]["itemid"];
                            $all_itemids[] = $iid;
                        }
                    }
                }
            }

            $trends_raw = [];
            if (!empty($all_itemids)) {
                $all_itemids = array_unique($all_itemids);
                $chunks = array_chunk($all_itemids, 100);
                foreach ($chunks as $chunk) {
                    $trend_res = call_zabbix_api("trend.get", [
                        "itemids" => $chunk,
                        "time_from" => $time_from,
                        "time_till" => $time_till
                    ]);
                    if (!empty($trend_res["result"])) {
                        $trends_raw = array_merge($trends_raw, $trend_res["result"]);
                    }
                }

                if (empty($trends_raw) && ($time_till - $time_from) <= (48 * 3600)) {
                    foreach ($chunks as $chunk) {
                        $hist_res = call_zabbix_api("history.get", [
                            "itemids" => $chunk,
                            "time_from" => $time_from,
                            "time_till" => $time_till,
                            "history" => 3,
                            "sortfield" => "clock",
                            "limit" => 5000
                        ]);
                        if (!empty($hist_res["result"])) {
                            $buckets = [];
                            foreach ($hist_res["result"] as $hp) {
                                $hour_clock = floor($hp["clock"] / 3600) * 3600;
                                $key_h = $hp["itemid"] . "_" . $hour_clock;
                                if (!isset($buckets[$key_h])) {
                                    $buckets[$key_h] = [
                                        "itemid" => $hp["itemid"],
                                        "clock" => (string)$hour_clock,
                                        "num" => 0,
                                        "sum" => 0,
                                        "value_min" => $hp["value"],
                                        "value_max" => $hp["value"]
                                    ];
                                }
                                $val = (float)$hp["value"];
                                $buckets[$key_h]["num"]++;
                                $buckets[$key_h]["sum"] += $val;
                                $buckets[$key_h]["value_min"] = min($buckets[$key_h]["value_min"], $val);
                                $buckets[$key_h]["value_max"] = max($buckets[$key_h]["value_max"], $val);
                            }
                            foreach ($buckets as $b) {
                                $trends_raw[] = [
                                    "itemid" => $b["itemid"],
                                    "clock" => $b["clock"],
                                    "num" => $b["num"],
                                    "value_min" => (string)$b["value_min"],
                                    "value_avg" => (string)round($b["sum"] / max(1, $b["num"]), 2),
                                    "value_max" => (string)$b["value_max"]
                                ];
                            }
                        }
                    }
                }
            }

            $trends_by_item = [];
            foreach ($trends_raw as $tr) {
                $iid = $tr["itemid"];
                $clock = (int)$tr["clock"];
                if (!isset($trends_by_item[$iid])) {
                    $trends_by_item[$iid] = [];
                }
                $trends_by_item[$iid][$clock] = [
                    "avg" => (float)$tr["value_avg"],
                    "max" => (float)$tr["value_max"],
                    "min" => (float)$tr["value_min"]
                ];
            }

            $problems_resp = call_zabbix_api("problem.get", [
                "hostids" => $hostids,
                "time_from" => $time_from,
                "time_till" => $time_till,
                "output" => ["eventid", "name", "severity", "clock", "r_clock", "r_eventid", "acknowledged"],
                "sortfield" => "clock",
                "limit" => 100
            ]);

            $alarms_list = [];
            $severity_labels = [
                "0" => ["name" => "No clasificado", "class" => "badge-secondary", "color" => "#6c757d"],
                "1" => ["name" => "Información", "class" => "badge-info", "color" => "#17a2b8"],
                "2" => ["name" => "Advertencia", "class" => "badge-warning", "color" => "#ffc107"],
                "3" => ["name" => "Promedio", "class" => "badge-orange", "color" => "#fd7e14"],
                "4" => ["name" => "Alta", "class" => "badge-danger", "color" => "#dc3545"],
                "5" => ["name" => "Desastre", "class" => "badge-dark", "color" => "#721c24"]
            ];

            foreach ($problems_resp["result"] ?? [] as $pr) {
                $sev = (string)$pr["severity"];
                $sinfo = $severity_labels[$sev] ?? $severity_labels["0"];
                
                $duration = "Activa";
                if (!empty($pr["r_clock"]) && $pr["r_clock"] > 0) {
                    $diff = $pr["r_clock"] - $pr["clock"];
                    $duration = format_seconds_duration($diff);
                }

                $alarms_list[] = [
                    "eventid" => $pr["eventid"],
                    "name" => $pr["name"],
                    "severity" => $sev,
                    "severity_name" => $sinfo["name"],
                    "severity_class" => $sinfo["class"],
                    "severity_color" => $sinfo["color"],
                    "clock" => (int)$pr["clock"],
                    "date_formatted" => date("Y-m-d H:i:s", $pr["clock"]),
                    "r_clock" => (int)$pr["r_clock"],
                    "r_date_formatted" => !empty($pr["r_clock"]) ? date("Y-m-d H:i:s", $pr["r_clock"]) : "N/A",
                    "duration" => $duration
                ];
            }

            $processed_hosts = [];
            $consolidated_days = [];
            $consolidated_hours = [];
            $summary_kpis = [
                "total_hosts" => count($hostids),
                "total_interfaces" => 0,
                "peak_traffic_in" => 0,
                "peak_traffic_out" => 0,
                "total_errors_in" => 0,
                "total_errors_out" => 0,
                "total_alarms" => count($alarms_list)
            ];

            foreach ($raw_selected as $hid => $interfaces) {
                $h_info = $hosts_map[$hid] ?? [
                    "hostid" => $hid,
                    "name" => "Host " . $hid,
                    "host" => "Host " . $hid,
                    "ip" => "",
                    "status" => "0",
                    "status_label" => "Desconocido",
                    "groups" => []
                ];

                $host_interfaces = [];

                foreach ($interfaces as $ifname => $if_data) {
                    $summary_kpis["total_interfaces"]++;

                    $alias = $if_data["alias"] ?? "";
                    $speed = (float)($if_data["speed"]["lastvalue"] ?? 1000000000);
                    if ($speed <= 0) $speed = 1000000000;

                    $tin_id = $if_data["traffic_in"]["itemid"] ?? null;
                    $tout_id = $if_data["traffic_out"]["itemid"] ?? null;
                    $ein_id = $if_data["errors_in"]["itemid"] ?? null;
                    $eout_id = $if_data["errors_out"]["itemid"] ?? null;

                    $obs_key = $hid . "_" . $ifname;
                    $interface_obs = $raw_obs[$obs_key] ?? ($if_data["observation"] ?? "");

                    $hourly_labels = [];
                    $hourly_in_avg = [];
                    $hourly_in_max = [];
                    $hourly_out_avg = [];
                    $hourly_out_max = [];
                    $hourly_ein = [];
                    $hourly_eout = [];

                    $daily_map = [];

                    $start_hour = floor($time_from / 3600) * 3600;
                    $end_hour = floor($time_till / 3600) * 3600;

                    $tot_in_max = 0;
                    $tot_out_max = 0;
                    $sum_in_avg = 0;
                    $sum_out_avg = 0;
                    $count_points = 0;
                    $sum_ein = 0;
                    $sum_eout = 0;

                    for ($curr = $start_hour; $curr <= $end_hour; $curr += 3600) {
                        $label = date("m-d H:00", $curr);
                        $day_str = date("Y-m-d", $curr);

                        $in_avg_val = isset($trends_by_item[$tin_id][$curr]) ? $trends_by_item[$tin_id][$curr]["avg"] : 0;
                        $in_max_val = isset($trends_by_item[$tin_id][$curr]) ? $trends_by_item[$tin_id][$curr]["max"] : 0;
                        $out_avg_val = isset($trends_by_item[$tout_id][$curr]) ? $trends_by_item[$tout_id][$curr]["avg"] : 0;
                        $out_max_val = isset($trends_by_item[$tout_id][$curr]) ? $trends_by_item[$tout_id][$curr]["max"] : 0;

                        $ein_val = isset($trends_by_item[$ein_id][$curr]) ? $trends_by_item[$ein_id][$curr]["max"] : 0;
                        $eout_val = isset($trends_by_item[$eout_id][$curr]) ? $trends_by_item[$eout_id][$curr]["max"] : 0;

                        $in_avg_mbps = round($in_avg_val / 1000000, 3);
                        $in_max_mbps = round($in_max_val / 1000000, 3);
                        $out_avg_mbps = round($out_avg_val / 1000000, 3);
                        $out_max_mbps = round($out_max_val / 1000000, 3);

                        $hourly_labels[] = $label;
                        $hourly_in_avg[] = $in_avg_mbps;
                        $hourly_in_max[] = $in_max_mbps;
                        $hourly_out_avg[] = $out_avg_mbps;
                        $hourly_out_max[] = $out_max_mbps;
                        $hourly_ein[] = (int)$ein_val;
                        $hourly_eout[] = (int)$eout_val;

                        $tot_in_max = max($tot_in_max, $in_max_val);
                        $tot_out_max = max($tot_out_max, $out_max_val);
                        $sum_in_avg += $in_avg_val;
                        $sum_out_avg += $out_avg_val;
                        $sum_ein += $ein_val;
                        $sum_eout += $eout_val;
                        $count_points++;

                        if (!isset($daily_map[$day_str])) {
                            $daily_map[$day_str] = [
                                "date" => $day_str,
                                "date_formatted" => date("d/m/Y", strtotime($day_str)),
                                "in_sum" => 0,
                                "in_max" => 0,
                                "out_sum" => 0,
                                "out_max" => 0,
                                "count" => 0,
                                "errors_in" => 0,
                                "errors_out" => 0
                            ];
                        }
                        $daily_map[$day_str]["in_sum"] += $in_avg_val;
                        $daily_map[$day_str]["in_max"] = max($daily_map[$day_str]["in_max"], $in_max_val);
                        $daily_map[$day_str]["out_sum"] += $out_avg_val;
                        $daily_map[$day_str]["out_max"] = max($daily_map[$day_str]["out_max"], $out_max_val);
                        $daily_map[$day_str]["errors_in"] += $ein_val;
                        $daily_map[$day_str]["errors_out"] += $eout_val;
                        $daily_map[$day_str]["count"]++;

                        if (!isset($consolidated_days[$day_str])) {
                            $consolidated_days[$day_str] = [
                                "date" => $day_str,
                                "date_formatted" => date("d/m/Y", strtotime($day_str)),
                                "in_sum" => 0,
                                "in_max" => 0,
                                "out_sum" => 0,
                                "out_max" => 0,
                                "errors_in" => 0,
                                "errors_out" => 0,
                                "points" => 0
                            ];
                        }
                        $consolidated_days[$day_str]["in_sum"] += $in_avg_val;
                        $consolidated_days[$day_str]["in_max"] = max($consolidated_days[$day_str]["in_max"], $in_max_val);
                        $consolidated_days[$day_str]["out_sum"] += $out_avg_val;
                        $consolidated_days[$day_str]["out_max"] = max($consolidated_days[$day_str]["out_max"], $out_max_val);
                        $consolidated_days[$day_str]["errors_in"] += $ein_val;
                        $consolidated_days[$day_str]["errors_out"] += $eout_val;
                        $consolidated_days[$day_str]["points"]++;

                        if (!isset($consolidated_hours[$label])) {
                            $consolidated_hours[$label] = [
                                "label" => $label,
                                "in_avg_mbps" => 0,
                                "in_max_mbps" => 0,
                                "out_avg_mbps" => 0,
                                "out_max_mbps" => 0,
                                "errors_in" => 0,
                                "errors_out" => 0
                            ];
                        }
                        $consolidated_hours[$label]["in_avg_mbps"] += $in_avg_mbps;
                        $consolidated_hours[$label]["in_max_mbps"] += $in_max_mbps;
                        $consolidated_hours[$label]["out_avg_mbps"] += $out_avg_mbps;
                        $consolidated_hours[$label]["out_max_mbps"] += $out_max_mbps;
                        $consolidated_hours[$label]["errors_in"] += (int)$ein_val;
                        $consolidated_hours[$label]["errors_out"] += (int)$eout_val;
                    }

                    $daily_rows = [];
                    foreach ($daily_map as $d => $dval) {
                        $c = max(1, $dval["count"]);
                        $avg_in = $dval["in_sum"] / $c;
                        $avg_out = $dval["out_sum"] / $c;
                        $daily_rows[] = [
                            "date" => $dval["date"],
                            "date_formatted" => $dval["date_formatted"],
                            "in_avg_mbps" => round($avg_in / 1000000, 2),
                            "in_max_mbps" => round($dval["in_max"] / 1000000, 2),
                            "out_avg_mbps" => round($avg_out / 1000000, 2),
                            "out_max_mbps" => round($dval["out_max"] / 1000000, 2),
                            "errors_in" => (int)$dval["errors_in"],
                            "errors_out" => (int)$dval["errors_out"],
                            "usage_pct_in" => round(($dval["in_max"] / $speed) * 100, 2),
                            "usage_pct_out" => round(($dval["out_max"] / $speed) * 100, 2)
                        ];
                    }

                    $avg_in_tot = ($count_points > 0) ? ($sum_in_avg / $count_points) : 0;
                    $avg_out_tot = ($count_points > 0) ? ($sum_out_avg / $count_points) : 0;

                    $summary_kpis["peak_traffic_in"] = max($summary_kpis["peak_traffic_in"], $tot_in_max);
                    $summary_kpis["peak_traffic_out"] = max($summary_kpis["peak_traffic_out"], $tot_out_max);
                    $summary_kpis["total_errors_in"] += $sum_ein;
                    $summary_kpis["total_errors_out"] += $sum_eout;

                    $host_interfaces[] = [
                        "ifname" => $ifname,
                        "alias" => $alias,
                        "speed" => $speed,
                        "speed_formatted" => format_bitrate($speed),
                        "observation" => $interface_obs,
                        "totals" => [
                            "avg_in_mbps" => round($avg_in_tot / 1000000, 2),
                            "max_in_mbps" => round($tot_in_max / 1000000, 2),
                            "avg_out_mbps" => round($avg_out_tot / 1000000, 2),
                            "max_out_mbps" => round($tot_out_max / 1000000, 2),
                            "total_errors_in" => (int)$sum_ein,
                            "total_errors_out" => (int)$sum_eout,
                            "usage_pct_in" => round(($tot_in_max / $speed) * 100, 2),
                            "usage_pct_out" => round(($tot_out_max / $speed) * 100, 2)
                        ],
                        "hourly" => [
                            "labels" => $hourly_labels,
                            "in_avg" => $hourly_in_avg,
                            "in_max" => $hourly_in_max,
                            "out_avg" => $hourly_out_avg,
                            "out_max" => $hourly_out_max,
                            "in_errors" => $hourly_ein,
                            "out_errors" => $hourly_eout
                        ],
                        "daily" => $daily_rows
                    ];
                }

                $processed_hosts[] = [
                    "hostid" => $hid,
                    "name" => $h_info["name"],
                    "host" => $h_info["host"],
                    "ip" => $h_info["ip"],
                    "status" => $h_info["status"],
                    "status_label" => $h_info["status_label"],
                    "groups" => $h_info["groups"],
                    "interfaces" => $host_interfaces
                ];
            }

            $consolidated_daily_rows = [];
            foreach ($consolidated_days as $cd) {
                $c = max(1, $cd["points"]);
                $consolidated_daily_rows[] = [
                    "date" => $cd["date"],
                    "date_formatted" => $cd["date_formatted"],
                    "in_avg_mbps" => round(($cd["in_sum"] / $c) / 1000000, 2),
                    "in_max_mbps" => round($cd["in_max"] / 1000000, 2),
                    "out_avg_mbps" => round(($cd["out_sum"] / $c) / 1000000, 2),
                    "out_max_mbps" => round($cd["out_max"] / 1000000, 2),
                    "errors_in" => (int)$cd["errors_in"],
                    "errors_out" => (int)$cd["errors_out"]
                ];
            }

            $summary_kpis["peak_traffic_in_formatted"] = format_bitrate($summary_kpis["peak_traffic_in"]);
            $summary_kpis["peak_traffic_out_formatted"] = format_bitrate($summary_kpis["peak_traffic_out"]);

            // 6. Análisis con Inteligencia Artificial
            $ai_model_used = "";
            $ai_analysis_text = "";

            if ($ai_provider !== "none") {
                $pdo = getPDO();
                $stmt = $pdo->query("SELECT * FROM app_ai_config ORDER BY id DESC LIMIT 1");
                $db_cfg = $stmt->fetch(PDO::FETCH_ASSOC);

                $prompt_payload = build_ai_prompt(
                    $title,
                    $date_from_str,
                    $date_to_str,
                    $processed_hosts,
                    $alarms_list,
                    $summary_kpis,
                    $general_obs
                );

                if ($ai_provider === "gemini") {
                    $key = $db_cfg["gemini_api_key"] ?? "";
                    $ai_model_used = $db_cfg["gemini_model"] ?? "gemini-1.5-flash";
                    if (!empty($key)) {
                        $ai_res = execute_gemini_request($prompt_payload, $key, $ai_model_used);
                        if ($ai_res["success"]) {
                            $ai_analysis_text = $ai_res["text"];
                        }
                    }
                } elseif ($ai_provider === "ollama") {
                    $url = $db_cfg["ollama_url"] ?? "http://localhost:11434";
                    $ai_model_used = $db_cfg["ollama_model"] ?? "llama3:latest";
                    $ai_res = execute_ollama_request($prompt_payload, $url, $ai_model_used);
                    if ($ai_res["success"]) {
                        $ai_analysis_text = $ai_res["text"];
                    }
                }

                if (empty($ai_analysis_text)) {
                    $ai_model_used = "Motor Analítico Experto SYNAPSE (Reglas Heurísticas)";
                    $ai_analysis_text = generate_expert_heuristic_analysis(
                        $title,
                        $processed_hosts,
                        $alarms_list,
                        $summary_kpis
                    );
                }
            }

            $compiled_report = [
                "report_id" => null,
                "title" => $title,
                "period" => [
                    "from" => $date_from_str,
                    "to" => $date_to_str,
                    "from_formatted" => date("d/m/Y H:i", $time_from),
                    "to_formatted" => date("d/m/Y H:i", $time_till)
                ],
                "generated_at" => date("Y-m-d H:i:s"),
                "generated_at_formatted" => date("d/m/Y H:i:s"),
                "generated_by" => current_user()["username"] ?? "Administrador",
                "ai_provider" => $ai_provider,
                "ai_model" => $ai_model_used,
                "ai_analysis" => $ai_analysis_text,
                "general_observations" => $general_obs,
                "report_type" => $report_type,
                "summary_kpis" => $summary_kpis,
                "consolidated" => [
                    "daily" => $consolidated_daily_rows,
                    "hourly_labels" => array_keys($consolidated_hours),
                    "hourly_in_avg" => array_column($consolidated_hours, "in_avg_mbps"),
                    "hourly_in_max" => array_column($consolidated_hours, "in_max_mbps"),
                    "hourly_out_avg" => array_column($consolidated_hours, "out_avg_mbps"),
                    "hourly_out_max" => array_column($consolidated_hours, "out_max_mbps"),
                    "hourly_errors_in" => array_column($consolidated_hours, "errors_in"),
                    "hourly_errors_out" => array_column($consolidated_hours, "errors_out")
                ],
                "hosts" => $processed_hosts,
                "alarms" => $alarms_list
            ];

            // 7. Guardar en Base de Datos si fue solicitado
            if ($save_report) {
                $pdo = getPDO();
                $ins_stmt = $pdo->prepare("INSERT INTO custom_zabbix_reports 
                    (title, created_by, date_from, date_to, groups_data, hosts_data, interfaces_data, report_type, ai_provider, ai_model, ai_analysis, general_observations, interface_observations, compiled_data)
                    VALUES 
                    (:title, :by, :df, :dt, :groups, :hosts, :ifaces, :rtype, :aip, :aim, :aia, :gobs, :iobs, :cdata)");

                $ins_stmt->execute([
                    ":title" => $title,
                    ":by" => current_user()["username"] ?? "admin",
                    ":df" => $date_from_str,
                    ":dt" => $date_to_str,
                    ":groups" => json_encode($_POST["selected_groups"] ?? []),
                    ":hosts" => json_encode($hostids),
                    ":ifaces" => json_encode($raw_selected),
                    ":rtype" => $report_type,
                    ":aip" => $ai_provider,
                    ":aim" => $ai_model_used,
                    ":aia" => $ai_analysis_text,
                    ":gobs" => $general_obs,
                    ":iobs" => json_encode($raw_obs),
                    ":cdata" => json_encode($compiled_report)
                ]);

                $compiled_report["report_id"] = $pdo->lastInsertId();
            }

            echo json_encode(["success" => true, "data" => $compiled_report]);
            break;

        // -------------------------------------------------------------
        // 6. Listado de Informes Guardados (Historial)
        // -------------------------------------------------------------
        case "list_saved":
            $pdo = getPDO();
            $stmt = $pdo->query("SELECT id, title, created_at, created_by, date_from, date_to, report_type, ai_provider, ai_model 
                                 FROM custom_zabbix_reports 
                                 ORDER BY id DESC LIMIT 50");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r["created_at_formatted"] = date("d/m/Y H:i", strtotime($r["created_at"]));
                $r["period_formatted"] = date("d/m/Y", strtotime($r["date_from"])) . " al " . date("d/m/Y", strtotime($r["date_to"]));
            }
            echo json_encode(["success" => true, "data" => $rows]);
            break;

        // -------------------------------------------------------------
        // 7. Cargar Informe Guardado por ID
        // -------------------------------------------------------------
        case "get_saved":
            $id = (int)($_REQUEST["id"] ?? 0);
            if (!$id) {
                echo json_encode(["success" => false, "error" => "ID inválido"]);
                break;
            }
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT * FROM custom_zabbix_reports WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(["success" => false, "error" => "Informe no encontrado"]);
                break;
            }

            $compiled = json_decode($row["compiled_data"], true);
            if (!$compiled) {
                echo json_encode(["success" => false, "error" => "No se pudo decodificar el informe guardado"]);
                break;
            }
            $compiled["report_id"] = $row["id"];
            $compiled["saved_db"] = true;

            echo json_encode(["success" => true, "data" => $compiled]);
            break;

        // -------------------------------------------------------------
        // 8. Eliminar Informe Guardado
        // -------------------------------------------------------------
        case "delete_saved":
            $id = (int)($_POST["id"] ?? 0);
            if (!$id) {
                echo json_encode(["success" => false, "error" => "ID inválido"]);
                break;
            }
            $pdo = getPDO();
            $stmt = $pdo->prepare("DELETE FROM custom_zabbix_reports WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["success" => true, "message" => "Informe eliminado correctamente"]);
            break;

        // -------------------------------------------------------------
        // 9. Actualizar Observaciones de un Informe Guardado
        // -------------------------------------------------------------
        case "update_observations":
            $id = (int)($_POST["id"] ?? 0);
            $general_obs = trim($_POST["general_observations"] ?? "");
            if (!$id) {
                echo json_encode(["success" => false, "error" => "ID de informe inválido"]);
                break;
            }

            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT compiled_data FROM custom_zabbix_reports WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(["success" => false, "error" => "Informe no encontrado"]);
                break;
            }

            $compiled = json_decode($row["compiled_data"], true) ?: [];
            $compiled["general_observations"] = $general_obs;

            $up_stmt = $pdo->prepare("UPDATE custom_zabbix_reports SET general_observations = :go, compiled_data = :cd WHERE id = :id");
            $up_stmt->execute([
                ":go" => $general_obs,
                ":cd" => json_encode($compiled),
                ":id" => $id
            ]);

            echo json_encode(["success" => true, "message" => "Observaciones actualizadas exitosamente"]);
            break;

        default:
            echo json_encode(["success" => false, "error" => "Acción \"" . $action . "\" no reconocida"]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Excepción en el servidor: " . $e->getMessage()]);
}

// =====================================================================
// FUNCIONES DE APOYO Y CONEXIÓN CON IA
// =====================================================================

function execute_gemini_request($prompt, $api_key, $model = "gemini-1.5-flash") {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . urlencode($api_key);
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3,
            "maxOutputTokens" => 2500
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ["success" => false, "error" => "Error cURL con Gemini: " . $err];
    }

    if ($status >= 400) {
        $data = json_decode($resp, true);
        $msg = $data["error"]["message"] ?? "HTTP Status " . $status;
        return ["success" => false, "error" => "Google Gemini API Error: " . $msg];
    }

    $decoded = json_decode($resp, true);
    $text = $decoded["candidates"][0]["content"]["parts"][0]["text"] ?? "";
    if (empty($text)) {
        return ["success" => false, "error" => "Respuesta vacía recibida desde Google Gemini"];
    }

    return ["success" => true, "text" => $text];
}

function execute_ollama_request($prompt, $endpoint = "http://localhost:11434", $model = "llama3:latest") {
    $url = rtrim($endpoint, "/") . "/api/generate";
    $payload = [
        "model" => $model,
        "prompt" => $prompt,
        "stream" => false,
        "options" => [
            "temperature" => 0.3
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ["success" => false, "error" => "Error al conectar con Ollama en " . $endpoint . ": " . $err];
    }

    if ($status >= 400) {
        return ["success" => false, "error" => "Ollama respondió con código de estado HTTP " . $status];
    }

    $decoded = json_decode($resp, true);
    $text = $decoded["response"] ?? "";
    if (empty($text)) {
        return ["success" => false, "error" => "Respuesta vacía recibida desde Ollama"];
    }

    return ["success" => true, "text" => $text];
}

function build_ai_prompt($title, $from, $to, $hosts, $alarms, $kpis, $user_obs) {
    $summary = "INFORME: " . $title . "\n";
    $summary .= "PERÍODO ANALIZADO: " . $from . " al " . $to . "\n";
    $summary .= "TOTAL EQUIPOS: " . $kpis["total_hosts"] . " | TOTAL INTERFACES: " . $kpis["total_interfaces"] . "\n";
    $summary .= "PICO TRÁFICO IN: " . $kpis["peak_traffic_in_formatted"] . " | PICO TRÁFICO OUT: " . $kpis["peak_traffic_out_formatted"] . "\n";
    $summary .= "TOTAL ERRORES IN: " . $kpis["total_errors_in"] . " | TOTAL ERRORES OUT: " . $kpis["total_errors_out"] . "\n";
    $summary .= "TOTAL ALARMAS EN EL PERIODO: " . $kpis["total_alarms"] . "\n\n";

    if (!empty($user_obs)) {
        $summary .= "OBSERVACIONES GENERALES DEL OPERADOR:\n\"" . $user_obs . "\"\n\n";
    }

    $summary .= "DETALLE DE EQUIPOS E INTERFACES:\n";
    foreach ($hosts as $h) {
        $summary .= "- Host: " . $h["name"] . " (IP: " . $h["ip"] . ", Estado: " . $h["status_label"] . ")\n";
        foreach ($h["interfaces"] as $if) {
            $summary .= "  * Interfaz: " . $if["ifname"] . " (Alias: " . $if["alias"] . ", Capacidad: " . $if["speed_formatted"] . ")\n";
            $summary .= "    - Tráfico In: Promedio " . $if["totals"]["avg_in_mbps"] . " Mbps, Pico " . $if["totals"]["max_in_mbps"] . " Mbps (Utilización Pico: " . $if["totals"]["usage_pct_in"] . "%)\n";
            $summary .= "    - Tráfico Out: Promedio " . $if["totals"]["avg_out_mbps"] . " Mbps, Pico " . $if["totals"]["max_out_mbps"] . " Mbps (Utilización Pico: " . $if["totals"]["usage_pct_out"] . "%)\n";
            $summary .= "    - Errores de Transmisión: Inbound=" . $if["totals"]["total_errors_in"] . ", Outbound=" . $if["totals"]["total_errors_out"] . "\n";
            if (!empty($if["observation"])) {
                $summary .= "    - Nota Interfaz: \"" . $if["observation"] . "\"\n";
            }
        }
    }

    if (!empty($alarms)) {
        $summary .= "\nALARMAS Y EVENTOS REGISTRADOS:\n";
        foreach (array_slice($alarms, 0, 15) as $a) {
            $summary .= "- [" . $a["date_formatted"] . "] [Severidad: " . $a["severity_name"] . "] " . $a["name"] . " (Duración: " . $a["duration"] . ")\n";
        }
    } else {
        $summary .= "\nALARMAS: No se registraron incidentes ni alarmas durante el período seleccionado.\n";
    }

    $instructions = "
Eres un Ingeniero Principal de Redes y Telecomunicaciones (NOC/CCIE) para la infraestructura tecnológica de SONDA / SYNAPSE.
Con base en los datos telemétricos de Zabbix presentados arriba, redacta un ANÁLISIS TÉCNICO Y EJECUTIVO estructurado en Markdown con las siguientes secciones:

### 1. Resumen Ejecutivo
(Diagnóstico general del estado operativo de los equipos y enlaces monitoreados).

### 2. Análisis de Rendimiento y Capacidad de Tráfico
(Identificación de patrones de consumo, simetría/asimetría de enlaces, cuellos de botella e interfaces con mayor saturación).

### 3. Calidad de Transmisión y Tasa de Errores
(Evaluación de pérdidas de paquetes, descartes, tramas erróneas y su impacto en la capa de transporte).

### 4. Correlación de Alarmas e Incidentes Zabbix
(Correlación entre picos de tráfico, cortes de enlace, caídas de velocidad o flapping detectados).

### 5. Recomendaciones Técnicas y Plan de Remediación
(Acciones correctivas y preventivas priorizadas: QoS, ampliación de ancho de banda, revisión de cableado/SFPs o reconfiguración).

Usa un tono profesional, técnico, conciso y basado rigurosamente en los datos numéricos provistos. Responde en español.";

    return $summary . "\n" . $instructions;
}

function generate_expert_heuristic_analysis($title, $hosts, $alarms, $kpis) {
    $out = "### 1. Resumen Ejecutivo\n";
    $out .= "Durante el período evaluado, se monitorearon **" . $kpis["total_hosts"] . " equipos** y **" . $kpis["total_interfaces"] . " interfaces de red**. ";
    
    $max_peak = max($kpis["peak_traffic_in"], $kpis["peak_traffic_out"]);
    $max_formatted = format_bitrate($max_peak);
    $out .= "La demanda máxima registrada en la infraestructura alcanzó los **" . $max_formatted . "**. ";

    $tot_errors = $kpis["total_errors_in"] + $kpis["total_errors_out"];
    if ($tot_errors == 0) {
        $out .= "Los enlaces presentaron una integridad de transmisión óptima con **0 errores de capa física o enlace** registrados.\n\n";
    } else {
        $out .= "Se detectaron un total acumulado de **" . $tot_errors . " paquetes con error**, lo cual amerita revisión de capa física y negociación de puertos.\n\n";
    }

    $out .= "### 2. Análisis de Rendimiento y Capacidad de Tráfico\n";
    $saturated_links = [];
    foreach ($hosts as $h) {
        foreach ($h["interfaces"] as $if) {
            $max_usage = max($if["totals"]["usage_pct_in"], $if["totals"]["usage_pct_out"]);
            if ($max_usage >= 75) {
                $saturated_links[] = $h["name"] . " - " . $if["ifname"] . " (" . $max_usage . "% de utilización pico)";
            }
        }
    }

    if (!empty($saturated_links)) {
        $out .= "- **Advertencia de Saturación**: Se identificaron interfaces que superan el umbral recomendado de utilización (75%):\n";
        foreach ($saturated_links as $sl) {
            $out .= "  * " . $sl . "\n";
        }
        $out .= "Se recomienda evaluar la ampliación del ancho de banda o balanceo dinámico de carga.\n\n";
    } else {
        $out .= "- **Capacidad Holgada**: Todos los enlaces evaluados operan dentro de los umbrales seguros (<75% de ancho de banda asignado). No se observan cuellos de botella significativos que degraden los servicios corporativos.\n\n";
    }

    $out .= "### 3. Calidad de Transmisión y Tasa de Errores\n";
    if ($tot_errors > 0) {
        $out .= "- Se evidencia presencia de tramas con error en recepción/transmisión. En enlaces GigabitEthernet/Fibra, errores recurrentes suelen asociarse a atenuación óptica en jumpers, suciedad en conectores LC, o desajustes de duplex/speed en interfaces cobre.\n\n";
    } else {
        $out .= "- Cero errores de CRC, descartes o fallas de trama detectados. La calidad de la señal y la negociación en los puertos son consistentes y estables.\n\n";
    }

    $out .= "### 4. Correlación de Alarmas e Incidentes Zabbix\n";
    if (!empty($alarms)) {
        $alarm_count = count($alarms);
        $out .= "- Se contabilizaron **" . $alarm_count . " eventos/alarmas** en la plataforma Zabbix durante la ventana de tiempo. Se destacan eventos de alteración de velocidad o caídas de enlace (Link Down) que coinciden temporalmente con fluctuaciones de tráfico.\n\n";
    } else {
        $out .= "- No se reportaron alarmas activas ni eventos críticos de disponibilidad en los dispositivos analizados durante el período.\n\n";
    }

    $out .= "### 5. Recomendaciones Técnicas y Plan de Remediación\n";
    $out .= "1. **Monitoreo Proactivo de Tendencias**: Mantener la recolección horaria de trends en Zabbix para proyectar crecimiento de tráfico a 30 y 90 días.\n";
    $out .= "2. **Revisión de Interfaces Saturadas**: En los enlaces con picos superiores al 70%, validar políticas de QoS (Quality of Service) para priorizar tráfico crítico de negocio.\n";
    if ($tot_errors > 0) {
        $out .= "3. **Inspección Física de Puertos**: Realizar diagnóstico DDM/DOM en transceptores ópticos y verificar estado de parcheo de red para mitigar la tasa de errores detectada.\n";
    }
    $out .= "4. **Alineación con Ventanas de Mantenimiento**: Programar verificación preventiva de firmware y logs de buffer en equipos que registraron alertas.";

    return $out;
}

function format_bitrate($bps) {
    $bps = (float)$bps;
    if ($bps >= 1000000000) {
        return round($bps / 1000000000, 2) . " Gbps";
    } elseif ($bps >= 1000000) {
        return round($bps / 1000000, 2) . " Mbps";
    } elseif ($bps >= 1000) {
        return round($bps / 1000, 2) . " Kbps";
    }
    return round($bps, 2) . " bps";
}

function format_seconds_duration($seconds) {
    $seconds = (int)$seconds;
    if ($seconds < 60) return $seconds . "s";
    $mins = floor($seconds / 60);
    $rem_secs = $seconds % 60;
    if ($mins < 60) return $mins . "m " . $rem_secs . "s";
    $hours = floor($mins / 60);
    $rem_mins = $mins % 60;
    if ($hours < 24) return $hours . "h " . $rem_mins . "m";
    $days = floor($hours / 24);
    $rem_hours = $hours % 24;
    return $days . "d " . $rem_hours . "h";
}
