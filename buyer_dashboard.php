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

$balance = getWalletBalance($conn, $buyer_id, 'buyer');
$cart_count = getCartCount($conn, $buyer_id);
$wishlist_count = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE buyer_id = $buyer_id")->fetch_assoc()['count'];
$orders_count = $conn->query("SELECT COUNT(*) as count FROM orders WHERE buyer_id = $buyer_id")->fetch_assoc()['count'];

$fund_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_funds'])) {
    $amount = $_POST['amount'] ?? 0;
    $card_number = $_POST['card_number'] ?? '';
    $expiry = $_POST['expiry'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $card_name = $_POST['card_name'] ?? '';
    
    if ($amount > 0) {
        if (empty($card_number) || empty($expiry) || empty($cvv) || empty($card_name)) {
            $_SESSION['fund_message'] = '<div class="error-message">❌ Please fill in all card details.</div>';
        } elseif (strlen(preg_replace('/\s+/', '', $card_number)) < 16) {
            $_SESSION['fund_message'] = '<div class="error-message">❌ Please enter a valid 16-digit card number.</div>';
        } elseif (strlen($cvv) < 3) {
            $_SESSION['fund_message'] = '<div class="error-message">❌ Please enter a valid CVV.</div>';
        } else {
            $result = addFunds($conn, $buyer_id, 'buyer', $amount);
            if ($result['success']) {
                $last4 = substr(preg_replace('/\s+/', '', $card_number), -4);
                $_SESSION['fund_message'] = '<div class="success-message">✅ ₦' . number_format($amount, 2) . ' added to your wallet via card ending in ' . $last4 . '!</div>';
                $balance = getWalletBalance($conn, $buyer_id, 'buyer');
            } else {
                $_SESSION['fund_message'] = '<div class="error-message">❌ ' . $result['message'] . '</div>';
            }
        }
    } else {
        $_SESSION['fund_message'] = '<div class="error-message">❌ Please enter a valid amount.</div>';
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_SESSION['fund_message'])) {
    $fund_message = $_SESSION['fund_message'];
    unset($_SESSION['fund_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Dashboard</title>
    <link rel="stylesheet" href="buyer_dashboard.css" />
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
                <a href="buyer_dashboard.php" class="nav-item active">
                    <span class="nav-icon">🏠</span>
                    Dashboard
                </a>
                <a href="buyer_shop.php" class="nav-item">
                    <span class="nav-icon">🛍️</span>
                    Shop
                </a>
                <a href="buyer_wishlist.php" class="nav-item">
                    <span class="nav-icon">❤️</span>
                    Wishlist
                    <?php if ($wishlist_count > 0): ?>
                        <span class="badge"><?php echo $wishlist_count; ?></span>
                    <?php endif; ?>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($buyer['full_name'] ?? 'Buyer'); ?>!</h2>
                <p>Your shopping hub</p>
            </div>

            <?php if ($fund_message): ?>
                <div class="notification-message"><?php echo $fund_message; ?></div>
            <?php endif; ?>

            <div class="wallet-card">
                <div>
                    <div class="wallet-label">💰 Available Balance</div>
                    <div class="wallet-balance">₦<?php echo number_format($balance, 2); ?></div>
                </div>
                <div class="wallet-actions">
                    <button onclick="openFundModal()" class="add-funds-btn">
                        ➕ Add Funds
                    </button>
                </div>
            </div>

            <div class="quick-actions">
                <a href="buyer_orders.php" class="quick-action">
                    <span class="action-icon">📋</span>
                    <span class="action-label">My Orders</span>
                    <span class="action-count"><?php echo $orders_count; ?></span>
                </a>
                <a href="buyer_cart.php" class="quick-action">
                    <span class="action-icon">🛒</span>
                    <span class="action-label">Cart</span>
                    <span class="action-count"><?php echo $cart_count; ?></span>
                </a>
            </div>

            <div class="recent-activity">
                <h3>📊 Recent Transactions</h3>
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
                        <?php
                        $transactions = getTransactionHistory($conn, $buyer_id, 'buyer', 5);
                        if (empty($transactions)): ?>
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
                <div style="text-align: right; margin-top: 12px;">
                    <a href="buyer_orders.php" class="view-all-link">View All Orders →</a>
                </div>
            </div>
        </main>
    </div>

    <div id="fundModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>💰 Add Funds to Wallet</h3>
                <span class="close" onclick="closeFundModal()">&times;</span>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label>Amount (₦)</label>
                    <input type="number" name="amount" id="fundAmount" placeholder="Enter amount" step="0.01" min="0.01" required />
                </div>
                <div class="quick-amounts">
                    <button type="button" onclick="setAmount(1000)" class="quick-amount">₦1,000</button>
                    <button type="button" onclick="setAmount(5000)" class="quick-amount">₦5,000</button>
                    <button type="button" onclick="setAmount(10000)" class="quick-amount">₦10,000</button>
                    <button type="button" onclick="setAmount(50000)" class="quick-amount">₦50,000</button>
                    <button type="button" onclick="setAmount(100000)" class="quick-amount">₦100,000</button>
                    <button type="button" onclick="setAmount(500000)" class="quick-amount">₦500,000</button>
                </div>

                <div class="card-details-section">
                    <h4 style="color: #2d1f00; margin-bottom: 12px;">💳 Card Details</h4>
                    
                    <div class="form-group">
                        <label>Card Number *</label>
                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required 
                               oninput="formatCardNumber(this)" />
                    </div>
                    
                    <div class="form-group">
                        <label>Cardholder Name *</label>
                        <input type="text" name="card_name" placeholder="John Doe" required />
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Date *</label>
                            <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" required 
                                   oninput="formatExpiry(this)" />
                        </div>
                        <div class="form-group">
                            <label>CVV *</label>
                            <input type="password" name="cvv" placeholder="***" maxlength="4" required />
                        </div>
                    </div>
                </div>

                <button type="submit" name="add_funds" class="submit-btn">Add Funds</button>
                <button type="button" onclick="closeFundModal()" class="cancel-btn" style="margin-top: 8px;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openFundModal() {
            document.getElementById('fundModal').style.display = 'block';
            document.getElementById('fundAmount').focus();
        }

        function closeFundModal() {
            document.getElementById('fundModal').style.display = 'none';
        }

        function setAmount(amount) {
            document.getElementById('fundAmount').value = amount;
        }

        function formatCardNumber(input) {
            let value = input.value.replace(/\D/g, '');
            value = value.replace(/(.{4})/g, '$1 ').trim();
            input.value = value;
        }

        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            }
            input.value = value;
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('fundModal')) {
                closeFundModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeFundModal();
            }
        });
    </script>
</body>
</html>