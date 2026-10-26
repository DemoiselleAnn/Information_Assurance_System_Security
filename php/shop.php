<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'header.php';

// Product categories (updated for pastries)
$categories = [
    'all' => 'All Pastries',
    'cupcakes' => 'Cupcakes',
    'cookies' => 'Cookies',
    'breads' => 'Breads & Rolls',
    'tarts' => 'Tarts & Pies',
    'custom' => 'Custom Pastries' // Keep custom if applicable
];

// Product data (updated for pastries using gallery images)
$products = [
    [
        'id' => 'pastry-1',
        'img' => '../GalleryImgs/gallery1.png',
        'title' => 'Chocolate Chip Cookies',
        'price' => '₱350.00',
        'description' => 'Classic chewy chocolate chip cookies made with premium chocolate and butter.',
        'category' => 'cookies',
        'yield' => '1 dozen'
    ],
    [
        'id' => 'pastry-2',
        'img' => '../GalleryImgs/gallery2.png',
        'title' => 'Cakepops',
        'price' => '₱400.00',
        'description' => 'Delightful cake pops coated in chocolate and decorated with various toppings.',
        'category' => 'custom',
        'yield' => '6 pieces'
    ],
    [
        'id' => 'pastry-3',
        'img' => '../GalleryImgs/gallery3.png',
        'title' => 'Cupcake with Icing',
        'price' => '₱450.00',
        'description' => 'Moist cupcakes topped with rich, creamy icing in various flavors.',
        'category' => 'cupcakes',
        'yield' => '6 pieces'
    ],
    [
        'id' => 'pastry-4',
        'img' => '../GalleryImgs/gallery4.png',
        'title' => 'Cinnamon Roll',
        'price' => '₱380.00',
        'description' => 'Soft and fluffy cinnamon rolls with a sweet cinnamon filling and cream cheese frosting.',
        'category' => 'breads',
        'yield' => '4 pieces'
    ],
    [
        'id' => 'pastry-5',
        'img' => '../GalleryImgs/gallery5.png',
        'title' => 'Chocolate Mousse Cake',
        'price' => '₱850.00',
        'description' => 'Rich and decadent chocolate mousse cake with layers of chocolate sponge and silky mousse.',
        'category' => 'custom',
        'yield' => '8-inch cake'
    ],
    [
        'id' => 'pastry-6',
        'img' => '../GalleryImgs/gallery6.png',
        'title' => 'Assorted Macarons',
        'price' => '₱600.00',
        'description' => 'Delicate French macarons in various flavors with smooth ganache filling.',
        'category' => 'custom',
        'yield' => '12 pieces'
    ],
    [
        'id' => 'pastry-7',
        'img' => '../GalleryImgs/gallery7.png',
        'title' => 'Brownies',
        'price' => '₱400.00',
        'description' => 'Rich and fudgy chocolate brownies with a perfect balance of sweetness.',
        'category' => 'cookies',
        'yield' => '9 pieces'
    ],
    [
        'id' => 'pastry-8',
        'img' => '../GalleryImgs/gallery8.png',
        'title' => 'Eclairs',
        'price' => '₱450.00',
        'description' => 'Classic French eclairs filled with vanilla custard and topped with chocolate glaze.',
        'category' => 'custom',
        'yield' => '6 pieces'
    ]
];

// Filter products based on category
$selected_category = $_GET['category'] ?? 'all';
$filtered_products = array_filter($products, function($product) use ($selected_category) {
    return $selected_category === 'all' || $product['category'] === $selected_category;
});

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Cakes and Crafts</title>
    <link rel="stylesheet" href="../css/shop.css">
</head>

<body>
    <div class="shop-container">
        <div class="shop-header">
            <h1 class="shop-title">Our Pastries Collection</h1>
            <p class="shop-description">Discover our wide range of beautifully crafted pastries, perfect for any occasion. From delicious cupcakes to flaky croissants, we have something for everyone.</p>
        </div>

        <div class="category-filters">
            <?php foreach ($categories as $key => $name): ?>
                <a href="?category=<?php echo $key; ?>" 
                   class="category-btn <?php echo $selected_category === $key ? 'active' : ''; ?>">
                    <?php echo $name; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="products-grid">
            <?php if (empty($filtered_products)): ?>
                <p>No pastries found in this category.</p>
            <?php else: ?>
                <?php foreach ($filtered_products as $product): ?>
                    <div class="product-item">
                        <img src="<?php echo $product['img']; ?>" alt="<?php echo $product['title']; ?>">
                        <div class="product-info">
                            <h3><?php echo $product['title']; ?></h3>
                            <p><?php echo $product['description']; ?></p>
                            <span class="price"><?php echo $product['price']; ?></span>
                            <!-- Add details/yield if needed here -->
                            <?php if (isset($product['yield'])): ?>
                                <div class="product-details">
                                    <span><strong>Yield:</strong> <?php echo $product['yield']; ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="product-actions">
                                <a href="javascript:void(0)"
                                  onclick="addToCart('<?php echo urlencode($product['title']); ?>', '<?php echo urlencode($product['price']); ?>')"
                                  class="product-btn add-to-cart">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </a>
                                <a href="checkout.php?product=<?php echo urlencode($product['title']); ?>&price=<?php echo urlencode($product['price']); ?>"
                                   class="product-btn buy-now">
                                    <i class="fas fa-money-bill-wave"></i> Buy Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add to Cart Functionality
        function addToCart(product, price) {
            fetch('cart.php?action=add&product=' + product + '&price=' + price)
                .then(response => response.text())
                .then(data => {
                    // Show success message
                    const successMessage = document.createElement('div');
                    successMessage.className = 'cart-success-message';
                    successMessage.innerHTML = '<i class="fas fa-check-circle"></i> Added to cart!';
                    document.body.appendChild(successMessage);

                    // Remove message after 2 seconds
                    setTimeout(() => {
                        successMessage.remove();
                    }, 2000);

                    // Update cart count
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount) {
                        const currentCount = parseInt(cartCount.textContent) || 0;
                        cartCount.textContent = currentCount + 1;
                    } else {
                        // If cart count doesn't exist yet, create it
                        const cartLink = document.querySelector('.cart-link');
                        if (cartLink) {
                            const newCount = document.createElement('span');
                            newCount.className = 'cart-count';
                            newCount.textContent = '1';
                            cartLink.appendChild(newCount);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    </script>

    <?php require_once 'footer.php'; ?>
</body>
</html>





