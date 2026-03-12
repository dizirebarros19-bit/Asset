<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'auth.php';
include 'csrf.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF token invalid");
    }

    if ($_SESSION['role'] !== 'Manager') {
        die("Unauthorized");
    }

    $id = intval($_POST['id'] ?? 0); 

    // 1. Fetch info - checking for EITHER deleted=0 OR deleted_at IS NULL
    $stmt = $conn->prepare("
        SELECT asset_id, asset_name, employee_id 
        FROM assets 
        WHERE id = ? AND (deleted = 0 OR deleted_at IS NULL)
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($asset_id, $asset_name, $employee_id);
    
    if (!$stmt->fetch()) {
        $stmt->close();
        header("Location: index.php?page=assets&error=notfound");
        exit();
    }
    $stmt->close();

    // ---------------------------------------------------------
    // 2. Insert into History (Security Logs)
    // ---------------------------------------------------------
    $action = "Asset Deleted";
    $description = "Deleted asset: $asset_name";
    $user_id = $_SESSION['user_id'] ?? null;
    $emp_id = !empty($employee_id) ? $employee_id : null;

    $stmtHist = $conn->prepare("
        INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtHist->bind_param("iisss", $emp_id, $user_id, $asset_id, $action, $description);
    $stmtHist->execute();
    $stmtHist->close();

    // ---------------------------------------------------------
    // 3. Update Assets Table (Sets BOTH flag to 1 and Date)
    // ---------------------------------------------------------
    $stmtUpdate = $conn->prepare("
        UPDATE assets 
        SET deleted = 1, 
            deleted_at = CURDATE() 
        WHERE id = ?
    ");
    $stmtUpdate->bind_param("i", $id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    header("Location: index.php?page=assets&status=deleted");
    exit();
}
?>