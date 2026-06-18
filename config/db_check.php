<?php
function checkDatabaseTables($db, $required_tables = ['companies', 'users', 'documents']) {
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        try {
            $result = $db->query("SHOW TABLES LIKE '$table'");
            if ($result->rowCount() == 0) {
                $missing_tables[] = $table;
            }
        } catch (PDOException $e) {
            // اگر جدول不存在 باشد
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        die("
        <!DOCTYPE html>
        <html dir='rtl' lang='fa'>
        <head>
            <meta charset='UTF-8'>
            <title>خطا در اتصال به دیتابیس</title>
            <link rel='stylesheet' href='assets/css/vazirmatn.css'>
            <style>
                body {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Vazirmatn', sans-serif;
                    margin: 0;
                    padding: 20px;
                }
                .error-box {
                    background: white;
                    border-radius: 24px;
                    padding: 40px;
                    text-align: center;
                    max-width: 550px;
                    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
                }
                .error-box h2 {
                    color: #ef4444;
                    margin-bottom: 15px;
                }
                .error-box p {
                    color: #64748b;
                    margin-bottom: 25px;
                    line-height: 1.8;
                }
                .btn-back {
                    background: linear-gradient(135deg, #667eea, #764ba2);
                    color: white;
                    padding: 10px 25px;
                    border-radius: 12px;
                    text-decoration: none;
                    display: inline-block;
                }
                .btn-back:hover {
                    opacity: 0.9;
                }
            </style>
        </head>
        <body>
            <div class='error-box'>
                <h2>⚠️ خطا در ارتباط با دیتابیس</h2>
                <p>دیتابیس سیستم به درستی تنظیم نشده است.<br>
                لطفاً با مدیر سیستم تماس بگیرید.</p>
                <p style='font-size: 0.7rem; color: #94a3b8;'>خطا: جداول مورد نیاز در دیتابیس وجود ندارند</p>
                <a href='login.php' class='btn-back'>تلاش مجدد</a>
            </div>
        </body>
        </html>
        ");
    }
    
    return true;
}
?>