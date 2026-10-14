<?php
// Eswatini Police CAS Database Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eswatini_police_cas');

/**
 * Returns a PDO connection instance
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            try {
                $columnCheck = $pdo->query("SHOW COLUMNS FROM `cases` LIKE 'court_date'")->fetch();
                if (!$columnCheck) {
                    $pdo->exec("ALTER TABLE `cases` ADD COLUMN `court_date` DATETIME DEFAULT NULL AFTER `priority`");
                }
            } catch (PDOException $e) {
                // Ignore if the cases table is not available yet during initial setup.
            }
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}
