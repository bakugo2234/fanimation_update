<?php
require_once 'includes/db_connect.php';
include 'includes/header.php';
include 'assets/getData/functions_filter.php';

// Xác định identifier dựa trên trạng thái đăng nhập
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
$session_id = !$user_id ? session_id() : null;
$identifier = $user_id ? 'user_id' : 'session_id';
$identifier_value = $user_id ?: $session_id;

error_log("Fetching cart for $identifier: $identifier_value");
$cart_query = "SELECT c.*, pv.color_id, pv.stock 
               FROM Carts c 
               LEFT JOIN product_variants pv ON c.product_variant_id = pv.id 
               WHERE c.$identifier = ?";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param('s', $identifier_value);
$stmt->execute();
$cart_result = $stmt->get_result();

$_SESSION['cart'] = [];
while ($row = $cart_result->fetch_assoc()) {
    $key = $row['product_variant_id'] . '_' . ($row['color_id'] ?? 'NULL');
    $cart_id = $row['id'] ?? null;
    error_log("Cart item: key=$key, cart_id=$cart_id, product_variant_id={$row['product_variant_id']}, color_id={$row['color_id']}, quantity={$row['quantity']}, stock={$row['stock']}");
    $_SESSION['cart'][$key] = [
        'cart_id' => $cart_id,
        'product_variant_id' => $row['product_variant_id'],
        'color' => $row['color_id'] ? getColorHex($row['color_id']) : 'No color',
        'quantity' => $row['quantity'],
        'stock' => $row['stock'] ?? 0
    ];
}

function getColorHex($color_id)
{
    global $conn;
    $query = "SELECT hex_code FROM Colors WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $color_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['hex_code'] ?? '#000000';
}
?>

<div class="container mt-4">
    <h2 class="text-center">Giỏ hàng</h2>
    <?php if (empty($_SESSION['cart'])): ?>
        <p class="text-center">Giỏ hàng của bạn đang trống.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>Màu</th>
                    <th>Số lượng</th>
                    <th>Tổng</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total = 0;
                foreach ($_SESSION['cart'] as $key => $item):
                    $cart_id = $item['cart_id'];
                    $product_variant_id = $item['product_variant_id'];
                    $color = $item['color'];
                    $quantity = $item['quantity'];
                    $stock = $item['stock'];

                    // Lấy thông tin sản phẩm từ product_variants và products
                    $variant_query = "SELECT pv.product_id, p.name, p.price 
                                    FROM product_variants pv 
                                    JOIN products p ON pv.product_id = p.id 
                                    WHERE pv.id = ?";
                    $stmt = $conn->prepare($variant_query);
                    $stmt->bind_param('i', $product_variant_id);
                    $stmt->execute();
                    $variant = $stmt->get_result()->fetch_assoc();

                    if (!$variant) {
                        unset($_SESSION['cart'][$key]);
                        continue;
                    }

                    $price = floatval($variant["price"]);
                    $subtotal = $price * $quantity;
                    $total += $subtotal;
                ?>
                    <tr data-cart-id="<?php echo $cart_id; ?>" data-stock="<?php echo $stock; ?>">
                        <td><?= htmlspecialchars($variant['name']); ?></td>
                        <td style="background-color: <?= htmlspecialchars($color); ?>;"></td>
                        <td>
                            <button class="btn btn-sm btn-primary decrease-btn" data-cart-id="<?php echo $cart_id; ?>" <?php echo $quantity <= 1 ? 'disabled' : ''; ?>>-</button>
                            <span id="qty-<?php echo $cart_id; ?>"><?= intval($quantity); ?></span>
                            <button class="btn btn-sm btn-primary increase-btn" data-cart-id="<?php echo $cart_id; ?>" data-stock="<?php echo $stock; ?>">+</button>
                        </td>
                        <td id="subtotal-<?php echo $cart_id; ?>"><?= number_format($subtotal, 0, '', '.'); ?> VND</td>
                        <td>
                            <button class="btn btn-danger remove-btn" data-cart-id="<?php echo $cart_id; ?>">Xóa</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3"><b>Tổng cộng:</b></td>
                    <td id="total"><?= number_format($total, 0, '', '.'); ?> VND</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <a href="checkout.php?items=<?php echo implode(',', array_column($_SESSION['cart'], 'cart_id')); ?>" class="btn btn-success">Thanh toán</a>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Tăng số lượng
        $('.increase-btn').click(function() {
            var cart_id = $(this).data('cart-id');
            var stock = $(this).data('stock');
            var qtyElement = $('#qty-' + cart_id);
            var currentQty = parseInt(qtyElement.text());

            if (currentQty >= stock) {
                Swal.fire({
                    title: 'Lỗi',
                    text: 'Số lượng đã đạt giới hạn tồn kho (' + stock + ')!',
                    icon: 'error',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
                return;
            }

            updateCart(cart_id, 'increase');
        });

        // Giảm số lượng
        $('.decrease-btn').click(function() {
            var cart_id = $(this).data('cart-id');
            updateCart(cart_id, 'decrease');
        });

        // Xóa sản phẩm
        $('.remove-btn').click(function() {
            var cart_id = $(this).data('cart-id');
            Swal.fire({
                title: 'Bạn có chắc chắn?',
                text: 'Bạn có chắc chắn muốn xóa sản phẩm này khỏi giỏ hàng?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Xác nhận',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    removeCart(cart_id);
                }
            });
        });

        function updateCart(cart_id, action) {
            $.ajax({
                url: 'add_to_cart.php',
                method: 'POST',
                data: {
                    action: action,
                    cart_id: cart_id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload(); // Tải lại trang để cập nhật giỏ hàng
                    } else {
                        alert(response.message || 'Cập nhật thất bại');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    alert('Lỗi khi gửi yêu cầu: ' + (xhr.responseText || error));
                }
            });
        }

        function removeCart(cart_id) {
            $.ajax({
                url: 'add_to_cart.php',
                method: 'POST',
                data: {
                    action: 'remove',
                    cart_id: cart_id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload(); // Tải lại trang để cập nhật giỏ hàng
                        Swal.fire({
                            title: 'Thành công!',
                            text: response.message,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            title: 'Lỗi',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d33'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    Swal.fire({
                        title: 'Lỗi',
                        text: 'Lỗi khi gửi yêu cầu: ' + (xhr.responseText || error),
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                }
            });
        }
    });
</script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
