<?php
require 'config/database.php';
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE `' . DB_NAME . '`');

$statements = [
    "CREATE TABLE stations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, location VARCHAR(255) NOT NULL, region ENUM('Hhohho','Manzini','Shiselweni','Lubombo') NOT NULL, latitude DECIMAL(10,8) DEFAULT NULL, longitude DECIMAL(11,8) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE departments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, role ENUM('citizen','officer','desk_officer','hod','admin') NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL UNIQUE, national_id VARCHAR(20) UNIQUE NULL, password VARCHAR(255) NOT NULL, phone VARCHAR(30) NOT NULL, address TEXT NOT NULL, force_number VARCHAR(50) DEFAULT NULL UNIQUE, station_id INT DEFAULT NULL, department_id INT DEFAULT NULL, occupation VARCHAR(100) DEFAULT NULL, employer VARCHAR(100) DEFAULT NULL, work_phone VARCHAR(30) DEFAULT NULL, dob DATE DEFAULT NULL, nationality VARCHAR(50) DEFAULT NULL, chief VARCHAR(100) DEFAULT NULL, indvuna VARCHAR(100) DEFAULT NULL, region VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE crime_types (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, default_department_id INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE cases (id INT AUTO_INCREMENT PRIMARY KEY, case_number VARCHAR(50) NOT NULL UNIQUE, complainant_id INT NOT NULL, reported_by_desk_officer_id INT DEFAULT NULL, crime_type_id INT NOT NULL, station_id INT NOT NULL, department_id INT NOT NULL, investigator_id INT DEFAULT NULL, title VARCHAR(150) NOT NULL, description TEXT NOT NULL, crime_date DATETIME NOT NULL, reported_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, latitude DECIMAL(10,8) DEFAULT NULL, longitude DECIMAL(11,8) DEFAULT NULL, status ENUM('pending_approval','pending_assignment','pending_investigator_acceptance','under_investigation','warrant_issued','arrest_made','court_referral','closed') DEFAULT 'pending_approval', priority ENUM('low','medium','high') DEFAULT 'low', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE statements (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, user_id INT NOT NULL, statement_text TEXT NOT NULL, recorded_by_id INT NOT NULL, status ENUM('pending_citizen_approval','approved','rejected') DEFAULT 'pending_citizen_approval', approved_at TIMESTAMP NULL DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE case_updates (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, officer_id INT NOT NULL, note TEXT NOT NULL, status_from VARCHAR(50) DEFAULT NULL, status_to VARCHAR(50) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE arrests (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, type ENUM('warrant','arrest') NOT NULL, suspect_name VARCHAR(100) NOT NULL, suspect_national_id VARCHAR(50) DEFAULT NULL, details TEXT NOT NULL, status ENUM('active','executed','cancelled') DEFAULT 'active', date_issued TIMESTAMP DEFAULT CURRENT_TIMESTAMP, date_executed DATETIME DEFAULT NULL, created_by_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE case_transfers (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, from_station_id INT NOT NULL, to_station_id INT NOT NULL, requested_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, reason TEXT NOT NULL, status ENUM('pending','approved','rejected') DEFAULT 'pending', transfer_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE evidence (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, file_type VARCHAR(50) NOT NULL, description TEXT NOT NULL, uploaded_by_id INT NOT NULL, uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE investigative_reports (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, officer_id INT NOT NULL, report_text TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE signatures (id INT AUTO_INCREMENT PRIMARY KEY, case_id INT NOT NULL, signer_id INT NOT NULL, role VARCHAR(50) DEFAULT NULL, file_path VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($statements as $idx => $sql) {
    try {
        $pdo->exec($sql);
        echo "created $idx\n";
    } catch (Throwable $e) {
        echo "failed $idx: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$alterStatements = [
    "ALTER TABLE users ADD CONSTRAINT fk_users_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL",
    "ALTER TABLE users ADD CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL",
    "ALTER TABLE crime_types ADD CONSTRAINT fk_crime_types_department FOREIGN KEY (default_department_id) REFERENCES departments(id) ON DELETE SET NULL",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_complainant FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_reported_by FOREIGN KEY (reported_by_desk_officer_id) REFERENCES users(id) ON DELETE SET NULL",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_crime_type FOREIGN KEY (crime_type_id) REFERENCES crime_types(id)",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_station FOREIGN KEY (station_id) REFERENCES stations(id)",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_department FOREIGN KEY (department_id) REFERENCES departments(id)",
    "ALTER TABLE cases ADD CONSTRAINT fk_cases_investigator FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL",
    "ALTER TABLE statements ADD CONSTRAINT fk_statements_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE statements ADD CONSTRAINT fk_statements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE statements ADD CONSTRAINT fk_statements_recorder FOREIGN KEY (recorded_by_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE case_updates ADD CONSTRAINT fk_case_updates_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE case_updates ADD CONSTRAINT fk_case_updates_officer FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE arrests ADD CONSTRAINT fk_arrests_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE arrests ADD CONSTRAINT fk_arrests_creator FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE case_transfers ADD CONSTRAINT fk_case_transfers_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE case_transfers ADD CONSTRAINT fk_case_transfers_from_station FOREIGN KEY (from_station_id) REFERENCES stations(id) ON DELETE CASCADE",
    "ALTER TABLE case_transfers ADD CONSTRAINT fk_case_transfers_to_station FOREIGN KEY (to_station_id) REFERENCES stations(id) ON DELETE CASCADE",
    "ALTER TABLE case_transfers ADD CONSTRAINT fk_case_transfers_requester FOREIGN KEY (requested_by_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE case_transfers ADD CONSTRAINT fk_case_transfers_approver FOREIGN KEY (approved_by_id) REFERENCES users(id) ON DELETE SET NULL",
    "ALTER TABLE evidence ADD CONSTRAINT fk_evidence_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE evidence ADD CONSTRAINT fk_evidence_uploader FOREIGN KEY (uploaded_by_id) REFERENCES users(id) ON DELETE CASCADE",
    "ALTER TABLE investigative_reports ADD CONSTRAINT fk_investigative_reports_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE investigative_reports ADD CONSTRAINT fk_investigative_reports_officer FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE SET NULL",
    "ALTER TABLE signatures ADD CONSTRAINT fk_signatures_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE",
    "ALTER TABLE signatures ADD CONSTRAINT fk_signatures_signer FOREIGN KEY (signer_id) REFERENCES users(id) ON DELETE CASCADE",
];
foreach ($alterStatements as $idx => $sql) {
    try {
        $pdo->exec($sql);
        echo "alter-ok $idx\n";
    } catch (Throwable $e) {
        echo "alter-fail $idx: " . $e->getMessage() . "\n";
        exit(2);
    }
}

echo "all-ok\n";
