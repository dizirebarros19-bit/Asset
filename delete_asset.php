<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'auth.php';

// Set header to return JSON instead of HTML
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Updated Authorization: Allow both Manager and Super Admin
    $user_role = $_SESSION['role'] ?? '';
    if ($user_role !== 'Manager' && $user_role !== 'Super Admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $id = intval($_POST['id'] ?? 0); 

    // 2. Fetch info to log before deleting
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
        echo json_encode(['success' => false, 'message' => 'Asset not found or already deleted']);
        exit();
    }
    $stmt->close();

    // ---------------------------------------------------------
    // 3. Insert into History (Security Logs)
    // ---------------------------------------------------------
    $action = "Asset Deleted";
    $description = "Deleted asset: $asset_name (Role: $user_role)";
    $user_id = $_SESSION['user_id'] ?? null;
    
    // We keep the employee_id in the log so we know who HAD it last
    $emp_id_for_log = !empty($employee_id) ? $employee_id : null;

    $stmtHist = $conn->prepare("
        INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmtHist->bind_param("iisss", $emp_id_for_log, $user_id, $asset_id, $action, $description);
    $stmtHist->execute();
    $stmtHist->close();

    // ---------------------------------------------------------
    // 4. Update Assets Table (Soft Delete & Nullify Employee)
    // ---------------------------------------------------------
    // Added: employee_id = NULL to unassign the asset upon deletion
    $stmtUpdate = $conn->prepare("
        UPDATE assets 
        SET deleted = 1, 
            deleted_at = CURDATE(),
            employee_id = NULL 
        WHERE id = ?
    ");
    $stmtUpdate->bind_param("i", $id);
    $success = $stmtUpdate->execute();
    $stmtUpdate->close();

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}
?>