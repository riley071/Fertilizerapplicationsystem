<?php
// includes/sms_helper.php
// SMS Notifications Helper Functions

/**
 * Send SMS using Africa's Talking API or similar SMS gateway
 * For Malawi, you can use: Africa's Talking, Twilio, or local providers
 */

class SMSNotification {
    private $api_key;
    private $username;
    private $sender_id;
    
    public function __construct() {
        // Configure your SMS gateway credentials
        $this->api_key = 'atsk_c1bb6d37afdda2864f35e7105b43e7d0034329afb120fa9232acbce3af7fa9a41bc0c8a2'; // Get from SMS provider
        $this->username = 'newusername';
        $this->sender_id = 'FertilizerSys'; // Your sender name
    }
    
    /**
     * Send SMS via Africa's Talking (recommended for Malawi)
     */
    public function sendSMS($phone_number, $message) {
        // Clean phone number (ensure it has country code)
        $phone = $this->formatPhoneNumber($phone_number);
        
        // Africa's Talking API endpoint
        $url = 'https://api.africastalking.com/version1/messaging';
        
        $data = [
            'username' => $this->username,
            'to' => $phone,
            'message' => $message,
            'from' => $this->sender_id
        ];
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $this->api_key
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log SMS
        $this->logSMS($phone, $message, $http_code, $response);
        
        return [
            'success' => $http_code == 200 || $http_code == 201,
            'response' => json_decode($response, true),
            'http_code' => $http_code
        ];
    }
    
    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone) {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add Malawi country code if not present
        if (substr($phone, 0, 1) === '0') {
            $phone = '+265' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+265' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Log SMS for tracking and debugging
     */
    private function logSMS($phone, $message, $status_code, $response) {
        global $conn;
        
        $stmt = $conn->prepare("INSERT INTO sms_logs (phone_number, message, status_code, response, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssis", $phone, $message, $status_code, $response);
        $stmt->execute();
        $stmt->close();
    }
    
    // ===== NOTIFICATION TEMPLATES =====
    
    /**
     * Order Confirmation SMS
     */
    public function sendOrderConfirmation($phone, $order_id, $fertilizer_name, $quantity) {
        $message = "Order #$order_id confirmed! You ordered $quantity units of $fertilizer_name. We'll notify you when it's approved. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Order Approved SMS
     */
    public function sendOrderApproved($phone, $order_id, $estimated_delivery) {
        $message = "Great news! Order #$order_id has been approved. Estimated delivery: $estimated_delivery. Track your order on our portal. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Order Dispatched SMS
     */
    public function sendOrderDispatched($phone, $order_id, $driver_name, $driver_phone) {
        $message = "Order #$order_id is on the way! Driver: $driver_name ($driver_phone). You'll receive another SMS when delivered. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Order Delivered SMS
     */
    public function sendOrderDelivered($phone, $order_id) {
        $message = "Order #$order_id has been delivered! Thank you for your business. Please confirm receipt on our portal. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Payment Received SMS
     */
    public function sendPaymentReceived($phone, $order_id, $amount) {
        $message = "Payment of MWK " . number_format($amount, 2) . " received for Order #$order_id. Thank you! - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Payment Reminder SMS
     */
    public function sendPaymentReminder($phone, $order_id, $amount_due) {
        $message = "Reminder: Payment of MWK " . number_format($amount_due, 2) . " pending for Order #$order_id. Please complete payment to avoid delays. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Certificate Approved SMS
     */
    public function sendCertificateApproved($phone, $certificate_number) {
        $message = "Your certificate #$certificate_number has been approved! Download it from our portal. Valid until expiry date. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Certificate Expiry Warning SMS
     */
    public function sendCertificateExpiryWarning($phone, $certificate_number, $days_remaining) {
        $message = "Alert! Your certificate #$certificate_number expires in $days_remaining days. Please renew to avoid service interruption. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Low Stock Alert SMS (for admin)
     */
    public function sendLowStockAlert($phone, $fertilizer_name, $current_stock, $minimum_stock) {
        $message = "ALERT: $fertilizer_name stock is low! Current: $current_stock, Minimum: $minimum_stock. Reorder required. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Account Verification SMS
     */
    public function sendVerificationCode($phone, $code) {
        $message = "Your verification code is: $code. Valid for 10 minutes. Do not share this code. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Password Reset SMS
     */
    public function sendPasswordResetCode($phone, $code) {
        $message = "Password reset code: $code. Valid for 15 minutes. If you didn't request this, please ignore. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
    
    /**
     * Welcome SMS for new users
     */
    public function sendWelcomeSMS($phone, $full_name) {
        $message = "Welcome to FertilizerSys, $full_name! Your account is active. Login at [your-url] or contact support for help. - FertilizerSys";
        return $this->sendSMS($phone, $message);
    }
}

// ===== DATABASE SCHEMA FOR SMS LOGS =====
/*
CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `phone_number` (`phone_number`),
  KEY `sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

// ===== USAGE EXAMPLES =====

// Example 1: Send order confirmation
/*
require_once('includes/sms_helper.php');
$sms = new SMSNotification();
$result = $sms->sendOrderConfirmation('+265999123456', 123, 'NPK Fertilizer', 50);
if ($result['success']) {
    echo "SMS sent successfully!";
}
*/

// Example 2: In your order processing code
/*
// After order is created
$stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bind_param("i", $supplier_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$sms = new SMSNotification();
$sms->sendOrderConfirmation($user['phone'], $order_id, $fertilizer_name, $quantity);
*/

// Example 3: Send certificate expiry reminders (cron job)
/*
// This can be run daily via cron job
require_once('includes/db.php');
require_once('includes/sms_helper.php');

$sms = new SMSNotification();

// Find certificates expiring in 30 days
$query = "SELECT c.certificate_number, u.phone 
          FROM certificates c 
          JOIN users u ON c.supplier_id = u.id 
          WHERE c.expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND c.status = 'Approved'";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $days_remaining = floor((strtotime($row['expires_on']) - time()) / 86400);
    $sms->sendCertificateExpiryWarning($row['phone'], $row['certificate_number'], $days_remaining);
}
*/

// Example 4: Send low stock alerts (cron job)
/*
// Run this hourly or daily
$query = "SELECT f.name, f.stock_remaining, f.minimum_stock, u.phone 
          FROM fertilizers f 
          JOIN users u ON u.role = 'admin'
          WHERE f.stock_remaining <= f.minimum_stock";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $sms->sendLowStockAlert($row['phone'], $row['name'], $row['stock_remaining'], $row['minimum_stock']);
}
*/

// ===== SMS SETTINGS PAGE (admin/sms_settings.php) =====
/*

*/
?>