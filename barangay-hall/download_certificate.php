<?php
require_once '../includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Check if user is staff or the application owner
$is_staff = in_array($_SESSION['role'] ?? '', ['admin', 'barangay_staff', 'barangay_hall', 'encoder1', 'encoder2', 'encoder3']);

require_once '../includes/database.php';
$db = new Database();
$conn = $db->getConnection();

// Get application ID
$application_id = $_GET['id'] ?? '';

if (empty($application_id)) {
    http_response_code(400);
    exit('Application ID required');
}

// Get application details
try {
    // Staff can download any certificate, residents can only download their own
    if ($is_staff) {
        $stmt = $conn->prepare("
            SELECT a.*, u.full_name, u.email, u.address, u.phone
            FROM applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$application_id]);
    } else {
        $stmt = $conn->prepare("
            SELECT a.*, u.full_name, u.email, u.address, u.phone
            FROM applications a
            JOIN users u ON a.user_id = u.id
            WHERE a.id = ? AND a.user_id = ?
        ");
        $stmt->execute([$application_id, $_SESSION['user_id']]);
    }
    
    $application = $stmt->fetch();
    
    if (!$application) {
        http_response_code(404);
        exit('Application not found');
    }
    
    // Only check approval status for residents, staff can download anytime
    if (!$is_staff && $application['status'] !== 'approved') {
        http_response_code(403);
        exit('Certificate not available - application not approved');
    }
    
    // Get service details based on service_type
    $service_table = ($application['service_type'] === 'barangay') ? 'barangay_services' : 'health_services';
    $stmt = $conn->prepare("SELECT service_name, requirements FROM $service_table WHERE id = ?");
    $stmt->execute([$application['service_id']]);
    $service = $stmt->fetch();
    
    if (!$service) {
        http_response_code(404);
        exit('Service not found');
    }
    
    // Merge service details into application array
    $application['service_name'] = $service['service_name'];
    $application['requirements'] = $service['requirements'];
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Error fetching application: ' . $e->getMessage());
}

// Generate HTML content for PDF
$service_name = strtolower($application['service_name']);
$full_name = htmlspecialchars($application['full_name']);
$address = htmlspecialchars($application['address']);
$date = date('F j, Y');
// Use processed_date if available, otherwise use current date
$approval_date = $application['processed_date'] ?? date('Y-m-d H:i:s');
$issued_date = date('jS \d\a\y \o\f F Y', strtotime($approval_date));

// Determine certificate type and generate content
if (strpos($service_name, 'clearance') !== false) {
    $cert_title = 'BARANGAY CLEARANCE';
    $cert_content = "
        <p style='text-align: justify; line-height: 1.8;'>
            This certifies that <strong>$full_name</strong>, years old, is a bona fide resident of this barangay with a postal address at <strong>$address</strong>, and has resided in this barangay for years.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            Upon verification of records, the said individual has no derogatory record and is known to have a good moral standing in the community.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            This certification is issued upon the request of the above-named person as <strong>Barangay Clearance</strong>.
        </p>
    ";
} elseif (strpos($service_name, 'indigency') !== false) {
    $cert_title = 'CERTIFICATE OF INDIGENCY';
    $cert_content = "
        <p style='text-align: justify; line-height: 1.8;'>
            This is to certify that <strong>$full_name</strong>, years old, is a bona fide resident of <strong>$address</strong>.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            This is to certify further that the above-named person belongs to an <strong>INDIGENT FAMILY</strong> in this barangay.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            This certification is issued upon the request of the above-named person for whatever legal purpose it may serve.
        </p>
    ";
} elseif (strpos($service_name, 'residency') !== false) {
    $cert_title = 'CERTIFICATE OF RESIDENCY';
    $cert_content = "
        <p style='text-align: justify; line-height: 1.8;'>
            This is to certify that <strong>$full_name</strong>, years old, is a bona fide resident of <strong>$address</strong>, and has been residing in this barangay for years.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            This certification is issued upon the request of the above-named person for whatever legal purpose it may serve.
        </p>
    ";
} else {
    $cert_title = 'BARANGAY CERTIFICATE';
    $cert_content = "
        <p style='text-align: justify; line-height: 1.8;'>
            This is to certify that <strong>$full_name</strong> is a bona fide resident of <strong>$address</strong>.
        </p>
        <p style='text-align: justify; line-height: 1.8; margin-top: 20px;'>
            This certification is issued upon the request of the above-named person for whatever legal purpose it may serve.
        </p>
    ";
}

// Output HTML for browser to print to PDF
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $cert_title; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page { margin: 40px; size: letter; }
        body { font-family: 'Times New Roman', serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h3 { margin: 5px 0; font-size: 14px; }
        .header h1 { margin: 20px 0; font-size: 24px; text-decoration: underline; }
        .content { margin: 30px 0; font-size: 14px; }
        .signature { margin-top: 60px; }
        .signature-line { border-top: 2px solid #000; width: 250px; margin: 0 auto; padding-top: 5px; text-align: center; }
        .note { margin-top: 40px; font-size: 12px; font-style: italic; }
        .footer { margin-top: 30px; font-size: 12px; }
        @media print {
            body { margin: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <div id="certificate">
        <div class="header">
            <h3>REPUBLIC OF THE PHILIPPINES</h3>
            <h3>CITY OF CALOOCAN</h3>
            <h3>OFFICE OF THE PUNONG BARANGAY</h3>
            <h1><?php echo $cert_title; ?></h1>
        </div>
        
        <div class="content">
            <p><strong><?php echo $date; ?></strong></p>
            <?php echo $cert_content; ?>
            <p style="margin-top: 30px;">Issued this <?php echo $issued_date; ?> at Barangay 172 Urduja, Caloocan City.</p>
        </div>
        
        <div class="signature">
            <div class="signature-line">
                <strong>Signature of the Bearer</strong>
            </div>
        </div>
        
        <div class="note">
            <p><strong>NOTE:</strong> Any mark, erasure or alteration of any entries herein will invalidate this certification.</p>
            <p><strong>NOT VALID WITHOUT DRY SEAL</strong></p>
        </div>
        
        <div class="footer">
            <p>📞 8452-15-51 • 8842-80-61</p>
            <p>📧 barangay172202@urduja@gmail.com</p>
            <p>📍 Magat Salamat Street, Urduja Village, Caloocan City</p>
        </div>
    </div>
    
    <script>
        window.onload = function() {
            const element = document.getElementById('certificate');
            const opt = {
                margin: [10, 10, 10, 10],
                filename: '<?php echo str_replace(' ', '_', $cert_title) . '_' . $application_id; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, letterRendering: true },
                jsPDF: { unit: 'mm', format: 'letter', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                // Close window after download starts
                setTimeout(() => window.close(), 1000);
            }).catch(err => {
                console.error('PDF generation error:', err);
                alert('Failed to generate PDF. Please try again.');
            });
        };
    </script>
</body>
</html>
