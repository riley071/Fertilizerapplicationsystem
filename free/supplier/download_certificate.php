<?php
session_start();
include('../includes/db.php');

// Check if TCPDF is available, if not provide instructions
if (!file_exists('../includes/tcpdf/tcpdf.php')) {
    die("TCPDF library not found. Please install TCPDF in /includes/tcpdf/ folder. 
         Download from: https://github.com/tecnickcom/TCPDF");
}

require_once('../includes/tcpdf/tcpdf.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$cert_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$cert_id) {
    die("Invalid certificate ID.");
}

// Fetch certificate
$stmt = $conn->prepare("
    SELECT c.*, s.company_name, s.address as company_address, s.phone as company_phone,
           u.full_name, u.email, ca.details
    FROM certificates c
    LEFT JOIN suppliers s ON c.supplier_id = s.user_id
    LEFT JOIN users u ON c.supplier_id = u.id
    LEFT JOIN certificate_applications ca ON c.application_id = ca.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $cert_id);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert) {
    die("Certificate not found.");
}

// Access check
if ($role !== 'admin' && $cert['supplier_id'] != $user_id) {
    die("Access denied.");
}

if ($cert['status'] !== 'Approved') {
    die("This certificate is not approved.");
}

// Parse details
$details = $cert['details'] ? json_decode($cert['details'], true) : [];
$businessName = $details['business_name'] ?? $cert['company_name'] ?? $cert['full_name'];
$businessAddress = $details['business_address'] ?? $cert['company_address'] ?? 'N/A';
$businessRegNo = $details['business_reg_no'] ?? 'N/A';

// Create PDF
class CertificatePDF extends TCPDF {
    public function Header() {
        // Green header bar
        $this->SetFillColor(26, 92, 46);
        $this->Rect(0, 0, 297, 15, 'F');
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'This is a computer-generated certificate. Verify authenticity by scanning the QR code.', 0, 0, 'C');
    }
}

// Initialize PDF (Landscape A4)
$pdf = new CertificatePDF('L', 'mm', 'A4', true, 'UTF-8', false);

$pdf->SetCreator('Fertilizer Management System');
$pdf->SetAuthor('Ministry of Agriculture');
$pdf->SetTitle('Certificate - ' . $cert['certificate_number']);
$pdf->SetSubject('Fertilizer Supplier Certificate');

$pdf->SetMargins(20, 25, 20);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// Border
$pdf->SetDrawColor(26, 92, 46);
$pdf->SetLineWidth(1);
$pdf->Rect(10, 10, 277, 190, 'D');
$pdf->SetLineWidth(0.3);
$pdf->Rect(13, 13, 271, 184, 'D');

// Header
$pdf->SetFont('times', 'B', 14);
$pdf->SetTextColor(26, 92, 46);
$pdf->SetY(25);
$pdf->Cell(0, 8, 'REPUBLIC OF MALAWI', 0, 1, 'C');
$pdf->SetFont('times', '', 12);
$pdf->Cell(0, 6, 'MINISTRY OF AGRICULTURE', 0, 1, 'C');

// Title
$pdf->Ln(8);
$pdf->SetFont('times', 'B', 36);
$pdf->SetTextColor(26, 92, 46);
$pdf->Cell(0, 15, 'CERTIFICATE', 0, 1, 'C');

$pdf->SetFont('times', '', 14);
$pdf->SetTextColor(100);
$pdf->Cell(0, 8, 'Authorized Fertilizer Supplier', 0, 1, 'C');

// Certificate number
$pdf->Ln(5);
$pdf->SetFont('courier', 'B', 12);
$pdf->SetTextColor(26, 92, 46);
$pdf->Cell(0, 8, 'Certificate No: ' . $cert['certificate_number'], 0, 1, 'C');

// Recipient
$pdf->Ln(8);
$pdf->SetFont('times', 'I', 12);
$pdf->SetTextColor(100);
$pdf->Cell(0, 6, 'This is to certify that', 0, 1, 'C');

$pdf->SetFont('times', 'B', 24);
$pdf->SetTextColor(26, 92, 46);
$pdf->Cell(0, 12, $businessName, 0, 1, 'C');

// Underline
$pdf->SetDrawColor(212, 175, 55);
$pdf->SetLineWidth(0.5);
$nameWidth = $pdf->GetStringWidth($businessName);
$startX = (297 - $nameWidth) / 2;
$pdf->Line($startX, $pdf->GetY(), $startX + $nameWidth, $pdf->GetY());

// Details
$pdf->Ln(8);
$pdf->SetFont('times', '', 11);
$pdf->SetTextColor(80);
$pdf->Cell(0, 6, 'Registration No: ' . $businessRegNo, 0, 1, 'C');
$pdf->Cell(0, 6, 'Address: ' . $businessAddress, 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('times', '', 12);
$pdf->MultiCell(0, 6, 'has been duly registered and authorized to operate as a Licensed Fertilizer Supplier under the Fertilizer Act of Malawi.', 0, 'C');

// Validity dates
$pdf->Ln(8);
$pdf->SetFont('times', 'B', 11);
$pdf->SetTextColor(26, 92, 46);

$issuedDate = $cert['issued_on'] ? date('F d, Y', strtotime($cert['issued_on'])) : 'N/A';
$expiryDate = $cert['expires_on'] ? date('F d, Y', strtotime($cert['expires_on'])) : 'N/A';

$pdf->Cell(140, 8, 'Date of Issue: ' . $issuedDate, 0, 0, 'R');
$pdf->Cell(0, 8, 'Valid Until: ' . $expiryDate, 0, 1, 'R');

// QR Code
if ($cert['qr_code_path'] && file_exists('../' . ltrim($cert['qr_code_path'], '../'))) {
    $qrPath = '../' . ltrim($cert['qr_code_path'], '../');
    $pdf->Image($qrPath, 25, 155, 30, 30, '', '', '', false, 300);
    $pdf->SetXY(25, 186);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(128);
    $pdf->Cell(30, 4, 'Scan to Verify', 0, 0, 'C');
}

// Signature area
$pdf->SetFont('times', '', 11);
$pdf->SetTextColor(0);
$pdf->SetXY(200, 165);
$pdf->Cell(60, 5, '_______________________', 0, 1, 'C');
$pdf->SetX(200);
$pdf->Cell(60, 5, 'Director of Agriculture', 0, 1, 'C');
$pdf->SetX(200);
$pdf->SetFont('times', 'I', 9);
$pdf->Cell(60, 5, 'Authorized Signatory', 0, 1, 'C');

// Official Seal placeholder
$pdf->SetDrawColor(212, 175, 55);
$pdf->SetLineWidth(0.8);
$pdf->Circle(148.5, 170, 18, 0, 360, 'D');
$pdf->SetFont('times', 'B', 10);
$pdf->SetTextColor(212, 175, 55);
$pdf->SetXY(130, 165);
$pdf->Cell(37, 5, 'OFFICIAL', 0, 1, 'C');
$pdf->SetX(130);
$pdf->Cell(37, 5, 'SEAL', 0, 1, 'C');

// Output
$filename = 'Certificate_' . $cert['certificate_number'] . '.pdf';
$pdf->Output($filename, 'D');
exit();