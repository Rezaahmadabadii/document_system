<?php
/**
 * فایل خروجی گرفتن از ساختار و محتوای دیتابیس
 * مسیر: export_tables.php
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>خروجی ساختار دیتابیس</title>
    <style>
        body {
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif;
            background: #f0f4f8;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .table-card {
            background: white;
            border-radius: 24px;
            margin-bottom: 30px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .table-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1a2c3e;
            margin-bottom: 15px;
            border-right: 4px solid #2c5f8a;
            padding-right: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        th {
            background: #1a2c3e;
            color: white;
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: center;
        }
        .struct-table th {
            background: #2c5f8a;
        }
        .sample-data {
            margin-top: 20px;
        }
        .sample-data h4 {
            color: #2c5f8a;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        hr {
            margin: 20px 0;
            border: 1px dashed #cbd5e1;
        }
        .badge {
            display: inline-block;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
        }
        .index-info {
            margin-top: 10px;
            background: #f8fafc;
            padding: 10px;
            border-radius: 12px;
            font-size: 0.7rem;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 style="color:#1a2c3e;">📊 خروجی ساختار و محتوای دیتابیس</h1>
    <p style="color:#6c86a3; margin-bottom: 25px;">نام دیتابیس: <strong>document_system</strong></p>

<?php

// گرفتن لیست جدول‌ها
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "<div class='table-card'>";
    echo "<div class='table-title'>📋 جدول: " . htmlspecialchars($table) . "</div>";
    
    // ========== 1. ساختار جدول (ستون‌ها) ==========
    echo "<div class='struct-table'>";
    echo "<h4 style='margin:10px 0;'>🔧 ساختار جدول (ستون‌ها)</h4>";
    $columns = $db->query("DESCRIBE `$table`")->fetchAll();
    
    echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
    echo "<thead><tr>
            <th>#</th>
            <th>نام فیلد</th>
            <th>نوع داده</th>
            <th>NULL</th>
            <th>کلید</th>
            <th>مقدار پیش‌فرض</th>
            <th>Extra</th>
          </tr></thead><tbody>";
    
    foreach ($columns as $idx => $col) {
        echo "<tr>
                <td>" . ($idx + 1) . "</td>
                <td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>
                <td>" . htmlspecialchars($col['Type']) . "</td>
                <td>" . ($col['Null'] == 'YES' ? '✓' : '✗') . "</td>
                <td>" . htmlspecialchars($col['Key']) . "</td>
                <td>" . htmlspecialchars($col['Default']) . "</td>
                <td>" . htmlspecialchars($col['Extra']) . "</td>
              </tr>";
    }
    echo "</tbody></table>";
    echo "</div>";
    
    // ========== 2. ایندکس‌ها ==========
    $indexes = $db->query("SHOW INDEX FROM `$table`")->fetchAll();
    if (count($indexes) > 0) {
        echo "<div class='index-info'>";
        echo "<strong>🔑 ایندکس‌ها:</strong> ";
        $indexNames = [];
        foreach ($indexes as $idx) {
            if ($idx['Key_name'] != 'PRIMARY') {
                $indexNames[] = $idx['Key_name'] . " (" . $idx['Column_name'] . ")";
            }
        }
        if (count($indexNames) > 0) {
            echo implode(' , ', array_unique($indexNames));
        } else {
            echo "فقط کلید اصلی (PRIMARY)";
        }
        echo "</div>";
    }
    
    // ========== 3. روابط (Foreign Keys) ==========
    try {
        $fkQuery = "
            SELECT 
                k.COLUMN_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
            WHERE k.TABLE_SCHEMA = 'document_system'
              AND k.TABLE_NAME = '$table'
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ";
        $foreignKeys = $db->query($fkQuery)->fetchAll();
        if (count($foreignKeys) > 0) {
            echo "<div class='index-info' style='background:#eef2ff;'>";
            echo "<strong>🔗 ارتباطات (Foreign Keys):</strong><br>";
            foreach ($foreignKeys as $fk) {
                echo "• " . htmlspecialchars($fk['COLUMN_NAME']) . " → " . htmlspecialchars($fk['REFERENCED_TABLE_NAME']) . "." . htmlspecialchars($fk['REFERENCED_COLUMN_NAME']) . "<br>";
            }
            echo "</div>";
        }
    } catch (PDOException $e) {
        // بعضی ورژن‌های MySQL ممکن است دسترسی به INFORMATION_SCHEMA نداشته باشند
    }
    
    // ========== 4. تعداد کل رکوردها ==========
    $count = $db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "<div class='index-info' style='background:#eef2ff; margin-top:10px;'>";
    echo "<strong>📊 تعداد کل رکوردها:</strong> " . number_format($count) . " عدد";
    echo "</div>";
    
    // ========== 5. نمونه داده (حداکثر 10 رکورد) ==========
    if ($count > 0) {
        echo "<div class='sample-data'>";
        echo "<h4>📝 نمونه داده (حداکثر 10 رکورد)</h4>";
        
        $sampleData = $db->query("SELECT * FROM `$table` LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        if (count($sampleData) > 0) {
            // گرفتن نام ستون‌ها
            $colNames = array_keys($sampleData[0]);
            
            echo "<table border='1' style='border-collapse:collapse; width:100%;'>";
            echo "<thead><tr>";
            foreach ($colNames as $col) {
                echo "<th>" . htmlspecialchars($col) . "</th>";
            }
            echo "</tr></thead><tbody>";
            
            foreach ($sampleData as $row) {
                echo "<tr>";
                foreach ($colNames as $col) {
                    $value = $row[$col];
                    if ($value === null) {
                        $value = '<span style="color:#94a3b8;">NULL</span>';
                    } elseif ($value === '') {
                        $value = '<span style="color:#94a3b8;">—</span>';
                    } elseif (strlen($value) > 50) {
                        $value = htmlspecialchars(mb_substr($value, 0, 50)) . '...';
                    } else {
                        $value = htmlspecialchars($value);
                    }
                    echo "<td style='max-width:200px; word-break:break-word;'>" . $value . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        echo "</div>";
    }
    
    echo "</div>"; // بستن table-card
}

// ========== خلاصه نهایی ==========
echo "<div class='table-card' style='background:linear-gradient(135deg, #1a2c3e, #2c5f8a); color:white;'>";
echo "<div class='table-title' style='color:white; border-right-color:#fbbf24;'>📈 خلاصه دیتابیس</div>";
$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE is_admin = 0) as normal_users,
        (SELECT COUNT(*) FROM users WHERE is_admin = 1) as admin_users,
        (SELECT COUNT(*) FROM companies) as companies,
        (SELECT COUNT(*) FROM documents) as documents
")->fetch(PDO::FETCH_ASSOC);

echo "<table style='color:white;'>";
echo "<tr><td style='border:none;'><strong>👥 کاربران عادی:</strong> " . number_format($stats['normal_users']) . "</td>";
echo "<td style='border:none;'><strong>👑 کاربران ادمین:</strong> " . number_format($stats['admin_users']) . "</td></tr>";
echo "<tr><td style='border:none;'><strong>🏢 شرکت‌ها:</strong> " . number_format($stats['companies']) . "</td>";
echo "<td style='border:none;'><strong>📄 کل اسناد:</strong> " . number_format($stats['documents']) . "</td></tr>";
echo "</table>";
echo "</div>";

?>

</div>
</body>
</html>