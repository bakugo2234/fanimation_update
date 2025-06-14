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
