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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_from_cart'])) {
        $product_id = $_POST['product_id'] ?? 0;
        if ($product_id > 0) {
            $delete = $conn->prepare("DELETE FROM cart WHERE buyer_id = ? AND product_id = ?");
            $delete->bind_param("ii", $buyer_id, $product_id);
            $delete->execute();
            $delete->close();
            $_SESSION['cart_message'] = '🗑️ Product removed from cart';
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['update_quantity'])) {
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($product_id > 0 && $quantity > 0) {
            $stock_check = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stock_check->bind_param("i", $product_id);
            $stock_check->execute();
            $stock_data = $stock_check->get_result()->fetch_assoc();
            $stock_check->close();
            
            $available_stock = $stock_data['stock'] ?? 0;
            
            if ($quantity > $available_stock) {
                $_SESSION['cart_message'] = '⚠️ Only ' . $available_stock . ' available in stock.';
            } else {
                $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
                $update->bind_param("iii", $quantity, $buyer_id, $product_id);
                $update->execute();
                $update->close();
                $_SESSION['cart_message'] = '✅ Quantity updated';
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$cart_message = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);

$cart_items = $conn->query("
    SELECT c.*, p.name, p.price, p.image, p.image_path, p.seller_id, s.business_name, p.stock as max_stock
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    JOIN sellers s ON p.seller_id = s.id 
    WHERE c.buyer_id = $buyer_id
");
$cart_total = getCartTotal($conn, $buyer_id);
$subtotal = $cart_total;
$tax = $subtotal * 0.075;
$delivery_fee = 1500;
$grand_total = $subtotal + $tax + $delivery_fee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Cart</title>
    <link rel="stylesheet" href="buyer_cart.css" />
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
                <a href="buyer_cart.php" class="nav-item active">
                    <span class="nav-icon">🛒</span>
                    Cart
                    <?php if ($cart_count > 0): ?>
                        <span class="badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="buyer_orders.php" class="nav-item">
                    <span class="nav-icon">📋</span>
                    Orders
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
                <h2>🛒 Shopping Cart</h2>
                <p>Review your items and proceed to checkout</p>
            </div>

            <?php if ($cart_message): ?>
                <div class="notification-message"><?php echo $cart_message; ?></div>
            <?php endif; ?>

            <?php if ($cart_items->num_rows > 0): ?>
                <div class="cart-layout">
                    <div class="cart-items-section">
                        <?php while ($item = $cart_items->fetch_assoc()): ?>
                            <div class="cart-item">
                                <div class="cart-item-info">
                                    <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="cart-item-image-upload" />
                                    <?php else: ?>
                                        <span class="cart-item-image"><?php echo htmlspecialchars($item['image'] ?? '📦'); ?></span>
                                    <?php endif; ?>
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                        <p class="cart-item-seller">🏪 <?php echo htmlspecialchars($item['business_name']); ?></p>
                                        <p class="cart-item-price">₦<?php echo number_format($item['price'], 2); ?></p>
                                        <p class="cart-item-stock-info">Stock available: <?php echo $item['max_stock']; ?></p>
                                    </div>
                                </div>
                                <div class="cart-item-actions">
                                    <form method="POST" class="quantity-form">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>" />
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['max_stock']; ?>" />
                                        <button type="submit" name="update_quantity" class="update-btn">Update</button>
                                    </form>
                                    <span class="cart-item-total">₦<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>" />
                                        <button type="submit" name="remove_from_cart" class="remove-btn">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="cart-summary-section">
                        <div class="summary-card">
                            <h3>Order Summary</h3>
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>₦<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (7.5%)</span>
                                <span>₦<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Delivery Fee</span>
                                <span>₦<?php echo number_format($delivery_fee, 2); ?></span>
                            </div>
                            <div class="summary-total">
                                <span>Total</span>
                                <span>₦<?php echo number_format($grand_total, 2); ?></span>
                            </div>
                            <div class="balance-check">
                                <span>Available Balance:</span>
                                <span class="balance-amount <?php echo $balance >= $grand_total ? 'sufficient' : 'insufficient'; ?>">
                                    ₦<?php echo number_format($balance, 2); ?>
                                    <?php if ($balance < $grand_total): ?>
                                        <span class="insufficient-label">⚠️ Insufficient</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($balance >= $grand_total): ?>
                                <button onclick="showCheckout()" class="checkout-btn">Proceed to Checkout</button>
                            <?php else: ?>
                                <button onclick="showInsufficientFundsModal()" class="checkout-btn disabled">Insufficient Balance</button>
                                <p class="balance-warning">⚠️ Please add funds to your wallet to complete this order.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="checkoutSection" class="checkout-section" style="display: none;">
                    <div class="checkout-card">
                        <h3>📋 Delivery & Payment</h3>
                        <form method="POST" action="buyer_checkout.php" onsubmit="return validateCheckout()">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($buyer['full_name']); ?>" required />
                            </div>
                            <div class="form-group">
                                <label>Delivery Address *</label>
                                <textarea name="delivery_address" rows="3" required placeholder="Enter your delivery address"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>City *</label>
                                    <input type="text" name="city" required placeholder="City" />
                                </div>
                                <div class="form-group">
                                    <label>Phone Number *</label>
                                    <input type="tel" name="phone" required placeholder="Phone number" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Payment Method *</label>
                                <select name="payment_method" required onchange="togglePaymentFields(this.value)">
                                    <option value="balance">Pay with Balance (₦<?php echo number_format($balance, 2); ?>)</option>
                                    <option value="card">Pay with Card</option>
                                </select>
                            </div>
                            <div id="cardDetails" style="display: none;">
                                <div class="card-details-section">
                                    <h4>💳 Card Details</h4>
                                    <div class="form-group">
                                        <label>Card Number *</label>
                                        <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" 
                                               oninput="formatCardNumber(this)" />
                                    </div>
                                    <div class="form-group">
                                        <label>Cardholder Name *</label>
                                        <input type="text" name="card_name" placeholder="John Doe" />
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Expiry Date *</label>
                                            <input type="text" name="expiry" placeholder="MM/YY" maxlength="5" 
                                                   oninput="formatExpiry(this)" />
                                        </div>
                                        <div class="form-group">
                                            <label>CVV *</label>
                                            <input type="password" name="cvv" placeholder="***" maxlength="4" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>" />
                            <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
                            <button type="button" onclick="hideCheckout()" class="cancel-btn">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span style="font-size: 48px;">🛒</span>
                    <p>Your cart is empty.</p>
                    <a href="buyer_shop.php" class="continue-shopping">Continue Shopping</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <div id="insufficientFundsModal" class="modal">
        <div class="modal-content error-content">
            <div class="error-icon">⚠️</div>
            <h2>Insufficient Balance!</h2>
            <p>Your available balance is not enough to complete this order.</p>
            <div class="balance-details">
                <div class="detail-row">
                    <span class="detail-label">Order Total:</span>
                    <span class="detail-value">₦<?php echo number_format($grand_total, 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Available Balance:</span>
                    <span class="detail-value" style="color: #dc3545;">₦<?php echo number_format($balance, 2); ?></span>
                </div>
                <div class="detail-row highlight">
                    <span class="detail-label">Balance Needed:</span>
                    <span class="detail-value" style="color: #e67a00;">₦<?php echo number_format($grand_total - $balance, 2); ?></span>
                </div>
            </div>
            <div class="modal-actions">
                <a href="buyer_dashboard.php" class="add-funds-btn-modal">➕ Add Funds Now</a>
                <button onclick="closeInsufficientFundsModal()" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function showCheckout() {
            document.getElementById('checkoutSection').style.display = 'block';
            document.querySelector('.checkout-section').scrollIntoView({ behavior: 'smooth' });
        }

        function hideCheckout() {
            document.getElementById('checkoutSection').style.display = 'none';
        }

        function showInsufficientFundsModal() {
            document.getElementById('insufficientFundsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeInsufficientFundsModal() {
            document.getElementById('insufficientFundsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function togglePaymentFields(value) {
            if (value === 'card') {
                document.getElementById('cardDetails').style.display = 'block';
            } else {
                document.getElementById('cardDetails').style.display = 'none';
            }
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

        function validateCheckout() {
            const paymentMethod = document.querySelector('select[name="payment_method"]').value;
            if (paymentMethod === 'card') {
                const cardNumber = document.querySelector('input[name="card_number"]').value.replace(/\s/g, '');
                const cardName = document.querySelector('input[name="card_name"]').value;
                const expiry = document.querySelector('input[name="expiry"]').value;
                const cvv = document.querySelector('input[name="cvv"]').value;
                
                if (cardNumber.length < 16) {
                    alert('Please enter a valid 16-digit card number.');
                    return false;
                }
                if (!cardName) {
                    alert('Please enter the cardholder name.');
                    return false;
                }
                if (!expiry || expiry.length < 5) {
                    alert('Please enter a valid expiry date (MM/YY).');
                    return false;
                }
                if (!cvv || cvv.length < 3) {
                    alert('Please enter a valid CVV.');
                    return false;
                }
            }
            return true;
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('insufficientFundsModal')) {
                closeInsufficientFundsModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeInsufficientFundsModal();
            }
        });
    </script>
</body>
</html>