<?php
session_start();
require_once 'config/database.php';

// فقط ادمین می‌تواند این صفحه را ببیند
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("دسترسی غیرمجاز. فقط ادمین می‌تواند این صفحه را ببیند.");
}

// تابع ساده برای تاریخ
function getCurrentDate() {
    return date('Y/m/d H:i:s');
}

echo "<!DOCTYPE html>
<html dir='rtl' lang='fa'>
<head>
    <meta charset='UTF-8'>
    <title>بررسی دیتابیس سیستم</title>
    <style>
        body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f1f5f9; padding: 20px; margin: 0; direction: rtl; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #1a2c3e; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #475569; font-size: 1.2rem; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.8rem; }
        th { background: #667eea; color: white; padding: 10px; text-align: center; }
        td { padding: 8px; border: 1px solid #e2e8f0; text-align: center; }
        .status-ok { color: #10b981; font-weight: bold; }
        .status-warning { color: #f59e0b; font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }
        .summary { background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
        .badge-ok { background: #10b98120; color: #10b981; }
        .badge-warning { background: #f59e0b20; color: #f59e0b; }
        .badge-error { background: #ef444420; color: #ef4444; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📊 گزارش بررسی دیتابیس سیستم</h1>
    <p>تاریخ: " . getCurrentDate() . "</p>";

// لیست جداول مورد نیاز
$required_tables = [
    'users',
    'companies',
    'documents',
    'delivery_approvals',
    'saved_signatures',
    'revert_requests'
];

// ستون‌های مورد نیاز برای هر جدول
$required_columns = [
    'users' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'username' => 'varchar(100) NOT NULL UNIQUE',
        'password' => 'varchar(255) NOT NULL',
        'fullname' => 'varchar(255) NOT NULL',
        'gender' => "enum('male','female') DEFAULT 'male'",
        'unit_name' => 'varchar(100) NOT NULL',
        'require_doc_date' => "tinyint(1) DEFAULT '1'",
        'lock_delivery_date' => "tinyint(1) DEFAULT '0'",
        'is_admin' => "tinyint(1) DEFAULT '0'",
        'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP()"
    ],
    'companies' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'name' => 'varchar(255) NOT NULL',
        'is_active' => "tinyint(1) DEFAULT '1'",
        'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP()"
    ],
    'documents' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'user_id' => 'int(11) NOT NULL',
        'company_id' => 'int(11) NOT NULL',
        'doc_number' => 'varchar(100) NOT NULL',
        'doc_date' => "varchar(10) NOT NULL DEFAULT '-'",
        'description' => 'text DEFAULT NULL',
        'is_locked' => "tinyint(1) DEFAULT '0'",
        'delivery_date' => 'varchar(10) NOT NULL',
        'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP()",
        'updated_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP()"
    ],
    'delivery_approvals' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'user_id' => 'int(11) NOT NULL',
        'delivery_date' => 'varchar(10) DEFAULT NULL',
        'user_approved_at' => 'timestamp NULL DEFAULT NULL',
        'user_reverted_at' => 'timestamp NULL DEFAULT NULL',
        'admin_approved_at' => 'timestamp NULL DEFAULT NULL',
        'admin_signature_used' => 'varchar(255) DEFAULT NULL'
    ],
    'saved_signatures' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'user_id' => 'int(11) NOT NULL UNIQUE',
        'signature_path' => 'varchar(255) NOT NULL',
        'is_default' => "tinyint(1) DEFAULT '0'",
        'created_at' => "timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP()"
    ],
    'revert_requests' => [
        'id' => 'int(11) AUTO_INCREMENT',
        'user_id' => 'int(11) NOT NULL',
        'delivery_date' => 'varchar(10) DEFAULT NULL',
        'requested_at' => 'timestamp NULL DEFAULT NULL',
        'approved_at' => 'timestamp NULL DEFAULT NULL',
        "status" => "enum('pending','approved','rejected') DEFAULT 'pending'"
    ]
];

echo "<h2>📋 بررسی جداول دیتابیس</h2>";
echo "<table>";
echo "<tr><th>#</th><th>نام جدول</th><th>وضعیت</th><th>تعداد رکوردها</th><th>توضیحات</th></tr>";

$table_status = [];
$i = 1;

foreach ($required_tables as $table) {
    $stmt = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $status = "✅ موجود";
        $status_class = "status-ok";
    } else {
        $count = 0;
        $status = "❌ وجود ندارد";
        $status_class = "status-error";
    }
    
    $table_status[$table] = $exists;
    
    echo "<tr>
        <td>{$i}</td>
        <td><strong>{$table}</strong></td>
        <td class='{$status_class}'>{$status}</td>
        <td>{$count}</td>
        <td>" . ($exists ? "جدول با موفقیت ایجاد شده" : "نیاز به ایجاد جدول") . "</td>
    </tr>";
    $i++;
}

echo "</table>";

// بررسی ستون‌های هر جدول
echo "<h2>🔍 بررسی ستون‌های جداول</h2>";

foreach ($required_columns as $table => $columns) {
    if (!$table_status[$table]) {
        echo "<div class='summary' style='background:#fee2e2;'>
            <strong>⚠️ جدول {$table} وجود ندارد!</strong> لطفاً ابتدا جدول را ایجاد کنید.
        </div>";
        continue;
    }
    
    echo "<h3>جدول: {$table}</h3>";
    echo "<table>";
    echo "<tr><th>نام ستون</th><th>نوع مورد نیاز</th><th>نوع موجود</th><th>وضعیت</th></tr>";
    
    $stmt = $db->query("DESCRIBE $table");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[$row['Field']] = $row['Type'];
    }
    
    foreach ($columns as $col_name => $required_type) {
        $exists = isset($existing_columns[$col_name]);
        $current_type = $exists ? $existing_columns[$col_name] : '-';
        
        $status_text = $exists ? "✅ موجود" : "❌缺失";
        $status_class = $exists ? "status-ok" : "status-error";
        
        echo "<tr>
            <td><strong>{$col_name}</strong></td>
            <td style='font-size:0.7rem;'>{$required_type}</td>
            <td style='font-size:0.7rem;'>{$current_type}</td>
            <td class='{$status_class}'>{$status_text}</td>
        </tr>";
    }
    
    echo "</table>";
}

// خلاصه نهایی
echo "<div class='summary'>";
echo "<h2>📊 خلاصه نهایی</h2>";

$total_tables = count($required_tables);
$existing_tables = count(array_filter($table_status));
$missing_tables = $total_tables - $existing_tables;

if ($missing_tables == 0) {
    echo "<p class='status-ok'>✅ <strong>همه جداول دیتابیس با موفقیت ایجاد شده‌اند.</strong></p>";
} else {
    echo "<p class='status-error'>⚠️ <strong>{$missing_tables} جدول وجود ندارد. لطفاً جداول را ایجاد کنید.</strong></p>";
}

// بررسی وجود داده‌های اولیه
$admin_count = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
$user_count = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
$company_count = $db->query("SELECT COUNT(*) FROM companies")->fetchColumn();

echo "<p>👑 تعداد ادمین‌ها: <strong>{$admin_count}</strong></p>";
echo "<p>👥 تعداد کاربران عادی: <strong>{$user_count}</strong></p>";
echo "<p>🏢 تعداد شرکت‌ها: <strong>{$company_count}</strong></p>";

if ($admin_count == 0) {
    echo "<p class='status-error'>⚠️ هیچ ادمینی در سیستم ثبت نشده است! لطفاً یک ادمین ایجاد کنید.</p>";
}
if ($company_count == 0) {
    echo "<p class='status-warning'>⚠️ هیچ شرکتی ثبت نشده است! لطفاً شرکت‌های خود را اضافه کنید.</p>";
}

echo "</div>";

// توصیه‌های نهایی
echo "<div class='summary'>";
echo "<h2>✅ توصیه‌های نهایی برای راه‌اندازی</h2>";
echo "<ul>
    <li>✅ مطمئن شوید پوشه‌های <code>storage/signatures/users/</code> و <code>storage/signatures/admin/</code> وجود دارند و قابل نوشتن هستند.</li>
    <li>✅ یک ادمین در سیستم داشته باشید (نام کاربری: admin، رمز: admin یا هر چیز دیگر).</li>
    <li>✅ حداقل ۵ شرکت فعال در سیستم ثبت کنید.</li>
    <li>✅ برای هر کاربر عادی، یک واحد مشخص کنید.</li>
    <li>✅ فایل‌های .htaccess را بررسی کنید تا دسترسی به پوشه‌ها محدود شده باشد.</li>
</ul>";
echo "</div>";

echo "<div style='text-align: center; margin-top: 30px; padding: 15px; background: #eef2ff; border-radius: 12px;'>
    <p>🔧 <strong>سیستم شما آماده راه‌اندازی است!</strong> اگر خطایی مشاهده می‌کنید، لطفاً طبق توصیه‌های بالا اقدام کنید.</p>
    <a href='index.php' style='display: inline-block; margin-top: 10px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none;'>← بازگشت به داشبورد</a>
</div>";

echo "</div></body></html>";
?>