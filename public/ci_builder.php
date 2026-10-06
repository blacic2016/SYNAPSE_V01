<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();

$cat_id = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$ci_id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ci_id > 0) {
    header("Location: ci_list.php?show_ci_id=" . $ci_id . "&action=edit");
} else {
    header("Location: ci_list.php?action=create" . ($cat_id > 0 ? "&category_id=" . $cat_id : ""));
}
exit;
