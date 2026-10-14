<?php
// Eswatini Police CAS - Global Footer Layout

// Dynamically determine the relative path prefix based on the currently executing script
$basePath = '';
if (file_exists('login.php')) {
    $basePath = './';
} elseif (file_exists('../login.php')) {
    $basePath = '../';
} elseif (file_exists('../../login.php')) {
    $basePath = '../../';
} else {
    $basePath = '../';
}
?>
            </main>

            <!-- Footer -->
            <footer class="text-center py-4 bg-dark text-white-50 border-top border-warning border-3 mt-auto no-print">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-6 text-md-start">
                            <p class="mb-0">&copy; <?php echo date('Y'); ?> Royal Eswatini Police Service. Crime Administration System.</p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-shield"></i> Eswatini CAS Portal</span>
                            <span class="badge bg-secondary"><i class="fa-solid fa-code"></i> Secure Session</span>
                        </div>
                    </div>
                </div>
            </footer>
            
        </div> <!-- Close #page-content-wrapper -->
    </div> <!-- Close #wrapper -->

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet.js Map script (Loads from CDN) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <!-- Custom Application Javascript -->
    <script src="<?php echo $basePath; ?>assets/js/app.js"></script>
    
    <!-- Sidebar Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const menuToggle = document.getElementById('menu-toggle');
            const wrapper = document.getElementById('wrapper');
            const sidebar = document.getElementById('sidebar-wrapper');
            
            if (menuToggle && wrapper) {
                menuToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('toggled');
                    } else {
                        // Toggle sidebar visibility on desktop
                        if (sidebar.style.display === 'none') {
                            sidebar.style.display = 'block';
                        } else {
                            sidebar.style.display = 'none';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
