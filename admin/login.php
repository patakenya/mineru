```php
<?php
// login.php
session_start();
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Check if user exists and is admin
            $stmt = $pdo->prepare("SELECT user_id, full_name, password_hash, account_status FROM users WHERE email = ? AND email = 'admin@example.com'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['account_status'] === 'active' && password_verify($password, $user['password_hash'])) {
                // Log successful login attempt
                $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, action, created_at) VALUES (?, ?, ?, ?, ?, 'login', NOW())");
                $device_type = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $browser = get_browser_name($device_type);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $location = 'Unknown'; // Replace with geolocation service if available
                $stmt->execute([$user['user_id'], $device_type, $browser, $ip_address, $location]);

                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                header('Location: admin_dashboard.php');
                exit;
            } else {
                // Log failed login attempt
                $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, action, created_at) VALUES (?, ?, ?, ?, ?, 'login', NOW())");
                $device_type = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $browser = get_browser_name($device_type);
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $location = 'Unknown';
                $stmt->execute([null, $device_type, $browser, $ip_address, $location]);

                $error = 'Invalid email or password, or you are not an admin.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}

// Helper function to detect browser (simplified)
function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    return 'Unknown';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#10b981'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-sm p-8 w-full max-w-md">
        <div class="flex justify-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-800">Admin Login</h1>
        </div>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded-md mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <div class="mt-1 relative">
                    <input type="email" name="email" id="email" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" placeholder="admin@example.com">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class="ri-mail-line text-gray-400"></i>
                    </div>
                </div>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1 relative">
                    <input type="password" name="password" id="password" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" placeholder="••••••••">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class="ri-lock-line text-gray-400"></i>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between mb-6">
                <a href="forgot_password.php" class="text-sm text-primary hover:text-blue-600">Forgot Password?</a>
            </div>
            <button type="submit" class="w-full bg-primary text-white py-2 rounded-button font-medium hover:bg-blue-600 transition-colors">
                <i class="ri-login-box-line mr-1"></i> Log In
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-gray-500">
            Not an admin? <a href="login_user.php" class="text-primary hover:text-blue-600">User Login</a>
        </p>
    </div>
</body>
</html>
