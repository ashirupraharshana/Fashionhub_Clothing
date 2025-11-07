<?php
include '../db_connect.php';
session_start();

// Increase MySQL max_allowed_packet (add this at the top after db connection)
$conn->query("SET GLOBAL max_allowed_packet=67108864"); // 64MB

// Function to compress and resize image
function compressImage($source, $quality = 75, $maxWidth = 800, $maxHeight = 800) {
    // Check if GD library is available
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
    
    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate new dimensions
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    if ($ratio < 1) {
        $newWidth = floor($width * $ratio);
        $newHeight = floor($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG and GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Copy and resize
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Output to buffer
    ob_start();
    imagejpeg($newImage, null, $quality);
    $imageData = ob_get_clean();
    
    // Free memory
    imagedestroy($image);
    imagedestroy($newImage);
    
    return $imageData;
}

// Handle Add Category
if (isset($_POST['add_category'])) {
    $category_name = $_POST['category_name'];
    $description = $_POST['description'];
    $photo_base64 = null;

    // Convert uploaded file to base64 with compression
    if (isset($_FILES['category_photo']) && $_FILES['category_photo']['error'] == 0) {
        // Check file size (limit to 5MB)
        if ($_FILES['category_photo']['size'] > 5242880) {
            $_SESSION['error'] = "Image file size should not exceed 5MB";
            header("Location: ManageCategory.php");
            exit;
        }
        
        // Check if GD is enabled
        if (!extension_loaded('gd')) {
            // Fallback: Store original image without compression
            $imageData = file_get_contents($_FILES['category_photo']['tmp_name']);
            $photo_base64 = base64_encode($imageData);
        } else {
            // Compress and resize image
            $compressedImage = compressImage($_FILES['category_photo']['tmp_name'], 75, 800, 800);
            
            if ($compressedImage) {
                $photo_base64 = base64_encode($compressedImage);
            } else {
                $_SESSION['error'] = "Failed to process image. Please use JPG, PNG, or GIF format.";
                header("Location: ManageCategory.php");
                exit;
            }
        }
    }

    $sql = "INSERT INTO categories (category_name, description, category_photo)
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $category_name, $description, $photo_base64);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Category added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add category: " . $conn->error;
    }
    
    header("Location: ManageCategory.php");
    exit;
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM categories WHERE id = $id")) {
        $_SESSION['success'] = "Category deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete category.";
    }
    header("Location: ManageCategory.php");
    exit;
}

// Handle Edit Category
if (isset($_POST['edit_category'])) {
    $id = intval($_POST['category_id']);
    $category_name = $conn->real_escape_string($_POST['edit_category_name']);
    $description = $conn->real_escape_string($_POST['edit_description']);

    $photo_update = "";
    if (isset($_FILES['edit_category_photo']) && $_FILES['edit_category_photo']['error'] == 0) {
        // Check file size
        if ($_FILES['edit_category_photo']['size'] > 5242880) {
            $_SESSION['error'] = "Image file size should not exceed 5MB";
            header("Location: ManageCategory.php");
            exit;
        }
        
        // Check if GD is enabled
        if (!extension_loaded('gd')) {
            // Fallback: Store original image without compression
            $imageData = file_get_contents($_FILES['edit_category_photo']['tmp_name']);
            $photo_base64 = base64_encode($imageData);
            $photo_update = ", category_photo = '$photo_base64'";
        } else {
            // Compress and resize image
            $compressedImage = compressImage($_FILES['edit_category_photo']['tmp_name'], 75, 800, 800);
            
            if ($compressedImage) {
                $photo_base64 = base64_encode($compressedImage);
                $photo_update = ", category_photo = '$photo_base64'";
            }
        }
    }

    if ($conn->query("UPDATE categories 
                      SET category_name = '$category_name', description = '$description' $photo_update 
                      WHERE id = $id")) {
        $_SESSION['success'] = "Category updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update category: " . $conn->error;
    }
    
    header("Location: ManageCategory.php");
    exit;
}

// Handle Search
$search = "";
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $query = "SELECT * FROM categories 
              WHERE category_name LIKE '%$search%' OR description LIKE '%$search%'
              ORDER BY id DESC";
} else {
    $query = "SELECT * FROM categories ORDER BY id DESC";
}
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | FashionHub</title>
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

        /* Main Content Area */
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
            position: fixed;
            top: 80px;
            right: 20px;
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

        /* Page Header */
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

        /* Search and Add Section */
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

        .add-category-btn {
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

        .add-category-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Gallery Grid */
        .category-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .category-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .category-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            position: relative;
            overflow: hidden;
        }

        .category-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .category-image i {
            position: relative;
            z-index: 1;
        }

        .category-info {
            padding: 20px;
        }

        .category-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .category-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .category-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .category-date {
            font-size: 12px;
            color: #999;
        }

        .category-actions {
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

        /* Modal Styles */
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
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
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

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            color: #666;
        }

        .file-input-label:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-info {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
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

        /* Empty State */
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }

            .alert {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }

            .category-gallery {
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

            .add-category-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/AdminNavBar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Alert Messages -->
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

        <!-- Page Header -->
        <div class="page-header">
            <h2>Manage Categories</h2>
            <div class="breadcrumb">
                <a href="AdminDashboard.php">Dashboard</a> / Categories
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="ManageCategory.php">
                    <input type="text" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <button class="add-category-btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Category
            </button>
        </div>

        <!-- Category Gallery -->
        <div class="category-gallery">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="category-card">
                        <div class="category-image">
                            <?php if (!empty($row['category_photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $row['category_photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($row['category_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='block';">
                                <i class="fas fa-image" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                        </div>
                        <div class="category-info">
                            <div class="category-name"><?php echo htmlspecialchars($row['category_name']); ?></div>
                            <div class="category-description">
                                <?php echo htmlspecialchars($row['description'] ?: 'No description available'); ?>
                            </div>
                            <div class="category-meta">
                                <div class="category-date">
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </div>
                                <div class="category-actions">
                                    <button class="action-btn edit-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete-btn" onclick="deleteCategory(<?php echo $row['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Categories Found</h3>
                    <p>Start by adding your first category</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeAddModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Add New Category</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('add')">
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="category_name" required placeholder="Enter category name">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Enter category description"></textarea>
                </div>

                <div class="form-group">
                    <label>Category Photo</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="category_photo" id="add_photo" accept="image/*" onchange="validateImage(this)">
                        <label for="add_photo" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose an image</span>
                        </label>
                    </div>
                    <div class="file-info">Max size: 5MB | Formats: JPG, PNG, GIF</div>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="add_category" class="btn-primary">
                        <i class="fas fa-check"></i> Add Category
                    </button>
                    <button type="button" class="btn-secondary" onclick="closeAddModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-header">
                <h3>Edit Category</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm('edit')">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="edit_category_name" id="edit_category_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="edit_description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>New Photo (optional)</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="edit_category_photo" id="edit_photo" accept="image/*" onchange="validateImage(this)">
                        <label for="edit_photo" class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose a new image</span>
                        </label>
                    </div>
                    <div class="file-info">Max size: 5MB | Formats: JPG, PNG, GIF</div>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="edit_category" class="btn-primary">
                        <i class="fas fa-save"></i> Update Category
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
                // Check file size (5MB = 5242880 bytes)
                if (file.size > 5242880) {
                    alert('File size must be less than 5MB!');
                    input.value = '';
                    return false;
                }
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, or GIF)');
                    input.value = '';
                    return false;
                }
                
                // Update label with filename
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
            // Reset form
            document.querySelector('#addModal form').reset();
            document.querySelector('#addModal .file-input-label span').textContent = 'Choose an image';
        }

        function openEditModal(data) {
            document.getElementById('editModal').classList.add('active');
            document.getElementById('edit_category_id').value = data.id;
            document.getElementById('edit_category_name').value = data.category_name;
            document.getElementById('edit_description').value = data.description || '';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            // Reset form
            document.querySelector('#editModal form').reset();
            document.querySelector('#editModal .file-input-label span').textContent = 'Choose a new image';
        }

        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category?')) {
                window.location.href = 'ManageCategory.php?delete=' + id;
            }
        }

        // Close modals when clicking outside
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

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Manual close with animation
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