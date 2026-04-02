<?php
$role = $_SESSION['role'] ?? null;
$currentFile = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $currentFile;
    return $currentFile === $page ? 'active' : '';
}

function isActiveSection($pages) {
    global $currentFile;
    return in_array($currentFile, $pages) ? 'show' : '';
}
?>

<!-- Mobile Toggle Button -->
<button class="btn btn-success d-lg-none position-fixed" 
        style="top: 10px; left: 10px; z-index: 1050;" 
        type="button" 
        data-bs-toggle="offcanvas" 
        data-bs-target="#sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar for Desktop -->
<div class="sidebar bg-white border-end d-none d-lg-block" style="width: 260px; min-height: 100vh; overflow-y: auto;">
    <div class="p-3 border-bottom bg-success text-white text-center">
        <h5 class="mb-0"><i class="bi bi-leaf"></i> Fertilizer System</h5>
        <small class="opacity-75"><?= ucfirst($role ?? 'User') ?> Portal</small>
    </div>

    <div class="sidebar-content p-2">
        <?php if ($role === 'admin'): ?>
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <!-- Orders Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#ordersMenu">
                    <i class="bi bi-cart-check"></i>
                    <span>Orders Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['manage_order.php', 'create_order.php']) ?>" id="ordersMenu">
                    <a href="manage_order.php" class="sidebar-sublink <?= isActive('manage_order.php') ?>">
                        <i class="bi bi-list-check"></i> All Orders
                    </a>
                </div>
            </div>

            <!-- Certificates Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#certificatesMenu">
                    <i class="bi bi-patch-check"></i>
                    <span>Certificates</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['certificates.php', 'generate_qr.php', 'verify_certificate.php']) ?>" id="certificatesMenu">
                    <a href="certificates.php" class="sidebar-sublink <?= isActive('certificates.php') ?>">
                        <i class="bi bi-file-earmark-check"></i> Manage Certificates
                    </a>
                    <a href="generate_qr.php" class="sidebar-sublink <?= isActive('generate_qr.php') ?>">
                        <i class="bi bi-qr-code"></i> Generate QR Code
                    </a>
                    <a href="verify_certificate.php" class="sidebar-sublink <?= isActive('verify_certificate.php') ?>">
                        <i class="bi bi-shield-check"></i> Verify Certificate
                    </a>
                </div>
            </div>

            <!-- Inventory Section -->
            <a href="inventory.php" class="sidebar-link <?= isActive('inventory.php') ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory</span>
            </a>

            <!-- Financial Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#financeMenu">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Financial</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['payments.php', 'invoices.php']) ?>" id="financeMenu">
                    <a href="payments.php" class="sidebar-sublink <?= isActive('payments.php') ?>">
                        <i class="bi bi-credit-card"></i> Payments
                    </a>
                </div>
            </div>

            <!-- Users & System -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#systemMenu">
                    <i class="bi bi-gear"></i>
                    <span>System Users</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['manage_users.php', 'audit_logs.php', 'settings.php']) ?>" id="systemMenu">
                    <a href="manage_users.php" class="sidebar-sublink <?= isActive('manage_users.php') ?>">
                        <i class="bi bi-people"></i> Users
                    </a>
                    <a href="audit_logs.php" class="sidebar-sublink <?= isActive('audit_logs.php') ?>">
                        <i class="bi bi-clipboard-data"></i> Audit Logs
                    </a>
                </div>
            </div>

            <!-- Reports -->
            <a href="reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>

            <!-- Messages -->
            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
                <span class="badge bg-danger ms-auto" id="unread-count" style="display: none;"></span>
            </a>

            <!-- Help -->
            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <!-- Profile -->
            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php elseif ($role === 'supplier'): ?>
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <!-- Certificates Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#certificatesMenu">
                    <i class="bi bi-award"></i>
                    <span>Certificates</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['apply_certificate.php', 'my_certificates.php']) ?>" id="certificatesMenu">
                    <a href="apply_certificate.php" class="sidebar-sublink <?= isActive('apply_certificate.php') ?>">
                        <i class="bi bi-file-plus"></i> Apply for Certificate
                    </a>
                    <a href="my_certificates.php" class="sidebar-sublink <?= isActive('my_certificates.php') ?>">
                        <i class="bi bi-patch-check"></i> My Certificates
                    </a>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#ordersMenu">
                    <i class="bi bi-cart"></i>
                    <span>Orders</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['place_order.php', 'my_orders.php']) ?>" id="ordersMenu">
                    <a href="place_order.php" class="sidebar-sublink <?= isActive('place_order.php') ?>">
                        <i class="bi bi-cart-plus"></i> Place Order
                    </a>
                    <a href="my_orders.php" class="sidebar-sublink <?= isActive('my_orders.php') ?>">
                        <i class="bi bi-bag-check"></i> My Orders
                    </a>
                </div>
            </div>

            <!-- Delivery -->
            <a href="track_delivery.php" class="sidebar-link <?= isActive('track_delivery.php') ?>">
                <i class="bi bi-truck"></i>
                <span>Track Deliveries</span>
            </a>

            <!-- Locations -->
            <a href="supplier_locations.php" class="sidebar-link <?= isActive('supplier_locations.php') ?>">
                <i class="bi bi-geo-alt"></i>
                <span>My Locations</span>
            </a>

            <!-- Financial Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#financeMenu">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Financial</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['payments.php', 'invoices.php']) ?>" id="financeMenu">
                    <a href="payments.php" class="sidebar-sublink <?= isActive('payments.php') ?>">
                        <i class="bi bi-credit-card"></i> Payments
                    </a>
                    <a href="invoices.php" class="sidebar-sublink <?= isActive('invoices.php') ?>">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </div>
            </div>

            <!-- Reports -->
            <a href="reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>

            <!-- Messages -->
            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
                <span class="badge bg-danger ms-auto" id="unread-count-supplier" style="display: none;"></span>
            </a>

            <!-- Help -->
            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <!-- Profile -->
            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php elseif ($role === 'driver'): ?>
            
            <!-- Dashboard -->
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <!-- Deliveries Section -->
            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#deliveriesMenu">
                    <i class="bi bi-truck"></i>
                    <span>Deliveries</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['my_deliveries.php', 'delivery_history.php']) ?>" id="deliveriesMenu">
                    <a href="my_deliveries.php" class="sidebar-sublink <?= isActive('my_deliveries.php') ?>">
                        <i class="bi bi-box-seam"></i> Active Deliveries
                    </a>
                    <a href="delivery_history.php" class="sidebar-sublink <?= isActive('delivery_history.php') ?>">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                </div>
            </div>

            <!-- Route & Location -->
            <a href="route_optimization.php" class="sidebar-link <?= isActive('route_optimization.php') ?>">
                <i class="bi bi-map"></i>
                <span>Route Planning</span>
            </a>

            <a href="my_location.php" class="sidebar-link <?= isActive('my_location.php') ?>">
                <i class="bi bi-geo-alt"></i>
                <span>My Location</span>
            </a>

            <!-- Messages -->
            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
                <span class="badge bg-danger ms-auto" id="unread-count-driver" style="display: none;"></span>
            </a>

            <!-- Help -->
            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <!-- Profile -->
            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php endif; ?>

        <!-- Logout -->
        <div class="mt-3 pt-3 border-top">
            <a href="../includes/logout.php" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- Offcanvas Sidebar for Mobile -->
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebar" style="width: 260px;">
    <div class="offcanvas-header bg-success text-white">
        <h5 class="offcanvas-title"><i class="bi bi-leaf"></i> Fertilizer System</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-2">
        <?php if ($role === 'admin'): ?>
            
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#ordersMenuMobile">
                    <i class="bi bi-cart-check"></i>
                    <span>Orders Management</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['manage_order.php', 'create_order.php']) ?>" id="ordersMenuMobile">
                    <a href="manage_order.php" class="sidebar-sublink <?= isActive('manage_order.php') ?>">
                        <i class="bi bi-list-check"></i> All Orders
                    </a>
                </div>
            </div>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#certificatesMenuMobile">
                    <i class="bi bi-patch-check"></i>
                    <span>Certificates</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['certificates.php', 'generate_qr.php', 'verify_certificate.php']) ?>" id="certificatesMenuMobile">
                    <a href="certificates.php" class="sidebar-sublink <?= isActive('certificates.php') ?>">
                        <i class="bi bi-file-earmark-check"></i> Manage Certificates
                    </a>
                    <a href="generate_qr.php" class="sidebar-sublink <?= isActive('generate_qr.php') ?>">
                        <i class="bi bi-qr-code"></i> Generate QR Code
                    </a>
                    <a href="verify_certificate.php" class="sidebar-sublink <?= isActive('verify_certificate.php') ?>">
                        <i class="bi bi-shield-check"></i> Verify Certificate
                    </a>
                </div>
            </div>

            <a href="inventory.php" class="sidebar-link <?= isActive('inventory.php') ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory</span>
            </a>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#financeMenuMobile">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Financial</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['payments.php', 'invoices.php']) ?>" id="financeMenuMobile">
                    <a href="payments.php" class="sidebar-sublink <?= isActive('payments.php') ?>">
                        <i class="bi bi-credit-card"></i> Payments
                    </a>
                </div>
            </div>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#systemMenuMobile">
                    <i class="bi bi-gear"></i>
                    <span>System</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['manage_users.php', 'audit_logs.php', 'settings.php']) ?>" id="systemMenuMobile">
                    <a href="manage_users.php" class="sidebar-sublink <?= isActive('manage_users.php') ?>">
                        <i class="bi bi-people"></i> Users
                    </a>
                    <a href="audit_logs.php" class="sidebar-sublink <?= isActive('audit_logs.php') ?>">
                        <i class="bi bi-clipboard-data"></i> Audit Logs
                    </a>
                </div>
            </div>

            <a href="reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>

            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
            </a>

            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php elseif ($role === 'supplier'): ?>
            
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#certificatesMenuMobile">
                    <i class="bi bi-award"></i>
                    <span>Certificates</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['apply_certificate.php', 'my_certificates.php']) ?>" id="certificatesMenuMobile">
                    <a href="apply_certificate.php" class="sidebar-sublink <?= isActive('apply_certificate.php') ?>">
                        <i class="bi bi-file-plus"></i> Apply for Certificate
                    </a>
                    <a href="my_certificates.php" class="sidebar-sublink <?= isActive('my_certificates.php') ?>">
                        <i class="bi bi-patch-check"></i> My Certificates
                    </a>
                </div>
            </div>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#ordersMenuMobile">
                    <i class="bi bi-cart"></i>
                    <span>Orders</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['place_order.php', 'my_orders.php']) ?>" id="ordersMenuMobile">
                    <a href="place_order.php" class="sidebar-sublink <?= isActive('place_order.php') ?>">
                        <i class="bi bi-cart-plus"></i> Place Order
                    </a>
                    <a href="my_orders.php" class="sidebar-sublink <?= isActive('my_orders.php') ?>">
                        <i class="bi bi-bag-check"></i> My Orders
                    </a>
                </div>
            </div>

            <a href="track_delivery.php" class="sidebar-link <?= isActive('track_delivery.php') ?>">
                <i class="bi bi-truck"></i>
                <span>Track Deliveries</span>
            </a>

            <a href="supplier_locations.php" class="sidebar-link <?= isActive('supplier_locations.php') ?>">
                <i class="bi bi-geo-alt"></i>
                <span>My Locations</span>
            </a>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#financeMenuMobile">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Financial</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['payments.php', 'invoices.php']) ?>" id="financeMenuMobile">
                    <a href="payments.php" class="sidebar-sublink <?= isActive('payments.php') ?>">
                        <i class="bi bi-credit-card"></i> Payments
                    </a>
                    <a href="invoices.php" class="sidebar-sublink <?= isActive('invoices.php') ?>">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </div>
            </div>

            <a href="reports.php" class="sidebar-link <?= isActive('reports.php') ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports</span>
            </a>

            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
            </a>

            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php elseif ($role === 'driver'): ?>
            
            <a href="dashboard.php" class="sidebar-link <?= isActive('dashboard.php') ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <div class="sidebar-section">
                <a class="sidebar-toggle collapsed" data-bs-toggle="collapse" href="#deliveriesMenuMobile">
                    <i class="bi bi-truck"></i>
                    <span>Deliveries</span>
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?= isActiveSection(['my_deliveries.php', 'delivery_history.php']) ?>" id="deliveriesMenuMobile">
                    <a href="my_deliveries.php" class="sidebar-sublink <?= isActive('my_deliveries.php') ?>">
                        <i class="bi bi-box-seam"></i> Active Deliveries
                    </a>
                    <a href="delivery_history.php" class="sidebar-sublink <?= isActive('delivery_history.php') ?>">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                </div>
            </div>

            <a href="route_optimization.php" class="sidebar-link <?= isActive('route_optimization.php') ?>">
                <i class="bi bi-map"></i>
                <span>Route Planning</span>
            </a>

            <a href="my_location.php" class="sidebar-link <?= isActive('my_location.php') ?>">
                <i class="bi bi-geo-alt"></i>
                <span>My Location</span>
            </a>

            <a href="messages.php" class="sidebar-link <?= isActive('messages.php') ?>">
                <i class="bi bi-chat-dots"></i>
                <span>Messages</span>
            </a>

            <a href="help_center.php" class="sidebar-link <?= isActive('help_center.php') ?>">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
            </a>

            <a href="profile.php" class="sidebar-link <?= isActive('profile.php') ?>">
                <i class="bi bi-person-circle"></i>
                <span>Profile</span>
            </a>

        <?php endif; ?>
 <a href="../help_center.php" class="list-group-item list-group-item-action text-info">
            <i class="bi bi-question-circle"></i> Help Center
        </a>
        <div class="mt-3 pt-3 border-top">
            <a href="../includes/logout.php" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<style>
/* Sidebar Base Styles */
.sidebar {
    position: sticky;
    top: 0;
    height: 100vh;
}

.sidebar-content {
    max-height: calc(100vh - 80px);
    overflow-y: auto;
    overflow-x: hidden;
}

/* Custom Scrollbar */
.sidebar-content::-webkit-scrollbar {
    width: 6px;
}

.sidebar-content::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.sidebar-content::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Sidebar Links */
.sidebar-link, .sidebar-toggle, .sidebar-sublink {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #495057;
    text-decoration: none;
    border-radius: 8px;
    margin: 2px 0;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    font-size: 0.9rem;
}

.sidebar-link i, .sidebar-toggle i, .sidebar-sublink i {
    width: 20px;
    margin-right: 10px;
    font-size: 1.1rem;
}

.sidebar-link span, .sidebar-toggle span {
    flex: 1;
}

/* Hover Effects */
.sidebar-link:hover, .sidebar-toggle:hover, .sidebar-sublink:hover {
    background-color: #f8f9fa;
    color: #198754;
    border-left-color: #198754;
}

/* Active State */
.sidebar-link.active, .sidebar-sublink.active {
    background-color: #198754;
    color: white;
    border-left-color: #146c43;
    font-weight: 500;
}

.sidebar-link.active:hover, .sidebar-sublink.active:hover {
    background-color: #157347;
    color: white;
}

/* Section Styles */
.sidebar-section {
    margin: 2px 0;
}

.sidebar-toggle {
    cursor: pointer;
    user-select: none;
}

.sidebar-toggle .bi-chevron-down {
    transition: transform 0.2s;
    font-size: 0.8rem;
}

.sidebar-toggle:not(.collapsed) .bi-chevron-down {
    transform: rotate(180deg);
}

/* Sublinks */
.sidebar-sublink {
    padding-left: 2.5rem;
    font-size: 0.85rem;
    border-left: 3px solid transparent;
}

.sidebar-sublink i {
    font-size: 0.9rem;
}

/* Badge Styles */
.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Logout Link */
.sidebar-link.text-danger:hover {
    background-color: #f8d7da;
    border-left-color: #dc3545;
}

/* Mobile Adjustments */
@media (max-width: 991.98px) {
    .sidebar-content {
        max-height: 100%;
    }
}

/* Compact Mode for Smaller Screens */
@media (max-height: 700px) {
    .sidebar-link, .sidebar-toggle, .sidebar-sublink {
        padding: 0.5rem 1rem;
    }
    
    .sidebar-link i, .sidebar-toggle i {
        font-size: 1rem;
    }
}

/* Smooth Collapse Animation */
.collapse {
    transition: height 0.3s ease;
}

/* Print Styles */
@media print {
    .sidebar, .offcanvas, button[data-bs-toggle="offcanvas"] {
        display: none !important;
    }
}
</style>