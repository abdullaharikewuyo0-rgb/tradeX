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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_from_wishlist'])) {
        $product_id = $_POST['product_id'] ?? 0;
        if ($product_id > 0) {
            $delete = $conn->prepare("DELETE FROM wishlist WHERE buyer_id = ? AND product_id = ?");
            $delete->bind_param("ii", $buyer_id, $product_id);
            $delete->execute();
            $_SESSION['wishlist_message'] = '🗑️ Removed from wishlist';
        }
    }
    
    if (isset($_POST['add_to_cart_wishlist'])) {
        $product_id = $_POST['product_id'] ?? 0;
        if ($product_id > 0) {
            $check = $conn->prepare("SELECT id FROM cart WHERE buyer_id = ? AND product_id = ?");
            $check->bind_param("ii", $buyer_id, $product_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $update = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE buyer_id = ? AND product_id = ?");
                $update->bind_param("ii", $buyer_id, $product_id);
                $update->execute();
            } else {
                $insert = $conn->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, 1)");
                $insert->bind_param("ii", $buyer_id, $product_id);
                $insert->execute();
            }
            $_SESSION['wishlist_message'] = '✅ Added to cart!';
        }
    }
}

$wishlist_message = $_SESSION['wishlist_message'] ?? '';
unset($_SESSION['wishlist_message']);

$wishlist_items = $conn->query("
    SELECT w.*, p.name, p.price, p.image, p.stock, p.description, s.business_name, s.email
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    JOIN sellers s ON p.seller_id = s.id
    WHERE w.buyer_id = $buyer_id
    ORDER BY w.added_at DESC
");
$wishlist_count = $wishlist_items->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Wishlist</title>
    <link rel="stylesheet" href="buyer_wishlist.css" />
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
                <a href="buyer_orders.php" class="nav-item">
                    <span class="nav-icon">📋</span>
                    Orders
                </a>
                <a href="buyer_wishlist.php" class="nav-item active">
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
                <h2>❤️ My Wishlist</h2>
                <p>Products you've saved for later</p>
            </div>

            <?php if ($wishlist_message): ?>
                <div class="notification-message"><?php echo $wishlist_message; ?></div>
            <?php endif; ?>

            <?php if ($wishlist_items->num_rows > 0): ?>
                <div class="wishlist-grid">
                    <?php while ($item = $wishlist_items->fetch_assoc()): ?>
                        <div class="wishlist-card">
                            <div class="wishlist-image"><?php echo htmlspecialchars($item['image'] ?? '📦'); ?></div>
                            <div class="wishlist-info">
                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="wishlist-seller">🏪 <?php echo htmlspecialchars($item['business_name'] ?? $item['email']); ?></p>
                                <p class="wishlist-price">₦<?php echo number_format($item['price'], 2); ?></p>
                                <p class="wishlist-stock <?php echo $item['stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                    <?php echo $item['stock'] > 0 ? '✅ In Stock' : '❌ Out of Stock'; ?>
                                </p>
                            </div>
                            <div class="wishlist-actions">
                                <?php if ($item['stock'] > 0): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>" />
                                        <button type="submit" name="add_to_cart_wishlist" class="add-cart-btn">🛒 Add to Cart</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>" />
                                    <button type="submit" name="remove_from_wishlist" class="remove-btn">🗑️ Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span style="font-size: 48px;">❤️</span>
                    <p>Your wishlist is empty.</p>
                    <p class="empty-sub">Start adding items from the shop!</p>
                    <a href="buyer_shop.php" class="continue-shopping">Browse Shop</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>