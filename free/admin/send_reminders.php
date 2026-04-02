<?php
require '../includes/db.php'; // Database connection

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files (same structure as your working script)
require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';
require_once __DIR__ . '/../src/Exception.php';

// Calculate target date: 30 days from today
$targetDate = date('Y-m-d', strtotime('+30 days'));

// Select certificates that will expire in 30 days
$sql = "
    SELECT c.id, c.certificate_number, c.expires_on, u.full_name, u.email 
    FROM certificates c
    INNER JOIN users u ON c.supplier_id = u.id
    WHERE c.status = 'Approved' 
    AND c.expires_on = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $targetDate);
$stmt->execute();
$result = $stmt->get_result();

$emailsSent = 0;

while ($row = $result->fetch_assoc()) {
    $to = $row['email'];
    $name = $row['full_name'];
    $certificateNumber = $row['certificate_number'];
    $expiryDate = $row['expires_on'];

    $subject = "⚠️ Certificate Expiration Reminder - {$certificateNumber}";
    $body = "
        Dear $name,<br><br>
        This is a reminder that your fertilizer certificate 
        <strong>{$certificateNumber}</strong> will expire on 
        <strong>{$expiryDate}</strong>.<br><br>
        Please renew it in time to avoid any disruption in your operations.<br><br>
        Regards,<br>
        <strong>Fertilizer Compliance Team</strong><br>
        Umami Malawi
    ";

    $mail = new PHPMailer(true);

    try {
        // SMTP configuration (same as your working setup)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'trevorkabango6@gmail.com';
        $mail->Password   = 'zeby xyod xqis pput'; // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPDebug = 0; // Change to 2 for detailed logs during testing

        // Sender & recipient
        $mail->setFrom('noreply@umamimalawi.com', 'Umami Malawi');
        $mail->addAddress($to, $name);
        $mail->addReplyTo('support@umamimalawi.com', 'Support Team');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        $emailsSent++;

    } catch (Exception $e) {
        error_log("Email to {$to} failed: " . $mail->ErrorInfo);
    }
}

$stmt->close();
$conn->close();

// Optional: summary message in logs
if ($emailsSent > 0) {
    error_log("✅ $emailsSent certificate reminder(s) sent successfully on " . date('Y-m-d H:i:s'));
} else {
    error_log("ℹ️ No certificates expiring within 30 days found.");
}
?>
