<?php
/**
 * Display Vaccine Recommendations
 * Include this in child_details.php or parent dashboard
 */

require_once 'vaccine_recommendation.php';

function displayVaccineRecommendations($conn, $child_id) {
    try {
        $engine = new VaccineRecommendationEngine($conn, $child_id);
        $summary = $engine->getSummary();
        $recommendations = $engine->getRecommendations();
        $next_due = $engine->getNextDue();
        $overdue = $engine->getOverdueVaccines();
        ?>
        
        <!-- Recommendation Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-white shadow-sm border-0 border-start border-info border-4 mt-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3 d-flex align-items-center justify-content-center">
                                <i class="bi bi-robot fs-2 text-info"></i>
                            </div>
                            <div class="text-dark">
                                <h4 class="mb-1 fw-bold text-info">AI Vaccine Assistant</h4>
                                <p class="mb-0 text-secondary">
                                    Personalized recommendations for <strong class="text-dark"><?php echo $engine->getChildName(); ?></strong> 
                                    (Age: <?php echo $engine->getChildAgeText(); ?>)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body p-3">
                        <h6 class="text-white-50">Completion</h6>
                        <h3 class="fw-bold mb-0"><?php echo $summary['completion_percentage']; ?>%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body p-3">
                        <h6 class="text-white-50">Completed</h6>
                        <h3 class="fw-bold mb-0"><?php echo $summary['completed']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body p-3">
                        <h6 class="text-white-50">Due</h6>
                        <h3 class="fw-bold mb-0"><?php echo $summary['due']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body p-3">
                        <h6 class="text-white-50">Overdue</h6>
                        <h3 class="fw-bold mb-0"><?php echo $summary['overdue']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Next Due Alert -->
        <?php if ($next_due && !$next_due['completed']): ?>
        <div class="alert alert-<?php echo $next_due['status_class']; ?> mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-bell fs-3 me-3"></i>
                <div>
                    <strong>Next Vaccine Due:</strong> 
                    <?php echo $next_due['name']; ?> (Dose <?php echo $next_due['dose']; ?>)
                    <?php if ($next_due['due_date']): ?>
                        - Due by <?php echo $next_due['due_date']; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Overdue Alert -->
        <?php if (count($overdue) > 0): ?>
        <div class="alert alert-danger mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle fs-3 me-3"></i>
                <div>
                    <strong>⚠️ Overdue Vaccines:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($overdue as $v): ?>
                        <li><?php echo $v['name']; ?> (Dose <?php echo $v['dose']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recommendations Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Complete Vaccination Schedule</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Status</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Age Group</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $rec): ?>
                            <tr class="<?php echo $rec['status'] == 'overdue' ? 'table-danger' : ''; ?>">
                                <td class="ps-4">
                                    <span class="badge bg-<?php echo $rec['status_class']; ?>">
                                        <?php echo $rec['message']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?php echo $rec['name']; ?></span>
                                    <br>
                                    <small class="text-muted"><?php echo $rec['notes']; ?></small>
                                </td>
                                <td><?php echo $rec['dose']; ?></td>
                                <td><?php echo $rec['age_group']; ?></td>
                                <td>
                                    <?php if (!$rec['completed'] && $rec['due_date']): ?>
                                        <?php echo $rec['due_date']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rec['priority'] == 'high'): ?>
                                        <span class="badge bg-danger">High</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Medium</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (!$rec['completed']): ?>
                                        <a href="book_appointment.php?child_id=<?php echo $child_id; ?>&vaccine_id=<?php echo $rec['id']; ?>" 
                                           class="btn btn-sm btn-<?php echo $rec['status_class']; ?>">
                                            Book Now
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-success">✓ Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Chatbot Style Message -->
        <div class="card mt-4 bg-light">
            <div class="card-body">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="bi bi-chat-dots-fill fs-1 text-primary"></i>
                    </div>
                    <div>
                        <h6>💬 Vaccine Assistant says:</h6>
                        <?php if ($summary['overdue'] > 0): ?>
                            <p class="mb-0 text-danger">
                                ⚠️ Your child has <?php echo $summary['overdue']; ?> overdue vaccine(s). 
                                Please book appointment immediately!
                            </p>
                        <?php elseif ($summary['due'] > 0): ?>
                            <p class="mb-0 text-warning">
                                🔔 You have <?php echo $summary['due']; ?> vaccine(s) due now. 
                                Schedule an appointment soon.
                            </p>
                        <?php elseif ($summary['completed'] == $summary['total']): ?>
                            <p class="mb-0 text-success">
                                🎉 Congratulations! Your child has completed all recommended vaccines!
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-info">
                                ⏳ Next vaccine is due soon. Keep tracking!
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>