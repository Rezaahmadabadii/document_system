<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once 'config/database.php';
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['unit_name'] = $user['unit_name'];
        $_SESSION['require_doc_date'] = $user['require_doc_date'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['gender'] = $user['gender'];
        $_SESSION['username'] = $user['username'];
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است';
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه بایگانی اسناد</title>
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
            padding: 40px 35px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
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
        
        .smiley-area {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .pulse-smiley {
            font-size: 2.5rem;
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
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
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
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.4);
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 0.75rem;
            text-align: center;
            border-right: 3px solid #c53030;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .shake {
            animation: shake 0.3s ease;
        }
        
        @media (max-width: 500px) {
            .login-card {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h1>سامانه بایگانی و تحویل  اسناد</h1>
            <p>مدیریت و بایگانی هوشمند اسناد</p>
        </div>
        
        <?php if($error): ?>
        <div class="error-message" id="errorMsg">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="نام کاربری" required autofocus>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="رمز عبور" required>
            </div>
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> ورود به سامانه
            </button>
        </form>
        
        <div class="smiley-area">
            <div class="pulse-smiley">
                😊
            </div>
        </div>
    </div>
</div>

<script>
    <?php if($error): ?>
    document.getElementById('errorMsg')?.classList.add('shake');
    setTimeout(() => {
        let error = document.getElementById('errorMsg');
        if(error) error.classList.remove('shake');
    }, 300);
    <?php endif; ?>
    
    const passwordInput = document.getElementById('password');
    if(passwordInput) {
        const wrapper = passwordInput.parentElement;
        const eyeIcon = document.createElement('i');
        eyeIcon.className = 'far fa-eye-slash';
        eyeIcon.style.cssText = 'position: absolute; left: 15px; right: auto; top: 50%; transform: translateY(-50%); cursor: pointer; color: #a0aec0;';
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