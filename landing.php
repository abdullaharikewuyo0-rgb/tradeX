<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'seller') {
        header('Location: seller.php');
        exit();
    } elseif ($_SESSION['role'] === 'buyer') {
        header('Location: buyer_dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TradeX · Buy · Sell · Trust</title>
    <link rel="stylesheet" href="landing.css" />
</head>
<body>
    <header class="landing-header">
        <div class="header-left">
            <span class="brand-icon">⚡</span>
            <span class="brand-name">TradeX</span>
        </div>
        <div class="header-right">
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['role'])): ?>
                <a href="<?php echo $_SESSION['role'] === 'seller' ? 'seller.php' : 'buyer_dashboard.php'; ?>" class="login-btn">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="login-btn">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <span class="hero-badge">🚀 Nigeria's Fastest Growing Marketplace</span>
            <h1>Buy. Sell. Trust.</h1>
            <p>TradeX is the modern e-commerce platform that connects buyers and sellers across Nigeria. Whether you're a small business owner looking to reach new customers or a shopper searching for the best deals — TradeX has you covered.</p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn-primary">Get Started</a>
                <a href="#services" class="btn-secondary">Learn More</a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card">
                <span class="hero-card-icon">🛍️</span>
                <span class="hero-card-title">10,000+</span>
                <span class="hero-card-label">Products Listed</span>
            </div>
            <div class="hero-card">
                <span class="hero-card-icon">👥</span>
                <span class="hero-card-title">5,000+</span>
                <span class="hero-card-label">Active Users</span>
            </div>
            <div class="hero-card">
                <span class="hero-card-icon">💰</span>
                <span class="hero-card-title">₦50M+</span>
                <span class="hero-card-label">Transactions</span>
            </div>
        </div>
    </section>

    <section class="about">
        <div class="section-header">
            <h2>Who We Are</h2>
            <p>TradeX is a Nigerian-built e-commerce platform designed to empower local businesses and give buyers a seamless shopping experience. We handle payments, security, and delivery logistics so you can focus on what matters.</p>
        </div>
    </section>

    <section class="services" id="services">
        <h2>Our Services</h2>
        <div class="services-grid">
            <div class="service-card">
                <div class="service-icon">🛍️</div>
                <h3>For Buyers</h3>
                <p>Browse thousands of products from verified sellers, add items to your cart, pay securely from your TradeX wallet or card, and get your order delivered the next day.</p>
                <ul class="service-features">
                    <li>✅ Verified sellers only</li>
                    <li>✅ Secure wallet payments</li>
                    <li>✅ Next-day delivery</li>
                    <li>✅ Wishlist & easy checkout</li>
                </ul>
            </div>
            <div class="service-card">
                <div class="service-icon">📦</div>
                <h3>For Sellers</h3>
                <p>Upload your products, manage inventory, track orders, and receive payments directly into your TradeX wallet. Withdraw to your bank anytime.</p>
                <ul class="service-features">
                    <li>✅ Free product listings</li>
                    <li>✅ Real-time order tracking</li>
                    <li>✅ Instant earnings dashboard</li>
                    <li>✅ Easy bank withdrawals</li>
                </ul>
            </div>
            <div class="service-card">
                <div class="service-icon">🔒</div>
                <h3>Secure Payments</h3>
                <p>TradeX uses a wallet-based payment system with proper transaction tracking. Every naira is accounted for — from buyer to seller.</p>
                <ul class="service-features">
                    <li>✅ Escrow-style protection</li>
                    <li>✅ Encrypted transactions</li>
                    <li>✅ Full transaction history</li>
                    <li>✅ Multiple payment options</li>
                </ul>
            </div>
            <div class="service-card">
                <div class="service-icon">🚚</div>
                <h3>Fast Delivery</h3>
                <p>Our logistics partners ensure your orders reach you within 24 hours. Track every step from seller to your doorstep.</p>
                <ul class="service-features">
                    <li>✅ Next-day delivery</li>
                    <li>✅ Delivery tracking</li>
                    <li>✅ Nationwide coverage</li>
                    <li>✅ Affordable delivery fee</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="why-us">
        <h2>Why Choose TradeX?</h2>
        <div class="why-grid">
            <div class="why-card">
                <span class="why-icon">⚡</span>
                <h4>Fast & Reliable</h4>
                <p>Built for speed with instant wallet transfers and same-day order processing.</p>
            </div>
            <div class="why-card">
                <span class="why-icon">🛡️</span>
                <h4>Secure</h4>
                <p>Your data and money are protected with industry-standard encryption.</p>
            </div>
            <div class="why-card">
                <span class="why-icon">💎</span>
                <h4>Trusted</h4>
                <p>Thousands of sellers and buyers trust TradeX every day.</p>
            </div>
            <div class="why-card">
                <span class="why-icon">🌍</span>
                <h4>Built for Nigeria</h4>
                <p>Local support, Naira pricing, and delivery to every state.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <h2>Ready to start trading?</h2>
        <p>Join thousands of buyers and sellers on Nigeria's friendliest marketplace.</p>
        <div class="cta-buttons">
            <a href="signup.php" class="btn-primary">Create Account</a>
            <a href="login.php" class="btn-secondary">Login</a>
        </div>
    </section>

    <footer class="landing-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <span class="brand-icon">⚡</span>
                <span class="brand-name">TradeX</span>
                <p>Buy · Sell · Trust</p>
                <div class="social-links">
                    <a href="#" target="_blank" rel="noopener" class="social-btn facebook" title="Facebook">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="#" target="_blank" rel="noopener" class="social-btn x" title="X">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                    </a>
                    <a href="#" target="_blank" rel="noopener" class="social-btn instagram" title="Instagram">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678a6.162 6.162 0 100 12.324 6.162 6.162 0 100-12.324zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405a1.441 1.441 0 01-2.88 0 1.44 1.44 0 012.88 0z"/>
                        </svg>
                    </a>
                    <a href="#" target="_blank" rel="noopener" class="social-btn whatsapp" title="WhatsApp">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                    </a>
                    <a href="#" target="_blank" rel="noopener" class="social-btn youtube" title="YouTube">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Platform</h4>
                <a href="signup.php">Sign Up</a>
                <a href="login.php">Login</a>
            </div>
            <div class="footer-links">
                <h4>Company</h4>
                <a href="#services">Services</a>
                <a href="#about">About</a>
            </div>
            <div class="footer-links">
                <h4>Contact</h4>
                <a href="mailto:support@tradex.com">support@tradex.com</a>
                <a href="#">Ilorin, Nigeria</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?php echo date('Y'); ?> TradeX. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>