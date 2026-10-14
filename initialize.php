<?php
// Eswatini Police CAS - Database Web Initializer
session_start();

$message = '';
$status = '';

if (isset($_POST['initialize'])) {
    require_once __DIR__ . '/config/database.php';
    
    try {
        // Connect to MySQL
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        
        // Read schema.sql
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("schema.sql file not found!");
        }
        
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);

        $courtDateCheck = $pdo->query("SHOW COLUMNS FROM `cases` LIKE 'court_date'")->fetch();
        if (!$courtDateCheck) {
            $pdo->exec("ALTER TABLE `cases` ADD COLUMN `court_date` DATETIME DEFAULT NULL AFTER `priority`");
        }
        
        // Seed Stations
        $stations = [
            ['Mbabane Police Station', 'Mbabane Central, Hospital Hill', 'Hhohho', -26.305537, 31.136724],
            ['Manzini Police Station', 'Manzini Central, Ngwane St', 'Manzini', -26.491378, 31.371298],
            ['Piggs Peak Police Station', 'Piggs Peak Central, Evelyn Baring Rd', 'Hhohho', -25.961054, 31.250000],
            ['Nhlangano Police Station', 'Nhlangano Central, 5th Ave', 'Shiselweni', -27.116670, 31.200000],
            ['Siteki Police Station', 'Siteki Central, Jacaranda St', 'Lubombo', -26.450000, 31.950000]
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO `stations` (`name`, `location`, `region`, `latitude`, `longitude`) VALUES (?, ?, ?, ?, ?)");
        foreach ($stations as $station) {
            $stmt->execute($station);
        }
        
        // Seed Departments
        $departments = [
            ['General Investigation Department'],
            ['Serious Crimes Unit'],
            ['Domestic Violence & FCS Unit'],
            ['Fraud & Commercial Crimes Unit'],
            ['Traffic Department']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO `departments` (`name`) VALUES (?)");
        foreach ($departments as $dept) {
            $stmt->execute($dept);
        }
        
        // Seed Crime Types
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
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO `crime_types` (`name`, `default_department_id`) VALUES (?, ?)");
        foreach ($crimeTypes as $ct) {
            $stmt->execute($ct);
        }
        
        // Seed Default Users
        $stationIDs = [];
        $res = $pdo->query("SELECT id, name FROM stations")->fetchAll();
        foreach ($res as $row) {
            $stationIDs[$row['name']] = $row['id'];
        }
        
        $users = [
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
            try {
                $stmtUser->execute($u);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    continue;
                }
                throw $e;
            }
        }
        
        // Create uploads directory if not exists
        if (!file_exists(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }
        
        $status = 'success';
        $message = 'Database and tables successfully created, pre-seeded with 5 Police Stations, 5 Departments, and test accounts!';
    } catch (Exception $e) {
        $status = 'error';
        $message = 'Setup Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS Initializer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="container" style="max-width: 600px;">
        <div class="glass-card p-5 border text-center shadow">
            <h1 class="h3 mb-3 fw-bold text-primary"><i class="fa-solid fa-database text-warning"></i> ESWATINI POLICE CAS</h1>
            <p class="text-muted mb-4">Welcome to the Crime Administration System (CAS) database setup. Tap initialize below to create database structures & demo accounts.</p>
            
            <?php if ($status === 'success'): ?>
                <div class="alert alert-success border-0 mb-4 shadow-sm" role="alert">
                    <strong>Success!</strong> <?php echo $message; ?>
                </div>
                <div class="d-grid gap-2">
                    <a href="login.php" class="btn btn-gold text-dark py-2"><i class="fa-solid fa-right-to-bracket me-1"></i> Proceed to Portal Login</a>
                </div>
            <?php else: ?>
                <?php if ($status === 'error'): ?>
                    <div class="alert alert-danger border-0 mb-4 shadow-sm" role="alert">
                        <strong>Error!</strong> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="initialize.php" method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" name="initialize" class="btn btn-blue py-3"><i class="fa-solid fa-server me-1"></i> Setup & Seed Database</button>
                    </div>
                </form>
            <?php endif; ?>
            
            <div class="mt-4 pt-3 border-top text-start">
                <h6 class="fw-bold">Default Credentials for Testing:</h6>
                <table class="table table-sm table-borderless text-muted small">
                    <tr>
                        <td><strong>Citizen:</strong></td>
                        <td>citizen@gmail.com</td>
                        <td>Citizen@123</td>
                    </tr>
                    <tr>
                        <td><strong>Desk Officer:</strong></td>
                        <td>desk@police.gov.sz</td>
                        <td>Desk@123</td>
                    </tr>
                    <tr>
                        <td><strong>Investigator:</strong></td>
                        <td>officer@police.gov.sz</td>
                        <td>Officer@123</td>
                    </tr>
                    <tr>
                        <td><strong>HOD Serious Crimes:</strong></td>
                        <td>hod@police.gov.sz</td>
                        <td>Hod@123</td>
                    </tr>
                    <tr>
                        <td><strong>Station Commander:</strong></td>
                        <td>admin@police.gov.sz</td>
                        <td>Admin@123</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
