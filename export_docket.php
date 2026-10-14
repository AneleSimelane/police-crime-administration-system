<?php
// Legacy redirect: root export_docket.php → reports/export_docket.php
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: reports/export_docket.php' . $qs);
exit;
