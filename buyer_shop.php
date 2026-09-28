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
$wishlist_count = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE buyer_id = $buyer_id")->fetch_assoc()['count'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $product_id = $_POST['product_id'] ?? 0;
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($product_id > 0 && $quantity > 0) {
            $product_check = $conn->prepare("SELECT stock, name FROM products WHERE id = ? AND status = 'active'");
            $product_check->bind_param("i", $product_id);
            $product_check->execute();
            $product_data = $product_check->get_result()->fetch_assoc();
            $product_check->close();
            
            if (!$product_data) {
                $_SESSION['shop_message'] = '❌ Product not available.';
            } else {
                $available_stock = $product_data['stock'];
                
                $cart_check = $conn->prepare("SELECT quantity FROM cart WHERE buyer_id = ? AND product_id = ?");
                $cart_check->bind_param("ii", $buyer_id, $product_id);
                $cart_check->execute();
                $cart_row = $cart_check->get_result()->fetch_assoc();
                $cart_check->close();
                
                $current_in_cart = $cart_row ? $cart_row['quantity'] : 0;
                $new_total = $current_in_cart + $quantity;
                
                if ($current_in_cart >= $available_stock) {
                    $_SESSION['shop_message'] = '⚠️ You already have all ' . $available_stock . ' available in your cart.';
                } elseif ($new_total > $available_stock) {
                    $can_add = $available_stock - $current_in_cart;
                    $_SESSION['shop_message'] = '⚠️ Only ' . $can_add . ' more available. You already have ' . $current_in_cart . ' in cart.';
                } else {
                    if ($cart_row) {
                        $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE buyer_id = ? AND product_id = ?");
                        $update->bind_param("iii", $new_total, $buyer_id, $product_id);
                        $update->execute();
                        $update->close();
                    } else {
                        $insert = $conn->prepare("INSERT INTO cart (buyer_id, product_id, quantity) VALUES (?, ?, ?)");
                        $insert->bind_param("iii", $buyer_id, $product_id, $quantity);
                        $insert->execute();
                        $insert->close();
                    }
                    $_SESSION['shop_message'] = '✅ Product added to cart!';
                }
            }
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['add_to_wishlist'])) {
        $product_id = $_POST['product_id'] ?? 0;
        if ($product_id > 0) {
            $check = $conn->prepare("SELECT id FROM wishlist WHERE buyer_id = ? AND product_id = ?");
            $check->bind_param("ii", $buyer_id, $product_id);
            $check->execute();
            if ($check->get_result()->num_rows == 0) {
                $insert = $conn->prepare("INSERT INTO wishlist (buyer_id, product_id) VALUES (?, ?)");
                $insert->bind_param("ii", $buyer_id, $product_id);
                $insert->execute();
                $insert->close();
                $_SESSION['shop_message'] = '❤️ Added to wishlist!';
            } else {
                $_SESSION['shop_message'] = '⚠️ Already in wishlist';
            }
            $check->close();
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$shop_message = $_SESSION['shop_message'] ?? '';
unset($_SESSION['shop_message']);

$products_query = $conn->query("
    SELECT p.*, s.business_name, s.email,
        COALESCE(c.quantity, 0) as in_cart
    FROM products p 
    JOIN sellers s ON p.seller_id = s.id 
    LEFT JOIN cart c ON c.product_id = p.id AND c.buyer_id = $buyer_id
    WHERE p.status = 'active' AND p.stock > 0 
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Shop</title>
    <link rel="stylesheet" href="buyer_shop.css" />
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
                <a href="buyer_shop.php" class="nav-item active">
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
                <h2>🛍️ Shop</h2>
                <p>Discover amazing products from trusted sellers</p>
            </div>

            <?php if ($shop_message): ?>
                <div class="notification-message"><?php echo $shop_message; ?></div>
            <?php endif; ?>

            <div class="product-grid">
                <?php if ($products_query->num_rows > 0): ?>
                    <?php while ($product = $products_query->fetch_assoc()): 
                        $remaining = $product['stock'] - $product['in_cart'];
                    ?>
                        <div class="product-card">
                            <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image-medium" />
                            <?php else: ?>
                                <div class="product-image-placeholder"><?php echo htmlspecialchars($product['image'] ?? '📦'); ?></div>
                            <?php endif; ?>
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <p class="product-seller">🏪 <?php echo htmlspecialchars($product['business_name'] ?? $product['email']); ?></p>
                            <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                            <p class="product-price">₦<?php echo number_format($product['price'], 2); ?></p>
                            <p class="product-stock">
                                <?php if ($product['in_cart'] > 0): ?>
                                    🛒 In Cart: <?php echo $product['in_cart']; ?> / <?php echo $product['stock']; ?>
                                <?php else: ?>
                                    📦 In Stock: <?php echo $product['stock']; ?>
                                <?php endif; ?>
                            </p>
                            <div class="product-actions">
                                <button onclick="viewProduct(<?php echo $product['id']; ?>)" class="view-btn">👁️ View</button>
                                <?php if ($remaining > 0): ?>
                                    <form method="POST" style="margin: 0; display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
                                        <input type="hidden" name="quantity" value="1" />
                                        <button type="submit" name="add_to_cart" class="add-cart-btn">🛒 Add</button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-cart-btn disabled" disabled>Max in Cart</button>
                                <?php endif; ?>
                                <form method="POST" style="margin: 0; display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
                                    <button type="submit" name="add_to_wishlist" class="wishlist-btn">❤️</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <span style="font-size: 48px;">🛍️</span>
                        <p>No products available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="productModal" class="modal">
        <div class="modal-content product-modal scrollable">
            <div class="modal-header">
                <h3>📦 Product Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="productDetails">
                <div class="product-view">
                    <div class="product-view-image-container">
                        <img id="viewImage" src="" alt="Product" class="product-view-image" />
                        <div id="viewImagePlaceholder" class="product-view-placeholder">📦</div>
                    </div>
                    <div class="product-view-info">
                        <h2 id="viewName">Product Name</h2>
                        <p class="view-seller" id="viewSeller">🏪 Seller Name</p>
                        <p class="view-desc" id="viewDesc">Description</p>
                        <p class="view-price" id="viewPrice">₦0.00</p>
                        <p class="view-stock" id="viewStock">In Stock: 0</p>
                        <p class="view-in-cart" id="viewInCart"></p>
                        <div class="product-actions-modal">
                            <form method="POST" style="margin: 0; display: inline;">
                                <input type="hidden" name="product_id" id="viewProductId" value="" />
                                <input type="hidden" name="quantity" value="1" />
                                <button type="submit" name="add_to_cart" class="add-cart-btn" id="viewCartBtn">🛒 Add to Cart</button>
                            </form>
                            <form method="POST" style="margin: 0; display: inline;">
                                <input type="hidden" name="product_id" id="viewProductId2" value="" />
                                <button type="submit" name="add_to_wishlist" class="wishlist-btn">❤️ Add to Wishlist</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const products = <?php 
            $products_data = [];
            $products_query->data_seek(0);
            while ($p = $products_query->fetch_assoc()) {
                $products_data[] = $p;
            }
            echo json_encode($products_data);
        ?>;

        function viewProduct(productId) {
            const product = products.find(p => p.id == productId);
            if (product) {
                const img = document.getElementById('viewImage');
                const placeholder = document.getElementById('viewImagePlaceholder');
                const remaining = product.stock - product.in_cart;
                
                if (product.image_path && product.image_path !== '') {
                    img.src = product.image_path;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                } else {
                    img.style.display = 'none';
                    placeholder.style.display = 'block';
                    placeholder.textContent = product.image || '📦';
                }
                
                document.getElementById('viewName').textContent = product.name;
                document.getElementById('viewSeller').textContent = '🏪 ' + (product.business_name || product.email);
                document.getElementById('viewDesc').textContent = product.description || 'No description available.';
                document.getElementById('viewPrice').textContent = '₦' + parseFloat(product.price).toFixed(2);
                document.getElementById('viewStock').textContent = 'In Stock: ' + product.stock;
                document.getElementById('viewProductId').value = product.id;
                document.getElementById('viewProductId2').value = product.id;
                
                const inCartEl = document.getElementById('viewInCart');
                const cartBtn = document.getElementById('viewCartBtn');
                
                if (product.in_cart > 0) {
                    inCartEl.textContent = '🛒 In Cart: ' + product.in_cart + ' of ' + product.stock;
                    inCartEl.style.display = 'block';
                } else {
                    inCartEl.style.display = 'none';
                }
                
                if (remaining <= 0) {
                    cartBtn.disabled = true;
                    cartBtn.textContent = '❌ Max Quantity in Cart';
                    cartBtn.classList.add('disabled');
                } else {
                    cartBtn.disabled = false;
                    cartBtn.textContent = '🛒 Add to Cart';
                    cartBtn.classList.remove('disabled');
                }
                
                document.getElementById('productModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('productModal')) {
                closeModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>