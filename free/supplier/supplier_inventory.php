<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$supplier_id = $_SESSION['user_id'];
$success = $error = "";

/**
 * ADD Fertilizer
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fertilizer'])) {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $npk_value = trim($_POST['npk_value']);
    $batch_no = trim($_POST['batch_no']);
    $expiry_date = $_POST['expiry_date'];
    $stock = (int)$_POST['stock'];
    $certified = isset($_POST['certified']) ? 1 : 0;
    $depot_location = trim($_POST['depot_location']);

    if ($name === '') {
        $error = "Fertilizer name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO fertilizers (supplier_id, name, type, npk_value, batch_no, expiry_date, stock, certified, depot_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssiss", $supplier_id, $name, $type, $npk_value, $batch_no, $expiry_date, $stock, $certified, $depot_location);
        if ($stmt->execute()) {
            $success = "Fertilizer added successfully.";
        } else {
            $error = "Failed to add fertilizer.";
        }
    }
}

/**
 * UPDATE Fertilizer (all fields)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fertilizer'])) {
    $fertilizer_id = (int)$_POST['fertilizer_id'];
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $npk_value = trim($_POST['npk_value']);
    $batch_no = trim($_POST['batch_no']);
    $expiry_date = $_POST['expiry_date'];
    $stock = (int)$_POST['stock'];
    $certified = isset($_POST['certified']) ? 1 : 0;
    $depot_location = trim($_POST['depot_location']);

    // Ensure supplier owns it
    $check = $conn->prepare("SELECT supplier_id FROM fertilizers WHERE id=?");
    $check->bind_param("i", $fertilizer_id);
    $check->execute();
    $check->bind_result($owner_id);
    $check->fetch();
    $check->close();

    if ($owner_id !== $supplier_id) {
        $error = "Unauthorized action.";
    } else {
        $stmt = $conn->prepare("UPDATE fertilizers SET name=?, type=?, npk_value=?, batch_no=?, expiry_date=?, stock=?, certified=?, depot_location=? WHERE id=?");
        $stmt->bind_param("sssssiisi", $name, $type, $npk_value, $batch_no, $expiry_date, $stock, $certified, $depot_location, $fertilizer_id);
        if ($stmt->execute()) {
            $success = "Fertilizer updated successfully.";
        } else {
            $error = "Failed to update fertilizer.";
        }
    }
}

/**
 * DELETE Fertilizer
 */
if (isset($_GET['delete_id'])) {
    $fertilizer_id = (int)$_GET['delete_id'];

    // Validate ownership
    $check = $conn->prepare("SELECT supplier_id FROM fertilizers WHERE id=?");
    $check->bind_param("i", $fertilizer_id);
    $check->execute();
    $check->bind_result($owner_id);
    $check->fetch();
    $check->close();

    if ($owner_id !== $supplier_id) {
        $error = "Unauthorized action.";
    } else {
        $del = $conn->prepare("DELETE FROM fertilizers WHERE id=?");
        $del->bind_param("i", $fertilizer_id);
        if ($del->execute()) {
            $success = "Fertilizer deleted successfully.";
        } else {
            $error = "Failed to delete fertilizer.";
        }
    }
}

/**
 * FETCH fertilizers
 */
$stmt = $conn->prepare("SELECT * FROM fertilizers WHERE supplier_id = ? ORDER BY expiry_date ASC");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        .low-stock { background-color: #f8d7da; }
        .uncertified { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h2>My Fertilizer Inventory</h2>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Add Fertilizer -->
        <div class="card mb-3">
            <div class="card-header">Add New Fertilizer</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_fertilizer" value="1">
                    <div class="row mb-2">
                        <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
                        <div class="col-md-2"><input type="text" name="type" class="form-control" placeholder="Type"></div>
                        <div class="col-md-2"><input type="text" name="npk_value" class="form-control" placeholder="NPK"></div>
                        <div class="col-md-2"><input type="text" name="batch_no" class="form-control" placeholder="Batch No"></div>
                        <div class="col-md-3"><input type="date" name="expiry_date" class="form-control"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-2"><input type="number" name="stock" class="form-control" placeholder="Stock" min="0"></div>
                        <div class="col-md-2">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="certified" class="form-check-input" checked>
                                <label class="form-check-label">Certified</label>
                            </div>
                        </div>
                        <div class="col-md-4"><input type="text" name="depot_location" class="form-control" placeholder="Depot Location"></div>
                        <div class="col-md-4"><button class="btn btn-success w-100">Add Fertilizer</button></div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <h4>Current Inventory</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Name</th><th>Type</th><th>NPK</th><th>Batch No</th><th>Expiry</th><th>Depot</th><th>Stock</th><th>Certified</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($fert = $result->fetch_assoc()): ?>
                <tr class="<?= ($fert['stock'] < 20) ? 'low-stock' : '' ?>">
                    <form method="POST">
                        <input type="hidden" name="fertilizer_id" value="<?= $fert['id'] ?>">
                        <td><input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['name']) ?>"></td>
                        <td><input type="text" name="type" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['type']) ?>"></td>
                        <td><input type="text" name="npk_value" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['npk_value']) ?>"></td>
                        <td><input type="text" name="batch_no" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['batch_no']) ?>"></td>
                        <td><input type="date" name="expiry_date" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['expiry_date']) ?>"></td>
                        <td><input type="text" name="depot_location" class="form-control form-control-sm" value="<?= htmlspecialchars($fert['depot_location']) ?>"></td>
                        <td><input type="number" name="stock" class="form-control form-control-sm" value="<?= (int)$fert['stock'] ?>"></td>
                        <td>
                            <input type="checkbox" name="certified" value="1" <?= $fert['certified'] ? 'checked' : '' ?>>
                        </td>
                        <td class="d-flex gap-1">
                            <button type="submit" name="edit_fertilizer" class="btn btn-sm btn-primary">Save</button>
                            <a href="?delete_id=<?= $fert['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this fertilizer?')">Delete</a>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="9" class="text-center">No fertilizers added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
