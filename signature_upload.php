<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$delivery_date = isset($_GET['delivery_date']) ? $_GET['delivery_date'] : '';

if (empty($delivery_date)) {
    die("تاریخ تحویل مشخص نشده است");
}

$upload_dir = 'storage/signatures/users/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// ========== اضافه کن: بررسی اینکه ادمین قبلاً تایید نکرده باشد ==========
$stmt = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
$stmt->execute([$user_id, $delivery_date]);
$admin_approved = $stmt->fetchColumn();

if ($admin_approved) {
    header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&error=locked');
    exit;
}

// پردازش ذخیره امضا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signature_data'])) {
    $signature_data = $_POST['signature_data'];
    $signature_data = preg_replace('/^data:image\/\w+;base64,/', '', $signature_data);
    $signature_data = str_replace(' ', '+', $signature_data);
    $image_data = base64_decode($signature_data);
    
    $filename = $user_id . '_' . str_replace('/', '-', $delivery_date) . '.png';
    $upload_path = $upload_dir . $filename;
    
    if (file_put_contents($upload_path, $image_data)) {
        // ========== اصلاح: استفاده از ON DUPLICATE KEY UPDATE ==========
        $stmt = $db->prepare("INSERT INTO delivery_approvals (user_id, delivery_date, user_approved_at) 
                              VALUES (?, ?, NOW()) 
                              ON DUPLICATE KEY UPDATE user_approved_at = NOW(), delivery_date = ?");
        $stmt->execute([$user_id, $delivery_date, $delivery_date]);
        
        // ذخیره به عنوان امضای پیش‌فرض
        if (isset($_POST['save_as_default'])) {
            $default_path = $upload_dir . $user_id . '_default.png';
            copy($upload_path, $default_path);
            
            $stmt = $db->prepare("INSERT INTO saved_signatures (user_id, signature_path, is_default) 
                                  VALUES (?, ?, 1) 
                                  ON DUPLICATE KEY UPDATE signature_path = ?, is_default = 1");
            $stmt->execute([$user_id, $default_path, $default_path]);
        }
        
        header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&success=1');
        exit;
    }
    
    header('Location: signature_upload.php?delivery_date=' . urlencode($delivery_date) . '&error=1');
    exit;
}

// استفاده از امضای قبلی
if (isset($_GET['use_default'])) {
    $default_signature_file = $upload_dir . $user_id . '_default.png';
    if (file_exists($default_signature_file)) {
        $filename = $user_id . '_' . str_replace('/', '-', $delivery_date) . '.png';
        $upload_path = $upload_dir . $filename;
        copy($default_signature_file, $upload_path);
        
        $stmt = $db->prepare("INSERT INTO delivery_approvals (user_id, delivery_date, user_approved_at) 
                              VALUES (?, ?, NOW()) 
                              ON DUPLICATE KEY UPDATE user_approved_at = NOW(), delivery_date = ?");
        $stmt->execute([$user_id, $delivery_date, $delivery_date]);
    }
    header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&signature=1');
    exit;
}

$has_default_signature = file_exists($upload_dir . $user_id . '_default.png');
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>ثبت امضای تحویل‌دهنده</title>
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
        .save-option { margin: 15px 0; text-align: right; background: #f0fdf4; padding: 12px; border-radius: 12px; }
        .info-box { background: #e0e7ff; padding: 12px; border-radius: 12px; margin-bottom: 15px; }
        hr { margin: 15px 0; }
        .error-box { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h2>✍️ ثبت امضای تحویل‌دهنده</h2>
    <p>تاریخ تحویل: <strong><?php echo htmlspecialchars($delivery_date); ?></strong></p>
    
    <?php if(isset($_GET['error'])): ?>
        <div class="error-box">❌ خطا در ذخیره امضا. لطفاً مجدداً تلاش کنید.</div>
    <?php endif; ?>
    
    <form method="POST" id="signatureForm">
        <input type="hidden" name="signature_data" id="signature_data">
        <input type="hidden" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date); ?>">
        <input type="hidden" name="save_as_default" id="save_as_default" value="0">
        
        <div class="signature-pad">
            <canvas id="signatureCanvas" width="450" height="200"></canvas>
            <div>
                <button type="button" onclick="clearCanvas()"><i class="fas fa-eraser"></i> پاک کردن</button>
                <button type="button" onclick="submitSignature()"><i class="fas fa-save"></i> ذخیره امضا</button>
            </div>
        </div>
        
        <div class="save-option">
            <label>
                <input type="checkbox" id="save_as_default_checkbox" onchange="document.getElementById('save_as_default').value = this.checked ? 1 : 0">
                ذخیره این امضا به عنوان امضای پیش‌فرض
            </label>
        </div>
    </form>
    
    <?php if($has_default_signature): ?>
    <hr>
    <div class="info-box">
        <i class="fas fa-save"></i> شما یک امضای ذخیره شده دارید.
    </div>
    <a href="?use_default=1&delivery_date=<?php echo urlencode($delivery_date); ?>" class="btn-default" onclick="return confirm('از امضای قبلی استفاده شود؟')">استفاده از امضای قبلی</a>
    <?php endif; ?>
</div>

<script>
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    
    canvas.width = 450;
    canvas.height = 200;
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = '#333';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);
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
    
    function stopDrawing() { 
        drawing = false; 
        ctx.beginPath();
    }
    
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
        
        x = Math.min(Math.max(x, 0), canvas.width);
        y = Math.min(Math.max(y, 0), canvas.height);
        
        return { x: x, y: y };
    }
    
    function clearCanvas() {
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
    }
    
    function submitSignature() {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let hasDrawing = false;
        for (let i = 0; i < imageData.data.length; i += 4) {
            if (imageData.data[i+3] !== 0) {
                hasDrawing = true;
                break;
            }
        }
        
        if (!hasDrawing) {
            alert('❌ لطفاً ابتدا امضای خود را ثبت کنید');
            return;
        }
        
        const dataURL = canvas.toDataURL('image/png');
        document.getElementById('signature_data').value = dataURL;
        
        const btn = document.querySelector('button[onclick="submitSignature()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
        btn.disabled = true;
        
        document.getElementById('signatureForm').submit();
    }
</script>
</body>
</html>