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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['status'] ?? '';
    
    if ($order_id > 0 && in_array($new_status, ['processing', 'completed', 'cancelled'])) {
        $verify = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ? AND p.seller_id = ?
        ");
        $verify->bind_param("ii", $order_id, $seller_id);
        $verify->execute();
        $belongs_to_seller = $verify->get_result()->fetch_assoc()['count'] > 0;
        $verify->close();
        
        if ($belongs_to_seller) {
            $current_query = $conn->prepare("
                SELECT status FROM seller_order_status 
                WHERE order_id = ? AND seller_id = ?
            ");
            $current_query->bind_param("ii", $order_id, $seller_id);
            $current_query->execute();
            $current_result = $current_query->get_result();
            $current_row = $current_result->fetch_assoc();
            $current_status = $current_row['status'] ?? 'processing';
            $current_query->close();
            
            $insert = $conn->prepare("
                INSERT INTO seller_order_status (order_id, seller_id, status) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE status = ?
            ");
            $insert->bind_param("iiss", $order_id, $seller_id, $new_status, $new_status);
            $insert->execute();
            $insert->close();
            
            if ($new_status === 'completed' && $current_status !== 'completed') {
                $order_data_query = $conn->prepare("SELECT buyer_id FROM orders WHERE id = ?");
                $order_data_query->bind_param("i", $order_id);
                $order_data_query->execute();
                $order_data = $order_data_query->get_result()->fetch_assoc();
                $order_data_query->close();
                
                $already_paid = $conn->prepare("
                    SELECT id FROM transactions 
                    WHERE to_user_id = ? AND to_user_type = 'seller' 
                    AND description LIKE ?
                ");
                $search_pattern = "%Order #$order_id%Seller: $seller_id%";
                $already_paid->bind_param("is", $seller_id, $search_pattern);
                $already_paid->execute();
                $payment_exists = $already_paid->get_result()->num_rows > 0;
                $already_paid->close();
                
                if (!$payment_exists && $order_data) {
                    $amount_query = $conn->prepare("
                        SELECT SUM(oi.quantity * oi.price) as total 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ? AND p.seller_id = ?
                    ");
                    $amount_query->bind_param("ii", $order_id, $seller_id);
                    $amount_query->execute();
                    $gross_amount = $amount_query->get_result()->fetch_assoc()['total'] ?? 0;
                    $amount_query->close();
                    
                    $seller_amount = $gross_amount * 0.95;
                    
                    if ($seller_amount > 0) {
                        $current_balance = getWalletBalance($conn, $seller_id, 'seller');
                        $new_balance = $current_balance + $seller_amount;
                        
                        $update_wallet = $conn->prepare("UPDATE seller_wallets SET balance = ? WHERE seller_id = ?");
                        $update_wallet->bind_param("di", $new_balance, $seller_id);
                        $update_wallet->execute();
                        $update_wallet->close();
                        
                        $transaction_id = generateTransactionId();
                        $description = "Payment for Order #$order_id - Seller: $seller_id - Completed";
                        
                        $txn_stmt = $conn->prepare("
                            INSERT INTO transactions (transaction_id, from_user_id, from_user_type, to_user_id, to_user_type, amount, status, type, description) 
                            VALUES (?, ?, 'system', ?, 'seller', ?, 'completed', 'payment', ?)
                        ");
                        $txn_stmt->bind_param("siids", $transaction_id, $order_data['buyer_id'], $seller_id, $seller_amount, $description);
                        $txn_stmt->execute();
                        $txn_stmt->close();
                    }
                }
            }
            
            $check_all_query = $conn->prepare("
                SELECT COUNT(DISTINCT p.seller_id) as total_sellers,
                       SUM(CASE WHEN sos.status = 'completed' THEN 1 ELSE 0 END) as completed_sellers
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                LEFT JOIN seller_order_status sos ON sos.order_id = oi.order_id AND sos.seller_id = p.seller_id
                WHERE oi.order_id = ?
            ");
            $check_all_query->bind_param("i", $order_id);
            $check_all_query->execute();
            $counts = $check_all_query->get_result()->fetch_assoc();
            $check_all_query->close();
            
            if ($counts['total_sellers'] == $counts['completed_sellers']) {
                $update_master = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
                $update_master->bind_param("i", $order_id);
                $update_master->execute();
                $update_master->close();
            }
        }
        
        $_SESSION['order_message'] = '✅ Order status updated!';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$order_message = $_SESSION['order_message'] ?? '';
unset($_SESSION['order_message']);

$orders_query = $conn->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_method,
        o.delivery_address,
        o.delivery_city,
        o.delivery_phone,
        o.delivery_date,
        o.created_at,
        b.full_name as buyer_name,
        b.email as buyer_email,
        COUNT(DISTINCT oi.id) as item_count,
        COALESCE(sos.status, 'processing') as seller_status,
        GROUP_CONCAT(DISTINCT CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ') as items,
        SUM(oi.quantity * oi.price) as seller_subtotal
    FROM orders o
    JOIN buyers b ON o.buyer_id = b.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN seller_order_status sos ON sos.order_id = o.id AND sos.seller_id = p.seller_id
    WHERE p.seller_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_query->bind_param("i", $seller_id);
$orders_query->execute();
$orders = $orders_query->get_result();

$total_orders = $orders->num_rows;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

$orders->data_seek(0);
while ($order = $orders->fetch_assoc()) {
    if ($order['seller_status'] === 'processing') $pending_orders++;
    elseif ($order['seller_status'] === 'completed') $completed_orders++;
    elseif ($order['seller_status'] === 'cancelled') $cancelled_orders++;
}
$orders->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Orders</title>
    <link rel="stylesheet" href="seller_orders.css" />
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
                <a href="seller.php" class="nav-item">
                    <span class="nav-icon">📊</span>
                    Dashboard
                </a>
                <a href="seller_products.php" class="nav-item">
                    <span class="nav-icon">📦</span>
                    Products
                </a>
                <a href="seller_orders.php" class="nav-item active">
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
                <h2>🛒 Customer Orders</h2>
                <p>View and manage orders placed by customers</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_orders; ?></span>
                        <span class="stat-label">Total Orders</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $pending_orders; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $completed_orders; ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $cancelled_orders; ?></span>
                        <span class="stat-label">Cancelled</span>
                    </div>
                </div>
            </div>

            <?php if ($order_message): ?>
                <div class="notification-message"><?php echo $order_message; ?></div>
            <?php endif; ?>

            <?php if ($orders->num_rows > 0): ?>
                <div class="orders-container">
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div>
                                    <span class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span class="order-date">📅 <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></span>
                                </div>
                                <span class="order-status <?php echo $order['seller_status']; ?>">
                                    <?php 
                                    if ($order['seller_status'] === 'processing') echo '⏳ Processing';
                                    elseif ($order['seller_status'] === 'completed') echo '✅ Completed';
                                    elseif ($order['seller_status'] === 'cancelled') echo '❌ Cancelled';
                                    ?>
                                </span>
                            </div>
                            
                            <div class="order-body">
                                <div class="customer-section">
                                    <h4>👤 Customer Details</h4>
                                    <div class="customer-grid">
                                        <div>
                                            <span class="label">Name:</span>
                                            <span class="value"><?php echo htmlspecialchars($order['buyer_name']); ?></span>
                                        </div>
                                        <div>
                                            <span class="label">Email:</span>
                                            <span class="value"><?php echo htmlspecialchars($order['buyer_email']); ?></span>
                                        </div>
                                        <div>
                                            <span class="label">Phone:</span>
                                            <span class="value"><?php echo htmlspecialchars($order['delivery_phone']); ?></span>
                                        </div>
                                        <div>
                                            <span class="label">Payment:</span>
                                            <span class="value payment-method"><?php echo ucfirst($order['payment_method']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="delivery-section">
                                    <h4>📍 Delivery Details</h4>
                                    <div class="delivery-info">
                                        <div class="delivery-address">
                                            <span class="label">Address:</span>
                                            <span><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                                        </div>
                                        <div>
                                            <span class="label">City:</span>
                                            <span><?php echo htmlspecialchars($order['delivery_city']); ?></span>
                                        </div>
                                        <div>
                                            <span class="label">Delivery Date:</span>
                                            <span class="delivery-date">📅 <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-summary">
                                    <div class="items-list">
                                        <span class="label">Items:</span>
                                        <span><?php echo htmlspecialchars($order['items']); ?></span>
                                    </div>
                                    <div class="total-amount">
                                        <span class="label">Your Total:</span>
                                        <span class="amount">₦<?php echo number_format($order['seller_subtotal'], 2); ?></span>
                                    </div>
                                </div>

                                <form method="POST" class="status-form">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>" />
                                    <div class="status-control">
                                        <label>Update Status:</label>
                                        <select name="status" class="status-select">
                                            <option value="processing" <?php echo $order['seller_status'] === 'processing' ? 'selected' : ''; ?>>⏳ Processing</option>
                                            <option value="completed" <?php echo $order['seller_status'] === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                                            <option value="cancelled" <?php echo $order['seller_status'] === 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="update-btn">Update Status</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span style="font-size: 64px;">🛒</span>
                    <h3>No Orders Yet</h3>
                    <p>When customers purchase your products, they'll appear here.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>