<?php
// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

include '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Order Deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_order'])) {
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = "Invalid security token. Please try again.";
        $_SESSION['message_type'] = "error";
        header("Location: CustomerOrders.php");
        exit;
    }
    
    $order_id = intval($_POST['order_id']);
    
    // Start transaction with proper isolation level
    $conn->autocommit(FALSE);
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
    
    try {
        // STEP 1: Lock and get order details with all required information
        $order_query = "SELECT o.id, o.user_id, o.product_id, o.size, o.color_id, 
                               o.quantity, o.price, o.total_price, o.status
                        FROM orders o 
                        WHERE o.id = ? AND o.user_id = ? AND o.status = 0
                        FOR UPDATE";
        
        $stmt = $conn->prepare($order_query);
        if (!$stmt) {
            throw new Exception("Failed to prepare order query: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $order_result = $stmt->get_result();
        
        if ($order_result->num_rows === 0) {
            throw new Exception("Order not found, already processed, or you don't have permission to delete it.");
        }
        
        $order = $order_result->fetch_assoc();
        $stmt->close();
        
        // Validate order data
        $quantity = intval($order['quantity']);
        $product_id = intval($order['product_id']);
        $size = trim($order['size']);
        $stored_color_id = isset($order['color_id']) && !empty($order['color_id']) 
            ? intval($order['color_id']) 
            : null;
        
        if (empty($product_id) || empty($size) || $quantity <= 0) {
            throw new Exception("Invalid order data: missing required fields");
        }
        
        error_log("Order Deletion - Order ID: $order_id, Product: $product_id, Size: $size, Quantity: $quantity, Color ID: " . ($stored_color_id ?? 'NULL'));
        
        // STEP 2: Get and lock size record
        $get_size_query = "SELECT id, quantity FROM product_sizes 
                          WHERE product_id = ? AND size = ? 
                          FOR UPDATE";
        
        $stmt_size = $conn->prepare($get_size_query);
        if (!$stmt_size) {
            throw new Exception("Failed to prepare size query: " . $conn->error);
        }
        
        $stmt_size->bind_param("is", $product_id, $size);
        $stmt_size->execute();
        $size_result = $stmt_size->get_result();
        
        if ($size_result->num_rows === 0) {
            throw new Exception("Size '$size' not found for this product");
        }
        
        $size_row = $size_result->fetch_assoc();
        $size_id = intval($size_row['id']);
        $size_qty_before = intval($size_row['quantity']);
        $stmt_size->close();
        
        error_log("Size record found - ID: $size_id, Current quantity: $size_qty_before");
        
        // STEP 3: Update product_sizes stock
        $update_size_query = "UPDATE product_sizes 
                             SET quantity = quantity + ? 
                             WHERE id = ?";
        
        $stmt_update_size = $conn->prepare($update_size_query);
        if (!$stmt_update_size) {
            throw new Exception("Failed to prepare size update: " . $conn->error);
        }
        
        $stmt_update_size->bind_param("ii", $quantity, $size_id);
        
        if (!$stmt_update_size->execute()) {
            throw new Exception("Failed to update product_sizes: " . $stmt_update_size->error);
        }
        
        if ($stmt_update_size->affected_rows === 0) {
            throw new Exception("Size stock update affected 0 rows");
        }
        
        $stmt_update_size->close();
        
        error_log("Size stock updated successfully - Added $quantity units to size_id $size_id");
        
        // STEP 4: Handle color stock restoration
        $color_id_to_update = null;
        $color_name = 'Unknown';
        
        if ($stored_color_id !== null && $stored_color_id > 0) {
            // Verify the stored color_id exists and belongs to this product/size
            $verify_color_query = "SELECT id, color_name, quantity 
                                  FROM product_colors 
                                  WHERE id = ? AND product_id = ? AND size_id = ? 
                                  FOR UPDATE";
            
            $stmt_verify = $conn->prepare($verify_color_query);
            if (!$stmt_verify) {
                throw new Exception("Failed to prepare color verification: " . $conn->error);
            }
            
            $stmt_verify->bind_param("iii", $stored_color_id, $product_id, $size_id);
            $stmt_verify->execute();
            $verify_result = $stmt_verify->get_result();
            
            if ($verify_result->num_rows > 0) {
                $color_row = $verify_result->fetch_assoc();
                $color_id_to_update = intval($color_row['id']);
                $color_name = $color_row['color_name'];
                $color_qty_before = intval($color_row['quantity']);
                
                error_log("Color verified - ID: $color_id_to_update, Name: $color_name, Current quantity: $color_qty_before");
            } else {
                error_log("WARNING: Stored color_id $stored_color_id not found or doesn't match product/size");
            }
            
            $stmt_verify->close();
        }
        
        // If we didn't find a valid color_id, try to find one
        if ($color_id_to_update === null) {
            $find_color_query = "SELECT id, color_name, quantity 
                                FROM product_colors 
                                WHERE product_id = ? AND size_id = ? 
                                ORDER BY id ASC 
                                LIMIT 1 
                                FOR UPDATE";
            
            $stmt_find = $conn->prepare($find_color_query);
            if (!$stmt_find) {
                throw new Exception("Failed to prepare color search: " . $conn->error);
            }
            
            $stmt_find->bind_param("ii", $product_id, $size_id);
            $stmt_find->execute();
            $find_result = $stmt_find->get_result();
            
            if ($find_result->num_rows > 0) {
                $color_row = $find_result->fetch_assoc();
                $color_id_to_update = intval($color_row['id']);
                $color_name = $color_row['color_name'];
                $color_qty_before = intval($color_row['quantity']);
                
                error_log("Color found via search - ID: $color_id_to_update, Name: $color_name, Current quantity: $color_qty_before");
            }
            
            $stmt_find->close();
        }
        
        // Update color stock if we found a valid color_id
        if ($color_id_to_update !== null && $color_id_to_update > 0) {
            $update_color_query = "UPDATE product_colors 
                                  SET quantity = quantity + ? 
                                  WHERE id = ?";
            
            $stmt_update_color = $conn->prepare($update_color_query);
            if (!$stmt_update_color) {
                throw new Exception("Failed to prepare color update: " . $conn->error);
            }
            
            $stmt_update_color->bind_param("ii", $quantity, $color_id_to_update);
            
            if (!$stmt_update_color->execute()) {
                throw new Exception("Failed to update color stock: " . $stmt_update_color->error);
            }
            
            if ($stmt_update_color->affected_rows === 0) {
                throw new Exception("Color stock update affected 0 rows - possible concurrency issue");
            }
            
            $stmt_update_color->close();
            
            // Verify the update was successful
            $verify_update_query = "SELECT quantity FROM product_colors WHERE id = ?";
            $stmt_verify_update = $conn->prepare($verify_update_query);
            $stmt_verify_update->bind_param("i", $color_id_to_update);
            $stmt_verify_update->execute();
            $verify_update_result = $stmt_verify_update->get_result();
            $new_color_data = $verify_update_result->fetch_assoc();
            $color_qty_after = intval($new_color_data['quantity']);
            $stmt_verify_update->close();
            
            $expected_qty = $color_qty_before + $quantity;
            if ($color_qty_after !== $expected_qty) {
                throw new Exception("Color stock verification failed. Expected: $expected_qty, Got: $color_qty_after");
            }
            
            error_log("Color stock verified - Before: $color_qty_before, After: $color_qty_after, Expected: $expected_qty");
        } else {
            error_log("WARNING: No color record found to update - stock restoration skipped for colors");
        }
        
        // STEP 5: Delete the order
        $delete_order_query = "DELETE FROM orders WHERE id = ? AND user_id = ?";
        $stmt_delete = $conn->prepare($delete_order_query);
        
        if (!$stmt_delete) {
            throw new Exception("Failed to prepare order deletion: " . $conn->error);
        }
        
        $stmt_delete->bind_param("ii", $order_id, $user_id);
        
        if (!$stmt_delete->execute()) {
            throw new Exception("Failed to delete order: " . $stmt_delete->error);
        }
        
        if ($stmt_delete->affected_rows === 0) {
            throw new Exception("Order deletion affected 0 rows");
        }
        
        $stmt_delete->close();
        
        // Commit transaction - all updates succeeded
        $conn->commit();
        $conn->autocommit(TRUE);
        
        error_log("Transaction committed successfully - Order $order_id deleted");
        
        // Success message
        $restore_msg = "Order #$order_id deleted successfully! Stock restored: $quantity unit(s) to size '$size'";
        if ($color_id_to_update !== null) {
            $restore_msg .= " and '$color_name' color inventory";
        }
        
        $_SESSION['message'] = $restore_msg;
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        $conn->autocommit(TRUE);
        
        error_log("Transaction rolled back - Error: " . $e->getMessage());
        
        $_SESSION['message'] = "Error deleting order: " . htmlspecialchars($e->getMessage());
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: CustomerOrders.php");
    exit;
}

include 'Components/CustomerNavBar.php';

// Handle Search and Filter with prepared statements
$search = "";
$status_filter = null;
$params = [$user_id];
$types = "i";
$where_clauses = ["o.user_id = ?"];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $search_param = "%{$search}%";
    $where_clauses[] = "(p.product_name LIKE ? OR o.id LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $status_filter = intval($_GET['status']);
    $where_clauses[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= "i";
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Optimized query - get all data in one go to avoid N+1 problem
$query = "SELECT o.id, o.user_id, o.product_id, o.size, o.color_id, 
                 o.quantity, o.price, o.total_price, o.status, o.order_date,
                 o.phone, o.delivery_address,
                 p.product_name,
                 ps.id as size_id, ps.price as size_price, ps.discount as size_discount,
                 pc.id as actual_color_id, pc.color_name, pc.hex_code
          FROM orders o
          LEFT JOIN products p ON o.product_id = p.id
          LEFT JOIN product_sizes ps ON o.product_id = ps.product_id AND o.size = ps.size
          LEFT JOIN product_colors pc ON o.color_id = pc.id
          $where_sql
          ORDER BY o.order_date DESC
          LIMIT 100";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query preparation failed: " . htmlspecialchars($conn->error));
}

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as cancelled,
    SUM(total_price) as total_spent
    FROM orders
    WHERE user_id = ?";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();

// Collect all orders for photo fetching
$orders = [];
$order_ids = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $order_ids[] = intval($row['id']);
}
$stmt->close();

// Fetch all photos for all orders in one query (optimization)
$photos_by_order = [];
if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    
    // Fetch photos matching product_id, size_id, and color_id from orders
    $photo_query = "SELECT o.id as order_id, ph.photo, ph.color_id as photo_color_id
                    FROM orders o
                    INNER JOIN product_sizes ps ON o.product_id = ps.product_id AND o.size = ps.size
                    INNER JOIN photos ph ON o.product_id = ph.product_id 
                        AND ph.size_id = ps.id
                        AND (
                            (o.color_id IS NOT NULL AND ph.color_id = o.color_id) 
                            OR 
                            (o.color_id IS NULL AND ph.color_id = (
                                SELECT pc.id FROM product_colors pc 
                                WHERE pc.product_id = o.product_id AND pc.size_id = ps.id 
                                LIMIT 1
                            ))
                        )
                    WHERE o.id IN ($placeholders)
                    ORDER BY ph.id ASC";
    
    $photo_stmt = $conn->prepare($photo_query);
    if ($photo_stmt) {
        $types_photo = str_repeat('i', count($order_ids));
        $photo_stmt->bind_param($types_photo, ...$order_ids);
        $photo_stmt->execute();
        $photo_result = $photo_stmt->get_result();
        
        while ($photo_row = $photo_result->fetch_assoc()) {
            $oid = $photo_row['order_id'];
            if (!isset($photos_by_order[$oid])) {
                $photos_by_order[$oid] = [];
            }
            $photos_by_order[$oid][] = $photo_row['photo'];
        }
        $photo_stmt->close();
    }
    
    // If no photos found, try fallback: get first available photos for product/size
    foreach ($order_ids as $oid) {
        if (empty($photos_by_order[$oid])) {
            $fallback_query = "SELECT ph.photo
                              FROM orders o
                              INNER JOIN product_sizes ps ON o.product_id = ps.product_id AND o.size = ps.size
                              INNER JOIN photos ph ON o.product_id = ph.product_id AND ph.size_id = ps.id
                              WHERE o.id = ?
                              LIMIT 3";
            
            $fallback_stmt = $conn->prepare($fallback_query);
            if ($fallback_stmt) {
                $fallback_stmt->bind_param("i", $oid);
                $fallback_stmt->execute();
                $fallback_result = $fallback_stmt->get_result();
                
                $photos_by_order[$oid] = [];
                while ($fallback_row = $fallback_result->fetch_assoc()) {
                    $photos_by_order[$oid][] = $fallback_row['photo'];
                }
                $fallback_stmt->close();
            }
        }
    }
}

// Status labels and colors
$status_labels = [
    0 => ['label' => 'Pending', 'color' => '#f39c12', 'icon' => 'fa-clock'],
    1 => ['label' => 'Delivered', 'color' => '#27ae60', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Cancelled', 'color' => '#e74c3c', 'icon' => 'fa-times-circle']
];

// XSS Prevention helper function
function esc($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>My Orders | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
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
            font-size: 42px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 16px;
            color: #7f8c8d;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 14px;
            color: #7f8c8d;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
        }

        .search-filter {
            display: flex;
            gap: 15px;
            flex: 1;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 280px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 14px 55px 14px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .search-box input:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: #e74c3c;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-box button:hover {
            background: #c0392b;
        }

        .filter-select {
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            background: white;
            transition: all 0.3s ease;
            min-width: 160px;
            font-weight: 500;
            color: #2c3e50;
        }

        .filter-select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        /* Orders List */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e8e8e8;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .order-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-id {
            font-size: 18px;
            font-weight: 700;
        }

        .order-date {
            font-size: 14px;
            opacity: 0.9;
        }

        .order-body {
            padding: 25px;
        }

        .order-product {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .product-image-container {
            width: 120px;
            flex-shrink: 0;
        }

        .product-image-slider {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .product-image-slider img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .product-image-slider img.active {
            display: block;
        }

        .product-image-slider .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .slider-btn:hover {
            background: rgba(0, 0, 0, 0.8);
            transform: translateY(-50%) scale(1.1);
        }

        .slider-btn.prev {
            left: 5px;
        }

        .slider-btn.next {
            right: 5px;
        }

        .slider-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .image-counter {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .product-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            font-size: 14px;
            color: #7f8c8d;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: #e74c3c;
        }

        .size-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .order-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .total-price {
            font-size: 24px;
            font-weight: 700;
            color: #27ae60;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .view-details-btn, .delete-order-btn {
            padding: 12px 28px;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-details-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        .delete-order-btn {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .delete-order-btn:hover {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 15px;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h3 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 700;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f0f0f0;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .close-modal:hover {
            background: #e74c3c;
            color: white;
            transform: rotate(90deg) scale(1.1);
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }

        .address-detail {
            grid-column: 1 / -1;
        }

        .address-detail .detail-value {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #e74c3c;
        }

        .modal-image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        .modal-image-gallery img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e8e8e8;
        }

        .modal-image-gallery img:hover {
            transform: scale(1.05);
            border-color: #e74c3c;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .empty-state i {
            font-size: 80px;
            color: #e8e8e8;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 25px;
        }

        .shop-now-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .shop-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.3);
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 100px;
            right: -400px;
            background: #ffffff;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 350px;
            max-width: 500px;
            transition: right 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .toast.show {
            right: 20px;
        }

        .toast.success {
            border-left: 5px solid #27ae60;
        }

        .toast.error {
            border-left: 5px solid #e74c3c;
        }

        .toast-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .toast.success .toast-icon {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .toast.error .toast-icon {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
            color: #2c3e50;
        }

        .toast-message {
            font-size: 14px;
            color: #7f8c8d;
        }

        .toast-close {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #7f8c8d;
            transition: all 0.3s;
            padding: 5px;
        }

        .toast-close:hover {
            color: #2c3e50;
            transform: rotate(90deg);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 32px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-product {
                flex-direction: column;
                text-align: center;
            }

            .product-image-container {
                margin: 0 auto;
            }

            .order-info {
                grid-template-columns: 1fr;
            }

            .order-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .order-actions {
                flex-direction: column;
            }

            .view-details-btn, .delete-order-btn {
                justify-content: center;
            }

            .order-details-grid {
                grid-template-columns: 1fr;
            }

            .toast {
                right: -100%;
                left: 10px;
                min-width: auto;
                max-width: calc(100% - 20px);
            }

            .toast.show {
                right: auto;
                left: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <?php if (isset($_SESSION['message']) && isset($_SESSION['message_type'])): ?>
            <?php 
                $toast_message = $_SESSION['message'];
                $toast_type = $_SESSION['message_type'];
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
            <div class="toast <?php echo esc($toast_type); ?>">
                <div class="toast-icon">
                    <i class="fas fa-<?php echo $toast_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title"><?php echo $toast_type === 'success' ? 'Success!' : 'Error!'; ?></div>
                    <div class="toast-message"><?php echo esc($toast_message); ?></div>
                </div>
                <button class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo esc($stats['total_orders']); ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #f39c12;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo esc($stats['pending']); ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #27ae60;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo esc($stats['delivered']); ?></h3>
                    <p>Delivered</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #27ae60;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Rs. <?php echo number_format($stats['total_spent'], 2); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <form method="GET" action="CustomerOrders.php" class="search-filter">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by order ID or product name..." value="<?php echo esc($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Pending</option>
                    <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Delivered</option>
                    <option value="2" <?php echo $status_filter === 2 ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </form>
        </div>

        <!-- Orders List -->
        <div class="orders-list">
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $row): ?>
                    <?php 
                    $statusInfo = $status_labels[$row['status']];
                    
                    // Get size and color information with fallback logic
                    $display_size = esc($row['size']) ?: 'Standard';
                    $display_color = 'Default';
                    $color_hex = '#cccccc';
                    
                    // If we have color information from the JOIN
                    if (!empty($row['color_name'])) {
                        $display_color = esc($row['color_name']);
                        $color_hex = esc($row['hex_code']) ?: '#cccccc';
                    } 
                    // If order has color_id but JOIN failed, fetch it separately
                    else if (!empty($row['color_id'])) {
                        $color_fetch = $conn->prepare("SELECT color_name, hex_code FROM product_colors WHERE id = ?");
                        $color_fetch->bind_param("i", $row['color_id']);
                        $color_fetch->execute();
                        $color_fetch_result = $color_fetch->get_result();
                        if ($color_fetch_row = $color_fetch_result->fetch_assoc()) {
                            $display_color = esc($color_fetch_row['color_name']);
                            $color_hex = esc($color_fetch_row['hex_code']) ?: '#cccccc';
                        }
                        $color_fetch->close();
                    }
                    
                    // Get pricing information
                    $original_price = floatval($row['size_price'] ?: $row['price']);
                    $discount = floatval($row['size_discount'] ?: 0);
                    $discounted_price = $original_price - ($original_price * $discount / 100);
                    
                    // Get photos for this order
                    $photos = $photos_by_order[$row['id']] ?? [];
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-id">Order #<?php echo esc($row['id']); ?></div>
                                <div class="order-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('F d, Y • h:i A', strtotime($row['order_date'])); ?>
                                </div>
                            </div>
                            <span class="status-badge" style="background: <?php echo $statusInfo['color']; ?>20; color: <?php echo $statusInfo['color']; ?>;">
                                <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </div>

                        <div class="order-body">
                            <div class="order-product">
                                <div class="product-image-container">
                                    <div class="product-image-slider" id="slider-<?php echo $row['id']; ?>">
                                        <?php if (!empty($photos)): ?>
                                            <?php foreach ($photos as $index => $photo): ?>
                                                <img src="data:image/jpeg;base64,<?php echo $photo; ?>" 
                                                     alt="<?php echo esc($row['product_name']); ?>"
                                                     class="<?php echo $index === 0 ? 'active' : ''; ?>"
                                                     data-index="<?php echo $index; ?>">
                                            <?php endforeach; ?>
                                            <?php if (count($photos) > 1): ?>
                                                <button class="slider-btn prev" onclick="changeSlide(<?php echo $row['id']; ?>, -1)">
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <button class="slider-btn next" onclick="changeSlide(<?php echo $row['id']; ?>, 1)">
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                                <div class="image-counter">
                                                    <span id="counter-<?php echo $row['id']; ?>">1</span> / <?php echo count($photos); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-box"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="product-details">
                                    <div class="product-name"><?php echo esc($row['product_name']); ?></div>
                                    <div class="product-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-tag"></i>
                                            <span>Size: <span class="size-badge"><?php echo $display_size; ?></span></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-palette"></i>
                                            <span>Color: 
                                                <span style="display: inline-flex; align-items: center; gap: 6px;">
                                                    <span style="display: inline-block; width: 18px; height: 18px; border-radius: 50%; background-color: <?php echo $color_hex; ?>; border: 2px solid #ddd; vertical-align: middle;"></span>
                                                    <strong><?php echo $display_color; ?></strong>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-cube"></i>
                                            <span>Qty: <?php echo esc($row['quantity']); ?></span>
                                        </div>
                                        <?php if ($discount > 0): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-percent"></i>
                                                <span style="color: #e74c3c; font-weight: 600;"><?php echo number_format($discount, 0); ?>% OFF</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="order-info">
                                <div class="info-item">
                                    <span class="info-label">Unit Price</span>
                                    <span class="info-value">
                                        <?php if ($discount > 0): ?>
                                            <span style="text-decoration: line-through; color: #999; font-size: 14px;">Rs. <?php echo number_format($original_price, 2); ?></span>
                                            <span style="color: #e74c3c;">Rs. <?php echo number_format($discounted_price, 2); ?></span>
                                        <?php else: ?>
                                            Rs. <?php echo number_format($original_price, 2); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value"><?php echo esc($row['phone']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Delivery Address</span>
                                    <span class="info-value"><?php echo esc(substr($row['delivery_address'], 0, 50)) . (strlen($row['delivery_address']) > 50 ? '...' : ''); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div>
                                <div class="info-label">Total Amount</div>
                                <div class="total-price">Rs. <?php echo number_format($row['total_price'], 2); ?></div>
                            </div>
                            <div class="order-actions">
                                <button class="view-details-btn" onclick='openModal(<?php echo json_encode(array_merge($row, ["photos" => $photos, "discounted_price" => $discounted_price, "discount" => $discount, "display_size" => $display_size, "display_color" => $display_color, "color_hex" => $color_hex, "original_price" => $original_price]), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>)'>
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>
                                <?php if ($row['status'] == 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo esc($row['product_name']); ?>', <?php echo intval($row['quantity']); ?>, '<?php echo esc($display_size); ?>')">
                                        <input type="hidden" name="delete_order" value="1">
                                        <input type="hidden" name="order_id" value="<?php echo intval($row['id']); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo esc($_SESSION['csrf_token']); ?>">
                                        <button type="submit" class="delete-order-btn">
                                            <i class="fas fa-trash-alt"></i>
                                            Delete Order
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No Orders Found</h3>
                    <p>You haven't placed any orders matching your search criteria.</p>
                    <a href="Products.php" class="shop-now-btn">
                        <i class="fas fa-shopping-cart"></i>
                        Shop Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Order Details</h3>
            </div>
            <div class="order-details-grid" id="orderDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

    <script>
        const statusLabels = <?php echo json_encode($status_labels); ?>;

        // Image slider functionality
        const sliderStates = {};

        function changeSlide(orderId, direction) {
            const slider = document.getElementById(`slider-${orderId}`);
            const images = slider.querySelectorAll('img');
            const counter = document.getElementById(`counter-${orderId}`);
            
            if (!sliderStates[orderId]) {
                sliderStates[orderId] = 0;
            }
            
            images[sliderStates[orderId]].classList.remove('active');
            
            sliderStates[orderId] += direction;
            
            if (sliderStates[orderId] < 0) {
                sliderStates[orderId] = images.length - 1;
            } else if (sliderStates[orderId] >= images.length) {
                sliderStates[orderId] = 0;
            }
            
            images[sliderStates[orderId]].classList.add('active');
            
            if (counter) {
                counter.textContent = sliderStates[orderId] + 1;
            }
        }

        function confirmDelete(productName, quantity, size) {
            return confirm(`Are you sure you want to delete this order?\n\nProduct: ${productName}\nSize: ${size}\nQuantity: ${quantity}\n\nThe stock quantity will be restored.`);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function openModal(data) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderDetailsContent');
            
            const statusInfo = statusLabels[data.status];
            
            let photosHtml = '';
            if (data.photos && data.photos.length > 0) {
                photosHtml = `
                    <div class="modal-image-gallery">
                        ${data.photos.map(photo => `
                            <img src="data:image/jpeg;base64,${photo}" alt="Product photo" onclick="viewFullImage(this.src)">
                        `).join('')}
                    </div>
                `;
            }

            const discount = parseFloat(data.discount || 0);
            const originalPrice = parseFloat(data.original_price || data.price);
            const discountedPrice = parseFloat(data.discounted_price || data.price);
            
            let priceHtml = '';
            if (discount > 0) {
                priceHtml = `
                    <span style="text-decoration: line-through; color: #999; font-size: 14px; display: block;">Rs. ${originalPrice.toFixed(2)}</span>
                    <span style="color: #e74c3c; font-size: 18px; font-weight: 700;">Rs. ${discountedPrice.toFixed(2)}</span>
                    <span style="color: #27ae60; font-size: 13px; display: block;">(${discount.toFixed(0)}% discount applied)</span>
                `;
            } else {
                priceHtml = `Rs. ${originalPrice.toFixed(2)}`;
            }
            
            content.innerHTML = `
                ${photosHtml}
                <div class="detail-group">
                    <span class="detail-label">Order ID</span>
                    <span class="detail-value">#${escapeHtml(String(data.id))}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Order Date</span>
                    <span class="detail-value">${new Date(data.order_date).toLocaleString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric', 
                        hour: '2-digit', 
                        minute: '2-digit' 
                    })}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Product</span>
                    <span class="detail-value">${escapeHtml(data.product_name)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Size</span>
                    <span class="detail-value">
                        <span class="size-badge">${escapeHtml(data.display_size || 'Standard')}</span>
                    </span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Color</span>
                    <span class="detail-value" style="display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-block; width: 30px; height: 30px; border-radius: 50%; background-color: ${escapeHtml(data.color_hex || '#cccccc')}; border: 3px solid #ddd;"></span>
                        <strong>${escapeHtml(data.display_color || 'Default')}</strong>
                    </span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Quantity</span>
                    <span class="detail-value">${escapeHtml(String(data.quantity))} units</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Unit Price</span>
                    <span class="detail-value">${priceHtml}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Phone Number</span>
                    <span class="detail-value">${escapeHtml(data.phone)}</span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="status-badge" style="background: ${statusInfo.color}20; color: ${statusInfo.color};">
                            <i class="fas ${statusInfo.icon}"></i>
                            ${statusInfo.label}
                        </span>
                    </span>
                </div>
                <div class="detail-group address-detail">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value">${escapeHtml(data.delivery_address)}</span>
                </div>
                <div class="detail-group" style="grid-column: 1 / -1; margin-top: 10px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <span class="detail-label">Total Amount Paid</span>
                    <span class="detail-value" style="color: #27ae60; font-size: 24px;">Rs. ${parseFloat(data.total_price).toFixed(2)}</span>
                </div>
            `;
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        function viewFullImage(src) {
            const imgWindow = window.open('', '_blank');
            imgWindow.document.write(`
                <html>
                <head>
                    <title>Product Image</title>
                    <style>
                        body { margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #000; }
                        img { max-width: 100%; max-height: 100vh; object-fit: contain; }
                    </style>
                </head>
                <body>
                    <img src="${src}" alt="Product Image">
                </body>
                </html>
            `);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Toast Notification
        const toast = document.querySelector('.toast');
        if (toast) {
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                });
            }

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentElement) toast.remove();
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>