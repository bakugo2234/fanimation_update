<?php
session_start();
require_once 'includes/db_connect.php';
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
// Khai báo biến $current_page
$current_page = basename($_SERVER['PHP_SELF']);
// Debug: Kiểm tra giá trị của $current_page
// echo "Current page: " . $current_page . "<br>";
$is_help_center = ($current_page == 'help_center.php' || strpos($_SERVER['REQUEST_URI'], 'help_center.php#') !== false) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FANIMATION</title>
    <meta name="description" content="Shop premium luggage, backpacks, handbags, and accessories at Brown Luggage. Enjoy exclusive deals and quality products.">
    <meta name="keywords" content="luggage, backpacks, handbags, accessories, Brown Luggage">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztZQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <div class="logo-container">
                <a href="index.php" class="logo-container">
                    <i class="bi bi-fan fan-icon"></i>
                    <div class="fanimation-text">Fanimation</div>
                    <div class="ceiling-fans-text">Ceiling Fans</div>
                </a>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main_nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main_nav">
                <ul class="navbar-nav mx-auto d-flex justify-content-center align-items-center">
                    <li class="nav-item fs-4 p-2">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item fs-4 p-2">
                        <a class="nav-link <?php echo $current_page == 'product.php' ? 'active' : ''; ?>" href="product.php">Product</a>
                    </li>
                    <li class="nav-item dropdown p-2">
                        <a class="nav-link fs-4 <?php echo $is_help_center; ?>" href="help_center.php">Help Center</a>
                        <a class="nav-link dropdown-toggle fs-4 d-inline-block" href="#" data-bs-toggle="dropdown" aria-expanded="false"></a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="help_center.php#contact-tech">Contact Tech Support</a></li>
                            <li><a class="dropdown-item" href="help_center.php#about-us">About Us</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown p-2">
                        <a class="nav-link dropdown-toggle fs-4" href="#" data-bs-toggle="dropdown">Hover me</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Submenu item 1</a></li>
                            <li><a class="dropdown-item" href="#">Submenu item 2</a></li>
                            <li><a class="dropdown-item" href="#">Submenu item 3</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <div>
                <form method="GET" action="search_result.php" class="d-flex align-content-center mb-1">
                    <input class="form-control form-control-sm w-24 me-1 align-items-center mt-2" name="search" type="text" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm kiếm sản phẩm" aria-label="Tìm kiếm">
                </form>
            </div>
            <div class="search-container position-relative d-inline-block me-3">
                <a href="cart.php" class="position-relative">
                    <i class="bi bi-cart3"></i>
                    <?php
                    $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
                    if ($cart_count > 0):
                    ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $cart_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="user-dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                    <i class="bi bi-person-circle"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a class="dropdown-item" href="profile.php">Thông tin tài khoản</a></li>
                        <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                        <li><a class="dropdown-item" href="my_order.php">Đơn hàng của tôi</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="login.php">Đăng nhập</a></li>
                        <li><a class="dropdown-item" href="my_order.php">Đơn hàng của tôi</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
