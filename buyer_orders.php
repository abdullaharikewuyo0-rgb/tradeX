<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php');
    exit();
}

$buyer_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM buyers WHERE id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$buyer = $result->fetch_assoc();
$stmt->close();

$cart_count = getCartCount($conn, $buyer_id);

updateOrderStatuses($conn);

$orders_query = $conn->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.buyer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_query->bind_param("i", $buyer_id);
$orders_query->execute();
$orders = $orders_query->get_result();

$transactions = getTransactionHistory($conn, $buyer_id, 'buyer', 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · My Orders</title>
    <link rel="stylesheet" href="buyer_orders.css" />
</head>
<body>
    <header class="dashboard-header">
        <div class="header-left">
            <span class="brand-icon">⚡</span>
            <span class="brand-name">TradeX</span>
            <span class="role-badge buyer-badge">Buyer</span>
        </div>
        <div class="header-right">
            <span class="user-email"><?php echo htmlspecialchars($buyer['email']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="buyer_dashboard.php" class="nav-item">
                    <span class="nav-icon">🏠</span>
                    Dashboard
                </a>
                <a href="buyer_shop.php" class="nav-item">
                    <span class="nav-icon">🛍️</span>
                    Shop
                </a>
                <a href="buyer_cart.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="buyer_orders.php" class="nav-item active">
                    <span class="nav-icon">📋</span>
                    Orders
                </a>
                <a href="buyer_wishlist.php" class="nav-item">
                    <span class="nav-icon">❤️</span>
                    Wishlist
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-section">
                <h2>📋 My Orders</h2>
                <p>View all your orders and transaction history</p>
            </div>

            <div class="orders-tabs">
                <button class="tab-btn active" onclick="showTab('orders')">Orders</button>
                <button class="tab-btn" onclick="showTab('transactions')">Transactions</button>
            </div>

            <div id="orders-tab" class="tab-content">
                <?php if ($orders->num_rows > 0): ?>
                    <?php while ($order = $orders->fetch_assoc()): 
                        $is_completed = ($order['status'] === 'completed');
                        $delivery_date = strtotime($order['delivery_date']);
                        $current_date = time();
                        $days_until_delivery = ceil(($delivery_date - $current_date) / (60 * 60 * 24));
                    ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <span class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span class="order-date">📅 <?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <span class="order-status <?php echo $order['status']; ?>">
                                    <?php 
                                    if ($order['status'] === 'processing' && $days_until_delivery > 0) {
                                        echo '⏳ Processing (' . $days_until_delivery . ' days)';
                                    } elseif ($order['status'] === 'processing') {
                                        echo '📦 Delivering today!';
                                    } elseif ($order['status'] === 'completed') {
                                        echo '✅ Completed';
                                    } else {
                                        echo ucfirst($order['status']);
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="order-body">
                                <div class="order-items">
                                    <strong>Items:</strong>
                                    <p><?php echo htmlspecialchars($order['items'] ?? 'No items'); ?></p>
                                </div>
                                <div class="order-details-grid">
                                    <div>
                                        <span class="detail-label">Payment Method:</span>
                                        <span><?php echo ucfirst($order['payment_method'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div>
                                        <span class="detail-label">Total Amount:</span>
                                        <span class="order-total">₦<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                    <?php if ($order['delivery_date']): ?>
                                        <div>
                                            <span class="detail-label">Delivery Date:</span>
                                            <span>📦 <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></span>
                                            <?php if ($order['status'] === 'processing' && $days_until_delivery > 0): ?>
                                                <span class="delivery-countdown">(<?php echo $days_until_delivery; ?> days left)</span>
                                            <?php elseif ($order['status'] === 'processing' && $days_until_delivery <= 0): ?>
                                                <span class="delivery-today">📍 Out for delivery today!</span>
                                            <?php elseif ($order['status'] === 'completed'): ?>
                                                <span class="delivery-completed">✅ Delivered</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="detail-label">Items:</span>
                                        <span><?php echo $order['item_count']; ?> items</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span style="font-size: 48px;">📋</span>
                        <p>You haven't placed any orders yet.</p>
                        <a href="buyer_shop.php" class="continue-shopping">Start Shopping</a>
                    </div>
                <?php endif; ?>
            </div>

            <div id="transactions-tab" class="tab-content" style="display: none;">
                <div class="transactions-table-wrapper">
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
                                    <td colspan="6" style="text-align: center; color: #8a7a6a;">No transactions yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tx['transaction_id']); ?></td>
                                        <td>
                                            <?php
                                            $is_sent = ($tx['from_user_id'] == $buyer_id && $tx['from_user_type'] == 'buyer');
                                            echo $is_sent ? '🔴 Sent' : '🟢 Received';
                                            ?>
                                        </td>
                                        <td style="font-weight: 600; color: <?php echo ($is_sent) ? '#dc3545' : '#28a745'; ?>;">
                                            <?php echo ($is_sent ? '-' : '+'); ?>₦<?php echo number_format($tx['amount'], 2); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($tx['description'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge" style="background: <?php echo $tx['status'] === 'completed' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo $tx['status'] === 'completed' ? '#155724' : '#856404'; ?>;">
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
            </div>
        </main>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            if (tab === 'orders') {
                document.getElementById('orders-tab').style.display = 'block';
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
            } else {
                document.getElementById('transactions-tab').style.display = 'block';
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
            }
        }
    </script>
</body>
</html>