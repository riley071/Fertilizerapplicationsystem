<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Get supplier
$stmt = $conn->prepare("SELECT s.*, u.email, u.phone as user_phone FROM suppliers s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Get specific order invoice
$order_id = isset($_GET['order']) ? (int) $_GET['order'] : null;
$invoice = null;

if ($order_id) {
    $stmt = $conn->prepare("
        SELECT o.*, p.*, f.name as fertilizer_name, f.type as fertilizer_type, 
               f.batch_no, f.npk_value
        FROM orders o
        LEFT JOIN payments p ON o.id = p.order_id
        JOIN fertilizers f ON o.fertilizer_id = f.id
        WHERE o.id = ? AND o.supplier_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all invoices (paid orders)
$invoices = $conn->query("
    SELECT o.*, p.payment_status, p.payment_date, p.amount_paid, p.payment_method,
           f.name as fertilizer_name
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    WHERE o.supplier_id = {$supplier_id} AND o.status IN ('Approved', 'Dispatched', 'Delivered')
    ORDER BY o.order_date DESC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoices</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .invoice-preview {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        .invoice-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
        }
        .invoice-body { padding: 30px; }
        .invoice-table th { background: #f8f9fa; }
        .invoice-total { background: #f8f9fa; border-radius: 8px; }
        .invoice-item { transition: all 0.2s; }
        .invoice-item:hover { background: #f8f9fa; }
        @media print {
            .no-print { display: none !important; }
            .invoice-preview { box-shadow: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <?php if ($invoice): ?>
        <!-- Single Invoice View -->
        <div class="no-print mb-4 d-flex justify-content-between align-items-center">
            <a href="invoices.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Invoices
            </a>
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Print Invoice
                </button>
                <button onclick="downloadInvoice()" class="btn btn-success">
                    <i class="bi bi-download"></i> Download PDF
                </button>
            </div>
        </div>

        <div class="invoice-preview">
            <div class="invoice-header">
                <div class="row">
                    <div class="col-6">
                        <h3 class="mb-1">INVOICE</h3>
                        <p class="mb-0 opacity-75">#INV-<?= str_pad($invoice['id'], 6, '0', STR_PAD_LEFT) ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h5 class="mb-1"><?= htmlspecialchars($supplier['company_name']) ?></h5>
                        <small class="opacity-75">
                            <?= htmlspecialchars($supplier['address'] ?? '') ?><br>
                            <?= htmlspecialchars($supplier['email']) ?><br>
                            <?= htmlspecialchars($supplier['phone'] ?? $supplier['user_phone'] ?? '') ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="invoice-body">
                <!-- Invoice Info -->
                <div class="row mb-4">
                    <div class="col-6">
                        <strong class="text-muted">Invoice Date</strong><br>
                        <?= date('F d, Y', strtotime($invoice['order_date'])) ?>
                    </div>
                    <div class="col-6 text-end">
                        <strong class="text-muted">Status</strong><br>
                        <?php if ($invoice['payment_status'] === 'Completed'): ?>
                            <span class="badge bg-success">PAID</span>
                        <?php else: ?>
                            <span class="badge bg-warning">PENDING</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="row mb-4">
                    <div class="col-6">
                        <strong class="text-muted">Order Reference</strong><br>
                        #<?= $invoice['id'] ?>
                    </div>
                    <div class="col-6 text-end">
                        <?php if ($invoice['payment_date']): ?>
                            <strong class="text-muted">Payment Date</strong><br>
                            <?= date('F d, Y', strtotime($invoice['payment_date'])) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="table invoice-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($invoice['fertilizer_name']) ?></strong><br>
                                <small class="text-muted">
                                    Type: <?= htmlspecialchars($invoice['fertilizer_type']) ?>
                                    <?php if ($invoice['npk_value']): ?> | NPK: <?= htmlspecialchars($invoice['npk_value']) ?><?php endif; ?>
                                    <?php if ($invoice['batch_no']): ?><br>Batch: <?= htmlspecialchars($invoice['batch_no']) ?><?php endif; ?>
                                </small>
                            </td>
                            <td class="text-center"><?= $invoice['quantity'] ?></td>
                            <td class="text-end">MWK <?= number_format($invoice['price_per_unit'], 2) ?></td>
                            <td class="text-end">MWK <?= number_format($invoice['total_price'], 2) ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <div class="invoice-total p-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span>MWK <?= number_format($invoice['total_price'], 2) ?></span>
                            </div>
                            <?php if ($invoice['subsidy']): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Subsidy (10%)</span>
                                <span>-MWK <?= number_format($invoice['subsidy'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <strong>Total Due</strong>
                                <strong class="text-primary">MWK <?= number_format($invoice['total_price'] - ($invoice['subsidy'] ?? 0), 2) ?></strong>
                            </div>
                            <?php if ($invoice['amount_paid']): ?>
                            <div class="d-flex justify-content-between mt-2 text-success">
                                <span>Amount Paid</span>
                                <span>MWK <?= number_format($invoice['amount_paid'], 2) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Info -->
                <?php if ($invoice['payment_method']): ?>
                <div class="mt-4 p-3 bg-light rounded">
                    <strong>Payment Information</strong><br>
                    <small class="text-muted">
                        Method: <?= htmlspecialchars($invoice['payment_method']) ?>
                        <?php if ($invoice['transaction_id']): ?> | Transaction ID: <?= htmlspecialchars($invoice['transaction_id']) ?><?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="mt-5 pt-4 border-top text-center text-muted">
                    <small>
                        Thank you for your business!<br>
                        This is a computer-generated invoice.
                    </small>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Invoices List -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-receipt"></i> Invoices</h3>
            <a href="payments.php" class="btn btn-outline-success">
                <i class="bi bi-credit-card"></i> Manage Payments
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Order #</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                            <tr class="invoice-item">
                                <td><strong>#INV-<?= str_pad($inv['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                <td>#<?= $inv['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($inv['fertilizer_name']) ?><br>
                                    <small class="text-muted">Qty: <?= $inv['quantity'] ?></small>
                                </td>
                                <td><strong>MWK <?= number_format($inv['total_price'], 2) ?></strong></td>
                                <td>
                                    <?php if ($inv['payment_status'] === 'Completed'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($inv['order_date'])) ?></td>
                                <td>
                                    <a href="?order=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function downloadInvoice() {
        // For PDF download, you would integrate with a PDF library
        // For now, use print to PDF
        alert('To save as PDF, use Print and select "Save as PDF" as the destination.');
        window.print();
    }
</script>
</body>
</html>