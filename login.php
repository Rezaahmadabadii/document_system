<?php
session_name('doc_system');
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$entered_username = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/database.php';
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $entered_username = $username;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['unit_name'] = $user['unit_name'];
        $_SESSION['require_doc_date'] = $user['require_doc_date'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['gender'] = $user['gender'];
        $_SESSION['username'] = $user['username'];
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => true]);
            exit;
        } else {
            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است';
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه بایگانی اسناد</title>
    <link rel="icon" type="image/x-icon" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            top: -100px;
            left: -100px;
        }
        
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            bottom: -150px;
            right: -150px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 40px 35px 25px 35px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            transition: all 0.5s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }
        
        /* حالت خطا */
        .login-card.error-mode {
            background: rgba(255,240,240,0.98);
            border: 1px solid #fecaca;
            box-shadow: 0 0 0 3px rgba(239,68,68,0.2);
        }
        
        /* لرزش قوی */
        .shake-strong {
            animation: shakeStrong 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }
        
        @keyframes shakeStrong {
            0%, 100% { transform: translateX(0); }
            10% { transform: translateX(-8px); }
            20% { transform: translateX(8px); }
            30% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            50% { transform: translateX(-4px); }
            60% { transform: translateX(4px); }
            70% { transform: translateX(-2px); }
            80% { transform: translateX(2px); }
            90% { transform: translateX(-1px); }
        }
        
        /* قرمز شدن فیلدها در حالت خطا */
        .error-mode .input-group input {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .error-mode .input-group i {
            color: #ef4444;
        }
        
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .logo-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .logo-area h1 {
            font-size: 1.3rem;
            color: #1a2c3e;
            margin-bottom: 5px;
        }
        
        .logo-area p {
            font-size: 0.7rem;
            color: #6c86a3;
        }
        
        .dev-credit {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.65rem;
            color: #94a3b8;
        }
        
        .dev-credit span {
            color: #667eea;
            font-weight: 500;
        }
        
        .smiley-area {
            text-align: center;
            margin-top: 20px;
        }
        
        .pulse-smiley {
            font-size: 2rem;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .pulse-smiley:hover {
            animation: none;
            transform: scale(1.3);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 45px 14px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .input-group input:focus + i {
            color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.4);
        }
        
        .login-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        
        .login-btn.loading .btn-text {
            display: none;
        }
        
        .login-btn.loading .loader {
            display: flex;
        }
        
        .loader {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .loader span {
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            animation: bounce 0.5s ease-in-out infinite;
        }
        
        .loader span:nth-child(1) {
            animation-delay: 0s;
        }
        
        .loader span:nth-child(2) {
            animation-delay: 0.1s;
        }
        
        .loader span:nth-child(3) {
            animation-delay: 0.2s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 0.75rem;
            text-align: center;
            border-right: 3px solid #dc2626;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* انیمیشن لودینگ صفحه برای انتقال */
        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.5s ease;
        }
        
        .page-transition.active {
            opacity: 1;
            visibility: visible;
        }
        
        .transition-loader {
            text-align: center;
        }
        
        .transition-loader i {
            font-size: 4rem;
            color: white;
            animation: spin 1s linear infinite;
        }
        
        .transition-loader p {
            color: white;
            margin-top: 20px;
            font-size: 1rem;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 500px) {
            .login-card {
                padding: 30px 25px 20px 25px;
            }
        }
    </style>
</head>
<body>

<div class="page-transition" id="pageTransition">
    <div class="transition-loader">
        <i class="fas fa-spinner fa-pulse"></i>
        <p>در حال ورود به سامانه...</p>
    </div>
</div>

<div class="login-container">
    <div class="login-card" id="loginCard">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h1>سامانه بایگانی و تحویل اسناد</h1>
            <p>مدیریت و بایگانی هوشمند اسناد</p>
        </div>
        
        <div id="errorContainer"></div>
        
        <form method="POST" id="loginForm">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" id="username" placeholder="نام کاربری" value="<?php echo htmlspecialchars($entered_username); ?>" required autofocus>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="رمز عبور" required>
            </div>
            <button type="submit" class="login-btn" id="loginBtn">
                <span class="btn-text"><i class="fas fa-sign-in-alt"></i> ورود به سامانه</span>
                <span class="loader">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </form>
        
        <div class="dev-credit">
            <span>Dev : Reza.Ahmadabadi</span>
        </div>
        
        <div class="smiley-area">
            <div class="pulse-smiley">
                😊
            </div>
        </div>
    </div>
</div>

<script>
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const errorContainer = document.getElementById('errorContainer');
    const pageTransition = document.getElementById('pageTransition');
    const loginCard = document.getElementById('loginCard');
    
    <?php if($error): ?>
    showError('<?php echo addslashes($error); ?>');
    <?php endif; ?>
    
    function showError(message) {
        // ایجاد پیام خطا
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
        errorContainer.innerHTML = '';
        errorContainer.appendChild(errorDiv);
        
        // اضافه کردن کلاس خطا به کارت
        loginCard.classList.add('error-mode');
        
        // لرزش قوی
        loginCard.classList.add('shake-strong');
        
        // حذف کلاس لرزش بعد از 500 میلی‌ثانیه
        setTimeout(() => {
            loginCard.classList.remove('shake-strong');
        }, 500);
        
        // حذف کلاس خطا بعد از 800 میلی‌ثانیه (بازگشت نرم)
        setTimeout(() => {
            loginCard.classList.remove('error-mode');
        }, 800);
        
        // حذف پیام خطا بعد از 3 ثانیه
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.style.animation = 'slideDown 0.3s ease reverse';
                setTimeout(() => {
                    if (errorDiv.parentNode) errorDiv.remove();
                }, 300);
            }
        }, 3000);
    }
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        if (!username || !password) {
            showError('لطفاً نام کاربری و رمز عبور را وارد کنید');
            return;
        }
        
        // نمایش حالت لودینگ روی دکمه
        loginBtn.classList.add('loading');
        
        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                // نمایش انیمیشن لودینگ صفحه
                pageTransition.classList.add('active');
                
                // بعد از 0.8 ثانیه به صفحه اصلی هدایت شود
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 800);
            } else {
                // حذف حالت لودینگ
                loginBtn.classList.remove('loading');
                showError(result.error || 'نام کاربری یا رمز عبور اشتباه است');
            }
        } catch(err) {
            loginBtn.classList.remove('loading');
            showError('خطا در ارتباط با سرور');
            console.error(err);
        }
    });
    
    // نمایش/مخفی کردن رمز عبور
    const passwordInput = document.getElementById('password');
    if(passwordInput) {
        const wrapper = passwordInput.parentElement;
        const eyeIcon = document.createElement('i');
        eyeIcon.className = 'far fa-eye-slash';
        eyeIcon.style.cssText = 'position: absolute; left: 15px; right: auto; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0aec0; z-index: 2;';
        wrapper.appendChild(eyeIcon);
        eyeIcon.addEventListener('click', function() {
            if(passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.className = 'far fa-eye';
            } else {
                passwordInput.type = 'password';
                eyeIcon.className = 'far fa-eye-slash';
            }
        });
    }
</script>
</body>
</html>