<?php
require_once 'includes/db_connect.php';
include 'includes/header.php';
include 'assets/getData/functions_filter.php';

// Get parameters
$records_per_page = 8;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : '';
$color = isset($_GET['color']) && is_numeric($_GET['color']) ? (int)$_GET['color'] : '';
$brand = isset($_GET['brand']) && is_numeric($_GET['brand']) ? (int)$_GET['brand'] : '';

// Fetch data
$data = getProducts($conn, $records_per_page, $page, $search, $category, $min_price, $max_price, $color, $brand);
$products = $data['products'];
$total_pages = $data['total_pages'];
?>

<style>
    .color-options {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;
        margin: 5px 0;
        min-height: 25px;
        border: 1px solid #ccc;
    }

    .color-circle {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #fff;
        cursor: pointer;
        display: inline-block;
        box-sizing: border-box;
        outline: 1px solid #000;
        transition: transform 0.2s;
    }

    .color-circle:hover {
        transform: scale(1.2);
    }

    .product-card .image-container {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
    }

    .product-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: opacity 0.3s ease;
    }

    .rating {
        margin-bottom: 5px;
    }

    .debug {
        color: red;
        font-size: 12px;
    }
</style>

<div id="contactCarousel" class="carousel slide">
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="images/another/banner_product_page.jpg" alt="Banner Product Image" class="d-block w-100">
            <div class="carousel-content">
                <h1 class="">Products</h1>
            </div>
        </div>
    </div>
</div>

<div class="w-90 mx-auto">
    <!-- Phần Trang Chủ / Products căn trái -->
    <div class="d-flex justify-content-start mb-2">
        <p class="mb-0 text-dark">
            <a href="index.php" class="link text-dark text-decoration-none">Home</a> / Products
        </p>
    </div>
</div>

<div class="w-75 mx-auto">
    <div class="row">
        <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4 position-relative">
                <a href="detail_product.php?id=<?php echo $product['product_id']; ?>">
                    <div class="card product-card w-63 h-auto position-relative">
                        <div class="image-container">
                            <div class="rating">
                                <p class="card-text fw-bold d-flex text-start gap-1">
                                    <?php echo number_format($product['average_rating'], 1); ?><i class="bi bi-star-fill"></i>
                                </p>
                            </div>
                            <img src="<?php echo htmlspecialchars($product['product_image'] ?: 'images/product/aviara1.jpg'); ?>" class="card-img-top current-image" alt="<?php echo htmlspecialchars($product['product_name']); ?>" id="main-image-<?php echo $product['product_id']; ?>" data-default-image="<?php echo htmlspecialchars($product['product_image'] ?: 'images/product/aviara1.jpg'); ?>">
                        </div>
                        <div class="card-body text-center">
                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <p class="card-text fw-bold"><?php echo number_format($product['product_price'], 0, '', '.'); ?>₫</p>
                            <div class="color-options">
                                <?php
                                // Truy vấn để lấy màu và ảnh
                                $stmt = $conn->prepare("SELECT pv.color_id, c.hex_code, pi.image_url
                                                       FROM product_variants pv
                                                       JOIN colors c ON pv.color_id = c.id
                                                       LEFT JOIN product_images pi ON pv.product_id = pi.product_id AND pv.color_id = pi.color_id
                                                       WHERE pv.product_id = ?");
                                $stmt->bind_param("i", $product['product_id']);
                                $stmt->execute();
                                $variants = $stmt->get_result();

                                if ($variants->num_rows > 0) {
                                    while ($variant = $variants->fetch_assoc()) {
                                        $hex_color = $variant['hex_code'];
                                        $image_url = $variant['image_url'] ?: 'images/product/default.jpg';
                                        echo "<div class='color-circle' style='background-color: " . $hex_color . " !important;' title='Color: " . $hex_color . "' data-image='" . htmlspecialchars($image_url) . "' data-product-id='" . $product['product_id'] . "'></div>";
                                    }
                                } else {
                                    echo "<div class='debug'>No variants found for product ID: " . $product['product_id'] . "</div>";
                                }
                                $stmt->close();
                                ?>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endwhile; ?>

        <!-- Phân trang -->
        <nav aria-label="Page navigation" mb-3">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                        <a class="page-link bg-danger border-0" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&min_price=<?php echo urlencode($min_price); ?>&max_price=<?php echo urlencode($max_price); ?>&color=<?php echo urlencode($color); ?>&brand=<?php echo urlencode($brand); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.color-circle').forEach(circle => {
            const productId = circle.getAttribute('data-product-id');
            const defaultImage = document.getElementById('main-image-' + productId).getAttribute('data-default-image');

            circle.addEventListener('mouseover', function() {
                const imageUrl = this.getAttribute('data-image');
                const mainImage = document.getElementById('main-image-' + productId);
                if (mainImage && imageUrl) {
                    mainImage.src = imageUrl;
                    console.log('Hovering over color, new image src:', imageUrl);
                } else {
                    console.log('Error: mainImage or imageUrl not found', {
                        productId,
                        imageUrl
                    });
                }
            });

            circle.addEventListener('mouseout', function() {
                const mainImage = document.getElementById('main-image-' + productId);
                if (mainImage && defaultImage) {
                    mainImage.src = defaultImage;
                    console.log('Mouse out, reverting to default image:', defaultImage);
                } else {
                    console.log('Error: mainImage or defaultImage not found', {
                        productId,
                        defaultImage
                    });
                }
            });
        });
        console.log('Number of color circles:', document.querySelectorAll('.color-circle').length);
    });
</script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>