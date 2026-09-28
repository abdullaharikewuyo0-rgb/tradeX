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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'] ?? '';
    $model = $_POST['model'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category = $_POST['category'] ?? '';
    $stock = $_POST['stock'] ?? 0;
    $image_path = '';
    
    if ($name && $price > 0) {
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }
        
        $status = ($stock > 0) ? 'active' : 'inactive';
        $full_name = $name . ($model ? ' - ' . $model : '');
        
        $stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, image_path, category, stock, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdsiis", $seller_id, $full_name, $description, $price, $image_path, $category, $stock, $status);
        
        if ($stmt->execute()) {
            $_SESSION['product_message'] = '<div class="success-message">✅ Product added successfully!</div>';
        } else {
            $_SESSION['product_message'] = '<div class="error-message">❌ Failed to add product.</div>';
        }
        $stmt->close();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['product_message'] = '<div class="error-message">❌ Please fill all required fields.</div>';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';
    if ($product_id > 0 && in_array($new_status, ['active', 'inactive'])) {
        $update = $conn->prepare("UPDATE products SET status = ? WHERE id = ? AND seller_id = ?");
        $update->bind_param("sii", $new_status, $product_id, $seller_id);
        $update->execute();
        $update->close();
        $_SESSION['product_message'] = '✅ Product status updated!';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'] ?? 0;
    if ($product_id > 0) {
        $delete = $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $delete->bind_param("ii", $product_id, $seller_id);
        $delete->execute();
        $delete->close();
        $_SESSION['product_message'] = '🗑️ Product deleted successfully!';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'] ?? 0;
    $new_stock = $_POST['stock'] ?? 0;
    if ($product_id > 0 && $new_stock >= 0) {
        $new_status = ($new_stock > 0) ? 'active' : 'inactive';
        $update = $conn->prepare("UPDATE products SET stock = ?, status = ? WHERE id = ? AND seller_id = ?");
        $update->bind_param("isii", $new_stock, $new_status, $product_id, $seller_id);
        $update->execute();
        $update->close();
        $_SESSION['product_message'] = '✅ Stock updated! Status auto-adjusted.';
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$product_message = $_SESSION['product_message'] ?? '';
unset($_SESSION['product_message']);

$products_query = $conn->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC");
$products_query->bind_param("i", $seller_id);
$products_query->execute();
$products = $products_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Products</title>
    <link rel="stylesheet" href="seller_products.css" />
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
                <a href="seller_products.php" class="nav-item active">
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
                <div>
                    <h2>📦 My Products</h2>
                    <p>Manage your product inventory</p>
                </div>
                <button onclick="openAddProductModal()" class="add-product-btn">
                    ➕ Add Product
                </button>
            </div>

            <?php if ($product_message): ?>
                <div class="notification-message"><?php echo $product_message; ?></div>
            <?php endif; ?>

            <div class="products-grid">
                <?php if ($products->num_rows > 0): ?>
                    <?php while ($product = $products->fetch_assoc()): 
                        $status = $product['status'];
                        $stock = $product['stock'];
                        if ($stock <= 0) {
                            $status = 'inactive';
                        }
                    ?>
                        <div class="product-card">
                            <div class="product-image-wrapper">
                                <?php if (!empty($product['image_path']) && file_exists($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" />
                                <?php else: ?>
                                    <div class="product-image-placeholder">📦</div>
                                <?php endif; ?>
                                <div class="product-status-badge <?php echo $status; ?>">
                                    <?php echo $status === 'active' ? '✅ Available' : '❌ Sold Out'; ?>
                                </div>
                            </div>
                            <div class="product-info">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 60)); ?></p>
                                <div class="product-meta">
                                    <span class="product-price">₦<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="product-stock">📦 <?php echo $product['stock']; ?> left</span>
                                </div>
                                <div class="product-category">
                                    <span class="category-tag"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                                </div>
                                <div class="product-actions">
                                    <div class="status-display <?php echo $status; ?>">
                                        <?php if ($stock > 0): ?>
                                            <span class="status-text available">✅ In Stock</span>
                                        <?php else: ?>
                                            <span class="status-text soldout">❌ Sold Out</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="POST" class="stock-form">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
                                        <input type="number" name="stock" value="<?php echo $product['stock']; ?>" min="0" class="stock-input" />
                                        <button type="submit" name="update_stock" class="update-stock-btn">Update</button>
                                    </form>
                                    
                                    <?php if ($stock > 0): ?>
                                        <form method="POST" style="margin: 0; display: inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
                                            <input type="hidden" name="new_status" value="<?php echo $status === 'active' ? 'inactive' : 'active'; ?>" />
                                            <button type="submit" name="toggle_status" class="status-btn <?php echo $status; ?>">
                                                <?php echo $status === 'active' ? 'Mark Sold Out' : 'Mark Available'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>" />
                                        <button type="submit" name="delete_product" class="delete-btn">🗑️</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <span style="font-size: 64px;">📦</span>
                        <h3>No Products Yet</h3>
                        <p>Start adding your products to sell!</p>
                        <button onclick="openAddProductModal()" class="empty-add-btn">➕ Add Your First Product</button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div id="addProductModal" class="modal">
        <div class="modal-content scrollable">
            <div class="modal-header">
                <h3>➕ Add New Product</h3>
                <span class="close" onclick="closeAddProductModal()">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" id="productName" placeholder="Enter product name" required />
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" placeholder="e.g., XR-2000, Pro Max" />
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Describe your product in detail"></textarea>
                </div>
                <div class="form-group">
                    <label>Price (₦) *</label>
                    <input type="number" name="price" id="productPrice" placeholder="0.00" step="0.01" min="0.01" required />
                </div>
                <div class="form-group">
                    <label>Quantity Available (Stock) *</label>
                    <input type="number" name="stock" id="productStock" placeholder="0" min="0" required />
                    <small>If quantity is 0, product will be marked as "Sold Out" automatically.</small>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" placeholder="e.g., Electronics, Fashion, Home" />
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="product_image" id="productImage" accept="image/*" onchange="previewImage(this)" />
                    <div id="imagePreview" style="margin-top: 8px;"></div>
                </div>
                <button type="submit" name="add_product" class="submit-btn">Add Product</button>
                <button type="button" onclick="closeAddProductModal()" class="cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #f0e8e0; object-fit: cover;" />';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = '';
            }
        }

        function validateForm() {
            const name = document.getElementById('productName').value.trim();
            const price = document.getElementById('productPrice').value;
            const stock = document.getElementById('productStock').value;
            
            if (!name) {
                alert('Please enter a product name.');
                return false;
            }
            if (!price || parseFloat(price) <= 0) {
                alert('Please enter a valid price.');
                return false;
            }
            if (stock === '' || parseInt(stock) < 0) {
                alert('Please enter a valid quantity.');
                return false;
            }
            return true;
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('addProductModal')) {
                closeAddProductModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAddProductModal();
            }
        });
    </script>
</body>
</html>