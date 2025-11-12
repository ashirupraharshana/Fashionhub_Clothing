<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /fashionhub/Homepage.php");
    exit;
}

// Get logged-in user details
$user_id = $_SESSION['user_id'];
$query = "SELECT fullname, email, phone FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$orders_query = "SELECT COUNT(*) as total_orders, 
                 COALESCE(SUM(total_price), 0) as total_spent 
                 FROM orders WHERE user_id = ?";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders_data = $orders_result->fetch_assoc();
$total_orders = $orders_data['total_orders'];
$total_spent = $orders_data['total_spent'];
$stmt->close();

$cart_query = "SELECT COUNT(*) as cart_items, 
               COALESCE(SUM(c.price * c.quantity), 0) as cart_total 
               FROM cart c WHERE user_id = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_data = $cart_result->fetch_assoc();
$cart_items = $cart_data['cart_items'];
$cart_total = $cart_data['cart_total'];
$stmt->close();

// Get recent orders with product details
$recent_orders_query = "SELECT o.*, p.product_name
                        FROM orders o 
                        LEFT JOIN products p ON o.product_id = p.id
                        WHERE o.user_id = ? 
                        ORDER BY o.order_date DESC 
                        LIMIT 3";
$stmt = $conn->prepare($recent_orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result();
$stmt->close();

// Get featured products with photos
$featured_products_query = "SELECT p.*, c.category_name,
                            (p.price - (p.price * COALESCE((SELECT discount FROM product_sizes WHERE product_id = p.id LIMIT 1), 0) / 100)) as final_price
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            ORDER BY p.id DESC 
                            LIMIT 4";
$featured_products = $conn->query($featured_products_query);

// Get recent feedback with admin replies
$feedback_query = "SELECT f.*, u.fullname 
                   FROM feedback f 
                   LEFT JOIN users u ON f.user_id = u.id 
                   WHERE f.admin_reply IS NOT NULL
                   ORDER BY f.replied_at DESC 
                   LIMIT 6";
$feedback_result = $conn->query($feedback_query);

// Get feedback statistics
$feedback_stats_query = "SELECT 
    COUNT(*) as total_feedbacks,
    COUNT(CASE WHEN admin_reply IS NOT NULL THEN 1 END) as replied_feedbacks,
    COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_feedbacks
    FROM feedback";
$feedback_stats_result = $conn->query($feedback_stats_query);
$feedback_stats = $feedback_stats_result->fetch_assoc();

// Image URLs
$hero_background = "https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1920"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FashionHub | Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1a1f3a 0%, #2d1b4e 50%, #1a1f3a 100%);
            background-attachment: fixed;
            padding-top: 70px;
            min-height: 100vh;
            color: #ffffff;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated mesh gradient background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 15% 30%, rgba(231, 76, 60, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 70%, rgba(52, 152, 219, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(155, 89, 182, 0.1) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
            animation: meshMove 20s ease-in-out infinite;
        }

        @keyframes meshMove {
            0%, 100% { opacity: 0.6; transform: scale(1) rotate(0deg); }
            50% { opacity: 0.8; transform: scale(1.1) rotate(5deg); }
        }

        /* Floating orbs */
        body::after {
            content: '';
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: floatOrb 15s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-100px, 100px) scale(1.2); }
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 50px 30px;
            position: relative;
            z-index: 1;
        }

        /* Hero Section */
        .hero-welcome {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            padding: 70px 60px;
            margin-bottom: 50px;
            box-shadow: 
                0 30px 90px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            min-height: 300px;
            display: flex;
            align-items: center;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hero-welcome::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 8s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
        }
        
        .hero-welcome:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 40px 100px rgba(231, 76, 60, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 700px;
        }

        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #ffffff;
            line-height: 1.2;
            letter-spacing: -1px;
            animation: fadeInUp 0.8s ease;
        }

        .hero-content h1 .highlight {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.8;
            font-weight: 400;
            animation: fadeInUp 0.8s ease 0.2s both;
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

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 40px 35px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 30px 80px rgba(231, 76, 60, 0.3);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            box-shadow: 0 15px 35px rgba(231, 76, 60, 0.5);
            transition: all 0.4s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 20px 45px rgba(231, 76, 60, 0.7);
        }

        .stat-label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }

        .stat-value {
            font-size: 48px;
            font-weight: 800;
            color: #ffffff;
            margin-top: 10px;
            position: relative;
            z-index: 1;
            letter-spacing: -1px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            animation: fadeInUp 0.8s ease;
        }

        .section-title {
            font-size: 32px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 15px;
            letter-spacing: -0.5px;
        }

        .section-title i {
            color: #e74c3c;
            font-size: 32px;
            filter: drop-shadow(0 4px 12px rgba(231, 76, 60, 0.5));
        }

        .btn-primary {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(231, 76, 60, 0.6);
            background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
        }

        /* Orders List */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-bottom: 60px;
        }

        .order-card {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(231, 76, 60, 0.1), transparent);
            transition: left 0.7s ease;
        }

        .order-card:hover::before {
            left: 100%;
        }

        .order-card:hover {
            transform: translateX(8px);
            box-shadow: 0 30px 80px rgba(231, 76, 60, 0.3);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .order-image {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 15px 35px rgba(231, 76, 60, 0.4);
            position: relative;
        }

        .order-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: relative;
            z-index: 1;
        }

        .order-image i {
            color: white;
            font-size: 48px;
            position: relative;
            z-index: 1;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .order-title {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .order-meta {
            display: flex;
            gap: 25px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }

        .order-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-meta i {
            color: #e74c3c;
        }

        .order-price {
            font-size: 32px;
            font-weight: 800;
            color: #e74c3c;
            letter-spacing: -1px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .empty-state i {
            font-size: 80px;
            color: #e74c3c;
            margin-bottom: 25px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 28px;
            color: #ffffff;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 30px;
            font-size: 16px;
        }

        /* Feedback Section */
        .feedback-section {
            margin-bottom: 60px;
        }

        .feedback-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .feedback-stat {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 35px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .feedback-stat:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 80px rgba(231, 76, 60, 0.3);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .feedback-stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            margin: 0 auto 20px;
            box-shadow: 0 15px 35px rgba(231, 76, 60, 0.4);
            transition: all 0.4s ease;
        }

        .feedback-stat:hover .feedback-stat-icon {
            transform: scale(1.1);
        }

        .feedback-stat-value {
            font-size: 42px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .feedback-stat-label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .feedback-card {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feedback-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 80px rgba(231, 76, 60, 0.3);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .feedback-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .feedback-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 10px 25px rgba(231, 76, 60, 0.4);
        }

        .feedback-user {
            flex: 1;
        }

        .feedback-name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 5px;
        }

        .feedback-date {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        .feedback-message {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.8;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .admin-reply {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(192, 57, 43, 0.1) 100%);
            border-left: 4px solid #e74c3c;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .admin-reply-header {
            font-size: 12px;
            font-weight: 700;
            color: #e74c3c;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-reply-text {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.8;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: linear-gradient(135deg, rgba(26, 31, 58, 0.6) 0%, rgba(45, 27, 78, 0.6) 100%);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 35px 90px rgba(231, 76, 60, 0.4);
            border-color: rgba(231, 76, 60, 0.3);
        }

        .product-image {
            width: 100%;
            height: 280px;
            position: relative;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: relative;
            z-index: 1;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.1);
        }

        .product-image i {
            color: white;
            font-size: 64px;
            position: relative;
            z-index: 1;
        }

        .discount-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 10px 30px rgba(231, 76, 60, 0.5);
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .product-details {
            padding: 30px;
            position: relative;
            z-index: 2;
        }

        .product-category {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .product-name {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 50px;
            line-height: 1.4;
        }

        .product-price {
            font-size: 28px;
            font-weight: 800;
            color: #e74c3c;
            letter-spacing: -1px;
        }

        .product-old-price {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.4);
            text-decoration: line-through;
            margin-left: 12px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 20px 15px;
            }

            .hero-welcome {
                padding: 50px 30px;
                min-height: 250px;
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .hero-content p {
                font-size: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .section-title {
                font-size: 24px;
            }

            .btn-primary {
                width: 100%;
                justify-content: center;
            }

            .order-card {
                flex-direction: column;
                text-align: center;
                padding: 25px;
            }

            .order-meta {
                justify-content: center;
                flex-wrap: wrap;
            }

            .feedback-grid {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .stat-value {
                font-size: 40px;
            }

            .order-price {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <?php include 'Components/CustomerNavBar.php'; ?>

    <div class="dashboard-container">
        <!-- Hero Welcome -->
        <div class="hero-welcome">
            <div class="hero-content">
                <h1>Welcome back, <span class="highlight"><?php echo htmlspecialchars(explode(' ', $user['fullname'])[0]); ?>!</span> 👋</h1>
                <p>Explore the latest fashion trends and manage your shopping experience from your personal dashboard.</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-label">Cart Items</div>
                </div>
                <div class="stat-value"><?php echo $cart_items; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-value">Rs. <?php echo number_format($total_spent, 0); ?></div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Recent Orders
            </h2>
            <?php if ($recent_orders->num_rows > 0): ?>
                <a href="Orders.php" class="btn-primary">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            <?php endif; ?>
        </div>

        <div class="orders-list">
            <?php if ($recent_orders->num_rows > 0): ?>
                <?php while($order = $recent_orders->fetch_assoc()): 
                    // Get product photo
                    $photoStmt = $conn->prepare("SELECT photo FROM photos WHERE product_id = ? LIMIT 1");
                    $photoStmt->bind_param("i", $order['product_id']);
                    $photoStmt->execute();
                    $photoResult = $photoStmt->get_result();
                    $photo = $photoResult->fetch_assoc();
                    $photoStmt->close();
                ?>
                    <div class="order-card">
                        <div class="order-image">
                            <?php if ($photo && !empty($photo['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $photo['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($order['product_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='flex';">
                                <i class="fas fa-shopping-bag" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-shopping-bag"></i>
                            <?php endif; ?>
                        </div>
                        <div class="order-info">
                            <div class="order-number">Order #<?php echo $order['id']; ?></div>
                            <div class="order-title"><?php echo htmlspecialchars($order['product_name']); ?></div>
                            <div class="order-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                                <span><i class="fas fa-cube"></i> <?php echo $order['quantity']; ?> item(s)</span>
                            </div>
                        </div>
                        <div class="order-price">Rs. <?php echo number_format($order['total_price'], 2); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Orders Yet</h3>
                    <p>Start shopping to see your orders here!</p>
                    <a href="Products.php" class="btn-primary">Browse Products</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Customer Feedback -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-comments"></i>
                Customer Feedback
            </h2>
            <a href="Feedback.php" class="btn-primary">
                Share Feedback <i class="fas fa-paper-plane"></i>
            </a>
        </div>

        <div class="feedback-section">
            <div class="feedback-stats">
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['total_feedbacks']; ?></div>
                    <div class="feedback-stat-label">Total Feedback</div>
                </div>
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-reply-all"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['replied_feedbacks']; ?></div>
                    <div class="feedback-stat-label">Replied</div>
                </div>
                <div class="feedback-stat">
                    <div class="feedback-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="feedback-stat-value"><?php echo $feedback_stats['recent_feedbacks']; ?></div>
                    <div class="feedback-stat-label">This Week</div>
                </div>
            </div>

            <?php if ($feedback_result->num_rows > 0): ?>
                <div class="feedback-grid">
                    <?php while($feedback = $feedback_result->fetch_assoc()): ?>
                        <div class="feedback-card">
                            <div class="feedback-header">
                                <div class="feedback-avatar">
                                    <?php echo strtoupper(substr($feedback['name'], 0, 1)); ?>
                                </div>
                                <div class="feedback-user">
                                    <div class="feedback-name"><?php echo htmlspecialchars($feedback['name']); ?></div>
                                    <div class="feedback-date">
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($feedback['submitted_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="feedback-message">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            <?php if (!empty($feedback['admin_reply'])): ?>
                                <div class="admin-reply">
                                    <div class="admin-reply-header">
                                        <i class="fas fa-reply"></i>
                                        Admin Response
                                    </div>
                                    <div class="admin-reply-text">
                                        <?php echo nl2br(htmlspecialchars($feedback['admin_reply'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <h3>No Feedback Yet</h3>
                    <p>Be the first to share your thoughts!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Latest Products -->
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-star"></i>
                Latest Arrivals
            </h2>
            <a href="Products.php" class="btn-primary">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="products-grid">
            <?php if ($featured_products->num_rows > 0): ?>
                <?php while($product = $featured_products->fetch_assoc()): 
                    // Get product photo and discount
                    $photoStmt = $conn->prepare("SELECT photo FROM photos WHERE product_id = ? LIMIT 1");
                    $photoStmt->bind_param("i", $product['id']);
                    $photoStmt->execute();
                    $photoResult = $photoStmt->get_result();
                    $photo = $photoResult->fetch_assoc();
                    $photoStmt->close();

                    // Get discount from product_sizes
                    $discountStmt = $conn->prepare("SELECT discount FROM product_sizes WHERE product_id = ? LIMIT 1");
                    $discountStmt->bind_param("i", $product['id']);
                    $discountStmt->execute();
                    $discountResult = $discountStmt->get_result();
                    $discountData = $discountResult->fetch_assoc();
                    $discount = $discountData ? $discountData['discount'] : 0;
                    $discountStmt->close();

                    $final_price = $product['price'] - ($product['price'] * $discount / 100);
                ?>
                    <div class="product-card" onclick="window.location.href='Products.php'">
                        <div class="product-image">
                            <?php if ($discount > 0): ?>
                                <div class="discount-badge"><?php echo $discount; ?>% OFF</div>
                            <?php endif; ?>
                            <?php if ($photo && !empty($photo['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo $photo['photo']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                     onerror="this.style.display='none'; this.parentElement.querySelector('i').style.display='flex';">
                                <i class="fas fa-tshirt" style="display: none;"></i>
                            <?php else: ?>
                                <i class="fas fa-tshirt"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div>
                                <span class="product-price">Rs. <?php echo number_format($final_price, 2); ?></span>
                                <?php if ($discount > 0): ?>
                                    <span class="product-old-price">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'Components/Footer.php'; ?>
</body>
</html>