<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
}

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
    } else {
        die("دسترسی غیرمجاز");
    }
    exit;
}

$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
$delivery_date = $_GET['delivery_date'] ?? $_POST['delivery_date'] ?? '';
$admin_id = $_SESSION['user_id'];

if (empty($user_id) || empty($delivery_date)) {
    die("خطا: شناسه کاربر یا تاریخ تحویل مشخص نشده است");
}

// اطمینان از وجود رکورد در delivery_approvals برای این تاریخ
$stmt = $db->prepare("INSERT IGNORE INTO delivery_approvals (user_id, delivery_date) VALUES (?, ?)");
$stmt->execute([$user_id, $delivery_date]);

$upload_dir = 'storage/signatures/admin/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
$admin_signature_file = $upload_dir . 'admin.png';

// پردازش POST (ذخیره امضای ادمین)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['admin_signature'])) {
    if (move_uploaded_file($_FILES['admin_signature']['tmp_name'], $admin_signature_file)) {
        $stmt = $db->prepare("UPDATE delivery_approvals SET admin_approved_at = NOW() WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $delivery_date]);
        
        echo json_encode(['success' => true, 'message' => 'تایید نهایی با موفقیت ثبت شد']);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در آپلود فایل']);
        exit;
    }
}

// استفاده از امضای قبلی ادمین
if (isset($_GET['use_admin_default'])) {
    if (file_exists($admin_signature_file)) {
        $stmt = $db->prepare("UPDATE delivery_approvals SET admin_approved_at = NOW() WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $delivery_date]);
    }
    header('Location: print.php?user_id=' . urlencode($user_id) . '&delivery_date=' . urlencode($delivery_date) . '&admin_approved=1');
    exit;
}

// حذف امضای ادمین
if (isset($_GET['delete_signature'])) {
    if (file_exists($admin_signature_file)) {
        unlink($admin_signature_file);
    }
    header('Location: admin_approve.php?user_id=' . urlencode($user_id) . '&delivery_date=' . urlencode($delivery_date));
    exit;
}

$has_admin_signature = file_exists($admin_signature_file);

$stmt = $db->prepare("SELECT fullname, unit_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_info ? $user_info['fullname'] : $user_id;
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تایید نهایی اسناد</title>
    <script defer src="assets/js/all.min.js"></script>
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>
        * { font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif; }
        body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
        .container { background: white; border-radius: 24px; padding: 30px; text-align: center; max-width: 550px; width: 100%; box-shadow: 0 20px 35px rgba(0,0,0,0.2); }
        .signature-pad { border: 2px dashed #ccc; border-radius: 16px; margin: 20px 0; padding: 20px; background: #f9fafb; }
        canvas { border: 1px solid #ddd; border-radius: 8px; cursor: crosshair; background: white; }
        button { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; margin: 8px; }
        .btn-default { background: #10b981; display: inline-block; text-decoration: none; color: white; padding: 10px 20px; border-radius: 12px; margin: 8px; }
        .btn-delete { background: #ef4444; }
        .info-box { background: #e0e7ff; padding: 12px; border-radius: 12px; margin-bottom: 15px; }
        hr { margin: 15px 0; }
        .current-signature { margin: 15px 0; padding: 10px; background: #f0fdf4; border-radius: 12px; }
        .current-signature img { max-width: 200px; max-height: 60px; margin-top: 10px; }
        .user-info { background: #f1f5f9; padding: 10px; border-radius: 12px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h2>✅ تایید نهایی اسناد</h2>
    <div class="user-info">
        <strong>نام کاربر:</strong> <?php echo htmlspecialchars($user_name); ?><br>
        <strong>تاریخ تحویل:</strong> <?php echo htmlspecialchars($delivery_date); ?>
    </div>
    
    <?php if($has_admin_signature): ?>
    <div class="current-signature">
        <i class="fas fa-save"></i> امضای فعلی شما:
        <br>
        <img src="<?php echo $admin_signature_file . '?t=' . time(); ?>" alt="امضای فعلی">
        <br>
        <a href="?delete_signature=1&user_id=<?php echo $user_id; ?>&delivery_date=<?php echo urlencode($delivery_date); ?>" class="btn-delete" style="display: inline-block; padding: 5px 12px; font-size: 0.7rem; margin-top: 8px;" onclick="return confirm('آیا از حذف امضای خود اطمینان دارید؟')">🗑️ حذف امضا</a>
    </div>
    <hr>
    <?php endif; ?>
    
    <div class="signature-pad">
        <canvas id="adminCanvas" width="450" height="200"></canvas>
        <div>
            <button onclick="clearCanvas()"><i class="fas fa-eraser"></i> پاک کردن</button>
            <button onclick="saveAdminSignature()"><i class="fas fa-save"></i> ثبت و جایگزینی امضا</button>
        </div>
    </div>
    
    <?php if($has_admin_signature): ?>
    <div class="info-box">
        <i class="fas fa-info-circle"></i> با ثبت امضای جدید، امضای قبلی شما <strong>جایگزین</strong> می‌شود.
    </div>
    <a href="?use_admin_default=1&user_id=<?php echo $user_id; ?>&delivery_date=<?php echo urlencode($delivery_date); ?>" class="btn-default" onclick="return confirm('از امضای فعلی استفاده شود؟')">✅ استفاده از امضای فعلی</a>
    <?php endif; ?>
</div>

<script>
    const canvas = document.getElementById('adminCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    
    canvas.width = 450;
    canvas.height = 200;
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#333';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);
    
    function startDrawing(e) {
        drawing = true;
        ctx.beginPath();
        const pos = getMousePos(e);
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }
    
    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const pos = getMousePos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }
    
    function stopDrawing() { drawing = false; }
    
    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        let clientX, clientY;
        if (e.touches) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }
        let x = (clientX - rect.left) * scaleX;
        let y = (clientY - rect.top) * scaleY;
        return { x: Math.min(Math.max(x, 0), canvas.width), y: Math.min(Math.max(y, 0), canvas.height) };
    }
    
    function clearCanvas() {
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#333';
    }
    
    function dataURLToBlob(dataURL) {
        const arr = dataURL.split(',');
        const mime = arr[0].match(/:(.*?);/)[1];
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while (n--) u8arr[n] = bstr.charCodeAt(n);
        return new Blob([u8arr], { type: mime });
    }
    
    function saveAdminSignature() {
        const dataURL = canvas.toDataURL('image/png');
        const formData = new FormData();
        const blob = dataURLToBlob(dataURL);
        formData.append('admin_signature', blob, 'admin_signature.png');
        formData.append('user_id', '<?php echo $user_id; ?>');
        formData.append('delivery_date', '<?php echo $delivery_date; ?>');
        
        const btn = document.querySelector('button[onclick="saveAdminSignature()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
        btn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
                window.location.href = 'print.php?user_id=<?php echo $user_id; ?>&delivery_date=<?php echo urlencode($delivery_date); ?>';
            } else {
                alert('❌ خطا: ' + data.error);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }).catch(err => {
            console.error(err);
            alert('❌ خطا در ارتباط با سرور');
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
</body>
</html>