<?php
include 'db.php';
include 'auth.php';

// Ensure request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die('Invalid request method.');
}

// 1. Check required parameters
if (!isset($_GET['id']) || !isset($_GET['asset_id'])) {
    // Redirecting with your notification system parameters
    header("Location: index.php?page=asset_detail&msg=Missing parameters for deletion&type=error&title=Error");
    exit;
}

$file_id = intval($_GET['id']);
$asset_row_id = intval($_GET['asset_id']);

// 2. Fetch file info to get the path for unlinking (physical delete)
$file_stmt = $conn->prepare("SELECT file_path, file_name FROM asset_files WHERE id = ?");
$file_stmt->bind_param("i", $file_id);
$file_stmt->execute();
$file_data = $file_stmt->get_result()->fetch_assoc();

if (!$file_data) {
    header("Location: index.php?page=asset_detail&id=$asset_row_id&msg=File record not found in database&type=warning&title=Not Found");
    exit;
}

$file_path = $file_data['file_path'];
$file_name = $file_data['file_name'];

// 3. Fetch asset_id string for history logging
$asset_stmt = $conn->prepare("SELECT asset_id FROM assets WHERE id = ?");
$asset_stmt->bind_param("i", $asset_row_id);
$asset_stmt->execute();
$asset_result = $asset_stmt->get_result()->fetch_assoc();
$asset_display_id = $asset_result['asset_id'] ?? 'Unknown Asset';

// 4. Delete file record from database
$delete_stmt = $conn->prepare("DELETE FROM asset_files WHERE id = ?");
$delete_stmt->bind_param("i", $file_id);

if ($delete_stmt->execute()) {
    // 5. Delete actual physical file from the server folder
    if (!empty($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }

    // 6. Log deletion in history table
    $user_id = $_SESSION['user_id'];
    $action = "File Deleted";
    $description = "Deleted document '$file_name' for Asset: $asset_display_id";

    // Matching your history table structure
    $history_stmt = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, ?, ?)");
    $history_stmt->bind_param("isss", $user_id, $asset_display_id, $action, $description);
    $history_stmt->execute();

    // 7. Final Redirect with success parameters for notification.php
    header("Location: index.php?page=asset_detail&id=$asset_row_id&msg=File '$file_name' has been deleted successfully&type=success&title=Deleted");
    exit;
} else {
    // Handle database execution failure
    header("Location: index.php?page=asset_detail&id=$asset_row_id&msg=Database error occurred while deleting&type=error&title=System Error");
    exit;
}
?>