<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'auth.php';

// Set header to return JSON instead of HTML
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($_SESSION['role'] !== 'Manager') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $id = intval($_POST['id'] ?? 0); 

    // 1. Fetch info
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
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
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
    // 3. Update Assets Table (Soft Delete)
    // ---------------------------------------------------------
    $stmtUpdate = $conn->prepare("
        UPDATE assets 
        SET deleted = 1, 
            deleted_at = CURDATE() 
        WHERE id = ?
    ");
    $stmtUpdate->bind_param("i", $id);
    $success = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($success) {
        // Return success JSON
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}
?>