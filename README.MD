# 🌱 Fertilizer Application System (FAS)

### Fertilizer Association of Malawi (FAM)

A full-stack web-based system designed to digitize and streamline fertilizer certification, inventory management, order processing, delivery tracking, and payment handling for the Fertilizer Association of Malawi.

---

## 📌 Overview

The Fertilizer Association of Malawi previously relied on manual, paper-based processes for supplier certification, order management, and logistics coordination.

This system replaces those workflows with a centralized digital platform that improves **efficiency, transparency, and traceability** across the fertilizer supply chain.

It introduces automation across key operations including certification, payments, delivery tracking, and reporting.

---

## 🚀 Key Features

### 🏛️ Admin Panel

* User management (create, activate, deactivate accounts)
* Supplier certification approval with QR code generation
* Inventory and fertilizer stock management
* Order processing and driver assignment
* Payment tracking and verification
* Reports and analytics dashboard
* Audit logs for system activity monitoring
* Internal messaging system

---

### 🧑‍🌾 Supplier Portal

* Digital certification application via QR code
* Browse and place fertilizer orders
* Secure online payments (Stripe integration)
* Automatic subsidy calculation (20% government support)
* Real-time delivery tracking
* Invoice generation and download
* Business profile and location management

---

### 🚚 Driver Module

* View assigned deliveries
* Live GPS location updates
* Route optimization for efficient delivery
* Delivery status updates (in transit / completed)

---

## 🧰 Tech Stack

| Layer         | Technology                 |
| ------------- | -------------------------- |
| Backend       | PHP (MySQLi)               |
| Database      | MySQL                      |
| Frontend      | HTML5, CSS3, JavaScript    |
| Payments      | Stripe PHP SDK             |
| QR Generation | PHP QR Code Library        |
| Mapping       | Google Maps API            |
| Server        | Apache (XAMPP recommended) |
| Auth          | PHP Sessions               |

---

## 👥 User Roles

| Role     | Description                                             |
| -------- | ------------------------------------------------------- |
| Admin    | Full system control (users, inventory, orders, reports) |
| Supplier | Orders fertilizers, makes payments, tracks deliveries   |
| Driver   | Handles deliveries and updates logistics status         |

---

## 🗄️ Database Structure

Core system tables include:

* **users** — system accounts and roles
* **suppliers** — supplier profile data
* **certifications** — supplier approval records
* **inventory / fertilizers** — stock and pricing data
* **orders** — purchase and order tracking
* **deliveries** — logistics and driver assignments
* **payments** — Stripe transaction records
* **invoices** — generated billing documents
* **notifications** — system alerts and updates
* **qr_links** — certification QR tracking

---

## ⚙️ Getting Started

### Requirements

* PHP 7.4+
* MySQL 5.7+
* Apache server (XAMPP recommended)
* Composer
* Stripe account (test mode supported)

---

## 📥 Installation

### 1. Clone Repository

```bash
git clone https://github.com/your-username/fertilizer-application-system.git
```

---

### 2. Move to Server Directory

```bash
# XAMPP (Windows)
C:/xampp/htdocs/FertilizerApplicationSystem

# XAMPP (Linux/Mac)
 /opt/lampp/htdocs/FertilizerApplicationSystem
```

---

### 3. Install Dependencies

```bash
cd FertilizerApplicationSystem
composer require stripe/stripe-php
```

---

### 4. Database Setup

* Open phpMyAdmin
* Create database: `fertilizer_db`
* Import:

```
database/fertilizer_db.sql
```

---

### 5. Configure Database

Edit:

```
includes/db.php
```

```php
$conn = new mysqli("localhost", "root", "", "fertilizer_db");
```

---

### 6. Configure Stripe

Update your Stripe secret key:

```php
\Stripe\Stripe::setApiKey('sk_test_YOUR_KEY');
```

Also update:

* success_url
* cancel_url

---

### 7. Run the System

```
http://localhost/FertilizerApplicationSystem/
```

---

## 🔄 System Workflow

1. Admin generates QR code for supplier certification
2. Supplier applies for certification digitally
3. Admin reviews and approves application
4. Supplier places fertilizer order
5. Admin assigns driver for delivery
6. Driver delivers and updates GPS status
7. Supplier pays via Stripe (20% subsidy applied automatically)
8. System generates invoice and logs transaction

---

## 📁 Project Structure

```
FertilizerApplicationSystem/
├── includes/
│   └── db.php
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   ├── inventory.php
│   ├── orders.php
│   ├── payments.php
│   ├── reports.php
│   ├── messages.php
│   └── audit_logs.php
├── supplier/
│   ├── dashboard.php
│   ├── apply_certificate.php
│   ├── place_order.php
│   ├── payment.php
│   ├── track_delivery.php
│   └── invoices.php
├── driver/
│   ├── dashboard.php
│   ├── deliveries.php
│   └── route_optimization.php
├── stripe/
│   └── init.php
├── database/
│   └── fertilizer_db.sql
├── login.php
├── register.php
└── logout.php
```

---

## 👨‍💻 Author

**Emmanuel Matewere**
Full-Stack Web Developer | Systems Builder | Malawi

---

## 📄 License

This project was developed as an academic final-year project and is maintained by the author for learning and portfolio purposes.

---



