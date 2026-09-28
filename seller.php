<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}
$seller_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$seller = $result->fetch_assoc();
$stmt->close();
$balance = getWalletBalance($conn, $seller_id, 'seller');
$products_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE seller_id = $seller_id")->fetch_assoc()['count'];
$orders_count = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id
")->fetch_assoc()['count'];
$earnings = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id AND o.status = 'completed'
")->fetch_assoc()['total'] ?? 0;
$net_earnings = $earnings * 0.95;
$pending_orders = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    LEFT JOIN seller_order_status sos ON sos.order_id = o.id AND sos.seller_id = p.seller_id
    WHERE p.seller_id = $seller_id AND COALESCE(sos.status, 'processing') = 'processing'
")->fetch_assoc()['count'];
$transactions = getTransactionHistory($conn, $seller_id, 'seller');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Seller Dashboard</title>
    <link rel="stylesheet" href="seller.css" />
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <span class="brand-icon">⚡</span>
            <span class="brand-name">TradeX</span>
            <span class="role-badge seller-badge">Seller</span>
        </div>
        <div class="header-right">
            <span class="user-email"><?php echo htmlspecialchars($seller['email']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="seller.php" class="nav-item active">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="seller_products.php" class="nav-item">
                    <span class="nav-icon">📦</span>
                    Products
                </a>
                <a href="seller_orders.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    Orders
                </a>
                <a href="seller_earnings.php" class="nav-item">
                    <span class="nav-icon">💰</span>
                    Earnings
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($seller['business_name'] ?? 'Seller'); ?>!</h2>
                <p>Manage your store and products</p>
            </div>

            <div class="wallet-card">
                <div>
                    <div class="wallet-label">💰 Available Balance</div>
                    <div class="wallet-balance">₦<?php echo number_format($net_earnings, 2); ?></div>
                    <div style="font-size: 13px; opacity: 0.85; margin-top: 4px;">
                        Net profit after 5% commission
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $products_count; ?></span>
                        <span class="stat-label">Total Products</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $pending_orders; ?></span>
                        <span class="stat-label">Pending Orders</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <span class="stat-number">₦<?php echo number_format($net_earnings, 2); ?></span>
                        <span class="stat-label">Total Earnings</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <span class="stat-number">4.8</span>
                        <span class="stat-label">Rating</span>
                    </div>
                </div>
            </div>

            <div class="recent-activity">
                <h3>📊 Transaction History</h3>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #8a7a6a; padding: 20px;">No transactions yet</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tx['transaction_id']); ?></td>
                                    <td>
                                        <?php
                                        $is_received = ($tx['to_user_id'] == $seller_id && $tx['to_user_type'] == 'seller');
                                        $is_sent = ($tx['from_user_id'] == $seller_id && $tx['from_user_type'] == 'seller');
                                        if ($is_received) {
                                            echo '🟢 Received';
                                        } elseif ($is_sent) {
                                            echo '🔴 Sent';
                                        } else {
                                            echo '🔄 Transfer';
                                        }
                                        ?>
                                    </td>
                                    <td style="font-weight: 600; color: <?php echo $is_received ? '#28a745' : '#dc3545'; ?>;">
                                        <?php echo $is_received ? '+' : '-'; ?>₦<?php echo number_format($tx['amount'], 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($tx['description'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-delivered" style="background: <?php echo $tx['status'] === 'completed' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $tx['status'] === 'completed' ? '#155724' : '#856404'; ?>;">
                                            <?php echo ucfirst($tx['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>