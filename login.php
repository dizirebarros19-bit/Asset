<?php
session_start(); // CRITICAL: This must be the very first line
include 'db.php'; // Ensure this file connects to 'inventory_system'

$error = '';
$demo_notice = "Production Login System - Enter your credentials to access";

$max_attempts = 5;
$lockout_time = 300; 

// Initialize login attempts if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if locked out
    if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['last_attempt']) < $lockout_time) {
        $error = "Too many login attempts. Try again in 5 minutes.";
        $demo_notice = $error;
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if ($username && $password) {
            // Prepare statement
            $stmt = $conn->prepare("SELECT id, username, password, role, profile_pic FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // Check password
                if (password_verify($password, $user['password'])) {
                    // Success! Reset attempts
                    $_SESSION['login_attempts'] = 0;
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                    $_SESSION['logged_in'] = true;

                    header("Location: index.php");
                    exit();
                } else {
                    // Wrong password
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                    $error = "Invalid username or password.";
                    $demo_notice = $error;
                }
            } else {
                // User not found
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt'] = time();
                $error = "Invalid username or password.";
                $demo_notice = $error;
            }
            $stmt->close();
        } else {
            $error = "Please enter both username and password.";
            $demo_notice = $error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMS - Asset Management - Staff Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'custom-teal': '#3A7472',
                        'custom-teal-light': '#4A8A87',
                        'custom-teal-dark': '#2A5A58'
                    }
                }
            }
        }
    </script>
<style>
    body, html { 
        margin: 0; padding: 0; height: 100%; width: 100%;
        overflow-x: hidden; 
        font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; 
    }

    /* --- ANIMATIONS --- */
    @keyframes slideInLeft {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
    }

    @keyframes slideInRight {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (min-width: 1025px) {
        body { 
            display: flex; 
            overflow-y: hidden; 
            background-color: #DFDFDE; 
        }

        .image-section { 
            width: 50% !important; 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            animation: slideInLeft 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        }

        .form-section { 
            width: 60% !important; 
            height: 100vh;
            margin-left: -10%; 
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #FFFFFF;
            clip-path: polygon(35% 0, 100% 0, 100% 100%, 0% 100%);
            padding-left: 15% !important; 
            animation: slideInRight 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        }

        .form-content {
            opacity: 0; 
            animation: fadeIn 0.8s ease-out 0.8s forwards; 
        }
    }

    @media (max-width: 1024px) {
        .image-section { display: none !important; }
        .form-section { 
            width: 100% !important; 
            min-height: 100vh; 
            margin-left: 0; 
            clip-path: none; 
            padding-left: 1.5rem !important; 
            background-color: #FFFFFF;
        }
        .form-content {
            animation: fadeIn 1s ease-out forwards;
        }
    }

    .notification { 
        padding: 0.75rem; 
        border-radius: 0.5rem; 
        margin-bottom: 1rem; 
        text-align: center; 
        font-size: 0.875rem; 
    }
    .demo-notice { background-color: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
    .error-notice { background-color: #fee2e2; border: 1px solid #fecaca; color: #dc2626; }
    
    .form-content { width: 100%; max-width: 350px; }
</style>
</head>
<body class="bg-gradient-to-br from-custom-teal to-custom-teal-dark lg:bg-none">

    <div class="image-section">
        <img src="logo.png" alt="Asset inventory" class="object-contain -translate-y-14">
    </div>

    <div class="form-section bg-gradient-to-br from-custom-teal to-custom-teal-dark p-6">
        <div class="form-content py-4">
            <div class="text-center mb-1">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <img src="login.gif" alt="Login Logo" class="w-32 h-32 rounded-full object-cover">
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Asset Management System</h1>
                <p class="text-gray-200">Staff Access Portal</p>
            </div>

            <div class="notification <?php echo empty($error) ? 'demo-notice' : 'error-notice'; ?>">
                <p class="text-sm"><?php echo htmlspecialchars($demo_notice); ?></p>
            </div>

            <form id="loginForm" method="POST" action="" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-white mb-2">Username</label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-teal focus:border-custom-teal transition-colors"
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-sm font-medium text-white">Password</label>
                        <a href="#" class="text-sm text-gray-200 hover:text-white transition-colors">Reset password</a>
                    </div>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-custom-teal focus:border-custom-teal transition-colors"
                               placeholder="Enter your password">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-custom-teal hover:bg-custom-teal-dark text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center">
                    <svg id="btnIcon" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 01-3-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Access Warehouse System
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-200">Need help? Contact 
                    <a href="#" class="text-white hover:text-gray-300 font-semibold transition-colors">IT Support</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Button Loading State
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = e.target.querySelector('button[type="submit"]');
            const icon = document.getElementById('btnIcon');
            icon.classList.add('animate-spin');
            icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>`;
            button.innerHTML = icon.outerHTML + ' Authenticating...';
            button.style.opacity = '0.8';
            button.disabled = false; // Set to false for testing if you are having issues
        });
    </script>
</body>
</html>