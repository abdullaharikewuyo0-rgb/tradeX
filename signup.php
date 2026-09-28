<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $business_name = $_POST['business_name'] ?? '';
    
    if (empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        if ($role === 'seller') {
            $check = $conn->prepare("SELECT id FROM sellers WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'This email is already registered as a seller.';
            } else {
                $stmt = $conn->prepare("INSERT INTO sellers (email, password, business_name) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $hashed_password, $business_name);
                
                if ($stmt->execute()) {
                    $seller_id = $conn->insert_id;
                    $wallet_stmt = $conn->prepare("INSERT INTO seller_wallets (seller_id, balance) VALUES (?, 0)");
                    $wallet_stmt->bind_param("i", $seller_id);
                    $wallet_stmt->execute();
                    $wallet_stmt->close();
                    $success = 'Seller account created successfully! Please login.';
                    $stmt->close();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
            $check->close();
            
        } elseif ($role === 'buyer') {
            $check = $conn->prepare("SELECT id FROM buyers WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'This email is already registered as a buyer.';
            } else {
                $stmt = $conn->prepare("INSERT INTO buyers (email, password, full_name) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $hashed_password, $full_name);
                
                if ($stmt->execute()) {
                    $buyer_id = $conn->insert_id;
                    $wallet_stmt = $conn->prepare("INSERT INTO buyer_wallets (buyer_id, balance) VALUES (?, 0)");
                    $wallet_stmt->bind_param("i", $buyer_id);
                    $wallet_stmt->execute();
                    $wallet_stmt->close();
                    $success = 'Buyer account created successfully! Please login.';
                    $stmt->close();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
            $check->close();
        } else {
            $error = 'Please select a valid role.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Sign Up</title>
    <link rel="stylesheet" href="signup.css" />
</head>
<body>
    <a href="landing.php" class="home-btn" title="Back to Home">
        <span class="home-icon">←</span>
        <span class="home-text">Home</span>
    </a>

    <div class="signup-container">
        <div class="signup-card">
            <div class="brand">
                <span class="brand-icon">⚡</span>
                <h1>TradeX</h1>
                <p class="tagline">Join the marketplace</p>
            </div>
            <h2>Create Account</h2>
            <p class="subtitle">Sign up to start buying or selling</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="login.php" class="login-link">Go to Login →</a>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
                <form class="signup-form" method="POST">
                    <div class="role-selector">
                        <span class="role-label">I want to sign up as a</span>
                        <div class="role-options">
                            <label class="role-option">
                                <input type="radio" name="role" value="seller" checked />
                                <span class="role-badge seller">Seller</span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="buyer" />
                                <span class="role-badge buyer">Buyer</span>
                            </label>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="email">Email address *</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required />
                    </div>

                    <div id="nameFields">
                        <div class="input-group" id="sellerNameField">
                            <label for="business_name">Business Name</label>
                            <input type="text" id="business_name" name="business_name" placeholder="Your business name" />
                        </div>
                        <div class="input-group" id="buyerNameField" style="display: none;">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" placeholder="Your full name" />
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" placeholder="•••••••• (min 6 characters)" required minlength="6" />
                    </div>

                    <div class="input-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required />
                    </div>

                    <button type="submit" class="signup-btn">Create Account</button>
                </form>

                <div class="signup-footer">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                    <p class="hint">⚡ Choose your role and create your account</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const roleRadios = document.querySelectorAll('input[name="role"]');
        const sellerNameField = document.getElementById('sellerNameField');
        const buyerNameField = document.getElementById('buyerNameField');
        const businessNameInput = document.getElementById('business_name');
        const fullNameInput = document.getElementById('full_name');

        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'seller') {
                    sellerNameField.style.display = 'block';
                    buyerNameField.style.display = 'none';
                    businessNameInput.required = false;
                    fullNameInput.required = false;
                } else {
                    sellerNameField.style.display = 'none';
                    buyerNameField.style.display = 'block';
                    businessNameInput.required = false;
                    fullNameInput.required = true;
                }
            });
        });

        document.querySelector('input[name="role"][value="seller"]').checked = true;
        sellerNameField.style.display = 'block';
        buyerNameField.style.display = 'none';
    </script>
</body>
</html>