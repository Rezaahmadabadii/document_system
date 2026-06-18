<?php
session_name('doc_system');
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/jdatetime.class.php';

// ========== بررسی وجود جداول دیتابیس ==========
try {
    // چک کردن وجود جدول companies
    $check = $db->query("SHOW TABLES LIKE 'companies'");
    if ($check->rowCount() == 0) {
        throw new PDOException("جداول دیتابیس وجود ندارند");
    }
} catch (PDOException $e) {
    // نمایش پیام مناسب به جای خطای فنی
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <title>خطا در دیتابیس</title>
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
            .btn-back:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>⚠️ خطا در ارتباط با دیتابیس</h2>
            <p>دیتابیس سیستم به درستی تنظیم نشده است یا اطلاعات آن حذف شده است.<br>
            لطفاً با مدیر سیستم تماس بگیرید.</p>
            <a href="logout.php" class="btn-back">خروج و تلاش مجدد</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// ============================================

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;

// دریافت دسترسی کاربر به بایگانی کاربران
$can_view_all_archives = false;
if (!$is_admin) {
    $stmt = $db->prepare("SELECT can_view_all_archives FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $can_view_all_archives = $stmt->fetchColumn() == 1;
}

$require_doc_date = $_SESSION['require_doc_date'] ?? 1;
$lock_delivery_date = $_SESSION['lock_delivery_date'] ?? 0;
$fullname = $_SESSION['fullname'] ?? 'کاربر';
$unit_name = $_SESSION['unit_name'] ?? 'واحد نامشخص';

$today = jDateTime::date('Y/m/d', false, false, true);
$persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
$english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
$today = str_replace($persian, $english, $today);
$companies = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

if ($is_admin) {
    $users_list = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY unit_name")->fetchAll();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم بایگانی اسناد تایید شده</title>
	<link rel="icon" type="image/x-icon" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg">
    <script defer src="assets/js/all.min.js"></script>
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, 'Tahoma', sans-serif !important; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-size: 14px; }
        .main-header { background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid #e2e8f0; }
        .header-container { max-width: 1400px; margin: 0 auto; padding: 12px 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .logo h1 { font-size: 1.2rem; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo p { font-size: 0.6rem; color: #6c86a3; }
        .main-content { max-width: 1400px; margin: 24px auto; padding: 0 28px; }
        .documents-layout { display: flex; gap: 24px; align-items: flex-start; }
        .right-panel-panel { width: 360px; position: sticky; top: 90px; align-self: flex-start; }
        .left-panel-list { flex: 1; background: white; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden; max-height: calc(100vh - 120px); overflow-y: auto; }
        .main-panel-card { background: white; border-radius: 24px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); overflow: hidden; }
        .profile-section { padding: 16px 20px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; gap: 15px; }
        .user-avatar-large { width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .user-info-header h3 { font-size: 0.9rem; margin-bottom: 3px; }
        .user-info-header p { font-size: 0.65rem; opacity: 0.9; }
        .header-actions { margin-right: auto; display: flex; gap: 8px; }
        .icon-btn { background: rgba(255,255,255,0.2); border: none; color: white; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; }
        .icon-btn:hover { background: rgba(255,255,255,0.3); }
        .admin-menu { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 8px 12px; }
        .menu-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; margin: 4px 0; border-radius: 12px; cursor: pointer; transition: all 0.2s; font-size: 0.75rem; font-weight: 500; color: #475569; }
        .menu-item i { width: 24px; font-size: 0.9rem; color: #667eea; }
        .menu-item:hover { background: #eef2ff; color: #667eea; }
        .menu-item.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .menu-item.active i { color: white; }
        .left-content { padding: 16px; }
        .left-section-title { font-size: 0.85rem; font-weight: 700; color: #1a2c3e; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #667eea; display: flex; align-items: center; gap: 8px; }
        
        /* ========== استایل جدید آمار کاربر عادی ========== */
        .stats-grid-new { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 15px; }
        .stat-card-new { background: linear-gradient(135deg, #ffffff, #eff6ff); border-radius: 20px; padding: 14px 12px; border: 1px solid #dbeafe; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .stat-card-new:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(59,130,246,0.2); border-color: #93c5fd; }
        .stat-card-new-primary { background: linear-gradient(135deg, #ffffff, #eff6ff); border-radius: 20px; padding: 14px 12px; border: 1px solid #dbeafe; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .stat-card-new-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(59,130,246,0.2); border-color: #93c5fd; }
        .stat-card-new-primary .stat-header { border-bottom-color: #dbeafe; }
        .stat-card-new-primary .stat-title { color: #1e40af; }
        .stat-card-new-primary .stat-icon { color: #3b82f6; }
        .stat-header { display: flex; align-items: center; gap: 6px; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px dashed #dbeafe; }
        .stat-icon { font-size: 0.85rem; }
        .stat-title { font-size: 0.65rem; font-weight: 600; color: #1e40af; }
        .stat-main { display: flex; align-items: baseline; justify-content: center; gap: 12px; margin-bottom: 8px; }
        .stat-main-center { text-align: center; margin-bottom: 8px; }
        .stat-big { font-size: 1.5rem; font-weight: 800; color: #1e293b; }
        .stat-sep { font-size: 1rem; color: #93c5fd; }
        .stat-today, .stat-yesterday { text-align: center; background: #dbeafe; padding: 4px 12px; border-radius: 30px; min-width: 60px; }
        .stat-small-label { font-size: 0.5rem; color: #1e40af; display: block; }
        .stat-small-value { font-size: 0.8rem; font-weight: 700; color: #1e3a8a; }
        .stat-trend { font-size: 0.65rem; font-weight: 600; text-align: center; margin-top: 6px; padding-top: 6px; border-top: 1px solid #dbeafe; }
        .stat-companies { display: flex; flex-direction: column; gap: 8px; }
        .company-most, .company-least { display: flex; align-items: center; justify-content: space-between; font-size: 0.65rem; }
        .company-label { font-weight: 500; color: #1e40af; }
        .company-name { font-weight: 700; }
        .company-count { font-size: 0.55rem; color: #64748b; }
        .avg-stats-new { display: flex; justify-content: space-around; gap: 10px; }
        .avg-item-new { display: flex; align-items: center; gap: 6px; background: #dbeafe; padding: 8px 12px; border-radius: 40px; flex: 1; justify-content: center; }
        .avg-item-new.avg-change-up { background: #d1fae5; }
        .avg-item-new.avg-change-down { background: #fee2e2; }
        .avg-item-new.avg-change-neutral { background: #fef3c7; }
        .avg-icon { font-size: 0.7rem; color: #1e40af; }
        .avg-value { font-size: 0.7rem; font-weight: 700; color: #1e3a8a; }
        .avg-change-up .avg-value { color: #065f46; }
        .avg-change-down .avg-value { color: #991b1b; }
        .avg-change-neutral .avg-value { color: #b45309; }
        .avg-label { font-size: 0.55rem; color: #64748b; }
        @media (max-width: 640px) { .stats-grid-new { grid-template-columns: 1fr; gap: 10px; } .avg-stats-new { flex-wrap: wrap; } .avg-item-new { flex: auto; } }
        
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        .data-table th { background: #f1f5f9; padding: 10px 8px; border: 1px solid #e2e8f0; font-weight: 700; color: #475569; text-align: center; }
        .data-table td { padding: 8px 6px; border: 1px solid #e2e8f0; text-align: center; color: #334155; }
        .data-table tr:hover { background: #f8fafc; }
        .docs-list { padding: 16px; }
        .doc-group { background: white; border-radius: 16px; margin-bottom: 20px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 1px solid #eef2f5; }
        .group-title { padding: 12px 16px; background: linear-gradient(135deg, #f8fafc, #fff); display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #667eea; flex-wrap: wrap; gap: 8px; }
        .group-date { font-weight: 700; font-size: 0.85rem; color: #667eea; }
        .print-btn { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 5px 14px; border-radius: 20px; text-decoration: none; font-size: 0.65rem; font-weight: 600; }
        .print-btn:hover { background: linear-gradient(135deg, #059669, #047857); }
        .action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; }
        .edit-btn { color: #667eea; }
        .delete-btn { color: #ef4444; }
        .empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 0.75rem; }
        .form-section { padding: 14px 16px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 0.7rem; font-weight: 600; color: #475569; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 0.75rem; }
        .form-group input:disabled, .form-group textarea:disabled { background: #eef2f5; color: #94a3b8; cursor: not-allowed; }
        .btn-submit, .btn-green { width: 100%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 9px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; cursor: pointer; margin-top: 8px; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-green { background: linear-gradient(135deg, #10b981, #059669); margin-top: 0; }
        .btn-green:disabled { opacity: 0.5; cursor: not-allowed; }
        .admin-filter-box { padding: 14px 16px; background: #f8fafc; border-bottom: 1px solid #eef2f5; display: none; }
        .admin-filter-box.visible { display: block; }
        .admin-filter-group { margin-bottom: 12px; }
        .admin-filter-group label { display: block; font-size: 0.65rem; font-weight: 600; color: #6c86a3; margin-bottom: 4px; }
        .admin-filter-group select, .admin-filter-group input { width: 100%; padding: 8px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.7rem; }
        .user-panel-buttons { display: flex; gap: 8px; padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #eef2f5; }
        .user-panel-btn { flex: 1; padding: 8px; border: none; border-radius: 10px; font-size: 0.7rem; font-weight: 600; cursor: pointer; background: #e2e8f0; color: #475569; transition: all 0.2s; }
        .user-panel-btn.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .user-form-panel { display: block; }
        .user-search-panel { display: none; }
        .user-select-for-stats { padding: 12px 16px; border-bottom: 1px solid #eef2f5; background: #f8fafc; }
        .user-select-for-stats label { font-size: 0.7rem; font-weight: 600; display: block; margin-bottom: 5px; }
        .user-select-for-stats select { width: 100%; padding: 8px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .descriptions-section { margin-top: 15px; padding: 12px; background: #fefce8; border-radius: 12px; border-right: 3px solid #eab308; }
        .desc-title { font-size: 0.7rem; font-weight: 700; color: #854d0e; margin-bottom: 8px; }
        .desc-item { background: #fffbeb; padding: 6px 10px; border-radius: 8px; margin-bottom: 5px; font-size: 0.65rem; color: #78350f; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 24px; padding: 24px; width: 500px; max-width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-content h3 { margin-bottom: 15px; font-size: 1rem; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 8px 16px; border-radius: 10px; border: none; cursor: pointer; font-size: 0.7rem; }
        .btn-secondary { background: #e2e8f0; color: #475569; padding: 8px 16px; border-radius: 10px; border: none; cursor: pointer; font-size: 0.7rem; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #1a2c3e; color: white; padding: 8px 18px; border-radius: 12px; font-size: 0.7rem; z-index: 1100; display: none; }
        .scrollable-list { max-height: 450px; overflow-y: auto; }
        .companies-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .company-card { background: #f8fafc; border-radius: 12px; padding: 10px; text-align: center; border: 1px solid #eef2f5; display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .company-card .company-name { font-size: 0.7rem; font-weight: 600; display: block; }
        .company-card div { display: flex; gap: 6px; justify-content: center; }
        .user-item { background: #f8fafc; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .user-info { font-size: 0.7rem; }
        .user-name { font-weight: 700; color: #1a2c3e; }
        .user-unit { color: #6c86a3; font-size: 0.6rem; }
        .date-spinner:hover { background: #667eea !important; color: white !important; border-color: #667eea !important; }
        @media (max-width: 1400px) { .companies-grid { grid-template-columns: repeat(6, 1fr); } }
        @media (max-width: 1200px) { .companies-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 900px) { .documents-layout { flex-direction: column; } .right-panel-panel { width: 100%; position: relative; top: 0; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .companies-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .companies-grid { grid-template-columns: repeat(1, 1fr); } }
		
        /* ========== استایل بایگانی گروهی (تاریخ محور) با رنگ جذاب ========== */
        .archive-stats-container { display: flex; flex-direction: column; gap: 16px; }
        .archive-date-card { background: linear-gradient(135deg, #ffffff, #fef9f0); border-radius: 24px; border: 1px solid rgba(249, 115, 22, 0.15); overflow: hidden; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .archive-date-card:hover { transform: translateY(-3px); box-shadow: 0 20px 30px -12px rgba(249, 115, 22, 0.2); border-color: #f97316; }
        .archive-date-header { background: linear-gradient(135deg, #fff7ed, #ffedd5); padding: 14px 18px; border-bottom: 2px solid #fed7aa; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .archive-date-title { font-size: 0.9rem; font-weight: 800; color: #9a3412; background: white; padding: 5px 16px; border-radius: 40px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .archive-date-title i { color: #f97316; font-size: 0.9rem; }
        .archive-count-badge { background: #f97316; color: white; padding: 4px 12px; border-radius: 30px; font-size: 0.65rem; font-weight: 600; box-shadow: 0 2px 6px rgba(249,115,22,0.3); }
        .archive-users-list { padding: 14px 18px; display: flex; flex-wrap: wrap; gap: 12px; background: #ffffff; }
        .archive-user-tag { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #fef9f0, #ffffff); border: 1px solid #fed7aa; border-radius: 40px; padding: 6px 14px 6px 8px; transition: all 0.2s; cursor: pointer; }
        .archive-user-tag:hover { background: #fff7ed; border-color: #f97316; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(249,115,22,0.15); }
        .archive-user-avatar { width: 26px; height: 26px; background: linear-gradient(135deg, #f97316, #ea580c); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.65rem; font-weight: bold; box-shadow: 0 2px 6px rgba(234,88,12,0.3); }
        .archive-user-name { font-size: 0.7rem; font-weight: 600; color: #431407; }
        .archive-view-btn { background: #fef3c7; border: none; color: #f97316; font-size: 0.7rem; cursor: pointer; padding: 5px 10px; border-radius: 30px; transition: all 0.2s; }
        .archive-view-btn:hover { background: #f97316; color: white; transform: scale(1.02); }
        @media (max-width: 640px) { .archive-date-header { flex-direction: column; align-items: flex-start; } .archive-users-list { gap: 10px; } }
        
        /* ========== استایل کارت کاربران با رنگ جذاب ========== */
        .user-stats-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
        @media (max-width: 768px) { .user-stats-container { grid-template-columns: 1fr; } }
        .user-card { background: linear-gradient(135deg, #ffffff, #faf9ff); border-radius: 20px; padding: 12px; transition: all 0.25s ease; border: 1px solid rgba(102,126,234,0.15); box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .user-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px -10px rgba(102,126,234,0.25); border-color: #818cf8; background: white; }
        .user-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #818cf8, #c084fc); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(129,140,248,0.4); }
        .user-details h4 { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin: 0 0 2px 0; }
        .user-details .user-unit { font-size: 0.55rem; color: #64748b; background: #eef2ff; padding: 2px 10px; border-radius: 20px; display: inline-block; }
        .user-first-register { font-size: 0.55rem; color: #64748b; margin-top: 5px; display: flex; align-items: center; gap: 4px; }
        .user-first-register i { font-size: 0.45rem; color: #94a3b8; }
        .user-stats-badges { display: flex; gap: 8px; }
        .stat-badge { text-align: center; background: linear-gradient(135deg, #f8fafc, #f1f5f9); padding: 5px 8px; border-radius: 14px; min-width: 55px; border: 1px solid #e2e8f0; }
        .stat-badge .stat-value { font-size: 0.9rem; font-weight: 800; background: linear-gradient(135deg, #1e293b, #334155); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-badge .stat-label { font-size: 0.5rem; color: #64748b; margin-top: 2px; }
        .user-card-middle { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 8px; flex-wrap: wrap; }
        .trend-chip { display: inline-flex; align-items: center; gap: 5px; background: #f8fafc; padding: 4px 12px; border-radius: 30px; font-size: 0.6rem; border: 1px solid #e2e8f0; }
        .trend-up { color: #10b981; font-weight: 700; }
        .trend-down { color: #ef4444; font-weight: 700; }
        .trend-neutral { color: #f59e0b; font-weight: 700; }
        .trend-label { color: #64748b; font-size: 0.55rem; margin-right: 3px; }
        .avg-chips { display: flex; gap: 6px; }
        .avg-chip { background: #f8fafc; padding: 4px 10px; border-radius: 30px; display: inline-flex; align-items: center; gap: 5px; font-size: 0.6rem; font-weight: 500; border: 1px solid #e2e8f0; }
        .avg-chip span:first-child { font-size: 0.7rem; }
        .avg-up { color: #10b981; font-weight: 600; }
        .avg-down { color: #ef4444; font-weight: 600; }
        .avg-neutral { color: #f59e0b; font-weight: 600; }
        .avg-label { color: #64748b; font-size: 0.55rem; }
        @media (max-width: 640px) { .user-card-header { flex-wrap: wrap; gap: 10px; } .user-stats-badges { flex: 1; justify-content: flex-end; } .user-card-middle { flex-direction: column; align-items: flex-start; } }
        
        /* ========== استایل بایگانی کاربر عادی (دکمه‌های گرد کوچک) ========== */
        .archive-months-container { display: flex; flex-direction: column; gap: 16px; }
        .archive-month-card { background: white; border-radius: 20px; border: 1px solid #eef2ff; overflow: hidden; }
        .archive-month-header { background: linear-gradient(135deg, #f8fafc, #ffffff); padding: 10px 16px; border-bottom: 1px solid #eef2ff; display: flex; justify-content: space-between; align-items: center; }
        .archive-month-title { font-size: 0.85rem; font-weight: 700; color: #1e293b; display: inline-flex; align-items: center; gap: 6px; }
        .archive-month-title i { color: #667eea; }
        .archive-month-count { background: #eef2ff; color: #667eea; padding: 2px 10px; border-radius: 20px; font-size: 0.6rem; font-weight: 500; }
        .archive-days-list { padding: 12px 16px; display: flex; flex-wrap: wrap; gap: 10px; }
        .archive-day-item { display: inline-flex; align-items: center; justify-content: center; gap: 4px; background: #f1f5f9; border-radius: 40px; padding: 6px 12px; cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; }
        .archive-day-item:hover { background: #eef2ff; border-color: #667eea; transform: translateY(-2px); }
        .archive-day-number { font-size: 0.7rem; font-weight: 600; color: #1e293b; }
        .archive-day-eye { font-size: 0.6rem; color: #667eea; opacity: 0.7; }
        .archive-day-item:hover .archive-day-eye { opacity: 1; }
        @media (max-width: 480px) { .archive-days-list { gap: 8px; } .archive-day-item { padding: 4px 10px; } }
        
        /* ========== استایل رادیو باتن بایگانی کاربران ========== */
        .radio-group { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .radio-label { display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; padding: 5px 12px; border-radius: 40px; font-size: 0.7rem; cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; }
        .radio-label:hover { background: #eef2ff; border-color: #818cf8; transform: translateY(-1px); }
        .radio-label input[type="radio"] { width: 12px; height: 12px; margin: 0; cursor: pointer; accent-color: #818cf8; }
        .radio-label span { color: #334155; font-weight: 500; }
        .radio-label:has(input:checked) { background: linear-gradient(135deg, #818cf8, #c084fc); border-color: #818cf8; box-shadow: 0 2px 8px rgba(129,140,248,0.3); }
        .radio-label:has(input:checked) span { color: white; }
        
        /* ========== استایل کارت بیشترین و کمترین سند ========== */
        .max-user-card, .min-user-card { border-radius: 12px; padding: 12px 15px; display: flex; align-items: center; gap: 12px; transition: all 0.2s; cursor: default; }
        .max-user-card { background: #d1fae5; border: 1px solid #10b981; }
        .min-user-card { background: #fee2e2; border: 1px solid #ef4444; }
        .max-user-card i, .min-user-card i { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.2rem; }
        .max-user-card i { background: #10b98120; color: #059669; }
        .min-user-card i { background: #ef444420; color: #dc2626; }
        .max-user-card .info, .min-user-card .info { flex: 1; }
        .max-user-card .stat-label, .min-user-card .stat-label { font-size: 0.65rem; font-weight: 500; margin-bottom: 6px; }
        .max-user-card .stat-label { color: #065f46; }
        .min-user-card .stat-label { color: #991b1b; }
        .max-user-card .user-detail, .min-user-card .user-detail { display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .max-user-card .stat-value, .min-user-card .stat-value { font-weight: 700; font-size: 0.9rem; }
        .max-user-card .stat-value { color: #065f46; }
        .min-user-card .stat-value { color: #991b1b; }
        .max-user-card .stat-count, .min-user-card .stat-count { font-size: 0.6rem; padding: 2px 10px; border-radius: 20px; font-weight: 500; }
        .max-user-card .stat-count { background: #10b98120; color: #047857; }
        .min-user-card .stat-count { background: #ef444420; color: #b91c1c; }
        .max-user-card .user-first-date, .min-user-card .user-first-date { font-size: 0.55rem; margin-top: 6px; opacity: 0.8; display: flex; align-items: center; gap: 4px; }
        .max-user-card .user-first-date { color: #065f46; }
        .min-user-card .user-first-date { color: #991b1b; }
        .max-user-card .user-first-date i, .min-user-card .user-first-date i { font-size: 0.5rem; width: auto; height: auto; background: transparent; }
        
        /* ========== استایل باکس کل اسناد ========== */
        .total-stats-card { background: linear-gradient(135deg, #ffffff, #eff6ff); border-radius: 16px; padding: 12px 15px; border: 1px solid #dbeafe; text-align: center; transition: all 0.2s; }
        .total-stats-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(59,130,246,0.2); border-color: #93c5fd; }
        .total-stats-card .stat-title { font-size: 0.7rem; font-weight: 600; color: #1e40af; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .total-stats-card .stat-big { font-size: 1.1rem; font-weight: 800; color: #1e293b; background: #e0e7ff; padding: 2px 10px; border-radius: 20px; }
        .total-stats-card .stat-comparison { display: flex; flex-direction: column; gap: 8px; margin-top: 15px; }
        .comparison-item { font-size: 0.65rem; background: #f8fafc; padding: 6px 10px; border-radius: 20px; text-align: center; border: 1px solid #eef2ff; }
        .comparison-item i { margin-left: 5px; color: #667eea; }
        
        /* ========== استایل باکس مقایسه بیشترین و کمترین ========== */
        .compare-stats-card { background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .compare-stats-header { background: #f8fafc; padding: 10px 12px; font-size: 0.7rem; font-weight: 600; color: #475569; border-bottom: 1px solid #eef2ff; display: flex; align-items: center; gap: 6px; }
        .compare-stats-header i { color: #667eea; }
        .compare-stats-content { padding: 10px 12px; }
        .compare-item { display: flex; align-items: center; gap: 10px; }
        .compare-icon i { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1rem; }
        .max-compare .compare-icon i { background: #d1fae5; color: #059669; }
        .min-compare .compare-icon i { background: #fee2e2; color: #dc2626; }
        .compare-info { flex: 1; }
        .compare-label { font-size: 0.55rem; color: #94a3b8; }
        .compare-name { font-size: 0.75rem; font-weight: 700; color: #1e293b; }
        .compare-date { font-size: 0.5rem; color: #94a3b8; margin-top: 3px; display: flex; align-items: center; gap: 3px; }
        .compare-date i { font-size: 0.45rem; }
        .compare-number { font-size: 1.1rem; font-weight: 800; background: #f1f5f9; padding: 4px 10px; border-radius: 20px; min-width: 55px; text-align: center; }
        .max-compare .compare-number { background: #d1fae5; color: #047857; }
        .min-compare .compare-number { background: #fee2e2; color: #b91c1c; }
        .compare-divider { height: 1px; background: #eef2ff; margin: 10px 0; }
        /* ========== پایان استایل‌ها ========== */
    </style>
</head>
<body>

<div class="main-header">
    <div class="header-container">
        <div class="logo">
            <h1><i class="fas fa-file-alt"></i> سیستم بایگانی اسناد تایید شده</h1>
            <p>سامانه هوشمند بایگانی و تایید اسناد</p>
        </div>
    </div>
</div>

<div class="main-content">
    <div class="documents-layout">
        <div class="right-panel-panel">
            <div class="main-panel-card">
                <div class="profile-section">
                    <div class="user-avatar-large"><i class="fas fa-user-circle"></i></div>
                    <div class="user-info-header">
                        <h3><?php echo htmlspecialchars($fullname); ?></h3>
                        <p><?php echo htmlspecialchars($unit_name); ?></p>
                    </div>
                    <div class="header-actions">
                        <button class="icon-btn" onclick="showChangePasswordModal()" title="تغییر رمز"><i class="fas fa-key"></i></button>
                        <button class="icon-btn" onclick="window.location.href='logout.php'" title="خروج"><i class="fas fa-sign-out-alt"></i></button>
                    </div>
                </div>
                
                <?php if($is_admin): ?>
                <div class="admin-menu">
                    <div class="menu-item active" data-section="stats" onclick="showLeftContent('stats')"><i class="fas fa-chart-line"></i> آمار کاربران</div>
                    <div class="menu-item" data-section="users" onclick="showLeftContent('users')"><i class="fas fa-users"></i> مدیریت کاربران</div>
                    <div class="menu-item" data-section="companies" onclick="showLeftContent('companies')"><i class="fas fa-building"></i> مدیریت شرکت‌ها</div>
                    <div class="menu-item" data-section="filters" onclick="showLeftContent('filters')"><i class="fas fa-filter"></i> جستجوی اسناد</div>
                    <div class="menu-item" data-section="approvals" onclick="showLeftContent('approvals')"><i class="fas fa-check-double"></i> تاییدات نهایی</div>
                    <div class="menu-item" data-section="archive" onclick="showLeftContent('archive')"><i class="fas fa-archive"></i> بایگانی</div>
					<div class="menu-item" onclick="window.open('export_excel.php', '_blank')"><i class="fas fa-file-excel"></i> خروجی اکسل</div>
                </div>
                                
                <div id="adminFiltersPanel" class="admin-filter-box">
                    <div class="admin-filter-group"><label><i class="fas fa-user"></i> انتخاب کاربر</label><select id="admin_user_select"><option value="">همه کاربران</option><?php foreach($users_list as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-filter-group"><label><i class="fas fa-hashtag"></i> شماره سند</label><input type="text" id="admin_filter_number" placeholder="جستجو..."></div>
                    <div class="admin-filter-group"><label><i class="fas fa-calendar"></i> تاریخ سند</label><input type="text" id="admin_filter_date" placeholder="1404/01/01"></div>
                    <div class="admin-filter-group"><label><i class="fas fa-building"></i> شرکت</label><select id="admin_filter_company"><option value="">همه شرکت‌ها</option><?php $companies_list = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY id ASC")->fetchAll(); foreach($companies_list as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-filter-group"><label><i class="fas fa-calendar-check"></i> تاریخ تحویل</label><input type="text" id="admin_filter_delivery" placeholder="1404/01/01"></div>
                </div>
                
                <?php else: ?>
                <div class="user-panel-buttons">
                    <button class="user-panel-btn active" data-panel="form" onclick="toggleUserPanel('form')">📝 ثبت سند جدید</button>
                    <button class="user-panel-btn" data-panel="archive" onclick="toggleUserPanel('archive')">📦 بایگانی من</button>
                    <?php if($can_view_all_archives): ?>
                    <button class="user-panel-btn" data-panel="admin_archive" onclick="toggleUserPanel('admin_archive')">📂 بایگانی کاربران</button>
                    <?php endif; ?>
                </div>
                
                <div id="userFormPanel" class="user-form-panel">
                    <div class="form-section">
                        <div class="form-group"><label>تاریخ تحویل</label><div style="display: flex; align-items: center; gap: 8px;"><button type="button" id="dateMinus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">−</button><input type="text" id="delivery_date" readonly style="flex: 1; text-align: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 8px 12px; font-size: 0.9rem; font-weight: 600;"><button type="button" id="datePlus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">+</button></div></div>
                        <div class="form-group"><div style="display: flex; gap: 8px; align-items: center;"><input type="number" id="company_number" min="1" max="<?php echo count($companies); ?>" style="width: 70px; text-align: center; padding: 8px; border: 1.5px solid #e2e8f0; border-radius: 12px;" placeholder="#"><select id="company_id" style="flex: 1;"><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div></div>
                        <div class="form-group"><label>شماره سند</label><input type="text" id="doc_number" placeholder="INV-12345"></div>
                        <div class="form-group" id="date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>><label>تاریخ سند</label><input type="text" id="doc_date" placeholder="1405/02/30"></div>
                        <button class="btn-submit" id="submitBtn" onclick="saveDocument()">✓ ثبت سند</button>
                    </div>
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 0;">
                        <div class="form-group"><label>گزارش / یادداشت</label><textarea id="doc_description" rows="3" placeholder="هرگونه توضیح یا گزارش اضافی..."></textarea></div>
                        <button class="btn-green" id="submitDescriptionBtn" onclick="addDescriptionToDocument()">✏️ ثبت توضیح</button>
                    </div>
                    
                    <!-- فیلدهای جستجو بعد از دکمه ثبت توضیح -->
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 12px;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 12px; padding-right: 4px;"><i class="fas fa-search"></i> جستجوی اسناد</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group" style="margin-bottom: 0;"><label>شماره سند</label><input type="text" id="filter_number" placeholder="شماره سند..." oninput="autoSearchDocuments()"></div>
                            <div class="form-group" style="margin-bottom: 0;"><label>تاریخ سند</label><input type="text" id="filter_date" placeholder="1404/01/01" oninput="autoSearchDocuments()"></div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group" style="margin-bottom: 0;"><label>شرکت</label><select id="filter_company" onchange="autoSearchDocuments()"><option value="">همه شرکت‌ها</option><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group" style="margin-bottom: 0;"><label>تاریخ تحویل</label><input type="text" id="filter_delivery" placeholder="1404/01/01" oninput="autoSearchDocuments()"></div>
                        </div>
                    </div>
                </div>
                
                <div id="userArchivePanel" class="user-search-panel" style="display:none;">
                    <div style="padding: 14px 16px;">
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ========== Footer در پنل راست ========== -->
                <div class="right-panel-footer" style="margin-top: 20px; padding: 12px 16px; background: #f8fafc; border-top: 1px solid #eef2ff; text-align: center;">
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <div>
                            <img src="/document_system/assets/logo.png" alt="لوگو" style="max-height: 35px; width: auto;" onerror="this.style.display='none'">
                        </div>
                        <div>
                            <p style="margin: 0; font-size: 0.65rem; color: #64748b;">
                                <i class="fas fa-code"></i> توسعه توسط Reza.ahmadabadi | 
                                <i class="fas fa-phone"></i> 09353984864
                            </p>
                        </div>
                    </div>
                </div>
                <!-- ========== پایان Footer ========== -->
                
            </div>
        </div>

        <div class="left-panel-list">
            <div id="leftContentArea">
                <?php if($is_admin): ?>
                <div id="statsContent" class="left-content">
                    <div class="left-section-title">
                        <i class="fas fa-chart-bar"></i> آمار کاربران
                        <span class="today-badge" style="font-size: 0.6rem; background: #e2e8f0; padding: 2px 8px; border-radius: 20px; margin-right: 10px;" id="statsTodayDate"></span>
                    </div>
                    
                    <!-- کارت بیشترین، کل تایید شده‌ها و کمترین -->
                    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <!-- یک باکس واحد برای بیشترین و کمترین -->
                        <div id="compareStatsCard" class="compare-stats-card" style="flex: 0.3;">
                            <div class="compare-stats-header">
                                <i class="fas fa-chart-simple"></i> مقایسه کاربران
                            </div>
                            <div class="compare-stats-content">
                                <div class="compare-item max-compare">
                                    <div class="compare-icon"><i class="fas fa-trophy"></i></div>
                                    <div class="compare-info">
                                        <div class="compare-label">بیشترین سند</div>
                                        <div class="compare-name" id="maxUserName">-</div>
                                        <div class="compare-date"><i class="fas fa-calendar-alt"></i> شروع ثبت از : <span id="maxUserFirstDateText">-</span></div>
                                    </div>
                                    <div class="compare-number" id="maxUserCount">0</div>
                                </div>
                                <div class="compare-divider"></div>
                                <div class="compare-item min-compare">
                                    <div class="compare-icon"><i class="fas fa-chart-line"></i></div>
                                    <div class="compare-info">
                                        <div class="compare-label">کمترین سند</div>
                                        <div class="compare-name" id="minUserName">-</div>
                                        <div class="compare-date"><i class="fas fa-calendar-alt"></i> شروع ثبت از : <span id="minUserFirstDateText">-</span></div>
                                    </div>
                                    <div class="compare-number" id="minUserCount">0</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- باکس کل اسناد تایید شده -->
                        <div id="totalStatsCard" class="total-stats-card" style="flex: 0.4;">
                            <div class="stat-title">
                                <span><i class="fas fa-check-circle"></i> کل اسناد تایید شده</span>
                                <span class="stat-big" id="totalApprovedCount">0</span>
                            </div>
                            <div class="stat-comparison">
                                <span class="comparison-item" id="vsYesterday"><i class="fas fa-calendar-day"></i> امروز : -</span>
                                <span class="comparison-item" id="vsLastWeek"><i class="fas fa-calendar-week"></i> هفته : -</span>
                                <span class="comparison-item" id="vsLastMonth"><i class="fas fa-calendar-alt"></i> ماه : -</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- لیست کاربران -->
                    <div class="left-section-title" style="margin-top: 10px;"><i class="fas fa-users"></i> لیست کاربران</div>
                    <div id="adminUsersStatsList" class="scrollable-list" style="max-height: 350px; overflow-y: auto;">
                        <div class="empty-state">در حال بارگذاری...</div>
                    </div>
                </div>
                <div id="usersContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-users"></i> مدیریت کاربران<button class="btn-primary" style="padding:4px 12px; font-size:0.65rem; margin-right:auto;" onclick="showAddUserModal()">+ افزودن کاربر</button></div><div class="scrollable-list" id="usersList"><div class="empty-state">در حال بارگذاری...</div></div></div>
                <div id="companiesContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-building"></i> مدیریت شرکت‌ها<button class="btn-primary" style="padding:4px 12px; font-size:0.65rem; margin-right:auto;" onclick="showAddCompanyModal()">+ افزودن شرکت</button></div><div class="companies-grid" id="companiesList"><?php foreach($companies as $c): ?><div class="company-card"><span class="company-name"><?php echo htmlspecialchars($c['name']); ?></span><div><button class="action-btn edit-btn" onclick="editCompany(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['name']); ?>')"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="toggleCompany(<?php echo $c['id']; ?>, 0)"><i class="fas fa-trash-alt"></i></button></div></div><?php endforeach; ?></div></div>
                <div id="filtersContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-list-ul"></i> نتیجه جستجو</div><div id="adminDocumentsList" class="docs-list"><div class="empty-state">برای جستجو از فیلدهای سمت راست استفاده کنید</div></div></div>
                <div id="approvalsContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-check-double"></i> تایید نهایی اسناد</div><div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;"><button class="tab-btn active" onclick="showApprovalsTab('pending')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#667eea; border-bottom:2px solid #667eea;">⏳ در انتظار تایید</button><button class="tab-btn" onclick="showApprovalsTab('revert')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#475569;">🔄 درخواست بازیابی</button><button class="tab-btn" onclick="showApprovalsTab('approved')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#475569;">✅ تایید شده‌ها</button></div><div id="pendingApprovalsTab"><div id="usersPendingList"><div class="empty-state">در حال بارگذاری...</div></div><div id="userDatesContainer" style="display:none; margin-top: 20px;"><div class="left-section-title"><i class="fas fa-calendar"></i> تاریخ‌های تحویل<button class="btn-secondary" onclick="backToUsersList()" style="margin-right:auto; padding:4px 12px;">← بازگشت</button></div><div id="userDatesList"></div></div></div><div id="revertRequestsTab" style="display:none;"><div id="revertRequestsList"><div class="empty-state">در حال بارگذاری...</div></div></div><div id="approvedApprovalsTab" style="display:none;"><div id="approvedApprovalsList"><div class="empty-state">در حال بارگذاری...</div></div></div></div>
                <div id="archiveContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-archive"></i> بایگانی اسناد تایید شده</div><div id="archiveList"><div class="empty-state">در حال بارگذاری...</div></div></div>
                <?php else: ?>
                <div id="userStatsContent" class="left-content">
                    <div class="left-section-title"><i class="fas fa-chart-line"></i> آمار شما</div>
                    <div id="userStatsContainer"><div class="empty-state">در حال بارگذاری...</div></div>
                </div>
                <div id="userDocumentsList" class="docs-list left-content" style="margin-top: 15px;">
                    <div class="empty-state">برای جستجو از فیلدهای سمت راست استفاده کنید</div>
                </div>
                <div id="userArchiveList" class="archive-list left-content" style="margin-top: 15px; display: none;">
                    <div class="left-section-title"><i class="fas fa-archive"></i> تاریخ‌های تایید شده</div>
                    <div id="archive_list_content" style="margin-top: 10px;">
                        <div class="empty-state">در حال بارگذاری...</div>
                    </div>
                </div>
                <div id="userAdminArchiveList" class="archive-list left-content" style="margin-top: 15px; display: none;">
                    <div class="left-section-title"><i class="fas fa-users"></i> بایگانی کاربران</div>
                    <div id="admin_archive_list_container" style="margin-top: 10px;">
                        <div class="empty-state">در حال بارگذاری...</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- ========== بدون footer در پنل چپ ========== -->
        </div>
    </div>
</div>

<!-- مودال‌ها -->
<div id="passwordModal" class="modal"><div class="modal-content"><h3>تغییر رمز عبور</h3><div class="form-group"><input type="password" id="old_password" placeholder="رمز فعلی" style="width:100%;"></div><div class="form-group"><input type="password" id="new_password" placeholder="رمز جدید" style="width:100%;"></div><div class="form-group"><input type="password" id="confirm_password" placeholder="تکرار رمز جدید" style="width:100%;"></div><div class="modal-buttons"><button class="btn-primary" onclick="changePassword()">تغییر رمز</button><button class="btn-secondary" onclick="closePasswordModal()">انصراف</button></div></div></div>
<div id="editModal" class="modal"><div class="modal-content"><h3>ویرایش سند</h3><input type="hidden" id="edit_id"><div class="form-group"><label>شرکت</label><select id="edit_company_id" style="width:100%;"><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>شماره سند</label><input type="text" id="edit_number" style="width:100%;"></div><div class="form-group" id="edit_date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>><label>تاریخ سند</label><input type="text" id="edit_date" style="width:100%;" placeholder="1405/02/30"></div><div class="modal-buttons"><button class="btn-primary" onclick="saveEdit()">ذخیره</button><button class="btn-secondary" onclick="closeEditModal()">انصراف</button></div></div></div>
<div id="userModal" class="modal"><div class="modal-content"><h3 id="userModalTitle">افزودن کاربر جدید</h3><input type="hidden" id="edit_user_id"><div class="form-group"><label>نام کاربری</label><input type="text" id="user_username" style="width:100%;"></div><div class="form-group"><label>نام کامل</label><input type="text" id="user_fullname" style="width:100%;"></div><div class="form-group"><label>واحد</label><input type="text" id="user_unit" style="width:100%;"></div><div class="form-group"><label>رمز عبور</label><input type="password" id="user_password" placeholder="برای ویرایش خالی بگذارید" style="width:100%;"></div><div class="form-group"><label><input type="checkbox" id="user_require_date" checked> تاریخ سند اجباری باشد</label></div><div class="modal-buttons"><button class="btn-primary" onclick="saveUser()">ذخیره</button><button class="btn-secondary" onclick="closeUserModal()">انصراف</button></div></div></div>
<div id="companyModal" class="modal"><div class="modal-content"><h3 id="companyModalTitle">افزودن شرکت جدید</h3><input type="hidden" id="edit_company_id"><div class="form-group"><label>نام شرکت</label><input type="text" id="company_name" style="width:100%;"></div><div class="modal-buttons"><button class="btn-primary" onclick="saveCompany()">ذخیره</button><button class="btn-secondary" onclick="closeCompanyModal()">انصراف</button></div></div></div>

<div id="toast" class="toast"></div>

<script>
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const requireDocDate = <?php echo $require_doc_date ? 'true' : 'false'; ?>;
const apiUrl = 'api/ajax.php';
let searchTimeout;
let currentDeliveryDate = '';
let currentPanel = 'form';

// ========== تابع checkLockStatus در ابتدا ==========
async function checkLockStatus(deliveryDate) {
    try {
        let res = await fetch(`${apiUrl}?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`);
        let data = await res.json();
        let docNumberInput = document.getElementById('doc_number');
        let descInput = document.getElementById('doc_description');
        if (docNumberInput) docNumberInput.disabled = data.has_admin_approval === true;
        if (descInput) descInput.disabled = data.has_admin_approval === true;
    } catch(e) { console.error(e); }
}

function showToast(msg, isError = false) {
    let toast = document.getElementById('toast');
    toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i> ${msg}`;
    toast.style.background = isError ? '#ef4444' : '#10b981';
    toast.style.display = 'flex';
    setTimeout(() => toast.style.display = 'none', 2500);
}

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}

function formatJalali(input) {
    let raw = input.value.replace(/\D/g, '');
    if (raw.length === 0) { input.value = ''; return; }
    if (raw.length > 8) raw = raw.slice(0, 8);
    if (raw.length <= 4) { input.value = raw; }
    else if (raw.length <= 6) { input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6); }
    else { input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6) + '/' + raw.slice(6, 8); }
}

function toggleUserPanel(panel) {
    currentPanel = panel;
    let formPanel = document.getElementById('userFormPanel');
    let searchPanel = document.getElementById('userSearchPanel');
    let archivePanel = document.getElementById('userArchivePanel');
    let adminArchivePanel = document.getElementById('userAdminArchiveList');
    let btns = document.querySelectorAll('.user-panel-btn');
    
    let documentsList = document.getElementById('userDocumentsList');
    let archiveList = document.getElementById('userArchiveList');
    let adminArchiveList = document.getElementById('userAdminArchiveList');
    
    // فعال کردن دکمه مناسب بر اساس data-panel
    btns.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-panel') === panel) {
            btn.classList.add('active');
        }
    });
    
    if (formPanel) formPanel.style.display = 'none';
    if (searchPanel) searchPanel.style.display = 'none';
    if (archivePanel) archivePanel.style.display = 'none';
    if (adminArchivePanel) adminArchivePanel.style.display = 'none';
    
    if (documentsList) documentsList.style.display = 'none';
    if (archiveList) archiveList.style.display = 'none';
    if (adminArchiveList) adminArchiveList.style.display = 'none';
    
    if (panel === 'form') {
        if (formPanel) formPanel.style.display = 'block';
        let today = '<?php echo $today; ?>';
        let deliveryDateInput = document.getElementById('delivery_date');
        if (deliveryDateInput) {
            deliveryDateInput.value = today;
            if (typeof loadDocumentsForDeliveryDate === 'function') loadDocumentsForDeliveryDate(today);
            if (typeof checkLockStatus === 'function') checkLockStatus(today);
        }
        if (document.getElementById('company_id')) document.getElementById('company_id').focus();
        if (documentsList) documentsList.style.display = 'block';
    } else if (panel === 'search') {
        if (searchPanel) searchPanel.style.display = 'block';
        if (typeof autoSearchDocuments === 'function') autoSearchDocuments();
        if (documentsList) documentsList.style.display = 'block';
    } else if (panel === 'archive') {
        if (archivePanel) archivePanel.style.display = 'block';
        if (typeof loadUserArchiveList === 'function') loadUserArchiveList();
        if (archiveList) archiveList.style.display = 'block';
    } else if (panel === 'admin_archive') {
        if (adminArchivePanel) adminArchivePanel.style.display = 'block';
        if (typeof loadAdminArchiveUsers === 'function') loadAdminArchiveUsers();
        if (adminArchiveList) adminArchiveList.style.display = 'block';
    }
}

// ========== جستجوی آنی برای کاربر عادی ==========
function autoSearchDocuments() {
    const doc_number = document.getElementById('filter_number')?.value || '';
    const doc_date = document.getElementById('filter_date')?.value || '';
    const company_id = document.getElementById('filter_company')?.value || '';
    const delivery_date = document.getElementById('filter_delivery')?.value || '';
    
    fetch(`api/ajax.php?action=get_documents&doc_number=${encodeURIComponent(doc_number)}&doc_date=${encodeURIComponent(doc_date)}&company_id=${encodeURIComponent(company_id)}&delivery_date=${encodeURIComponent(delivery_date)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('userDocumentsList');
                if (container && data.groups && data.groups.length > 0) {
                    let html = '';
                    data.groups.forEach(group => {
                        html += `<div class="doc-group" style="margin-bottom:15px; border:1px solid #ddd; border-radius:8px; overflow:hidden;">
                            <div class="group-title" style="background:#f5f5f5; padding:10px; display:flex; justify-content:space-between;">
                                <span><i class="fas fa-calendar"></i> ${group.delivery_date}</span>
                                <span>${group.count} سند</span>
                                <button class="print-btn" onclick="viewArchiveDocument('${group.delivery_date}')" style="background:#667eea; color:white; border:none; padding:5px 10px; border-radius:5px;">👁 مشاهده</button>
                            </div>
                            <table class="data-table" style="width:100%; border-collapse:collapse;">
                                <thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th></tr></thead>
                                <tbody>`;
                        group.documents.forEach((doc, idx) => {
                            html += `<tr><td>${idx+1}</td><td>${escapeHtml(doc.doc_number)}</td><td>${doc.doc_date}</td><td>${escapeHtml(doc.company_name)}</td></tr>`;
                        });
                        html += `</tbody></table></div>`;
                    });
                    container.innerHTML = html;
                } else if (container) {
                    container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
                }
            }
        });
}

// تابع کمکی برای escape
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

async function loadUserStats() {
    try {
        let res = await fetch('api/ajax.php?action=get_user_stats');
        let data = await res.json();
        if (data.success) {
            let dateRange = '';
            if (data.first_date !== '-' && data.last_date !== '-') {
                dateRange = `<div style="font-size: 0.55rem; color: #94a3b8; margin-top: 4px;">از ${data.first_date} تا ${data.last_date}</div>`;
            }
            
            let trendIcon = '';
            let trendColor = '';
            if (data.trend_text.includes('▲')) {
                trendIcon = '📈';
                trendColor = '#10b981';
            } else if (data.trend_text.includes('▼')) {
                trendIcon = '📉';
                trendColor = '#ef4444';
            } else {
                trendIcon = '➖';
                trendColor = '#f59e0b';
            }
            
            let html = `
                <div class="stats-grid-new">
                    <div class="stat-card-new">
                        <div class="stat-header">
                            <span class="stat-icon">📊</span>
                            <span class="stat-title">پیشرفت نسبت به لیست قبل</span>
                        </div>
                        <div class="stat-main">
                            <div class="stat-today">
                                <span class="stat-small-label">لیست آخر : </span>
                                <span class="stat-small-value">${data.today_count}</span>
                            </div>
                            <span class="stat-sep">|</span>
                            <div class="stat-yesterday">
                                <span class="stat-small-label">لیست قبل : </span>
                                <span class="stat-small-value">${data.yesterday_count}</span>
                            </div>
                        </div>
                        <div class="stat-trend" style="color: ${trendColor}">
                            ${trendIcon} ${data.trend_text}
                        </div>
                    </div>
                    
                    <div class="stat-card-new">
                        <div class="stat-header">
                            <span class="stat-icon">📊</span>
                            <span class="stat-title">کل اسناد</span>
                        </div>
                        <div class="stat-main-center">
                            <span class="stat-big">${data.total_docs}</span>
                        </div>
                        ${dateRange}
                    </div>
                    
                    <div class="stat-card-new">
                        <div class="stat-header">
                            <span class="stat-icon">🏢</span>
                            <span class="stat-title">پراکندگی شرکت</span>
                        </div>
                        <div class="stat-companies">
                            <div class="company-most">
                                <span class="company-label">بیشترین</span>
                                <span class="company-name" style="color: #10b981;">${data.most_company}</span>
                                <span class="company-count">(${data.most_count})</span>
                            </div>
                            <div class="company-least">
                                <span class="company-label">کمترین</span>
                                <span class="company-name" style="color: #ef4444;">${data.least_company}</span>
                                <span class="company-count">(${data.least_count})</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card-new-primary">
                    <div class="stat-header">
                        <span class="stat-icon">⭐</span>
                        <span class="stat-title">پیشرفت اسناد تایید شده</span>
                    </div>
                    <div class="avg-stats-new">
                        <div class="avg-item-new ${data.week_change_class}">
                            <span class="avg-icon">📅</span>
                            <span class="avg-value">${data.week_change}</span>
                            <span class="avg-label">هفته</span>
                        </div>
                        <div class="avg-item-new ${data.month_change_class}">
                            <span class="avg-icon">📆</span>
                            <span class="avg-value">${data.month_change}</span>
                            <span class="avg-label">ماه</span>
                        </div>
                        <div class="avg-item-new ${data.year_change_class}">
                            <span class="avg-icon">📅</span>
                            <span class="avg-value">${data.year_change}</span>
                            <span class="avg-label">سال</span>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('userStatsContainer').innerHTML = html;
        }
    } catch(e) {
        console.error(e);
        document.getElementById('userStatsContainer').innerHTML = '<div class="empty-state">خطا در دریافت آمار</div>';
    }
}

function saveDocument() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn.disabled) return;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ثبت...';
    
    const delivery_date = document.getElementById('delivery_date')?.value || '';
    const company_id = document.getElementById('company_id')?.value || '';
    const company_number = document.getElementById('company_number')?.value || '';
    const doc_number = document.getElementById('doc_number')?.value || '';
    let doc_date = document.getElementById('doc_date')?.value || '';
    const description = document.getElementById('doc_description')?.value || '';
    
    let finalCompanyId = company_id;
    if (company_number && !company_id) {
        const companySelect = document.getElementById('company_id');
        const options = companySelect.options;
        if (company_number <= options.length) {
            finalCompanyId = options[company_number - 1].value;
            companySelect.value = finalCompanyId;
        }
    }
    
    if (!doc_number) {
        showToast('شماره سند الزامی است', true);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '✓ ثبت سند';
        return;
    }
    
    fetch('api/ajax.php?action=save_document', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            delivery_date: delivery_date,
            company_id: finalCompanyId,
            doc_number: doc_number,
            doc_date: doc_date,
            description: description
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('سند با موفقیت ثبت شد', false);
            
            document.getElementById('doc_number').value = '';
            if (document.getElementById('doc_date')) document.getElementById('doc_date').value = '';
            document.getElementById('doc_description').value = '';
            
            const companyNumberInput = document.getElementById('company_number');
            if (companyNumberInput) {
                companyNumberInput.value = '';
            }
            
            // ✅ تغییر: فوکوس روی شماره سند
            document.getElementById('doc_number').focus();
            
            const currentDate = document.getElementById('delivery_date').value;
            loadDocumentsForDeliveryDate(currentDate);
            
            if (typeof loadUserStats === 'function') {
                loadUserStats();
            }
        } else {
            showToast(data.error || 'خطا در ثبت سند', true);
        }
    })
    .catch(err => {
        console.error('خطا در ثبت سند:', err);
        showToast('خطا در ارتباط با سرور', true);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '✓ ثبت سند';
    });
}

function addDescriptionToDocument() {
    const description = document.getElementById('doc_description')?.value.trim();
    if (!description) {
        showToast('لطفاً ابتدا توضیح را در کادر بالا وارد کنید', true);
        return;
    }
    
    const delivery_date = document.getElementById('delivery_date')?.value || '';
    if (!delivery_date) {
        alert('تاریخ تحویل مشخص نیست');
        return;
    }
    
    const btn = document.getElementById('submitDescriptionBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
    btn.disabled = true;
    
    fetch('api/ajax.php?action=add_delivery_description', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delivery_date: delivery_date, description: description })
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if (data.success) {
            showToast('گزارش با موفقیت ذخیره شد');
            document.getElementById('doc_description').value = '';
            const currentDate = document.getElementById('delivery_date')?.value || '';
            if (currentDate && typeof loadDocumentsForDeliveryDate === 'function') {
                loadDocumentsForDeliveryDate(currentDate);
            }
        } else {
            alert('خطا: ' + (data.error || 'مشخص نیست'));
        }
    })
    .catch(err => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        console.error(err);
        alert('خطا در ارتباط با سرور');
    });
}

function openEditModal(id, number, date, description) {
    const newNumber = prompt('شماره سند جدید:', number);
    if (!newNumber) return;
    let newDate = prompt('تاریخ سند جدید (مثال: 1404/01/01) یا - برای خالی:', date);
    if (newDate === '') newDate = '-';
    
    fetch('api/ajax.php?action=update_document', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, doc_number: newNumber, doc_date: newDate })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('سند با موفقیت ویرایش شد');
            // به‌روزرسانی آنی لیست بر اساس تاریخ تحویل فعلی
            const currentDate = document.getElementById('delivery_date')?.value || '';
            if (currentDate) {
                loadDocumentsForDeliveryDate(currentDate);
            } else {
                autoSearchDocuments();
            }
            // به‌روزرسانی آمار کاربر
            if (typeof loadUserStats === 'function') {
                loadUserStats();
            }
        } else {
            alert('خطا: ' + (data.error || 'مشخص نیست'));
        }
    });
}

function deleteDocument(id) {
    if (!confirm('آیا از حذف این سند اطمینان دارید؟')) return;
    
    fetch('api/ajax.php?action=delete_document', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('سند با موفقیت حذف شد');
            // به‌روزرسانی آنی لیست بر اساس تاریخ تحویل فعلی
            const currentDate = document.getElementById('delivery_date')?.value || '';
            if (currentDate) {
                loadDocumentsForDeliveryDate(currentDate);
            } else {
                autoSearchDocuments();
            }
            // به‌روزرسانی آمار کاربر
            if (typeof loadUserStats === 'function') {
                loadUserStats();
            }
        } else {
            alert('خطا: ' + (data.error || 'مشخص نیست'));
        }
    });
}

// ========== تغییر تاریخ تحویل با دکمه‌های + و - ==========
function getDaysInMonth(year, month) {
    const jalaliMonths = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    if (month === 12) {
        const isLeap = (year + 38) % 2820 < 8;
        return isLeap ? 30 : 29;
    }
    return jalaliMonths[month - 1];
}

// ========== تغییر تاریخ تحویل با دکمه + و - ==========
function changeDeliveryDate(delta) {
    const deliveryDateInput = document.getElementById('delivery_date');
    let currentDate = deliveryDateInput.value;
    if (!currentDate) return;
    
    const parts = currentDate.split('/');
    if (parts.length !== 3) return;
    
    let year = parseInt(parts[0]);
    let month = parseInt(parts[1]);
    let day = parseInt(parts[2]);
    
    day += delta;
    if (day < 1) {
        month--;
        if (month < 1) {
            year--;
            month = 12;
        }
        const daysInMonth = getDaysInMonth(year, month);
        day = daysInMonth;
    } else if (day > getDaysInMonth(year, month)) {
        day = 1;
        month++;
        if (month > 12) {
            year++;
            month = 1;
        }
    }
    
    const newDate = `${year}/${month.toString().padStart(2, '0')}/${day.toString().padStart(2, '0')}`;
    deliveryDateInput.value = newDate;
    
    // بارگذاری اسناد برای تاریخ جدید
    loadDocumentsForDeliveryDate(newDate);
}

function loadDocumentsForDeliveryDate(deliveryDate) {
    console.log('بارگذاری اسناد برای تاریخ:', deliveryDate);
    
    const container = document.getElementById('userDocumentsList');
    console.log('آیا container پیدا شد؟', container);
    
    if (!container) {
        console.error('container userDocumentsList یافت نشد');
        return;
    }
    
    if (!deliveryDate || deliveryDate === '') {
        console.error('تاریخ تحویل خالی است');
        container.innerHTML = '<div class="empty-state">تاریخ تحویل مشخص نشده است</div>';
        return;
    }
    
    fetch(`api/ajax.php?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`)
        .then(res => res.json())
        .then(data => {
            console.log('داده دریافتی:', data);
            
            if (data.success && data.documents && data.documents.length > 0) {
                let html = `<div class="doc-group">
                            <div class="group-title">
                                <div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(deliveryDate)} <span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${data.documents.length} سند</span></div>
                                <div style="display:flex; gap:8px;">
                                    <a href="print.php?delivery_date=${encodeURIComponent(deliveryDate)}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a>
                                </div>
                            </div>
                            <div style="overflow-x:auto; max-height: 400px; overflow-y: auto;">
                                <table class="data-table">
                                    <thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead>
                                    <tbody>`;
                
                for (let i = 0; i < data.documents.length; i++) {
                    let doc = data.documents[i];
                    let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                    let actions = '';
                    // شرط: فقط تا زمانی که ادمین تایید نکرده باشد، دکمه‌ها نمایش داده شوند
                    if (doc.can_edit && !data.has_admin_approval) {
                        actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button>
                                   <button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                    }
                    html += `<tr>
                        <td style="padding: 8px 6px;">${i+1}</td>
                        <td style="padding: 8px 6px;">${escapeHtml(doc.doc_number)}</td>
                        <td style="padding: 8px 6px;">${docDate}</td>
                        <td style="padding: 8px 6px;">${escapeHtml(doc.company_name)}</td>
                        <td style="padding: 8px 6px;">${actions}</td>
                    </tr>`;
                }
                html += `</tbody>
                </table>
            </div>`;
                
                let descriptions = data.documents.filter(d => d.description && d.description.trim() !== '');
                if (descriptions.length > 0) {
                    html += `<div class="descriptions-section">
                        <div class="desc-title"><i class="fas fa-comment-dots"></i> توضیحات اسناد</div>`;
                    for (let desc of descriptions) {
                        html += `<div class="desc-item"><strong>${escapeHtml(desc.doc_number)}:</strong> ${escapeHtml(desc.description.substring(0, 100))}${desc.description.length > 100 ? '...' : ''}</div>`;
                    }
                    html += `</div>`;
                }
                html += `</div>`;
                container.innerHTML = html;
                console.log('لیست به‌روز شد، تعداد سند:', data.documents.length);
                
                // اسکرول به انتهای لیست برای نمایش آخرین سند ثبت شده
                setTimeout(() => {
                    const tableContainer = container.querySelector('div[style*="overflow-x:auto"]');
                    if (tableContainer) {
                        tableContainer.scrollTop = tableContainer.scrollHeight;
                    }
                    const lastRow = container.querySelector('table tbody tr:last-child');
                    if (lastRow) {
                        lastRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }, 100);
                
            } else {
                container.innerHTML = `
                    <div class="doc-group">
                        <div class="group-title">
                            <div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(deliveryDate)}</div>
                        </div>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-file-alt" style="font-size: 2rem; opacity: 0.5;"></i>
                            <p style="margin-top: 10px;">در انتظار وارد کردن سند جدید در این تاریخ تحویل</p>
                        </div>
                    </div>
                `;
                console.log('هیچ سندی برای این تاریخ وجود ندارد');
            }
        })
        .catch(err => {
            console.error('خطا در fetch:', err);
            container.innerHTML = '<div class="empty-state">خطا در دریافت اسناد</div>';
        });
}

function viewArchiveDocument(deliveryDate, userId) {
    if (!userId) {
        userId = currentUserId; // اگر userId ارسال نشد، از کاربر جاری استفاده کن
    }
    console.log('باز کردن:', 'user_id=' + userId, 'delivery_date=' + deliveryDate);
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

function resetUserFilters() { 
    if (document.getElementById('filter_number')) document.getElementById('filter_number').value = ''; 
    if (document.getElementById('filter_date')) document.getElementById('filter_date').value = ''; 
    if (document.getElementById('filter_company')) document.getElementById('filter_company').value = ''; 
    if (document.getElementById('filter_delivery')) document.getElementById('filter_delivery').value = ''; 
    if (typeof loadSearchDocuments === 'function') loadSearchDocuments(); 
}

function showChangePasswordModal() { 
    if (document.getElementById('passwordModal')) document.getElementById('passwordModal').classList.add('active'); 
}

function closePasswordModal() { 
    if (document.getElementById('passwordModal')) document.getElementById('passwordModal').classList.remove('active'); 
}

async function changePassword() {
    let old_pass = document.getElementById('old_password')?.value || '';
    let new_pass = document.getElementById('new_password')?.value || '';
    let confirm_pass = document.getElementById('confirm_password')?.value || '';
    if (!old_pass || !new_pass) { showToast('تمامی فیلدها را پر کنید', true); return; }
    if (new_pass !== confirm_pass) { showToast('رمز جدید مطابقت ندارد', true); return; }
    let res = await fetch(`${apiUrl}?action=change_password`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({old_password: old_pass, new_password: new_pass}) });
    let result = await res.json();
    if (result.success) { showToast('رمز تغییر کرد'); closePasswordModal(); if (document.getElementById('old_password')) document.getElementById('old_password').value = ''; if (document.getElementById('new_password')) document.getElementById('new_password').value = ''; if (document.getElementById('confirm_password')) document.getElementById('confirm_password').value = ''; }
    else { showToast(result.error || 'خطا', true); }
}

// ========== کاربر عادی ==========
<?php if(!$is_admin): ?>
// ========== متغیرهای کاربر جاری ==========
let currentUserId = <?php echo $user_id; ?>;
let currentUserName = '<?php echo $fullname; ?>';

    function updateDeliveryDate() {
        let monthStr = deliveryMonth < 10 ? '0' + deliveryMonth : deliveryMonth;
        let dayStr = deliveryDay < 10 ? '0' + deliveryDay : deliveryDay;
        let dateInput = document.getElementById('delivery_date');
        if (dateInput) { dateInput.value = deliveryYear + '/' + monthStr + '/' + dayStr; }
    }
      
function loadUserArchiveList() {
    const container = document.getElementById('userArchiveList');
    if (!container) return;
    
    container.innerHTML = '<div id="archive_list_content"><div class="empty-state">در حال بارگذاری...</div></div>';
    
    fetch(`api/ajax.php?action=get_my_archived_dates`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.dates && data.dates.length > 0) {
                // تبدیل اعداد فارسی به انگلیسی
                const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
                const englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                
                const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
                
                const monthMap = new Map();
                
                data.dates.forEach(item => {
                    let dateStr = item.delivery_date;
                    // تبدیل اعداد فارسی به انگلیسی
                    for (let i = 0; i < persianNumbers.length; i++) {
                        dateStr = dateStr.replaceAll(persianNumbers[i], englishNumbers[i]);
                    }
                    // حالا dateStr به شکل "1405/03/18" است
                    const parts = dateStr.split('/');
                    if (parts.length === 3) {
                        const year = parts[0];
                        const monthNum = parseInt(parts[1], 10);
                        const day = parts[2];
                        const monthName = monthNames[monthNum - 1];
                        const key = `${year}-${monthNum}`;
                        
                        if (!monthMap.has(key)) {
                            monthMap.set(key, {
                                year: year,
                                monthName: monthName,
                                monthNum: monthNum,
                                days: []
                            });
                        }
                        monthMap.get(key).days.push({
                            day: day,
                            raw: item.delivery_date_raw
                        });
                    }
                });
                
                const sortedMonths = Array.from(monthMap.values()).sort((a, b) => {
                    if (a.year !== b.year) return parseInt(b.year) - parseInt(a.year);
                    return b.monthNum - a.monthNum;
                });
                
                let html = '<div class="archive-months-container">';
                for (const month of sortedMonths) {
                    month.days.sort((a, b) => parseInt(a.day) - parseInt(b.day));
                    
                    html += `
                        <div class="archive-month-card">
                            <div class="archive-month-header">
                                <div class="archive-month-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>${month.monthName} ${month.year}</span>
                                </div>
                                <div class="archive-month-count">${month.days.length} سند</div>
                            </div>
                            <div class="archive-days-list">
                    `;
                    for (const day of month.days) {
                        html += `
                            <div class="archive-day-item" onclick="viewArchiveDocument('${day.raw}')">
                                <span class="archive-day-number">${day.day}</span>
                                <i class="fas fa-eye archive-day-eye"></i>
                            </div>
                        `;
                    }
                    html += `
                            </div>
                        </div>
                    `;
                }
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای یافت نشد</div>';
            }
        });
}

function loadSelectedUserArchive() {
    const selectElem = document.getElementById('archive_user_select');
    if (selectElem) loadArchiveForUser(parseInt(selectElem.value));
}

function loadArchiveForUser(userId) {
    const content = document.getElementById('archive_list_content');
    if (!content) return;
    
    content.innerHTML = '<div class="empty-state">در حال بارگذاری...</div>';
    
    fetch(`api/ajax.php?action=get_archived_delivery_dates_for_user&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.dates && data.dates.length > 0) {
                let html = '<div class="archive-list">';
                data.dates.forEach(item => {
                    html += `
                        <div class="archive-item">
                            <div class="archive-item-left">
                                <div class="archive-item-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <div class="archive-item-date">${item.delivery_date}</div>
                                </div>
                            </div>
                            <button class="archive-view-btn" onclick="viewArchiveDocument('${item.delivery_date_raw}', ${userId})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    `;
                });
                content.innerHTML = html + '</div>';
            } else {
                content.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای یافت نشد</div>';
            }
        });
}

function viewArchiveDocument(deliveryDate, userId) {
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

function loadAdminArchiveUsers() {
    fetch('api/ajax.php?action=get_all_users_for_archive')
        .then(res => res.json())
        .then(data => {
            let container = document.getElementById('admin_archive_list_container');
            if (!container) return;
            
            if (!data.users || data.users.length === 0) {
                container.innerHTML = '<div class="empty-state">هیچ کاربری یافت نشد</div>';
                return;
            }
            
            let html = '<div class="radio-group">';
            data.users.forEach(user => {
                if (user.id != currentUserId) {
                    html += `
                        <label class="radio-label">
                            <input type="radio" name="admin_archive_user" value="${user.id}" onchange="loadAdminArchive()">
                            <span>${escapeHtml(user.fullname)}</span>
                        </label>
                    `;
                }
            });
            html += '</div><div id="admin_archive_list" class="archive-list"><div class="empty-state">کاربری را انتخاب کنید</div></div>';
            container.innerHTML = html;
        });
}

function loadAdminArchive() {
    const selectedRadio = document.querySelector('input[name="admin_archive_user"]:checked');
    if (!selectedRadio) return;
    const userId = selectedRadio.value;
    
    const container = document.getElementById('admin_archive_list');
    if (!container) return;
    container.innerHTML = '<div class="empty-state">در حال بارگذاری...</div>';
    
    fetch(`api/ajax.php?action=get_archived_delivery_dates_for_user&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.dates && data.dates.length > 0) {
                let html = '<div class="archive-list">';
                data.dates.forEach(item => {
                    html += `
                        <div class="archive-item">
                            <div class="archive-item-left">
                                <div class="archive-item-icon"><i class="fas fa-calendar-check"></i></div>
                                <div><div class="archive-item-date">${item.delivery_date}</div></div>
                            </div>
                            <button class="archive-view-btn" onclick="viewAdminArchive('${item.delivery_date_raw}', ${userId})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    `;
                });
                container.innerHTML = html + '</div>';
            } else {
                container.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای یافت نشد</div>';
            }
        });
}

function viewAdminArchive(deliveryDate, userId) {
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

// اتصال رویداد
document.getElementById('admin_archive_user_select')?.addEventListener('change', loadAdminArchive);
	  
    function addDaysToDelivery(days) {
        deliveryDay += days;
        let daysInMonth = getDaysInMonth(deliveryYear, deliveryMonth);
        if (deliveryDay > daysInMonth) { deliveryDay = 1; deliveryMonth++; if (deliveryMonth > 12) { deliveryMonth = 1; deliveryYear++; } }
        if (deliveryDay < 1) { deliveryMonth--; if (deliveryMonth < 1) { deliveryMonth = 12; deliveryYear--; } deliveryDay = getDaysInMonth(deliveryYear, deliveryMonth); }
        updateDeliveryDate();
        let newDate = deliveryYear + '/' + (deliveryMonth < 10 ? '0'+deliveryMonth : deliveryMonth) + '/' + (deliveryDay < 10 ? '0'+deliveryDay : deliveryDay);
        checkLockStatus(newDate);
        loadDocumentsForDeliveryDate(newDate);
    }

    let initialDelivery = '<?php echo $today; ?>'.split('/');
    if (initialDelivery.length === 3) { deliveryYear = parseInt(initialDelivery[0]); deliveryMonth = parseInt(initialDelivery[1]); deliveryDay = parseInt(initialDelivery[2]); }
    updateDeliveryDate();
    let initialDate = deliveryYear + '/' + (deliveryMonth < 10 ? '0'+deliveryMonth : deliveryMonth) + '/' + (deliveryDay < 10 ? '0'+deliveryDay : deliveryDay);
    checkLockStatus(initialDate);

    let minusBtn = document.getElementById('dateMinus');
    let plusBtn = document.getElementById('datePlus');
    if (minusBtn) minusBtn.addEventListener('click', function() { addDaysToDelivery(-1); });
    if (plusBtn) plusBtn.addEventListener('click', function() { addDaysToDelivery(1); });
    
    const descBtn = document.getElementById('submitDescriptionBtn');
    if (descBtn) { descBtn.addEventListener('click', function(e) { e.preventDefault(); saveReport(); }); }

    const companyNumberInput = document.getElementById('company_number');
    const companySelect = document.getElementById('company_id');
    if (companyNumberInput && companySelect) {
        const companiesList = <?php $list = []; foreach($companies as $c) { $list[] = ['id' => $c['id'], 'name' => $c['name']]; } echo json_encode($list); ?>;
        companyNumberInput.addEventListener('input', function() {
            let num = parseInt(this.value);
            if (isNaN(num)) return;
            if (num > companiesList.length) num = companiesList.length;
            if (num < 1) num = 1;
            const selectedCompany = companiesList[num - 1];
            if (selectedCompany) companySelect.value = selectedCompany.id;
        });
        companyNumberInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('doc_number').focus(); } });
    }

async function saveReport() {
    let reportText = document.getElementById('doc_description')?.value.trim() || '';
    if (!reportText) { showToast('لطفاً متن توضیحات را وارد کنید', true); return; }
    let btn = document.getElementById('submitDescriptionBtn');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
    btn.disabled = true;
    try {
        let res = await fetch(`${apiUrl}?action=save_report`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({report: reportText}) });
        let result = await res.json();
        if (result.success) { showToast('✅ توضیحات با موفقیت ذخیره شد'); document.getElementById('doc_description').value = ''; }
        else { showToast('❌ خطا: ' + (result.error || 'خطا در ذخیره توضیحات'), true); }
    } catch(e) { console.error(e); showToast('❌ خطا در ارتباط با سرور', true); }
    finally { btn.innerHTML = originalText; btn.disabled = false; }
}

if (document.getElementById('company_number')) { document.getElementById('company_number').focus(); }
else { document.getElementById('company_id').focus(); }

var companyNumberElem = document.getElementById('company_number');
if (companyNumberElem) {
    companyNumberElem.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('doc_number').focus(); } });
}
if (document.getElementById('doc_number')) {
    document.getElementById('doc_number').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (requireDocDate && document.getElementById('doc_date')) { document.getElementById('doc_date').focus(); }
            else { if (document.getElementById('company_number')) { document.getElementById('company_number').focus(); } else if (document.getElementById('company_id')) { document.getElementById('company_id').focus(); } }
        }
    });
}
if (requireDocDate && document.getElementById('doc_date')) {
    document.getElementById('doc_date').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (document.getElementById('company_number')) { document.getElementById('company_number').focus(); } else if (document.getElementById('company_id')) { document.getElementById('company_id').focus(); } }
    });
    let docDateElem = document.getElementById('doc_date');
    if (docDateElem) {
        docDateElem.addEventListener('input', function(e) { formatJalali(this); });
    }
}
var companySelectElem = document.getElementById('company_id');
if (companySelectElem) {
    companySelectElem.addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('doc_number').focus(); } });
}
if (document.getElementById('submitBtn')) {
    document.getElementById('submitBtn').addEventListener('keydown', function(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); return false; } });
}
document.addEventListener('keydown', async function(e) { if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); if (currentPanel === 'form') { await saveDocument(); } } });
if (document.getElementById('submitBtn')) {
    document.getElementById('submitBtn').addEventListener('click', function() { saveDocument(); setTimeout(function() { if (document.getElementById('doc_number')) document.getElementById('doc_number').focus(); }, 100); });
}

let todayDate = '<?php echo $today; ?>';
let deliveryDateInput = document.getElementById('delivery_date');
if (deliveryDateInput) {
    deliveryDateInput.value = todayDate;
    if (typeof loadDocumentsForDeliveryDate === 'function') {
        loadDocumentsForDeliveryDate(todayDate);
    }
    if (typeof checkLockStatus === 'function') {
        checkLockStatus(todayDate);
    }
}

window.openEditModal = function(id, number, date, description) {
    let editId = document.getElementById('edit_id'), editNumber = document.getElementById('edit_number'), editDate = document.getElementById('edit_date');
    if (editId) editId.value = id;
    if (editNumber) editNumber.value = number;
    if (editDate) editDate.value = (date === '-' ? '' : date);
    let modal = document.getElementById('editModal');
    if (modal) modal.classList.add('active');
}
window.closeEditModal = function() { if (document.getElementById('editModal')) document.getElementById('editModal').classList.remove('active'); }
window.saveEdit = async function() {
    let id = document.getElementById('edit_id')?.value || '';
    let number = document.getElementById('edit_number')?.value.trim() || '';
    let date = document.getElementById('edit_date')?.value.trim() || '';
    
    if (!id) { showToast('شناسه سند یافت نشد', true); return; }
    if (!number) { showToast('شماره سند الزامی است', true); return; }
    
    let res = await fetch(`${apiUrl}?action=update_document`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, doc_number: number, doc_date: date || '-' })
    });
    let result = await res.json();
    
    if (result.success) {
        showToast('ویرایش شد');
        closeEditModal();
        
        const currentDate = document.getElementById('delivery_date')?.value || '';
        if (currentDate && typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(currentDate);
        } else if (typeof autoSearchDocuments === 'function') {
            autoSearchDocuments();
        }
        if (typeof loadUserStats === 'function') {
            loadUserStats();
        }
    } else {
        showToast(result.error || 'خطا در ویرایش', true);
    }
}
window.deleteDocument = async function(id) {
    if (!confirm('حذف شود؟')) return;
    
    let res = await fetch(`${apiUrl}?action=delete_document`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    });
    let result = await res.json();
    
    if (result.success) {
        showToast('حذف شد');
        
        const currentDate = document.getElementById('delivery_date')?.value || '';
        if (currentDate && typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(currentDate);
        } else if (typeof autoSearchDocuments === 'function') {
            autoSearchDocuments();
        }
        if (typeof loadUserStats === 'function') {
            loadUserStats();
        }
    } else {
        showToast(result.error || 'خطا در حذف', true);
    }
}

    async function loadSearchDocuments() {
        let params = new URLSearchParams();
        params.append('action', 'get_documents');
        params.append('doc_number', document.getElementById('filter_number')?.value || '');
        params.append('doc_date', document.getElementById('filter_date')?.value || '');
        params.append('company_id', document.getElementById('filter_company')?.value || '');
        params.append('delivery_date', document.getElementById('filter_delivery')?.value || '');
        try {
            let res = await fetch(`${apiUrl}?${params.toString()}`);
            let data = await res.json();
            let container = document.getElementById('documents_list');
            if (data.success && data.groups && data.groups.length > 0) {
                let html = '';
                for (let group of data.groups) {
                    let printUrl = `print.php?delivery_date=${encodeURIComponent(group.delivery_date)}`;
                    let isArchived = group.is_archived || false;
                    let archiveBadge = isArchived ? '<span style="background:#10b981; color:white; padding:2px 8px; border-radius:12px; font-size:0.6rem; margin-right:8px;">تایید شده</span>' : '';
                    html += `<div class="doc-group"><div class="group-title"><div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(group.delivery_date)} ${archiveBadge}<span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${group.count} سند</span></div><a href="${printUrl}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a></div><div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead><tbody>`;
                    for (let doc of group.documents) {
                        let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                        let actions = '';
                        if (doc.can_edit && !isArchived) {
                            actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                        }
                        html += `<tr><td>${doc.row_num}</td><td>${escapeHtml(doc.doc_number)}</td><td>${docDate}</td><td>${escapeHtml(doc.company_name)}</td><td>${actions}</td></tr>`;
                    }
                    html += `</tbody></table></div>`;
                    let descriptions = group.documents.filter(d => d.description && d.description.trim() !== '');
                    if (descriptions.length > 0) {
                        html += `<div class="descriptions-section"><div class="desc-title"><i class="fas fa-comment-dots"></i> توضیحات اسناد</div>`;
                        for (let desc of descriptions) { html += `<div class="desc-item"><strong>${escapeHtml(desc.doc_number)}:</strong> ${escapeHtml(desc.description.substring(0, 100))}${desc.description.length > 100 ? '...' : ''}</div>`; }
                        html += `</div>`;
                    }
                    html += `</div>`;
                }
                container.innerHTML = html;
            } else { container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>'; }
        } catch(e) { console.error(e); }
    }

    // ========== توابع بایگانی ==========
    async function loadUserArchive() {
        let container = document.getElementById('userArchiveList');
        if (!container) return;
        container.innerHTML = '<div class="empty-state">در حال بارگذاری...</div>';
        try {
            let res = await fetch(`${apiUrl}?action=get_archived_delivery_dates`);
            let data = await res.json();
            if (data.success && data.dates && data.dates.length > 0) {
                let html = '';
                for (let item of data.dates) {
                    html += `<div style="background:#f8fafc; border-radius:12px; padding:10px 12px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-calendar-check" style="color:#10b981;"></i> ${escapeHtml(item.delivery_date)}</span>
                        <div>
                            <button onclick="window.open('print.php?delivery_date=${encodeURIComponent(item.delivery_date_raw)}', '_blank')" style="background:#10b98120; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer; margin-left:5px;"><i class="fas fa-eye" style="color:#10b981;"></i></button>
                            <button onclick="requestArchiveRevert('${escapeHtml(item.delivery_date_raw)}')" style="background:#ef444420; border:none; width:32px; height:32px; border-radius:8px; cursor:pointer;"><i class="fas fa-undo-alt" style="color:#ef4444;"></i></button>
                        </div>
                    </div>`;
                }
                container.innerHTML = html;
            } else { container.innerHTML = '<div class="empty-state">هیچ تاریخ تایید شده‌ای وجود ندارد</div>'; }
        } catch(e) { console.error(e); container.innerHTML = '<div class="empty-state">خطا در بارگذاری</div>'; }
    }

    async function requestArchiveRevert(deliveryDate) {
        if (!confirm('آیا از ادمین درخواست بازیابی می‌کنید؟')) return;
        try {
            let res = await fetch(`${apiUrl}?action=request_revert_from_archive`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ delivery_date: deliveryDate }) });
            let result = await res.json();
            if (result.success) { showToast('✅ درخواست بازیابی ارسال شد'); }
            else { showToast(result.error || '❌ خطا', true); }
        } catch(e) { console.error(e); showToast('❌ خطا در ارتباط با سرور', true); }
    }

<?php endif; ?>

// ========== ادمین ==========
<?php if($is_admin): ?>

let currentAdminUserId = '';

async function searchAdminDocuments() {
    let params = new URLSearchParams();
    params.append('action', 'search_admin_documents');
    let userId = document.getElementById('admin_user_select')?.value || '';
    if (userId) params.append('admin_user_id', userId);
    params.append('doc_number', document.getElementById('admin_filter_number')?.value || '');
    params.append('doc_date', document.getElementById('admin_filter_date')?.value || '');
    params.append('company_id', document.getElementById('admin_filter_company')?.value || '');
    params.append('delivery_date', document.getElementById('admin_filter_delivery')?.value || '');
    try {
        let res = await fetch(`${apiUrl}?${params.toString()}`);
        let data = await res.json();
        let container = document.getElementById('adminDocumentsList');
        if (data.success && data.results && data.results.length > 0) {
            let html = `<div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>تاریخ تحویل</th><th>شرکت</th><th>کاربر</th><th>عملیات</th></tr></thead><tbody>`;
            for (let i = 0; i < data.results.length; i++) {
                let doc = data.results[i];
                let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                html += `<tr>
                    <td>${i+1}</td>
                    <td>${escapeHtml(doc.doc_number)}</td>
                    <td>${docDate}</td>
                    <td>${escapeHtml(doc.delivery_date)}</td>
                    <td>${escapeHtml(doc.company_name)}</td>
                    <td>${escapeHtml(doc.user_fullname)}</td>
                    <td><button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button></td>
                </tr>`;
            }
            html += `</tbody></table></div>`;
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>'; }
    } catch(e) { console.error(e); }
}

async function loadUsersPendingList() {
    let container = document.getElementById('usersPendingList');
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_users_with_pending_approvals`);
        let data = await res.json();
        if (data.success && data.users && data.users.length > 0) {
            let html = '<div class="users-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px,1fr)); gap:15px;">';
            for (let user of data.users) {
                html += `<div class="user-pending-card" style="background:#f8fafc; border-radius:12px; padding:15px; cursor:pointer; transition:all 0.2s; border:1px solid #e2e8f0;" onclick="loadUserPendingDates(${user.user_id}, '${escapeHtml(user.fullname)}')"><div style="font-weight:bold; font-size:0.85rem;">${escapeHtml(user.fullname)}</div><div style="font-size:0.65rem; color:#6c86a3; margin-top:5px;">${escapeHtml(user.unit_name)}</div><div style="margin-top:8px;"><span style="background:#f59e0b; color:white; padding:2px 8px; border-radius:20px; font-size:0.6rem;">${user.pending_count} تاریخ در انتظار</span></div></div>`;
            }
            html += '</div>';
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state">هیچ کاربری با تایید pending وجود ندارد</div>'; }
    } catch(e) { console.error(e); }
}

async function loadUserPendingDates(userId, userName) {
    document.getElementById('usersPendingList').style.display = 'none';
    let container = document.getElementById('userDatesContainer');
    let listContainer = document.getElementById('userDatesList');
    container.style.display = 'block';
    listContainer.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_user_pending_dates&user_id=${userId}`);
        let data = await res.json();
        if (data.success && data.dates && data.dates.length > 0) {
            let html = '';
            for (let item of data.dates) {
                let hasUserApproval = item.user_approved ? '<span style="color:#10b981;">✓ امضا شده</span>' : '<span style="color:#f59e0b;">⌛ در انتظار امضا</span>';
                html += `<div class="archive-item" style="margin-bottom:10px;"><div class="archive-date"><i class="fas fa-calendar"></i> ${escapeHtml(item.delivery_date_persian)}<div style="font-size:0.6rem; margin-top:5px;">${hasUserApproval}</div></div><div class="archive-actions">${!item.user_approved ? `<a href="signature_upload.php?delivery_date=${encodeURIComponent(item.delivery_date)}" class="archive-btn view" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;"><i class="fas fa-pen"></i> ثبت امضا</a>` : ''}<button class="archive-btn view" onclick="window.open('print.php?user_id=${userId}&delivery_date=${encodeURIComponent(item.delivery_date)}', '_blank')"><i class="fas fa-eye"></i> مشاهده</button></div></div>`;
            }
            listContainer.innerHTML = html;
        } else { listContainer.innerHTML = '<div class="empty-state">هیچ تاریخی برای این کاربر وجود ندارد</div>'; }
    } catch(e) { console.error(e); }
}

function backToUsersList() { document.getElementById('usersPendingList').style.display = 'block'; document.getElementById('userDatesContainer').style.display = 'none'; }

async function loadRevertRequests() {
    let container = document.getElementById('revertRequestsList');
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_revert_requests`);
        let data = await res.json();
        if (data.success && data.requests && data.requests.length > 0) {
            let html = '';
            for (let req of data.requests) {
                html += `<div class="archive-item" style="margin-bottom:10px; flex-wrap:wrap;"><div><div style="font-weight:bold;">${escapeHtml(req.fullname)} (${escapeHtml(req.unit_name)})</div><div style="font-size:0.65rem; color:#6c86a3; margin-top:3px;">تاریخ تحویل: ${escapeHtml(req.delivery_date_persian)}</div><div style="font-size:0.6rem; color:#f59e0b;">درخواست: ${escapeHtml(req.requested_at_persian)}</div></div><div class="archive-actions"><button class="archive-btn view" onclick="approveRevertRequest(${req.id})" style="background:#10b981; color:white;"><i class="fas fa-check"></i> تایید</button><button class="archive-btn revert" onclick="rejectRevertRequest(${req.id})"><i class="fas fa-times"></i> رد</button></div></div>`;
            }
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state">هیچ درخواست بازیابی در انتظاری وجود ندارد</div>'; }
    } catch(e) { console.error(e); }
}

async function approveRevertRequest(requestId) {
    if (!confirm('آیا از تایید این درخواست اطمینان دارید؟ با تایید، هر دو امضا حذف خواهند شد.')) return;
    try {
        let res = await fetch(`${apiUrl}?action=approve_revert_request`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ request_id: requestId }) });
        let result = await res.json();
        if (result.success) { showToast('✅ درخواست بازیابی تایید شد'); loadRevertRequests(); loadUsersPendingList(); loadApprovedApprovals(); }
        else { showToast(result.error || '❌ خطا', true); }
    } catch(e) { console.error(e); }
}

async function rejectRevertRequest(requestId) {
    if (!confirm('آیا از رد این درخواست اطمینان دارید؟')) return;
    try {
        let res = await fetch(`${apiUrl}?action=reject_revert_request`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ request_id: requestId }) });
        let result = await res.json();
        if (result.success) { showToast('درخواست بازیابی رد شد'); loadRevertRequests(); }
        else { showToast(result.error || '❌ خطا', true); }
    } catch(e) { console.error(e); }
}

async function loadApprovedApprovals() {
    let container = document.getElementById('approvedApprovalsList');
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_all_approved_approvals`);
        let data = await res.json();
        if (data.success && data.approvals && data.approvals.length > 0) {
            let html = '';
            for (let app of data.approvals) {
                html += `<div class="archive-item" style="margin-bottom:10px;">
                    <div>
                        <div style="font-weight:bold;">${escapeHtml(app.fullname)} (${escapeHtml(app.unit_name)})</div>
                        <div style="font-size:0.65rem;">تاریخ تحویل: ${escapeHtml(app.delivery_date)}</div>
                        <div style="font-size:0.6rem; color:#10b981;">تایید شده در: ${escapeHtml(app.admin_approved_at_fa)}</div>
                    </div>
                    <div class="archive-actions">
                        <button class="archive-btn view" onclick="window.open('print.php?user_id=${app.user_id}&delivery_date=${encodeURIComponent(app.delivery_date)}', '_blank')">
                            <i class="fas fa-print"></i> پرینت
                        </button>
                    </div>
                </div>`;
            }
            container.innerHTML = html;
        } else { 
            container.innerHTML = '<div class="empty-state">هیچ تایید نهایی ثبت نشده است</div>'; 
        }
    } catch(e) { console.error(e); }
}

async function loadAdminArchive() {
    let container = document.getElementById('archiveList');
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_all_approved_approvals`);
        let data = await res.json();
        if (data.success && data.approvals && data.approvals.length > 0) {
            let html = '';
            for (let app of data.approvals) {
                html += `<div class="archive-item" style="margin-bottom:10px;"><div><div style="font-weight:bold;">${escapeHtml(app.fullname)} (${escapeHtml(app.unit_name)})</div><div style="font-size:0.65rem;">تاریخ تحویل: ${escapeHtml(app.delivery_date)}</div><div style="font-size:0.6rem; color:#10b981;">تایید شده در: ${escapeHtml(app.admin_approved_at)}</div></div><div class="archive-actions"><button class="archive-btn view" onclick="window.open('print.php?user_id=${app.user_id}&delivery_date=${encodeURIComponent(app.delivery_date)}', '_blank')"><i class="fas fa-print"></i> پرینت</button></div></div>`;
            }
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state">هیچ بایگانی ثبت نشده است</div>'; }
    } catch(e) { console.error(e); }
}

// ========== آمار کاربران ادمین ==========
let currentSelectedUserId = null;

function loadAdminUsersStats() {
    fetch('api/ajax.php?action=get_admin_users_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statsTodayDate').innerHTML = `📅 ${data.today_date}`;
                
                if (data.max_user) {
                    document.getElementById('maxUserName').innerHTML = data.max_user.fullname;
                    document.getElementById('maxUserCount').innerHTML = data.max_user.total_docs;
                    document.getElementById('maxUserFirstDateText').innerHTML = data.max_user.first_date || '-';
                }
                
                if (data.min_user) {
                    document.getElementById('minUserName').innerHTML = data.min_user.fullname;
                    document.getElementById('minUserCount').innerHTML = data.min_user.total_docs;
                    if (data.min_user.total_docs == 0) {
                        document.getElementById('minUserFirstDateText').innerHTML = 'سندی ثبت نشده';
                    } else {
                        document.getElementById('minUserFirstDateText').innerHTML = data.min_user.first_date || '-';
                    }
                }
                
                document.getElementById('totalApprovedCount').innerHTML = data.total_approved;
                document.getElementById('vsYesterday').innerHTML = `<div class="compare-item" style="display: flex; justify-content: space-between; align-items: center; direction: ltr;"><span style="text-align: right; direction: rtl; flex: 1;">آخرین لیست : ${data.vs_yesterday.split('|')[0]}</span><span style="margin: 0 8px; color: #cbd5e1;">|</span><span style="text-align: left; flex: 1;">${data.vs_yesterday.split('|')[1]}</span></div>`;
                document.getElementById('vsLastWeek').innerHTML = `<div class="compare-item" style="display: flex; justify-content: space-between; align-items: center; direction: ltr;"><span style="text-align: right; direction: rtl; flex: 1;">هفته جاری : ${data.vs_last_week.split('|')[0]}</span><span style="margin: 0 8px; color: #cbd5e1;">|</span><span style="text-align: left; flex: 1;">${data.vs_last_week.split('|')[1]}</span></div>`;
                document.getElementById('vsLastMonth').innerHTML = `<div class="compare-item" style="display: flex; justify-content: space-between; align-items: center; direction: ltr;"><span style="text-align: right; direction: rtl; flex: 1;">ماه جاری :  ${data.vs_last_month.split('|')[0]}</span><span style="margin: 0 8px; color: #cbd5e1;">|</span><span style="text-align: left; flex: 1;">${data.vs_last_month.split('|')[1]}</span></div>`;
                
                let usersHtml = '<div class="user-stats-container">';
                data.users.forEach(user => {
                    usersHtml += `
                        <div class="user-card">
                            <div class="user-card-header">
                                <div class="user-info">
                                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                                    <div class="user-details">
                                        <h4>${escapeHtml(user.fullname)}</h4>
                                        <div class="user-unit">${escapeHtml(user.unit_name)}</div>
                                        <div class="user-first-register"><i class="fas fa-calendar-alt"></i> شروع ثبت از : ${user.first_date !== '-' ? user.first_date : '---'}</div>
                                    </div>
                                </div>
                                <div class="user-stats-badges">
                                    <div class="stat-badge"><div class="stat-value">${user.total_docs}</div><div class="stat-label">کل</div></div>
                                    <div class="stat-badge"><div class="stat-value">${user.pending_today}</div><div class="stat-label">لیست آخر : </div></div>
                                    <div class="stat-badge"><div class="stat-value">${user.yesterday_count}</div><div class="stat-label">لیست قبل : </div></div>
                                </div>
                            </div>
                            <div class="user-card-middle">
                                <div class="trend-chip"><span class="trend-label">نسبت به لیست قبل : </span><span class="${user.trend_class}">${user.trend_text}</span></div>
                                <div class="avg-chips">
                                    <div class="avg-chip"><span>📅</span><span class="${user.week_change_class}">${user.week_change}</span><span class="avg-label"> هفته</span></div>
                                    <div class="avg-chip"><span>📆</span><span class="${user.month_change_class}">${user.month_change}</span><span class="avg-label"> ماه</span></div>
                                    <div class="avg-chip"><span>📅</span><span class="${user.year_change_class}">${user.year_change}</span><span class="avg-label"> سال</span></div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                usersHtml += '</div>';
                document.getElementById('adminUsersStatsList').innerHTML = usersHtml;
            }
        })
        .catch(err => console.error(err));
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function selectUserForStats(userId, userName) {
    document.querySelectorAll('.user-card').forEach(card => {
        card.classList.remove('selected');
    });
    const selectedCard = document.getElementById(`userCard_${userId}`);
    if (selectedCard) selectedCard.classList.add('selected');
    
    document.getElementById('selectedUserName').innerHTML = userName;
    document.getElementById('selectedUserStats').style.display = 'block';
    document.getElementById('selectedUserStats').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    fetch(`api/ajax.php?action=get_admin_user_detail_stats&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let trendColor = '';
                let trendIcon = '';
                if (data.trend_class.includes('10b981')) {
                    trendColor = 'linear-gradient(135deg, #10b981, #059669)';
                    trendIcon = '📈';
                } else if (data.trend_class.includes('ef4444')) {
                    trendColor = 'linear-gradient(135deg, #ef4444, #dc2626)';
                    trendIcon = '📉';
                } else {
                    trendColor = 'linear-gradient(135deg, #f59e0b, #d97706)';
                    trendIcon = '➖';
                }
                
                let detailHtml = `
                    <div class="stats-detail-wrapper">
                        <div class="stats-detail-card">
                            <div class="stats-detail-value">${data.pending_today}</div>
                            <div class="stats-detail-label">📅 اسناد جاری</div>
                        </div>
                        <div class="stats-detail-card-green">
                            <div class="stats-detail-value">${data.total_docs}</div>
                            <div class="stats-detail-label">📊 کل اسناد</div>
                        </div>
                        <div class="stats-detail-trend" style="background: ${trendColor};">
                            <div class="stats-detail-value-small">${trendIcon} ${data.trend}</div>
                            <div class="stats-detail-label-small">نسبت به لیست قبل (${data.yesterday_count})</div>
                        </div>
                        <div class="stats-detail-avg">
                            <div class="avg-item">
                                <div class="avg-icon">📅</div>
                                <div class="avg-value">${data.week_count}</div>
                                <div class="avg-label">هفته گذشته</div>
                                <div class="avg-desc">۷ روز اخیر</div>
                            </div>
                            <div class="avg-item">
                                <div class="avg-icon">📆</div>
                                <div class="avg-value">${data.month_count}</div>
                                <div class="avg-label">ماه گذشته</div>
                                <div class="avg-desc">۳۰ روز اخیر</div>
                            </div>
                            <div class="avg-item">
                                <div class="avg-icon">📅</div>
                                <div class="avg-value">${data.year_count}</div>
                                <div class="avg-label">سال گذشته</div>
                                <div class="avg-desc">۳۶۵ روز اخیر</div>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('selectedUserStatsContent').innerHTML = detailHtml;
            }
        });
}

function closeSelectedUserStats() {
    document.getElementById('selectedUserStats').style.display = 'none';
    currentSelectedUserId = null;
    document.querySelectorAll('.user-stat-item').forEach(item => {
        item.classList.remove('selected');
    });
}

function renderAdminDocumentsList(docs) {
    const container = document.getElementById('adminDocumentsList');
    if (!container) return;
    
    if (!docs || docs.length === 0) {
        container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
        return;
    }
    
    let html = '<div style="overflow-x: auto;"><table class="data-table"><thead><tr><th>#</th><th>کاربر</th><th>واحد</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>تاریخ تحویل</th></tr></thead><tbody>';
    docs.forEach((doc, idx) => {
        html += `
            <tr>
                <td>${idx+1}</td>
                <td>${escapeHtml(doc.user_fullname)}</td>
                <td>${escapeHtml(doc.user_unit)}</td>
                <td>${escapeHtml(doc.doc_number)}</td>
                <td>${doc.doc_date === '-' ? '-' : escapeHtml(doc.doc_date)}</td>
                <td>${escapeHtml(doc.company_name)}</td>
                <td>${escapeHtml(doc.delivery_date)}</td>
            </tr>
        `;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function adminAutoSearch() {
    const userId = document.getElementById('admin_user_select')?.value || '';
    const docNumber = document.getElementById('admin_filter_number')?.value || '';
    const docDate = document.getElementById('admin_filter_date')?.value || '';
    const companyId = document.getElementById('admin_filter_company')?.value || '';
    const deliveryDate = document.getElementById('admin_filter_delivery')?.value || '';
    
    let url = `api/ajax.php?action=search_admin_documents&doc_number=${encodeURIComponent(docNumber)}&doc_date=${encodeURIComponent(docDate)}&company_id=${encodeURIComponent(companyId)}&delivery_date=${encodeURIComponent(deliveryDate)}`;
    if (userId) url += `&admin_user_id=${userId}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderAdminDocumentsList(data.documents);
            } else {
                document.getElementById('adminDocumentsList').innerHTML = '<div class="empty-state">خطا در دریافت اسناد</div>';
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('adminDocumentsList').innerHTML = '<div class="empty-state">خطا در ارتباط با سرور</div>';
        });
}

function viewAdminDocument(deliveryDate, userId) {
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

function bindAdminSearchEvents() {
    const adminUserSelect = document.getElementById('admin_user_select');
    const adminFilterNumber = document.getElementById('admin_filter_number');
    const adminFilterDate = document.getElementById('admin_filter_date');
    const adminFilterCompany = document.getElementById('admin_filter_company');
    const adminFilterDelivery = document.getElementById('admin_filter_delivery');
    
    if (adminUserSelect) adminUserSelect.addEventListener('change', adminAutoSearch);
    if (adminFilterNumber) adminFilterNumber.addEventListener('input', adminAutoSearch);
    if (adminFilterDate) adminFilterDate.addEventListener('input', adminAutoSearch);
    if (adminFilterCompany) adminFilterCompany.addEventListener('change', adminAutoSearch);
    if (adminFilterDelivery) adminFilterDelivery.addEventListener('input', adminAutoSearch);
}

// ========== بایگانی ادمین ==========
function loadAdminArchiveList() {
    const container = document.getElementById('archiveList');
    if (!container) return;
    
    fetch('api/ajax.php?action=get_all_archived_dates')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.dates && data.dates.length > 0) {
                // گروه‌بندی بر اساس تاریخ
                const groupedByDate = {};
                data.dates.forEach(item => {
                    if (!groupedByDate[item.delivery_date]) {
                        groupedByDate[item.delivery_date] = [];
                    }
                    groupedByDate[item.delivery_date].push({
                        user_id: item.user_id,
                        user_name: item.user_name
                    });
                });
                
                let html = '<div class="archive-stats-container">';
                for (const [deliveryDate, users] of Object.entries(groupedByDate)) {
                    html += `
                        <div class="archive-date-card">
                            <div class="archive-date-header">
                                <div class="archive-date-title">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>${deliveryDate}</span>
                                </div>
                                <div class="archive-count-badge">
                                    <i class="fas fa-users"></i> ${users.length} کاربر
                                </div>
                            </div>
                            <div class="archive-users-list">
                    `;
                    users.forEach(user => {
                        // حرف اول نام برای آواتار
                        const firstChar = user.user_name.charAt(0);
                        html += `
                            <div class="archive-user-tag" onclick="viewAdminArchiveDocument('${deliveryDate}', ${user.user_id})">
                                <div class="archive-user-avatar">${escapeHtml(firstChar)}</div>
                                <div>
                                    <span class="archive-user-name">${escapeHtml(user.user_name)}</span>
                                </div>
                                <button class="archive-view-btn" onclick="event.stopPropagation(); viewAdminArchiveDocument('${deliveryDate}', ${user.user_id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        `;
                    });
                    html += `
                            </div>
                        </div>
                    `;
                }
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای یافت نشد</div>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div class="empty-state">خطا در دریافت اطلاعات</div>';
        });
}

function viewAdminArchiveDocument(deliveryDate, userId) {
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

function loadPendingUsersList(forceRefresh = false) {
    const container = document.getElementById('usersPendingList');
    if (!container) return;
    
    // اضافه کردن تایم‌استمپ برای جلوگیری از کش
    let url = 'api/ajax.php?action=get_users_with_pending_approvals';
    if (forceRefresh) {
        url += '&t=' + new Date().getTime();
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.users && data.users.length > 0) {
                let html = '<div class="scrollable-list">';
                data.users.forEach(user => {
                    html += `
                        <div class="user-item" onclick="loadUserPendingDates(${user.user_id})">
                            <div>
                                <div class="user-name">${escapeHtml(user.fullname)}</div>
                                <div class="user-unit">${escapeHtml(user.unit_name)}</div>
                            </div>
                            <div class="user-stat-badge">${user.pending_count} سند</div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="empty-state">هیچ کاربری در انتظار تایید نیست</div>';
            }
        })
        .catch(err => {
            console.error(err);
            container.innerHTML = '<div class="empty-state">خطا در دریافت اطلاعات</div>';
        });
}

function showLeftContent(section) {
    // مخفی کردن همه بخش‌ها
    const sections = ['statsContent', 'usersContent', 'companiesContent', 'filtersContent', 'archiveContent', 'userStatsContent', 'approvalsContent'];
    sections.forEach(s => {
        const el = document.getElementById(s);
        if (el) el.style.display = 'none';
    });
    
    // نمایش بخش انتخاب شده
    const target = document.getElementById(section + 'Content');
    if (target) target.style.display = 'block';
    
    // تغییر کلاس active در منو
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-section') === section) {
            item.classList.add('active');
        }
    });
    
    // مدیریت پنل فیلترها
    const adminFiltersPanel = document.getElementById('adminFiltersPanel');
    
    if (section === 'filters') {
        if (adminFiltersPanel) adminFiltersPanel.classList.add('visible');
        adminAutoSearch();
    } else {
        if (adminFiltersPanel) adminFiltersPanel.classList.remove('visible');
    }
    
    // بارگذاری محتوای هر بخش
    if (section === 'stats') loadAdminUsersStats();
    if (section === 'users') loadUsersList();
    if (section === 'companies') loadCompaniesList();
    if (section === 'archive') loadAdminArchiveList();
    if (section === 'approvals') {
        if (typeof loadPendingUsersList === 'function') {
            loadPendingUsersList(true); // بارگذاری تازه بدون کش
        }
    }
}

function showApprovalsTab(tab) {
    // فعال کردن دکمه تب
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        btn.style.color = '#475569';
        btn.style.borderBottom = 'none';
    });
    event.target.classList.add('active');
    event.target.style.color = '#667eea';
    event.target.style.borderBottom = '2px solid #667eea';
    
    // مخفی کردن همه تب‌ها
    document.getElementById('pendingApprovalsTab').style.display = 'none';
    document.getElementById('revertRequestsTab').style.display = 'none';
    document.getElementById('approvedApprovalsTab').style.display = 'none';
    
    if (tab === 'pending') {
        document.getElementById('pendingApprovalsTab').style.display = 'block';
        // بارگذاری مجدد با جلوگیری از کش
        loadPendingUsersList(true); // true برای forced refresh
    } else if (tab === 'revert') {
        document.getElementById('revertRequestsTab').style.display = 'block';
        loadRevertRequests();
    } else if (tab === 'approved') {
        document.getElementById('approvedApprovalsTab').style.display = 'block';
        loadApprovedApprovals();
    }
}

async function loadUsersList() {
    let container = document.getElementById('usersList');
    container.innerHTML = '<div class="empty-state">در حال بارگذاری...</div>';
    try {
        let res = await fetch(`${apiUrl}?action=get_users`);
        let data = await res.json();
        if (data.success && data.users) {
            let html = '';
            for (let user of data.users) {
                let permissionCheckbox = user.can_view_all_archives == 1 ? 'checked' : '';
                html += `<div class="user-item">
                    <div class="user-info">
                        <div class="user-name">${escapeHtml(user.fullname)}</div>
                        <div class="user-unit">${escapeHtml(user.unit_name)} | ${escapeHtml(user.username)} | کل اسناد: ${user.total_docs}</div>
                        <div class="user-unit" style="margin-top: 5px;">
                            <label style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" class="archive-permission" data-id="${user.id}" ${permissionCheckbox} onchange="toggleArchivePermission(${user.id}, this.checked)">
                                دسترسی به بایگانی همه کاربران
                            </label>
                        </div>
                    </div>
                    <div>
                        <button class="action-btn edit-btn" onclick="editUser(${user.id}, '${escapeHtml(user.username)}', '${escapeHtml(user.fullname)}', '${escapeHtml(user.unit_name)}', ${user.require_doc_date})"><i class="fas fa-edit"></i></button>
                        <button class="action-btn delete-btn" onclick="deleteUser(${user.id})"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>`;
            }
            container.innerHTML = html;
        }
    } catch(e) { console.error(e); }
}

function loadCompaniesList() {
    fetch('api/ajax.php?action=get_companies')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.companies) {
                let html = '<div class="companies-grid">';
                data.companies.forEach(company => {
                    html += `
                        <div class="company-card">
                            <span class="company-name">${escapeHtml(company.name)}</span>
                            <div>
                                <button class="action-btn edit-btn" onclick="editCompany(${company.id}, '${escapeHtml(company.name)}')"><i class="fas fa-edit"></i></button>
                                <button class="action-btn delete-btn" onclick="toggleCompany(${company.id}, 0)"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('companiesList').innerHTML = html;
            }
        });
}

// ========== رفرش خودکار (Polling) ==========
let refreshInterval = null;

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    
    refreshInterval = setInterval(function() {
        // فقط در صورتی که کاربر در بخش آمار کاربران باشد
        const statsContent = document.getElementById('statsContent');
        if (statsContent && statsContent.style.display !== 'none') {
            if (typeof loadAdminUsersStats === 'function') {
                loadAdminUsersStats();
            }
        }
        
        // اگر در بخش مدیریت کاربران است
        const usersContent = document.getElementById('usersContent');
        if (usersContent && usersContent.style.display !== 'none') {
            if (typeof loadUsersList === 'function') {
                loadUsersList();
            }
        }
    }, 5000); // هر 5 ثانیه
}

function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

// شروع رفرش خودکار
startAutoRefresh();

// توقف رفرش هنگام بسته شدن صفحه (اختیاری)
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

function showAddUserModal() {
    document.getElementById('userModalTitle').innerText = 'افزودن کاربر جدید';
    document.getElementById('edit_user_id').value = '';
    document.getElementById('user_username').value = '';
    document.getElementById('user_fullname').value = '';
    document.getElementById('user_unit').value = '';
    document.getElementById('user_password').value = '';
    document.getElementById('user_require_date').checked = true;
    document.getElementById('userModal').classList.add('active');
}

function editUser(id, username, fullname, unit, requireDate) {
    document.getElementById('userModalTitle').innerText = 'ویرایش کاربر';
    document.getElementById('edit_user_id').value = id;
    document.getElementById('user_username').value = username;
    document.getElementById('user_fullname').value = fullname;
    document.getElementById('user_unit').value = unit;
    document.getElementById('user_password').value = '';
    document.getElementById('user_require_date').checked = requireDate == 1;
    document.getElementById('userModal').classList.add('active');
}

async function saveUser() {
    let id = document.getElementById('edit_user_id').value;
    let username = document.getElementById('user_username').value.trim();
    let fullname = document.getElementById('user_fullname').value.trim();
    let unit = document.getElementById('user_unit').value.trim();
    let password = document.getElementById('user_password').value;
    let requireDate = document.getElementById('user_require_date').checked ? 1 : 0;
    if (!username || !fullname || !unit) { showToast('تمامی فیلدها الزامی است', true); return; }
    let url = id ? `${apiUrl}?action=update_user` : `${apiUrl}?action=add_user`;
    let body = id ? { id, username, fullname, unit_name: unit, require_doc_date: requireDate, password } : { username, fullname, unit_name: unit, require_doc_date: requireDate, password };
    if (!id && !password) { showToast('رمز عبور الزامی است', true); return; }
    if (id && !password) delete body.password;
    let res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    let result = await res.json();
    if (result.success) { showToast(id ? 'ویرایش شد' : 'کاربر اضافه شد'); closeUserModal(); loadUsersList(); }
    else showToast(result.error || 'خطا', true);
}

function closeUserModal() { document.getElementById('userModal').classList.remove('active'); }

async function deleteUser(id) {
    if (!confirm('حذف شود؟ کلیه اسناد کاربر نیز حذف می‌شوند')) return;
    let res = await fetch(`${apiUrl}?action=delete_user`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
    let result = await res.json();
    if (result.success) { showToast('حذف شد'); loadUsersList(); }
}

// ========== تغییر دسترسی بایگانی کاربر ==========
async function toggleArchivePermission(userId, isChecked) {
    try {
        let res = await fetch(`${apiUrl}?action=toggle_archive_permission`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, can_view_all_archives: isChecked ? 1 : 0 })
        });
        let result = await res.json();
        if (result.success) {
            showToast('✅ دسترسی با موفقیت به‌روز شد');
        } else {
            showToast('❌ خطا: ' + (result.error || 'مشخص نیست'), true);
        }
    } catch(e) {
        console.error(e);
        showToast('❌ خطا در ارتباط با سرور', true);
    }
}

function showAddCompanyModal() {
    document.getElementById('companyModalTitle').innerText = 'افزودن شرکت جدید';
    document.getElementById('edit_company_id').value = '';
    document.getElementById('company_name').value = '';
    document.getElementById('companyModal').classList.add('active');
}

function editCompany(id, name) {
    document.getElementById('companyModalTitle').innerText = 'ویرایش شرکت';
    document.getElementById('edit_company_id').value = id;
    document.getElementById('company_name').value = name;
    document.getElementById('companyModal').classList.add('active');
}

async function saveCompany() {
    let id = document.getElementById('edit_company_id').value;
    let name = document.getElementById('company_name').value.trim();
    if (!name) { showToast('نام شرکت الزامی است', true); return; }
    let url = id ? `${apiUrl}?action=edit_company` : `${apiUrl}?action=add_company`;
    let body = id ? { id, name } : { name };
    let res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    let result = await res.json();
    if (result.success) { showToast(id ? 'ویرایش شد' : 'شرکت اضافه شد'); closeCompanyModal(); location.reload(); }
    else showToast('خطا', true);
}

function closeCompanyModal() { document.getElementById('companyModal').classList.remove('active'); }

async function toggleCompany(id, isActive) {
    if (!confirm(isActive ? 'غیرفعال شود؟' : 'فعال شود؟')) return;
    let res = await fetch(`${apiUrl}?action=toggle_company`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, is_active: isActive }) });
    let result = await res.json();
    if (result.success) location.reload();
}

document.getElementById('admin_user_select')?.addEventListener('change', searchAdminDocuments);
document.getElementById('admin_filter_number')?.addEventListener('input', searchAdminDocuments);
document.getElementById('admin_filter_date')?.addEventListener('input', searchAdminDocuments);
document.getElementById('admin_filter_company')?.addEventListener('change', searchAdminDocuments);
document.getElementById('admin_filter_delivery')?.addEventListener('input', searchAdminDocuments);

window.openEditModal = function(id, number, date, description) {
    let editId = document.getElementById('edit_id'), editNumber = document.getElementById('edit_number'), editDate = document.getElementById('edit_date'), editCompany = document.getElementById('edit_company_id');
    if (editId) editId.value = id;
    if (editNumber) editNumber.value = number;
    if (editDate) editDate.value = (date === '-' ? '' : date);
    let modal = document.getElementById('editModal');
    if (modal) modal.classList.add('active');
}

window.closeEditModal = function() { document.getElementById('editModal').classList.remove('active'); }

window.saveEdit = async function() {
    let id = document.getElementById('edit_id').value, number = document.getElementById('edit_number').value.trim(), date = document.getElementById('edit_date').value.trim();
    if (!id) { showToast('شناسه سند یافت نشد', true); return; }
    if (!number) { showToast('شماره سند الزامی است', true); return; }
    let res = await fetch(`${apiUrl}?action=update_document`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, doc_number: number, doc_date: date || '-' }) });
    let result = await res.json();
    if (result.success) { showToast('ویرایش شد'); closeEditModal(); if (document.getElementById('filtersContent').style.display !== 'none') { searchAdminDocuments(); } }
    else { showToast(result.error || 'خطا در ویرایش', true); }
}

window.deleteDocument = async function(id) {
    if (!confirm('حذف شود؟')) return;
    let res = await fetch(`${apiUrl}?action=delete_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
    let result = await res.json();
    if (result.success) { showToast('حذف شد'); if (document.getElementById('filtersContent').style.display !== 'none') { searchAdminDocuments(); } }
}

loadUsersList();

<?php endif; ?>

// ========== تابع جستجوی آنی برای کاربر عادی ==========
function autoSearchDocuments() {
    const doc_number = document.getElementById('filter_number')?.value || '';
    const doc_date = document.getElementById('filter_date')?.value || '';
    const company_id = document.getElementById('filter_company')?.value || '';
    const delivery_date = document.getElementById('filter_delivery')?.value || '';
    
    fetch(`api/ajax.php?action=get_documents&doc_number=${encodeURIComponent(doc_number)}&doc_date=${encodeURIComponent(doc_date)}&company_id=${encodeURIComponent(company_id)}&delivery_date=${encodeURIComponent(delivery_date)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('userDocumentsList');
                if (!container) return;
                if (data.groups && data.groups.length > 0) {
                    let html = '';
                    data.groups.forEach(group => {
                        html += `<div class="doc-group"><div class="group-title"><span>${group.delivery_date}</span><span>${group.count} سند</span><button class="print-btn" onclick="viewArchiveDocument('${group.delivery_date}')">مشاهده</button></div>`;
                        html += `<table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th></tr></thead><tbody>`;
                        group.documents.forEach((doc, idx) => {
                            html += `<tr><td>${idx+1}</td><td>${doc.doc_number}</td><td>${doc.doc_date}</td><td>${doc.company_name}</td></tr>`;
                        });
                        html += `</tbody></table></div>`;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
                }
            }
        });
}

function viewArchiveDocument(deliveryDate) {
    window.open(`print.php?delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

// اتصال رویدادها و اجرای اولیه - یک بار
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM آماده است');
    
    // ========== فیلدهای جستجوی کاربر عادی ==========
    const filterNumber = document.getElementById('filter_number');
    const filterDate = document.getElementById('filter_date');
    const filterCompany = document.getElementById('filter_company');
    const filterDelivery = document.getElementById('filter_delivery');
    
    if (filterNumber) filterNumber.addEventListener('input', autoSearchDocuments);
    if (filterDate) filterDate.addEventListener('input', autoSearchDocuments);
    if (filterCompany) filterCompany.addEventListener('change', autoSearchDocuments);
    if (filterDelivery) filterDelivery.addEventListener('input', autoSearchDocuments);
    
    // اجرای اولیه برای نمایش همه اسناد
    autoSearchDocuments();
    
    // بارگذاری آمار کاربر
    if (document.getElementById('userStatsContainer')) {
        loadUserStats();
    }
    
    // ========== دکمه‌های + و - برای تغییر تاریخ تحویل ==========
    const dateMinus = document.getElementById('dateMinus');
    const datePlus = document.getElementById('datePlus');
    const deliveryDateInput = document.getElementById('delivery_date');
    
    if (dateMinus && datePlus && deliveryDateInput) {
        dateMinus.addEventListener('click', function() { changeDeliveryDate(-1); });
        datePlus.addEventListener('click', function() { changeDeliveryDate(1); });
        
        if (typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(deliveryDateInput.value);
        }
    }
    
    // ========== توابع تاریخ شمسی (فقط یک بار تعریف شوند) ==========
    function getDaysInMonthJalali(year, month) {
        if (month >= 1 && month <= 6) return 31;
        if (month >= 7 && month <= 11) return 30;
        if (month === 12) {
            let isLeap = (year % 33 === 1 || year % 33 === 5 || year % 33 === 9 || year % 33 === 13 || year % 33 === 17 || year % 33 === 22 || year % 33 === 26 || year % 33 === 30);
            return isLeap ? 30 : 29;
        }
        return 31;
    }

    let deliveryYear = 1405;
    let deliveryMonth = 1;
    let deliveryDay = 1;

    function updateDeliveryDate() {
        let monthStr = deliveryMonth < 10 ? '0' + deliveryMonth : deliveryMonth;
        let dayStr = deliveryDay < 10 ? '0' + deliveryDay : deliveryDay;
        let dateInput = document.getElementById('delivery_date');
        if (dateInput) { dateInput.value = deliveryYear + '/' + monthStr + '/' + dayStr; }
    }
        
    function addDaysToDelivery(days) {
        deliveryDay += days;
        let daysInMonth = getDaysInMonthJalali(deliveryYear, deliveryMonth);
        if (deliveryDay > daysInMonth) { deliveryDay = 1; deliveryMonth++; if (deliveryMonth > 12) { deliveryMonth = 1; deliveryYear++; } }
        if (deliveryDay < 1) { deliveryMonth--; if (deliveryMonth < 1) { deliveryMonth = 12; deliveryYear--; } deliveryDay = getDaysInMonthJalali(deliveryYear, deliveryMonth); }
        updateDeliveryDate();
        let newDate = deliveryYear + '/' + (deliveryMonth < 10 ? '0'+deliveryMonth : deliveryMonth) + '/' + (deliveryDay < 10 ? '0'+deliveryDay : deliveryDay);
        checkLockStatus(newDate);
        loadDocumentsForDeliveryDate(newDate);
    }

    let initialDelivery = '<?php echo $today; ?>'.split('/');
    if (initialDelivery.length === 3) {
        deliveryYear = parseInt(initialDelivery[0]);
        deliveryMonth = parseInt(initialDelivery[1]);
        deliveryDay = parseInt(initialDelivery[2]);
    }
    updateDeliveryDate();
    let initialDate = deliveryYear + '/' + (deliveryMonth < 10 ? '0'+deliveryMonth : deliveryMonth) + '/' + (deliveryDay < 10 ? '0'+deliveryDay : deliveryDay);
    checkLockStatus(initialDate);

    let minusBtn = document.getElementById('dateMinus');
    let plusBtn = document.getElementById('datePlus');
    if (minusBtn) minusBtn.addEventListener('click', function() { addDaysToDelivery(-1); });
    if (plusBtn) plusBtn.addEventListener('click', function() { addDaysToDelivery(1); });
    
    const descBtn = document.getElementById('submitDescriptionBtn');
    if (descBtn) { 
        descBtn.addEventListener('click', function(e) { 
            e.preventDefault(); 
            if (typeof saveReport === 'function') saveReport(); 
        }); 
    }
    
    const companyNumberInput = document.getElementById('company_number');
    const companySelect = document.getElementById('company_id');
    if (companyNumberInput && companySelect) {
        const companiesList = <?php $list = []; foreach($companies as $c) { $list[] = ['id' => $c['id'], 'name' => $c['name']]; } echo json_encode($list); ?>;
        companyNumberInput.addEventListener('input', function() {
            let num = parseInt(this.value);
            if (isNaN(num)) return;
            if (num > companiesList.length) num = companiesList.length;
            if (num < 1) num = 1;
            const selectedCompany = companiesList[num - 1];
            if (selectedCompany) companySelect.value = selectedCompany.id;
        });
        companyNumberInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('doc_number').focus(); } });
    }
    
    // ========== بارگذاری بایگانی کاربر ==========
    if (document.getElementById('userArchiveList')) {
        loadUserArchiveList();
    }
    
    // ========== جستجوی ادمین ==========
    if (document.getElementById('adminFiltersPanel')) {
        if (typeof bindAdminSearchEvents === 'function') bindAdminSearchEvents();
        if (typeof adminAutoSearch === 'function') adminAutoSearch();
    }
    
    // ========== بارگذاری لیست شرکت‌ها برای ادمین ==========
    if (document.getElementById('companiesList')) {
        if (typeof loadCompaniesList === 'function') loadCompaniesList();
    }
    
    // ========== بارگذاری آمار کاربران برای ادمین ==========
    if (document.getElementById('adminUsersStatsList')) {
        if (typeof loadAdminUsersStats === 'function') loadAdminUsersStats();
    }
    
    // ========== بارگذاری بایگانی ادمین ==========
    if (document.getElementById('adminArchiveList')) {
        if (typeof loadAdminArchiveList === 'function') loadAdminArchiveList();
    }
});
</script>
</body>
</html>