<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php');
    exit();
}

$buyer_id = $_SESSION['user_id'];
$balance = getWalletBalance($conn, $buyer_id, 'buyer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['place_order'])) {
    header('Location: buyer_cart.php');
    exit();
}

$total_amount = $_POST['total_amount'] ?? 0;
$payment_method = $_POST['payment_method'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$delivery_address = $_POST['delivery_address'] ?? '';
$city = $_POST['city'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($payment_method === 'balance') {
    if ($balance < $total_amount) {
        $_SESSION['checkout_error'] = 'insufficient_balance';
        header('Location: buyer_cart.php');
        exit();
    }
}

$delivery_date = date('Y-m-d', strtotime('+1 day'));

$conn->begin_transaction();

try {
    $order_number = generateOrderNumber();
    
    $order_stmt = $conn->prepare("
        INSERT INTO orders (
            order_number, buyer_id, total_amount, payment_method, payment_status, 
            delivery_address, delivery_city, delivery_phone, delivery_date, status
        ) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?, 'processing')
    ");
    $order_stmt->bind_param("sidsssss", $order_number, $buyer_id, $total_amount, $payment_method, $delivery_address, $city, $phone, $delivery_date);
    $order_stmt->execute();
    $order_id = $conn->insert_id;
    $order_stmt->close();
    
    $cart_items = $conn->query("
        SELECT c.product_id, c.quantity, p.price, p.seller_id 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.buyer_id = $buyer_id
    ");
    
    while ($item = $cart_items->fetch_assoc()) {
        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $item_stmt->execute();
        $item_stmt->close();
        
        $update_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
        $update_stock->execute();
        $update_stock->close();
        
        if ($payment_method === 'balance') {
            $total_item = $item['quantity'] * $item['price'];
            $transfer_result = transferMoney($conn, $buyer_id, 'buyer', $item['seller_id'], 'seller', $total_item, "Order #$order_number - Product ID: {$item['product_id']}");
            
            if (!$transfer_result['success']) {
                throw new Exception($transfer_result['message']);
            }
        }
    }
    
    $conn->query("DELETE FROM cart WHERE buyer_id = $buyer_id");
    
    $conn->commit();
    
    $_SESSION['order_success'] = [
        'order_number' => $order_number,
        'delivery_date' => date('F j, Y', strtotime($delivery_date)),
        'payment_method' => $payment_method,
        'total_amount' => $total_amount
    ];
    
    header('Location: buyer_success.php');
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['checkout_error'] = '❌ Checkout failed: ' . $e->getMessage();
    header('Location: buyer_cart.php');
    exit();
}
?>