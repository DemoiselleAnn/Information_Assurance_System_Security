<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'includes/header.php';

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart action
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['product'])) {
    $product = $_GET['product'];
    $price = isset($_GET['price']) ? $_GET['price'] : '₱0.00';
    
    // Add to cart array
    $_SESSION['cart'][] = [
        'product' => $product,
        'price' => $price,
        'quantity' => 1
    ];
    
    // Redirect to remove GET parameters
    header('Location: cart.php');
    exit();
}

// Handle remove from cart
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['index'])) {
    $index = (int)$_GET['index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
    }
    header('Location: cart.php');
    exit();
}

// Handle update quantity
if (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['index']) && isset($_GET['type'])) {
    $index = $_GET['index'];
    $type = $_GET['type'];
    
    if (isset($_SESSION['cart'][$index])) {
        if ($type === 'increase') {
            $_SESSION['cart'][$index]['quantity']++;
        } else if ($type === 'decrease') {
            $_SESSION['cart'][$index]['quantity']--;
            if ($_SESSION['cart'][$index]['quantity'] <= 0) {
                unset($_SESSION['cart'][$index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
            }
        }
        
        // If it's an AJAX request, return JSON response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            $response = [
                'success' => true,
                'cart_count' => count($_SESSION['cart']),
                'message' => 'Quantity updated successfully'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }
    // For non-AJAX requests, redirect
    header('Location: cart.php');
    exit();
}

// Calculate total
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    $price = str_replace(['₱', ','], '', $item['price']);
    $total += floatval($price) * $item['quantity'];
}

$formattedTotal = '₱' . number_format($total, 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Cakes and Crafts</title>
    <link rel="stylesheet" href="css/cart.css">
</head>

<body>
    <div class="cart-container">
        <h1 class="cart-title">Shopping Cart</h1>

        <?php if (empty($_SESSION['cart'])): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="continue-shopping">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                    <div class="cart-item">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['product']); ?></div>
                            <div class="item-price"><?php echo htmlspecialchars($item['price']); ?></div>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, 'decrease')">-</button>
                            <span><?php echo $item['quantity']; ?></span>
                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, 'increase')">+</button>
                        </div>
                        <button class="remove-btn" onclick="removeItem(<?php echo $index; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span><?php echo $formattedTotal; ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span>Free</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span><?php echo $formattedTotal; ?></span>
                </div>
                <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateQuantity(index, action) {
            // Show loading state
            const quantityBtn = event.target;
            const originalContent = quantityBtn.innerHTML;
            quantityBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            quantityBtn.disabled = true;

            // Make AJAX request
            fetch(`cart.php?action=update&index=${index}&type=${action}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show updated quantities
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                quantityBtn.innerHTML = originalContent;
                quantityBtn.disabled = false;
            });
        }

        function removeItem(index) {
            if (confirm('Are you sure you want to remove this item?')) {
                window.location.href = `cart.php?action=remove&index=${index}`;
            }
        }
    </script>
</body>
</html> 