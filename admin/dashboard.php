```php
<?php
// admin_dashboard.php
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data to verify admin status
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, account_status FROM users WHERE user_id = ? AND email = 'admin@example.com'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch system-wide stats
// Total users
$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users WHERE account_status = 'active'");
$stmt->execute();
$total_users = $stmt->fetch()['total_users'];

// Total investments
$stmt = $pdo->prepare("SELECT SUM(mp.price) as total_investment FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id");
$stmt->execute();
$total_investment = $stmt->fetch()['total_investment'] ?: 0.00;

// Total active miners
$stmt = $pdo->prepare("SELECT COUNT(*) as total_miners FROM user_miners WHERE status = 'active'");
$stmt->execute();
$total_miners = $stmt->fetch()['total_miners'];

// Total referral earnings
$stmt = $pdo->prepare("SELECT SUM(commission_earned) as total_referral_earnings FROM referrals WHERE status = 'active'");
$stmt->execute();
$total_referral_earnings = $stmt->fetch()['total_referral_earnings'] ?: 0.00;

// Fetch recent users
$stmt = $pdo->prepare("SELECT user_id, full_name, email, referral_code, account_status, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recent_users = $stmt->fetchAll();

// Fetch recent transactions
$stmt = $pdo->prepare("SELECT t.transaction_id, t.type, t.amount, t.method, t.status, t.created_at, u.email FROM transactions t JOIN users u ON t.user_id = u.user_id ORDER BY t.created_at DESC LIMIT 5");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Fetch recent contact messages
$stmt = $pdo->prepare("SELECT message_id, name, email, subject, status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$contact_messages = $stmt->fetchAll();

// Fetch mining packages
$stmt = $pdo->prepare("SELECT package_id, name, price, daily_profit, duration_days, daily_return_percentage, is_active FROM mining_packages ORDER BY created_at DESC");
$stmt->execute();
$mining_packages = $stmt->fetchAll();

// Fetch admin login activity
$stmt = $pdo->prepare("SELECT device_type, browser, ip_address, location, action, created_at FROM login_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$login_activity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CryptoMiner ERP</title>
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
<body class="bg-gray-50 min-h-screen">
    <?php include './header.php'; ?>

    <main class="container mx-auto px-4 py-6">
        <!-- Welcome Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-400 opacity-90"></div>
                    <div style="background-image: url('https://readdy.ai/api/search-image?query=abstract%20digital%20cryptocurrency%20mining%20concept%20with%20circuit%20board%20patterns%2C%20blue%20technology%20background%20with%20glowing%20nodes%20and%20connections%2C%20futuristic%20blockchain%20visualization%2C%20clean%20professional%20look%2C%20high%20resolution&width=1200&height=400&seq=2&orientation=landscape');" class="h-64 bg-cover bg-center"></div>
                    <div class="absolute inset-0 flex items-center">
                        <div class="px-8 md:px-12 w-full">
                            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Welcome, Admin <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                            <p class="text-blue-100 mb-6 max-w-xl">Manage users, transactions, mining packages, and system settings from your admin dashboard.</p>
                            <div class="flex flex-wrap gap-4">
                                <a href="#users" class="bg-white text-primary px-5 py-2.5 rounded-button font-medium hover:bg-blue-50 transition-colors whitespace-nowrap">Manage Users</a>
                                <a href="#packages" class="bg-blue-700 text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-800 transition-colors whitespace-nowrap">Manage Packages</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Cards -->
        <section class="mb-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Total Users</h3>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="ri-user-line text-primary"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_users); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Active
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Total Investment</h3>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="ri-funds-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_investment, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> System-wide
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Active Miners</h3>
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="ri-cpu-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_miners); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Running
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Referral Earnings</h3>
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="ri-user-add-line text-purple-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_referral_earnings, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">System-wide</span>
                </div>
            </div>
        </section>

        <!-- Recent Users -->
        <section id="users" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Users</h2>
                    <a href="users.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        View All <i class="ri-arrow-right-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Code</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No users found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['referral_code']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-800">
                                                <?php echo ucfirst($user['account_status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Recent Transactions -->
        <section id="transactions" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Transactions</h2>
                    <a href="transactions.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        View All <i class="ri-arrow-right-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                        No transactions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($tx['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo ucfirst($tx['type']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-<?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($tx['amount']), 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-900">
                                                <i class="ri-coin-line mr-1.5 text-yellow-500"></i> <?php echo htmlspecialchars($tx['method']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($tx['status']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Contact Messages -->
        <section id="messages" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Contact Messages</h2>
                    <a href="messages.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        View All <i class="ri-arrow-right-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($contact_messages)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No messages found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($contact_messages as $message): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($message['name']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($message['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($message['subject']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $message['status'] === 'new' ? 'yellow' : ($message['status'] === 'read' ? 'blue' : 'green'); ?>-100 text-<?php echo $message['status'] === 'new' ? 'yellow' : ($message['status'] === 'read' ? 'blue' : 'green'); ?>-800">
                                                <?php echo ucfirst($message['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($message['created_at'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Mining Packages -->
        <section id="packages" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Mining Packages</h2>
                    <a href="add_package.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        Add Package <i class="ri-add-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($mining_packages)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                        No mining packages found. <a href="add_package.php" class="text-primary hover:text-blue-600">Add a package</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mining_packages as $package): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($package['name']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">$<?php echo number_format($package['price'], 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">$<?php echo number_format($package['daily_profit'], 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $package['duration_days']; ?> days</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $package['is_active'] ? 'green' : 'red'; ?>-100 text-<?php echo $package['is_active'] ? 'green' : 'red'; ?>-800">
                                                <?php echo $package['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <a href="edit_package.php?id=<?php echo $package['package_id']; ?>" class="text-primary hover:text-blue-600 mr-3">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="delete_package.php?id=<?php echo $package['package_id']; ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Are you sure you want to delete this package?');">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Admin Security and Activity -->
        <section id="security" class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Admin Login Activity</h2>
                    <div class="space-y-3">
                        <?php if (empty($login_activity)): ?>
                            <p class="text-sm text-gray-500">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach ($login_activity as $activity): ?>
                                <div class="flex items-center justify-between p-3 bg-<?php echo $activity['action'] === 'login' ? 'green' : 'gray'; ?>-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full bg-<?php echo $activity['action'] === 'login' ? 'green' : 'gray'; ?>-100 flex items-center justify-center mr-3">
                                            <i class="ri-<?php echo strpos($activity['device_type'], 'Mobile') !== false ? 'smartphone' : 'computer'; ?>-line text-<?php echo $activity['action'] === 'login' ? 'green' : 'gray'; ?>-600"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['device_type']); ?> - <?php echo htmlspecialchars($activity['action']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($activity['location'] ?: 'Unknown'); ?> • <?php echo htmlspecialchars(substr($activity['browser'], 0, 20)); ?> • <?php echo date('M d, Y', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-<?php echo $activity['action'] === 'login' ? 'green' : 'gray'; ?>-100 text-<?php echo $activity['action'] === 'login' ? 'green' : 'gray'; ?>-800 px-2 py-1 rounded-full"><?php echo ucfirst($activity['action']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h2>
                    <div class="grid grid-cols-1 gap-3">
                        <a href="users.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors text-center">
                            <i class="ri-user-line mr-1"></i> Manage Users
                        </a>
                        <a href="transactions.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors text-center">
                            <i class="ri-wallet-3-line mr-1"></i> View Transactions
                        </a>
                        <a href="messages.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors text-center">
                            <i class="ri-mail-line mr-1"></i> View Messages
                        </a>
                        <a href="add_package.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors text-center">
                            <i class="ri-add-line mr-1"></i> Add Package
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include './footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>
```