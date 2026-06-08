<?php
session_start();
require_once 'config/database.php';
require_once 'config/jdatetime.class.php';  // ← این خط را اضافه کن

// فقط ادمین دسترسی داشته باشد
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("دسترسی غیرمجاز");
}

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