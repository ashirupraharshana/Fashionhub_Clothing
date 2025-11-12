<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../db_connect.php';


// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Handle Search and Filter
$search = "";
$category_filter = "";
$subcategory_filter = "";
$gender_filter = "";
$where_conditions = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where_conditions[] = "(p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $category_filter = intval($_GET['category']);
    $where_conditions[] = "p.category_id = $category_filter";
}

if (isset($_GET['subcategory']) && !empty($_GET['subcategory'])) {
    $subcategory_filter = intval($_GET['subcategory']);
    $where_conditions[] = "p.subcategory_id = $subcategory_filter";
}

if (isset($_GET['gender']) && $_GET['gender'] !== '') {
    $gender_filter = intval($_GET['gender']);
    $where_conditions[] = "p.gender = $gender_filter";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch products with categories
$sql = "SELECT p.*, c.category_name, s.subcategory_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN subcategories s ON p.subcategory_id = s.id
        $where_clause
        ORDER BY p.id DESC";
$result = $conn->query($sql);

// Get categories and subcategories for filter
$categories = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
$subcategories = $conn->query("SELECT id, subcategory_name FROM subcategories ORDER BY subcategory_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Premium Collection</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%);
            padding-top: 70px;
            min-height: 100vh;
            color: #2c3e50;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 50px 30px;
        }

        .page-hero {
            text-align: center;
            margin-bottom: 60px;
            animation: fadeInDown 0.8s ease;
        }

        .page-hero h1 {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }

        .page-hero p {
            font-size: 18px;
            color: #7f8c8d;
            font-weight: 500;
        }

        .toolbar {
            background: white;
            padding: 35px 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(231, 76, 60, 0.08);
            margin-bottom: 50px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
            border: 2px solid rgba(231, 76, 60, 0.1);
            animation: fadeInUp 0.8s ease;
        }

        .search-box {
            flex: 1;
            min-width: 320px;
        }

        .search-box form {
            position: relative;
        }

        .search-box input[type="text"] {
            width: 100%;
            padding: 16px 60px 16px 24px;
            border: 2px solid #f0f0f0;
            border-radius: 50px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-weight: 500;
            background: #fafafa;
        }

        .search-box input[type="text"]:focus {
            outline: none;
            border-color: #e74c3c;
            background: white;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        .search-box button {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 700;
            font-size: 14px;
        }

        .search-box button:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }

        .filter-select {
            padding: 16px 20px;
            border: 2px solid #f0f0f0;
            border-radius: 50px;
            font-size: 15px;
            cursor: pointer;
            background: #fafafa;
            transition: all 0.3s ease;
            min-width: 180px;
            font-weight: 600;
            color: #2c3e50;
        }

        .filter-select:focus {
            outline: none;
            border-color: #e74c3c;
            background: white;
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
        }

        .clear-filters {
            padding: 16px 32px;
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(52, 73, 94, 0.3);
        }

        .clear-filters:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(52, 73, 94, 0.4);
        }

        .product-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 35px;
            animation: fadeIn 1s ease;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 60px rgba(231, 76, 60, 0.2);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .product-image-container {
            position: relative;
            width: 100%;
            height: 300px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
        }

        .product-photos-slider {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .product-photos-slider img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            transition: opacity 0.6s ease;
        }

        .product-photos-slider img.active {
            opacity: 1;
            z-index: 1;
        }

        .photo-indicators {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 6px;
            z-index: 10;
        }

        .photo-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }

        .photo-indicator.active {
            background: white;
            width: 24px;
            border-radius: 4px;
        }



        .gender-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            z-index: 10;
            backdrop-filter: blur(10px);
            border: 2px solid white;
        }

        .gender-badge.unisex {
            background: rgba(149, 165, 166, 0.9);
            color: white;
        }

        .gender-badge.men {
            background: rgba(52, 152, 219, 0.9);
            color: white;
        }

        .gender-badge.women {
            background: rgba(233, 30, 99, 0.9);
            color: white;
        }

        .product-info {
            padding: 15px 24px;
        }

        .product-category {
            font-size: 11px;
            text-transform: uppercase;
            color: #e74c3c;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .product-name {
            font-size: 20px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.4;
            height: 30px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-sizes {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .size-tag {
            padding: 6px 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8eef3 100%);
            border-radius: 8px;
            font-size: 12px;
            color: #2c3e50;
            font-weight: 700;
            border: 2px solid #e8e8e8;
            transition: all 0.3s ease;
        }

        .product-card:hover .size-tag {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
            color: #e74c3c;
        }

        .product-price-section {
            display: flex;
            align-items: baseline;
            gap: 12px;
            margin-bottom: 5px;
        }

        .product-price {
            font-size: 25px;
            font-weight: 900;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .price-label {
            font-size: 12px;
            color: #95a5a6;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-photos-count {
            display: none;
        }

        .click-hint {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(231, 76, 60, 0.95) 0%, rgba(231, 76, 60, 0) 100%);
            color: white;
            padding: 40px 20px 20px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            opacity: 0;
            transition: all 0.4s ease;
            pointer-events: none;
        }

        .product-card:hover .click-hint {
            opacity: 1;
        }

        .empty-state {
            text-align: center;
            padding: 100px 20px;
            grid-column: 1 / -1;
            background: white;
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 80px;
            color: #e8e8e8;
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 800;
        }

        .empty-state p {
            font-size: 16px;
            color: #7f8c8d;
            font-weight: 500;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 30px 15px;
            }

            .page-hero h1 {
                font-size: 36px;
            }

            .page-hero p {
                font-size: 16px;
            }

            .product-gallery {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 25px;
            }
            
            .toolbar {
                flex-direction: column;
                padding: 25px 20px;
            }

            .search-box {
                width: 100%;
            }

            .filter-select,
            .clear-filters {
                width: 100%;
            }
        }
        .size-tag.out-of-stock {
    text-decoration: line-through;
    background: linear-gradient(135deg, #ecf0f1 0%, #bdc3c7 100%);
    color: #687070ff;
    cursor: not-allowed;
    position: relative;
}

.size-tag.out-of-stock::after {
    content: 'Out of Stock';
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    white-space: nowrap;
    color: #e74c3c;
    font-weight: 600;
}

.product-card:hover .size-tag.out-of-stock {
    border-color: #bdc3c7;
    background: linear-gradient(135deg, #ecf0f1 0%, #bdc3c7 100%);
    color: #95a5a6;
}
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-hero">
            <h1>Premium Collection</h1>
            <p>Discover the finest fashion pieces curated just for you</p>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <form method="GET" action="Products.php">
                    <input type="text" name="search" placeholder="Search your perfect style..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <input type="hidden" name="subcategory" value="<?php echo htmlspecialchars($subcategory_filter); ?>">
                    <input type="hidden" name="gender" value="<?php echo htmlspecialchars($gender_filter); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <form method="GET" action="Products.php" style="display: contents;">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="subcategory" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Subcategories</option>
                    <?php while ($sub = $subcategories->fetch_assoc()): ?>
                        <option value="<?php echo $sub['id']; ?>" <?php echo $subcategory_filter == $sub['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sub['subcategory_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select name="gender" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Genders</option>
                    <option value="0" <?php echo $gender_filter === '0' || $gender_filter === 0 ? 'selected' : ''; ?>>Unisex</option>
                    <option value="1" <?php echo $gender_filter === '1' || $gender_filter === 1 ? 'selected' : ''; ?>>Men</option>
                    <option value="2" <?php echo $gender_filter === '2' || $gender_filter === 2 ? 'selected' : ''; ?>>Women</option>
                </select>
            </form>

            <?php if ($search || $category_filter || $subcategory_filter || $gender_filter !== ''): ?>
                <button class="clear-filters" onclick="window.location.href='Products.php'">
                    <i class="fas fa-times"></i> Clear All
                </button>
            <?php endif; ?>
        </div>

        <div class="product-gallery">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php 
                    // Get all photos for this product
                    $photosStmt = $conn->prepare("SELECT photo FROM photos WHERE product_id = ? ORDER BY id");
                    $photosStmt->bind_param("i", $row['id']);
                    $photosStmt->execute();
                    $photosResult = $photosStmt->get_result();
                    $photos = [];
                    while ($photoRow = $photosResult->fetch_assoc()) {
                        $photos[] = $photoRow['photo'];
                    }
                    $photosStmt->close();
                    
                    // Get sizes
 // Get sizes with quantity
$sizesStmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ? ORDER BY 
    CASE 
        WHEN size = 'S' THEN 1
        WHEN size = 'M' THEN 2
        WHEN size = 'L' THEN 3
        WHEN size = 'XL' THEN 4
        WHEN size = 'XXL' THEN 5
        WHEN size = 'XXXL' THEN 6
        ELSE 7
    END");
$sizesStmt->bind_param("i", $row['id']);
$sizesStmt->execute();
$sizesResult = $sizesStmt->get_result();
$sizes = [];
while ($sizeRow = $sizesResult->fetch_assoc()) {
    $sizes[] = [
        'size' => $sizeRow['size'],
        'quantity' => $sizeRow['quantity']
    ];
}
$sizesStmt->close();
                    
                    $genderLabel = '';
                    $genderClass = '';
                    if ($row['gender'] == 0) {
                        $genderLabel = 'Unisex';
                        $genderClass = 'unisex';
                    } elseif ($row['gender'] == 1) {
                        $genderLabel = 'Men';
                        $genderClass = 'men';
                    } elseif ($row['gender'] == 2) {
                        $genderLabel = 'Women';
                        $genderClass = 'women';
                    }
                    
                    $uniqueId = 'product-' . $row['id'];
                    ?>
                    <div class="product-card" onclick="window.location.href='OrderNow.php?product_id=<?php echo $row['id']; ?>'">
                        <div class="product-image-container">
                            <div class="product-photos-slider" id="<?php echo $uniqueId; ?>">
                                <?php if (!empty($photos)): ?>
                                    <?php foreach ($photos as $index => $photo): ?>
                                        <img src="data:image/jpeg;base64,<?php echo $photo; ?>" 
                                             alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                             class="<?php echo $index === 0 ? 'active' : ''; ?>">
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($photos) > 1): ?>
                                    <div class="photo-indicators">
                                        <?php for ($i = 0; $i < count($photos); $i++): ?>
                                            <div class="photo-indicator <?php echo $i === 0 ? 'active' : ''; ?>"></div>
                                        <?php endfor; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #bdc3c7; font-size: 48px;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($genderLabel): ?>
                                <div class="gender-badge <?php echo $genderClass; ?>">
                                    <?php echo $genderLabel; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="click-hint">
                                <i class="fas fa-hand-pointer"></i> Click to View Details
                            </div>
                        </div>
                        
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($row['product_name']); ?></div>
                            
     <?php if (!empty($sizes)): ?>
<div class="product-sizes">
    <?php foreach ($sizes as $sizeData): ?>
        <span class="size-tag <?php echo $sizeData['quantity'] == 0 ? 'out-of-stock' : ''; ?>">
            <?php echo htmlspecialchars($sizeData['size']); ?>
        </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>
                            <div class="product-price-section">
                                <span class="price-label">Starting from</span>
                                <div class="product-price">Rs. <?php echo number_format($row['price'], 2); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Products Found</h3>
                    <p>Try adjusting your filters or search terms to discover more items</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

    <script>
        // Auto-rotate product photos
        document.addEventListener('DOMContentLoaded', function() {
            const sliders = document.querySelectorAll('.product-photos-slider');
            
            sliders.forEach(slider => {
                const images = slider.querySelectorAll('img');
                const indicators = slider.querySelectorAll('.photo-indicator');
                
                if (images.length <= 1) return;
                
                let currentIndex = 0;
                
                setInterval(() => {
                    images[currentIndex].classList.remove('active');
                    if (indicators.length > 0) {
                        indicators[currentIndex].classList.remove('active');
                    }
                    
                    currentIndex = (currentIndex + 1) % images.length;
                    
                    images[currentIndex].classList.add('active');
                    if (indicators.length > 0) {
                        indicators[currentIndex].classList.add('active');
                    }
                }, 3000);
            });
        });
    </script>
</body>
</html>