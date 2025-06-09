<?php
require_once 'includes/db_connect.php';
include 'includes/header.php';

// Nếu đã đăng nhập, chuyển hướng đến cart.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>


<div class="container my-5 justify-content-center">
    <div class="form-container">
        <h2 class="mb-3 fw-bold fs-2 text-center">ĐĂNG NHẬP</h2>
        <div id="error-message" class="alert alert-danger d-none"></div>
        <form id="login-form" method="POST">
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Nhập email của bạn" required>
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu của bạn" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="form-check-label">
                    <input type="checkbox" name="remember" class="form-check-input"> Ghi nhớ tôi
                </label>
                <p class="mb-0">Chưa có tài khoản? <a href="register.php" class="text-danger">Đăng ký</a></p>
            </div>
            <button type="submit" class="btn btn-danger"><span class="fw-bold fs-6">ĐĂNG NHẬP</span></button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault(); // Ngăn form gửi mặc định

        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'process_login.php', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                const errorMessage = document.getElementById('error-message');
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Sử dụng SweetAlert2 thay vì alert
                            Swal.fire({
                                title: 'Thành công!',
                                text: response.message,
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'index.php'; // Chuyển hướng
                            });
                        } else {
                            errorMessage.textContent = response.message;
                            errorMessage.classList.remove('d-none');
                        }
                    } catch (e) {
                        console.error('Lỗi phân tích JSON:', e, 'Phản hồi:', xhr.responseText);
                        errorMessage.textContent = 'Lỗi hệ thống, vui lòng thử lại sau.';
                        errorMessage.classList.remove('d-none');
                    }
                } else {
                    console.error('Lỗi server:', xhr.status, xhr.responseText);
                    errorMessage.textContent = 'Lỗi kết nối server (mã ' + xhr.status + ').';
                    errorMessage.classList.remove('d-none');
                }
            }
        };
        xhr.send(formData);
    });
</script>

<?php if (!empty($error)): ?>
    <p style="color:red;"><?= $error ?></p>
<?php endif; ?>



<?php
mysqli_close($conn);
include 'includes/footer.php';
?>