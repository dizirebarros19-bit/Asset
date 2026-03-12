<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = trim($_POST['employee_id']);
    
    // Quick check if the employee exists in your system
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE employee_id = ?"); // Adjust table/column name as needed
    $stmt->bind_param("s", $emp_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();

    if ($count > 0 || !empty($emp_id)) { // basic check
        $_SESSION['view_emp_id'] = $emp_id;
        header("Location: employee_records.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AMS - Portal Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .split-bg { clip-path: polygon(25% 0, 100% 0, 100% 100%, 0% 100%); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .animate-slide { animation: slideIn 0.8s ease-out; }
    </style>
</head>
<body class="bg-gray-100 flex min-h-screen overflow-hidden">

    <div class="hidden lg:flex w-1/2 items-center justify-center">
        <img src="logo.png" class="max-w-xs opacity-80" alt="Logo">
    </div>

    <div class="w-full lg:w-2/3 bg-[#3A7472] split-bg flex items-center justify-center p-8 animate-slide">
        <div class="max-w-sm w-full lg:ml-32">
            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold text-white mb-2">Employee Portal</h1>
                <p class="text-teal-100">Enter ID to view your asset history</p>
            </div>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-teal-50 text-sm mb-2">Employee ID Number</label>
                    <input type="text" name="employee_id" required 
                           class="w-full px-5 py-4 rounded-xl border border-white/20 bg-white/10 text-white outline-none focus:ring-2 focus:ring-white">
                </div>
                <button type="submit" class="w-full bg-white text-[#3A7472] font-bold py-4 rounded-xl hover:bg-gray-100 transition-all shadow-xl">
                    Check My Records
                </button>
            </form>
        </div>
    </div>

</body>
</html>