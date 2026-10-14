<?php
// Legacy redirect: citizen/my_cases.php → cases/cases.php (citizen case list is now in the unified cases module)
header('Location: ../cases/cases.php');
exit;
