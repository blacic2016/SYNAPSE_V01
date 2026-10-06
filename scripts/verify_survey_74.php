<?php
require_once __DIR__ . '/../src/db.php';
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id, client, location, area, device_name, device_label, ports_count, rack, ur_rack, ports_data_json FROM manual_portmap_surveys WHERE id = 74');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Survey 74: " . $row['device_name'] . " | Rack: " . $row['rack'] . " | UR: " . $row['ur_rack'] . PHP_EOL;
$ports = json_decode($row['ports_data_json'], true);
echo "Ports count: " . count($ports) . PHP_EOL;
for ($i = 0; $i < 6; $i++) {
    echo "Port " . $ports[$i]['port_name'] . ": Link=" . $ports[$i]['link_status'] . " | EstadoLink=" . $ports[$i]['estado_link'] . " | Status=" . $ports[$i]['status'] . " | PP=" . $ports[$i]['patch_panel_src'] . " | Dest=" . $ports[$i]['dest_device'] . PHP_EOL;
}
