<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scan QR Code - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { background: #f4f6f9; }
        #reader {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border: 3px solid #28a745;
            border-radius: 10px;
            overflow: hidden;
        }
        .result-box {
            display: none;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .verification-card {
            border-left: 4px solid #28a745;
        }
        .scanner-controls {
            position: sticky;
            top: 10px;
            z-index: 100;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-upc-scan"></i> Scan & Verify QR Code</h3>
            <a href="generate_qr.php" class="btn btn-outline-success">
                <i class="bi bi-arrow-left"></i> Back to QR Management
            </a>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-camera"></i> Scanner
                    </div>
                    <div class="card-body">
                        <div class="scanner-controls mb-3">
                            <button id="startButton" class="btn btn-success w-100">
                                <i class="bi bi-play-circle"></i> Start Scanner
                            </button>
                            <button id="stopButton" class="btn btn-danger w-100" style="display:none;">
                                <i class="bi bi-stop-circle"></i> Stop Scanner
                            </button>
                        </div>
                        
                        <div id="reader"></div>
                        
                        <div class="mt-3 text-center text-muted" id="scannerStatus">
                            <i class="bi bi-info-circle"></i> Click "Start Scanner" to begin
                        </div>

                        <!-- Manual Input Option -->
                        <div class="mt-4">
                            <hr>
                            <h6 class="text-muted"><i class="bi bi-keyboard"></i> Or Enter Code Manually</h6>
                            <div class="input-group">
                                <input type="text" id="manualCode" class="form-control" placeholder="Paste QR code here...">
                                <button class="btn btn-outline-success" onclick="verifyManualCode()">
                                    <i class="bi bi-check-circle"></i> Verify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Result Display -->
                <div id="resultBox" class="result-box">
                    <div class="card border-0 shadow-sm verification-card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-shield-check"></i> Verification Result</h5>
                        </div>
                        <div class="card-body" id="resultContent">
                            <!-- Dynamic content will be inserted here -->
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6><i class="bi bi-info-circle text-primary"></i> Instructions</h6>
                        <ol class="small text-muted ps-3 mb-0">
                            <li class="mb-2">Click "Start Scanner" to activate camera</li>
                            <li class="mb-2">Point camera at QR code</li>
                            <li class="mb-2">Wait for automatic detection</li>
                            <li class="mb-2">View verification details on the right</li>
                            <li>Alternatively, paste code manually below</li>
                        </ol>
                        
                        <div class="alert alert-info mt-3 mb-0">
                            <small><i class="bi bi-lightbulb"></i> <strong>Tip:</strong> Ensure good lighting and hold the QR code steady for best results.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let html5QrCode;
let isScanning = false;

const startButton = document.getElementById('startButton');
const stopButton = document.getElementById('stopButton');
const scannerStatus = document.getElementById('scannerStatus');
const resultBox = document.getElementById('resultBox');
const resultContent = document.getElementById('resultContent');

startButton.addEventListener('click', startScanner);
stopButton.addEventListener('click', stopScanner);

async function startScanner() {
    try {
        html5QrCode = new Html5Qrcode("reader");
        
        await html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            onScanSuccess,
            onScanFailure
        );
        
        isScanning = true;
        startButton.style.display = 'none';
        stopButton.style.display = 'block';
        scannerStatus.innerHTML = '<i class="bi bi-camera-video text-success"></i> Scanner active - Point at QR code';
    } catch (err) {
        console.error('Scanner error:', err);
        scannerStatus.innerHTML = '<i class="bi bi-exclamation-triangle text-danger"></i> Camera access denied or unavailable';
    }
}

async function stopScanner() {
    if (html5QrCode && isScanning) {
        await html5QrCode.stop();
        html5QrCode.clear();
        isScanning = false;
        startButton.style.display = 'block';
        stopButton.style.display = 'none';
        scannerStatus.innerHTML = '<i class="bi bi-info-circle"></i> Scanner stopped';
    }
}

function onScanSuccess(decodedText, decodedResult) {
    // Stop scanner after successful scan
    stopScanner();
    
    // Verify the QR code
    verifyQRCode(decodedText);
}

function onScanFailure(error) {
    // Silent - scanning continues
}

function verifyManualCode() {
    const code = document.getElementById('manualCode').value.trim();
    if (!code) {
        alert('Please enter a code');
        return;
    }
    verifyQRCode(code);
}

async function verifyQRCode(codeOrUrl) {
    // Extract code from URL if full URL is scanned
    let code = codeOrUrl;
    if (codeOrUrl.includes('qr=')) {
        const urlParams = new URLSearchParams(codeOrUrl.split('?')[1]);
        code = urlParams.get('qr');
    }
    
    resultContent.innerHTML = '<div class="text-center"><div class="spinner-border text-success"></div><p class="mt-2">Verifying...</p></div>';
    resultBox.style.display = 'block';
    
    try {
        const response = await fetch('verify_qr.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'code=' + encodeURIComponent(code)
        });
        
        const data = await response.json();
        
        if (data.success) {
            displaySuccessResult(data.qr);
        } else {
            displayErrorResult(data.message);
        }
    } catch (error) {
        displayErrorResult('Verification failed. Please try again.');
    }
}

function displaySuccessResult(qr) {
    const statusBadge = qr.is_valid 
        ? '<span class="badge bg-success">Valid & Active</span>'
        : '<span class="badge bg-danger">Invalid/Expired</span>';
    
    const expiryInfo = qr.expires_at 
        ? `<strong>Expires:</strong> ${formatDate(qr.expires_at)}`
        : '<strong>Expires:</strong> <span class="text-muted">Never</span>';
    
    resultContent.innerHTML = `
        <div class="text-center mb-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
        </div>
        
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted">Status:</span>
                ${statusBadge}
            </div>
            ${qr.is_valid ? '' : '<div class="alert alert-warning mb-0"><small>' + qr.reason + '</small></div>'}
        </div>
        
        <hr>
        
        <div class="small">
            <p class="mb-2"><strong>QR ID:</strong> #${qr.id}</p>
            <p class="mb-2"><strong>Purpose:</strong> <span class="badge bg-primary">${qr.purpose.replace('_', ' ')}</span></p>
            <p class="mb-2"><strong>Created:</strong> ${formatDate(qr.created_at)}</p>
            <p class="mb-2">${expiryInfo}</p>
            <p class="mb-2"><strong>Usage Count:</strong> <span class="badge bg-info">${qr.usage_count} times</span></p>
            <p class="mb-0"><strong>Created By:</strong> ${qr.created_by_name}</p>
        </div>
        
        <div class="mt-3">
            <a href="${qr.target_url}" target="_blank" class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-box-arrow-up-right"></i> Open Target URL
            </a>
        </div>
        
        <div class="mt-2">
            <button class="btn btn-sm btn-success w-100" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Scan Another
            </button>
        </div>
    `;
}

function displayErrorResult(message) {
    resultContent.innerHTML = `
        <div class="text-center mb-3">
            <i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>
        </div>
        
        <div class="alert alert-danger mb-3">
            <strong>Verification Failed</strong><br>
            ${message}
        </div>
        
        <button class="btn btn-sm btn-success w-100" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Try Again
        </button>
    `;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (isScanning) {
        stopScanner();
    }
});
</script>
</body>
</html>