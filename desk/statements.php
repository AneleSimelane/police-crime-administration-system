<?php
// Legacy redirect: desk/statements.php → cases/cases.php (statement management is now in case_details)
$qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../cases/case_details.php' . $qs);
exit;
