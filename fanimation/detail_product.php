<?php
// Đảm bảo không có output trước khi khởi tạo session
ob_start();
// Đảm bảo kết nối cơ sở dữ liệu
require_once 'includes/db_connect.php';
include 'includes/header.php';
// Thêm debug để kiểm tra session sớm

// Xử lý gửi đánh giá (trước khi có đầu ra)
$success = '';
$error = '';
$product_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) && $product_id > 0) {
    if (isset($_SESSION['user_id'])) {
        $rating = (int)$_POST['rating'];
        $message = mysqli_real_escape_string($conn, trim($_POST['message']));
        $user_id = (int)$_SESSION['user_id'];

        if ($rating >= 1 && $rating <= 5) {
            $check_query = "SELECT COUNT(*) as count FROM Feedbacks WHERE user_id = ? AND product_id = ? AND message = ?";
            $check_stmt = mysqli_prepare($conn, $check_query);
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, 'iis', $user_id, $product_id, $message);
                mysqli_stmt_execute($check_stmt);
                $result = mysqli_stmt_get_result($check_stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($check_stmt);

                if ($row['count'] == 0) {
                    $insert_feedback_query = "INSERT INTO feedbacks (user_id, product_id, message, rating, created_at, status) VALUES (?, ?, ?, ?, NOW(), 'approved')";
                    $insert_feedback_stmt = mysqli_prepare($conn, $insert_feedback_query);
                    if ($insert_feedback_stmt) {
                        mysqli_stmt_bind_param($insert_feedback_stmt, 'iisd', $user_id, $product_id, $message, $rating);
                        if (mysqli_stmt_execute($insert_feedback_stmt)) {
                            $success = "Đánh giá đã được gửi thành công!";
                        } else {
                            $error = "Lỗi khi gửi đánh giá: " . mysqli_stmt_error($insert_feedback_stmt);
                        }
                        mysqli_stmt_close($insert_feedback_stmt);
                    } else {
                        $error = "Lỗi chuẩn bị truy vấn: " . mysqli_error($conn);
                    }
                } else {
                    $error = "Bạn đã gửi đánh giá này trước đó!";
                }
            } else {
                $error = "Lỗi kiểm tra trùng lặp: " . mysqli_error($conn);
            }
        } else {
            $error = "Số sao phải từ 1 đến 5!";
        }
    } else {
        $error = "Vui lòng đăng nhập để gửi đánh giá!";
    }
}

// Lấy danh sách feedback
if ($product_id <= 0) {
    $feedback_error = "<div class='alert alert-danger'>ID sản phẩm không hợp lệ.</div>";
} else {
    $stmt = $conn->prepare("
        SELECT f.rating, f.message, f.created_at, u.name
        FROM feedbacks f
        LEFT JOIN users u ON f.user_id = u.id
        WHERE f.product_id = ? AND f.status = 'approved'
        ORDER BY f.created_at DESC
    ");
    if ($stmt === false) {
        $feedback_error = "<div class='alert alert-danger'>Lỗi chuẩn bị truy vấn Feedbacks: " . htmlspecialchars($conn->error) . "</div>";
    } else {
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $feedbacks = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $feedback_error = "<div class='alert alert-danger'>Lỗi thực thi truy vấn: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

// Fetch product details using getProducts with specific product

include 'assets/getData/functions_filter.php';
$result = getProducts($conn, 1, 1, '', '', '', '', '', '', false);
$product = null;
if ($result['products'] && $result['products']->num_rows > 0) {
    while ($row = $result['products']->fetch_assoc()) {
        if ($row['product_id'] == $product_id) {
            $product = $row;
            break;
        }
    }
}

if (!$product) {
    echo "<div class='alert alert-danger'>Sản phẩm không tồn tại hoặc đã hết hàng.</div>";
    include 'includes/footer.php';
    mysqli_close($conn);
    exit;
}

// Fetch additional images and stock by color
$images = [];
$stocks_by_color = [];
$sql = "SELECT pi.image_url, pv.color_id, c.hex_code, pv.stock
        FROM product_images pi
        JOIN product_variants pv ON pi.product_id = pv.product_id AND pi.color_id = pv.color_id
        JOIN colors c ON pv.color_id = c.id
        WHERE pi.product_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo "<div class='alert alert-danger'>Lỗi chuẩn bị truy vấn hình ảnh: " . htmlspecialchars($conn->error) . "</div>";
    exit;
}
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $images[] = [
        'image_url' => $row['image_url'] ?: 'images/product/default.jpg',
        'color_id' => $row['color_id'],
        'hex_code' => $row['hex_code']
    ];
    $stocks_by_color[$row['color_id']] = $row['stock'];
}
$stmt->close();
?>

<style>
    .color-options {
        display: flex;
        justify-content: start;
        align-items: center;
        gap: 5px;
        margin: 5px 0;
        min-height: 25px;
        border: 1px solid #ccc;
        padding: 5px;
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

    .color-circle.selected {
        border: 2px solid #ff0000;
    }

    .product-card .image-container {
        position: relative;
        width: 100%;
        height: 400px;
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

    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        gap: 5px;
        justify-content: flex-end;
    }

    .star-rating input {
        display: none;
    }

    .star-rating label {
        font-size: 1.5rem;
        color: #ccc;
        cursor: pointer;
        transition: color 0.2s;
    }

    .star-rating input:checked~label,
    .star-rating label:hover,
    .star-rating label:hover~label {
        color: #ffc107;
    }

    .star-rating input:checked+label {
        color: #ffc107;
    }
</style>

<div class="w-90 mx-auto">
    <div class="d-flex justify-content-start mb-2">
        <p class="mb-0 text-dark">
            <a href="index.php" class="link text-dark text-decoration-none">Home</a> /
            <a href="product.php" class="link text-dark text-decoration-none">Products</a> /
            <?php echo htmlspecialchars($product['product_name'] ?? 'Product'); ?>
        </p>
    </div>
</div>

<div class="w-75 mx-auto">
    <?php if ($product): ?>
        <div class="row">
            <div class="col-md-6">
                <div id="productCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <div class="image-container">
                                <img src="<?php echo htmlspecialchars($product['product_image'] ?: 'images/product/aviara1.jpg'); ?>"
                                    class="card-img-top current-image"
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                    id="main-image-<?php echo $product['product_id']; ?>"
                                    data-default-image="<?php echo htmlspecialchars($product['product_image'] ?: 'images/product/aviara1.jpg'); ?>">
                            </div>
                        </div>
                        <?php foreach ($images as $index => $image): ?>
                            <div class="carousel-item">
                                <div class="image-container">
                                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>"
                                        class="d-block w-100"
                                        alt="<?php echo htmlspecialchars($product['product_name']) . ' - Color ' . $image['color_id']; ?>"
                                        data-color-id="<?php echo $image['color_id']; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <p><strong>Thương hiệu:</strong> <?php echo htmlspecialchars($product['brand_name'] ?? 'N/A'); ?></p>
                <h1 class="mb-4"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                <p class="card-text fw-bold small d-flex align-items-center gap-1">
                    <?php
                    $rating = $product['average_rating'] ?? 0;
                    for ($i = 1; $i <= 5; $i++):
                        if ($i <= floor($rating)):
                    ?>
                            <i class="bi bi-star-fill text-warning"></i>
                        <?php elseif ($i - 0.5 <= $rating): ?>
                            <i class="bi bi-star-half text-warning"></i>
                        <?php else: ?>
                            <i class="bi bi-star text-warning"></i>
                    <?php endif;
                    endfor; ?>
                    <span class="ms-1"><?php echo number_format($rating, 1); ?></span>
                </p>

                <p><strong>Mã màu:</strong></p>
                <div class="color-options mb-3">
                    <?php
                    if (!empty($images)) {
                        foreach ($images as $image):
                            $color_id = $image['color_id'];
                            $color_hex = $image['hex_code'];
                            $stock = $stocks_by_color[$color_id] ?? 0;
                    ?>
                            <div class="color-circle"
                                style="background-color: <?php echo $color_hex; ?> !important;"
                                title="Color: Color ID <?php echo $color_id; ?> (Stock: <?php echo $stock; ?>)"
                                data-image="<?php echo htmlspecialchars($image['image_url']); ?>"
                                data-product-id="<?php echo $product['product_id']; ?>"
                                data-color-id="<?php echo $color_id; ?>"
                                data-stock="<?php echo $stock; ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php } else { ?>
                        <div class='debug'>Không có màu sắc nào cho sản phẩm này.</div>
                        <input type="hidden" id="color_id" value="0">
                    <?php } ?>
                </div>
                <p><strong>Tồn kho:</strong> <span id="stock-display">Vui lòng chọn màu để xem tồn kho</span></p>
                <p><strong>Số lượng:</strong></p>
                <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" class="form-control w-25 d-inline" required>

                <p><strong>Giá:</strong></p>
                <div class="fs-1 fw-bold text-danger">
                    <?php echo number_format($product['product_price'], 0, '', '.'); ?>₫
                </div>

                <button class="btn btn-danger add-to-cart"
                    data-id="<?php echo $product['product_id']; ?>">
                    MUA NGAY
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Sản phẩm không tồn tại hoặc đã hết hàng.</div>
    <?php endif; ?>
</div>

<div class="mt-8 w-75 mx-auto">
    <h2 class="text-2xl font-semibold mb-4">Đánh Giá Sản Phẩm</h2>
    <?php if (!empty($success)): ?>
        <p class='text-green-500 mb-4'><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class='text-red-500 mb-4'><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (isset($feedback_error)): ?>
        <?php echo $feedback_error; ?>
    <?php endif; ?>

    <?php
    // Debug session chi tiết
    $session_debug = var_export($_SESSION, true);
    ?>
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
        <form method="POST">
            <div class="mb-3">
                <label for="rating" class="form-label">Số Sao</label>
                <div class="star-rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" required>
                        <label for="star<?php echo $i; ?>" class="star"><i class="bi bi-star-fill"></i></label>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Bình Luận</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" name="submit_feedback" class="btn btn-danger">Gửi Đánh Giá</button>
        </form>
    <?php else: ?>
        <p>Vui lòng <a href="login.php" class="text-blue-500">đăng nhập</a> để gửi đánh giá!</p>
    <?php endif; ?>

    <div class="mt-6">
        <?php if (empty($feedbacks)): ?>
            <p>Chưa có đánh giá nào cho sản phẩm này.</p>
        <?php else: ?>
            <?php foreach ($feedbacks as $feedback): ?>
                <div class="border-b py-4">
                    <p><strong><?php echo htmlspecialchars($feedback['name'] ?? 'Ẩn danh'); ?></strong> -
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star-fill <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-secondary'; ?>"></i>
                        <?php endfor; ?>
                    </p>
                    <p><?php echo htmlspecialchars($feedback['message']); ?></p>
                    <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($feedback['created_at']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdlZxGxvAH8bEcMQ" crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('#productCarousel');
    const mainImage = document.getElementById('main-image-<?php echo $product['product_id'] ?? 0; ?>');
    const defaultImage = mainImage ? mainImage.getAttribute('data-default-image') : '';
    const stockDisplay = document.getElementById('stock-display');
    const quantityInput = document.getElementById('quantity');
    let selectedColorId = null;

    document.querySelectorAll('.color-circle').forEach(circle => {
        circle.addEventListener('click', function() {
            selectedColorId = this.getAttribute('data-color-id');
            const imageUrl = this.getAttribute('data-image');
            const stock = parseInt(this.getAttribute('data-stock')) || 0;

            // Cập nhật giao diện màu được chọn
            document.querySelectorAll('.color-circle').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');

            // Cập nhật hình ảnh
            if (mainImage && imageUrl) {
                mainImage.src = imageUrl;
                document.querySelectorAll('#productCarousel .carousel-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.querySelector('img').getAttribute('data-color-id') === selectedColorId) {
                        item.classList.add('active');
                    }
                });
                console.log('Selected color ID:', selectedColorId, 'Image:', imageUrl);
            }

            // Cập nhật tồn kho và số lượng tối đa
            if (stockDisplay) {
                stockDisplay.textContent = stock > 0 ? `${stock} sản phẩm có sẵn` : 'Hết hàng';
            }
            if (quantityInput) {
                quantityInput.max = stock > 0 ? stock : 1;
                if (parseInt(quantityInput.value) > stock) {
                    quantityInput.value = stock > 0 ? stock : 1;
                }
            }

            // Gửi yêu cầu AJAX để lấy tồn kho mới nhất (tùy chọn)
            fetch('assets/getData/functions_filter.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=getStock&product_id=<?php echo $product['product_id']; ?>&color_id=${selectedColorId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Stock response:', data);
                if (data.status === 'success') {
                    if (stockDisplay) {
                        stockDisplay.textContent = data.stock > 0 ? `${data.stock} sản phẩm có sẵn` : 'Hết hàng';
                    }
                    if (quantityInput) {
                        quantityInput.max = data.stock > 0 ? data.stock : 1;
                        if (parseInt(quantityInput.value) > data.stock) {
                            quantityInput.value = data.stock > 0 ? data.stock : 1;
                        }
                    }
                } else {
                    console.error('Error fetching stock:', data.message);
                    if (stockDisplay) {
                        stockDisplay.textContent = 'Lỗi khi lấy tồn kho';
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                if (stockDisplay) {
                    stockDisplay.textContent = 'Lỗi khi lấy tồn kho';
                }
            });
        });

        circle.addEventListener('mouseover', function() {
            const imageUrl = this.getAttribute('data-image');
            if (mainImage && imageUrl) {
                mainImage.src = imageUrl;
                console.log('Hovering over color, new image src:', imageUrl);
            }
        });

        circle.addEventListener('mouseout', function() {
            if (mainImage && defaultImage && selectedColorId === null) {
                mainImage.src = defaultImage;
                console.log('Mouse out, reverting to default image:', defaultImage);
            }
        });
    });

    document.querySelector('.add-to-cart').addEventListener('click', function() {
        const productId = this.getAttribute('data-id');
        const quantity = document.getElementById('quantity').value;

        if (!selectedColorId || selectedColorId === '0') {
            alert('Vui lòng chọn màu sắc trước khi thêm vào giỏ hàng!');
            return;
        }

        console.log('Adding to cart:', {
            productId,
            selectedColorId,
            quantity
        });

        // Thêm vào giỏ hàng mà không kiểm tra tồn kho
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&product_id=${productId}&color_id=${selectedColorId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(cartData => {
            console.log('Response from add_to_cart:', cartData);
            if (cartData.status === 'success') {
                alert(cartData.message);
            } else {
                alert(cartData.message);
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            alert('Đã xảy ra lỗi khi thêm vào giỏ hàng!');
        });
    });

    console.log('Number of color circles:', document.querySelectorAll('.color-circle').length);
});
</script>

<?php
ob_end_flush();
include 'includes/footer.php';
mysqli_close($conn);
?>
