<?php
include '../db_connect.php';
session_start();

// Increase MySQL max_allowed_packet
$conn->query("SET GLOBAL max_allowed_packet=67108864"); // 64MB

// Function to compress and resize image
function compressImage($source, $quality = 75, $maxWidth = 800, $maxHeight = 800) {
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
    
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = floor($width * $ratio);
        $newHeight = floor($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
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
    
    return $imageData;
}

// Handle Add Product
if (isset($_POST['add_product'])) {
    $category_id = intval($_POST['category_id']);
    $product_name = $_POST['product_name'];
    $description = $_POST['description'];
    $price = floatval($_POST['price']);
    $discount = floatval($_POST['discount']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $gender = isset($_POST['gender']) && $_POST['gender'] !== '' ? intval($_POST['gender']) : null;
    $size = isset($_POST['size']) && $_POST['size'] !== '' ? $_POST['size'] : null;
    $photo_base64 = null;

    if (isset($_FILES['product_photo']) && $_FILES['product_photo']['error'] == 0) {
        if ($_FILES['product_photo']['size'] > 5242880) {
            $_SESSION['error'] = "Image file size should not exceed 5MB";
            header("Location: ManageProducts.php");
            exit;
        }
        
        if (!extension_loaded('gd')) {
            $imageData = file_get_contents($_FILES['product_photo']['tmp_name']);
            $photo_base64 = base64_encode($imageData);
        } else {
            $compressedImage = compressImage($_FILES['product_photo']['tmp_name'], 75, 800, 800);
            
            if ($compressedImage) {
                $photo_base64 = base64_encode($compressedImage);
            } else {
                $_SESSION['error'] = "Failed to process image. Please use JPG, PNG, or GIF format.";
                header("Location: ManageProducts.php");
                exit;
            }
        }
    }

    $sql = "INSERT INTO products (category_id, product_name, description, price, discount, stock_quantity, gender, size, product_photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issddisss", $category_id, $product_name, $description, $price, $discount, $stock_quantity, $gender, $size, $photo_base64);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Product added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add product: " . $conn->error;
    }
    
    header("Location: ManageProducts.php");
    exit;
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM products WHERE id = $id")) {
        $_SESSION['success'] = "Product deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete product.";
    }
    header("Location: ManageProducts.php");
    exit;
}

// Handle Edit Product
if (isset($_POST['edit_product'])) {
    $id = intval($_POST['product_id']);
    $category_id = intval($_POST['edit_category_id']);
    $product_name = $conn->real_escape_string($_POST['edit_product_name']);
    $description = $conn->real_escape_string($_POST['edit_description']);
    $price = floatval($_POST['edit_price']);
    $discount = floatval($_POST['edit_discount']);
    $stock_quantity = intval($_POST['edit_stock_quantity']);
    $gender = isset($_POST['edit_gender']) && $_POST['edit_gender'] !== '' ? intval($_POST['edit_gender']) : null;
    $size = isset($_POST['edit_size']) && $_POST['edit_size'] !== '' ? $conn->real_escape_string($_POST['edit_size']) : null;

    $photo_update = "";
    if (isset($_FILES['edit_product_photo']) && $_FILES['edit_product_photo']['error'] == 0) {
        if ($_FILES['edit_product_photo']['size'] > 5242880) {
            $_SESSION['error'] = "Image file size should not exceed 5MB";
            header("Location: ManageProducts.php");
            exit;
        }
        
        if (!extension_loaded('gd')) {
            $imageData = file_get_contents($_FILES['edit_product_photo']['tmp_name']);
            $photo_base64 = base64_encode($imageData);
            $photo_update = ", product_photo = '$photo_base64'";
        } else {
            $compressedImage = compressImage($_FILES['edit_product_photo']['tmp_name'], 75, 800, 800);
            
            if ($compressedImage) {
                $photo_base64 = base64_encode($compressedImage);
                $photo_update = ", product_photo = '$photo_base64'";
            }
        }
    }

    $gender_value = $gender !== null ? $gender : 'NULL';
    $size_value = $size !== null ? "'$size'" : 'NULL';

    if ($conn->query("UPDATE products 
                      SET category_id = $category_id, 
                          product_name = '$product_name', 
                          description = '$description',
                          price = $price,
                          discount = $discount,
                          stock_quantity = $stock_quantity,
                          gender = $gender_value,
                          size = $size_value
                          $photo_update 
                      WHERE id = $id")) {
        $_SESSION['success'] = "Product updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update product: " . $conn->error;
    }
    
    header("Location: ManageProducts.php");
    exit;
}

// Get categories for dropdown
$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");

// Handle Search and Filter
$search = "";
$category_filter = "";
$gender_filter = "";
$size_filter = "";
$where_conditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_conditions[] = "(p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
    $where_conditions[] = "p.category_id = $category_filter";
}

if (isset($_GET['gender']) && $_GET['gender'] !== '') {
    $gender_filter = intval($_GET['gender']);
    $where_conditions[] = "p.gender = $gender_filter";
}

if (isset($_GET['size']) && !empty($_GET['size'])) {
    $size_filter = $conn->real_escape_string($_GET['size']);
    $where_conditions[] = "p.size = '$size_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT p.*, c.category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause
          ORDER BY p.id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
 
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <div class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                <button class="alert-close">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
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
            <div class="search-filter-group">
                <div class="search-box">
                    <form method="GET" action="ManageProducts.php" id="searchForm">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender_filter); ?>">
                        <input type="hidden" name="size" value="<?php echo htmlspecialchars($size_filter); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                <form method="GET" action="ManageProducts.php" id="filterForm" style="display: contents;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="gender" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Genders</option>
                        <option value="0" <?php echo $gender_filter === '0' || $gender_filter === 0 ? 'selected' : ''; ?>>Men</option>
                        <option value="1" <?php echo $gender_filter === '1' || $gender_filter === 1 ? 'selected' : ''; ?>>Women</option>
                    </select>

                    <select name="size" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Sizes</option>
                        <option value="S" <?php echo $size_filter == 'S' ? 'selected' : ''; ?>>S</option>
                        <option value="M" <?php echo $size_filter == 'M' ? 'selected' : ''; ?>>M</option>
                        <option value="L" <?php echo $size_filter == 'L' ? 'selected' : ''; ?>>L</option>
                        <option value="XL" <?php echo $size_filter == 'XL' ? 'selected' : ''; ?>>XL</option>
                        <option value="XXL" <?php echo $size_filter == 'XXL' ? 'selected' : ''; ?>>XXL</option>
                        <option value="XXXL" <?php echo $size_filter == 'XXXL' ? 'selected' : ''; ?>>XXXL</option>
                    </select>

                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                </form>
            </div>
            <button class="add-product-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Product
            </button>
        </div>

        <div class="product-gallery">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php 
                    $stock_status = '';
                    $stock_class = '';
                    if ($row['stock_quantity'] == 0) {
                        $stock_status = 'Out of Stock';
                        $stock_class = 'out-of-stock';
                    } elseif ($row['stock_quantity'] <= 10) {
                        $stock_status = 'Low Stock';
                        $stock_class = 'low-stock';
                    } else {
                        $stock_status = 'In Stock';
                        $stock_class = 'in-stock';
                    }
                    
                    $final_price = $row['price'] - ($row['price'] * $row['discount'] / 100);
                    ?>
                    <div class="product-card">
                        <?php if ($row['discount'] > 0): ?>
                            <div class="discount-badge"><?php echo $row['discount']; ?>% OFF</div>
                        <?php endif; ?>
                        
                        <?php if ($row['gender'] !== null): ?>
                            <div class="gender-badge <?php echo $row['gender'] == 0 ? 'men' : 'women'; ?>">
                                <?php echo $row['gender'] == 0 ? 'Men' : 'Women'; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_status; ?></div>
                        
                        <div class="product-image">
                            <?php if (!empty($row['product_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $row['product_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='block';">
                                <i class="fas fa-box" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            <div class="product-description">
                                <?php echo htmlspecialchars($row['description'] ?: 'No description available'); ?>
                            </div>
                            
                            <?php if ($row['gender'] !== null || $row['size'] !== null): ?>
                            <div class="product-attributes">
                                <?php if ($row['gender'] !== null): ?>
                                <span class="attribute-tag">
                                    <i class="fas fa-venus-mars"></i>
                                    <?php echo $row['gender'] == 0 ? 'Men' : 'Women'; ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if ($row['size'] !== null): ?>
                                <span class="attribute-tag">
                                    <i class="fas fa-ruler"></i>
                                    <?php echo htmlspecialchars($row['size']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-price-section">
                                <div class="product-price">$<?php echo number_format($final_price, 2); ?></div>
                                <?php if ($row['discount'] > 0): ?>
                                    <div class="product-original-price">$<?php echo number_format($row['price'], 2); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-stock">
                                <i class="fas fa-cube"></i> Stock: <?php echo $row['stock_quantity']; ?> units
                            </div>
                            
                            <div class="product-meta">
                                <div class="product-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </div>
                                <div class="product-actions">
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
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('add')">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" required placeholder="Enter product name">
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select a category</option>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter product description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price ($) *</label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label>Discount (%)</label>
                        <input type="number" name="discount" step="0.01" min="0" max="100" value="0" placeholder="0.00">
                    </div>
                </div>

                <div class="form-row-three">
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock_quantity" min="0" required placeholder="0">
                    </div>

                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="0">Men</option>
                            <option value="1">Women</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Size</label>
                        <select name="size">
                            <option value="">Select Size</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Photo</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="product_photo" id="add_photo" accept="image/*" onchange="validateImage(this)">
                        <label for="add_photo" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose an image</span>
                        </label>
                    </div>
                    <div class="file-info">Max size: 5MB | Formats: JPG, PNG, GIF</div>
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
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('edit')">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="edit_product_name" id="edit_product_name" required>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select name="edit_category_id" id="edit_category_id" required>
                        <option value="">Select a category</option>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price ($) *</label>
                        <input type="number" name="edit_price" id="edit_price" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Discount (%)</label>
                        <input type="number" name="edit_discount" id="edit_discount" step="0.01" min="0" max="100">
                    </div>
                </div>

                <div class="form-row-three">
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="edit_stock_quantity" id="edit_stock_quantity" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Gender</label>
                        <select name="edit_gender" id="edit_gender">
                            <option value="">Select Gender</option>
                            <option value="0">Men</option>
                            <option value="1">Women</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Size</label>
                        <select name="edit_size" id="edit_size">
                            <option value="">Select Size</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Photo (optional)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="edit_product_photo" id="edit_photo" accept="image/*" onchange="validateImage(this)">
                        <label for="edit_photo" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose a new image</span>
                        </label>
                    </div>
                    <div class="file-info">Max size: 5MB | Formats: JPG, PNG, GIF</div>
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

    <script>
        function validateImage(input) {
            const file = input.files[0];
            if (file) {
                if (file.size > 5242880) {
                    alert('File size must be less than 5MB!');
                    input.value = '';
                    return false;
                }
                
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, or GIF)');
                    input.value = '';
                    return false;
                }
                
                const label = input.nextElementSibling.querySelector('span');
                if (label) {
                    label.textContent = file.name;
                }
            }
            return true;
        }

        function validateForm(type) {
            const fileInput = type === 'add' ? 
                document.getElementById('add_photo') : 
                document.getElementById('edit_photo');
            
            if (fileInput.files.length > 0) {
                return validateImage(fileInput);
            }
            return true;
        }

        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.querySelector('#addModal form').reset();
            document.querySelector('#addModal .file-input-label span').textContent = 'Choose an image';
        }

        function openEditModal(data) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_product_id').value = data.id;
            document.getElementById('edit_product_name').value = data.product_name;
            document.getElementById('edit_category_id').value = data.category_id;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_discount').value = data.discount;
            document.getElementById('edit_stock_quantity').value = data.stock_quantity;
            document.getElementById('edit_gender').value = data.gender !== null ? data.gender : '';
            document.getElementById('edit_size').value = data.size || '';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.querySelector('#editModal form').reset();
            document.querySelector('#editModal .file-input-label span').textContent = 'Choose a new image';
        }

        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = 'ManageProducts.php?delete=' + id;
            }
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        document.querySelectorAll('.alert-close').forEach(btn => {
            btn.addEventListener('click', function() {
                const alert = this.parentElement;
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        });
    </script>
</body>
</html>