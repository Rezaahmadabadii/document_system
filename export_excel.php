<?php
session_name('doc_system');
session_start();
require_once 'config/database.php';
require_once 'config/jdatetime.class.php';

// فقط ادمین دسترسی داشته باشد
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("دسترسی غیرمجاز");
}

// ========== بررسی تأییدیه ==========
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] == 'yes';

if (!$confirmed) {
    // اگر تأییدیه نگرفته، صفحه تأیید را نشان بده
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <title>تأیید خروجی اکسل</title>
        <link rel="stylesheet" href="assets/css/vazirmatn.css">
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
            .confirm-box {
                background: white;
                border-radius: 24px;
                padding: 40px;
                text-align: center;
                max-width: 450px;
                box-shadow: 0 20px 35px rgba(0,0,0,0.2);
            }
            .confirm-box h2 {
                color: #1a2c3e;
                margin-bottom: 15px;
            }
            .confirm-box p {
                color: #64748b;
                margin-bottom: 25px;
            }
            .btn-yes {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 10px 25px;
                border-radius: 12px;
                text-decoration: none;
                margin: 0 10px;
                display: inline-block;
                font-weight: bold;
            }
            .btn-no {
                background: #ef4444;
                color: white;
                padding: 10px 25px;
                border-radius: 12px;
                text-decoration: none;
                margin: 0 10px;
                display: inline-block;
                font-weight: bold;
            }
            .btn-yes:hover { opacity: 0.9; }
            .btn-no:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class="confirm-box">
            <h2>⚠️ تأیید خروجی اکسل</h2>
            <p>آیا از خروجی گرفتن از تمام اسناد سیستم مطمئن هستید؟</p>
            <p style="font-size: 0.7rem; color: #ef4444;">این کار یک فایل اکسل از تمام اسناد خواهد ساخت.</p>
            <a href="?confirm=yes" class="btn-yes">✅ بله، خروجی بگیر</a>
            <a href="index.php" class="btn-no">❌ انصراف</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ========== ادامه کد قبلی برای خروجی اکسل ==========
// تنظیم هدر برای دانلود فایل Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="documents_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// دریافت همه اسناد به همراه اطلاعات کاربر و شرکت
$sql = "SELECT 
            d.id,
            u.fullname as user_name,
            u.unit_name,
            d.doc_number,
            d.doc_date,
            d.description,
            c.name as company_name,
            d.delivery_date,
            d.created_at,
            da.user_approved_at,
            da.admin_approved_at
        FROM documents d
        JOIN users u ON d.user_id = u.id
        JOIN companies c ON d.company_id = c.id
        LEFT JOIN delivery_approvals da ON d.user_id = da.user_id AND d.delivery_date = da.delivery_date
        ORDER BY d.id DESC";

$stmt = $db->query($sql);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تابع تبدیل تاریخ میلادی به شمسی
function toJalali($date) {
    if (empty($date)) return '-';
    try {
        return jDateTime::date('Y/m/d H:i:s', strtotime($date));
    } catch(Exception $e) {
        return $date;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>خروجی اسناد</title>
    <style>
        td, th { border: 1px solid #ccc; padding: 6px; text-align: center; }
        table { border-collapse: collapse; width: 100%; direction: rtl; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>نام کاربر</th>
                <th>واحد</th>
                <th>شماره سند</th>
                <th>تاریخ سند</th>
                <th>شرکت</th>
                <th>تاریخ تحویل</th>
                <th>توضیحات</th>
                <th>تاریخ ثبت</th>
                <th>تاریخ امضای کاربر</th>
                <th>تاریخ تایید ادمین</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach($documents as $doc): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($doc['user_name']); ?></td>
                <td><?php echo htmlspecialchars($doc['unit_name']); ?></td>
                <td><?php echo htmlspecialchars($doc['doc_number']); ?></td>
                <td><?php echo $doc['doc_date'] == '-' ? '—' : htmlspecialchars($doc['doc_date']); ?></td>
                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                <td><?php echo htmlspecialchars($doc['delivery_date']); ?></td>
                <td><?php echo htmlspecialchars(mb_substr($doc['description'], 0, 50)); ?></td>
                <td><?php echo toJalali($doc['created_at']); ?></td>
                <td><?php echo toJalali($doc['user_approved_at']); ?></td>
                <td><?php echo toJalali($doc['admin_approved_at']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>