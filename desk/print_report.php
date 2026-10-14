<?php
// Legacy redirect: desk/print_report.php → reports/print_report.php
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../reports/print_report.php' . $qs);
exit;
