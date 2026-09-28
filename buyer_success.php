<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php');
    exit();
}

$order_data = $_SESSION['order_success'] ?? null;
if (!$order_data) {
    header('Location: buyer_shop.php');
    exit();
}

$buyer_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM buyers WHERE id = ?");
$stmt->bind_param("i", $buyer_id);
$stmt->execute();
$result = $stmt->get_result();
$buyer = $result->fetch_assoc();
$stmt->close();

$balance = getWalletBalance($conn, $buyer_id, 'buyer');
$cart_count = getCartCount($conn, $buyer_id);

$tomorrow = date('F j, Y', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Order Successful</title>
    <link rel="stylesheet" href="buyer_success.css" />
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
                </a>
                <a href="buyer_orders.php" class="nav-item">
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
            <div class="success-container">
                <div class="success-icon">✅</div>
                <h1>Order Placed Successfully!</h1>
                
                <div class="order-details">
                    <div class="detail-row">
                        <span class="detail-label">Order Number:</span>
                        <span class="detail-value">#<?php echo $order_data['order_number']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Method:</span>
                        <span class="detail-value"><?php echo ucfirst($order_data['payment_method']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount:</span>
                        <span class="detail-value">₦<?php echo number_format($order_data['total_amount'], 2); ?></span>
                    </div>
                    <div class="detail-row highlight">
                        <span class="detail-label">📦 Delivery Date:</span>
                        <span class="detail-value"><?php echo $tomorrow; ?></span>
                    </div>
                </div>

                <div class="delivery-info">
                    <h3>🚚 Order will be delivered on</h3>
                    <p class="delivery-date"><?php echo $tomorrow; ?></p>
                    <p class="delivery-note">Your order will be delivered by 6:00 PM. Please ensure someone is available to receive the package.</p>
                    <div class="delivery-timeline">
                        <div class="timeline-step">
                            <span class="step-icon">✅</span>
                            <span class="step-label">Order Placed</span>
                            <span class="step-time">Today</span>
                        </div>
                        <div class="timeline-arrow">→</div>
                        <div class="timeline-step">
                            <span class="step-icon">📦</span>
                            <span class="step-label">Processing</span>
                            <span class="step-time">Today - <?php echo $tomorrow; ?></span>
                        </div>
                        <div class="timeline-arrow">→</div>
                        <div class="timeline-step">
                            <span class="step-icon">🚚</span>
                            <span class="step-label">Delivered</span>
                            <span class="step-time"><?php echo $tomorrow; ?></span>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="buyer_orders.php" class="btn btn-primary">View My Orders</a>
                    <a href="buyer_shop.php" class="btn btn-secondary">Continue Shopping</a>
                </div>
            </div>
        </main>
    </div>

    <?php
    unset($_SESSION['order_success']);
    ?>
</body>
</html>