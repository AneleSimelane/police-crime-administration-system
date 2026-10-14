<?php
// Legacy redirect: officer/docket.php → cases/case_details.php
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../cases/case_details.php' . $qs);
exit;
