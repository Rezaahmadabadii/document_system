<?php
$db_host = 'localhost';
$db_name = 'document_system';
$db_user = 'root';
$db_pass = '';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    // فقط همین خط عوض شده - جزئیات خطا حذف شد
    die("خطا در اتصال به دیتابیس. لطفاً با مدیر سیستم تماس بگیرید.");
}
?>