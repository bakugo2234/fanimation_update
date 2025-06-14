<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'assets/getData/functions_filter.php';

header('Content-Type: application/json');

error_log("Starting add_to_cart.php - " . date('Y-m-d H:i:s'));
error_log("Received POST data: " . print_r($_POST, true));

// Xác định identifier dựa trên trạng thái đăng nhập
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
$session_id = !$user_id ? session_id() : null;

$action = isset($_POST['action']) ? $_POST['action'] : 'add';

if ($action === 'add') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $color_id = isset($_POST['color_id']) ? intval($_POST['color_id']) : null;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    error_log("Add product: user_id=$user_id, session_id=$session_id, product_id=$product_id, color_id=" . ($color_id ?? 'null') . ", quantity=$quantity");

    if ($product_id <= 0 || $quantity < 1) {
        error_log("Invalid product data: product_id=$product_id, quantity=$quantity");
        echo json_encode(['status' => 'error', 'message' => 'Thông tin sản phẩm không hợp lệ']);
        exit;
    }

    if (!$conn) {
        error_log("Connection failed: No valid database connection");
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu']);
        exit;
    }

    // Tìm product_variant_id dựa trên product_id và color_id
    $variant_query = "SELECT id FROM product_variants WHERE product_id = ? AND color_id = ?";
    $stmt_variant = $conn->prepare($variant_query);
    if (!$stmt_variant) {
        error_log("Prepare variant query failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit;
    }
    $stmt_variant->bind_param('ii', $product_id, $color_id);
    $stmt_variant->execute();
    $result_variant = $stmt_variant->get_result();
    $variant = $result_variant->fetch_assoc();
    $stmt_variant->close();

    if (!$variant) {
        error_log("No variant found for product_id=$product_id, color_id=$color_id");
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy biến thể sản phẩm']);
        exit;
    }

    $product_variant_id = $variant['id'];

    // Thêm sản phẩm vào giỏ hàng
    $insert_query = "
        INSERT INTO Carts (user_id, session_id, product_variant_id, quantity) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE quantity = quantity + ?
    ";
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        error_log("Prepare insert query failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('issii', $user_id, $session_id, $product_variant_id, $quantity, $quantity);
    if ($stmt->execute()) {
        error_log("Insert successful: user_id=$user_id, session_id=$session_id, product_variant_id=$product_variant_id, quantity=$quantity");
        echo json_encode(['status' => 'success', 'message' => 'Đã thêm vào giỏ hàng!']);
    } else {
        error_log("Insert failed: " . $stmt->error . " (Query: $insert_query with params: user_id=$user_id, session_id=$session_id, product_variant_id=$product_variant_id, quantity=$quantity)");
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm vào giỏ hàng: ' . $stmt->error]);
    }
    $stmt->close();
} elseif ($action === 'increase' || $action === 'decrease') {
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
    $delta = $action === 'increase' ? 1 : -1;

    error_log("Update cart: cart_id=$cart_id, delta=$delta");

    if ($cart_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Thông tin không hợp lệ']);
        exit;
    }

    $select_query = "SELECT quantity FROM Carts WHERE id = ? AND (user_id = ? OR session_id = ?)";
    $stmt = $conn->prepare($select_query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('iss', $cart_id, $user_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy mục trong giỏ hàng']);
        exit;
    }

    $current_quantity = $result['quantity'];
    $new_quantity = $current_quantity + $delta;

    if ($new_quantity <= 0) {
        $delete_query = "DELETE FROM Carts WHERE id = ? AND (user_id = ? OR session_id = ?)";
        $stmt = $conn->prepare($delete_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('iss', $cart_id, $user_id, $session_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!']);
        } else {
            error_log("Delete failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa sản phẩm: ' . $stmt->error]);
        }
    } else {
        $update_query = "UPDATE Carts SET quantity = ? WHERE id = ? AND (user_id = ? OR session_id = ?)";
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('iiss', $new_quantity, $cart_id, $user_id, $session_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Đã cập nhật số lượng!']);
        } else {
            error_log("Update failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật số lượng: ' . $stmt->error]);
        }
    }
    $stmt->close();
} elseif ($action === 'remove') {
    $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;

    error_log("Remove cart: cart_id=$cart_id");

    if ($cart_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Thông tin không hợp lệ']);
        exit;
    }

    $delete_query = "DELETE FROM Carts WHERE id = ? AND (user_id = ? OR session_id = ?)";
    $stmt = $conn->prepare($delete_query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('iss', $cart_id, $user_id, $session_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng!']);
    } else {
        error_log("Delete failed: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa sản phẩm: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ']);
}

if (isset($conn)) {
    $conn->close();
}
?>
