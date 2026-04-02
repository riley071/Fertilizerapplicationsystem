<?php
// admin/messages.php (or common/messages.php for all users)
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = $error = "";

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = intval($_POST['recipient_id']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, message, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $user_id, $recipient_id, $subject, $message);
        
        if ($stmt->execute()) {
            $message_id = $stmt->insert_id;
            $success = "Message sent successfully!";
            
            // Send email notification (optional)
            $recipient_stmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $recipient_stmt->bind_param("i", $recipient_id);
            $recipient_stmt->execute();
            $recipient = $recipient_stmt->get_result()->fetch_assoc();
            
            // Email notification code here (optional)
            
            $recipient_stmt->close();
        } else {
            $error = "Failed to send message.";
        }
        $stmt->close();
    }
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $message_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: messages.php");
    exit();
}

// Handle delete message
if (isset($_GET['delete'])) {
    $message_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)");
    $stmt->bind_param("iii", $message_id, $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: messages.php");
    exit();
}

// Get message filter
$filter = $_GET['filter'] ?? 'inbox';
$search = $_GET['search'] ?? '';

// Build query based on filter
if ($filter === 'inbox') {
    $query = "SELECT m.*, u.full_name as sender_name, u.email as sender_email 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.recipient_id = ?";
} elseif ($filter === 'sent') {
    $query = "SELECT m.*, u.full_name as recipient_name, u.email as recipient_email 
              FROM messages m 
              JOIN users u ON m.recipient_id = u.id 
              WHERE m.sender_id = ?";
} else {
    $query = "SELECT m.*, u.full_name as sender_name, u.email as sender_email 
              FROM messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.recipient_id = ? AND m.is_read = 0";
}

if (!empty($search)) {
    $query .= " AND (m.subject LIKE ? OR m.message LIKE ? OR u.full_name LIKE ?)";
}

$query .= " ORDER BY m.sent_at DESC";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("isss", $user_id, $search_param, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unread count
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

// Get all users for recipient dropdown
$users_query = "SELECT id, full_name, email, role FROM users WHERE id != $user_id";
if ($role === 'supplier') {
    $users_query .= " AND role = 'admin'"; // Suppliers can only message admins
}
$all_users = $conn->query($users_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .message-list { max-height: 600px; overflow-y: auto; }
        .message-item {
            cursor: pointer;
            transition: background 0.2s;
            border-left: 3px solid transparent;
        }
        .message-item:hover { background: #f8f9fa; }
        .message-item.unread {
            background: #e7f3ff;
            border-left-color: #0d6efd;
            font-weight: 600;
        }
        .message-preview { 
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 300px;
        }
        .badge-new {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success">
                <i class="bi bi-chat-dots"></i> Messages 
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger badge-new"><?= $unread_count ?></span>
                <?php endif; ?>
            </h3>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#composeModal">
                <i class="bi bi-pencil-square"></i> Compose Message
            </button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sidebar with filters -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Mailbox</h6>
                        <div class="d-grid gap-2">
                            <a href="?filter=inbox" class="btn btn-<?= $filter === 'inbox' ? 'primary' : 'outline-primary' ?> text-start">
                                <i class="bi bi-inbox"></i> Inbox
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-danger float-end"><?= $unread_count ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="?filter=unread" class="btn btn-<?= $filter === 'unread' ? 'primary' : 'outline-primary' ?> text-start">
                                <i class="bi bi-envelope"></i> Unread
                            </a>
                            <a href="?filter=sent" class="btn btn-<?= $filter === 'sent' ? 'primary' : 'outline-primary' ?> text-start">
                                <i class="bi bi-send"></i> Sent
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Search -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="GET">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search messages..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="col-md-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <?= $filter === 'inbox' ? 'Inbox' : ($filter === 'sent' ? 'Sent Messages' : 'Unread Messages') ?>
                            (<?= count($messages) ?>)
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="message-list">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox" style="font-size: 4rem;"></i>
                                    <p class="mb-0 mt-3">No messages found</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message-item p-3 border-bottom <?= !$msg['is_read'] && $filter !== 'sent' ? 'unread' : '' ?>" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#viewModal<?= $msg['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center mb-1">
                                                    <?php if ($filter === 'sent'): ?>
                                                        <i class="bi bi-person-circle text-primary me-2"></i>
                                                        <strong><?= htmlspecialchars($msg['recipient_name']) ?></strong>
                                                    <?php else: ?>
                                                        <i class="bi bi-person-circle text-success me-2"></i>
                                                        <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                                    <?php endif; ?>
                                                    <?php if (!$msg['is_read'] && $filter !== 'sent'): ?>
                                                        <span class="badge bg-primary ms-2">New</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-primary fw-semibold"><?= htmlspecialchars($msg['subject']) ?></div>
                                                <div class="message-preview text-muted small">
                                                    <?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...
                                                </div>
                                            </div>
                                            <div class="text-end ms-3">
                                                <small class="text-muted"><?= date('M d, Y', strtotime($msg['sent_at'])) ?></small><br>
                                                <small class="text-muted"><?= date('H:i', strtotime($msg['sent_at'])) ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- View Message Modal -->
                                    <div class="modal fade" id="viewModal<?= $msg['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?= htmlspecialchars($msg['subject']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <strong>From:</strong> 
                                                        <?php if ($filter === 'sent'): ?>
                                                            <?= htmlspecialchars($msg['recipient_name']) ?> 
                                                            <small class="text-muted">&lt;<?= htmlspecialchars($msg['recipient_email']) ?>&gt;</small>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($msg['sender_name']) ?> 
                                                            <small class="text-muted">&lt;<?= htmlspecialchars($msg['sender_email']) ?>&gt;</small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Date:</strong> <?= date('F d, Y \a\t H:i', strtotime($msg['sent_at'])) ?>
                                                    </div>
                                                    <hr>
                                                    <div class="message-content" style="white-space: pre-wrap;">
                                                        <?= htmlspecialchars($msg['message']) ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <?php if ($filter !== 'sent'): ?>
                                                        <?php if (!$msg['is_read']): ?>
                                                            <a href="?mark_read=<?= $msg['id'] ?>" class="btn btn-primary">
                                                                <i class="bi bi-check2"></i> Mark as Read
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-success" onclick="replyTo(<?= $msg['sender_id'] ?>, '<?= addslashes($msg['sender_name']) ?>', 'Re: <?= addslashes($msg['subject']) ?>')">
                                                            <i class="bi bi-reply"></i> Reply
                                                        </button>
                                                    <?php endif; ?>
                                                    <a href="?delete=<?= $msg['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this message?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> New Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">To <span class="text-danger">*</span></label>
                        <select name="recipient_id" id="recipientSelect" class="form-select" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?= $user['id'] ?>">
                                    <?= htmlspecialchars($user['full_name']) ?> 
                                    (<?= ucfirst($user['role']) ?>) - 
                                    <?= htmlspecialchars($user['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="subjectInput" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_message" class="btn btn-success">
                        <i class="bi bi-send"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function replyTo(recipientId, recipientName, subject) {
    // Close all modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    });
    
    // Open compose modal with pre-filled data
    setTimeout(() => {
        document.getElementById('recipientSelect').value = recipientId;
        document.getElementById('subjectInput').value = subject;
        const composeModal = new bootstrap.Modal(document.getElementById('composeModal'));
        composeModal.show();
    }, 500);
}

// Auto-refresh unread count every 30 seconds
setInterval(() => {
    fetch('get_unread_count.php')
        .then(response => response.json())
        .then(data => {
            const badges = document.querySelectorAll('.badge-new');
            badges.forEach(badge => {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            });
        });
}, 30000);
</script>
</body>
</html>

<?php
// ===== DATABASE SCHEMA =====
/*
CREATE TABLE `messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sender_id` INT(11) NOT NULL,
  `recipient_id` INT(11) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create announcement table
CREATE TABLE `announcements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `type` ENUM('info', 'warning', 'success', 'danger') DEFAULT 'info',
  `target_role` ENUM('all', 'admin', 'supplier', 'driver') DEFAULT 'all',
  `created_by` INT(11) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_announcement_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

// ===== HELPER FILE: get_unread_count.php =====
/*
<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

echo json_encode(['count' => $count]);
?>
*/
?>