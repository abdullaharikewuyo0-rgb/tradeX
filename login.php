<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, email, password FROM sellers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = 'seller';
                header('Location: seller.php');
                exit();
            } else {
                $error = 'Invalid password!';
            }
        } else {
            $stmt2 = $conn->prepare("SELECT id, email, password FROM buyers WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($row2 = $result2->fetch_assoc()) {
                if (password_verify($password, $row2['password'])) {
                    $_SESSION['user_id'] = $row2['id'];
                    $_SESSION['role'] = 'buyer';
                    header('Location: buyer_dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password!';
                }
            } else {
                $error = 'Account not found! Please sign up first.';
            }
            $stmt2->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Login</title>
    <link rel="stylesheet" href="login.css" />
</head>
<body>
    <a href="landing.php" class="home-btn" title="Back to Home">
        <span class="home-icon">←</span>
        <span class="home-text">Home</span>
    </a>

    <div class="login-container">
        <div class="login-card">
            <div class="brand">
                <span class="brand-icon">⚡</span>
                <h1>TradeX</h1>
                <p class="tagline">buy · sell · trust</p>
            </div>
            <h2>Welcome back</h2>
            <p class="subtitle">Login to your TradeX account</p>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="login-form" method="POST">
                <div class="input-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required />
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required />
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="login-footer">
                <p class="signup-link">Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>
</body>
</html>