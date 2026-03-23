<?php
/**
 * download_schedule.php
 * Description: Generate PDF of complete vaccination schedule
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database
require_once 'db_config.php';

// Include TCPDF library
require_once('tcpdf/tcpdf.php');

// Fetch vaccines data
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

// Organize by age group
$schedule = [];
while ($vaccine = $vaccines_result->fetch_assoc()) {
    $age_group = $vaccine['age_group'];
    if (!isset($schedule[$age_group])) {
        $schedule[$age_group] = [];
    }
    $schedule[$age_group][] = $vaccine;
}

$age_groups_order = [
    'At Birth', '6 Weeks', '10 Weeks', '14 Weeks', '9 Months',
    '12 Months', '18 Months', '4-5 Years', '11-12 Years', '15-16 Years'
];

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        // Set font
        $this->SetFont('helvetica', 'B', 16);
        
        // Title
        $this->Cell(0, 15, 'EPI Vaccination Schedule - Pakistan', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Line break
        $this->Ln(10);
        
        // Subtitle
        $this->SetFont('helvetica', '', 11);
        $this->Cell(0, 10, 'Expanded Program on Immunization (0-18 Years)', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(15);
    }
    
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        
        // Generated date
        $this->Cell(0, 10, 'Generated: ' . date('d M Y'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Create PDF instance
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('VaccineCare');
$pdf->SetAuthor('Vaccination Management System');
$pdf->SetTitle('EPI Vaccination Schedule');
$pdf->SetSubject('Child Immunization Schedule');

// Set margins
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(20);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Introduction
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Complete Immunization Schedule for Children (0-18 Years)', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->MultiCell(0, 10, 'All vaccines listed below are provided FREE of cost at all government hospitals and EPI centers across Pakistan. Parents are advised to follow this schedule for complete protection of their children.', 0, 'L', 0, 1, '', '', true);
$pdf->Ln(5);

// Important notes
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'Important Notes:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 6, '• Bring child\'s vaccination card to every visit
• If a dose is missed, consult your doctor for catch-up schedule
• Keep vaccination record safe for school admissions
• Inform doctor if child is sick on appointment day', 0, 'L', 0, 1, '', '', true);
$pdf->Ln(5);

// Create table header
$pdf->SetFillColor(42, 157, 143); // #2A9D8F color
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);

$pdf->Cell(35, 12, 'Age', 1, 0, 'C', 1);
$pdf->Cell(55, 12, 'Vaccine', 1, 0, 'C', 1);
$pdf->Cell(20, 12, 'Dose', 1, 0, 'C', 1);
$pdf->Cell(70, 12, 'Protects Against', 1, 1, 'C', 1);

// Reset text color
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

// Table data
$fill = 0;
foreach ($age_groups_order as $age_group) {
    if (!isset($schedule[$age_group])) continue;
    
    $first = true;
    foreach ($schedule[$age_group] as $vaccine) {
        $pdf->SetFillColor(245, 245, 245);
        
        // Age column (with rowspan effect)
        if ($first) {
            $age_text = $age_group;
            $first = false;
        } else {
            $age_text = '';
        }
        
        // Disease information
        $disease = '';
        if ($vaccine['vaccine_name'] == 'BCG') $disease = 'Tuberculosis';
        elseif (strpos($vaccine['vaccine_name'], 'Pentavalent') !== false) $disease = 'Diphtheria, Tetanus, Pertussis, Hepatitis B, Hib';
        elseif (strpos($vaccine['vaccine_name'], 'PCV') !== false) $disease = 'Pneumococcal diseases';
        elseif (strpos($vaccine['vaccine_name'], 'Rotavirus') !== false) $disease = 'Severe diarrhea';
        elseif (strpos($vaccine['vaccine_name'], 'IPV') !== false || strpos($vaccine['vaccine_name'], 'OPV') !== false) $disease = 'Polio';
        elseif (strpos($vaccine['vaccine_name'], 'Measles') !== false) $disease = 'Measles';
        elseif (strpos($vaccine['vaccine_name'], 'MMR') !== false) $disease = 'Measles, Mumps, Rubella';
        elseif (strpos($vaccine['vaccine_name'], 'Typhoid') !== false) $disease = 'Typhoid fever';
        elseif (strpos($vaccine['vaccine_name'], 'Hepatitis') !== false) $disease = 'Hepatitis B';
        elseif (strpos($vaccine['vaccine_name'], 'DT') !== false) $disease = 'Diphtheria, Tetanus';
        elseif (strpos($vaccine['vaccine_name'], 'Tdap') !== false) $disease = 'Tetanus, Diphtheria, Pertussis';
        elseif (strpos($vaccine['vaccine_name'], 'HPV') !== false) $disease = 'Human Papillomavirus';
        elseif (strpos($vaccine['vaccine_name'], 'Vitamin') !== false) $disease = 'Vitamin A deficiency';
        else $disease = 'Multiple diseases';
        
        $pdf->Cell(35, 8, $age_text, 1, 0, 'L', $fill);
        $pdf->Cell(55, 8, $vaccine['vaccine_name'], 1, 0, 'L', $fill);
        $pdf->Cell(20, 8, 'Dose ' . $vaccine['dose_number'], 1, 0, 'C', $fill);
        $pdf->Cell(70, 8, $disease, 1, 1, 'L', $fill);
        
        $fill = !$fill;
    }
}

// Footer note
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->MultiCell(0, 5, 'Source: Expanded Program on Immunization (EPI) Pakistan. For more information, visit your nearest EPI center or consult your healthcare provider.', 0, 'C', 0, 1, '', '', true);

// Output PDF
$pdf->Output('EPI_Vaccination_Schedule.pdf', 'D');
exit;
?>