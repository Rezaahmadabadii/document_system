<?php
session_name('doc_system');
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

// اطمینان از وجود رکورد در delivery_approvals برای این تاریخ
$stmt = $db->prepare("INSERT IGNORE INTO delivery_approvals (user_id, delivery_date) VALUES (?, ?)");
$stmt->execute([$user_id, $delivery_date]);

// بررسی اینکه آیا این تاریخ قبلاً توسط ادمین تایید شده است
$stmt = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
$stmt->execute([$user_id, $delivery_date]);
$admin_approved = $stmt->fetchColumn();

if ($admin_approved) {
    header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&error=locked');
    exit;
}

$upload_dir = 'storage/signatures/users/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
$signature_file = $upload_dir . $user_id . '.png';
$has_signature = file_exists($signature_file);

// استفاده از امضای قبلی
if (isset($_GET['use_existing'])) {
    $stmt = $db->prepare("UPDATE delivery_approvals SET user_approved_at = NOW() WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$user_id, $delivery_date]);
    
    header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&signature=1');
    exit;
}

// حذف امضا
if (isset($_GET['delete_signature'])) {
    if (file_exists($signature_file)) {
        unlink($signature_file);
    }
    header('Location: signature_upload.php?delivery_date=' . urlencode($delivery_date));
    exit;
}

// پردازش ذخیره امضا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signature_data'])) {
    $signature_data = $_POST['signature_data'];
    $signature_data = preg_replace('/^data:image\/\w+;base64,/', '', $signature_data);
    $signature_data = str_replace(' ', '+', $signature_data);
    $image_data = base64_decode($signature_data);
    
    if (file_put_contents($signature_file, $image_data)) {
        $stmt = $db->prepare("UPDATE delivery_approvals SET user_approved_at = NOW() WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $delivery_date]);
        
        header('Location: print.php?delivery_date=' . urlencode($delivery_date) . '&success=1');
        exit;
    }
    
    header('Location: signature_upload.php?delivery_date=' . urlencode($delivery_date) . '&error=1');
    exit;
}

// ✅ تعیین اینکه آیا مودال نمایش داده شود یا نه
$show_modal = $has_signature && !isset($_SESSION['from_print']);
unset($_SESSION['from_print']); // بعد از استفاده پاکش کن
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
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-default { background: #10b981; display: inline-block; text-decoration: none; color: white; padding: 10px 20px; border-radius: 12px; margin: 8px; }
        .btn-delete { background: #ef4444; }
        .info-box { background: #e0e7ff; padding: 12px; border-radius: 12px; margin-bottom: 15px; }
        hr { margin: 15px 0; }
        .current-signature { margin: 15px 0; padding: 10px; background: #f0fdf4; border-radius: 12px; }
        .current-signature img { max-width: 200px; max-height: 60px; margin-top: 10px; }
        .error-msg { color: #ef4444; font-size: 0.7rem; margin-top: 8px; display: none; }
        
        /* ===== مودال ===== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active { display: flex; }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 30px 35px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-close {
            position: absolute;
            top: 12px;
            left: 18px;
            font-size: 1.2rem;
            color: #94a3b8;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: #f1f5f9;
            color: #475569;
        }
        .modal-icon {
            font-size: 2.2rem;
            color: #667eea;
            margin-bottom: 12px;
            display: block;
        }
        .modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .modal-desc {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .modal-btn {
            padding: 10px 24px;
            border-radius: 12px;
            border: none;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            min-width: 140px;
        }
        .modal-btn:hover { transform: translateY(-2px); }
        .modal-btn:active { transform: scale(0.97); }
        .modal-btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }
        .modal-btn-primary:hover { box-shadow: 0 6px 20px rgba(59,130,246,0.4); }
        .modal-btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }
        .modal-btn-success:hover { box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
    </style>
</head>
<body>

<!-- ===== مودال ===== -->
<?php if($show_modal): ?>
<div class="modal-overlay active" id="signatureModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <span class="modal-icon">✍️</span>
        <div class="modal-title">انتخاب روش ثبت امضا</div>
        <div class="modal-desc">
            آیا مایل به استفاده از امضای قبلی خود هستید<br>یا می‌خواهید امضای جدید رسم کنید؟
        </div>
        <div class="modal-buttons">
            <button class="modal-btn modal-btn-primary" onclick="chooseNewSignature()">
                <i class="fas fa-pen"></i> ترسیم امضای جدید
            </button>
            <button class="modal-btn modal-btn-success" onclick="chooseExistingSignature()">
                <i class="fas fa-check"></i> ثبت امضای قبلی
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container">
    <h2>✍️ ثبت امضای تحویل‌دهنده</h2>
    <p>تاریخ تحویل: <strong><?php echo htmlspecialchars($delivery_date); ?></strong></p>
    
    <?php if($has_signature): ?>
    <div class="current-signature">
        <i class="fas fa-save"></i> امضای فعلی شما:
        <br>
        <img src="<?php echo $signature_file . '?t=' . time(); ?>" alt="امضای فعلی">
        <br>
        <a href="?delete_signature=1&delivery_date=<?php echo urlencode($delivery_date); ?>" class="btn-delete" style="display: inline-block; padding: 5px 12px; font-size: 0.7rem; margin-top: 8px;" onclick="return confirm('آیا از حذف امضای خود اطمینان دارید؟')">🗑️ حذف امضا</a>
    </div>
    <hr>
    <?php endif; ?>
    
    <form method="POST" id="signatureForm">
        <input type="hidden" name="signature_data" id="signature_data">
        
        <div class="signature-pad" id="signaturePad" style="<?php echo ($show_modal && $has_signature) ? 'display:none;' : 'display:block;'; ?>">
            <canvas id="signatureCanvas" width="450" height="200"></canvas>
            <div>
                <button type="button" onclick="clearCanvas()"><i class="fas fa-eraser"></i> پاک کردن</button>
                <button type="button" id="submitBtn" onclick="submitSignature()" disabled><i class="fas fa-save"></i> ثبت امضا</button>
            </div>
            <div id="errorMsg" class="error-msg">⚠️ لطفاً ابتدا امضای خود را رسم کنید</div>
        </div>
    </form>
    
    <?php if($has_signature): ?>
    <hr>
    <div class="info-box">
        <i class="fas fa-info-circle"></i> با ثبت امضای جدید، امضای قبلی شما <strong>جایگزین</strong> می‌شود.
    </div>
    <?php endif; ?>
</div>

<script>
    // ===== متغیرهای امضا =====
    const canvas = document.getElementById('signatureCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasDrawn = false;
    const submitBtn = document.getElementById('submitBtn');
    const errorMsg = document.getElementById('errorMsg');
    
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
        if (!hasDrawn) {
            hasDrawn = true;
            submitBtn.disabled = false;
            errorMsg.style.display = 'none';
        }
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
        hasDrawn = false;
        submitBtn.disabled = true;
        errorMsg.style.display = 'block';
    }
    
    function submitSignature() {
        if (!hasDrawn) {
            errorMsg.style.display = 'block';
            return;
        }
        const dataURL = canvas.toDataURL('image/png');
        document.getElementById('signature_data').value = dataURL;
        const btn = submitBtn;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
        btn.disabled = true;
        document.getElementById('signatureForm').submit();
    }
    
    // ===== مودال =====
    function closeModal() {
        const modal = document.getElementById('signatureModal');
        if (modal) modal.classList.remove('active');
    }
    
    function chooseNewSignature() {
        closeModal();
        document.getElementById('signaturePad').style.display = 'block';
    }
    
    function chooseExistingSignature() {
        window.location.href = '?use_existing=1&delivery_date=<?php echo urlencode($delivery_date); ?>';
    }

    // کلیک خارج از مودال برای بستن
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('signatureModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        }
    });
</script>
</body>
</html>