<?php
// Eswatini Police CAS - Project Packaging and Compressor Utility
session_start();

$status = '';
$message = '';
$zipName = 'eswatini_police_cas_system.zip';
$zipPath = __DIR__ . '/' . $zipName;

if (isset($_POST['package_system'])) {
    try {
        if (!class_exists('ZipArchive')) {
            throw new Exception("Error: ZipArchive extension is not enabled in your PHP installation. Please enable it in php.ini.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Error: Cannot open/create zip file archive at " . $zipPath);
        }

        // Recursive directory iterator
        $rootPath = realpath(__DIR__);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        foreach ($files as $name => $file) {
            // Skip directories (they are added automatically)
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                // Get relative path for our zip archive
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Exclude any existing zip files, temporary database logs, or metadata
                if (
                    strpos($relativePath, '.zip') !== false ||
                    strpos($relativePath, '.git') !== false ||
                    strpos($relativePath, 'node_modules') !== false
                ) {
                    continue;
                }

                $zip->addFile($filePath, $relativePath);
                $fileCount++;
            }
        }

        $zip->close();
        
        $fileSizeMB = round(filesize($zipPath) / (1024 * 1024), 2);
        $status = 'success';
        $message = "System successfully packaged! <strong>{$fileCount} files</strong> compressed into <strong>{$zipName}</strong> ({$fileSizeMB} MB).";
    } catch (Exception $e) {
        $status = 'error';
        $message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eswatini Police CAS - System Archiver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="container" style="max-width: 600px;">
        <div class="glass-card p-5 border text-center shadow">
            
            <div class="mb-4">
                <div class="fs-1 text-warning mb-3">
                    <i class="fa-solid fa-file-zipper fa-2x"></i>
                </div>
                <h3 class="fw-bold text-primary">ESWATINI CAS SYSTEM PACKAGER</h3>
                <p class="text-muted">Generate a downloadable ZIP file containing the complete source code, database sql scripts, configurations, stylesheets, assets, and upload directory structures ready to deploy to another XAMPP server.</p>
            </div>

            <?php if ($status === 'success'): ?>
                <div class="alert alert-success border-0 mb-4 shadow-sm text-start" role="alert">
                    <i class="fa-solid fa-circle-check text-success me-2"></i> <?php echo $message; ?>
                </div>
                <div class="d-grid gap-2">
                    <a href="<?php echo $zipName; ?>" class="btn btn-gold text-dark py-3 fw-bold" download>
                        <i class="fa-solid fa-cloud-arrow-down me-1"></i> Download System ZIP
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-secondary mt-2">Go to Workspace Dashboard</a>
                </div>
            <?php else: ?>
                <?php if ($status === 'error'): ?>
                    <div class="alert alert-danger border-0 mb-4 shadow-sm" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form action="zip_project.php" method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" name="package_system" class="btn btn-blue py-3 fw-bold">
                            <i class="fa-solid fa-box-archive me-1"></i> Compress & Package CAS Codebase
                        </button>
                    </div>
                </form>
                <a href="dashboard.php" class="btn btn-outline-secondary mt-3 w-100">Cancel & Return</a>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
