<?php
/**
 * Generate Vaccination Certificate
 */

require_once 'db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get record ID from URL
$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($record_id == 0) {
    die("Invalid request");
}

// Fetch record details
$query = "SELECT 
            vr.*,
            c.full_name as child_name,
            c.date_of_birth,
            c.gender,
            c.blood_group,
            u.full_name as parent_name,
            v.vaccine_name,
            v.dose_number,
            h.id as hospital_id,
            hu.full_name as hospital_name,
            hu.address as hospital_address,
            hu.phone as hospital_phone
          FROM vaccination_records vr
          JOIN children c ON vr.child_id = c.id
          JOIN parents p ON c.parent_id = p.id
          JOIN users u ON p.user_id = u.id
          JOIN vaccines v ON vr.vaccine_id = v.id
          JOIN hospitals h ON vr.hospital_id = h.id
          JOIN users hu ON h.user_id = hu.id
          WHERE vr.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $record_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();

if (!$record) {
    die("Record not found");
}

// Generate unique certificate ID
$certificate_id = 'VAC-' . date('Y') . '-' . str_pad($record_id, 6, '0', STR_PAD_LEFT);

if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    require_once 'tcpdf/tcpdf.php';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('VaccineCare System');
    $pdf->SetAuthor('VaccineCare');
    $pdf->SetTitle('Vaccination Certificate - ' . $certificate_id);
    
    // Disable header and footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins and auto page break
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    $html = '
    <div style="text-align:center;">
        <h1 style="color:#2A9D8F; font-size:24pt; margin:0;">🇵🇰 VACCINATION CERTIFICATE</h1>
        <h3 style="color:#666666; margin:0;">VaccineCare Management System</h3>
        <p>Certificate ID: <strong>' . $certificate_id . '</strong></p>
    </div>
    <hr>
    <br><br>
    <table border="1" cellpadding="8" cellspacing="0" width="100%">
        <tr>
            <td width="35%" style="font-weight:bold; background-color:#f8f9fa;">Child Name</td>
            <td width="65%">' . htmlspecialchars($record["child_name"]) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Date of Birth</td>
            <td>' . date("d F Y", strtotime($record["date_of_birth"])) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Gender</td>
            <td>' . ucfirst($record["gender"]) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Blood Group</td>
            <td>' . ($record["blood_group"] ?? "Not Specified") . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Parent/Guardian</td>
            <td>' . htmlspecialchars($record["parent_name"]) . '</td>
        </tr>
        <tr>
            <td colspan="2" style="background-color:#2A9D8F; color:#ffffff; text-align:center; font-weight:bold;">
                VACCINATION DETAILS
            </td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Vaccine Name</td>
            <td>' . $record["vaccine_name"] . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Dose Number</td>
            <td>' . $record["dose_number"] . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Date Administered</td>
            <td>' . date("d F Y", strtotime($record["administered_date"])) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Hospital Name</td>
            <td>' . htmlspecialchars($record["hospital_name"]) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Hospital Address</td>
            <td>' . htmlspecialchars($record["hospital_address"]) . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Hospital Contact</td>
            <td>' . $record["hospital_phone"] . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Batch Number</td>
            <td>' . ($record["batch_number"] ?? "N/A") . '</td>
        </tr>
        <tr>
            <td style="font-weight:bold; background-color:#f8f9fa;">Next Due Date</td>
            <td>' . ($record["next_due_date"] ? date("d F Y", strtotime($record["next_due_date"])) : "Completed - No further doses") . '</td>
        </tr>
    </table>
    <br><br><br>
    <div style="text-align:center;">
        <h1 style="color:#2A9D8F;">🛡️</h1>
        <p>This is a digitally generated certificate from VaccineCare System</p>
        <p><strong>Verified & Authenticated</strong></p>
        <p>Generated on: ' . date("d F Y h:i A") . '</p>
    </div>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($certificate_id . '.pdf', 'D');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vaccination Certificate</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f4f7fc;
            margin: 0;
            padding: 20px;
        }
        .certificate {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 10px solid #2A9D8F;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2A9D8F;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2A9D8F;
            font-size: 32px;
            margin: 0;
        }
        .header h3 {
            color: #666;
            margin: 5px 0;
        }
        .content {
            margin-bottom: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
            background: #f8f9fa;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #2A9D8F;
        }
        .seal {
            font-size: 40px;
            color: #2A9D8F;
            margin: 10px 0;
        }
        .print-btn {
            background: #2A9D8F;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .print-btn:hover {
            background: #1a5f7a;
        }
        .pdf-btn {
            background: #dc3545;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        .pdf-btn:hover {
            background: #bb2d3b;
        }
        @media print {
            .print-btn {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .certificate {
                border: 2px solid #000;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="header">
            <h1>🇵🇰 VACCINATION CERTIFICATE</h1>
            <h3>VaccineCare Management System</h3>
            <p>Certificate ID: <strong><?php echo $certificate_id; ?></strong></p>
        </div>
        
        <div class="content">
            <table class="info-table">
                <tr>
                    <td>Child Name</td>
                    <td><?php echo htmlspecialchars($record['child_name']); ?></td>
                </tr>
                <tr>
                    <td>Date of Birth</td>
                    <td><?php echo date('d F Y', strtotime($record['date_of_birth'])); ?></td>
                </tr>
                <tr>
                    <td>Gender</td>
                    <td><?php echo ucfirst($record['gender']); ?></td>
                </tr>
                <tr>
                    <td>Blood Group</td>
                    <td><?php echo $record['blood_group'] ?? 'Not Specified'; ?></td>
                </tr>
                <tr>
                    <td>Parent/Guardian</td>
                    <td><?php echo htmlspecialchars($record['parent_name']); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="background: #2A9D8F; color: white; text-align: center;">
                        <strong>VACCINATION DETAILS</strong>
                    </td>
                </tr>
                <tr>
                    <td>Vaccine Name</td>
                    <td><?php echo $record['vaccine_name']; ?></td>
                </tr>
                <tr>
                    <td>Dose Number</td>
                    <td><?php echo $record['dose_number']; ?></td>
                </tr>
                <tr>
                    <td>Date Administered</td>
                    <td><?php echo date('d F Y', strtotime($record['administered_date'])); ?></td>
                </tr>
                <tr>
                    <td>Hospital Name</td>
                    <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                </tr>
                <tr>
                    <td>Hospital Address</td>
                    <td><?php echo htmlspecialchars($record['hospital_address']); ?></td>
                </tr>
                <tr>
                    <td>Hospital Contact</td>
                    <td><?php echo $record['hospital_phone']; ?></td>
                </tr>
                <tr>
                    <td>Batch Number</td>
                    <td><?php echo $record['batch_number'] ?? 'N/A'; ?></td>
                </tr>
                <tr>
                    <td>Next Due Date</td>
                    <td>
                        <?php 
                        if ($record['next_due_date']) {
                            echo date('d F Y', strtotime($record['next_due_date']));
                        } else {
                            echo 'Completed - No further doses';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <div class="seal">🛡️</div>
            <p>This is a digitally generated certificate from VaccineCare System</p>
            <p><strong>Verified & Authenticated</strong></p>
            <p>Generated on: <?php echo date('d F Y h:i A'); ?></p>
        </div>
        
        <div style="text-align: center;">
            <a href="?id=<?php echo $record_id; ?>&download=pdf" class="print-btn pdf-btn">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
            <button class="print-btn" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Certificate
            </button>
        </div>
    </div>
</body>
</html>