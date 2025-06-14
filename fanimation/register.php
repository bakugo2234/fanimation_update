<?php
require_once 'includes/db_connect.php';
include 'includes/header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>

<div class="container my-5 justify-content-center">
    <div class="form-container">
        <h2 class="mb-3 fw-bold fs-2 text-center">ĐĂNG KÝ TÀI KHOẢN</h2>
        <div id="error-message" class="alert alert-danger d-none"></div>
        <form id="register-form" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Họ và tên</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Nhập họ và tên" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Nhập email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Nhập mật khẩu" required>
            </div>
            <button type="submit" class="btn btn-danger"><span class="fw-bold fs-6">ĐĂNG KÝ</span></button>
            <p class="mt-3 text-center">Đã có tài khoản? <a href="login.php" class="text-danger">Đăng nhập</a></p>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('register-form').addEventListener('submit', function(e) {
        e.preventDefault(); // Ngăn form gửi mặc định

        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'process_register.php', true);
        xhr.onreadystatechange = function() {
            const errorMessage = document.getElementById('error-message');
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            Swal.fire({
                                title: 'Thành công!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = 'login.php'; // Chuyển hướng
                            });
                        } else {
                            errorMessage.textContent = response.message;
                            errorMessage.classList.remove('d-none');
                        }
                    } catch (e) {
                        errorMessage.textContent = 'Lỗi xử lý phản hồi từ server';
                        errorMessage.classList.remove('d-none');
                    }
                } else {
                    errorMessage.textContent = 'Lỗi server: ' + xhr.status;
                    errorMessage.classList.remove('d-none');
                }
            }
        };
        xhr.send(formData);
    });
</script>

<?php
include 'includes/footer.php';
?>
