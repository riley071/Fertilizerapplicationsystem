<?php
session_start();
include('../includes/db.php');

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

// Fetch certificate with supplier details
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

// Check access: only owner or admin can print
if ($role !== 'admin' && $cert['supplier_id'] != $user_id) {
    die("Access denied.");
}

// Check if certificate is valid
if ($cert['status'] !== 'Approved') {
    die("This certificate is not approved and cannot be printed.");
}

$today = date('Y-m-d');
$isExpired = ($cert['expires_on'] && $cert['expires_on'] < $today);

// Parse application details
$details = $cert['details'] ? json_decode($cert['details'], true) : [];
$businessName = $details['business_name'] ?? $cert['company_name'] ?? $cert['full_name'];
$businessAddress = $details['business_address'] ?? $cert['company_address'] ?? 'N/A';
$businessRegNo = $details['business_reg_no'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate - <?= htmlspecialchars($cert['certificate_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @page { size: A4 landscape; margin: 0; }
        
        body {
            font-family: 'Georgia', serif;
            background: #f0f0f0;
            padding: 20px;
        }
        
        .certificate {
            width: 297mm;
            height: 210mm;
            background: white;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .border-outer {
            position: absolute;
            top: 10mm; left: 10mm; right: 10mm; bottom: 10mm;
            border: 3px solid #1a5c2e;
        }
        
        .border-inner {
            position: absolute;
            top: 15mm; left: 15mm; right: 15mm; bottom: 15mm;
            border: 1px solid #1a5c2e;
        }
        
        .corner-decor {
            position: absolute;
            width: 40px; height: 40px;
            border: 3px solid #d4af37;
        }
        .corner-tl { top: 20mm; left: 20mm; border-right: none; border-bottom: none; }
        .corner-tr { top: 20mm; right: 20mm; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 20mm; left: 20mm; border-right: none; border-top: none; }
        .corner-br { bottom: 20mm; right: 20mm; border-left: none; border-top: none; }
        
        .content {
            position: absolute;
            top: 25mm; left: 25mm; right: 25mm; bottom: 25mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .header-logo {
            font-size: 18px;
            color: #1a5c2e;
            letter-spacing: 3px;
            margin-bottom: 5mm;
        }
        
        .title {
            font-size: 42px;
            color: #1a5c2e;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 8px;
            margin-bottom: 3mm;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .subtitle {
            font-size: 16px;
            color: #666;
            letter-spacing: 4px;
            margin-bottom: 8mm;
        }
        
        .cert-number {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            color: #1a5c2e;
            background: #f5f5f5;
            padding: 5px 20px;
            border-radius: 3px;
            margin-bottom: 8mm;
        }
        
        .recipient-label {
            font-size: 14px;
            color: #888;
            margin-bottom: 2mm;
        }
        
        .recipient-name {
            font-size: 32px;
            color: #1a5c2e;
            font-weight: bold;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 3mm;
            margin-bottom: 5mm;
            min-width: 300px;
        }
        
        .details {
            font-size: 13px;
            color: #555;
            margin-bottom: 5mm;
            line-height: 1.8;
        }
        
        .validity {
            display: flex;
            gap: 30mm;
            margin: 5mm 0;
        }
        
        .validity-item {
            text-align: center;
        }
        
        .validity-label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .validity-value {
            font-size: 16px;
            color: #1a5c2e;
            font-weight: bold;
        }
        
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            margin-top: auto;
            padding-top: 10mm;
        }
        
        .qr-section {
            text-align: center;
        }
        
        .qr-section img {
            width: 70px;
            height: 70px;
            border: 1px solid #ddd;
            padding: 3px;
            background: white;
        }
        
        .qr-label {
            font-size: 9px;
            color: #888;
            margin-top: 2mm;
        }
        
        .signature-section {
            text-align: center;
            min-width: 150px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 2mm;
            font-size: 12px;
            color: #555;
        }
        
        .seal {
            width: 80px;
            height: 80px;
            border: 3px solid #d4af37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #d4af37;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 120px;
            color: rgba(26, 92, 46, 0.03);
            font-weight: bold;
            pointer-events: none;
            white-space: nowrap;
        }
        
        <?php if ($isExpired): ?>
        .expired-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            font-size: 72px;
            color: rgba(220, 53, 69, 0.3);
            font-weight: bold;
            border: 8px solid rgba(220, 53, 69, 0.3);
            padding: 10px 30px;
            text-transform: uppercase;
        }
        <?php endif; ?>
        
        @media print {
            body { background: white; padding: 0; }
            .certificate { box-shadow: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #1a5c2e; color: white; border: none; border-radius: 5px;">
            🖨️ Print Certificate
        </button>
        <button onclick="window.close()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; margin-left: 10px;">
            ✕ Close
        </button>
    </div>

    <div class="certificate">
        <div class="border-outer"></div>
        <div class="border-inner"></div>
        <div class="corner-decor corner-tl"></div>
        <div class="corner-decor corner-tr"></div>
        <div class="corner-decor corner-bl"></div>
        <div class="corner-decor corner-br"></div>
        
        <div class="watermark">CERTIFIED</div>
        
        <?php if ($isExpired): ?>
        <div class="expired-stamp">EXPIRED</div>
        <?php endif; ?>
        
        <div class="content">
            <div class="header-logo">🌿 REPUBLIC OF MALAWI 🌿</div>
            <div class="header-logo">MINISTRY OF AGRICULTURE</div>
            
            <div class="title">Certificate</div>
            <div class="subtitle">Authorized Fertilizer Supplier</div>
            
            <div class="cert-number">№ <?= htmlspecialchars($cert['certificate_number']) ?></div>
            
            <div class="recipient-label">This is to certify that</div>
            <div class="recipient-name"><?= htmlspecialchars($businessName) ?></div>
            
            <div class="details">
                Registration No: <?= htmlspecialchars($businessRegNo) ?><br>
                Address: <?= htmlspecialchars($businessAddress) ?>
            </div>
            
            <div class="details">
                has been duly registered and authorized to operate as a<br>
                <strong>Licensed Fertilizer Supplier</strong> under the Fertilizer Act.
            </div>
            
            <div class="validity">
                <div class="validity-item">
                    <div class="validity-label">Date of Issue</div>
                    <div class="validity-value"><?= $cert['issued_on'] ? date('F d, Y', strtotime($cert['issued_on'])) : 'N/A' ?></div>
                </div>
                <div class="validity-item">
                    <div class="validity-label">Valid Until</div>
                    <div class="validity-value"><?= $cert['expires_on'] ? date('F d, Y', strtotime($cert['expires_on'])) : 'N/A' ?></div>
                </div>
            </div>
            
            <div class="footer">
                <div class="qr-section">
                    <?php if ($cert['qr_code_path']): ?>
                        <img src="<?= htmlspecialchars($cert['qr_code_path']) ?>" alt="QR Code">
                        <div class="qr-label">Scan to Verify</div>
                    <?php endif; ?>
                </div>
                
                <div class="seal">
                    Official<br>Seal
                </div>
                
                <div class="signature-section">
                    <div class="signature-line">
                        Director of Agriculture<br>
                        <small>Authorized Signatory</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>