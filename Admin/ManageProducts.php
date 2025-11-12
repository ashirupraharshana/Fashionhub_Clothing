<?php
include '../db_connect.php';
session_start();

if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $product_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT p.*, c.category_name, s.subcategory_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN subcategories s ON p.subcategory_id = s.id
              WHERE p.id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Get photos with size information AND photo ID
        $photosStmt = $conn->prepare("SELECT id, photo, size_id FROM photos WHERE product_id = ?");
        $photosStmt->bind_param("i", $product_id);
        $photosStmt->execute();
        $photosResult = $photosStmt->get_result();
        $photos = [];
        while ($photo = $photosResult->fetch_assoc()) {
            $photos[] = $photo;
        }
        $product['photos'] = $photos;
        $photosStmt->close();
        
        // Get sizes with their photos
        $sizesStmt = $conn->prepare("SELECT id, size, quantity, price, discount FROM product_sizes WHERE product_id = ?");
        $sizesStmt->bind_param("i", $product_id);
        $sizesStmt->execute();
        $sizesResult = $sizesStmt->get_result();
        $sizes = [];
        while ($size = $sizesResult->fetch_assoc()) {
            // Get photos for this specific size
            $size['photos'] = array_filter($photos, function($p) use ($size) {
                return $p['size_id'] == $size['id'];
            });
            $size['photos'] = array_values($size['photos']); // Reindex array
            $sizes[] = $size;
        }
        $product['sizes'] = $sizes;
        $sizesStmt->close();
        
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
    $stmt->close();
    exit;
}

// Handle AJAX requests for product details
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $product_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT p.*, c.category_name, s.subcategory_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN subcategories s ON p.subcategory_id = s.id
              WHERE p.id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Get photos with size information
        $photosStmt = $conn->prepare("SELECT photo, size_id FROM photos WHERE product_id = ?");
        $photosStmt->bind_param("i", $product_id);
        $photosStmt->execute();
        $photosResult = $photosStmt->get_result();
        $photos = [];
        while ($photo = $photosResult->fetch_assoc()) {
            $photos[] = $photo;
        }
        $product['photos'] = $photos;
        $photosStmt->close();
        
        // Get sizes
        $sizesStmt = $conn->prepare("SELECT id, size, quantity, price, discount FROM product_sizes WHERE product_id = ?");
        $sizesStmt->bind_param("i", $product_id);
        $sizesStmt->execute();
        $sizesResult = $sizesStmt->get_result();
        $sizes = [];
        while ($size = $sizesResult->fetch_assoc()) {
            $sizes[] = $size;
        }
        $product['sizes'] = $sizes;
        $sizesStmt->close();
        
        echo json_encode($product);
    } else {
        echo json_encode(['error' => 'Product not found']);
    }
    $stmt->close();
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_photo' && isset($_GET['photo_id'])) {
    header('Content-Type: application/json');
    $photo_id = intval($_GET['photo_id']);
    
    $stmt = $conn->prepare("DELETE FROM photos WHERE id = ?");
    $stmt->bind_param("i", $photo_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}
// Enhanced function to compress and resize image
function compressImage($source, $quality = 60, $maxWidth = 600, $maxHeight = 600) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $info = getimagesize($source);
    if (!$info) {
        return false;
    }
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Always resize to ensure smaller file size
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = floor($width * $ratio);
    $newHeight = floor($height * $ratio);
    
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    ob_start();
    imagejpeg($newImage, null, $quality);
    $imageData = ob_get_clean();
    
    imagedestroy($image);
    imagedestroy($newImage);
    
    // If still too large (over 500KB), compress more aggressively
    if (strlen($imageData) > 512000 && $quality > 40) {
        return compressImage($source, $quality - 10, $maxWidth, $maxHeight);
    }
    
    return $imageData;
}

// Handle Add Product
if (isset($_POST['add_product'])) {
    $category_id = intval($_POST['category_id']);
    $subcategory_id = !empty($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : NULL;
    $product_name = trim($_POST['product_name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $gender = isset($_POST['gender']) ? intval($_POST['gender']) : 0;

    $stmt = $conn->prepare("INSERT INTO products (category_id, subcategory_id, product_name, description, price, gender) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissdi", $category_id, $subcategory_id, $product_name, $description, $price, $gender);

    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;

        // Insert product_sizes and handle size-specific photos
        if (!empty($_POST['sizes']) && is_array($_POST['sizes'])) {
            foreach ($_POST['sizes'] as $i => $size) {
                $size = trim($size);
                $quantity = isset($_POST['quantities'][$i]) ? intval($_POST['quantities'][$i]) : 0;
                $size_price = isset($_POST['size_prices'][$i]) ? floatval($_POST['size_prices'][$i]) : 0;
                $discount = isset($_POST['discounts'][$i]) ? floatval($_POST['discounts'][$i]) : 0;

                // Insert size
                $stmt2 = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity, price, discount) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("isidd", $product_id, $size, $quantity, $size_price, $discount);
                $stmt2->execute();
                $size_id = $stmt2->insert_id;
                $stmt2->close();

             // Handle photos for this size (OPTIMIZED VERSION)
if (isset($_FILES['size_photos']['tmp_name'][$i]) && is_array($_FILES['size_photos']['tmp_name'][$i])) {
    foreach ($_FILES['size_photos']['tmp_name'][$i] as $key => $tmp_name) {
        if ($_FILES['size_photos']['error'][$i][$key] === 0) {
            // Check file size - skip if over 10MB
            if ($_FILES['size_photos']['size'][$i][$key] > 10485760) {
                continue;
            }
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp_name);
            finfo_close($finfo);
            
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($mime, $allowed_types)) {
                continue;
            }
            
            // Compress image more aggressively
            if (extension_loaded('gd')) {
                $compressedImage = compressImage($tmp_name, 60, 600, 600);
                if ($compressedImage) {
                    $base64Image = base64_encode($compressedImage);
                    
                    // Check if base64 is too large (over 1MB)
                    if (strlen($base64Image) > 1048576) {
                        // Try even more compression
                        $compressedImage = compressImage($tmp_name, 50, 500, 500);
                        if ($compressedImage) {
                            $base64Image = base64_encode($compressedImage);
                        }
                    }
                } else {
                    $imageData = file_get_contents($tmp_name);
                    $base64Image = base64_encode($imageData);
                }
            } else {
                $imageData = file_get_contents($tmp_name);
                $base64Image = base64_encode($imageData);
            }
            
            // Only insert if the base64 string is reasonable size (under 2MB)
            if (strlen($base64Image) < 2097152) {
                $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, photo) VALUES (?, ?, ?)");
                $stmt3->bind_param("iis", $product_id, $size_id, $base64Image);
                
                try {
                    $stmt3->execute();
                } catch (mysqli_sql_exception $e) {
                    // Log error but continue with other photos
                    error_log("Failed to insert photo: " . $e->getMessage());
                }
                
                $stmt3->close();
            }
        }
    }
}
            }
        }
        $_SESSION['success'] = " Product, sizes, and size-specific photos added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add product: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: ManageProducts.php");
    exit;
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt1 = $conn->prepare("DELETE FROM photos WHERE product_id = ?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    $stmt1->close();
    
    $stmt2 = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->close();
    
    $stmt3 = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt3->bind_param("i", $id);
    if ($stmt3->execute()) {
        $_SESSION['success'] = "Product deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete product.";
    }
    $stmt3->close();
    
    header("Location: ManageProducts.php");
    exit;
}

// Handle Edit Product - FIXED VERSION
// Handle Edit Product - COMPLETELY FIXED VERSION
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['product_id']);
    $category_id = intval($_POST['edit_category_id']);
    $subcategory_id = !empty($_POST['edit_subcategory_id']) ? intval($_POST['edit_subcategory_id']) : NULL;
    $product_name = trim($_POST['edit_product_name']);
    $description = trim($_POST['edit_description']);
    $price = floatval($_POST['edit_price']);
    $gender = intval($_POST['edit_gender']);

    $stmt = $conn->prepare("UPDATE products SET category_id = ?, subcategory_id = ?, product_name = ?, description = ?, price = ?, gender = ? WHERE id = ?");
    $stmt->bind_param("iissdii", $category_id, $subcategory_id, $product_name, $description, $price, $gender, $id);
    
    if ($stmt->execute()) {
        if (!empty($_POST['edit_sizes']) && is_array($_POST['edit_sizes'])) {
            // Step 1: Get ALL existing photos with their size information BEFORE deleting sizes
            $existingPhotosStmt = $conn->prepare("
                SELECT p.id as photo_id, p.photo, p.size_id, ps.size 
                FROM photos p 
                INNER JOIN product_sizes ps ON p.size_id = ps.id 
                WHERE p.product_id = ?
            ");
            $existingPhotosStmt->bind_param("i", $id);
            $existingPhotosStmt->execute();
            $existingPhotosResult = $existingPhotosStmt->get_result();
            
            // Store photos grouped by size name
            $photosBySize = [];
            while ($row = $existingPhotosResult->fetch_assoc()) {
                $sizeName = $row['size'];
                if (!isset($photosBySize[$sizeName])) {
                    $photosBySize[$sizeName] = [];
                }
                $photosBySize[$sizeName][] = [
                    'photo_id' => $row['photo_id'],
                    'photo' => $row['photo']
                ];
            }
            $existingPhotosStmt->close();
            
            // Step 2: Delete all existing sizes
            $deleteStmt = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Step 3: Since sizes are deleted, photos are also deleted due to CASCADE
            // So we need to recreate the sizes AND photos
            
            foreach ($_POST['edit_sizes'] as $i => $size) {
                $size = trim($size);
                $quantity = isset($_POST['edit_quantities'][$i]) ? intval($_POST['edit_quantities'][$i]) : 0;
                $size_price = isset($_POST['edit_size_prices'][$i]) ? floatval($_POST['edit_size_prices'][$i]) : 0;
                $discount = isset($_POST['edit_discounts'][$i]) ? floatval($_POST['edit_discounts'][$i]) : 0;

                // Insert new size
                $stmt2 = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity, price, discount) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("isidd", $id, $size, $quantity, $size_price, $discount);
                $stmt2->execute();
                $new_size_id = $stmt2->insert_id;
                $stmt2->close();

                // Re-insert existing photos for this size (if any existed before)
                if (isset($photosBySize[$size]) && count($photosBySize[$size]) > 0) {
                    foreach ($photosBySize[$size] as $photoData) {
                        $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, photo) VALUES (?, ?, ?)");
                        $stmt3->bind_param("iis", $id, $new_size_id, $photoData['photo']);
                        $stmt3->execute();
                        $stmt3->close();
                    }
                }

                // Handle NEW photo uploads for this size
                if (isset($_FILES['edit_size_photos']['tmp_name'][$i]) && is_array($_FILES['edit_size_photos']['tmp_name'][$i])) {
                    foreach ($_FILES['edit_size_photos']['tmp_name'][$i] as $key => $tmp_name) {
                        if ($_FILES['edit_size_photos']['error'][$i][$key] === 0) {
                            if ($_FILES['edit_size_photos']['size'][$i][$key] > 5242880) {
                                continue;
                            }
                            
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mime = finfo_file($finfo, $tmp_name);
                            finfo_close($finfo);
                            
                            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                            if (!in_array($mime, $allowed_types)) {
                                continue;
                            }
                            
                            if (extension_loaded('gd')) {
                                $compressedImage = compressImage($tmp_name, 75, 800, 800);
                                if ($compressedImage) {
                                    $base64Image = base64_encode($compressedImage);
                                } else {
                                    $imageData = file_get_contents($tmp_name);
                                    $base64Image = base64_encode($imageData);
                                }
                            } else {
                                $imageData = file_get_contents($tmp_name);
                                $base64Image = base64_encode($imageData);
                            }

                            $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, photo) VALUES (?, ?, ?)");
                            $stmt3->bind_param("iis", $id, $new_size_id, $base64Image);
                            $stmt3->execute();
                            $stmt3->close();
                        }
                    }
                }
            }
        }

        $_SESSION['success'] = "Product updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update product: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: ManageProducts.php");
    exit;
}

// Handle Search
$search = "";
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
    $searchParam = "%$search%";
    $stmt = $conn->prepare("SELECT p.*, c.category_name, s.subcategory_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN subcategories s ON p.subcategory_id = s.id
              WHERE p.product_name LIKE ? OR p.description LIKE ? OR c.category_name LIKE ?
              ORDER BY p.id DESC");
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT p.*, c.category_name, s.subcategory_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN subcategories s ON p.subcategory_id = s.id
              ORDER BY p.id DESC");
}

$categories = $conn->query("SELECT id, category_name FROM categories");
$subcategories = $conn->query("SELECT id, subcategory_name FROM subcategories");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | FashionHub</title>
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-close {
            margin-left: auto;
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
            opacity: 0.5;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideDown {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
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
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box button:hover {
            background: #5568d3;
        }

        .add-product-btn {
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
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .add-product-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .product-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            width: 100%;
            height: 250px;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image i {
            color: white;
            font-size: 48px;
        }

        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #667eea;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .product-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 15px;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .product-gender {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            background: #f0f0f0;
            color: #666;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .view-btn {
            color: #28a745;
        }

        .view-btn:hover {
            background: rgba(40, 167, 69, 0.1);
        }

        .edit-btn {
            color: #667eea;
        }

        .edit-btn:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .delete-btn {
            color: #ff4757;
        }

        .delete-btn:hover {
            background: rgba(255, 71, 87, 0.1);
        }

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
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 850px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-header h3 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .sizes-section {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafbfc;
        }

        .sizes-section h4 {
            color: #333;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sizes-container-wrapper {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 5px;
            margin-bottom: 15px;
        }

        .sizes-container-wrapper::-webkit-scrollbar {
            width: 8px;
        }

        .sizes-container-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .sizes-container-wrapper::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .sizes-container-wrapper::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }

        .size-row {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .size-row:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .size-input-group {
            margin-bottom: 12px;
        }

        .size-input-group label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .size-row input[type="text"],
        .size-row input[type="number"] {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .size-row input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 2px dashed #e9ecef;
            border-radius: 6px;
            font-size: 13px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .size-row input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .remove-size-btn {
            background: #ff4757;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
            width: 100%;
            margin-top: 10px;
        }

        .remove-size-btn:hover {
            background: #ff3838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }

        .add-size-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .add-size-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-primary,
        .btn-secondary {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #ddd;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #666;
        }

        .view-modal .product-gallery-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .view-modal .product-gallery-view img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-modal .product-gallery-view img:hover {
            transform: scale(1.05);
        }

        .view-modal .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .view-modal .info-label {
            font-weight: 600;
            color: #333;
            width: 150px;
        }

        .view-modal .info-value {
            color: #666;
            flex: 1;
        }

        .view-modal .sizes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .view-modal .sizes-table th,
        .view-modal .sizes-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .view-modal .sizes-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .product-gallery {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .add-product-btn {
                width: 100%;
                justify-content: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .alert {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }

            .modal-content {
                padding: 20px;
                max-width: 95%;
            }

            .modal-header h3 {
                font-size: 20px;
            }

            .close-modal {
                top: 15px;
                right: 15px;
            }
        }

        .existing-photos-section {
    margin: 10px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.existing-photos-section h5 {
    font-size: 13px;
    color: #666;
    margin-bottom: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.existing-photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
    gap: 8px;
}

.existing-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 6px;
    overflow: hidden;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    background: white;
}

.existing-photo-item:hover {
    border-color: #667eea;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.existing-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.delete-photo-btn {
    position: absolute;
    top: 3px;
    right: 3px;
    background: rgba(255, 71, 87, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    opacity: 0;
    z-index: 10;
}

.existing-photo-item:hover .delete-photo-btn {
    opacity: 1;
}

.delete-photo-btn:hover {
    background: rgba(255, 71, 87, 1);
    transform: scale(1.2);
}

.delete-photo-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.no-photos-message {
    font-size: 12px;
    color: #999;
    text-align: center;
    padding: 10px;
    font-style: italic;
}
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Products
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="ManageProducts.php">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <button class="add-product-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Product
            </button>
        </div>

        <div class="product-gallery">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $photoStmt = $conn->prepare("SELECT photo FROM photos WHERE product_id = ? LIMIT 1");
                    $photoStmt->bind_param("i", $row['id']);
                    $photoStmt->execute();
                    $photoResult = $photoStmt->get_result();
                    $photo = $photoResult->fetch_assoc();
                    $photoStmt->close();
                    
                    $genderLabels = ['Unisex', 'Male', 'Female'];
                    $genderLabel = $genderLabels[$row['gender']] ?? 'Unisex';
                ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if ($photo && !empty($photo['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $photo['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='block';">
                                <i class="fas fa-box" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                            <span class="product-badge"><?php echo htmlspecialchars($row['category_name']); ?></span>
                        </div>
                        <div class="product-info">
                            <div class="product-category">
                                <?php echo htmlspecialchars($row['subcategory_name'] ?: 'General'); ?>
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            <div class="product-description">
                                <?php echo htmlspecialchars($row['description'] ?: 'No description available'); ?>
                            </div>
                            <div class="product-price">$<?php echo number_format($row['price'], 2); ?></div>
                            <div class="product-meta">
                                <div class="product-gender"><?php echo $genderLabel; ?></div>
                                <div class="product-actions">
                                    <button class="action-btn view-btn" onclick='viewProduct(<?php echo $row['id']; ?>)' title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteProduct(<?php echo $row['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Found</h3>
                    <p>Start by adding your first product</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Add New Product</h3>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subcategory</label>
                        <select name="subcategory_id">
                            <option value="">-- Select Subcategory --</option>
                            <?php 
                            $subcategories->data_seek(0);
                            while ($sub = $subcategories->fetch_assoc()): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subcategory_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter product description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Base Price *</label>
                        <input type="number" step="0.01" name="price" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="0">Unisex</option>
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                        </select>
                    </div>
                </div>

                <div class="sizes-section">
                    <h4><i class="fas fa-ruler"></i> Product Sizes</h4>
                    <div class="sizes-container-wrapper">
                        <div id="sizes-container">
                            <div class="size-row">
                                <div class="size-input-group">
                                    <label>Size *</label>
                                    <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
                                </div>
                                <div class="size-input-group">
                                    <label>Quantity *</label>
                                    <input type="number" name="quantities[]" placeholder="0" min="0" required>
                                </div>
                                <div class="size-input-group">
                                    <label>Price ($) *</label>
                                    <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
                                </div>
                                <div class="size-input-group">
                                    <label>Discount (%)</label>
                                    <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
                                </div>
                                <div class="size-input-group">
                                    <label>Photos for this size</label>
                                    <input type="file" name="size_photos[0][]" multiple accept="image/*">
                                </div>
                                <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                                    <i class="fas fa-times"></i> Remove Size
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-size-btn" onclick="addSize()">
                        <i class="fas fa-plus"></i> Add Another Size
                    </button>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="add_product" class="btn-primary">
                        <i class="fas fa-check"></i> Add Product
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Edit Product</h3>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="edit_category_id" id="edit_category_id" required>
                            <option value="">-- Select Category --</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subcategory</label>
                        <select name="edit_subcategory_id" id="edit_subcategory_id">
                            <option value="">-- Select Subcategory --</option>
                            <?php 
                            $subcategories->data_seek(0);
                            while ($sub = $subcategories->fetch_assoc()): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['subcategory_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="edit_product_name" id="edit_product_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Base Price *</label>
                        <input type="number" step="0.01" name="edit_price" id="edit_price" required>
                    </div>

                    <div class="form-group">
                        <label>Gender</label>
                        <select name="edit_gender" id="edit_gender">
                            <option value="0">Unisex</option>
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                        </select>
                    </div>
                </div>

                <div class="sizes-section">
                    <h4><i class="fas fa-ruler"></i> Update Product Sizes</h4>
                    <div class="sizes-container-wrapper">
                        <div id="edit-sizes-container"></div>
                    </div>
                    <button type="button" class="add-size-btn" onclick="addEditSize()">
                        <i class="fas fa-plus"></i> Add Another Size
                    </button>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="edit_product" class="btn-primary">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Product Modal -->
    <div class="modal view-modal" id="viewModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeViewModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3 id="view_product_name">Product Details</h3>
            </div>
            <div id="view_content"></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeViewModal()" style="width: 100%">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
let sizeIndex = 1;
let editSizeIndex = 1;

function addSize() {
    const container = document.getElementById('sizes-container');
    const div = document.createElement('div');
    div.classList.add('size-row');
    div.innerHTML = `
        <div class="size-input-group">
            <label>Size *</label>
            <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
        </div>
        <div class="size-input-group">
            <label>Quantity *</label>
            <input type="number" name="quantities[]" placeholder="0" min="0" required>
        </div>
        <div class="size-input-group">
            <label>Price ($) *</label>
            <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
        </div>
        <div class="size-input-group">
            <label>Discount (%)</label>
            <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
        </div>
        <div class="size-input-group">
            <label>Photos for this size</label>
            <input type="file" name="size_photos[${sizeIndex}][]" multiple accept="image/*">
        </div>
        <button type="button" class="remove-size-btn" onclick="removeSize(this)">
            <i class="fas fa-times"></i> Remove Size
        </button>
    `;
    container.appendChild(div);
    sizeIndex++;
    
    setTimeout(() => {
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function addEditSize() {
    const container = document.getElementById('edit-sizes-container');
    const div = document.createElement('div');
    div.classList.add('size-row');
    div.innerHTML = `
        <div class="size-input-group">
            <label>Size *</label>
            <input type="text" name="edit_sizes[]" placeholder="e.g., S, M, L, XL" required>
        </div>
        <div class="size-input-group">
            <label>Quantity *</label>
            <input type="number" name="edit_quantities[]" placeholder="0" min="0" required>
        </div>
        <div class="size-input-group">
            <label>Price ($) *</label>
            <input type="number" step="0.01" name="edit_size_prices[]" placeholder="0.00" min="0" required>
        </div>
        <div class="size-input-group">
            <label>Discount (%)</label>
            <input type="number" step="0.01" name="edit_discounts[]" placeholder="0" min="0" max="100" value="0">
        </div>
        <div class="existing-photos-section">
            <p class="no-photos-message">No existing photos (new size)</p>
        </div>
        <div class="size-input-group">
            <label><i class="fas fa-plus-circle"></i> Add Photos for this size</label>
            <input type="file" name="edit_size_photos[${editSizeIndex}][]" multiple accept="image/*">
        </div>
        <button type="button" class="remove-size-btn" onclick="removeSize(this)">
            <i class="fas fa-times"></i> Remove Size
        </button>
    `;
    container.appendChild(div);
    editSizeIndex++;
    
    setTimeout(() => {
        div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function removeSize(button) {
    const row = button.closest('.size-row');
    const container = row.parentElement;
    
    if (container.children.length <= 1) {
        alert('You must have at least one size entry.');
        return;
    }
    
    row.style.transition = 'all 0.3s ease';
    row.style.opacity = '0';
    row.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        row.remove();
    }, 300);
}

function openAddModal() {
    document.getElementById('addModal').classList.add('active');
    sizeIndex = 1;
}

function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
    document.querySelector('#addModal form').reset();
    const container = document.getElementById('sizes-container');
    container.innerHTML = `
        <div class="size-row">
            <div class="size-input-group">
                <label>Size *</label>
                <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
            </div>
            <div class="size-input-group">
                <label>Quantity *</label>
                <input type="number" name="quantities[]" placeholder="0" min="0" required>
            </div>
            <div class="size-input-group">
                <label>Price ($) *</label>
                <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
            </div>
            <div class="size-input-group">
                <label>Discount (%)</label>
                <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
            </div>
            <div class="size-input-group">
                <label>Photos for this size</label>
                <input type="file" name="size_photos[0][]" multiple accept="image/*">
            </div>
            <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                <i class="fas fa-times"></i> Remove Size
            </button>
        </div>
    `;
    sizeIndex = 1;
}

function openEditModal(data) {
    document.getElementById('editModal').classList.add('active');
    document.getElementById('edit_product_id').value = data.id;
    document.getElementById('edit_category_id').value = data.category_id;
    document.getElementById('edit_subcategory_id').value = data.subcategory_id || '';
    document.getElementById('edit_product_name').value = data.product_name;
    document.getElementById('edit_description').value = data.description || '';
    document.getElementById('edit_price').value = data.price;
    document.getElementById('edit_gender').value = data.gender;
    editSizeIndex = 0;

    fetch(`ManageProducts.php?ajax=get_details&id=${data.id}`)
        .then(response => response.json())
        .then(productData => {
            const container = document.getElementById('edit-sizes-container');
            container.innerHTML = '';
            
            if (productData.sizes && productData.sizes.length > 0) {
                productData.sizes.forEach((size, index) => {
                    const div = document.createElement('div');
                    div.classList.add('size-row');
                    
                    let existingPhotosHtml = '';
                    if (size.photos && size.photos.length > 0) {
                        existingPhotosHtml = `
                            <div class="existing-photos-section">
                                <h5><i class="fas fa-images"></i> Existing Photos (${size.photos.length})</h5>
                                <div class="existing-photos-grid">
                                    ${size.photos.map(photo => `
                                        <div class="existing-photo-item" data-photo-id="${photo.id}">
                                            <img src="data:image/jpeg;base64,${photo.photo}" alt="Photo">
                                            <button type="button" class="delete-photo-btn" 
                                                    onclick="deletePhoto(${photo.id}, this)" 
                                                    title="Delete this photo">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    } else {
                        existingPhotosHtml = `
                            <div class="existing-photos-section">
                                <p class="no-photos-message">No existing photos for this size</p>
                            </div>
                        `;
                    }
                    
                    div.innerHTML = `
                        <div class="size-input-group">
                            <label>Size *</label>
                            <input type="text" name="edit_sizes[]" value="${size.size}" required>
                        </div>
                        <div class="size-input-group">
                            <label>Quantity *</label>
                            <input type="number" name="edit_quantities[]" value="${size.quantity}" min="0" required>
                        </div>
                        <div class="size-input-group">
                            <label>Price ($) *</label>
                            <input type="number" step="0.01" name="edit_size_prices[]" value="${size.price}" min="0" required>
                        </div>
                        <div class="size-input-group">
                            <label>Discount (%)</label>
                            <input type="number" step="0.01" name="edit_discounts[]" value="${size.discount}" min="0" max="100">
                        </div>
                        ${existingPhotosHtml}
                        <div class="size-input-group">
                            <label><i class="fas fa-plus-circle"></i> Add New Photos for this size</label>
                            <input type="file" name="edit_size_photos[${index}][]" multiple accept="image/*">
                        </div>
                        <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                            <i class="fas fa-times"></i> Remove Size
                        </button>
                    `;
                    container.appendChild(div);
                    editSizeIndex = index + 1;
                });
            } else {
                addEditSize();
            }
        })
        .catch(error => {
            console.error('Error loading product details:', error);
            alert('Error loading product details. Please try again.');
            addEditSize();
        });
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.querySelector('#editModal form').reset();
    document.getElementById('edit-sizes-container').innerHTML = '';
    editSizeIndex = 1;
}

function viewProduct(id) {
    document.getElementById('viewModal').classList.add('active');
    
    fetch(`ManageProducts.php?ajax=get_details&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error loading product details');
                closeViewModal();
                return;
            }
            
            document.getElementById('view_product_name').textContent = data.product_name;
            
            const genderLabels = ['Unisex', 'Male', 'Female'];
            const genderLabel = genderLabels[data.gender] || 'Unisex';
            
            let photosHtml = '';
            if (data.photos && data.photos.length > 0) {
                photosHtml = `
                    <div class="product-gallery-view">
                        ${data.photos.map(photo => `
                            <img src="data:image/jpeg;base64,${photo.photo}" alt="Product photo">
                        `).join('')}
                    </div>
                `;
            }
            
            let sizesHtml = '';
            if (data.sizes && data.sizes.length > 0) {
                sizesHtml = `
                    <table class="sizes-table">
                        <thead>
                            <tr>
                                <th>Size</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Final Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.sizes.map(size => {
                                const finalPrice = size.price - (size.price * size.discount / 100);
                                return `
                                    <tr>
                                        <td>${size.size}</td>
                                        <td>${size.quantity}</td>
                                        <td>$${parseFloat(size.price).toFixed(2)}</td>
                                        <td>${parseFloat(size.discount).toFixed(0)}%</td>
                                        <td>$${finalPrice.toFixed(2)}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            }
            
            document.getElementById('view_content').innerHTML = `
                ${photosHtml}
                <div class="info-row">
                    <div class="info-label">Category:</div>
                    <div class="info-value">${data.category_name || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Subcategory:</div>
                    <div class="info-value">${data.subcategory_name || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value">${data.description || 'No description available'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Base Price:</div>
                    <div class="info-value">$${parseFloat(data.price).toFixed(2)}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gender:</div>
                    <div class="info-value">${genderLabel}</div>
                </div>
                <div style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px;">Available Sizes:</h4>
                    ${sizesHtml}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading product details');
            closeViewModal();
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.getElementById('view_content').innerHTML = '';
}

function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product? This will also delete all associated photos and sizes.')) {
        window.location.href = 'ManageProducts.php?delete=' + id;
    }
}

function deletePhoto(photoId, buttonElement) {
    if (!confirm('Are you sure you want to delete this photo?')) {
        return;
    }
    
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    buttonElement.disabled = true;
    
    fetch(`ManageProducts.php?ajax=delete_photo&photo_id=${photoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const photoItem = buttonElement.closest('.existing-photo-item');
                photoItem.style.transition = 'all 0.3s ease';
                photoItem.style.opacity = '0';
                photoItem.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    photoItem.remove();
                    
                    const photosSection = buttonElement.closest('.existing-photos-section');
                    const remainingPhotos = photosSection.querySelectorAll('.existing-photo-item');
                    
                    if (remainingPhotos.length === 0) {
                        const photosGrid = photosSection.querySelector('.existing-photos-grid');
                        if (photosGrid) photosGrid.remove();
                        const heading = photosSection.querySelector('h5');
                        if (heading) heading.remove();
                        photosSection.innerHTML = '<p class="no-photos-message">No existing photos for this size</p>';
                    } else {
                        const heading = photosSection.querySelector('h5');
                        if (heading) {
                            heading.innerHTML = `<i class="fas fa-images"></i> Existing Photos (${remainingPhotos.length})`;
                        }
                    }
                }, 300);
                
                showTempMessage('Photo deleted successfully', 'success');
            } else {
                alert('Failed to delete photo: ' + (data.error || 'Unknown error'));
                buttonElement.innerHTML = '<i class="fas fa-times"></i>';
                buttonElement.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error deleting photo:', error);
            alert('Error deleting photo. Please try again.');
            buttonElement.innerHTML = '<i class="fas fa-times"></i>';
            buttonElement.disabled = false;
        });
}

function showTempMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

// Click outside modal to close
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const viewModal = document.getElementById('viewModal');
    
    if (event.target == addModal) {
        closeAddModal();
    }
    if (event.target == editModal) {
        closeEditModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
}

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Alert close button functionality
document.querySelectorAll('.alert-close').forEach(btn => {
    btn.addEventListener('click', function() {
        const alert = this.parentElement;
        alert.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => alert.remove(), 300);
    });
});

        function deletePhoto(photoId, buttonElement) {
    if (!confirm('Are you sure you want to delete this photo?')) {
        return;
    }
    
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    buttonElement.disabled = true;
    
    fetch(`ManageProducts.php?ajax=delete_photo&photo_id=${photoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const photoItem = buttonElement.closest('.existing-photo-item');
                photoItem.style.transition = 'all 0.3s ease';
                photoItem.style.opacity = '0';
                photoItem.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    photoItem.remove();
                    
                    const photosSection = buttonElement.closest('.existing-photos-section');
                    const remainingPhotos = photosSection.querySelectorAll('.existing-photo-item');
                    
                    if (remainingPhotos.length === 0) {
                        const photosGrid = photosSection.querySelector('.existing-photos-grid');
                        if (photosGrid) photosGrid.remove();
                        const heading = photosSection.querySelector('h5');
                        if (heading) heading.remove();
                        photosSection.innerHTML = '<p class="no-photos-message">No existing photos for this size</p>';
                    } else {
                        const heading = photosSection.querySelector('h5');
                        if (heading) {
                            heading.innerHTML = `<i class="fas fa-images"></i> Existing Photos (${remainingPhotos.length})`;
                        }
                    }
                }, 300);
                
                showTempMessage('Photo deleted successfully', 'success');
            } else {
                alert('Failed to delete photo: ' + (data.error || 'Unknown error'));
                buttonElement.innerHTML = '<i class="fas fa-times"></i>';
                buttonElement.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error deleting photo:', error);
            alert('Error deleting photo. Please try again.');
            buttonElement.innerHTML = '<i class="fas fa-times"></i>';
            buttonElement.disabled = false;
        });
}

function showTempMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">×</button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

function viewProduct(id) {
    document.getElementById('viewModal').classList.add('active');
    
    fetch(`ManageProducts.php?ajax=get_details&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error loading product details');
                closeViewModal();
                return;
            }
            
            document.getElementById('view_product_name').textContent = data.product_name;
            
            const genderLabels = ['Unisex', 'Male', 'Female'];
            const genderLabel = genderLabels[data.gender] || 'Unisex';
            
            let photosHtml = '';
            if (data.photos && data.photos.length > 0) {
                photosHtml = `
                    <div class="product-gallery-view">
                        ${data.photos.map(photo => `
                            <img src="data:image/jpeg;base64,${photo.photo}" alt="Product photo">
                        `).join('')}
                    </div>
                `;
            }
            
            let sizesHtml = '';
            if (data.sizes && data.sizes.length > 0) {
                sizesHtml = `
                    <table class="sizes-table">
                        <thead>
                            <tr>
                                <th>Size</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Final Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.sizes.map(size => {
                                const finalPrice = size.price - (size.price * size.discount / 100);
                                return `
                                    <tr>
                                        <td>${size.size}</td>
                                        <td>${size.quantity}</td>
                                        <td>$${parseFloat(size.price).toFixed(2)}</td>
                                        <td>${parseFloat(size.discount).toFixed(0)}%</td>
                                        <td>$${finalPrice.toFixed(2)}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `;
            }
            
            document.getElementById('view_content').innerHTML = `
                ${photosHtml}
                <div class="info-row">
                    <div class="info-label">Category:</div>
                    <div class="info-value">${data.category_name || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Subcategory:</div>
                    <div class="info-value">${data.subcategory_name || 'N/A'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value">${data.description || 'No description available'}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Base Price:</div>
                    <div class="info-value">$${parseFloat(data.price).toFixed(2)}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gender:</div>
                    <div class="info-value">${genderLabel}</div>
                </div>
                <div style="margin-top: 20px;">
                    <h4 style="margin-bottom: 10px;">Available Sizes:</h4>
                    ${sizesHtml}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading product details');
            closeViewModal();
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.getElementById('view_content').innerHTML = '';
}
    </script>
</body>
</html>