<?php
include '../db_connect.php';
session_start();

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Order ID not provided.";
    header("Location: ManageOrders.php");
    exit;
}

$order_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT o.*, 
                        u.fullname as customer_name, 
                        u.email as customer_email, 
                        u.phone as user_phone,
                        p.product_name, 
                        p.description as product_description,
                        ps.size, 
                        ps.price as size_price,
                        ps.discount,
                        ps.id as size_id,
                        pc.color_name,
                        o.color_id,
                        c.category_name,
                        s.subcategory_name
                        FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        LEFT JOIN products p ON o.product_id = p.id
                        LEFT JOIN product_sizes ps ON o.size_id = ps.id
                        LEFT JOIN product_colors pc ON o.color_id = pc.id
                        LEFT JOIN categories c ON p.category_id = c.id
                        LEFT JOIN subcategories s ON p.subcategory_id = s.id
                        WHERE o.id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Order not found.";
    header("Location: ManageOrders.php");
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// Fetch ALL product photos for the specific size AND color ordered
$photoStmt = $conn->prepare("SELECT photo FROM photos 
                            WHERE product_id = ? 
                            AND size_id = ? 
                            AND color_id = ? 
                            ORDER BY id ASC");
$photoStmt->bind_param("iii", $order['product_id'], $order['size_id'], $order['color_id']);
$photoStmt->execute();
$photoResult = $photoStmt->get_result();
$photos = [];
while ($photo = $photoResult->fetch_assoc()) {
    $photos[] = $photo['photo'];
}
$photoStmt->close();
// Status information
$statusLabels = ['Pending', 'Delivered', 'Cancelled'];
$statusClasses = ['status-pending', 'status-delivered', 'status-cancelled'];
$statusIcons = ['fa-clock', 'fa-check-circle', 'fa-times-circle'];
$statusLabel = $statusLabels[$order['status']] ?? 'Unknown';
$statusClass = $statusClasses[$order['status']] ?? 'status-pending';
$statusIcon = $statusIcons[$order['status']] ?? 'fa-clock';


$unitPrice = floatval($order['size_price']); 
$discount = floatval($order['discount']);
$quantity = intval($order['quantity']);
$discountAmount = ($unitPrice * $discount / 100) * $quantity;
$subtotal = $unitPrice * $quantity;
$finalTotal = $subtotal - $discountAmount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?> | FashionHub</title>
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
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .breadcrumb {
            color: #999;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #5568d3;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .order-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-top: 20px;
        }

        .order-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-header i {
            font-size: 24px;
            color: #667eea;
        }

        .card-header h3 {
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }

        .product-showcase {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .product-images {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .main-image {
            width: 200px;
            height: 200px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }

        .thumbnail-images {
            display: flex;
            gap: 8px;
        }

.thumbnail-images {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    max-width: 200px;
}

.thumbnail-images img {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.thumbnail-images img:hover {
    border-color: #667eea;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

        .product-placeholder {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 64px;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .product-category {
            display: inline-block;
            background: #e9ecef;
            color: #666;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .product-description {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .product-meta {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }

        .meta-item i {
            color: #667eea;
        }

        .meta-item strong {
            color: #333;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            width: 160px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label i {
            color: #667eea;
            width: 20px;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .price-breakdown {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            color: #666;
            font-size: 15px;
        }

        .price-row.subtotal {
            border-top: 1px solid #e9ecef;
            margin-top: 10px;
            padding-top: 15px;
        }

        .price-row.total {
            border-top: 2px solid #667eea;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .price-row.total .price-value {
            color: #667eea;
        }

        .discount-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 25px;
        }

        .action-btn {
            padding: 12px 20px;
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

        .btn-print {
            background: #667eea;
            color: white;
        }

        .btn-print:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-edit {
            background: #28a745;
            color: white;
        }

        .btn-edit:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .order-timeline {
            margin-top: 20px;
        }

        .timeline-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            position: relative;
        }

        .timeline-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 17px;
            top: 40px;
            width: 2px;
            height: calc(100% - 20px);
            background: #e9ecef;
        }

        .timeline-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1;
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .timeline-date {
            font-size: 13px;
            color: #999;
        }

        @media print {
            .main-content {
                margin-left: 0;
                margin-top: 0;
            }
            .page-header, .action-buttons, .back-btn {
                display: none;
            }
        }

        @media (max-width: 1024px) {
            .order-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .product-showcase {
                flex-direction: column;
            }

            .product-images {
                align-items: center;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .back-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div class="header-left">
                <h2>Order #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></h2>
                <div class="breadcrumb">
                    <a href="AdminDashboard.php">Dashboard</a> / 
                    <a href="ManageOrders.php">Orders</a> / 
                    Order Details
                </div>
            </div>
            <a href="ManageOrders.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>

        <div class="order-container">
            <!-- Left Column: Main Order Details -->
            <div>
                <!-- Product Information -->
                <div class="order-card">
                    <div class="card-header">
                        <i class="fas fa-box"></i>
                        <h3>Product Information</h3>
                    </div>

                    <div class="product-showcase">
                       <div class="product-images">
    <?php if (!empty($photos)): ?>
        <img src="data:image/jpeg;base64,<?php echo $photos[0]; ?>" 
             alt="Product" 
             class="main-image" 
             id="mainImage">
        <?php if (count($photos) > 1): ?>
            <div class="thumbnail-images">
                <?php foreach ($photos as $index => $photo): ?>
                    <img src="data:image/jpeg;base64,<?php echo $photo; ?>" 
                         alt="Thumbnail <?php echo $index + 1; ?>"
                         onclick="changeMainImage(this)"
                         <?php echo $index === 0 ? 'style="border-color: #667eea;"' : ''; ?>>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div style="margin-top: 10px; text-align: center; font-size: 12px; color: #999;">
    <?php echo count($photos); ?> photo<?php echo count($photos) > 1 ? 's' : ''; ?> for size <?php echo htmlspecialchars($order['size']); ?> - <?php echo htmlspecialchars($order['color_name'] ?? 'Default Color'); ?>
</div>
    <?php else: ?>
        <div class="product-placeholder">
            <i class="fas fa-box"></i>
        </div>
        <div style="margin-top: 10px; text-align: center; font-size: 12px; color: #999;">
            No photos available
        </div>
    <?php endif; ?>
</div>

                        <div class="product-details">
                            <div class="product-name"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            <span class="product-category">
                                <i class="fas fa-tag"></i> 
                                <?php echo htmlspecialchars($order['category_name']); ?>
                                <?php if ($order['subcategory_name']): ?>
                                    / <?php echo htmlspecialchars($order['subcategory_name']); ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($order['product_description']): ?>
                                <div class="product-description">
                                    <?php echo htmlspecialchars($order['product_description']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="product-meta">
                                <div class="meta-item">
                                    <i class="fas fa-ruler"></i>
                                    <span>Size: <strong><?php echo htmlspecialchars($order['size']); ?></strong></span>
                                </div>
                                <div class="meta-item">
        <i class="fas fa-palette"></i>
        <span>Color: <strong><?php echo htmlspecialchars($order['color_name'] ?? 'N/A'); ?></strong></span>
    </div>
                                <div class="meta-item">
                                    <i class="fas fa-boxes"></i>
                                    <span>Quantity: <strong><?php echo $quantity; ?></strong></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price Breakdown -->
                    <div class="price-breakdown">
                        <div class="price-row">
                            <span>Unit Price:</span>
                            <span class="price-value">Rs <?php echo number_format($unitPrice, 2); ?></span>
                        </div>
                        <div class="price-row">
                            <span>Quantity:</span>
                            <span class="price-value">×<?php echo $quantity; ?></span>
                        </div>
                        <div class="price-row subtotal">
                            <span>Subtotal:</span>
                            <span class="price-value">Rs <?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                            <div class="price-row">
                                <span>
                                    Discount 
                                    <span class="discount-badge"><?php echo number_format($discount, 0); ?>% OFF</span>
                                </span>
                                <span class="price-value" style="color: #28a745;">-Rs <?php echo number_format($discountAmount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="price-row total">
                            <span>Total Amount:</span>
                            <span class="price-value">Rs <?php echo number_format($finalTotal, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Customer & Delivery Information -->
                <div class="order-card" style="margin-top: 25px;">
                    <div class="card-header">
                        <i class="fas fa-user"></i>
                        <h3>Customer & Delivery Information</h3>
                    </div>

                    <div class="info-section">
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-user"></i>
                                Customer Name:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($order['customer_name'] ?: 'Guest Customer'); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-envelope"></i>
                                Email:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($order['customer_email'] ?: 'N/A'); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-phone"></i>
                                Phone:
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($order['phone']); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt"></i>
                                Delivery Address:
                            </div>
                            <div class="info-value">
                                <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Order Status & Timeline -->
            <div>
                <!-- Order Status -->
                <div class="order-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Order Status</h3>
                    </div>

                    <div class="info-section">
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-hashtag"></i>
                                Order ID:
                            </div>
                            <div class="info-value">
                                <strong>#<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></strong>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="fas fa-calendar"></i>
                                Order Date:
                            </div>
                            <div class="info-value">
                                <?php echo date('F d, Y - h:i A', strtotime($order['order_date'])); ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">
                                <i class="<?php echo $statusIcon; ?>"></i>
                                Status:
                            </div>
                            <div class="info-value">
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <i class="fas <?php echo $statusIcon; ?>"></i>
                                    <?php echo $statusLabel; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Timeline -->
                    <div class="order-timeline">
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Order Placed</div>
                                <div class="timeline-date">
                                    <?php echo date('F d, Y - h:i A', strtotime($order['order_date'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($order['status'] >= 1): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #28a745;">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Order Delivered</div>
                                    <div class="timeline-date">Completed</div>
                                </div>
                            </div>
                        <?php elseif ($order['status'] == 2): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #dc3545;">
                                    <i class="fas fa-times"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Order Cancelled</div>
                                    <div class="timeline-date">Cancelled</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: #ffc107;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Processing</div>
                                    <div class="timeline-date">Awaiting fulfillment</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="action-btn btn-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Order
                        </button>
                        <button class="action-btn btn-edit" onclick="window.location.href='ManageOrders.php'">
                            <i class="fas fa-edit"></i> Manage Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
function changeMainImage(thumbnail) {
    const mainImage = document.getElementById('mainImage');
    
    // Remove border from all thumbnails
    const thumbnails = document.querySelectorAll('.thumbnail-images img');
    thumbnails.forEach(img => {
        img.style.borderColor = '#e9ecef';
    });
    
    // Add border to clicked thumbnail
    thumbnail.style.borderColor = '#667eea';
    
    // Change main image with animation
    mainImage.style.opacity = '0';
    mainImage.style.transition = 'opacity 0.3s ease';
    
    setTimeout(() => {
        mainImage.src = thumbnail.src;
        mainImage.style.opacity = '1';
    }, 150);
}

        // Auto-adjust sidebar
        const toggleBtn = document.querySelector('.toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                document.getElementById('mainContent').classList.toggle('expanded');
            });
        }
    </script>
</body>
</html>