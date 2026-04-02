<?php
session_start();
$role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['full_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Help Center | Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .help-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        .help-card {
            border: none;
            border-radius: 12px;
            transition: all 0.3s;
            height: 100%;
        }
        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .help-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .faq-item {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
            transition: border-color 0.3s;
        }
        .faq-item:hover {
            border-left-color: #667eea;
        }
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .search-box {
            max-width: 600px;
            margin: 0 auto;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }
        .video-tutorial {
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
        }
        .video-tutorial iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="help-header">
        <div class="container text-center">
            <h1><i class="bi bi-question-circle"></i> Help Center</h1>
            <p class="lead">Find guides, tutorials, and answers to common questions</p>
            
            <!-- Search Box -->
            <div class="search-box mt-4">
                <div class="input-group input-group-lg">
                    <input type="text" id="helpSearch" class="form-control" placeholder="Search for help...">
                    <button class="btn btn-light" type="button" onclick="searchHelp()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        <!-- Role-Specific Quick Access -->
        <?php if ($role !== 'guest'): ?>
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle"></i> <strong>Hello, <?= htmlspecialchars($user_name) ?>!</strong> 
            Viewing help for <strong><?= ucfirst($role) ?></strong> role. 
            <a href="#<?= $role ?>-guide" class="alert-link">Jump to your guide</a>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card help-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="help-icon bg-primary bg-opacity-10 text-primary mx-auto">
                            <i class="bi bi-book"></i>
                        </div>
                        <h5>Getting Started</h5>
                        <p class="text-muted">Learn the basics</p>
                        <a href="#getting-started" class="btn btn-sm btn-outline-primary">Read More</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card help-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="help-icon bg-success bg-opacity-10 text-success mx-auto">
                            <i class="bi bi-play-circle"></i>
                        </div>
                        <h5>Video Tutorials</h5>
                        <p class="text-muted">Watch step-by-step</p>
                        <a href="#video-tutorials" class="btn btn-sm btn-outline-success">Watch Now</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card help-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="help-icon bg-warning bg-opacity-10 text-warning mx-auto">
                            <i class="bi bi-question-circle"></i>
                        </div>
                        <h5>FAQs</h5>
                        <p class="text-muted">Common questions</p>
                        <a href="#faq" class="btn btn-sm btn-outline-warning">View FAQs</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card help-card shadow-sm">
                    <div class="card-body text-center">
                        <div class="help-icon bg-info bg-opacity-10 text-info mx-auto">
                            <i class="bi bi-headset"></i>
                        </div>
                        <h5>Contact Support</h5>
                        <p class="text-muted">Get help now</p>
                        <a href="#contact" class="btn btn-sm btn-outline-info">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Getting Started -->
        <div id="getting-started" class="mb-5">
            <h2 class="mb-4"><i class="bi bi-rocket-takeoff"></i> Getting Started</h2>
            
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5><i class="bi bi-1-circle text-primary"></i> System Overview</h5>
                    <p>The Fertilizer Management System is designed to streamline the entire fertilizer supply chain in Malawi. It connects suppliers, drivers, and administrators to manage orders, deliveries, certifications, and payments efficiently.</p>
                    
                    <h6 class="mt-3">Key Features:</h6>
                    <ul>
                        <li><strong>Certificate Management:</strong> Apply for and manage fertilizer supplier certificates</li>
                        <li><strong>Order Processing:</strong> Place orders, track status, and manage deliveries</li>
                        <li><strong>Route Optimization:</strong> Smart delivery routing for drivers</li>
                        <li><strong>Payment Integration:</strong> Multiple payment methods including mobile money</li>
                        <li><strong>Real-time Tracking:</strong> GPS-based delivery tracking</li>
                        <li><strong>Government Subsidy:</strong> Automatic subsidy calculation</li>
                        <li><strong>SMS Notifications:</strong> Stay updated via text messages</li>
                        <li><strong>Inventory Management:</strong> Track stock levels and expiry dates</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Driver Guide Continued -->
        <div id="driver-guide" class="mb-5">
            <h2 class="mb-4"><i class="bi bi-truck text-primary"></i> Driver User Guide</h2>
            
            <div class="accordion" id="driverAccordion">
                <!-- Route Optimization -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#driver3">
                            <i class="bi bi-map me-2"></i> Using Route Optimization
                        </button>
                    </h2>
                    <div id="driver3" class="accordion-collapse collapse show" data-bs-parent="#driverAccordion">
                        <div class="accordion-body">
                            <h6>Optimizing Your Route:</h6>
                            <div class="faq-item">
                                <p><span class="step-number">1</span> Go to <strong>Route Optimization</strong></p>
                                <p><span class="step-number">2</span> View all pending deliveries on map</p>
                                <p><span class="step-number">3</span> Click <span class="badge bg-primary">Optimize Route</span></p>
                                <p><span class="step-number">4</span> System calculates best route from your location</p>
                                <p><span class="step-number">5</span> View optimized route details:</p>
                                <ul>
                                    <li>Total distance</li>
                                    <li>Estimated time</li>
                                    <li>Delivery order sequence</li>
                                    <li>Turn-by-turn directions</li>
                                </ul>
                                <p><span class="step-number">6</span> Click "Open in Google Maps" for navigation</p>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-lightbulb"></i> <strong>Tip:</strong> Route optimization saves fuel and time by finding the shortest path through all deliveries!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery History -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#driver4">
                            <i class="bi bi-clock-history me-2"></i> Viewing Delivery History
                        </button>
                    </h2>
                    <div id="driver4" class="accordion-collapse collapse" data-bs-parent="#driverAccordion">
                        <div class="accordion-body">
                            <p>Access your complete delivery history to track performance and view past deliveries.</p>
                            
                            <h6>What You Can See:</h6>
                            <ul>
                                <li>All completed deliveries</li>
                                <li>Delivery dates and times</li>
                                <li>Supplier information</li>
                                <li>Fertilizer details</li>
                                <li>Delivery locations</li>
                                <li>Performance statistics</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- 
         Video Tutorials -
        <div id="video-tutorials" class="mb-5">
            <h2 class="mb-4"><i class="bi bi-play-circle text-success"></i> Video Tutorials</h2>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="video-tutorial">
                            <img src="https://via.placeholder.com/640x360/667eea/ffffff?text=Getting+Started+Tutorial" 
                                 class="w-100" alt="Getting Started">
                        </div>
                        <div class="card-body">
                            <h5><i class="bi bi-play-btn"></i> Getting Started with the System</h5>
                            <p class="text-muted">Learn how to navigate the dashboard and access key features</p>
                            <span class="badge bg-primary">5:30 min</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="video-tutorial">
                            <img src="https://via.placeholder.com/640x360/764ba2/ffffff?text=Certificate+Application" 
                                 class="w-100" alt="Certificate Tutorial">
                        </div>
                        <div class="card-body">
                            <h5><i class="bi bi-play-btn"></i> Applying for a Certificate</h5>
                            <p class="text-muted">Step-by-step guide to submit your certificate application</p>
                            <span class="badge bg-success">8:15 min</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="video-tutorial">
                            <img src="https://via.placeholder.com/640x360/28a745/ffffff?text=Placing+Orders" 
                                 class="w-100" alt="Order Tutorial">
                        </div>
                        <div class="card-body">
                            <h5><i class="bi bi-play-btn"></i> How to Place an Order</h5>
                            <p class="text-muted">Complete walkthrough of the ordering process</p>
                            <span class="badge bg-warning">6:45 min</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="video-tutorial">
                            <img src="https://via.placeholder.com/640x360/ffc107/ffffff?text=Delivery+Tracking" 
                                 class="w-100" alt="Tracking Tutorial">
                        </div>
                        <div class="card-body">
                            <h5><i class="bi bi-play-btn"></i> Tracking Your Deliveries</h5>
                            <p class="text-muted">Monitor delivery status and communicate with drivers</p>
                            <span class="badge bg-info">4:20 min</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 -->
        <!-- FAQs -->
        <div id="faq" class="mb-5">
            <h2 class="mb-4"><i class="bi bi-question-circle text-warning"></i> Frequently Asked Questions</h2>
            
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            How do I reset my password?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>To reset your password:</p>
                            <ol>
                                <li>Click "Forgot Password" on the login page</li>
                                <li>Enter your registered email address</li>
                                <li>Check your email for a verification code</li>
                                <li>Enter the code and create a new password</li>
                                <li>Login with your new password</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            How long does certificate approval take?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Certificate applications are typically reviewed within <strong>5-7 business days</strong>. You'll receive an SMS notification when your application is reviewed. Check your dashboard regularly for status updates.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            What payment methods are accepted?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            We accept:
                            <ul>
                                <li><strong>Airtel Money</strong> - Instant mobile payment</li>
                                <li><strong>TNM Mpamba</strong> - Instant mobile payment</li>
                                <li><strong>Bank Transfer</strong> - Direct deposit (1-2 business days)</li>
                                <li><strong>Credit/Debit Cards</strong> - Via Stripe payment gateway</li>
                            </ul>
                            <p>All payments automatically include the 20% government subsidy discount.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Can I cancel an order?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Orders can be cancelled if they are still in "Requested" status (before admin approval). Once approved or dispatched, orders cannot be cancelled. Contact support for special circumstances.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            How do I track my delivery in real-time?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Once your order is dispatched:</p>
                            <ol>
                                <li>Go to <strong>Track Deliveries</strong> in your dashboard</li>
                                <li>You'll see your active deliveries on a map</li>
                                <li>Click on a delivery to see details</li>
                                <li>Driver's location updates automatically</li>
                                <li>View ETA and contact driver if needed</li>
                            </ol>
                            <p>You'll also receive SMS updates at each stage of delivery.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            What should I do if I don't receive SMS notifications?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>If SMS notifications aren't arriving:</p>
                            <ul>
                                <li>Verify your phone number is correct in your profile</li>
                                <li>Check your phone signal strength</li>
                                <li>SMS may take 1-2 minutes to arrive</li>
                                <li>Check if your phone memory is full</li>
                                <li>Contact support if issues persist</li>
                            </ul>
                            <p>You can always check status updates in your dashboard even without SMS.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            How does the government subsidy work?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The government provides subsidies on fertilizer purchases to support farmers. The system automatically calculates and applies eligible subsidies to your order total. The subsidy amount varies based on:
                            <ul>
                                <li>Fertilizer type</li>
                                <li>Order quantity</li>
                                <li>Supplier eligibility</li>
                                <li>Government policy settings</li>
                            </ul>
                            <p>You'll see the subsidy breakdown before confirming your order.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            What if my certificate is about to expire?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>The system sends automatic reminders:</p>
                            <ul>
                                <li>90 days before expiry</li>
                                <li>60 days before expiry</li>
                                <li>30 days before expiry</li>
                            </ul>
                            <p>To renew:</p>
                            <ol>
                                <li>Go to <strong>My Certificates</strong></li>
                                <li>Click on the expiring certificate</li>
                                <li>Click "Apply for Renewal"</li>
                                <li>Upload updated documents if required</li>
                                <li>Submit renewal application</li>
                            </ol>
                            <p><strong>Important:</strong> You cannot place orders with an expired certificate.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<!-- 
         Contact Support --
        <div id="contact" class="mb-5">
            <h2 class="mb-4"><i class="bi bi-headset text-info"></i> Contact Support</h2>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="help-icon bg-primary bg-opacity-10 text-primary mx-auto">
                                <i class="bi bi-chat-dots"></i>
                            </div>
                            <h5>In-App Messages</h5>
                            <p class="text-muted">Send a message directly to the admin team</p>
                            <a href="messages.php" class="btn btn-primary">Send Message</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="help-icon bg-success bg-opacity-10 text-success mx-auto">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <h5>Phone Support</h5>
                            <p class="text-muted">Call us Monday-Friday, 8AM-5PM</p>
                            <a href="tel:+265999999999" class="btn btn-success">
                                <i class="bi bi-telephone"></i> +265 999 999 999
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow-sm text-center">
                        <div class="card-body">
                            <div class="help-icon bg-warning bg-opacity-10 text-warning mx-auto">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <h5>Email Support</h5>
                            <p class="text-muted">We'll respond within 24 hours</p>
                            <a href="mailto:support@fertilizersys.com" class="btn btn-warning">
                                <i class="bi bi-envelope"></i> Email Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h5><i class="bi bi-clock-history"></i> Support Hours</h5>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <p><strong>Phone & Live Chat:</strong></p>
                            <p class="text-muted">Monday - Friday: 8:00 AM - 5:00 PM</p>
                            <p class="text-muted">Saturday: 9:00 AM - 1:00 PM</p>
                            <p class="text-muted">Sunday: Closed</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email & In-App Messages:</strong></p>
                            <p class="text-muted">24/7 - We respond within 24 hours</p>
                            <p class="text-muted">Urgent issues: Response within 4 hours</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

         Additional Resources --
        <div class="mb-5">
            <h2 class="mb-4"><i class="bi bi-bookmark"></i> Additional Resources</h2>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5><i class="bi bi-file-text text-primary"></i> User Manual</h5>
                            <p class="text-muted">Comprehensive guide covering all features</p>
                            <a href="downloads/user_manual.pdf" class="btn btn-sm btn-outline-primary" download>
                                <i class="bi bi-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5><i class="bi bi-journal-code text-success"></i> API Documentation</h5>
                            <p class="text-muted">For developers integrating with our system</p>
                            <a href="api_docs.php" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-code-slash"></i> View Docs
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5><i class="bi bi-list-check text-warning"></i> Quick Start Checklist</h5>
                            <p class="text-muted">Essential steps for new users</p>
                            <a href="downloads/quick_start.pdf" class="btn btn-sm btn-outline-warning" download>
                                <i class="bi bi-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5><i class="bi bi-megaphone text-info"></i> System Updates</h5>
                            <p class="text-muted">Latest features and improvements</p>
                            <a href="changelog.php" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-newspaper"></i> View Updates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback Section -
        <div class="card shadow-sm bg-light">
            <div class="card-body text-center py-5">
                <h3><i class="bi bi-star"></i> Was this helpful?</h3>
                <p class="text-muted">Help us improve our documentation</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button class="btn btn-success" onclick="submitFeedback('helpful')">
                        <i class="bi bi-hand-thumbs-up"></i> Yes, very helpful
                    </button>
                    <button class="btn btn-outline-secondary" onclick="submitFeedback('not-helpful')">
                        <i class="bi bi-hand-thumbs-down"></i> Could be better
                    </button>
                </div>
            </div>
        </div>
    </div> -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        function searchHelp() {
            const searchTerm = document.getElementById('helpSearch').value.toLowerCase();
            if (!searchTerm) return;
            
            // Find all accordion bodies and headers
            const accordionItems = document.querySelectorAll('.accordion-item');
            let found = false;
            
            accordionItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    // Expand the accordion
                    const button = item.querySelector('.accordion-button');
                    const collapse = item.querySelector('.accordion-collapse');
                    
                    if (button.classList.contains('collapsed')) {
                        button.click();
                    }
                    
                    // Scroll to item
                    if (!found) {
                        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        found = true;
                    }
                }
            });
            
            if (!found) {
                alert('No results found for "' + searchTerm + '". Try different keywords.');
            }
        }
        
        // Enter key search
        document.getElementById('helpSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchHelp();
            }
        });
        
        // Feedback submission
        function submitFeedback(type) {
            // You can implement actual feedback submission here
            const message = type === 'helpful' ? 
                'Thank you for your feedback! We\'re glad we could help.' : 
                'Thank you for your feedback. We\'ll work on improving our help center.';
            
            alert(message);
            
            // Optional: Send feedback to server
            // fetch('submit_feedback.php', {
            //     method: 'POST',
            //     body: JSON.stringify({ feedback: type, page: 'help_center' })
            // });
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Print page
        function printPage() {
            window.print();
        }
        
        // Copy to clipboard functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>