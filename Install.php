<?php
/**
 * فایل نصب سیستم بایگانی اسناد
 * پس از اجرا، این فایل را حذف کنید!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'document_system';
$username = 'root';
$password = '';

echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <title>نصب سیستم بایگانی اسناد</title>
    <style>
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f0f4f8; padding: 20px; }
        .container { max-width: 800px; margin: 50px auto; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #1a2c3e; border-right: 4px solid #667eea; padding-right: 15px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; margin: 10px 0; }
        .btn { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 1rem; margin-top: 20px; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📦 نصب سیستم بایگانی اسناد</h1>";

try {
    // اتصال به MySQL
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // حذف دیتابیس در صورت وجود
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    echo "<div class='success'>✅ دیتابیس قدیمی حذف شد</div>";
    
    // ایجاد دیتابیس جدید
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
    echo "<div class='success'>✅ دیتابیس '$dbname' ایجاد شد</div>";
    
    // انتخاب دیتابیس
    $pdo->exec("USE `$dbname`");
    
    // ============================================
    // جدول companies
    // ============================================
    $sql = "CREATE TABLE companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول companies ایجاد شد</div>";
    
    // ============================================
    // جدول users
    // ============================================
    $sql = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(255) NOT NULL,
        gender ENUM('male', 'female') DEFAULT 'male',
        unit_name VARCHAR(100) NOT NULL,
        require_doc_date TINYINT(1) DEFAULT 1,
        lock_delivery_date TINYINT(1) DEFAULT 0,
        is_admin TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول users ایجاد شد</div>";
    
    // ============================================
    // جدول documents
    // ============================================
    $sql = "CREATE TABLE documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        doc_number VARCHAR(100) NOT NULL,
        doc_date VARCHAR(10) NOT NULL DEFAULT '-',
        delivery_date VARCHAR(10) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (company_id) REFERENCES companies(id),
        INDEX idx_user_id (user_id),
        INDEX idx_company_id (company_id),
        INDEX idx_doc_number (doc_number),
        INDEX idx_doc_date (doc_date),
        INDEX idx_delivery_date (delivery_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول documents ایجاد شد</div>";
    
    // ============================================
    // جدول delivery_approvals
    // ============================================
    $sql = "CREATE TABLE delivery_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        delivery_date VARCHAR(10) NOT NULL,
        user_approved_at TIMESTAMP NULL,
        admin_approved_at TIMESTAMP NULL,
        admin_signature_used VARCHAR(255),
        UNIQUE KEY unique_user_date (user_id, delivery_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول delivery_approvals ایجاد شد</div>";
    
    // ============================================
    // جدول saved_signatures
    // ============================================
    $sql = "CREATE TABLE saved_signatures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        signature_path VARCHAR(255) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول saved_signatures ایجاد شد</div>";
    
    // ============================================
    // جدول revert_requests
    // ============================================
    $sql = "CREATE TABLE revert_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        requested_at TIMESTAMP NULL,
        approved_at TIMESTAMP NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci";
    $pdo->exec($sql);
    echo "<div class='success'>✅ جدول revert_requests ایجاد شد</div>";
    
    // ============================================
    // درج شرکت‌های اولیه
    // ============================================
    $companies = [
        'کیهان', 'دنا', 'قدرت', 'بنابام', 'شعبه6',
        'صولت', 'ماهان', 'رایان', 'مینوساز', 'پایش',
        'امین راه', 'سامان', 'آرمان', 'تفتان', 'احداث راه',
        'هونام', 'تحکیم', 'کیهان ریل', 'آناهیتا', 'باراد'
    ];
    
    $stmt = $pdo->prepare("INSERT INTO companies (name, is_active) VALUES (?, 1)");
    foreach ($companies as $company) {
        $stmt->execute([$company]);
    }
    echo "<div class='success'>✅ " . count($companies) . " شرکت اضافه شد</div>";
    
    // ============================================
    // ایجاد کاربر ادمین
    // ============================================
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, unit_name, is_admin) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute(['admin', $admin_password, 'مدیر کل سیستم', 'مدیریت']);
    echo "<div class='success'>✅ کاربر ادمین ایجاد شد (admin / admin123)</div>";
    
    // ============================================
    // ایجاد کاربر نمونه
    // ============================================
    $user_password = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, unit_name, is_admin) VALUES (?, ?, ?, ?, 0)");
    $stmt->execute(['user1', $user_password, 'کاربر نمونه', 'واحد آزمایش']);
    echo "<div class='success'>✅ کاربر نمونه ایجاد شد (user1 / 123456)</div>";
    
    echo "<div class='success' style='background:#28a745; color:white;'><strong>✅ نصب با موفقیت انجام شد!</strong></div>";
    echo "<div class='warning'><strong>⚠️ هشدار امنیتی:</strong> لطفاً فایل install.php را حذف کنید!</div>";
    echo "<a href='login.php' class='btn'>🔐 ورود به سامانه</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ خطا:</strong><br>" . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>