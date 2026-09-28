<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$balance = getWalletBalance($conn, $seller_id, 'seller');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = $_POST['amount'] ?? 0;
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    
    if ($amount <= 0) {
        $_SESSION['withdraw_error'] = 'Please enter a valid amount.';
        header('Location: seller.php');
        exit();
    }
    
    if ($amount > $balance) {
        $_SESSION['withdraw_error'] = 'Insufficient balance. Available: ₦' . number_format($balance, 2);
        header('Location: seller.php');
        exit();
    }
    
    if (empty($bank_name) || empty($account_number)) {
        $_SESSION['withdraw_error'] = 'Please fill in all required fields.';
        header('Location: seller.php');
        exit();
    }
    
    $result = transferMoney($conn, $seller_id, 'seller', 1, 'buyer', $amount, "Withdrawal to $bank_name - Account: $account_number");
    
    if ($result['success']) {
        $_SESSION['withdraw_success'] = true;
        $_SESSION['withdraw_amount'] = $amount;
        $_SESSION['withdraw_bank'] = $bank_name;
        $_SESSION['withdraw_account'] = $account_number;
        $_SESSION['withdraw_account_name'] = $account_name;
    } else {
        $_SESSION['withdraw_error'] = $result['message'];
    }
    
    header('Location: seller.php');
    exit();
} else {
    header('Location: seller.php');
    exit();
}
?>