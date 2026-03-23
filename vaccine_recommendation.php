<?php
/**
 * Rule-Based Vaccine Recommendation System
 * EPI Schedule Pakistan - 0 to 18 Years
 */

class VaccineRecommendationEngine {
    private $conn;
    private $child_id;
    private $child_data;
    private $completed_vaccines = [];
    
    /**
     * Constructor
     * @param mysqli $conn Database connection
     * @param int $child_id Child ID
     */
    public function __construct($conn, $child_id) {
        $this->conn = $conn;
        $this->child_id = $child_id;
        $this->loadChildData();
        $this->loadCompletedVaccines();
    }
    
    /**
     * Load child data from database
     */
    private function loadChildData() {
        $query = "SELECT id, full_name, date_of_birth,
                         TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) as age_months,
                         TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age_years
                  FROM children 
                  WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->child_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Child not found");
        }
        
        $this->child_data = $result->fetch_assoc();
    }
    
    /**
     * Load completed vaccines for this child
     */
    private function loadCompletedVaccines() {
        $query = "SELECT vaccine_id, dose_number 
                  FROM vaccination_records 
                  WHERE child_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->child_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $this->completed_vaccines[] = $row['vaccine_id'];
        }
    }
    
    /**
     * EPI Schedule Rules - Pakistan
     * Returns array of all vaccines with their rules
     */
    private function getEPIRules() {
        return [
            // AT BIRTH
            ['id' => 1, 'name' => 'BCG', 'age_min' => 0, 'age_max' => 1, 'dose' => 1, 'priority' => 'high', 'notes' => 'Within 24 hours of birth'],
            ['id' => 2, 'name' => 'OPV-0', 'age_min' => 0, 'age_max' => 1, 'dose' => 1, 'priority' => 'high', 'notes' => 'Oral Polio Vaccine - Birth dose'],
            ['id' => 3, 'name' => 'Hepatitis B - 1', 'age_min' => 0, 'age_max' => 1, 'dose' => 1, 'priority' => 'high', 'notes' => 'First dose of Hepatitis B'],
            
            // 6 WEEKS (1.5 months)
            ['id' => 4, 'name' => 'Pentavalent - 1', 'age_min' => 1.5, 'age_max' => 3, 'dose' => 1, 'priority' => 'high', 'notes' => 'Diphtheria, Tetanus, Pertussis, Hepatitis B, Hib'],
            ['id' => 5, 'name' => 'PCV - 1', 'age_min' => 1.5, 'age_max' => 3, 'dose' => 1, 'priority' => 'high', 'notes' => 'Pneumococcal Vaccine'],
            ['id' => 6, 'name' => 'Rotavirus - 1', 'age_min' => 1.5, 'age_max' => 3, 'dose' => 1, 'priority' => 'high', 'notes' => 'Protects against severe diarrhea'],
            ['id' => 7, 'name' => 'IPV - 1', 'age_min' => 1.5, 'age_max' => 3, 'dose' => 1, 'priority' => 'high', 'notes' => 'Inactivated Polio Vaccine'],
            
            // 10 WEEKS (2.5 months)
            ['id' => 8, 'name' => 'Pentavalent - 2', 'age_min' => 2.5, 'age_max' => 4, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second dose'],
            ['id' => 9, 'name' => 'PCV - 2', 'age_min' => 2.5, 'age_max' => 4, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second dose'],
            ['id' => 10, 'name' => 'Rotavirus - 2', 'age_min' => 2.5, 'age_max' => 4, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second dose'],
            
            // 14 WEEKS (3.5 months)
            ['id' => 11, 'name' => 'Pentavalent - 3', 'age_min' => 3.5, 'age_max' => 5, 'dose' => 3, 'priority' => 'high', 'notes' => 'Third dose'],
            ['id' => 12, 'name' => 'PCV - 3', 'age_min' => 3.5, 'age_max' => 5, 'dose' => 3, 'priority' => 'high', 'notes' => 'Third dose'],
            ['id' => 13, 'name' => 'IPV - 2', 'age_min' => 3.5, 'age_max' => 5, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second IPV'],
            
            // 9 MONTHS
            ['id' => 14, 'name' => 'Measles - 1', 'age_min' => 9, 'age_max' => 12, 'dose' => 1, 'priority' => 'high', 'notes' => 'First measles dose'],
            ['id' => 15, 'name' => 'Vitamin A', 'age_min' => 9, 'age_max' => 12, 'dose' => 1, 'priority' => 'medium', 'notes' => 'Vitamin A supplement'],
            
            // 12 MONTHS
            ['id' => 16, 'name' => 'MMR - 1', 'age_min' => 12, 'age_max' => 15, 'dose' => 1, 'priority' => 'high', 'notes' => 'Measles, Mumps, Rubella'],
            ['id' => 17, 'name' => 'Typhoid', 'age_min' => 12, 'age_max' => 18, 'dose' => 1, 'priority' => 'medium', 'notes' => 'Typhoid vaccine'],
            
            // 18 MONTHS
            ['id' => 18, 'name' => 'Pentavalent Booster', 'age_min' => 18, 'age_max' => 24, 'dose' => 4, 'priority' => 'high', 'notes' => 'Booster dose'],
            ['id' => 19, 'name' => 'IPV Booster', 'age_min' => 18, 'age_max' => 24, 'dose' => 3, 'priority' => 'high', 'notes' => 'IPV booster'],
            ['id' => 20, 'name' => 'Measles - 2', 'age_min' => 18, 'age_max' => 24, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second measles dose'],
            ['id' => 21, 'name' => 'Vitamin A - 2', 'age_min' => 18, 'age_max' => 24, 'dose' => 2, 'priority' => 'medium', 'notes' => 'Second Vitamin A'],
            
            // 4-5 YEARS (48-60 months)
            ['id' => 22, 'name' => 'DT Booster', 'age_min' => 48, 'age_max' => 72, 'dose' => 1, 'priority' => 'high', 'notes' => 'Diphtheria, Tetanus booster'],
            ['id' => 23, 'name' => 'OPV Booster', 'age_min' => 48, 'age_max' => 72, 'dose' => 1, 'priority' => 'high', 'notes' => 'Oral Polio booster'],
            ['id' => 24, 'name' => 'MMR - 2', 'age_min' => 48, 'age_max' => 72, 'dose' => 2, 'priority' => 'high', 'notes' => 'Second MMR'],
            
            // 11-12 YEARS (132-144 months)
            ['id' => 25, 'name' => 'Tdap', 'age_min' => 132, 'age_max' => 156, 'dose' => 1, 'priority' => 'high', 'notes' => 'Tetanus, Diphtheria, Pertussis'],
            ['id' => 26, 'name' => 'HPV - 1', 'age_min' => 132, 'age_max' => 156, 'dose' => 1, 'priority' => 'high', 'notes' => 'Human Papillomavirus (girls)'],
            ['id' => 27, 'name' => 'HPV - 2', 'age_min' => 138, 'age_max' => 162, 'dose' => 2, 'priority' => 'high', 'notes' => 'HPV second dose, 6 months after first'],
            
            // 15-16 YEARS (180-192 months)
            ['id' => 28, 'name' => 'Td Booster', 'age_min' => 180, 'age_max' => 204, 'dose' => 2, 'priority' => 'high', 'notes' => 'Tetanus, Diphtheria booster']
        ];
    }
    
    /**
     * Get vaccine recommendations based on child's age
     * @return array Recommendations with status
     */
    public function getRecommendations() {
        $age_months = $this->child_data['age_months'];
        $rules = $this->getEPIRules();
        $recommendations = [];
        
        foreach ($rules as $rule) {
            // Check if vaccine is completed
            $is_completed = in_array($rule['id'], $this->completed_vaccines);
            
            // Determine status based on age
            if ($is_completed) {
                $status = 'completed';
                $status_class = 'success';
                $message = '✅ Completed';
            } elseif ($age_months >= $rule['age_min']) {
                if ($age_months > $rule['age_max']) {
                    $status = 'overdue';
                    $status_class = 'danger';
                    $message = '⚠️ Overdue';
                } else {
                    $status = 'due';
                    $status_class = 'warning';
                    $message = '🔔 Due Now';
                }
            } else {
                $status = 'upcoming';
                $status_class = 'info';
                $message = '⏳ Upcoming';
            }
            
            // Calculate due date (approximate)
            $due_date = null;
            if (!$is_completed) {
                $birth_date = new DateTime($this->child_data['date_of_birth']);
                $due_date = clone $birth_date;
                $due_date->modify("+{$rule['age_min']} months");
            }
            
            $recommendations[] = [
                'id' => $rule['id'],
                'name' => $rule['name'],
                'dose' => $rule['dose'],
                'age_group' => $this->getAgeGroupName($rule['age_min']),
                'status' => $status,
                'status_class' => $status_class,
                'message' => $message,
                'priority' => $rule['priority'],
                'notes' => $rule['notes'],
                'due_date' => $due_date ? $due_date->format('d M Y') : null,
                'completed' => $is_completed
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get summary statistics
     */
    public function getSummary() {
        $recs = $this->getRecommendations();
        $summary = [
            'total' => count($recs),
            'completed' => 0,
            'due' => 0,
            'overdue' => 0,
            'upcoming' => 0
        ];
        
        foreach ($recs as $r) {
            $summary[$r['status']]++;
        }
        
        $summary['completion_percentage'] = $summary['total'] > 0 
            ? round(($summary['completed'] / $summary['total']) * 100) 
            : 0;
            
        return $summary;
    }
    
    /**
     * Get next due vaccine
     */
    public function getNextDue() {
        $recs = $this->getRecommendations();
        
        foreach ($recs as $rec) {
            if ($rec['status'] == 'due' || $rec['status'] == 'overdue') {
                return $rec;
            }
        }
        
        foreach ($recs as $rec) {
            if ($rec['status'] == 'upcoming') {
                return $rec;
            }
        }
        
        return null;
    }
    
    /**
     * Get overdue vaccines
     */
    public function getOverdueVaccines() {
        $recs = $this->getRecommendations();
        $overdue = [];
        
        foreach ($recs as $rec) {
            if ($rec['status'] == 'overdue') {
                $overdue[] = $rec;
            }
        }
        
        return $overdue;
    }
    
    /**
     * Get age group name from months
     */
    private function getAgeGroupName($months) {
        if ($months == 0) return 'At Birth';
        if ($months == 1.5) return '6 Weeks';
        if ($months == 2.5) return '10 Weeks';
        if ($months == 3.5) return '14 Weeks';
        if ($months == 9) return '9 Months';
        if ($months == 12) return '12 Months';
        if ($months == 18) return '18 Months';
        if ($months >= 48 && $months <= 60) return '4-5 Years';
        if ($months >= 132 && $months <= 144) return '11-12 Years';
        if ($months >= 180) return '15-16 Years';
        return $months . ' Months';
    }
    
    /**
     * Get child name
     */
    public function getChildName() {
        return $this->child_data['full_name'];
    }
    
    /**
     * Get child age text
     */
    public function getChildAgeText() {
        $years = $this->child_data['age_years'];
        $months = $this->child_data['age_months'] % 12;
        
        if ($years > 0) {
            return $years . " year" . ($years > 1 ? "s" : "") . 
                   ($months > 0 ? " " . $months . " month" . ($months > 1 ? "s" : "") : "");
        } else {
            return $months . " month" . ($months > 1 ? "s" : "");
        }
    }
}
?>