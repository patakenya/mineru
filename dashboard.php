<?php
// dashboard.php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, referral_code, account_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch balance data
$stmt = $pdo->prepare("SELECT available_balance, pending_balance, total_withdrawn FROM user_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetch() ?: ['available_balance' => 0.00, 'pending_balance' => 0.00, 'total_withdrawn' => 0.00];

// Fetch total investment
$stmt = $pdo->prepare("SELECT SUM(mp.price) as total_investment FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ?");
$stmt->execute([$user_id]);
$total_investment = $stmt->fetch()['total_investment'] ?: 0.00;

// Fetch active miners count
$stmt = $pdo->prepare("SELECT COUNT(*) as active_miners FROM user_miners WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$active_miners = $stmt->fetch()['active_miners'];

// Fetch daily earnings
$stmt = $pdo->prepare("SELECT SUM(mp.daily_profit) as daily_earnings FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ? AND um.status = 'active'");
$stmt->execute([$user_id]);
$daily_earnings = $stmt->fetch()['daily_earnings'] ?: 0.00;

// Fetch referral earnings and count
$stmt = $pdo->prepare("SELECT SUM(commission_earned) as referral_earnings, COUNT(*) as referral_count FROM referrals WHERE referrer_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$referrals = $stmt->fetch();
$referral_earnings = $referrals['referral_earnings'] ?: 0.00;
$referral_count = $referrals['referral_count'];
$referral_progress = min($referral_count / 3 * 100, 100); // 3 referrals needed for withdrawals

// Fetch active miners
$stmt = $pdo->prepare("SELECT um.miner_id, mp.name, mp.price, mp.daily_profit, um.purchase_date, um.status, um.days_remaining, mp.duration_days FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ? ORDER BY um.purchase_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$user_miners = $stmt->fetchAll();

// Fetch transactions
$stmt = $pdo->prepare("SELECT transaction_id, type, amount, method, status, transaction_hash, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Fetch security settings
$stmt = $pdo->prepare("SELECT two_factor_enabled, login_alerts, withdrawal_confirmations, referral_notifications, daily_earnings_reports, marketing_promotions FROM security_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$security = $stmt->fetch() ?: ['two_factor_enabled' => false, 'login_alerts' => true, 'withdrawal_confirmations' => true, 'referral_notifications' => true, 'daily_earnings_reports' => false, 'marketing_promotions' => false];

// Fetch login activity
$stmt = $pdo->prepare("SELECT device_type, browser, ip_address, location, login_time, status FROM login_activity WHERE user_id = ? ORDER BY login_time DESC LIMIT 3");
$stmt->execute([$user_id]);
$login_activity = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-6">
        <!-- Welcome Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-400 opacity-90"></div>
                    <div style="background-image: url('https://readdy.ai/api/search-image?query=abstract%20digital%20cryptocurrency%20mining%20concept%20with%20circuit%20board%20patterns%2C%20blue%20technology%20background%20with%20glowing%20nodes%20and%20connections%2C%20futuristic%20blockchain%20visualization%2C%20clean%20professional%20look%2C%20high%20resolution&width=1200&height=400&seq=2&orientation=landscape');" class="h-64 bg-cover bg-center"></div>
                    <div class="absolute inset-0 flex items-center">
                        <div class="px-8 md:px-12 w-full">
                            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                            <p class="text-blue-100 mb-6 max-w-xl">Manage your mining operations, track earnings, and grow your referrals from your dashboard.</p>
                            <div class="flex flex-wrap gap-4">
                                <a href="#miners" class="bg-white text-primary px-5 py-2.5 rounded-button font-medium hover:bg-blue-50 transition-colors whitespace-nowrap">View Miners</a>
                                <a href="#wallet" class="bg-blue-700 text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-800 transition-colors whitespace-nowrap">Check Wallet</a>
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
                    <h3 class="text-gray-500 text-sm font-medium">Total Investment</h3>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="ri-funds-line text-primary"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_investment, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> <?php echo $active_miners > 0 ? 'Active' : 'No'; ?> Miners
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Active Miners</h3>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="ri-cpu-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $active_miners; ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Running
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Daily Earnings</h3>
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="ri-coin-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 earnings-counter">$<?php echo number_format($daily_earnings, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Next update at midnight</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Referral Earnings</h3>
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="ri-user-add-line text-purple-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($referral_earnings, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500"><?php echo $referral_count; ?>/3 referrals</span>
                    <div class="w-24 h-2 bg-gray-200 rounded-full ml-2">
                        <div class="h-2 bg-purple-500 rounded-full" style="width: <?php echo $referral_progress; ?>%"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Earnings Chart -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Earnings Overview</h2>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1.5 text-sm bg-blue-50 text-primary rounded-full">7 Days</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full">30 Days</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full">All Time</button>
                    </div>
                </div>
                <div id="earningsChart" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Active Miners -->
        <section id="miners" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Your Active Miners</h2>
                    <a href="miners.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        View All <i class="ri-arrow-right-line ml-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miner</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($user_miners)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No active miners. <a href="miners.php" class="text-primary hover:text-blue-600">Purchase a miner now</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($user_miners as $miner): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                    <i class="ri-cpu-line text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($miner['name']); ?></div>
                                                    <div class="text-sm text-gray-500">$<?php echo number_format($miner['price'], 2); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($miner['purchase_date'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo floor((time() - strtotime($miner['purchase_date'])) / 86400); ?> days ago</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-green-600">+$<?php echo number_format($miner['daily_profit'], 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                    <?php $progress = max(0, ($miner['duration_days'] - $miner['days_remaining']) / $miner['duration_days'] * 100); ?>
                                                    <div class="bg-<?php echo $miner['status'] === 'active' ? 'primary' : 'gray-400'; ?> h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <span class="ml-2 text-xs text-gray-500"><?php echo ($miner['duration_days'] - $miner['days_remaining']); ?>/<?php echo $miner['duration_days']; ?> days</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $miner['status'] === 'active' ? 'green-100 text-green-800' : 'gray-100 text-gray-800'; ?>">
                                                <?php echo ucfirst($miner['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Referral Section -->
        <section id="referrals" class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 h-full">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Referral Progress</h2>
                    
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600">Referral Requirement</span>
                            <span class="text-sm font-medium text-primary"><?php echo $referral_count; ?>/3</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-primary h-2.5 rounded-full" style="width: <?php echo $referral_progress; ?>%"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500"><?php echo 3 - $referral_count; ?> more referral<?php echo (3 - $referral_count) !== 1 ? 's' : ''; ?> to enable withdrawals</p>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Your Referral Link</h3>
                        <div class="flex">
                            <input type="text" value="https://cryptominer.com/ref/<?php echo htmlspecialchars($user['referral_code']); ?>" readonly class="flex-1 border border-gray-300 rounded-l-button py-2 px-3 text-sm bg-gray-50 focus:outline-none">
                            <button id="copyLinkBtn" class="bg-primary text-white px-4 rounded-r-button hover:bg-blue-600 transition-colors whitespace-nowrap">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Share Your Link</h3>
                        <div class="flex space-x-2">
                            <button class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 hover:bg-blue-100 transition-colors">
                                <i class="ri-facebook-fill"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-400 hover:bg-blue-100 transition-colors">
                                <i class="ri-twitter-fill"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-green-500 hover:bg-blue-100 transition-colors">
                                <i class="ri-whatsapp-fill"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-700 hover:bg-blue-100 transition-colors">
                                <i class="ri-telegram-fill"></i>
                            </button>
                            <button class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-red-500 hover:bg-blue-100 transition-colors">
                                <i class="ri-mail-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Recent Transactions</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                            No transactions yet. <a href="#wallet" class="text-primary hover:text-blue-600">Make a deposit</a>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full bg-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : 'blue'); ?>-100 flex items-center justify-center mr-3">
                                                        <i class="ri-<?php echo $tx['type'] === 'deposit' ? 'arrow-down' : ($tx['type'] === 'withdrawal' ? 'arrow-up' : 'coins'); ?>-line text-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : 'blue'); ?>-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo ucfirst($tx['type']); ?></div>
                                                        <div class="text-xs text-gray-500 mt-0.5">TX: <?php echo htmlspecialchars(substr($tx['transaction_hash'] ?? 'N/A', 0, 10)); ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($tx['created_at'])); ?></div>
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
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Wallet Section -->
        <section id="wallet" class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Wallet Balance</h2>
                    
                    <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-lg p-5 text-white mb-4">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-blue-100 text-sm mb-1">Available Balance</p>
                                <h3 class="text-2xl font-bold">$<?php echo number_format($balance['available_balance'], 2); ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                                <i class="ri-wallet-3-line text-white"></i>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <div>
                                <p class="text-blue-100 mb-1">Pending</p>
                                <p class="font-medium">$<?php echo number_format($balance['pending_balance'], 2); ?></p>
                            </div>
                            <div>
                                <p class="text-blue-100 mb-1">Total Withdrawn</p>
                                <p class="font-medium">$<?php echo number_format($balance['total_withdrawn'], 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-medium text-gray-700">Withdrawal Status</h3>
                            <span class="text-xs text-<?php echo $referral_count >= 3 ? 'green' : 'yellow'; ?>-600 bg-<?php echo $referral_count >= 3 ? 'green' : 'yellow'; ?>-50 px-2 py-1 rounded-full"><?php echo $referral_count; ?>/3 Referrals</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-<?php echo $referral_count >= 3 ? 'green' : 'yellow'; ?>-500 h-2.5 rounded-full" style="width: <?php echo $referral_progress; ?>%"></div>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500"><?php echo $referral_count >= 3 ? 'Withdrawals enabled' : (3 - $referral_count) . ' more referral' . ((3 - $referral_count) !== 1 ? 's' : '') . ' needed'; ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="deposit.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors whitespace-nowrap text-center">
                                <i class="ri-arrow-down-line mr-1"></i> Deposit
                            </a>
                            <a href="withdraw.php" class="bg-<?php echo $referral_count >= 3 ? 'primary text-white' : 'gray-200 text-gray-500 cursor-not-allowed'; ?> py-2 rounded-button text-sm <?php echo $referral_count >= 3 ? 'hover:bg-blue-600' : ''; ?> transition-colors whitespace-nowrap text-center">
                                <i class="ri-arrow-up-line mr-1"></i> Withdraw
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Security Overview</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-800 mb-1">Two-Factor Authentication</h3>
                                    <p class="text-sm text-gray-500 mb-4">Enhance your account security</p>
                                </div>
                                <label class="custom-switch">
                                    <input type="checkbox" id="twoFactorToggle" <?php echo $security['two_factor_enabled'] ? 'checked' : ''; ?> disabled>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <p class="text-xs text-gray-500">Contact <a href="contact.php" class="text-primary hover:text-blue-600">support</a> to enable 2FA.</p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <h3 class="text-lg font-medium text-gray-800 mb-1">Recent Login Activity</h3>
                                <a href="activity.php" class="text-primary text-sm hover:text-blue-600">View All</a>
                            </div>
                            <div class="space-y-3 mt-4">
                                <?php if (empty($login_activity)): ?>
                                    <p class="text-sm text-gray-500">No recent activity.</p>
                                <?php else: ?>
                                    <?php foreach ($login_activity as $activity): ?>
                                        <div class="flex items-center justify-between p-3 bg-<?php echo $activity['status'] === 'active' ? 'green' : 'gray'; ?>-50 rounded-lg">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-<?php echo $activity['status'] === 'active' ? 'green' : 'gray'; ?>-100 flex items-center justify-center mr-3">
                                                    <i class="ri-<?php echo strpos($activity['device_type'], 'Mobile') !== false ? 'smartphone' : 'computer'; ?>-line text-<?php echo $activity['status'] === 'active' ? 'green' : 'gray'; ?>-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($activity['device_type']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($activity['location'] ?: 'Unknown'); ?> • <?php echo htmlspecialchars(substr($activity['browser'], 0, 20)); ?> • <?php echo date('M d, Y', strtotime($activity['login_time'])); ?></p>
                                                </div>
                                            </div>
                                            <span class="text-xs bg-<?php echo $activity['status'] === 'active' ? 'green' : 'gray'; ?>-100 text-<?php echo $activity['status'] === 'active' ? 'green' : 'gray'; ?>-800 px-2 py-1 rounded-full"><?php echo ucfirst($activity['status']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="scripts.js"></script>
</body>
</html>