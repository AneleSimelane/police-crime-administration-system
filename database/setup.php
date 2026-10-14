<?php
// Eswatini Police CAS Database Setup and Seeding Script

require_once __DIR__ . '/../config/database.php';

try {
    // 1. Connect to MySQL without database parameter first
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // 2. Create Database
    echo "Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "Database created or verified.\n";
    
    // 3. Connect to the specific database
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // 4. Load schema.sql and execute
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("Error: schema.sql file not found at " . $schemaFile . "\n");
    }
    
    echo "Executing schema.sql...\n";
    $sql = file_get_contents($schemaFile);
    
    // Use exec since it contains multiple queries
    $pdo->exec($sql);
    echo "Database tables created successfully.\n";
    
    // 5. Seed Stations
    echo "Seeding police stations...\n";
    $stations = [
        ['Mbabane Police Station', 'Mbabane Central, Hospital Hill', 'Hhohho', -26.305537, 31.136724],
        ['Manzini Police Station', 'Manzini Central, Ngwane St', 'Manzini', -26.491378, 31.371298],
        ['Piggs Peak Police Station', 'Piggs Peak Central, Evelyn Baring Rd', 'Hhohho', -25.961054, 31.250000],
        ['Nhlangano Police Station', 'Nhlangano Central, 5th Ave', 'Shiselweni', -27.116670, 31.200000],
        ['Siteki Police Station', 'Siteki Central, Jacaranda St', 'Lubombo', -26.450000, 31.950000]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `stations` (`name`, `location`, `region`, `latitude`, `longitude`) VALUES (?, ?, ?, ?, ?)");
    foreach ($stations as $station) {
        $stmt->execute($station);
    }
    
    // 6. Seed Departments
    echo "Seeding departments...\n";
    $departments = [
        ['General Investigation Department'],
        ['Serious Crimes Unit'],
        ['Domestic Violence & FCS Unit'],
        ['Fraud & Commercial Crimes Unit'],
        ['Traffic Department']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `departments` (`name`) VALUES (?)");
    foreach ($departments as $dept) {
        $stmt->execute($dept);
    }
    
    // 7. Seed Crime Types linked to default departments
    echo "Seeding crime types and setting default routing...\n";
    // Get department IDs first
    $deptIDs = [];
    $res = $pdo->query("SELECT id, name FROM departments")->fetchAll();
    foreach ($res as $row) {
        $deptIDs[$row['name']] = $row['id'];
    }
    
    $crimeTypes = [
        ['Theft / Robbery', $deptIDs['General Investigation Department']],
        ['Murder / Assault GBH', $deptIDs['Serious Crimes Unit']],
        ['Domestic Violence / Abuse', $deptIDs['Domestic Violence & FCS Unit']],
        ['Fraud / Cybercrime', $deptIDs['Fraud & Commercial Crimes Unit']],
        ['Traffic Offence', $deptIDs['Traffic Department']],
        ['Housebreaking / Burglary', $deptIDs['General Investigation Department']]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `crime_types` (`name`, `default_department_id`) VALUES (?, ?)");
    foreach ($crimeTypes as $ct) {
        $stmt->execute($ct);
    }
    
    // Get Station IDs
    $stationIDs = [];
    $res = $pdo->query("SELECT id, name FROM stations")->fetchAll();
    foreach ($res as $row) {
        $stationIDs[$row['name']] = $row['id'];
    }
    
    // 8. Seed Default Users for each role
    echo "Seeding default users...\n";
    $users = [
        // 1. Admin (Station Commander)
        [
            'role' => 'admin',
            'name' => 'Station Commander Khumalo',
            'email' => 'admin@police.gov.sz',
            'password' => password_hash('Admin@123', PASSWORD_DEFAULT),
            'phone' => '+268 7602 1111',
            'address' => 'Mbabane Station Police Quarters',
            'force_number' => 'F1001',
            'station_id' => $stationIDs['Mbabane Police Station'],
            'department_id' => $deptIDs['General Investigation Department'],
            'occupation' => null, 'employer' => null, 'work_phone' => null, 'dob' => null, 'nationality' => null, 'chief' => null, 'indvuna' => null, 'region' => null
        ],
        // 2. Head of Department (HOD)
        [
            'role' => 'hod',
            'name' => 'Inspector Mdluli',
            'email' => 'hod@police.gov.sz',
            'password' => password_hash('Hod@123', PASSWORD_DEFAULT),
            'phone' => '+268 7602 2222',
            'address' => 'Mbabane Police Barracks',
            'force_number' => 'F2001',
            'station_id' => $stationIDs['Mbabane Police Station'],
            'department_id' => $deptIDs['Serious Crimes Unit'],
            'occupation' => null, 'employer' => null, 'work_phone' => null, 'dob' => null, 'nationality' => null, 'chief' => null, 'indvuna' => null, 'region' => null
        ],
        // 3. Police Officer (Investigator)
        [
            'role' => 'officer',
            'name' => 'Constable Gamedze',
            'email' => 'officer@police.gov.sz',
            'password' => password_hash('Officer@123', PASSWORD_DEFAULT),
            'phone' => '+268 7602 3333',
            'address' => 'Mbabane Police Barracks Section B',
            'force_number' => 'F3001',
            'station_id' => $stationIDs['Mbabane Police Station'],
            'department_id' => $deptIDs['Serious Crimes Unit'],
            'occupation' => null, 'employer' => null, 'work_phone' => null, 'dob' => null, 'nationality' => null, 'chief' => null, 'indvuna' => null, 'region' => null
        ],
        // 4. Desk Officer
        [
            'role' => 'desk_officer',
            'name' => 'Sergeant Shongwe',
            'email' => 'desk@police.gov.sz',
            'password' => password_hash('Desk@123', PASSWORD_DEFAULT),
            'phone' => '+268 7602 4444',
            'address' => 'Mbabane Police Quarters Front Office',
            'force_number' => 'F4001',
            'station_id' => $stationIDs['Mbabane Police Station'],
            'department_id' => $deptIDs['General Investigation Department'],
            'occupation' => null, 'employer' => null, 'work_phone' => null, 'dob' => null, 'nationality' => null, 'chief' => null, 'indvuna' => null, 'region' => null
        ],
        // 5. Citizen (Complainant)
        [
            'role' => 'citizen',
            'name' => 'Sibusiso Dlamini',
            'email' => 'citizen@gmail.com',
            'password' => password_hash('Citizen@123', PASSWORD_DEFAULT),
            'phone' => '+268 7801 9876',
            'address' => 'Malagwane Hill, Mbabane',
            'force_number' => null,
            'station_id' => null,
            'department_id' => null,
            'occupation' => 'Teacher',
            'employer' => 'Ministry of Education',
            'work_phone' => '+268 2404 1234',
            'dob' => '1990-06-15',
            'nationality' => 'Emaswati',
            'chief' => 'Chief Mndzebele',
            'indvuna' => 'Indvuna Masilela',
            'region' => 'Hhohho'
        ]
    ];
    
    $sqlUser = "INSERT INTO `users` (
        `role`, `name`, `email`, `password`, `phone`, `address`, 
        `force_number`, `station_id`, `department_id`, 
        `occupation`, `employer`, `work_phone`, `dob`, `nationality`, `chief`, `indvuna`, `region`
    ) VALUES (
        :role, :name, :email, :password, :phone, :address, 
        :force_number, :station_id, :department_id, 
        :occupation, :employer, :work_phone, :dob, :nationality, :chief, :indvuna, :region
    )";
    
    $stmtUser = $pdo->prepare($sqlUser);
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }
    
    echo "Database setup and seeding completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
