<?php
echo "<h2>لیست دیتابیس‌های موجود در XAMPP</h2>";
echo "<hr>";

// اتصال به MySQL بدون انتخاب دیتابیس
$mysqli = new mysqli('localhost', 'root', '');

if ($mysqli->connect_error) {
    die("اتصال失敗: " . $mysqli->connect_error);
}

// دریافت لیست دیتابیس‌ها
$result = $mysqli->query("SHOW DATABASES");

echo "<ul>";
while ($row = $result->fetch_assoc()) {
    $dbname = $row['Database'];
    // نمایش دیتابیس‌های مربوط به پروژه‌های شما
    if (strpos($dbname, 'document') !== false || 
        strpos($dbname, 'invoice') !== false || 
        strpos($dbname, 'service') !== false ||
        $dbname == 'document_system' ||
        $dbname == 'invoice_system_v2' ||
        $dbname == 'service_system_v2') {
        echo "<li style='color: green; font-weight: bold;'>✅ $dbname</li>";
    } else {
        echo "<li style='color: gray;'>📁 $dbname</li>";
    }
}
echo "</ul>";

$mysqli->close();
?>