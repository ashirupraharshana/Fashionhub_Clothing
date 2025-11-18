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
        
        // Get sizes with their colors
        $sizesStmt = $conn->prepare("SELECT id, size, quantity, price, discount FROM product_sizes WHERE product_id = ?");
        $sizesStmt->bind_param("i", $product_id);
        $sizesStmt->execute();
        $sizesResult = $sizesStmt->get_result();
        $sizes = [];
        while ($size = $sizesResult->fetch_assoc()) {
            // Get colors for this size with their photos
            $colorsStmt = $conn->prepare("SELECT id, color_name, hex_code, quantity FROM product_colors WHERE product_id = ? AND size_id = ?");
            $colorsStmt->bind_param("ii", $product_id, $size['id']);
            $colorsStmt->execute();
            $colorsResult = $colorsStmt->get_result();
            $colors = [];
            while ($color = $colorsResult->fetch_assoc()) {
                // Get photos for this specific color
                $photosStmt = $conn->prepare("SELECT id, photo FROM photos WHERE product_id = ? AND color_id = ?");
                $photosStmt->bind_param("ii", $product_id, $color['id']);
                $photosStmt->execute();
                $photosResult = $photosStmt->get_result();
                $photos = [];
                while ($photo = $photosResult->fetch_assoc()) {
                    $photos[] = $photo;
                }
                $color['photos'] = $photos;
                $photosStmt->close();
                
                $colors[] = $color;
            }
            $size['colors'] = $colors;
            $colorsStmt->close();
            
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


// Handle Delete Color
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_color' && isset($_GET['color_id'])) {
    header('Content-Type: application/json');
    $color_id = intval($_GET['color_id']);
    
    // First, delete all photos associated with this color
    $stmt1 = $conn->prepare("DELETE FROM photos WHERE color_id = ?");
    $stmt1->bind_param("i", $color_id);
    $stmt1->execute();
    $stmt1->close();
    
    // Then delete the color itself
    $stmt2 = $conn->prepare("DELETE FROM product_colors WHERE id = ?");
    $stmt2->bind_param("i", $color_id);
    
    if ($stmt2->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt2->close();
    exit;
}

// Handle Delete Photo
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
// Handle Get Color Photos
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_color_photos' && isset($_GET['color_id'])) {
    header('Content-Type: application/json');
    $color_id = intval($_GET['color_id']);
    
    $stmt = $conn->prepare("SELECT id, photo FROM photos WHERE color_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $color_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $photos = [];
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }
    
    echo json_encode(['success' => true, 'photos' => $photos]);
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

    // Insert product_sizes and handle color-specific photos
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

            // Handle colors for this size
            if (isset($_POST['color_names'][$i]) && is_array($_POST['color_names'][$i])) {
                foreach ($_POST['color_names'][$i] as $colorIndex => $color_name) {
                    $color_name = trim($color_name);
                    if (empty($color_name)) continue;
                    
                    $hex_code = isset($_POST['hex_codes'][$i][$colorIndex]) ? trim($_POST['hex_codes'][$i][$colorIndex]) : NULL;
                    $color_quantity = isset($_POST['color_quantities'][$i][$colorIndex]) ? intval($_POST['color_quantities'][$i][$colorIndex]) : 0;
                    
                    $colorStmt = $conn->prepare("INSERT INTO product_colors (product_id, size_id, color_name, hex_code, quantity) VALUES (?, ?, ?, ?, ?)");
                    $colorStmt->bind_param("iissi", $product_id, $size_id, $color_name, $hex_code, $color_quantity);
                    $colorStmt->execute();
                    $color_id = $colorStmt->insert_id;
                    $colorStmt->close();
                    
                    // Handle photos for this COLOR (not size)
                    if (isset($_FILES['color_photos']['tmp_name'][$i][$colorIndex]) && is_array($_FILES['color_photos']['tmp_name'][$i][$colorIndex])) {
                        foreach ($_FILES['color_photos']['tmp_name'][$i][$colorIndex] as $key => $tmp_name) {
                            if ($_FILES['color_photos']['error'][$i][$colorIndex][$key] === 0) {
                                // Check file size - skip if over 10MB
                                if ($_FILES['color_photos']['size'][$i][$colorIndex][$key] > 10485760) {
                                    continue;
                                }
                                
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $mime = finfo_file($finfo, $tmp_name);
                                finfo_close($finfo);
                                
                                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                                if (!in_array($mime, $allowed_types)) {
                                    continue;
                                }
                                
                                // Compress image
                                if (extension_loaded('gd')) {
                                    $compressedImage = compressImage($tmp_name, 60, 600, 600);
                                    if ($compressedImage) {
                                        $base64Image = base64_encode($compressedImage);
                                        
                                        if (strlen($base64Image) > 1048576) {
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
                                
                                if (strlen($base64Image) < 2097152) {
                                    $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, color_id, photo) VALUES (?, ?, ?, ?)");
                                    $stmt3->bind_param("iiis", $product_id, $size_id, $color_id, $base64Image);
                                    
                                    try {
                                        $stmt3->execute();
                                    } catch (mysqli_sql_exception $e) {
                                        error_log("Failed to insert photo: " . $e->getMessage());
                                    }
                                    
                                    $stmt3->close();
                                }
                            }
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

// Handle Edit Product - FIXED VERSION (Preserves photos when size names change)
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
            // Step 1: Get ALL existing sizes in ORDER
            $existingSizesStmt = $conn->prepare("
                SELECT id, size, quantity, price, discount 
                FROM product_sizes 
                WHERE product_id = ? 
                ORDER BY id ASC
            ");
            $existingSizesStmt->bind_param("i", $id);
            $existingSizesStmt->execute();
            $existingSizesResult = $existingSizesStmt->get_result();
            
            $existingSizes = [];
            while ($row = $existingSizesResult->fetch_assoc()) {
                $existingSizes[] = $row;
            }
            $existingSizesStmt->close();
            
            // Step 2: Get ALL colors BY SIZE ID (not size name)
            $existingColorsStmt = $conn->prepare("
                SELECT c.id as color_id, c.color_name, c.hex_code, c.quantity, c.size_id
                FROM product_colors c 
                WHERE c.product_id = ?
            ");
            $existingColorsStmt->bind_param("i", $id);
            $existingColorsStmt->execute();
            $existingColorsResult = $existingColorsStmt->get_result();

            $colorsBySizeId = [];
            while ($row = $existingColorsResult->fetch_assoc()) {
                $sizeId = $row['size_id'];
                if (!isset($colorsBySizeId[$sizeId])) {
                    $colorsBySizeId[$sizeId] = [];
                }
                $colorKey = strtolower(trim($row['color_name'])) . '_' . strtolower(trim($row['hex_code'] ?? ''));
                $colorsBySizeId[$sizeId][$colorKey] = [
                    'color_id' => $row['color_id'],
                    'color_name' => $row['color_name'],
                    'hex_code' => $row['hex_code'],
                    'quantity' => $row['quantity']
                ];
            }
            $existingColorsStmt->close();

            // Step 3: Get ALL photos BY SIZE ID
            $existingPhotosStmt = $conn->prepare("
                SELECT p.photo, p.size_id, pc.color_name, pc.hex_code
                FROM photos p 
                LEFT JOIN product_colors pc ON p.color_id = pc.id
                WHERE p.product_id = ?
            ");
            $existingPhotosStmt->bind_param("i", $id);
            $existingPhotosStmt->execute();
            $existingPhotosResult = $existingPhotosStmt->get_result();

            $photosBySizeId = [];
            while ($row = $existingPhotosResult->fetch_assoc()) {
                $sizeId = $row['size_id'];
                $colorName = strtolower(trim($row['color_name'] ?? 'default'));
                $hexCode = strtolower(trim($row['hex_code'] ?? ''));
                $colorKey = $colorName . '_' . $hexCode;
                
                if (!isset($photosBySizeId[$sizeId])) {
                    $photosBySizeId[$sizeId] = [];
                }
                if (!isset($photosBySizeId[$sizeId][$colorKey])) {
                    $photosBySizeId[$sizeId][$colorKey] = [];
                }
                
                $photosBySizeId[$sizeId][$colorKey][] = $row['photo'];
            }
            $existingPhotosStmt->close();
            
            // Step 4: Delete all existing sizes (cascades to colors and photos)
            $deleteStmt = $conn->prepare("DELETE FROM product_sizes WHERE product_id = ?");
            $deleteStmt->bind_param("i", $id);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Step 5: Recreate sizes BY POSITION INDEX
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

                // Get OLD size ID by position
                $old_size_id = isset($existingSizes[$i]) ? $existingSizes[$i]['id'] : null;
                
                $insertedColors = [];
                
                // Check if form has colors for this size
                $formHasColors = isset($_POST['edit_color_names'][$i]) && 
                                is_array($_POST['edit_color_names'][$i]) && 
                                count(array_filter($_POST['edit_color_names'][$i], function($name) {
                                    return !empty(trim($name));
                                })) > 0;
                
                // Re-insert existing colors with photos (using OLD size ID)
                if ($old_size_id && isset($colorsBySizeId[$old_size_id]) && count($colorsBySizeId[$old_size_id]) > 0) {
                    foreach ($colorsBySizeId[$old_size_id] as $colorKey => $colorData) {
                        $insertedColors[$colorKey] = true;
                        
                        // Re-insert the color
                        $colorStmt = $conn->prepare("INSERT INTO product_colors (product_id, size_id, color_name, hex_code, quantity) VALUES (?, ?, ?, ?, ?)");
                        $colorStmt->bind_param("iissi", $id, $new_size_id, $colorData['color_name'], $colorData['hex_code'], $colorData['quantity']);
                        $colorStmt->execute();
                        $new_color_id = $colorStmt->insert_id;
                        $colorStmt->close();
                        
                        // Re-insert ALL existing photos for this color
                        if (isset($photosBySizeId[$old_size_id][$colorKey]) && count($photosBySizeId[$old_size_id][$colorKey]) > 0) {
                            foreach ($photosBySizeId[$old_size_id][$colorKey] as $photoData) {
                                $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, color_id, photo) VALUES (?, ?, ?, ?)");
                                $stmt3->bind_param("iiis", $id, $new_size_id, $new_color_id, $photoData);
                                try {
                                    $stmt3->execute();
                                } catch (mysqli_sql_exception $e) {
                                    error_log("Failed to re-insert photo: " . $e->getMessage());
                                }
                                $stmt3->close();
                            }
                        }
                    }
                }
                
                // Process NEW/UPDATED colors from form
                if ($formHasColors) {
                    foreach ($_POST['edit_color_names'][$i] as $colorIndex => $color_name) {
                        $color_name = trim($color_name);
                        if (empty($color_name)) continue;
                        
                        $hex_code = isset($_POST['edit_hex_codes'][$i][$colorIndex]) ? trim($_POST['edit_hex_codes'][$i][$colorIndex]) : NULL;
                        $color_quantity = isset($_POST['edit_color_quantities'][$i][$colorIndex]) ? intval($_POST['edit_color_quantities'][$i][$colorIndex]) : 0;
                        
                        $newColorKey = strtolower(trim($color_name)) . '_' . strtolower(trim($hex_code ?? ''));
                        
                        // If color already exists, just update quantity and add new photos
                        if (isset($insertedColors[$newColorKey])) {
                            // Get color ID
                            $getColorIdStmt = $conn->prepare("SELECT id FROM product_colors WHERE product_id = ? AND size_id = ? AND color_name = ? AND (hex_code = ? OR (hex_code IS NULL AND ? IS NULL)) LIMIT 1");
                            $getColorIdStmt->bind_param("iisss", $id, $new_size_id, $color_name, $hex_code, $hex_code);
                            $getColorIdStmt->execute();
                            $colorIdResult = $getColorIdStmt->get_result();
                            if ($colorIdRow = $colorIdResult->fetch_assoc()) {
                                $new_color_id = $colorIdRow['id'];
                            }
                            $getColorIdStmt->close();
                            
                            // Update quantity if different
                            $updateColorStmt = $conn->prepare("UPDATE product_colors SET quantity = ? WHERE id = ?");
                            $updateColorStmt->bind_param("ii", $color_quantity, $new_color_id);
                            $updateColorStmt->execute();
                            $updateColorStmt->close();
                            
                            // Add NEW photos
                            if (isset($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex]) && is_array($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex])) {
                                foreach ($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex] as $key => $tmp_name) {
                                    if ($_FILES['edit_color_photos']['error'][$i][$colorIndex][$key] === 0) {
                                        if ($_FILES['edit_color_photos']['size'][$i][$colorIndex][$key] > 10485760) continue;
                                        
                                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                        $mime = finfo_file($finfo, $tmp_name);
                                        finfo_close($finfo);
                                        
                                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                                        if (!in_array($mime, $allowed_types)) continue;
                                        
                                        if (extension_loaded('gd')) {
                                            $compressedImage = compressImage($tmp_name, 60, 600, 600);
                                            if ($compressedImage) {
                                                $base64Image = base64_encode($compressedImage);
                                                if (strlen($base64Image) > 1048576) {
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

                                        if (strlen($base64Image) < 2097152) {
                                            $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, color_id, photo) VALUES (?, ?, ?, ?)");
                                            $stmt3->bind_param("iiis", $id, $new_size_id, $new_color_id, $base64Image);
                                            try {
                                                $stmt3->execute();
                                            } catch (mysqli_sql_exception $e) {
                                                error_log("Failed to insert new photo: " . $e->getMessage());
                                            }
                                            $stmt3->close();
                                        }
                                    }
                                }
                            }
                            continue;
                        }
                        
                        // This is a completely NEW color
                        $colorStmt = $conn->prepare("INSERT INTO product_colors (product_id, size_id, color_name, hex_code, quantity) VALUES (?, ?, ?, ?, ?)");
                        $colorStmt->bind_param("iissi", $id, $new_size_id, $color_name, $hex_code, $color_quantity);
                        $colorStmt->execute();
                        $new_color_id = $colorStmt->insert_id;
                        $colorStmt->close();
                        
                        $insertedColors[$newColorKey] = true;
                        
                        // Add photos for new color
                        if (isset($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex]) && is_array($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex])) {
                            foreach ($_FILES['edit_color_photos']['tmp_name'][$i][$colorIndex] as $key => $tmp_name) {
                                if ($_FILES['edit_color_photos']['error'][$i][$colorIndex][$key] === 0) {
                                    if ($_FILES['edit_color_photos']['size'][$i][$colorIndex][$key] > 10485760) continue;
                                    
                                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                    $mime = finfo_file($finfo, $tmp_name);
                                    finfo_close($finfo);
                                    
                                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                                    if (!in_array($mime, $allowed_types)) continue;
                                    
                                    if (extension_loaded('gd')) {
                                        $compressedImage = compressImage($tmp_name, 60, 600, 600);
                                        if ($compressedImage) {
                                            $base64Image = base64_encode($compressedImage);
                                            if (strlen($base64Image) > 1048576) {
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

                                    if (strlen($base64Image) < 2097152) {
                                        $stmt3 = $conn->prepare("INSERT INTO photos (product_id, size_id, color_id, photo) VALUES (?, ?, ?, ?)");
                                        $stmt3->bind_param("iiis", $id, $new_size_id, $new_color_id, $base64Image);
                                        try {
                                            $stmt3->execute();
                                        } catch (mysqli_sql_exception $e) {
                                            error_log("Failed to insert photo: " . $e->getMessage());
                                        }
                                        $stmt3->close();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $_SESSION['success'] = "Product updated successfully with all photos preserved!";
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



/* Enhanced Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    padding: 20px;
    overflow-y: auto;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal.active {
    display: flex;
}

.modal-content {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    padding: 0;
    border-radius: 20px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    overflow: hidden;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px 40px;
    margin: 0;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.modal-header h3 {
    color: white;
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 12px;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.modal-header h3 i {
    font-size: 32px;
}

.modal-body {
    padding: 40px;
    max-height: calc(90vh - 200px);
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: #667eea #f0f0f0;
}

.modal-body::-webkit-scrollbar {
    width: 10px;
}

.modal-body::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 10px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
}

.modal-body::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
}

.close-modal {
    position: absolute;
    top: 25px;
    right: 30px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
    width: 45px;
    height: 45px;
    border-radius: 50%;
    font-size: 22px;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg) scale(1.1);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

/* Enhanced Form Styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 25px;
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group label i {
    color: #667eea;
    font-size: 16px;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 15px;
    font-family: inherit;
    transition: all 0.3s ease;
    background: white;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

/* Enhanced Sizes Section */
.sizes-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: none;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.sizes-section h4 {
    color: #333;
    font-size: 22px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 700;
    padding-bottom: 15px;
    border-bottom: 3px solid #667eea;
}

.sizes-section h4 i {
    color: #667eea;
    font-size: 24px;
}

.sizes-container-wrapper {
    max-height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 10px;
    margin-bottom: 20px;
}

.size-row {
    padding: 25px;
    background: white;
    border-radius: 15px;
    border: 2px solid transparent;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
}

.size-row::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.size-row:hover {
    border-color: #667eea;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
    transform: translateX(5px);
}

.size-row:hover::before {
    transform: scaleY(1);
}

.size-input-group {
    margin-bottom: 15px;
}

.size-input-group label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.size-input-group label i {
    color: #667eea;
}

/* Enhanced Colors Subsection */
.colors-subsection {
    margin: 20px 0;
    padding: 20px;
    background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
    border-radius: 12px;
    border: 2px solid #d1ddf7;
    box-shadow: inset 0 2px 10px rgba(102, 126, 234, 0.05);
}

.colors-subsection h5 {
    font-size: 16px;
    color: #333;
    margin-bottom: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid rgba(102, 126, 234, 0.2);
}

.colors-subsection h5 i {
    color: #667eea;
    font-size: 18px;
}

.color-row {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 15px;
    background: white;
    border-radius: 10px;
    margin-bottom: 12px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.color-row:hover {
    border-color: #667eea;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.color-row input {
    padding: 10px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.color-row input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.remove-color-btn {
    background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
    color: white;
    border: none;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.remove-color-btn:hover {
    background: linear-gradient(135deg, #ff3838 0%, #ff1f1f 100%);
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);
}

.add-color-btn {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    width: 100%;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
}

.add-color-btn:hover {
    background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

.remove-size-btn {
    background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s ease;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    width: 100%;
    margin-top: 15px;
    box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
}

.remove-size-btn:hover {
    background: linear-gradient(135deg, #ff3838 0%, #ff1f1f 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
}

.add-size-btn {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border: none;
    padding: 16px 28px;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s ease;
    width: 100%;
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
}

.add-size-btn:hover {
    background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
}

/* Enhanced Modal Actions */
.modal-actions {
    display: flex;
    gap: 15px;
    padding: 25px 40px;
    background: #f8f9fa;
    margin: 0;
    border-radius: 0 0 20px 20px;
}

.btn-primary,
.btn-secondary {
    flex: 1;
    padding: 16px 24px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: white;
    color: #666;
    border: 2px solid #e9ecef;
}

.btn-secondary:hover {
    background: #f8f9fa;
    border-color: #667eea;
    color: #667eea;
    transform: translateY(-2px);
}

/* Enhanced Photo Management */
.existing-photos-section {
    margin: 15px 0;
    padding: 15px;
    background: white;
    border-radius: 10px;
    border: 2px dashed #d1ddf7;
}

.existing-photos-section h5, 
.existing-photos-section h6 {
    font-size: 13px;
    color: #667eea;
    margin-bottom: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.existing-photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 12px;
}

.existing-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 10px;
    overflow: hidden;
    border: 3px solid #e9ecef;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    cursor: pointer;
}

.existing-photo-item:hover {
    border-color: #667eea;
    transform: scale(1.08) rotate(2deg);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    z-index: 10;
}

.existing-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.existing-photo-item:hover img {
    transform: scale(1.1);
}

.delete-photo-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(255, 71, 87, 0.95);
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    opacity: 0;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.existing-photo-item:hover .delete-photo-btn {
    opacity: 1;
    transform: scale(1.1);
}

.delete-photo-btn:hover {
    background: #ff1f1f;
    transform: scale(1.25) rotate(90deg);
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-content {
        max-width: 95%;
        border-radius: 15px;
    }

    .modal-header {
        padding: 25px 30px;
    }

    .modal-header h3 {
        font-size: 22px;
    }

    .modal-body {
        padding: 25px;
    }

    .form-row {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .sizes-section {
        padding: 20px;
    }

    .size-row {
        padding: 20px;
    }

    .modal-actions {
        padding: 20px;
        flex-direction: column;
    }

    .btn-primary,
    .btn-secondary {
        width: 100%;
    }

    .close-modal {
        width: 40px;
        height: 40px;
        top: 20px;
        right: 20px;
    }

    .existing-photos-grid {
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        gap: 8px;
    }
}

@media (max-width: 480px) {
    .modal-header h3 {
        font-size: 18px;
    }

    .modal-body {
        padding: 20px;
    }

    .color-row {
        flex-direction: column;
    }

    .color-row input {
        width: 100% !important;
    }
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

        .view-modal .color-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    border: 1px solid rgba(0,0,0,0.1);
}

.view-modal .size-card {
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    background: white;
    transition: all 0.3s ease;
}

.view-modal .size-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.view-modal .colors-section {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.view-modal .color-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    border: 1px solid rgba(0,0,0,0.1);
    margin: 4px;
}

.view-modal .stat-label {
    font-size: 12px;
    color: #999;
    display: block;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.view-modal .stat-value {
    font-size: 16px;
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

       

            .alert {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }

        
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

.view-modal .modal-content {
    max-width: 1200px;
}

.view-modal .product-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px 15px 0 0;
    margin: -30px -30px 30px -30px;
}

.view-modal .product-header h3 {
    color: white;
    font-size: 28px;
    margin-bottom: 10px;
}

.view-modal .product-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.view-modal .stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.view-modal .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.view-modal .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
}

.view-modal .stat-card.category .stat-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.view-modal .stat-card.price .stat-icon {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.view-modal .stat-card.gender .stat-icon {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.view-modal .stat-label {
    font-size: 12px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.view-modal .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #333;
}

.view-modal .description-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.view-modal .description-section h4 {
    color: #333;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-modal .description-text {
    color: #666;
    line-height: 1.8;
}

.view-modal .sizes-variants-section {
    margin-top: 30px;
}

.view-modal .section-title {
    font-size: 22px;
    font-weight: 700;
    color: #333;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.view-modal .section-title i {
    color: #667eea;
}

.view-modal .size-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.view-modal .size-card:hover {
    border-color: #667eea;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
    transform: translateY(-3px);
}

.view-modal .size-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.view-modal .size-name {
    font-size: 24px;
    font-weight: 700;
    color: #667eea;
}

.view-modal .size-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.view-modal .size-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.view-modal .detail-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.view-modal .detail-label {
    font-size: 11px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.view-modal .detail-value {
    font-size: 18px;
    font-weight: 700;
    color: #333;
}

.view-modal .detail-value.price {
    color: #667eea;
}

.view-modal .detail-value.discount {
    color: #ff4757;
}

.view-modal .detail-value.final-price {
    color: #28a745;
    font-size: 22px;
}

.view-modal .colors-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 12px;
}

.view-modal .colors-title {
    font-size: 16px;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-modal .color-card {
    background: white;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.view-modal .color-card:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    transform: translateX(5px);
}

.view-modal .color-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.view-modal .color-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    border: 2px solid rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 8px;
}

.view-modal .color-swatch {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
}

.view-modal .quantity-badge {
    background: #667eea;
    color: white;
    padding: 6px 14px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 600;
}

.view-modal .photos-badge {
    background: #28a745;
    color: white;
    padding: 6px 14px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.view-modal .color-photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 12px;
    margin-top: 12px;
}

.view-modal .color-photo-item {
    position: relative;
    aspect-ratio: 1;
    border-radius: 10px;
    overflow: hidden;
    border: 3px solid #e9ecef;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-modal .color-photo-item:hover {
    border-color: #667eea;
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.view-modal .color-photo-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.view-modal .load-more-photos-btn {
    margin-top: 12px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.view-modal .load-more-photos-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.view-modal .no-colors-message {
    text-align: center;
    padding: 30px;
    color: #999;
    font-style: italic;
}

.view-modal .empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
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
            <h2>Manage Products</h2>
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
                            <div class="product-price">Rs <?php echo number_format($row['price'], 2); ?></div>
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
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Product</h3>
            <button class="close-modal" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category *</label>
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
                        <label><i class="fas fa-folder-open"></i> Subcategory</label>
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
                    <label><i class="fas fa-tag"></i> Product Name *</label>
                    <input type="text" name="product_name" required placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" placeholder="Enter product description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Base Price (Rs) *</label>
                        <input type="number" step="0.01" name="price" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender">
                            <option value="0">Unisex</option>
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                        </select>
                    </div>
                </div>

                <div class="sizes-section">
                    <h4><i class="fas fa-ruler-combined"></i> Product Sizes</h4>
                    <div class="sizes-container-wrapper">
                        <div id="sizes-container">
                            <div class="size-row">
                                <div class="size-input-group">
                                    <label><i class="fas fa-text-width"></i> Size *</label>
                                    <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
                                </div>
                                <div class="size-input-group">
                                    <label><i class="fas fa-boxes"></i> Quantity (Auto-calculated) *</label>
                                    <input type="number" name="quantities[]" placeholder="0" min="0" required readonly style="background: #f8f9fa; cursor: not-allowed;" title="Auto-calculated from color quantities">
                                </div>
                                <div class="size-input-group">
                                    <label><i class="fas fa-tag"></i> Price (Rs) *</label>
                                    <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
                                </div>
                                <div class="size-input-group">
                                    <label><i class="fas fa-percentage"></i> Discount (%)</label>
                                    <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
                                </div>
                                <div class="colors-subsection">
                                    <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
                                    <div class="colors-container" data-size-index="0">
                                        <div class="color-row">
                                            <input type="text" name="color_names[0][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
                                            <input type="text" name="hex_codes[0][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
                                            <input type="number" name="color_quantities[0][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
                                            <input type="file" name="color_photos[0][0][]" multiple accept="image/*" style="flex: 2; font-size: 12px;">
                                            <button type="button" class="remove-color-btn" onclick="removeColor(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <button type="button" class="add-color-btn" onclick="addColor(0)">
                                        <i class="fas fa-plus"></i> Add Color/Design
                                    </button>
                                </div>
                                <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                                    <i class="fas fa-trash-alt"></i> Remove Size
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-size-btn" onclick="addSize()">
                        <i class="fas fa-plus-circle"></i> Add Another Size
                    </button>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" name="add_product" class="btn-primary">
                    <i class="fas fa-save"></i> Add Product
                </button>
                <button type="button" class="btn-secondary" onclick="closeAddModal()">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- Edit Product Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Product</h3>
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-folder"></i> Category *</label>
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
                        <label><i class="fas fa-folder-open"></i> Subcategory</label>
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
                    <label><i class="fas fa-tag"></i> Product Name *</label>
                    <input type="text" name="edit_product_name" id="edit_product_name" required placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="edit_description" id="edit_description" placeholder="Enter product description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign"></i> Base Price (Rs) *</label>
                        <input type="number" step="0.01" name="edit_price" id="edit_price" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="edit_gender" id="edit_gender">
                            <option value="0">Unisex</option>
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                        </select>
                    </div>
                </div>

                <div class="sizes-section">
                    <h4><i class="fas fa-ruler-combined"></i> Update Product Sizes</h4>
                    <div class="sizes-container-wrapper">
                        <div id="edit-sizes-container"></div>
                    </div>
                    <button type="button" class="add-size-btn" onclick="addEditSize()">
                        <i class="fas fa-plus-circle"></i> Add Another Size
                    </button>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" name="edit_product" class="btn-primary">
                    <i class="fas fa-save"></i> Update Product
                </button>
                <button type="button" class="btn-secondary" onclick="closeEditModal()">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

    <!-- View Product Modal -->
<div class="modal view-modal" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="view_product_name"><i class="fas fa-eye"></i> Product Details</h3>
            <button class="close-modal" onclick="closeViewModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <div id="view_content"></div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeViewModal()" style="width: 100%">
                <i class="fas fa-times-circle"></i> Close
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
            <label><i class="fas fa-text-width"></i> Size *</label>
            <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
        </div>
        <div class="size-input-group">
            <label><i class="fas fa-boxes"></i> Quantity (Auto-calculated) *</label>
            <input type="number" name="quantities[]" placeholder="0" min="0" required readonly style="background: #f8f9fa; cursor: not-allowed;" title="Auto-calculated from color quantities">
        </div>
        <div class="size-input-group">
            <label><i class="fas fa-tag"></i> Price (Rs) *</label>
            <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
        </div>
        <div class="size-input-group">
            <label><i class="fas fa-percentage"></i> Discount (%)</label>
            <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
        </div>
        <div class="colors-subsection">
            <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
            <div class="colors-container" data-size-index="${sizeIndex}">
                <div class="color-row">
                    <input type="text" name="color_names[${sizeIndex}][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
                    <input type="text" name="hex_codes[${sizeIndex}][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
                    <input type="number" name="color_quantities[${sizeIndex}][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
                    <input type="file" name="color_photos[${sizeIndex}][0][]" multiple accept="image/*" style="flex: 2; font-size: 12px;">
                    <button type="button" class="remove-color-btn" onclick="removeColor(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <button type="button" class="add-color-btn" onclick="addColor(${sizeIndex})">
                <i class="fas fa-plus"></i> Add Color/Design
            </button>
        </div>
        <button type="button" class="remove-size-btn" onclick="removeSize(this)">
            <i class="fas fa-trash-alt"></i> Remove Size
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
            <label>Price (Rs) *</label>
            <input type="number" step="0.01" name="edit_size_prices[]" placeholder="0.00" min="0" required>
        </div>
        <div class="size-input-group">
            <label>Discount (%)</label>
            <input type="number" step="0.01" name="edit_discounts[]" placeholder="0" min="0" max="100" value="0">
        </div>
        <div class="colors-subsection">
            <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
            <div class="colors-container" data-edit-size-index="${editSizeIndex}">
                <div class="color-row" style="flex-direction: column; gap: 8px; padding: 10px; background: white; border-radius: 6px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="text" name="edit_color_names[${editSizeIndex}][]" placeholder="Color or Design" style="flex: 2;">
                        <input type="text" name="edit_hex_codes[${editSizeIndex}][]" placeholder="#FF0000 or Code" style="flex: 1;">
                        <input type="number" name="edit_color_quantities[${editSizeIndex}][]" placeholder="Qty" min="0" value="0" style="flex: 1;">
                        <button type="button" class="remove-color-btn" onclick="removeEditColor(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div style="margin-top: 4px;">
                        <label style="font-size: 11px; color: #666; display: block; margin-bottom: 4px;">
                            <i class="fas fa-plus-circle"></i> Add photos for this color/design
                        </label>
                        <input type="file" name="edit_color_photos[${editSizeIndex}][0][]" multiple accept="image/*" style="width: 100%; font-size: 11px;">
                    </div>
                </div>
            </div>
            <button type="button" class="add-color-btn" onclick="addEditColor(${editSizeIndex})">
                <i class="fas fa-plus"></i> Add Color/Design
            </button>
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
    
    // Reset the form
    document.querySelector('#addModal form').reset();
    
    // Reset the sizes container with the initial size row including colors
    const container = document.getElementById('sizes-container');
    container.innerHTML = `
        <div class="size-row">
            <div class="size-input-group">
                <label><i class="fas fa-text-width"></i> Size *</label>
                <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-boxes"></i> Quantity (Auto-calculated) *</label>
                <input type="number" name="quantities[]" placeholder="0" min="0" required readonly style="background: #f8f9fa; cursor: not-allowed;" title="Auto-calculated from color quantities">
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-tag"></i> Price (Rs) *</label>
                <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-percentage"></i> Discount (%)</label>
                <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
            </div>
            <div class="colors-subsection">
                <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
                <div class="colors-container" data-size-index="0">
                    <div class="color-row">
                        <input type="text" name="color_names[0][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
                        <input type="text" name="hex_codes[0][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
                        <input type="number" name="color_quantities[0][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
                        <input type="file" name="color_photos[0][0][]" multiple accept="image/*" style="flex: 2; font-size: 12px;">
                        <button type="button" class="remove-color-btn" onclick="removeColor(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="add-color-btn" onclick="addColor(0)">
                    <i class="fas fa-plus"></i> Add Color/Design
                </button>
            </div>
            <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                <i class="fas fa-trash-alt"></i> Remove Size
            </button>
        </div>
    `;
    
    // Reset the size index
    sizeIndex = 1;
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
    document.querySelector('#addModal form').reset();
    const container = document.getElementById('sizes-container');
    container.innerHTML = `
        <div class="size-row">
            <div class="size-input-group">
                <label><i class="fas fa-text-width"></i> Size *</label>
                <input type="text" name="sizes[]" placeholder="e.g., S, M, L, XL" required>
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-boxes"></i> Quantity (Auto-calculated) *</label>
                <input type="number" name="quantities[]" placeholder="0" min="0" required readonly style="background: #f8f9fa; cursor: not-allowed;" title="Auto-calculated from color quantities">
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-tag"></i> Price (Rs) *</label>
                <input type="number" step="0.01" name="size_prices[]" placeholder="0.00" min="0" required>
            </div>
            <div class="size-input-group">
                <label><i class="fas fa-percentage"></i> Discount (%)</label>
                <input type="number" step="0.01" name="discounts[]" placeholder="0" min="0" max="100" value="0">
            </div>
            <div class="colors-subsection">
                <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
                <div class="colors-container" data-size-index="0">
                    <div class="color-row">
                        <input type="text" name="color_names[0][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
                        <input type="text" name="hex_codes[0][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
                        <input type="number" name="color_quantities[0][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
                        <input type="file" name="color_photos[0][0][]" multiple accept="image/*" style="flex: 2; font-size: 12px;">
                        <button type="button" class="remove-color-btn" onclick="removeColor(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <button type="button" class="add-color-btn" onclick="addColor(0)">
                    <i class="fas fa-plus"></i> Add Color/Design
                </button>
            </div>
            <button type="button" class="remove-size-btn" onclick="removeSize(this)">
                <i class="fas fa-trash-alt"></i> Remove Size
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

    // Show loading state
    const container = document.getElementById('edit-sizes-container');
    container.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;"><i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 15px;">Loading product details...</p></div>';

    fetch(`ManageProducts.php?ajax=get_details&id=${data.id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(productData => {
            container.innerHTML = '';
            
            if (productData.sizes && productData.sizes.length > 0) {
                productData.sizes.forEach((size, index) => {
                    const div = document.createElement('div');
                    div.classList.add('size-row');
                    
                    // Build existing colors HTML with LAZY-LOADED photos
                    let existingColorsHtml = '';
                    if (size.colors && size.colors.length > 0) {
                        const colorRowsHtml = size.colors.map((color, colorIdx) => {
                            // Limit photos display and use lazy loading
                            let colorPhotosHtml = '';
                            if (color.photos && color.photos.length > 0) {
                                const maxDisplayPhotos = 6; // Only show first 6 photos
                                const displayPhotos = color.photos.slice(0, maxDisplayPhotos);
const remainingCount = color.photos.length - maxDisplayPhotos;

colorPhotosHtml = `
    <div class="existing-photos-section" style="margin-top: 8px;">
        <h6 style="font-size: 11px; color: #666; margin-bottom: 6px;">
            <i class="fas fa-images"></i> Photos (${color.photos.length})
            ${remainingCount > 0 ? `<span style="color: #999; font-weight: normal;"> - Showing first ${maxDisplayPhotos}</span>` : ''}
        </h6>
        <div class="existing-photos-grid">
            ${displayPhotos.map(photo => `
                <div class="existing-photo-item" data-photo-id="${photo.id}">
                    <img src="data:image/jpeg;base64,${photo.photo}" 
                         alt="Photo"
                         loading="lazy"
                         style="background: #f0f0f0;">
                    <button type="button" class="delete-photo-btn" 
                            onclick="deletePhoto(${photo.id}, this)" 
                            title="Delete this photo">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('')}
        </div>
                                        ${remainingCount > 0 ? `
                                            <button type="button" 
                                                    onclick="loadMorePhotos(${data.id}, ${color.id}, this)" 
                                                    style="margin-top: 8px; padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer; width: 100%;">
                                                <i class="fas fa-plus-circle"></i> Load ${remainingCount} more ${remainingCount === 1 ? 'photo' : 'photos'}
                                            </button>
                                        ` : ''}
                                    </div>
                                `;
                            }
                            
                            return `
                                <div class="color-row" style="flex-direction: column; gap: 8px; padding: 10px; background: white; border-radius: 6px; margin-bottom: 10px;" data-color-id="${color.id}">
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <input type="text" name="edit_color_names[${index}][]" value="${color.color_name}" placeholder="Color or Design" style="flex: 2;">
                                        <input type="text" name="edit_hex_codes[${index}][]" value="${color.hex_code || ''}" placeholder="#FF0000 or Code" style="flex: 1;">
                                        <input type="number" name="edit_color_quantities[${index}][]" value="${color.quantity}" placeholder="Qty" min="0" style="flex: 1;">
                                        <button type="button" class="remove-color-btn" onclick="removeEditColor(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    ${colorPhotosHtml}
                                    <div style="margin-top: 4px;">
                                        <label style="font-size: 11px; color: #666; display: block; margin-bottom: 4px;">
                                            <i class="fas fa-plus-circle"></i> Add photos for this color/design
                                        </label>
                                        <input type="file" name="edit_color_photos[${index}][${colorIdx}][]" multiple accept="image/*" style="width: 100%; font-size: 11px;">
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        existingColorsHtml = `
                            <div class="colors-subsection">
                                <h5><i class="fas fa-palette"></i> Colors/Designs for this size (${size.colors.length} existing)</h5>
                                <div class="colors-container" data-edit-size-index="${index}">
                                    ${colorRowsHtml}
                                </div>
                                <button type="button" class="add-color-btn" onclick="addEditColor(${index})">
                                    <i class="fas fa-plus"></i> Add Color/Design
                                </button>
                            </div>
                        `;
                    } else {
                        existingColorsHtml = `
                            <div class="colors-subsection">
                                <h5><i class="fas fa-palette"></i> Colors/Designs for this size</h5>
                                <div class="colors-container" data-edit-size-index="${index}">
                                    <div class="color-row" style="flex-direction: column; gap: 8px; padding: 10px; background: white; border-radius: 6px;">
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <input type="text" name="edit_color_names[${index}][]" placeholder="Color or Design" style="flex: 2;">
                                            <input type="text" name="edit_hex_codes[${index}][]" placeholder="#FF0000 or Code" style="flex: 1;">
                                            <input type="number" name="edit_color_quantities[${index}][]" placeholder="Qty" min="0" value="0" style="flex: 1;">
                                            <button type="button" class="remove-color-btn" onclick="removeEditColor(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div style="margin-top: 4px;">
                                            <label style="font-size: 11px; color: #666; display: block; margin-bottom: 4px;">
                                                <i class="fas fa-plus-circle"></i> Add photos for this color/design
                                            </label>
                                            <input type="file" name="edit_color_photos[${index}][0][]" multiple accept="image/*" style="width: 100%; font-size: 11px;">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="add-color-btn" onclick="addEditColor(${index})">
                                    <i class="fas fa-plus"></i> Add Color/Design
                                </button>
                            </div>
                        `;
                    }
                    
                    div.innerHTML = `
    <div class="size-input-group">
        <label><i class="fas fa-text-width"></i> Size *</label>
        <input type="text" name="edit_sizes[]" value="${size.size}" required placeholder="e.g., S, M, L">
    </div>
    <div class="size-input-group">
        <label><i class="fas fa-boxes"></i> Quantity *</label>
        <input type="number" name="edit_quantities[]" value="${size.quantity}" min="0" required placeholder="0">
    </div>
    <div class="size-input-group">
        <label><i class="fas fa-tag"></i> Price (Rs) *</label>
        <input type="number" step="0.01" name="edit_size_prices[]" value="${size.price}" min="0" required placeholder="0.00">
    </div>
    <div class="size-input-group">
        <label><i class="fas fa-percentage"></i> Discount (%)</label>
        <input type="number" step="0.01" name="edit_discounts[]" value="${size.discount}" min="0" max="100" placeholder="0">
    </div>
    ${existingColorsHtml}
    <button type="button" class="remove-size-btn" onclick="removeSize(this)">
        <i class="fas fa-trash-alt"></i> Remove Size
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
            container.innerHTML = '<div style="text-align: center; padding: 40px; color: #ff4757;"><i class="fas fa-exclamation-triangle" style="font-size: 32px;"></i><p style="margin-top: 15px;">Error loading product details. Please try again.</p></div>';
            setTimeout(() => {
    makeQuantityFieldsReadOnly();
    // Recalculate all size quantities based on existing colors
    document.querySelectorAll('.size-row').forEach(sizeRow => {
        const firstColorInput = sizeRow.querySelector('input[name^="edit_color_quantities"]');
        if (firstColorInput) {
            updateSizeQuantity(firstColorInput);
        }
    });
}, 100);
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
                    <div class="info-value">Rs ${parseFloat(data.price).toFixed(2)}</div>
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
    const modal = document.getElementById('viewModal');
    const contentDiv = document.getElementById('view_content');
    
    modal.classList.add('active');
    
    // Show loading state
    document.getElementById('view_product_name').textContent = 'Loading...';
    contentDiv.innerHTML = '<div style="text-align: center; padding: 60px; color: #999;"><i class="fas fa-spinner fa-spin" style="font-size: 48px;"></i><p style="margin-top: 20px; font-size: 16px;">Loading product details...</p></div>';
    
    fetch(`ManageProducts.php?ajax=get_details&id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            document.getElementById('view_product_name').textContent = data.product_name;
            
            const genderLabels = ['Unisex', 'Male', 'Female'];
            const genderLabel = genderLabels[data.gender] || 'Unisex';
            
            // Build stats cards
            const statsHtml = `
                <div class="product-stats">
                    <div class="stat-card category">
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-label">Category</div>
                        <div class="stat-value">${data.category_name || 'N/A'}</div>
                        ${data.subcategory_name ? `<div style="font-size: 13px; color: #999; margin-top: 5px;">${data.subcategory_name}</div>` : ''}
                    </div>
                    
                    <div class="stat-card price">
                        <div class="stat-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-label">Base Price</div>
                        <div class="stat-value">Rs ${parseFloat(data.price).toFixed(2)}</div>
                    </div>
                    
                    <div class="stat-card gender">
                        <div class="stat-icon">
                            <i class="fas fa-${data.gender === 1 ? 'mars' : data.gender === 2 ? 'venus' : 'venus-mars'}"></i>
                        </div>
                        <div class="stat-label">Gender</div>
                        <div class="stat-value">${genderLabel}</div>
                    </div>
                </div>
            `;
            
            // Build description section
            const descriptionHtml = data.description ? `
                <div class="description-section">
                    <h4><i class="fas fa-align-left"></i> Description</h4>
                    <div class="description-text">${data.description}</div>
                </div>
            ` : '';
            
            // Build sizes and variants HTML
            let sizesHtml = '';
            if (data.sizes && data.sizes.length > 0) {
                sizesHtml = `
                    <div class="sizes-variants-section">
                        <h4 class="section-title">
                            <i class="fas fa-th-large"></i>
                            Available Sizes & Variants
                        </h4>
                        ${data.sizes.map(size => {
                            const finalPrice = size.price - (size.price * size.discount / 100);
                            
                            // Build colors HTML
                            let colorsHtml = '';
                            if (size.colors && size.colors.length > 0) {
                                colorsHtml = `
                                    <div class="colors-section">
                                        <div class="colors-title">
                                            <i class="fas fa-palette"></i>
                                            Colors & Designs (${size.colors.length})
                                        </div>
                                        ${size.colors.map(color => {
                                            // Build photos grid
                                            let photosHtml = '';
                                            if (color.photos && color.photos.length > 0) {
                                                const maxInitialPhotos = 6;
                                                const displayPhotos = color.photos.slice(0, maxInitialPhotos);
                                                const remainingCount = color.photos.length - maxInitialPhotos;
                                                
                                                photosHtml = `
                                                    <div class="color-photos-grid photos-grid-${color.id}">
                                                        ${displayPhotos.map(photo => `
                                                            <div class="color-photo-item" onclick="openImageModal('data:image/jpeg;base64,${photo.photo}')">
                                                                <img src="data:image/jpeg;base64,${photo.photo}" 
                                                                     alt="${color.color_name}" 
                                                                     loading="lazy">
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                    ${remainingCount > 0 ? `
                                                        <button class="load-more-photos-btn" onclick="loadAllColorPhotos(${color.id}, ${JSON.stringify(color.photos.slice(maxInitialPhotos)).replace(/"/g, '&quot;')}, this)">
                                                            <i class="fas fa-images"></i>
                                                            Show ${remainingCount} More ${remainingCount === 1 ? 'Photo' : 'Photos'}
                                                        </button>
                                                    ` : ''}
                                                `;
                                            }
                                            
                                            // Determine badge style
                                            const colorBadgeStyle = color.hex_code && color.hex_code.startsWith('#')
                                                ? `background: ${color.hex_code}; color: white; text-shadow: 0 1px 2px rgba(0,0,0,0.3);`
                                                : 'background: #e9ecef; color: #495057;';
                                            
                                            return `
                                                <div class="color-card">
                                                    <div class="color-header">
                                                        <div class="color-badge" style="${colorBadgeStyle}">
                                                            ${color.hex_code && color.hex_code.startsWith('#') ? `
                                                                <span class="color-swatch" style="background: ${color.hex_code};"></span>
                                                            ` : ''}
                                                            <span>${color.color_name}</span>
                                                            ${color.hex_code && !color.hex_code.startsWith('#') ? `
                                                                <em style="opacity: 0.7;">(${color.hex_code})</em>
                                                            ` : ''}
                                                        </div>
                                                        <div class="quantity-badge">
                                                            Qty: ${color.quantity}
                                                        </div>
                                                        ${color.photos && color.photos.length > 0 ? `
                                                            <div class="photos-badge">
                                                                <i class="fas fa-images"></i>
                                                                ${color.photos.length}
                                                            </div>
                                                        ` : ''}
                                                    </div>
                                                    ${photosHtml}
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                `;
                            } else {
                                colorsHtml = `
                                    <div class="no-colors-message">
                                        <div class="empty-state-icon">
                                            <i class="fas fa-palette"></i>
                                        </div>
                                        <p>No colors/designs available for this size</p>
                                    </div>
                                `;
                            }
                            
                            return `
                                <div class="size-card">
                                    <div class="size-header">
                                        <div class="size-name">Size: ${size.size}</div>
                                        <div class="size-badge">
                                            <i class="fas fa-box"></i>
                                            Stock: ${size.quantity}
                                        </div>
                                    </div>
                                    
                                    <div class="size-details-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">Price</div>
                                            <div class="detail-value price">Rs ${parseFloat(size.price).toFixed(2)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Discount</div>
                                            <div class="detail-value discount">${parseFloat(size.discount).toFixed(0)}%</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Final Price</div>
                                            <div class="detail-value final-price">Rs ${finalPrice.toFixed(2)}</div>
                                        </div>
                                    </div>
                                    
                                    ${colorsHtml}
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            } else {
                sizesHtml = `
                    <div class="no-colors-message">
                        <div class="empty-state-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <p>No sizes available for this product</p>
                    </div>
                `;
            }
            
            contentDiv.innerHTML = statsHtml + descriptionHtml + sizesHtml;
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = `
                <div style="text-align: center; padding: 60px; color: #ff4757;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px;"></i>
                    <p style="margin-top: 20px; font-size: 16px;">Error loading product details. Please try again.</p>
                </div>
            `;
            setTimeout(() => {
                closeViewModal();
            }, 2000);
        });
}
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.getElementById('view_content').innerHTML = '';
}

function addColor(sizeIndex) {
    const container = document.querySelector(`.colors-container[data-size-index="${sizeIndex}"]`);
    const colorRow = document.createElement('div');
    colorRow.className = 'color-row';
    colorRow.innerHTML = `
        <input type="text" name="color_names[${sizeIndex}][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
        <input type="text" name="hex_codes[${sizeIndex}][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
        <input type="number" name="color_quantities[${sizeIndex}][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
        <input type="file" name="color_photos[${sizeIndex}][${container.children.length}][]" multiple accept="image/*" style="flex: 2; font-size: 12px;">
        <button type="button" class="remove-color-btn" onclick="removeColor(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(colorRow);
}

function removeEditColor(button) {
    const colorRow = button.closest('.color-row');
    const container = colorRow.parentElement;
    
    // Check if this is an existing color (has color_id data attribute)
    const colorNameInput = colorRow.querySelector('input[name^="edit_color_names"]');
    const colorId = colorRow.dataset.colorId; // Check if this color exists in DB
    
    // Allow removing but confirm if it's the last one
    if (container.children.length <= 1) {
        if (!confirm('This will remove the only color/design. You can add it back later. Continue?')) {
            return;
        }
    }
    
    // If this is an existing color in the database, delete it via AJAX
    if (colorId) {
        if (!confirm('This will permanently delete this color and all its photos from the database. Continue?')) {
            return;
        }
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        fetch(`ManageProducts.php?ajax=delete_color&color_id=${colorId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    colorRow.style.transition = 'all 0.3s ease';
                    colorRow.style.opacity = '0';
                    colorRow.style.transform = 'translateX(-20px)';
                    
                    setTimeout(() => {
                        colorRow.remove();
                        showTempMessage('Color deleted successfully', 'success');
                    }, 300);
                } else {
                    alert('Failed to delete color: ' + (data.error || 'Unknown error'));
                    button.innerHTML = '<i class="fas fa-times"></i>';
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error deleting color:', error);
                alert('Error deleting color. Please try again.');
                button.innerHTML = '<i class="fas fa-times"></i>';
                button.disabled = false;
            });
    } else {
        // This is a new color not yet saved, just remove from DOM
        colorRow.style.transition = 'all 0.3s ease';
        colorRow.style.opacity = '0';
        colorRow.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            colorRow.remove();
        }, 300);
    }
}
function removeColor(button) {
    const colorRow = button.closest('.color-row');
    const container = colorRow.parentElement;
    
    if (container.children.length <= 1) {
        if (!confirm('This will remove the only color/design. You can add it back later. Continue?')) {
            return;
        }
    }
    
    colorRow.style.transition = 'all 0.3s ease';
    colorRow.style.opacity = '0';
    colorRow.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        colorRow.remove();
        // Recalculate size quantity after removal
        const sizeRow = container.closest('.size-row');
        const sizeQuantityInput = sizeRow?.querySelector('input[name="quantities[]"], input[name="edit_quantities[]"]');
        if (sizeQuantityInput) {
            const dummyInput = document.createElement('input');
            dummyInput.closest = () => sizeRow;
            updateSizeQuantity(dummyInput);
        }
    }, 300);
}

function addEditColor(sizeIndex) {
    const container = document.querySelector(`.colors-container[data-edit-size-index="${sizeIndex}"]`);
    if (!container) {
        console.error('Color container not found for size index:', sizeIndex);
        return;
    }
    
    const currentColorCount = container.children.length;
    
    const colorRow = document.createElement('div');
    colorRow.className = 'color-row';
    colorRow.style.cssText = 'flex-direction: column; gap: 8px; padding: 10px; background: white; border-radius: 6px; margin-bottom: 10px;';
    colorRow.innerHTML = `
        <div style="display: flex; gap: 8px; align-items: center;">
            <input type="text" name="edit_color_names[${sizeIndex}][]" placeholder="Color or Design (e.g., Red, Striped)" style="flex: 2;">
            <input type="text" name="edit_hex_codes[${sizeIndex}][]" placeholder="#FF0000 or Design Code (optional)" style="flex: 1;">
            <input type="number" name="edit_color_quantities[${sizeIndex}][]" placeholder="Qty" min="0" value="0" style="flex: 1;" oninput="updateSizeQuantity(this)">
            <button type="button" class="remove-color-btn" onclick="removeEditColor(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="margin-top: 4px;">
            <label style="font-size: 11px; color: #666; display: block; margin-bottom: 4px;">
                <i class="fas fa-plus-circle"></i> Add photos for this color/design
            </label>
            <input type="file" name="edit_color_photos[${sizeIndex}][${currentColorCount}][]" multiple accept="image/*" style="width: 100%; font-size: 11px;">
        </div>
    `;
    container.appendChild(colorRow);
    
    setTimeout(() => {
        colorRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
}

function loadMorePhotos(productId, colorId, buttonElement) {
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    buttonElement.disabled = true;
    
    // Get the photos section and grid
    const photosSection = buttonElement.closest('.existing-photos-section');
    const photosGrid = photosSection.querySelector('.existing-photos-grid');
    
    // Fetch all photos for this color
    fetch(`ManageProducts.php?ajax=get_color_photos&color_id=${colorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.photos) {
                // Get currently displayed photo IDs
                const displayedPhotoIds = Array.from(photosGrid.querySelectorAll('.existing-photo-item'))
                    .map(item => parseInt(item.dataset.photoId));
                
                // Filter out already displayed photos
                const remainingPhotos = data.photos.filter(photo => !displayedPhotoIds.includes(photo.id));
                
                // Add remaining photos to grid
                remainingPhotos.forEach(photo => {
                    const photoDiv = document.createElement('div');
                    photoDiv.className = 'existing-photo-item';
                    photoDiv.dataset.photoId = photo.id;
                    photoDiv.innerHTML = `
                        <img src="data:image/jpeg;base64,${photo.photo}" 
                             alt="Photo"
                             loading="lazy"
                             style="background: #f0f0f0;">
                        <button type="button" class="delete-photo-btn" 
                                onclick="deletePhoto(${photo.id}, this)" 
                                title="Delete this photo">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    photosGrid.appendChild(photoDiv);
                });
                
                // Update header to show total count
                const header = photosSection.querySelector('h6');
                if (header) {
                    header.innerHTML = `<i class="fas fa-images"></i> Photos (${data.photos.length})`;
                }
                
                // Remove the load more button
                buttonElement.remove();
            } else {
                alert('Failed to load photos');
                buttonElement.innerHTML = '<i class="fas fa-images"></i> Load more photos';
                buttonElement.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error loading photos:', error);
            alert('Error loading photos. Please try again.');
            buttonElement.innerHTML = '<i class="fas fa-images"></i> Load more photos';
            buttonElement.disabled = false;
        });
}

// Open image in fullscreen modal
function openImageModal(imageSrc) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 20px;
    `;
    
    const img = document.createElement('img');
    img.src = imageSrc;
    img.style.cssText = `
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 8px;
        box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
    `;
    
    overlay.appendChild(img);
    document.body.appendChild(overlay);
    
    overlay.onclick = () => overlay.remove();
}
function updateSizeQuantity(colorQuantityInput) {
    const sizeRow = colorQuantityInput.closest('.size-row');
    const sizeQuantityInput = sizeRow.querySelector('input[name="quantities[]"], input[name="edit_quantities[]"]');
    
    if (!sizeQuantityInput) return;
    
    // Get all color quantity inputs in this size row
    const colorQuantityInputs = sizeRow.querySelectorAll('input[name^="color_quantities"], input[name^="edit_color_quantities"]');
    
    // Sum up all color quantities
    let totalQuantity = 0;
    colorQuantityInputs.forEach(input => {
        const value = parseInt(input.value) || 0;
        totalQuantity += value;
    });
    
    // Update size quantity
    sizeQuantityInput.value = totalQuantity;
    
    // Add visual feedback
    sizeQuantityInput.style.background = '#e8f5e9';
    setTimeout(() => {
        sizeQuantityInput.style.background = '';
    }, 500);
}

// Make size quantity read-only and add tooltips
function makeQuantityFieldsReadOnly() {
    document.querySelectorAll('input[name="quantities[]"], input[name="edit_quantities[]"]').forEach(input => {
        input.readOnly = true;
        input.style.background = '#f8f9fa';
        input.style.cursor = 'not-allowed';
        input.title = 'Auto-calculated from color quantities';
    });
}

// Call this when modal opens or sizes are added
document.addEventListener('DOMContentLoaded', makeQuantityFieldsReadOnly);

// Function to load all remaining photos for a color
function loadAllColorPhotos(colorId, remainingPhotos, buttonElement) {
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    buttonElement.disabled = true;
    
    const photosGrid = document.querySelector(`.photos-grid-${colorId}`);
    
    try {
        // Parse and add remaining photos
        remainingPhotos.forEach(photo => {
            const photoDiv = document.createElement('div');
            photoDiv.className = 'color-photo-item';
            photoDiv.onclick = () => openImageModal(`data:image/jpeg;base64,${photo.photo}`);
            photoDiv.innerHTML = `
                <img src="data:image/jpeg;base64,${photo.photo}" 
                     alt="Photo" 
                     loading="lazy">
            `;
            photosGrid.appendChild(photoDiv);
        });
        
        // Remove the button
        buttonElement.remove();
    } catch (error) {
        console.error('Error loading photos:', error);
        buttonElement.innerHTML = '<i class="fas fa-images"></i> Show More Photos';
        buttonElement.disabled = false;
    }
}
    </script>
</body>
</html>