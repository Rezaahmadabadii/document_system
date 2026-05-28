<?php
session_start();
require_once '../config/database.php';
require_once '../config/jdatetime.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;
$type = $_GET['type'] ?? 'pdf';

// دریافت محتوای HTML پرینت
ob_start();
include '../print.php';
$html = ob_get_clean();

if ($type === 'pdf') {
    // نیاز به کتابخانه TCPDF یا Dompdf
    // require_once 'vendor/autoload.php';
    // ... کد تولید PDF
}

echo $html;
?>