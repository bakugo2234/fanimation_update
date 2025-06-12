<?php
require_once 'includes/db_connect.php';

// Get filtered products with pagination
function getProducts($conn, $records_per_page, $page, $search, $category, $min_price, $max_price, $color = '', $brand = '', $five_star_only = false)
{
    // Kiểm tra trạng thái kết nối
    if (!$conn->ping()) {
        error_log("Kết nối cơ sở dữ liệu đã bị đóng trong getProducts.");
        return ['products' => null, 'total_pages' => 0, 'total_records' => 0];
    }

    $start_from = ($page - 1) * $records_per_page;

    $conditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR c.name LIKE ? OR b.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    // Chỉ áp dụng điều kiện category nếu không có tìm kiếm
    if (!empty($category) && empty($search)) {
        $conditions[] = "p.category_id = ?";
        $params[] = $category;
        $types .= 'i';
    }

    if ($min_price !== '') {
        $conditions[] = "p.price >= ?";
        $params[] = $min_price;
        $types .= 'd';
    }

    if ($max_price !== '') {
        $conditions[] = "p.price <= ?";
        $params[] = $max_price;
        $types .= 'd';
    }

    if (!empty($color)) {
        $conditions[] = "pv.color_id = ?";
        $params[] = $color;
        $types .= 'i';
    }

    if (!empty($brand)) {
        $conditions[] = "p.brand_id = ?";
        $params[] = $brand;
        $types .= 'i';
    }

    $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

    // Xây dựng mệnh đề HAVING nếu $five_star_only được bật
    $having_clause = $five_star_only ? " HAVING AVG(f.rating) = 5" : "";

    // Get total records
    $sql_total = "SELECT COUNT(DISTINCT p.id) as total FROM products p
                  LEFT JOIN product_variants pv ON p.id = pv.product_id
                  LEFT JOIN brands b ON p.brand_id = b.id
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN feedbacks f ON p.id = f.product_id
                  " . $where_clause;

    $stmt_total = $conn->prepare($sql_total);
    if ($stmt_total === false) {
        error_log("Lỗi chuẩn bị truy vấn total: " . $conn->error);
        return ['products' => null, 'total_pages' => 0, 'total_records' => 0];
    }

    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_records = $result_total->fetch_assoc()['total'] ?? 0;
    $stmt_total->close();

    $total_pages = $total_records > 0 ? ceil($total_records / $records_per_page) : 0;

    // Get records for current page with improved image handling
    $sql = "SELECT p.id AS product_id, p.name AS product_name, p.price AS product_price, 
            GROUP_CONCAT(DISTINCT c.hex_code) AS colour_hex_code, 
            COALESCE((SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.u_primary = 1 LIMIT 1), 
                     (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id LIMIT 1)) AS product_image,
            AVG(f.rating) AS average_rating,
            cat.name AS category_name,
            b.name AS brand_name,
            pv.stock
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN colors c ON pv.color_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.u_primary = 1
            LEFT JOIN feedbacks f ON p.id = f.product_id
            " . $where_clause . "
            GROUP BY p.id, p.name, p.price, cat.name, pv.stock
            " . $having_clause . "
            ORDER BY p.id
            LIMIT ?, ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn chính: " . $conn->error);
        return ['products' => null, 'total_pages' => 0, 'total_records' => 0];
    }

    $params[] = $start_from;
    $params[] = $records_per_page;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return [
        'products' => $result,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ];
}

// Get distinct categories for filter dropdown
function getCategories($conn)
{
    if (!$conn->ping()) {
        error_log("Kết nối cơ sở dữ liệu đã bị đóng trong getCategories.");
        return [];
    }

    $categories = [];
    $sql = "SELECT DISTINCT name AS category FROM categories ORDER BY name";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Lỗi truy vấn getCategories: " . $conn->error);
        return [];
    }
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
    $result->free();
    return $categories;
}

// Get distinct colors for filter dropdown
function getColors($conn)
{
    if (!$conn->ping()) {
        error_log("Kết nối cơ sở dữ liệu đã bị đóng trong getColors.");
        return [];
    }

    $colors = [];
    $sql = "SELECT DISTINCT id, hex_code AS color FROM colors ORDER BY hex_code";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Lỗi truy vấn getColors: " . $conn->error);
        return [];
    }
    while ($row = $result->fetch_assoc()) {
        $colors[$row['id']] = $row['color'];
    }
    $result->free();
    return $colors;
}

// Get distinct brands for filter dropdown
function getBrands($conn)
{
    if (!$conn->ping()) {
        error_log("Kết nối cơ sở dữ liệu đã bị đóng trong getBrands.");
        return [];
    }

    $brands = [];
    $sql = "SELECT DISTINCT id, name AS brand FROM brands ORDER BY name";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Lỗi truy vấn getBrands: " . $conn->error);
        return [];
    }
    while ($row = $result->fetch_assoc()) {
        $brands[$row['id']] = $row['brand'];
    }
    $result->free();
    return $brands;
}

// New function to get image by color
function getImageByColor($conn, $product_id, $color_id)
{
    if (!$conn->ping()) {
        error_log("Kết nối cơ sở dữ liệu đã bị đóng trong getImageByColor.");
        return null;
    }

    $sql = "SELECT image_url FROM product_images WHERE product_id = ? AND color_id = ? AND u_primary = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn getImageByColor: " . $conn->error);
        return null;
    }
    $stmt->bind_param("ii", $product_id, $color_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $image = $result->fetch_assoc();
    $stmt->close();
    return $image ? $image['image_url'] : null;
}

// Check stock availability
$action = $_POST['action'] ?? '';

if ($action === 'getStock' || $action === 'checkStock') {
    ob_clean(); // Xóa mọi đầu ra trước đó
    header('Content-Type: application/json');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $color_id = isset($_POST['color_id']) ? intval($_POST['color_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    error_log("Received POST data: " . json_encode($_POST));
    $result = checkStockAvailability($product_id, $color_id, $quantity, $conn);
    echo json_encode($result);
    exit;
}

function checkStockAvailability($product_id, $color_id, $quantity, $conn)
{
    error_log("Checking stock: product_id=$product_id, color_id=$color_id, quantity=$quantity");
    error_log("Executing stock query: SELECT stock FROM product_variants WHERE id = $product_id AND color_id = $color_id");
    if (!$conn->ping()) {
        error_log("Database connection closed in checkStockAvailability.");
        return ['status' => 'error', 'message' => 'Lỗi kết nối cơ sở dữ liệu', 'stock' => 0];
    }

    if (!$product_id || !$color_id || $color_id === 0) {
        error_log("Invalid input: product_id=$product_id, color_id=$color_id");
        return ['status' => 'error', 'message' => 'Dữ liệu đầu vào không hợp lệ', 'stock' => 0];
    }

    // Kiểm tra product_id và color_id
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for product check: " . $conn->error);
        return ['status' => 'error', 'message' => 'Lỗi kiểm tra sản phẩm', 'stock' => 0];
    }
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product_exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
    $stmt->close();

    if (!$product_exists) {
        error_log("Product not found: product_id=$product_id");
        return ['status' => 'error', 'message' => 'Sản phẩm không tồn tại', 'stock' => 0];
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM colors WHERE id = ?");
    if ($stmt === false) {
        error_log("Prepare failed for color check: " . $conn->error);
        return ['status' => 'error', 'message' => 'Lỗi kiểm tra màu', 'stock' => 0];
    }
    $stmt->bind_param('i', $color_id);
    $stmt->execute();
    $color_exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
    $stmt->close();

    if (!$color_exists) {
        error_log("Color not found: color_id=$color_id");
        return ['status' => 'error', 'message' => 'Màu không tồn tại', 'stock' => 0];
    }

    // Update the query to match the correct column name
    $stmt = $conn->prepare("SELECT stock FROM product_variants WHERE product_id = ? AND color_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed in checkStockAvailability: " . $conn->error);
        return ['status' => 'error', 'message' => 'Lỗi chuẩn bị truy vấn stock: ' . $conn->error, 'stock' => 0];
    }

    $stmt->bind_param('ii', $product_id, $color_id);
    if (!$stmt->execute()) {
        error_log("Execute failed in checkStockAvailability: " . $stmt->error);
        $stmt->close();
        return ['status' => 'error', 'message' => 'Lỗi thực thi truy vấn stock: ' . $stmt->error, 'stock' => 0];
    }

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result) {
        error_log("No stock data found for product_id=$product_id, color_id=$color_id");
        return ['status' => 'error', 'message' => 'Màu không tồn tại hoặc không có stock', 'stock' => 0];
    }

    error_log("Stock check success: available stock = " . $result['stock']);
    return ['status' => 'success', 'stock' => (int)$result['stock']];
}
