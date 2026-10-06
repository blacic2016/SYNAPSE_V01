<?php
/**
 * Datacenter Floor Plan 3D Viewer - Redirect to Unified 3DViewer
 */
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: viewer_3d.php" . $qs);
exit;
