<?php
/**
 * API-style rule-based recommendations
 * Call this via AJAX for dynamic updates
 */

header('Content-Type: application/json');

require_once 'db_config.php';
require_once 'vaccine_recommendation.php';

$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;

if ($child_id == 0) {
    echo json_encode(['error' => 'Invalid child ID']);
    exit;
}

try {
    $engine = new VaccineRecommendationEngine($conn, $child_id);
    
    $response = [
        'success' => true,
        'child_name' => $engine->getChildName(),
        'child_age' => $engine->getChildAgeText(),
        'summary' => $engine->getSummary(),
        'recommendations' => $engine->getRecommendations(),
        'next_due' => $engine->getNextDue(),
        'overdue' => $engine->getOverdueVaccines()
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
