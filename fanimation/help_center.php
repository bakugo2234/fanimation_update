<?php
require_once 'includes/db_connect.php';
include 'includes/header.php';

$error_messages = []; // Mảng lưu trữ thông báo lỗi
$success = '';
$user_data = []; // Lưu thông tin người dùng đã đăng nhập

// Lấy thông tin người dùng nếu đã đăng nhập
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT name, email, phone, address FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $product_name = !empty($_POST['product_name']) ? mysqli_real_escape_string($conn, $_POST['product_name']) : null;
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Kiểm tra định dạng số điện thoại Việt Nam
    $phonePattern = '/^(0[35789][0-9]{8,9})$/';
    if (!preg_match($phonePattern, $phone)) {
        $error_messages[] = "Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại Việt Nam (10 hoặc 11 chữ số, bắt đầu bằng 03, 05, 07, 08, hoặc 09).";
    }

    // Kiểm tra định dạng email
    $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    if ($email && !preg_match($emailPattern, $email)) { // Chỉ kiểm tra nếu email không rỗng
        $error_messages[] = "Email không hợp lệ. Vui lòng nhập đúng định dạng email (ví dụ: example@domain.com).";
    }

    // Kiểm tra các trường bắt buộc
    if (!$name || !$email || !$phone || !$address || !$description) {
        $error_messages[] = "Vui lòng điền đầy đủ các trường bắt buộc!";
    }

    // Kiểm tra email trùng lặp
    if (empty($error_messages)) {
        $check_email_query = "SELECT email FROM contacts WHERE email = ?";
        $stmt = mysqli_prepare($conn, $check_email_query);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error_messages[] = "Email đã được sử dụng. Vui lòng chọn email khác.";
        }
        mysqli_stmt_close($stmt);
    }

    // Kiểm tra user_id hợp lệ
    if ($user_id && empty($error_messages)) {
        $check_user_query = "SELECT id FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_user_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) == 0) {
            $error_messages[] = "ID người dùng không hợp lệ.";
        }
        mysqli_stmt_close($stmt);
    }

    // Xử lý file upload
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $file_name = basename($file['name']);
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = $file['type'];

        $valid_extensions = ['image/jpeg', 'image/gif', 'image/png', 'application/pdf', 'video/mp4', 'video/heic', 'video/hevc'];
        $max_size = 39 * 1024 * 1024;

        if (!in_array($file_type, $valid_extensions)) {
            $error_messages[] = "Loại file không được hỗ trợ!";
        } elseif ($file_size > $max_size) {
            $error_messages[] = "Kích thước file vượt quá 39MB!";
        } else {
            $upload_dir = 'uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_path = $upload_dir . uniqid() . '_' . $file_name;
            if (!move_uploaded_file($file_tmp, $file_path)) {
                $error_messages[] = "Lỗi khi di chuyển file!";
            }
        }
    }

    // Ghi log để debug
    error_log("Error messages: " . print_r($error_messages, true));

    // Nếu không có lỗi, thực hiện lưu vào database
    if (empty($error_messages)) {
        $query = "INSERT INTO contacts (name, email, user_id, phone, address, product_name, file_path, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssisssss", $name, $email, $user_id, $phone, $address, $product_name, $file_path, $description);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Thành công!',
                            text: 'Phản hồi của bạn đã được gửi thành công!',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.querySelector('form').reset();
                                window.location.href = 'index.php';
                            }
                        });
                    });
                </script>";
                mysqli_stmt_close($stmt);
                exit;
            } else {
                $error_messages[] = "Lỗi khi gửi phản hồi: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_messages[] = "Lỗi khi chuẩn bị câu truy vấn: " . mysqli_error($conn);
        }
    }
}
?>

<!-- HTML và CSS giữ nguyên -->
<style>
    .icon { color: rgb(133, 55, 167); }
    .required::after { content: " *"; color: #a94442; }
    .search-container { position: relative; }
    #searchInput { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
    #suggestions { display: none; position: absolute; width: 100%; border: 1px solid #ccc; background-color: #fff; max-height: 150px; overflow-y: auto; z-index: 1; }
    #suggestions div { padding: 8px; cursor: pointer; }
    #suggestions div:hover { background-color: #f0f0f0; }
    .drag-area { border: 2px dashed #ccc; height: auto; width: 100%; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-direction: column; padding: 20px; background-color: #f9f9f9; color: #666; position: relative; overflow: hidden; }
    .drag-area.active { border: 2px solid #007bff; background-color: #e9f0f8; }
    .drag-area .preview { width: 100%; height: auto; display: flex; justify-content: center; align-items: center; }
    .drag-area .preview img { max-width: 100%; max-height: 200px; object-fit: contain; }
    .drag-area .drag-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
    .drag-area .drag-text.hidden { display: none; }
    .bttsubmit { background-color: #922492; border: none; padding: 0.7rem; width: 6rem; border-radius: 3px; font-weight: bold; color: #fff; opacity: 1; cursor: pointer; }
    .bttsubmit:hover { background-color: #520a52; }
    .error { color: red; }
</style>

<div id="about-us">
    <div id="contactCarousel" class="carousel slide">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img src="images/another/contact_us.jpg" alt="Contact Us Image" class="d-block w-100">
                <div class="carousel-content">
                    <h1 class="">Contact</h1>
                </div>
            </div>
        </div>
    </div>
    <div class="justify-items-center mx-auto w-75 mt-3">
        <p class="fs-2 fw-semibold text-center">Learn more about us</p>
        <p class="fs-5 fw-normal">Fanimation strives hard to be environmentally friendly. We encourage you to browse our products online, which includes all the latest information on our great products and styles. If you are in need of additional information not found on our web site or would just like to learn more about the company in general, please contact us by any of the following methods or simply fill out our request information form below. For product and shipping issues please fill out our product support form.</p>
    </div>

    <div class="container my-5 mx-auto w-75">
        <div class="row g-4">
            <div class="col-md-4 text-start">
                <i class="icon bi bi-geo-alt fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Location</h5>
                    <p class="mb-0">10983 Bennett Parkway</p>
                    <p class="mb-0">Zionsville, IN 46077</p>
                    <p class="mb-0">Phone: 888.567.2055</p>
                    <p>Fax: 866.482.5215</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-card-list fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Product Support</h5>
                    <p>Every Fanimation fan is backed by our firm commitment to quality materials and manufacturing.</p>
                    <p class="fw-bold">Get product support</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-file-earmark-text fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Marketing</h5>
                    <p>If you need additional marketing materials that aren't presented in our press room or have other marketing and public relations related questions, please contact:</p>
                    <p class="fw-bold">press@fanimation.com</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-chat-dots fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Suggestions</h5>
                    <p>Fanimation wants to enhance your experience. If you have suggestions on how we can better serve you, please contact:</p>
                    <p class="fw-bold">suggestions@fanimation.com</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-send-fill fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Find a Sales Agent</h5>
                    <p>Fanimation works with sales agents throughout the United States and worldwide to assist you with selling our product.</p>
                    <p class="fw-bold">Find your agent</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-person-circle fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">Careers</h5>
                    <p>Find something on our website that is not working the way it should? Contact us so that we can improve your experience on our website:</p>
                    <p class="fw-bold">careers@fanimation.com</p>
                </div>
            </div>
            <div class="col-md-4 text-start">
                <i class="icon bi bi-pc-display-horizontal fs-2 mb-2"></i>
                <div class="text-start">
                    <h5 class="fw-bold text-uppercase">WEBMASTER</h5>
                    <p>Interested in working at Fanimation? Email your resume to:</p>
                    <p class="fw-bold">webmaster@fanimation.com</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="contact-tech" class="bg-light">
    <div class="justify-items-center mx-auto w-75 mt-3">
        <p class="fs-2 fw-semibold text-center">Questions? Contact tech support</p>
    </div>
    <div class="container">
        <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <div class="row mb-3">
                <div class="col">
                    <label class="required">Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($user_data['name']) ? htmlspecialchars($user_data['name']) : ''); ?>" required>
                </div>
                <div class="col">
                    <label class="required">Phone number</label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : (isset($user_data['phone']) ? htmlspecialchars($user_data['phone']) : ''); ?>" required>
                </div>
                <div class="col">
                    <label class="required">Email address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($user_data['email']) ? htmlspecialchars($user_data['email']) : ''); ?>" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label class="required">Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Street address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : (isset($user_data['address']) ? htmlspecialchars($user_data['address']) : ''); ?>" required>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label>Product name</label>
                    <input type="text" name="product_name" class="form-control" value="<?php echo isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : ''; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col">
                    <label class="required">Upload photo/video of fan</label>
                    <div class="drag-area" id="dragArea">
                        <div class="drag-text">
                            <span>Drop files here or</span>
                            <button type="button" class="btn btn-primary" onclick="triggerFileInput()">Select files</button>
                        </div>
                        <div class="preview"></div>
                        <input type="file" name="file" class="form-control-file" accept="image/jpeg,image/gif,image/png,application/pdf,video/mp4,video/heic,video/hevc" multiple style="display: none;" id="fileInput" onchange="handleFileSelect(event)">
                    </div>
                    <small class="text-muted">Accepted file types: jpg, gif, png, pdf, mp4, heif, hevc, Max. file size: 39 MB, Max. files: 4.</small>
                </div>
            </div>
            <div class="row mb-3 problem-description">
                <div class="col">
                    <label class="required">Description of problem</label>
                    <textarea name="description" class="form-control" maxlength="280" placeholder="Accident! Full description of problem" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <small class="char-count">0 of 280 max characters</small>
                </div>
            </div>
            <button type="submit" class="bttsubmit">Submit</button>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/izitoast/1.4.0/js/iziToast.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/izitoast/1.4.0/css/iziToast.min.css" />

<script>
// Hiển thị thông báo lỗi từ PHP bằng iToast
<?php if (!empty($error_messages)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($error_messages as $error): ?>
            iziToast.error({
                title: 'Lỗi',
                message: '<?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>',
                position: 'topRight'
            });
        <?php endforeach; ?>
    });
<?php endif; ?>

// Trigger file input
function triggerFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.click();
    } else {
        console.error('File input not found!');
    }
}

// Handle file selection
function handleFileSelect(event) {
    const file = event.target.files[0];
    const dropArea = document.getElementById('dragArea');
    const dragText = dropArea.querySelector('.drag-text');
    const preview = dropArea.querySelector('.preview');

    if (file) {
        dropArea.classList.add('active');
        dragText.classList.add('hidden');
        let fileReader = new FileReader();
        fileReader.onload = () => {
            let fileURL = fileReader.result;
            preview.innerHTML = `<img src="${fileURL}" alt="uploaded file">`;
        };
        fileReader.readAsDataURL(file);
    }
}

// Validate form
function validateForm() {
    const requiredFields = document.querySelectorAll('.required + input, .required + textarea');
    const phoneInput = document.querySelector("input[name='phone']");
    const emailInput = document.querySelector("input[name='email']");
    const phonePattern = /^(0[35789][0-9]{8,9})$/;
    const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    let isValid = true;

    // Kiểm tra các trường bắt buộc
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            iziToast.error({
                title: 'Lỗi',
                message: `Vui lòng điền đầy đủ ${field.name === 'description' ? 'mô tả sự cố' : field.name}!`,
                position: 'topRight'
            });
            isValid = false;
        }
    });

    // Kiểm tra định dạng số điện thoại
    if (phoneInput.value && !phonePattern.test(phoneInput.value)) {
        iziToast.error({
            title: 'Lỗi',
            message: 'Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại Việt Nam (10 hoặc 11 chữ số, bắt đầu bằng 03, 05, 07, 08, hoặc 09).',
            position: 'topRight'
        });
        isValid = false;
    }

    // Kiểm tra định dạng email
    if (emailInput.value && !emailPattern.test(emailInput.value)) {
        iziToast.error({
            title: 'Lỗi',
            message: 'Email không hợp lệ. Vui lòng nhập đúng định dạng email (ví dụ: example@domain.com).',
            position: 'topRight'
        });
        isValid = false;
    }

    return isValid;
}

// Drag and drop file handling
const dropArea = document.getElementById('dragArea');
dropArea.addEventListener('dragover', (event) => {
    event.preventDefault();
    dropArea.classList.add('active');
});
dropArea.addEventListener('dragleave', () => {
    dropArea.classList.remove('active');
});
dropArea.addEventListener('drop', (event) => {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    const dragText = dropArea.querySelector('.drag-text');
    const preview = dropArea.querySelector('.preview');

    if (file) {
        dropArea.classList.add('active');
        dragText.classList.add('hidden');
        let fileReader = new FileReader();
        fileReader.onload = () => {
            let fileURL = fileReader.result;
            preview.innerHTML = `<img src="${fileURL}" alt="uploaded file">`;
            document.getElementById('fileInput').files = event.dataTransfer.files;
        };
        fileReader.readAsDataURL(file);
    }
});

// Character count for description
const textarea = document.querySelector(".problem-description textarea");
const charCount = document.querySelector(".problem-description .char-count");
textarea.addEventListener("input", function() {
    const maxLength = this.maxLength;
    const currentLength = this.value.length;
    charCount.textContent = `${currentLength} of ${maxLength} max characters`;
});
</script>
<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
