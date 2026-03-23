<?php
/**
 * Project: Vaccination Management System
 * File: footer.php
 * Description: Professional footer for all pages
 */
?>

            </div> <!-- End app-content -->
        </main> <!-- End app-main -->

        <!-- Footer -->
        <footer class="app-footer bg-dark text-white py-5 mt-auto">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <h4 class="mb-3"><?php echo getSetting('site_name', 'VaccineCare'); ?></h4>
                        <p class="text-secondary">Complete child vaccination management system for children aged 0-18 years. Making immunization tracking simple and efficient.</p>
                        <div class="social-links">
                            <a href="#" class="text-white me-3"><i class="bi bi-facebook fs-4"></i></a>
                            <a href="#" class="text-white me-3"><i class="bi bi-twitter fs-4"></i></a>
                            <a href="#" class="text-white me-3"><i class="bi bi-linkedin fs-4"></i></a>
                            <a href="#" class="text-white"><i class="bi bi-instagram fs-4"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <h5 class="mb-3">Quick Links</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="index.php" class="text-secondary text-decoration-none">Home</a></li>
                            <li class="mb-2"><a href="hospitals_list.php" class="text-secondary text-decoration-none">Hospitals</a></li>
                            <li class="mb-2"><a href="vaccination_schedule.php" class="text-secondary text-decoration-none">Schedule</a></li>
                            <li class="mb-2"><a href="about.php" class="text-secondary text-decoration-none">About Us</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-3">
                        <h5 class="mb-3">Support</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><a href="faq.php" class="text-secondary text-decoration-none">FAQ</a></li>
                            <li class="mb-2"><a href="contact.php" class="text-secondary text-decoration-none">Contact Us</a></li>
                            <li class="mb-2"><a href="privacy.php" class="text-secondary text-decoration-none">Privacy Policy</a></li>
                            <li class="mb-2"><a href="terms.php" class="text-secondary text-decoration-none">Terms of Service</a></li>
                        </ul>
                    </div>
                    <div class="col-lg-3">
                        <h5 class="mb-3">Contact Info</h5>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="bi bi-envelope me-2"></i> <?php echo getSetting('contact_email', 'info@vaccinecare.com'); ?></li>
                            <li class="mb-2"><i class="bi bi-telephone me-2"></i> <?php echo getSetting('contact_phone', '+92 300 1234567'); ?></li>
                            <li class="mb-2"><i class="bi bi-geo-alt me-2"></i> Karachi, Pakistan</li>
                        </ul>
                    </div>
                </div>
                
                <hr class="my-4 bg-secondary">
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="text-secondary mb-0">&copy; <?php echo date('Y'); ?> <?php echo getSetting('site_name', 'VaccineCare'); ?>. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="text-secondary mb-0">Designed for healthy futures 🇵🇰</p>
                    </div>
                </div>
            </div>
        </footer>
    </div> <!-- End app-wrapper -->

    <!-- REQUIRED SCRIPTS -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 Bundle (Includes Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS & Bootstrap 5 Integration -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Custom Init -->
    <script>
        $(document).ready(function() {
            // Initialize DataTables globally for main tables
            var dataTablesConfig = {
                "pageLength": 10,
                "lengthMenu": [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                "language": {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Search records...",
                    "lengthMenu": "Show _MENU_ entries"
                },
                "dom": "<'row mb-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
                       "<'row'<'col-sm-12 table-responsive'tr>>" +
                       "<'row mt-3 align-items-center'<'col-sm-12 col-md-5 text-muted small'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
                "responsive": true,
                "ordering": true,
                "stateSave": false // Disabled state save by default to show fresh data sorting
            };

            // Apply to main data grids
            $('.table-hover:not(.no-datatable):not(.modal .table-hover):not(.table-borderless)').each(function() {
                // Ensure table has thead and tbody before init to avoid DT errors on simple tables
                if ($(this).find('thead').length > 0 && $(this).find('tbody').length > 0) {
                    if (!$.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable(dataTablesConfig);
                    }
                }
            });
        });
    </script>
</body>
</html>