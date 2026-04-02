<?php
// send_expiry_reminders.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // Assuming PHPMailer installed via Composer

include('../includes/db.php');

$today = date('Y-m-d');
$target_date = date('Y-m-d', strtotime('+30 days'));

// Get certificates expiring in 30 days, status Approved only
$sql = "SELECT c.id, c.expires_on, u.email, u.full_name
        FROM certificates c
        JOIN users u ON c.supplier_id = u.id
        WHERE c.status = 'Approved' AND c.expires_on = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $target_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';  // Set your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@example.com'; // SMTP username
            $mail->Password = 'your-email-password';   // SMTP password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('no-reply@yourdomain.com', 'Fertilizer Certification System');
            $mail->addAddress($row['email'], $row['full_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Certificate Expiry Reminder';
            $mail->Body = "
                <p>Dear {$row['full_name']},</p>
                <p>This is a reminder that your fertilizer supplier certificate (ID: {$row['id']}) is set to expire on <strong>{$row['expires_on']}</strong>.</p>
                <p>Please take necessary action to renew or update your certificate before the expiry date.</p>
                <p>Thank you.</p>
            ";

            $mail->send();
            echo "Reminder sent to {$row['email']}<br>";
        } catch (Exception $e) {
            echo "Mailer Error for {$row['email']}: {$mail->ErrorInfo}<br>";
        }
    }
} else {
    echo "No certificates expiring in 30 days.";
}
