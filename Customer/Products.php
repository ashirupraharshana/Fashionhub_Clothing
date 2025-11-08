<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../db_connect.php';

// ====== HANDLE ADD TO CART AJAX - MUST BE BEFORE ANY OUTPUT ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_add_to_cart'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first!']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];
    $price = $_POST['price'];
    $quantity = 1;

    // Check if product already exists in cart
    $check_sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update quantity and total price
        $update_sql = "UPDATE cart 
                       SET quantity = quantity + 1, 
                           total_price = price * (quantity + 1)
                       WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
    } else {
        // Insert new product into cart
        $total_price = $quantity * $price;
        $insert_sql = "INSERT INTO cart (user_id, product_id, quantity, price, total_price) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiidd", $user_id, $product_id, $quantity, $price, $total_price);
        $stmt->execute();
    }

    // Get updated cart count
    $cart_count_sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($cart_count_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result();
    $cart_count_row = $cart_count_result->fetch_assoc();
    $cart_count = $cart_count_row['total'] ?? 0;

    echo json_encode(['success' => true, 'message' => 'Product added to cart!', 'cart_count' => $cart_count]);
    exit;
}

// NOW include navbar after AJAX handling
include 'Components/CustomerNavBar.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Fetch products
$sql = "SELECT * FROM products ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            padding-top: 70px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 36px;
            color: #333;
            margin-bottom: 10px;
        }

        .page-header p {
            font-size: 16px;
            color: #666;
        }

        .gallery-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image-wrapper {
            position: relative;
            width: 100%;
            height: 300px;
            overflow: hidden;
            background: #f8f8f8;
        }

        .product-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover img {
            transform: scale(1.1);
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e74c3c;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            font-size: 12px;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .product-card h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 12px;
            line-height: 1.4;
            height: 50px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .add-cart-btn,
        .view-details-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-cart-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .add-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }

        .add-cart-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .view-details-btn {
            background: white;
            color: #333;
            border: 2px solid #e0e0e0;
        }

        .view-details-btn:hover {
            background: #f8f8f8;
            border-color: #ccc;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 18px 24px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            z-index: 3000;
            min-width: 300px;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 4px solid #4caf50;
        }

        .toast.error {
            border-left: 4px solid #f44336;
        }

        .toast i {
            font-size: 24px;
        }

        .toast.success i {
            color: #4caf50;
        }

        .toast.error i {
            color: #f44336;
        }

        .toast-message {
            flex: 1;
            color: #333;
            font-weight: 500;
        }

        .toast-close {
            background: transparent;
            border: none;
            color: #999;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .gallery-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .product-actions {
                flex-direction: column;
            }

            .toast {
                right: 15px;
                left: 15px;
                bottom: 15px;
                min-width: auto;
            }
        }
    </style>
</head>
<body>


    <div class="page-container">
        <div class="page-header">
            <h1>Our Products</h1>
            <p>Discover the latest fashion trends</p>
        </div>

        <div class="gallery-container">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image-wrapper">
                            <?php if ($row['product_photo']): ?>
                                <img src="data:image/jpeg;base64,<?php echo $row['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/300x300?text=No+Image" 
                                     alt="No image">
                            <?php endif; ?>
                            <span class="product-badge">New</span>
                        </div>
                        <div class="product-info">
                            <div class="product-category">Fashion</div>
                            <h3><?php echo htmlspecialchars($row['product_name']); ?></h3>
                            <div class="product-price">Rs. <?php echo number_format($row['price'], 2); ?></div>
                            <div class="product-actions">
                                <button type="button" 
                                        class="add-cart-btn" 
                                        onclick="addToCart(<?php echo $row['id']; ?>, <?php echo $row['price']; ?>, this)"
                                        data-product-id="<?php echo $row['id']; ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Add to Cart</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Available</h3>
                    <p>Check back later for new arrivals!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <div class="toast-message"></div>
        <button class="toast-close" onclick="hideToast()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <script>
        function addToCart(productId, price, button) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = '../index.php';
                return;
            <?php endif; ?>

            // Disable button and show loading
            button.disabled = true;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> Adding...';

            // Create FormData
            const formData = new FormData();
            formData.append('ajax_add_to_cart', '1');
            formData.append('product_id', productId);
            formData.append('price', price);

            // Send AJAX request
            fetch('Products.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Update cart badge
                    updateCartBadge(data.cart_count);
                    
                    // Refresh cart dropdown if exists
                    if (typeof refreshCartDropdown === 'function') {
                        setTimeout(() => {
                            location.reload(); // Reload to update cart dropdown
                        }, 500);
                    }
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to add product to cart', 'error');
            })
            .finally(() => {
                // Re-enable button
                button.disabled = false;
                button.innerHTML = originalHTML;
            });
        }

        function updateCartBadge(count) {
            const badge = document.querySelector('.icon-button .badge');
            if (badge) {
                badge.textContent = count;
            } else if (count > 0) {
                const cartButton = document.getElementById('cartToggle');
                if (cartButton) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge';
                    newBadge.textContent = count;
                    cartButton.appendChild(newBadge);
                }
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            const messageEl = toast.querySelector('.toast-message');

            // Set message and type
            messageEl.textContent = message;
            toast.className = `toast ${type}`;

            // Set icon
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
            } else {
                icon.className = 'fas fa-exclamation-circle';
            }

            // Show toast
            toast.classList.add('show');

            // Hide after 3 seconds
            setTimeout(() => {
                hideToast();
            }, 3000);
        }

        function hideToast() {
            const toast = document.getElementById('toast');
            toast.classList.remove('show');
        }
    </script>
</body>
</html>