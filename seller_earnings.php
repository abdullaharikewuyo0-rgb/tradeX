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

$total_earnings = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id AND o.status = 'completed'
")->fetch_assoc()['total'] ?? 0;

$total_orders = $conn->query("
    SELECT COUNT(DISTINCT o.id) as count 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id
")->fetch_assoc()['count'] ?? 0;

$total_items_sold = $conn->query("
    SELECT SUM(oi.quantity) as total 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id
")->fetch_assoc()['total'] ?? 0;

$average_order_value = $total_orders > 0 ? $total_earnings / $total_orders : 0;
$net_profit = $total_earnings * 0.95;

$monthly_earnings = $conn->query("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(oi.quantity * oi.price) as total
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id AND o.status = 'completed'
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

$top_products = $conn->query("
    SELECT 
        p.id,
        p.name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.price) as total_revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.seller_id = $seller_id AND o.status = 'completed'
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 5
");

$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

$current_earnings = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id AND o.status = 'completed' AND DATE_FORMAT(o.created_at, '%Y-%m') = '$current_month'
")->fetch_assoc()['total'] ?? 0;

$last_month_earnings = $conn->query("
    SELECT SUM(oi.quantity * oi.price) as total 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.seller_id = $seller_id AND o.status = 'completed' AND DATE_FORMAT(o.created_at, '%Y-%m') = '$last_month'
")->fetch_assoc()['total'] ?? 0;

$growth_percentage = $last_month_earnings > 0 ? (($current_earnings - $last_month_earnings) / $last_month_earnings) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Earnings</title>
    <link rel="stylesheet" href="seller_earnings.css" />
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
                <a href="seller_orders.php" class="nav-item">
                    <span class="nav-icon">🛒</span>
                    Orders
                </a>
                <a href="seller_earnings.php" class="nav-item active">
                    <span class="nav-icon">💰</span>
                    Earnings
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-section">
                <h2>💰 Earnings Analytics</h2>
                <p>Track your revenue and sales performance</p>
            </div>

            <div class="wallet-withdraw-card">
                <div class="wallet-info">
                    <span class="wallet-label">💰 Available Balance</span>
                    <span class="wallet-balance">₦<?php echo number_format($net_profit, 2); ?></span>
                    <span style="font-size: 13px; opacity: 0.85; margin-top: 4px; display: block;">
                        Net profit after 5% commission
                    </span>
                </div>
                <button onclick="openWithdrawModal()" class="withdraw-btn">🏦 Withdraw</button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <span class="stat-number">₦<?php echo number_format($total_earnings, 2); ?></span>
                        <span class="stat-label">Total Earnings</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_orders; ?></span>
                        <span class="stat-label">Total Orders</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_items_sold; ?></span>
                        <span class="stat-label">Items Sold</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <span class="stat-number">₦<?php echo number_format($average_order_value, 2); ?></span>
                        <span class="stat-label">Avg Order Value</span>
                    </div>
                </div>
            </div>

            <div class="growth-card">
                <div class="growth-header">
                    <span class="growth-label">📈 Monthly Growth</span>
                    <span class="growth-value <?php echo $growth_percentage >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $growth_percentage >= 0 ? '↑' : '↓'; ?> <?php echo number_format(abs($growth_percentage), 1); ?>%
                    </span>
                </div>
                <div class="growth-details">
                    <span>This Month: ₦<?php echo number_format($current_earnings, 2); ?></span>
                    <span>Last Month: ₦<?php echo number_format($last_month_earnings, 2); ?></span>
                </div>
            </div>

            <div class="chart-section">
                <h3>📊 Monthly Earnings</h3>
                <div class="chart-container">
                    <?php 
                    $months = [];
                    $amounts = [];
                    while ($row = $monthly_earnings->fetch_assoc()) {
                        $months[] = date('M Y', strtotime($row['month'] . '-01'));
                        $amounts[] = $row['total'];
                    }
                    $months = array_reverse($months);
                    $amounts = array_reverse($amounts);
                    
                    if (!empty($months)) {
                        $max_amount = max($amounts) > 0 ? max($amounts) : 1;
                    ?>
                        <div class="bar-chart">
                            <?php for ($i = 0; $i < count($months); $i++): ?>
                                <div class="bar-item">
                                    <div class="bar-label"><?php echo $months[$i]; ?></div>
                                    <div class="bar-wrapper">
                                        <div class="bar" style="height: <?php echo ($amounts[$i] / $max_amount) * 100; ?>%;">
                                            <span class="bar-value">₦<?php echo number_format($amounts[$i], 2); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    <?php } else { ?>
                        <p style="text-align: center; color: #8a7a6a; padding: 40px;">No earnings data available yet</p>
                    <?php } ?>
                </div>
            </div>

            <div class="products-section">
                <h3>🏆 Top Selling Products</h3>
                <?php if ($top_products->num_rows > 0): ?>
                    <div class="products-list">
                        <?php while ($product = $top_products->fetch_assoc()): ?>
                            <div class="product-item">
                                <div class="product-info">
                                    <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                    <span class="product-sold"><?php echo $product['total_sold']; ?> sold</span>
                                </div>
                                <div class="product-revenue">
                                    ₦<?php echo number_format($product['total_revenue'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #8a7a6a; padding: 20px;">No products sold yet</p>
                <?php endif; ?>
            </div>

            <div class="profit-section">
                <h3>📈 Profit Analysis</h3>
                <div class="profit-grid">
                    <div class="profit-card">
                        <span class="profit-label">Total Revenue</span>
                        <span class="profit-value">₦<?php echo number_format($total_earnings, 2); ?></span>
                    </div>
                    <div class="profit-card">
                        <span class="profit-label">Average Profit per Order</span>
                        <span class="profit-value">₦<?php echo number_format($average_order_value * 0.95, 2); ?></span>
                    </div>
                    <div class="profit-card">
                        <span class="profit-label">Total Items</span>
                        <span class="profit-value"><?php echo $total_items_sold; ?></span>
                    </div>
                    <div class="profit-card">
                        <span class="profit-label">Commission (5%)</span>
                        <span class="profit-value">₦<?php echo number_format($total_earnings * 0.05, 2); ?></span>
                    </div>
                    <div class="profit-card highlight">
                        <span class="profit-label">Net Profit</span>
                        <span class="profit-value">₦<?php echo number_format($net_profit, 2); ?></span>
                    </div>
                    <div class="profit-card">
                        <span class="profit-label">Profit Margin</span>
                        <span class="profit-value"><?php echo $total_earnings > 0 ? '95%' : '0%'; ?></span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="withdrawModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏦 Withdraw Funds</h3>
                <span class="close" onclick="closeWithdrawModal()">&times;</span>
            </div>
            <form method="POST" action="seller_withdraw.php">
                <div class="form-group">
                    <label>Amount (₦)</label>
                    <input type="number" name="amount" placeholder="Enter amount" step="0.01" min="0.01" required />
                    <small>Available: ₦<?php echo number_format($net_profit, 2); ?></small>
                </div>
                <div class="form-group">
                    <label>Bank Name *</label>
                    <select name="bank_name" required>
                        <option value="">Select Bank</option>
                        <option value="Access Bank">Access Bank</option>
                        <option value="GTBank">GTBank</option>
                        <option value="First Bank">First Bank</option>
                        <option value="Zenith Bank">Zenith Bank</option>
                        <option value="UBA">UBA</option>
                        <option value="Opay">Opay</option>
                        <option value="PalmPay">PalmPay</option>
                        <option value="Moniepoint">Moniepoint</option>
                        <option value="Kuda Bank">Kuda Bank</option>
                        <option value="Sterling Bank">Sterling Bank</option>
                        <option value="Fidelity Bank">Fidelity Bank</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Number *</label>
                    <input type="text" name="account_number" placeholder="0123456789" maxlength="10" required />
                </div>
                <div class="form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" placeholder="John Doe" />
                </div>
                <button type="submit" name="withdraw" class="submit-btn">Withdraw Funds</button>
                <button type="button" onclick="closeWithdrawModal()" class="cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

    <div id="withdrawSuccessModal" class="modal">
        <div class="modal-content success-content">
            <div class="success-icon">✅</div>
            <h2>Withdrawal Successful!</h2>
            <p>Your funds have been processed successfully.</p>
            <div class="withdraw-details">
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value" id="withdrawAmount">₦0.00</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bank:</span>
                    <span class="detail-value" id="withdrawBank">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Account Number:</span>
                    <span class="detail-value" id="withdrawAccount">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: #28a745;">✅ Completed</span>
                </div>
            </div>
            <button onclick="closeWithdrawSuccessModal()" class="submit-btn">Done</button>
        </div>
    </div>

    <script>
        function openWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'block';
        }

        function closeWithdrawModal() {
            document.getElementById('withdrawModal').style.display = 'none';
        }

        function openWithdrawSuccessModal(amount, bank, account) {
            document.getElementById('withdrawAmount').textContent = '₦' + parseFloat(amount).toFixed(2);
            document.getElementById('withdrawBank').textContent = bank;
            document.getElementById('withdrawAccount').textContent = account;
            document.getElementById('withdrawSuccessModal').style.display = 'block';
        }

        function closeWithdrawSuccessModal() {
            document.getElementById('withdrawSuccessModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('withdrawModal')) {
                closeWithdrawModal();
            }
            if (event.target == document.getElementById('withdrawSuccessModal')) {
                closeWithdrawSuccessModal();
            }
        }

        <?php if (isset($_SESSION['withdraw_success'])): ?>
            openWithdrawSuccessModal(
                '<?php echo $_SESSION['withdraw_amount']; ?>',
                '<?php echo $_SESSION['withdraw_bank']; ?>',
                '<?php echo $_SESSION['withdraw_account']; ?>'
            );
            <?php 
            unset($_SESSION['withdraw_success']);
            unset($_SESSION['withdraw_amount']);
            unset($_SESSION['withdraw_bank']);
            unset($_SESSION['withdraw_account']);
            ?>
        <?php endif; ?>
    </script>
</body>
</html>