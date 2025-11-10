<?php
include 'db_connect.php';
include 'Components/CustomerNavBar.php';

// Get filter parameters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$gender_filter = isset($_GET['gender']) ? intval($_GET['gender']) : -1;
$size_filter = isset($_GET['size']) ? $_GET['size'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 999999;

// Build WHERE clause
$where_conditions = ["p.stock_quantity > 0"];

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = $category_filter";
}

if ($gender_filter >= 0) {
    $where_conditions[] = "p.gender = $gender_filter";
}

if (!empty($size_filter)) {
    $size_filter_escaped = $conn->real_escape_string($size_filter);
    $where_conditions[] = "p.size = '$size_filter_escaped'";
}

if (!empty($search)) {
    $where_conditions[] = "(p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if ($min_price > 0 || $max_price < 999999) {
    $where_conditions[] = "p.price >= $min_price AND p.price <= $max_price";
}

$where_clause = implode(" AND ", $where_conditions);

// Determine ORDER BY
$order_by = "p.id DESC";
switch ($sort_by) {
    case 'price_low':
        $order_by = "p.price ASC";
        break;
    case 'price_high':
        $order_by = "p.price DESC";
        break;
    case 'name':
        $order_by = "p.product_name ASC";
        break;
    case 'popular':
        $order_by = "p.stock_quantity ASC";
        break;
}

// Get products
$products_query = "SELECT p.*, c.category_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   WHERE $where_clause
                   ORDER BY $order_by";
$products_result = $conn->query($products_query);

// Get categories for filter
$categories_query = "SELECT id, category_name FROM categories ORDER BY category_name ASC";
$categories_result = $conn->query($categories_query);

// Get price range
$price_range_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE stock_quantity > 0";
$price_range_result = $conn->query($price_range_query);
$price_range = $price_range_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collections | FashionHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2c3e50;
            --accent: #e74c3c;
            --accent-dark: #c0392b;
            --text: #2c3e50;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text);
            background: var(--white);
            overflow-x: hidden;
        }

        /* Hero Header */
        .collections-hero {
            padding: 140px 5% 80px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 50%, #fef5f5 100%);
            position: relative;
            overflow: hidden;
        }

        .collections-hero::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.15); opacity: 0.9; }
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 64px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 20px;
            letter-spacing: -2px;
            line-height: 1.1;
        }

        .hero-content h1 .highlight {
            color: var(--accent);
            position: relative;
        }

        .hero-content p {
            font-size: 18px;
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto 30px;
            line-height: 1.8;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-light);
        }

        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .breadcrumb a:hover {
            color: var(--accent-dark);
        }

        /* Main Container */
        .collections-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 60px 5%;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 50px;
        }

        /* Sidebar Filters */
        .filters-sidebar {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .filter-section:hover {
            border-color: rgba(231, 76, 60, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filter-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }

        .clear-filter {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .clear-filter:hover {
            color: var(--accent-dark);
        }

        .filter-group {
            margin-bottom: 20px;
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 10px 12px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .filter-option:hover {
            background: rgba(231, 76, 60, 0.05);
        }

        .filter-option input[type="radio"],
        .filter-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .filter-option label {
            flex: 1;
            font-size: 14px;
            color: var(--text);
            cursor: pointer;
        }

        .filter-option .count {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 600;
        }

        .size-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .size-option {
            padding: 12px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .size-option:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(231, 76, 60, 0.05);
        }

        .size-option.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .price-slider {
            padding: 20px 10px;
        }

        .price-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .price-input {
            position: relative;
        }

        .price-input span {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
        }

        .price-input input {
            width: 100%;
            padding: 12px 12px 12px 32px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            transition: all 0.3s ease;
        }

        .price-input input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .apply-filters-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .apply-filters-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
        }

        /* Products Area */
        .products-area {
            min-height: 600px;
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .results-info {
            font-size: 16px;
            color: var(--text);
            font-weight: 600;
        }

        .results-info span {
            color: var(--accent);
            font-weight: 800;
        }

        .view-sort {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .view-toggle {
            display: flex;
            gap: 8px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .view-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .view-btn.active {
            background: var(--accent);
            color: white;
        }

        .view-btn:hover:not(.active) {
            background: rgba(231, 76, 60, 0.1);
            color: var(--accent);
        }

        .sort-select {
            padding: 12px 40px 12px 18px;
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            cursor: pointer;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232c3e50' d='M6 9L1 4h10z'/%3E%3C/svg%3E") no-repeat right 15px center;
            appearance: none;
            transition: all 0.3s ease;
        }

        .sort-select:hover {
            border-color: var(--accent);
        }

        .sort-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .products-grid.list-view {
            grid-template-columns: 1fr;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0, 0, 0, 0.06);
            cursor: pointer;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .product-image-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 120%;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }

        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.12);
        }

        .product-image i {
            font-size: 80px;
            color: rgba(0, 0, 0, 0.08);
        }

        .product-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 2;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(10px);
        }

        .badge-discount {
            background: rgba(231, 76, 60, 0.95);
            color: white;
        }

        .badge-stock {
            background: rgba(255, 193, 7, 0.95);
            color: #333;
        }

        .quick-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.4s ease;
            z-index: 3;
        }

        .product-card:hover .quick-actions {
            opacity: 1;
            transform: translateX(0);
        }

        .quick-btn {
            width: 45px;
            height: 45px;
            background: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text);
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .quick-btn:hover {
            background: var(--accent);
            color: white;
            transform: scale(1.1);
        }

        .product-info {
            padding: 25px;
        }

        .product-category {
            font-size: 12px;
            color: var(--accent);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .product-attributes {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .attribute-tag {
            padding: 5px 12px;
            background: rgba(231, 76, 60, 0.08);
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }

        .product-price-section {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .product-price {
            font-size: 26px;
            font-weight: 800;
            color: var(--accent);
        }

        .product-original-price {
            font-size: 15px;
            color: var(--text-light);
            text-decoration: line-through;
        }

        .add-to-cart-btn {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .add-to-cart-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.5);
        }

        /* List View Styles */
        .product-card.list-view {
            display: grid;
            grid-template-columns: 300px 1fr;
        }

        .product-card.list-view .product-image-wrapper {
            padding-bottom: 0;
            height: 100%;
        }

        .product-card.list-view .product-info {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 30px;
            align-items: center;
        }

        .product-card.list-view .product-footer {
            border-top: none;
            padding-top: 0;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 100px;
            color: rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .empty-state h3 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .empty-state p {
            font-size: 16px;
            color: var(--text-light);
            margin-bottom: 30px;
        }

        .empty-state-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 35px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .empty-state-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
        }

        /* Mobile Filter Toggle */
        .mobile-filter-toggle {
            display: none;
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .mobile-filter-toggle:hover {
            transform: scale(1.1);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .collections-container {
                grid-template-columns: 280px 1fr;
                gap: 30px;
            }
        }

        @media (max-width: 968px) {
            .collections-hero {
                padding: 120px 20px 60px;
            }

            .hero-content h1 {
                font-size: 48px;
            }

            .collections-container {
                grid-template-columns: 1fr;
                padding: 40px 20px;
            }

            .filters-sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 100%;
                max-width: 400px;
                height: 100vh;
                background: white;
                z-index: 2000;
                overflow-y: auto;
                padding: 80px 20px 20px;
                transition: left 0.4s ease;
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
            }

            .filters-sidebar.active {
                left: 0;
            }

            .mobile-filter-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
            }

            .products-header {
                flex-direction: column;
                align-items: stretch;
            }

            .view-sort {
                justify-content: space-between;
            }
        }

        @media (max-width: 640px) {
            .hero-content h1 {
                font-size: 36px;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-card.list-view {
                grid-template-columns: 1fr;
            }

            .product-card.list-view .product-info {
                grid-template-columns: 1fr;
            }

            .product-card.list-view .product-footer {
                flex-direction: row;
                justify-content: space-between;
            }
        }

        /* Filter Overlay */
        .filter-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1999;
        }

        .filter-overlay.active {
            display: block;
        }

        .close-filters {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-filters:hover {
            transform: scale(1.1);
        }

        @media (max-width: 968px) {
            .close-filters {
                display: flex;
            }
        }

        /* Members Only Modal Styles */
        .login-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .login-modal.active {
            display: flex;
        }

        .login-modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-modal-header {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            padding: 50px 40px 40px;
            text-align: center;
            position: relative;
            color: white;
        }

        .close-login-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-login-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .login-modal-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            color: white;
            backdrop-filter: blur(10px);
        }

        .login-modal-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 10px;
            color: white;
        }

        .login-modal-header p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.95);
            font-weight: 500;
        }

        .login-modal-body {
            padding: 40px;
        }

        .login-modal-body p {
            font-size: 15px;
            color: var(--text-light);
            line-height: 1.8;
            margin-bottom: 30px;
            text-align: center;
        }

        .login-modal-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn-login-modal,
        .btn-signup-modal {
            padding: 16px 30px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-login-modal {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-login-modal:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(231, 76, 60, 0.4);
        }

        .btn-signup-modal {
            background: white;
            color: var(--accent);
            border: 2px solid var(--accent);
        }

        .btn-signup-modal:hover {
            background: rgba(231, 76, 60, 0.05);
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .login-modal-header {
                padding: 40px 30px 30px;
            }

            .login-modal-header h2 {
                font-size: 26px;
            }

            .login-modal-body {
                padding: 30px;
            }

            .login-modal-icon {
                width: 70px;
                height: 70px;
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Hero Header -->
    <section class="collections-hero">
        <div class="hero-content">
            <h1>Discover Your <span class="highlight">Perfect Style</span></h1>
            <p>Explore our curated collection of premium fashion pieces, handpicked for the discerning trendsetter</p>
            <div class="breadcrumb">
                <a href="/fashionhub/Homepage.php">Home</a>
                <i class="fas fa-chevron-right"></i>
                <span>Collections</span>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div class="collections-container">
        <!-- Filters Sidebar -->
        <aside class="filters-sidebar" id="filtersSidebar">
            <button class="close-filters" onclick="toggleFilters()">
                <i class="fas fa-times"></i>
            </button>

            <form method="GET" action="Collections.php" id="filtersForm">
                <!-- Category Filter -->
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Categories</h3>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" name="category" value="0" id="cat_all" 
                                   <?php echo $category_filter == 0 ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <label for="cat_all">All Categories</label>
                        </div>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <div class="filter-option">
                            <input type="radio" name="category" value="<?php echo $category['id']; ?>" 
                                   id="cat_<?php echo $category['id']; ?>"
                                   <?php echo $category_filter == $category['id'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <label for="cat_<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </label>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Gender Filter -->
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Gender</h3>
                    </div>
                    <div class="filter-options">
                        <div class="filter-option">
                            <input type="radio" name="gender" value="-1" id="gender_all" 
                                   <?php echo $gender_filter == -1 ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <label for="gender_all">All</label>
                        </div>
                        <div class="filter-option">
                            <input type="radio" name="gender" value="0" id="gender_men" 
                                   <?php echo $gender_filter === 0 ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <label for="gender_men">Men</label>
                        </div>
                        <div class="filter-option">
                            <input type="radio" name="gender" value="1" id="gender_women" 
                                   <?php echo $gender_filter === 1 ? 'checked' : ''; ?> 
                                   onchange="this.form.submit()">
                            <label for="gender_women">Women</label>
                        </div>
                    </div>
                </div>

                <!-- Size Filter -->
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Size</h3>
                        <?php if (!empty($size_filter)): ?>
                        <button type="button" class="clear-filter" onclick="clearSize()">Clear</button>
                        <?php endif; ?>
                    </div>
                    <div class="size-grid">
                        <?php 
                        $sizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
                        foreach ($sizes as $size): 
                        ?>
                        <div class="size-option <?php echo $size_filter == $size ? 'active' : ''; ?>" 
                             onclick="selectSize('<?php echo $size; ?>')">
                            <?php echo $size; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="size" id="sizeInput" value="<?php echo htmlspecialchars($size_filter); ?>">
                </div>

                <!-- Price Range Filter -->
                <div class="filter-section">
                    <div class="filter-header">
                        <h3>Price Range</h3>
                    </div>
                    <div class="price-slider">
                        <div class="price-inputs">
                            <div class="price-input">
                                <span>Rs.</span>
                                <input type="number" name="min_price" placeholder="Min" 
                                       value="<?php echo $min_price > 0 ? $min_price : ''; ?>" 
                                       min="0" step="100">
                            </div>
                            <div class="price-input">
                                <span>Rs.</span>
                                <input type="number" name="max_price" placeholder="Max" 
                                       value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" 
                                       min="0" step="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search -->
                <?php if (!empty($search)): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>

                <!-- Sort -->
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">

                <!-- Apply Filters Button -->
                <button type="submit" class="apply-filters-btn">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </form>
        </aside>

        <!-- Products Area -->
        <main class="products-area">
            <!-- Products Header -->
            <div class="products-header">
                <div class="results-info">
                    Showing <span><?php echo $products_result->num_rows; ?></span> 
                    <?php echo $products_result->num_rows == 1 ? 'Product' : 'Products'; ?>
                </div>

                <div class="view-sort">
                    <div class="view-toggle">
                        <button class="view-btn active" id="gridViewBtn" onclick="setView('grid')">
                            <i class="fas fa-th"></i>
                        </button>
                        <button class="view-btn" id="listViewBtn" onclick="setView('list')">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>

                    <select class="sort-select" name="sort" onchange="changeSort(this.value)">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                        <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <?php if ($products_result->num_rows > 0): ?>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <?php 
                        $final_price = $product['price'] - ($product['price'] * $product['discount'] / 100);
                        ?>
                        <div class="product-card" onclick="window.location.href='/fashionhub/ProductDetails.php?id=<?php echo $product['id']; ?>'">
                            <div class="product-image-wrapper">
                                <div class="product-image">
                                    <?php if (!empty($product['product_photo'])): ?>
                                        <img src="data:image/jpeg;base64,<?php echo $product['product_photo']; ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <i class="fas fa-tshirt" style="display: none;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-tshirt"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="product-badges">
                                    <?php if ($product['discount'] > 0): ?>
                                        <span class="badge badge-discount"><?php echo $product['discount']; ?>% OFF</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['stock_quantity'] <= 10): ?>
                                        <span class="badge badge-stock">Low Stock</span>
                                    <?php endif; ?>
                                </div>

                                <div class="quick-actions">
                                    <button class="quick-btn" onclick="event.stopPropagation(); quickView(<?php echo $product['id']; ?>)" title="Quick View">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="product-info">
                                <div>
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <h3 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    
                                    <?php if ($product['gender'] !== null || $product['size'] !== null): ?>
                                    <div class="product-attributes">
                                        <?php if ($product['gender'] !== null): ?>
                                        <span class="attribute-tag">
                                            <i class="fas fa-venus-mars"></i>
                                            <?php echo $product['gender'] == 0 ? 'Men' : 'Women'; ?>
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['size'] !== null): ?>
                                        <span class="attribute-tag">
                                            <i class="fas fa-ruler"></i>
                                            <?php echo htmlspecialchars($product['size']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="product-footer">
                                    <div class="product-price-section">
                                        <div class="product-price">Rs.<?php echo number_format($final_price, 2); ?></div>
                                        <?php if ($product['discount'] > 0): ?>
                                            <div class="product-original-price">Rs.<?php echo number_format($product['price'], 2); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>)" title="Add to Cart">
                                        <i class="fas fa-shopping-cart"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Products Found</h3>
                        <p>We couldn't find any products matching your criteria. Try adjusting your filters.</p>
                        <a href="Collections.php" class="empty-state-btn">
                            <i class="fas fa-redo"></i>
                            <span>Clear All Filters</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mobile Filter Toggle -->
    <button class="mobile-filter-toggle" onclick="toggleFilters()">
        <i class="fas fa-filter"></i>
    </button>

    <!-- Filter Overlay -->
    <div class="filter-overlay" id="filterOverlay" onclick="toggleFilters()"></div>

    <!-- Members Only Modal -->
    <div class="login-modal" id="membersOnlyModal">
        <div class="login-modal-content">
            <div class="login-modal-header">
                <button class="close-login-modal" onclick="closeMembersOnlyModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="login-modal-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h2>Members Only</h2>
                <p>Exclusive access for registered members</p>
            </div>
            <div class="login-modal-body">
                <p>To add items to your cart and enjoy exclusive member benefits, please log in to your account or create a new one.</p>
                <div class="login-modal-actions">
                    <button class="btn-login-modal" onclick="openLoginFromMembersModal()">
                        <i class="fas fa-sign-in-alt"></i>
                        Login
                    </button>
                    <button class="btn-signup-modal" onclick="openSignupFromMembersModal()">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>

    <script>
 // Add to Cart - Opens "Members Only" modal for non-logged-in users
function addToCart(productId) {
    // For testing/debugging - check if user is logged in
    // Replace this with your actual session check
    const isLoggedIn = false; // Change based on your PHP session: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>
    
    if (!isLoggedIn) {
        console.log('Opening members only modal...'); // Debug log
        openMembersOnlyModal();
    } else {
        // Add your cart logic here for logged-in users
        console.log('Adding to cart: Product ID ' + productId);
        // You can add AJAX call here to add to cart
        alert('Product added to cart! (Product ID: ' + productId + ')');
    }
}

// Members Only Modal Functions
function openMembersOnlyModal() {
    console.log('openMembersOnlyModal called'); // Debug log
    const modal = document.getElementById('membersOnlyModal');
    console.log('Modal element:', modal); // Debug log
    
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        console.log('Modal opened successfully'); // Debug log
    } else {
        console.error('Members Only Modal not found!'); // Error log
    }
}

function closeMembersOnlyModal() {
    const modal = document.getElementById('membersOnlyModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Function to open navbar login modal from members only modal
function openLoginFromMembersModal() {
    closeMembersOnlyModal();
    
    // Wait a bit for modal to close, then try to trigger login
    setTimeout(function() {
        // Try multiple ways to open the login modal
        const navLoginBtn = document.getElementById('loginBtn');
        const loginLink = document.querySelector('[href*="login"]');
        const loginModal = document.getElementById('loginModal');
        
        if (navLoginBtn) {
            navLoginBtn.click();
        } else if (loginModal) {
            loginModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else if (loginLink) {
            loginLink.click();
        } else {
            // Fallback: redirect to login page or homepage
            window.location.href = '/fashionhub/Homepage.php?action=login';
        }
    }, 300);
}

// Function to open navbar signup modal from members only modal
function openSignupFromMembersModal() {
    closeMembersOnlyModal();
    
    // Wait a bit for modal to close, then try to trigger signup
    setTimeout(function() {
        // Try multiple ways to open the signup modal
        const navSignupBtn = document.getElementById('signupBtn');
        const signupLink = document.querySelector('[href*="signup"]');
        const signupModal = document.getElementById('signupModal');
        
        if (navSignupBtn) {
            navSignupBtn.click();
        } else if (signupModal) {
            signupModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else if (signupLink) {
            signupLink.click();
        } else {
            // Fallback: redirect to signup page or homepage
            window.location.href = '/fashionhub/Homepage.php?action=signup';
        }
    }, 300);
}

// Close members only modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const membersModal = document.getElementById('membersOnlyModal');
        if (membersModal && membersModal.classList.contains('active')) {
            closeMembersOnlyModal();
        }
        
        const sidebar = document.getElementById('filtersSidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            toggleFilters();
        }
    }
});

// Close members only modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const membersModal = document.getElementById('membersOnlyModal');
    if (membersModal) {
        membersModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeMembersOnlyModal();
            }
        });
    }
    
    // Test if modal exists on page load
    console.log('Members Only Modal exists:', !!membersModal);
});

// Quick View
function quickView(productId) {
    window.location.href = '/fashionhub/ProductDetails.php?id=' + productId;
}

// View Toggle
function setView(view) {
    const grid = document.getElementById('productsGrid');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const cards = document.querySelectorAll('.product-card');

    if (view === 'grid') {
        grid.classList.remove('list-view');
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        cards.forEach(card => card.classList.remove('list-view'));
    } else {
        grid.classList.add('list-view');
        listBtn.classList.add('active');
        gridBtn.classList.remove('active');
        cards.forEach(card => card.classList.add('list-view'));
    }

    localStorage.setItem('preferredView', view);
}

// Load preferred view
window.addEventListener('DOMContentLoaded', function() {
    const preferredView = localStorage.getItem('preferredView') || 'grid';
    setView(preferredView);
});

// Sort Change
function changeSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    window.location.href = url.toString();
}

// Size Selection
function selectSize(size) {
    const sizeInput = document.getElementById('sizeInput');
    const currentSize = sizeInput.value;
    
    if (currentSize === size) {
        sizeInput.value = '';
    } else {
        sizeInput.value = size;
    }
    
    document.getElementById('filtersForm').submit();
}

function clearSize() {
    document.getElementById('sizeInput').value = '';
    document.getElementById('filtersForm').submit();
}

// Mobile Filter Toggle
function toggleFilters() {
    const sidebar = document.getElementById('filtersSidebar');
    const overlay = document.getElementById('filterOverlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
}

// Smooth scroll to top when changing filters
document.getElementById('filtersForm').addEventListener('submit', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
    </script>
</body>
</html>