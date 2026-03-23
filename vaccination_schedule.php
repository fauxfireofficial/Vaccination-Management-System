<?php
/**
 * Project: Vaccination Management System (0-18 Years Child Immunization)
 * File: vaccination_schedule.php
 * Description: Complete EPI vaccination schedule with age-wise breakdown,
 *              vaccine details, and printable format for parents.
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Include database configuration
require_once 'db_config.php';

// Get user role for personalized content
$user_role = $_SESSION['user_role'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = isset($user_id);

// Fetch all vaccines from database
$vaccines_query = "SELECT * FROM vaccines ORDER BY 
                   CASE age_group
                       WHEN 'At Birth' THEN 1
                       WHEN '6 Weeks' THEN 2
                       WHEN '10 Weeks' THEN 3
                       WHEN '14 Weeks' THEN 4
                       WHEN '9 Months' THEN 5
                       WHEN '12 Months' THEN 6
                       WHEN '18 Months' THEN 7
                       WHEN '4-5 Years' THEN 8
                       WHEN '11-12 Years' THEN 9
                       WHEN '15-16 Years' THEN 10
                       ELSE 11
                   END, dose_number";

$vaccines_result = $conn->query($vaccines_query);

// Organize vaccines by age group
$schedule = [];
while ($vaccine = $vaccines_result->fetch_assoc()) {
    $age_group = $vaccine['age_group'];
    if (!isset($schedule[$age_group])) {
        $schedule[$age_group] = [];
    }
    $schedule[$age_group][] = $vaccine;
}

// Age group order for display
$age_groups_order = [
    'At Birth',
    '6 Weeks',
    '10 Weeks',
    '14 Weeks',
    '9 Months',
    '12 Months',
    '18 Months',
    '4-5 Years',
    '11-12 Years',
    '15-16 Years'
];

// Get child's age if parent is logged in and has children
$child_age_months = null;
$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$child_info = null;

if ($is_logged_in && $user_role === 'parent' && $child_id > 0) {
    $child_query = "SELECT id, full_name, date_of_birth, 
                   TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) as age_months,
                   TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age_years
                   FROM children 
                   WHERE id = ? AND parent_id = (SELECT id FROM parents WHERE user_id = ?)";
    
    $child_stmt = $conn->prepare($child_query);
    $child_stmt->bind_param("ii", $child_id, $user_id);
    $child_stmt->execute();
    $child_result = $child_stmt->get_result();
    
    if ($child_result->num_rows > 0) {
        $child_info = $child_result->fetch_assoc();
        $child_age_months = $child_info['age_months'];
    }
}

// Get due vaccines for the child
$due_vaccines = [];
$completed_vaccines = [];

if ($child_info) {
    // Get completed vaccines
    $completed_query = "SELECT v.id, v.vaccine_name, v.age_group, v.dose_number
                       FROM vaccination_records vr
                       JOIN vaccines v ON vr.vaccine_id = v.id
                       WHERE vr.child_id = ?";
    $completed_stmt = $conn->prepare($completed_query);
    $completed_stmt->bind_param("i", $child_id);
    $completed_stmt->execute();
    $completed_result = $completed_stmt->get_result();
    
    while ($row = $completed_result->fetch_assoc()) {
        $completed_vaccines[] = $row['id'];
    }
    
    // Calculate due vaccines based on age
    foreach ($schedule as $age_group => $vaccines) {
        $age_months = 0;
        switch ($age_group) {
            case 'At Birth': $age_months = 0; break;
            case '6 Weeks': $age_months = 1.5; break;
            case '10 Weeks': $age_months = 2.5; break;
            case '14 Weeks': $age_months = 3.5; break;
            case '9 Months': $age_months = 9; break;
            case '12 Months': $age_months = 12; break;
            case '18 Months': $age_months = 18; break;
            case '4-5 Years': $age_months = 48; break;
            case '11-12 Years': $age_months = 132; break;
            case '15-16 Years': $age_months = 180; break;
        }
        
        if ($child_age_months >= $age_months) {
            foreach ($vaccines as $vaccine) {
                if (!in_array($vaccine['id'], $completed_vaccines)) {
                    $due_vaccines[] = $vaccine;
                }
            }
        }
    }
}

// Include header
include_once 'header.php';
?>

<div class="container-fluid py-4">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="bg-gradient-primary text-white rounded-4 p-4 shadow-lg">
                <div class="d-flex align-items-center">
                    <div class="avatar-circle bg-white bg-opacity-25 p-3 rounded-3 me-3">
                        <i class="bi bi-calendar-week-fill fs-1"></i>
                    </div>
                    <div>
                        <h2 class="fw-bold mb-1">EPI Vaccination Schedule</h2>
                        <p class="mb-0 opacity-75">Complete immunization schedule for children (0-18 years) as per Pakistan EPI program</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Child Selector (for logged in parents) -->
    <?php if ($is_logged_in && $user_role === 'parent'): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-child me-2 text-primary"></i>Select Child</h5>
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-8">
                            <select name="child_id" class="form-select form-select-lg" onchange="this.form.submit()">
                                <option value="">-- Select a child to see due vaccines --</option>
                                <?php
                                $children_query = "SELECT c.id, c.full_name, 
                                                  TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months
                                                  FROM children c
                                                  JOIN parents p ON c.parent_id = p.id
                                                  WHERE p.user_id = ?";
                                $children_stmt = $conn->prepare($children_query);
                                $children_stmt->bind_param("i", $user_id);
                                $children_stmt->execute();
                                $children_result = $children_stmt->get_result();
                                
                                while ($child = $children_result->fetch_assoc()) {
                                    $selected = ($child_id == $child['id']) ? 'selected' : '';
                                    $age_text = '';
                                    if ($child['age_months'] < 12) {
                                        $age_text = $child['age_months'] . ' months';
                                    } else {
                                        $years = floor($child['age_months'] / 12);
                                        $months = $child['age_months'] % 12;
                                        $age_text = $years . ' year' . ($years > 1 ? 's' : '');
                                        if ($months > 0) {
                                            $age_text .= ' ' . $months . ' month' . ($months > 1 ? 's' : '');
                                        }
                                    }
                                    echo "<option value='{$child['id']}' $selected>" . htmlspecialchars($child['full_name']) . " ($age_text)</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <?php if ($child_id > 0): ?>
                        <div class="col-md-4">
                            <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-calendar-plus"></i> Book Appointment
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Due Vaccines Summary -->
        <?php if ($child_info && !empty($due_vaccines)): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 bg-warning bg-opacity-10">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Due Vaccines for <?php echo htmlspecialchars($child_info['full_name']); ?></h5>
                    <div class="row">
                        <?php foreach (array_slice($due_vaccines, 0, 3) as $vaccine): ?>
                        <div class="col-md-4 mb-2">
                            <div class="bg-white p-2 rounded-3 text-center">
                                <small class="text-muted d-block"><?php echo $vaccine['age_group']; ?></small>
                                <span class="fw-bold"><?php echo $vaccine['vaccine_name']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($due_vaccines) > 3): ?>
                    <p class="text-muted small mt-2">+ <?php echo count($due_vaccines) - 3; ?> more vaccines due</p>
                    <?php endif; ?>
                    <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn btn-warning mt-2 w-100">
                        <i class="bi bi-calendar-check"></i> Book Due Vaccines
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Schedule Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 bg-primary text-white">
                <div class="card-body p-3">
                    <h3 class="fw-bold mb-0">0-18</h3>
                    <small>Years Coverage</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-success text-white">
                <div class="card-body p-3">
                    <h3 class="fw-bold mb-0">10</h3>
                    <small>Diseases Prevented</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-info text-white">
                <div class="card-body p-3">
                    <h3 class="fw-bold mb-0"><?php 
                        $count = $conn->query("SELECT COUNT(*) as total FROM vaccines")->fetch_assoc()['total'];
                        echo $count;
                    ?></h3>
                    <small>Total Doses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-warning text-white">
                <div class="card-body p-3">
                    <h3 class="fw-bold mb-0">Free</h3>
                    <small>At Govt Hospitals</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- EPI Information Alert -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info rounded-4 border-0 shadow-sm">
                <div class="d-flex">
                    <i class="bi bi-info-circle-fill fs-3 me-3"></i>
                    <div>
                        <h5 class="fw-bold">Expanded Program on Immunization (EPI) Pakistan</h5>
                        <p class="mb-0">All vaccines in this schedule are provided free of cost at all government hospitals and EPI centers across Pakistan. Parents are advised to follow this schedule for complete protection of their children.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schedule Table -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Complete Vaccination Schedule</h5>
            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Schedule
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 no-datatable" id="scheduleTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Age</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Protects Against</th>
                            <th>Due Date</th>
                            <th class="text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_age = '';
                        foreach ($age_groups_order as $age_group):
                            if (!isset($schedule[$age_group])) continue;
                            
                            $age_vaccines = $schedule[$age_group];
                            $rowspan = count($age_vaccines);
                            $first = true;
                            
                            foreach ($age_vaccines as $vaccine):
                                // Calculate due date for child if selected
                                $due_date = '';
                                $status_class = '';
                                $status_text = '';
                                
                                if ($child_info) {
                                    $dob = new DateTime($child_info['date_of_birth']);
                                    switch ($age_group) {
                                        case 'At Birth':
                                            $due = clone $dob;
                                            break;
                                        case '6 Weeks':
                                            $due = clone $dob->modify('+6 weeks');
                                            break;
                                        case '10 Weeks':
                                            $due = clone $dob->modify('+10 weeks');
                                            break;
                                        case '14 Weeks':
                                            $due = clone $dob->modify('+14 weeks');
                                            break;
                                        case '9 Months':
                                            $due = clone $dob->modify('+9 months');
                                            break;
                                        case '12 Months':
                                            $due = clone $dob->modify('+12 months');
                                            break;
                                        case '18 Months':
                                            $due = clone $dob->modify('+18 months');
                                            break;
                                        case '4-5 Years':
                                            $due = clone $dob->modify('+4 years');
                                            break;
                                        case '11-12 Years':
                                            $due = clone $dob->modify('+11 years');
                                            break;
                                        case '15-16 Years':
                                            $due = clone $dob->modify('+15 years');
                                            break;
                                    }
                                    $due_date = $due->format('d M Y');
                                    
                                    // Check status
                                    if (in_array($vaccine['id'], $completed_vaccines)) {
                                        $status_class = 'bg-success';
                                        $status_text = 'Completed';
                                    } elseif (in_array($vaccine, $due_vaccines)) {
                                        $status_class = 'bg-warning';
                                        $status_text = 'Due';
                                    } else {
                                        $status_class = 'bg-secondary';
                                        $status_text = 'Pending';
                                    }
                                }
                        ?>
                        <tr>
                            <?php if ($first): ?>
                                <td class="ps-4 align-middle" rowspan="<?php echo $rowspan; ?>">
                                    <span class="fw-bold"><?php echo $age_group; ?></span>
                                </td>
                                <?php $first = false; ?>
                            <?php endif; ?>
                            <td>
                                <span class="fw-semibold"><?php echo $vaccine['vaccine_name']; ?></span>
                                <?php if (!empty($vaccine['short_name'])): ?>
                                    <br><small class="text-muted"><?php echo $vaccine['short_name']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>Dose <?php echo $vaccine['dose_number']; ?></td>
                            <td>
                                <?php 
                                if ($vaccine['vaccine_name'] == 'BCG') echo 'Tuberculosis';
                                elseif (strpos($vaccine['vaccine_name'], 'Pentavalent') !== false) echo 'Diphtheria, Tetanus, Pertussis, Hepatitis B, Hib';
                                elseif (strpos($vaccine['vaccine_name'], 'PCV') !== false) echo 'Pneumococcal diseases';
                                elseif (strpos($vaccine['vaccine_name'], 'Rotavirus') !== false) echo 'Severe diarrhea';
                                elseif (strpos($vaccine['vaccine_name'], 'IPV') !== false || strpos($vaccine['vaccine_name'], 'OPV') !== false) echo 'Polio';
 elseif (strpos($vaccine['vaccine_name'], 'Measles') !== false) echo 'Measles';
 elseif (strpos($vaccine['vaccine_name'], 'MMR') !== false) echo 'Measles, Mumps, Rubella';
 elseif (strpos($vaccine['vaccine_name'], 'Typhoid') !== false) echo 'Typhoid fever';
 elseif (strpos($vaccine['vaccine_name'], 'Hepatitis') !== false) echo 'Hepatitis B';
 elseif (strpos($vaccine['vaccine_name'], 'DT') !== false) echo 'Diphtheria, Tetanus';
 elseif (strpos($vaccine['vaccine_name'], 'Tdap') !== false) echo 'Tetanus, Diphtheria, Pertussis';
 elseif (strpos($vaccine['vaccine_name'], 'HPV') !== false) echo 'Human Papillomavirus (cervical cancer)';
 elseif (strpos($vaccine['vaccine_name'], 'Vitamin') !== false) echo 'Vitamin A deficiency';
 else echo 'Multiple diseases';
                                ?>
                            </td>
                            <td>
                                <?php if ($child_info): ?>
                                    <span class="<?php echo $status_class == 'bg-warning' ? 'text-warning fw-bold' : ''; ?>">
                                        <?php echo $due_date; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($child_info): ?>
                                    <span class="badge <?php echo $status_class; ?> rounded-pill p-2">
                                        <?php echo $status_text; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary rounded-pill p-2">Required</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Important Notes -->
    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Important Notes</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Vaccines are free at all government hospitals</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Bring child's vaccination card to every visit</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> If a dose is missed, consult your doctor for catch-up schedule</li>
                        <li class="mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i> Keep vaccination record safe for school admissions</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i> Inform doctor if child is sick on appointment day</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-question-circle text-primary me-2"></i>Frequently Asked Questions</h5>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-light rounded-3 p-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What if I miss a vaccine dose?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body p-3">
                                    Don't worry. Contact your healthcare provider for a catch-up schedule. Most vaccines can be given later without restarting the series.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-light rounded-3 p-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Are these vaccines safe?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body p-3">
                                    Yes, all EPI vaccines are tested and proven safe. Side effects are usually mild and temporary.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-light rounded-3 p-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    Why so many doses?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body p-3">
                                    Multiple doses ensure strong immunity. Some vaccines need boosters for long-lasting protection.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Download/Print Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 bg-light rounded-4">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-3">Download Vaccination Schedule</h5>
                    <p class="text-muted mb-4">Get a printable copy of the complete EPI schedule</p>
                    <a href="#" class="btn btn-primary btn-lg px-5 me-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Schedule
                    </a>
                    <a href="download_schedule.php" class="btn btn-outline-primary btn-lg px-5">
                        <i class="bi bi-download"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Styles -->
<style media="print">
    .navbar, .sidebar, .footer, .btn, .avatar-circle, .card-header .btn, .row.mb-4, .bg-gradient-primary {
        display: none !important;
    }
    body {
        background: white;
        padding: 20px;
    }
    .container-fluid {
        padding: 0;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .table {
        border: 1px solid #000;
    }
    .table th {
        background: #f0f0f0 !important;
        color: black !important;
    }
    .badge {
        border: 1px solid #000;
        color: black !important;
        background: white !important;
    }
    .bg-success, .bg-warning, .bg-secondary {
        background: white !important;
    }
</style>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #2A9D8F, #1a5f7a);
    }
    .avatar-circle {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .table td {
        vertical-align: middle;
    }
    .accordion-button:not(.collapsed) {
        background-color: #e8f5f3;
        color: #2A9D8F;
    }
    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(42, 157, 143, 0.25);
    }
    @media (max-width: 768px) {
        .table {
            font-size: 0.9rem;
        }
    }
</style>

<script>
// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php include_once 'footer.php'; ?>