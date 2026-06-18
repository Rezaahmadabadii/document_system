<?php
session_name('doc_system');
session_start();

// فقط ادمین میتونه این کار رو انجام بده
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    die('شما دسترسی لازم را ندارید');
}

require_once 'config/database.php';

echo "<pre dir='rtl' style='font-family: Vazirmatn; background: #f0f0f0; padding: 20px; margin: 20px;'>";

try {
    // شروع تراکنش
    $db->beginTransaction();
    
    echo "⏳ شروع پاکسازی داده‌ها...\n\n";
    
    // 1. حذف اسناد
    $stmt = $db->exec("DELETE FROM documents");
    echo "✅ حذف " . $stmt . " رکورد از جدول documents\n";
    
    // 2. حذف درخواست‌های بازیابی
    $stmt = $db->exec("DELETE FROM revert_requests");
    echo "✅ حذف " . $stmt . " رکورد از جدول revert_requests\n";
    
    // 3. حذف تاییدیه‌های تحویل
    $stmt = $db->exec("DELETE FROM delivery_approvals");
    echo "✅ حذف " . $stmt . " رکورد از جدول delivery_approvals\n";
    
    // 4. حذف امضاهای ذخیره شده (فقط رکوردهای دیتابیس، فایل‌ها باقی می‌مانند)
    $stmt = $db->exec("DELETE FROM saved_signatures");
    echo "✅ حذف " . $stmt . " رکورد از جدول saved_signatures\n";
    
    // تایید تراکنش
    $db->commit();
    
    echo "\n✨ کلیه داده‌ها با موفقیت پاکسازی شدند.\n";
    echo "⚠️ توجه: کاربران و شرکت‌ها همچنان حفظ شده‌اند.\n";
    
} catch (Exception $e) {
    // در صورت خطا، برگرداندن تغییرات
    $db->rollBack();
    echo "❌ خطا در پاکسازی داده‌ها: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<br><a href='Index.php' style='font-family: Vazirmatn;'>بازگشت به صفحه اصلی</a>";
?>