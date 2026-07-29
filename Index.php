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
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.svg">
    <link rel="shortcut icon" href="favicon.svg">
    
    <!-- Font Awesome -->
    <script defer src="assets/js/all.min.js"></script>
    
    <!-- فونت و استایل‌های اصلی -->
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    
    <!-- استایل‌های مدیریت دیتابیس -->
    <link rel="stylesheet" href="assets/css/db_manager.css">
    
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
        
        /* ===== منوی ادمین با استایل جدید ===== */
        .admin-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 8px 10px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .menu-item-wrapper {
            overflow: hidden;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #475569;
            position: relative;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        /* ===== افکت فلش ===== */
        .menu-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            opacity: 0;
            transform: translateX(-16px);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #667eea;
            font-size: 0.6rem;
            flex-shrink: 0;
        }
        
        .menu-item:hover .menu-arrow {
            opacity: 1;
            transform: translateX(0);
        }
        
        .menu-item.active .menu-arrow {
            opacity: 1;
            transform: translateX(0);
            color: #fff;
        }
        
        /* ===== آیکون ===== */
        .menu-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            font-size: 0.8rem;
            color: #667eea;
            transition: all 0.3s ease;
            flex-shrink: 0;
            background: #eef2ff;
            border-radius: 8px;
        }
        
        .menu-item:hover .menu-icon {
            background: #667eea;
            color: #fff;
            transform: scale(1.05);
        }
        
        .menu-item.active .menu-icon {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        
        /* ===== لیبل ===== */
        .menu-label {
            flex: 1;
            transition: all 0.3s ease;
            font-weight: 500;
            color: #475569;
            font-size: 0.75rem;
        }
        
        .menu-item:hover .menu-label {
            color: #1e293b;
            transform: translateX(4px);
        }
        
        .menu-item.active .menu-label {
            color: #ffffff;
        }
        
        /* ===== حالت Active ===== */
        .menu-item.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .menu-item.active .menu-label {
            color: #ffffff;
        }
        
        .menu-item.active .menu-icon {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
        }
        
        /* ===== هاور ===== */
        .menu-item:not(.active):hover {
            background: #eef2ff;
            transform: translateX(4px);
        }
        
        /* ===== ریسپانسیو منو ===== */
        @media (max-width: 768px) {
            .admin-menu {
                padding: 6px 8px;
                gap: 3px;
            }
            .menu-item {
                padding: 8px 10px;
                font-size: 0.65rem;
                gap: 8px;
            }
            .menu-arrow {
                width: 18px;
                height: 18px;
                font-size: 0.5rem;
            }
            .menu-icon {
                width: 22px;
                height: 22px;
                font-size: 0.65rem;
            }
            .menu-label {
                font-size: 0.65rem;
            }
        }
        
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
        
        /* ===== دو ستون کنار هم ===== */
        .stats-grid-new.two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
        
        /* ===== تاریخ و زمان در یک خط ===== */
        #confirmStatsDate { display: flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap; font-size: 0.65rem; font-weight: 600; color: #1e293b; }
        
        /* ===== دکمه‌های ناوبری ===== */
        .date-nav-btn { width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #475569; font-size: 0.7rem; flex-shrink: 0; }
        .date-nav-btn:hover { background: #667eea; color: white; border-color: #667eea; transform: scale(1.05); }
        .date-nav-btn:active { transform: scale(0.95); }
        .date-nav-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
        
        /* ===== آمار کاربران واحد ===== */
        .unit-users-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 0; max-height: 250px; overflow-y: auto; }
        .unit-users-grid::-webkit-scrollbar { width: 4px; }
        .unit-users-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .unit-users-grid::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .unit-user-card { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 30px; padding: 3px 10px 3px 3px; transition: all 0.15s; cursor: default; flex-shrink: 0; }
        .unit-user-card:hover { background: white; border-color: #94a3b8; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .unit-user-avatar { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.5rem; font-weight: 700; color: white; flex-shrink: 0; }
        .unit-user-name { font-size: 0.55rem; font-weight: 500; color: #1e293b; white-space: nowrap; }
        .unit-user-count { font-size: 0.5rem; font-weight: 700; color: #3b82f6; background: #dbeafe; padding: 0px 5px; border-radius: 20px; min-width: 18px; text-align: center; }
        
        /* ===== دکمه گزارش با رنگ قابل توجه ===== */
        .btn-report-toggle {
            position: relative;
            padding: 6px 14px;
            border: none;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            overflow: hidden;
            z-index: 1;
            font-family: inherit;
            white-space: nowrap;
            margin-right: auto;
        }
        
        /* دکمه گزارش ثبت - صورتی جذاب */
        .btn-report-toggle.register-mode {
            background: linear-gradient(135deg, #ec4899, #f472b6);
            color: white;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4);
        }
        
        .btn-report-toggle.register-mode:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 25px rgba(236, 72, 153, 0.5);
        }
        
        .btn-report-toggle.register-mode:active {
            transform: scale(0.96);
        }
        
        /* دکمه گزارش تایید - بنفش/آبی */
        .btn-report-toggle.confirm-mode {
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        
        .btn-report-toggle.confirm-mode:hover {
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 6px 25px rgba(139, 92, 246, 0.5);
        }
        
        .btn-report-toggle.confirm-mode:active {
            transform: scale(0.96);
        }
        
        /* دکمه غیرفعال */
        .btn-report-toggle:not(.register-mode):not(.confirm-mode) {
            background: linear-gradient(135deg, #94a3b8, #cbd5e1);
            color: #475569;
            box-shadow: 0 2px 10px rgba(148, 163, 184, 0.2);
        }
        
        .btn-report-toggle:not(.register-mode):not(.confirm-mode):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(148, 163, 184, 0.3);
        }
        
        /* افکت درخشش */
        .btn-report-toggle::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: -1;
        }
        
        .btn-report-toggle:hover::before {
            opacity: 1;
        }
        
        /* نبض زدن */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(236, 72, 153, 0.4); }
            50% { box-shadow: 0 0 20px 10px rgba(236, 72, 153, 0.1); }
        }
        
        .btn-report-toggle.register-mode {
            animation: pulse-glow 2s infinite;
        }
        
        .btn-report-toggle.confirm-mode {
            animation: pulse-glow 2s infinite;
        }
        
        @media (max-width: 500px) { .unit-user-avatar { width: 20px; height: 20px; font-size: 0.4rem; } .unit-user-name { font-size: 0.45rem; } .unit-user-count { font-size: 0.4rem; padding: 0px 3px; min-width: 14px; } .unit-user-card { padding: 2px 6px 2px 2px; } .btn-report-toggle { font-size: 0.5rem; padding: 4px 10px; } }
        
        @media (max-width: 768px) { .stats-grid-new.two-cols { grid-template-columns: 1fr; } }
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
        
        /* دکمه بارگذاری اسناد تایید */
        .btn-load { width: 100%; background: linear-gradient(135deg, #38bdf8, #0ea5e9); color: white; border: none; padding: 9px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; cursor: pointer; margin-top: 8px; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-load:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-load i { font-size: 0.9rem; }
		
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
		
        /* ===== نمایش توضیحات اسناد با طراحی کارت جذاب ===== */
        .descriptions-section { margin-top: 15px; padding: 16px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 16px; border: 1px solid #e2e8f0; }
        .desc-title { font-size: 0.75rem; font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .desc-title i { color: #667eea; }
        .desc-grid { display: grid; gap: 10px; }
        .desc-item { padding: 10px 14px; border-radius: 12px; font-size: 0.65rem; color: #1e293b; border: 1px solid #e2e8f0; transition: all 0.2s ease; display: flex; align-items: flex-start; gap: 8px; word-break: break-word; line-height: 1.5; }
        .desc-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .desc-item .desc-number { font-weight: 700; color: #667eea; background: #eef2ff; padding: 1px 10px; border-radius: 20px; white-space: nowrap; font-size: 0.6rem; flex-shrink: 0; }
        .desc-item .desc-text { flex: 1; color: #475569; }
        .desc-item .desc-text small { color: #94a3b8; font-size: 0.55rem; }
        .desc-item:nth-child(1) { background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-color: #6ee7b7; }
        .desc-item:nth-child(2) { background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-color: #93c5fd; }
        .desc-item:nth-child(3) { background: linear-gradient(135deg, #fef3c7, #fde68a); border-color: #fcd34d; }
        .desc-item:nth-child(4) { background: linear-gradient(135deg, #fce7f3, #fbcfe8); border-color: #f9a8d4; }
        .desc-item:nth-child(5) { background: linear-gradient(135deg, #ede9fe, #ddd6fe); border-color: #c4b5fd; }
        .desc-item:nth-child(6) { background: linear-gradient(135deg, #fef2f2, #fecaca); border-color: #fca5a5; }
        .desc-item:nth-child(7) { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-color: #6ee7b7; }
        .desc-item:nth-child(8) { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-color: #93c5fd; }
        
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
        /* ========== گزارش برهان ========== */
        .reports-filters {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-end;
            gap: 6px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            margin-bottom: 5px;
            overflow-x: auto;
        }
        .reports-filters .filter-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 0 0 auto;
        }
        .reports-filters .filter-group label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .reports-filters .filter-group label i {
            font-size: 0.6rem;
            color: #667eea;
        }
        .reports-filters .filter-group select,
        .reports-filters .filter-group input {
            padding: 8px 10px;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            background: white;
            font-size: 0.7rem;
            transition: all 0.2s;
        }
        .reports-filters .filter-group select:focus,
        .reports-filters .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }

        /* ===== اندازه‌های دقیق فیلدها ===== */
        .reports-filters .filter-group.filter-number input { width: 70px; } /* 6 کاراکتر */
        .reports-filters .filter-group.filter-user select { width: 90px; }
        .reports-filters .filter-group.filter-year select { width: 75px; } /* 1405 */
        .reports-filters .filter-group.filter-company select { width: 120px; }
        .reports-filters .filter-group.filter-date input { width: 95px; } /* 1404/01/01 */
        .reports-filters .filter-group.filter-type select { width: 100px; } /* آخر ردیف */

        @media (max-width: 900px) {
            .reports-filters { flex-wrap: wrap; gap: 4px; padding: 10px 12px; }
            .reports-filters .filter-group.filter-number input { width: 60px; }
            .reports-filters .filter-group.filter-user select { width: 75px; }
            .reports-filters .filter-group.filter-year select { width: 60px; }
            .reports-filters .filter-group.filter-company select { width: 100px; }
            .reports-filters .filter-group.filter-date input { width: 85px; }
            .reports-filters .filter-group.filter-type select { width: 85px; }
        }
        @media (max-width: 600px) {
            .reports-filters { flex-wrap: wrap; gap: 4px; padding: 8px 10px; }
            .reports-filters .filter-group { flex: 1 1 auto; min-width: 60px; }
            .reports-filters .filter-group label { font-size: 0.5rem; }
            .reports-filters .filter-group select,
            .reports-filters .filter-group input { font-size: 0.6rem; padding: 4px 6px; width: 100%; }
        }

        #reportTable th { position: sticky; top: 0; z-index: 10; }
        #reportTable tbody tr:nth-child(even) { background: #f8fafc; }
        #reportTable tbody tr:hover { background: #eef2ff; }
        #reportTable tbody tr.row-new { background: #d1fae5 !important; }
        #reportTable tbody tr.row-new:hover { background: #a7f3d0 !important; }
        #reportTable tbody tr.row-edit { background: #fef3c7 !important; }
        #reportTable tbody tr.row-edit:hover { background: #fde68a !important; }
        #reportTable tbody tr.row-delete { background: #fee2e2 !important; }
        #reportTable tbody tr.row-delete:hover { background: #fca5a5 !important; }

        .badge { padding: 2px 10px; border-radius: 20px; font-size: 0.6rem; font-weight: 600; }
        .badge-success { background: #10b981; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-danger { background: #ef4444; color: white; }
        .badge-neutral { background: #f1f5f9; color: #475569; }

        /* ========== سایز فونت ستون‌ها ========== */
        #reportTable td:nth-child(2), #reportTable td:nth-child(3), #reportTable td:nth-child(5), #reportTable td:nth-child(6), #reportTable td:nth-child(9), #reportTable td:nth-child(10), #reportTable td:nth-child(11), #reportTable td:nth-child(12) { font-size: 0.55rem !important; }
        #reportTable td:nth-child(4), #reportTable td:nth-child(7), #reportTable td:nth-child(8) { font-size: 0.7rem !important; }

        /* ========== آمار لحظه‌ای ========== */
        .report-stats-container { background: white; border-radius: 16px; border: 1px solid #e2e8f0; padding: 16px; margin-bottom: 15px; }
        .report-stats-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .report-stats-title { font-size: 0.75rem; font-weight: 700; color: #1e293b; }
        .report-stats-title i { color: #667eea; margin-left: 6px; }
        .report-stats-date { display: flex; align-items: center; gap: 8px; }
        .report-stats-date .date-quick-btn { padding: 4px 12px; border-radius: 20px; border: 1px solid #e2e8f0; background: #f8fafc; font-size: 0.6rem; cursor: pointer; transition: all 0.2s; color: #475569; }
        .report-stats-date .date-quick-btn:hover { background: #667eea; color: white; border-color: #667eea; }
        .report-stats-date input { padding: 4px 10px; border-radius: 20px; border: 1px solid #e2e8f0; font-size: 0.6rem; width: 100px; text-align: center; }
        .report-stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
        .stat-card-new { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-radius: 12px; transition: all 0.2s; }
        .stat-card-new:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-card-new .stat-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 1rem; }
        .stat-card-new .stat-info { flex: 1; }
        .stat-card-new .stat-label { font-size: 0.6rem; font-weight: 500; color: #64748b; }
        .stat-card-new .stat-number { font-size: 1.1rem; font-weight: 800; }
        .stat-new { background: #d1fae5; }
        .stat-new .stat-icon { background: #10b98120; color: #059669; }
        .stat-new .stat-number { color: #065f46; }
        .stat-edit { background: #fef3c7; }
        .stat-edit .stat-icon { background: #f59e0b20; color: #b45309; }
        .stat-edit .stat-number { color: #92400e; }
        .stat-delete { background: #fee2e2; }
        .stat-delete .stat-icon { background: #ef444420; color: #dc2626; }
        .stat-delete .stat-number { color: #991b1b; }
        .stat-total { background: #dbeafe; }
        .stat-total .stat-icon { background: #3b82f620; color: #2563eb; }
        .stat-total .stat-number { color: #1e40af; }
        .stat-login-success { background: #dbeafe; }
        .stat-login-success .stat-icon { background: #3b82f620; color: #2563eb; }
        .stat-login-success .stat-number { color: #1e40af; }
        .stat-login-fail { background: #fee2e2; }
        .stat-login-fail .stat-icon { background: #ef444420; color: #dc2626; }
        .stat-login-fail .stat-number { color: #991b1b; }
        @media (max-width: 1200px) { .report-stats-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 768px) { .report-stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 500px) { .report-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 380px) { .report-stats-grid { grid-template-columns: 1fr; } }

        /* ========== گزارش ثبت جدید (حسابداری) ========== */
        .report-warehouse-container { 
            border-radius: 16px; 
            padding: 0 !important; 
            margin: 10px 0; 
            overflow: hidden; 
            border: 2px solid rgba(255, 255, 255, 0.8) !important;
            box-shadow: 20px 20px 60px rgba(190, 190, 190, 0.3), -20px -20px 60px rgba(255, 255, 255, 0.5);
        }
        .report-warehouse-container .card-content { padding: 12px 16px; background: rgba(255,255,255,0.88); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 14px; border: none !important; }
        .report-warehouse-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
        .report-warehouse-title { font-size: 0.75rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .report-warehouse-title i { color: #3b82f6; font-size: 0.9rem; }
        .report-warehouse-date { font-size: 0.55rem; font-weight: 400; color: #94a3b8; background: #f1f5f9; padding: 1px 8px; border-radius: 20px; }
        .report-warehouse-total { font-size: 0.65rem; font-weight: 600; color: #1e293b; background: #eef2ff; padding: 2px 12px; border-radius: 20px; }
        .report-warehouse-grid { display: flex; flex-wrap: wrap; gap: 6px; padding: 0; max-height: 350px; overflow-y: auto; }
        .report-warehouse-grid:empty { display: none; }
        .report-warehouse-grid::-webkit-scrollbar { width: 4px; }
        .report-warehouse-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .report-warehouse-grid::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .report-warehouse-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4px 0; color: #94a3b8; text-align: center; }
        .report-warehouse-empty i { font-size: 0.8rem; opacity: 0.4; margin-bottom: 2px; }
        .report-warehouse-empty p { font-size: 0.6rem; font-weight: 500; margin: 0; }
        .report-warehouse-empty small { font-size: 0.5rem; opacity: 0.7; }
        .warehouse-user-card { display: flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #f8afc, #f1f5f9); border: 1px solid #e2e8f0; border-radius: 40px; padding: 3px 10px 3px 3px; transition: all .2s; cursor: default; min-width: 70px; flex-shrink: 0; }
        .warehouse-user-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); border-color: #3b82f6; background: #fff; }
        .warehouse-user-avatar { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .5rem; font-weight: 700; color: #fff; flex-shrink: 0; }
        .warehouse-user-name { font-size: .6rem; font-weight: 600; color: #1e293b; white-space: nowrap; }
        .warehouse-user-count { font-size: .55rem; font-weight: 700; color: #3b82f6; background: #dbeafe; padding: 1px 6px; border-radius: 20px; margin-right: auto; }
        .toggle-report-btn { position: relative; padding: 6px 16px; border: none; border-radius: 30px; font-size: .6rem; font-weight: 600; cursor: pointer; transition: all .3s cubic-bezier(.34,1.56,.64,1); display: inline-flex; align-items: center; gap: 6px; overflow: hidden; z-index: 1; font-family: inherit; white-space: nowrap; }
        .toggle-report-btn.register-mode { background: linear-gradient(135deg,#ec4899,#f472b6); color: #fff; box-shadow: 0 4px 15px rgba(236,72,153,.4); }
        .toggle-report-btn.register-mode:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 6px 25px rgba(236,72,153,.5); }
        .toggle-report-btn.register-mode:active { transform: scale(.96); }
        .toggle-report-btn.confirm-mode { background: linear-gradient(135deg,#8b5cf6,#a78bfa); color: #fff; box-shadow: 0 4px 15px rgba(139,92,246,.4); }
        .toggle-report-btn.confirm-mode:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 6px 25px rgba(139,92,246,.5); }
        .toggle-report-btn.confirm-mode:active { transform: scale(.96); }
        .toggle-report-btn::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle,rgba(255,255,255,.15) 0%,transparent 60%); opacity: 0; transition: opacity .4s ease; z-index: -1; }
        .toggle-report-btn:hover::before { opacity: 1; }
        @keyframes pulse-glow-report { 0%,100% { box-shadow: 0 0 0 0 rgba(236,72,153,.4); } 50% { box-shadow: 0 0 20px 10px rgba(236,72,153,.1); } }
        .toggle-report-btn.register-mode { animation: pulse-glow-report 2s infinite; }
        .toggle-report-btn.confirm-mode { animation: pulse-glow-report 2s infinite; }
        .warehouse-card-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px 20px; }
        .bounce-loader { display: flex; align-items: center; justify-content: center; gap: 12px; }
        .bounce-dot { width: 16px; height: 16px; background: linear-gradient(135deg,#667eea,#764ba2); border-radius: 50%; animation: bounceDot 1.4s ease-in-out infinite both; box-shadow: 0 2px 8px rgba(102,126,234,.3); }
        .bounce-dot:nth-child(1) { animation-delay: -.32s; }
        .bounce-dot:nth-child(2) { animation-delay: -.16s; }
        .bounce-dot:nth-child(3) { animation-delay: 0s; }
        @keyframes bounceDot { 0%,80%,100% { transform: scale(.6); opacity: .4; } 40% { transform: scale(1); opacity: 1; } }
        @media (max-width:500px) { .report-warehouse-grid { max-height: 200px; } .warehouse-user-card { padding: 2px 6px 2px 2px; min-width: 50px; } .warehouse-user-name { font-size: .5rem; } .warehouse-user-count { font-size: .45rem; padding: 0 4px; } .toggle-report-btn { font-size: .5rem; padding: 4px 10px; } }

        /* ===== رنگ پس زمینه هدر هر واحد ===== */
        .unit-column-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            background: linear-gradient(135deg, #fdf2f8, #fce7f3); 
            border-bottom: 1px solid #fbcfe8; 
        }
        .unit-column[data-unit="تنخواه"] .unit-column-header { background: linear-gradient(135deg, #fdf2f8, #fce7f3); border-bottom-color: #fbcfe8; }
        .unit-column[data-unit="خزانه"] .unit-column-header { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-bottom-color: #bbf7d0; }
        .unit-column[data-unit="فاکتور"] .unit-column-header { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom-color: #bfdbfe; }
        .unit-column[data-unit="پیمانکاران"] .unit-column-header { background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-bottom-color: #ddd6fe; }
        .unit-column[data-unit="درآمد"] .unit-column-header { background: linear-gradient(135deg, #fef2f2, #fee2e2); border-bottom-color: #fecaca; }
        .unit-column[data-unit="سایر"] .unit-column-header { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); border-bottom-color: #cbd5e1; }

        .unit-column-icon { flex-shrink: 0; }
        .unit-column-name { font-weight: 700; color: #1e293b; flex: 1; margin: 0 4px; white-space: nowrap; }
        .unit-column-total { font-weight: 800; color: #ffffff; background: linear-gradient(135deg, #059669, #10b981); border-radius: 20px; text-align: center; box-shadow: 0 2px 8px rgba(16,185,129,0.3); letter-spacing: 0.5px; flex-shrink: 0; }
        .unit-column[data-unit="تنخواه"] .unit-column-total { background: linear-gradient(135deg, #d97706, #f59e0b); box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
        .unit-column[data-unit="خزانه"] .unit-column-total { background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 2px 8px rgba(16,185,129,0.3); }
        .unit-column[data-unit="فاکتور"] .unit-column-total { background: linear-gradient(135deg, #2563eb, #3b82f6); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
        .unit-column[data-unit="پیمانکاران"] .unit-column-total { background: linear-gradient(135deg, #7c3aed, #8b5cf6); box-shadow: 0 2px 8px rgba(139,92,246,0.3); }
        .unit-column[data-unit="درآمد"] .unit-column-total { background: linear-gradient(135deg, #dc2626, #ef4444); box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
        .unit-column[data-unit="سایر"] .unit-column-total { background: linear-gradient(135deg, #475569, #64748b); box-shadow: 0 2px 8px rgba(100,116,139,0.3); }
        .unit-column-users { display: flex; flex-direction: column; }
        /* ========== پایان گزارش ثبت جدید ========== */

        /* ========== انیمیشن دایره اعلان ========== */
        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }
        .report-notification { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #ef4444; margin-right: 6px; animation: pulse-dot 1.5s infinite; }
        .report-notification.green { background: #10b981; }
        .report-notification.yellow { background: #f59e0b; }
        .report-notification.red { background: #ef4444; }

        /* ========== انیمیشن Toast ========== */
        #toast { position: fixed; bottom: 30px; right: 30px; padding: 12px 24px; border-radius: 14px; color: white; font-size: 0.85rem; font-weight: 500; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: none; align-items: center; gap: 10px; z-index: 9999; max-width: 400px; direction: rtl; animation-duration: 0.5s; }
        #toast i { font-size: 1.1rem; }
        
        /* ===== چشمک زدن دکمه ثبت (سند تکراری) ===== */
        @keyframes blink-error {
            0% { background: #ef4444; transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
            25% { background: #f59e0b; transform: scale(1.05); box-shadow: 0 0 20px 5px rgba(239,68,68,0.3); }
            50% { background: #ef4444; transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
            75% { background: #f59e0b; transform: scale(1.05); box-shadow: 0 0 20px 5px rgba(239,68,68,0.3); }
            100% { background: #ef4444; transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
        }
        .blink-btn {
            animation: blink-error 0.5s ease 4 !important;
            color: white !important;
            font-weight: 700 !important;
        }
		
        /* ========== چیدمان عمودی واحدها ========== */
        .unit-column { flex: 1 1 0; min-width: 100px; max-width: none; background: white; border-radius: 12px; border: 1px solid #eef2f6; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s ease; }
        .unit-column.six-columns { flex: 1 1 0; min-width: 100px; }
        .unit-column.six-columns .unit-column-header { padding: 6px 10px; }
        .unit-column.six-columns .unit-column-icon { font-size: 0.85rem; }
        .unit-column.six-columns .unit-column-name { font-size: 0.7rem; }
        .unit-column.six-columns .unit-column-total { font-size: 0.8rem; padding: 1px 8px; min-width: 24px; }
        .unit-column.six-columns .user-card-vertical { padding: 3px 7px 3px 3px; border-radius: 18px; gap: 4px; }
        .unit-column.six-columns .user-avatar-vertical { width: 20px; height: 20px; font-size: 0.45rem; }
        .unit-column.six-columns .user-name-vertical { font-size: 0.55rem; }
        .unit-column.six-columns .user-count-vertical { font-size: 0.6rem; padding: 1px 6px; min-width: 20px; }
        .unit-column.six-columns .unit-column-users { padding: 4px 8px; gap: 4px; }
        .unit-column:not(.six-columns) { flex: 1 1 0; min-width: 130px; }
        .unit-column:not(.six-columns) .unit-column-header { padding: 8px 12px; }
        .unit-column:not(.six-columns) .unit-column-icon { font-size: 0.9rem; }
        .unit-column:not(.six-columns) .unit-column-name { font-size: 0.8rem; }
        .unit-column:not(.six-columns) .unit-column-total { font-size: 0.9rem; padding: 1px 10px; min-width: 28px; }
        .unit-column:not(.six-columns) .user-card-vertical { padding: 4px 10px 4px 4px; border-radius: 24px; gap: 6px; }
        .unit-column:not(.six-columns) .user-avatar-vertical { width: 26px; height: 26px; font-size: 0.6rem; }
        .unit-column:not(.six-columns) .user-name-vertical { font-size: 0.65rem; }
        .unit-column:not(.six-columns) .user-count-vertical { font-size: 0.7rem; padding: 1px 10px; min-width: 26px; }
        .unit-column:not(.six-columns) .unit-column-users { padding: 6px 10px; gap: 5px; }
        .unit-column-header { display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-bottom: 1px solid #e2e8f0; }
        .unit-column-icon { flex-shrink: 0; }
        .unit-column-name { font-weight: 700; color: #1e293b; flex: 1; margin: 0 4px; white-space: nowrap; }
        .unit-column-total { font-weight: 800; color: #ffffff; background: linear-gradient(135deg, #059669, #10b981); border-radius: 20px; text-align: center; box-shadow: 0 2px 8px rgba(16,185,129,0.3); letter-spacing: 0.5px; flex-shrink: 0; }
        .unit-column[data-unit="تنخواه"] .unit-column-total { background: linear-gradient(135deg, #d97706, #f59e0b); box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
        .unit-column[data-unit="خزانه"] .unit-column-total { background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 2px 8px rgba(16,185,129,0.3); }
        .unit-column[data-unit="فاکتور"] .unit-column-total { background: linear-gradient(135deg, #2563eb, #3b82f6); box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
        .unit-column[data-unit="پیمانکاران"] .unit-column-total { background: linear-gradient(135deg, #7c3aed, #8b5cf6); box-shadow: 0 2px 8px rgba(139,92,246,0.3); }
        .unit-column[data-unit="درآمد"] .unit-column-total { background: linear-gradient(135deg, #dc2626, #ef4444); box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
        .unit-column[data-unit="سایر"] .unit-column-total { background: linear-gradient(135deg, #475569, #64748b); box-shadow: 0 2px 8px rgba(100,116,139,0.3); }
        .unit-column-users { display: flex; flex-direction: column; }
        .user-card-vertical { display: flex; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; transition: all 0.15s; cursor: default; min-width: 0; max-width: 100%; overflow: hidden; }
        .user-card-vertical:hover { background: white; border-color: #94a3b8; transform: translateX(2px); }
        .user-avatar-vertical { border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; flex-shrink: 0; }
        .user-name-vertical { font-weight: 500; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1 1 auto; min-width: 0; }
        .user-count-vertical { font-weight: 700; color: #3b82f6; background: #dbeafe; border-radius: 20px; text-align: center; flex-shrink: 0; }
        @media (max-width: 1024px) { .unit-column:not(.six-columns) { min-width: 110px; } .unit-column.six-columns { min-width: 90px; } .unit-column.six-columns .user-avatar-vertical { width: 18px; height: 18px; font-size: 0.4rem; } .unit-column.six-columns .user-name-vertical { font-size: 0.5rem; } .unit-column.six-columns .user-count-vertical { font-size: 0.55rem; padding: 0px 5px; min-width: 18px; } .unit-column.six-columns .unit-column-name { font-size: 0.65rem; } .unit-column.six-columns .unit-column-total { font-size: 0.75rem; padding: 0px 7px; min-width: 22px; } }
        @media (max-width: 768px) { .unit-column, .unit-column.six-columns, .unit-column:not(.six-columns) { flex: 0 0 calc(50% - 6px) !important; min-width: 0 !important; } .unit-column .user-avatar-vertical { width: 20px; height: 20px; font-size: 0.45rem; } .unit-column .user-name-vertical { font-size: 0.55rem; } .unit-column .user-count-vertical { font-size: 0.6rem; padding: 0px 6px; min-width: 20px; } .unit-column .unit-column-name { font-size: 0.65rem; } .unit-column .unit-column-total { font-size: 0.75rem; padding: 0px 7px; min-width: 20px; } .unit-column.six-columns .user-avatar-vertical { width: 18px; height: 18px; font-size: 0.4rem; } .unit-column.six-columns .user-name-vertical { font-size: 0.5rem; } .unit-column.six-columns .user-count-vertical { font-size: 0.5rem; padding: 0px 5px; min-width: 16px; } .unit-column.six-columns .unit-column-name { font-size: 0.6rem; } .unit-column.six-columns .unit-column-total { font-size: 0.7rem; padding: 0px 6px; min-width: 18px; } }
        /* ===== دکمه تغییر نوع گزارش ===== */
        .toggle-report-btn { padding: 5px 14px; border-radius: 30px; border: none; font-size: 0.6rem; font-weight: 600; cursor: pointer; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); background: linear-gradient(135deg, #10b981, #059669); color: white; box-shadow: 0 2px 10px rgba(16,185,129,0.3); display: flex; align-items: center; gap: 5px; white-space: nowrap; letter-spacing: 0.3px; }
        .toggle-report-btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 4px 18px rgba(16,185,129,0.45); }
        .toggle-report-btn:active { transform: scale(0.96); }
        .toggle-report-btn i { font-size: 0.65rem; }
        .toggle-report-btn.confirm-mode { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 2px 10px rgba(59,130,246,0.3); }
        .toggle-report-btn.confirm-mode:hover { box-shadow: 0 4px 18px rgba(59,130,246,0.45); }
        
        /* ===== هدر گزارش ثبت/تایید ===== */
        .report-warehouse-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 4px; }
        .report-warehouse-title { font-size: 0.75rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .report-warehouse-title i { color: #3b82f6; font-size: 0.9rem; }
        .report-warehouse-date { font-size: 0.55rem; font-weight: 400; color: #94a3b8; background: #f1f5f9; padding: 1px 8px; border-radius: 20px; }
        .report-warehouse-total { font-size: 0.65rem; font-weight: 600; color: #1e293b; background: #eef2ff; padding: 2px 12px; border-radius: 20px; flex-shrink: 0; }
        .report-warehouse-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: center; }
        /* ===== انیمیشن لودینگ جدید ===== */
        .report-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 20px;
            min-height: 300px;
            animation: fadeInLoading 0.5s ease;
        }
        @keyframes fadeInLoading {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* متن Loading با نقطه */
        .loading-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            direction: ltr;
        }
        .loading-text .dot {
            display: inline-block;
            animation: blink 1.5s infinite;
            font-weight: 700;
            color: #667eea;
        }
        @keyframes blink {
            0%, 100% { opacity: 0; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }
        
        /* کانتینر نوار پیشرفت */
        .loading-bar-container {
            display: flex;
            align-items: center;
            box-sizing: border-box;
            padding: 5px;
            width: 220px;
            height: 32px;
            background: #212121;
            box-shadow: inset -2px 2px 4px #0c0c0c;
            border-radius: 15px;
            direction: ltr;
        }
        
        /* نوار پیشرفت */
        .loading-bar {
            position: relative;
            display: flex;
            justify-content: flex-start;
            flex-direction: column;
            width: 0%;
            height: 22px;
            overflow: hidden;
            border-radius: 10px;
            background: linear-gradient(to right, #de4a0f, #f9c74f);
            transition: width 0.3s ease;
        }
        
        /* نوارهای سفید متحرک داخل بار */
        .loading-bar-shine {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 18px;
            width: 100%;
            height: 100%;
            animation: shineMove 1.5s linear infinite;
        }
        .loading-bar-shine::before,
        .loading-bar-shine::after {
            content: '';
            display: block;
            width: 10px;
            height: 45px;
            opacity: 0.3;
            transform: rotate(45deg);
            background: linear-gradient(to top right, white, transparent);
        }
        .loading-bar-shine::before {
            margin-left: 10px;
        }
        .loading-bar-shine::after {
            margin-left: auto;
            margin-right: 10px;
        }
        
        /* حرکت نوارهای سفید */
        @keyframes shineMove {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* ===== درصد و مراحل ===== */
        .loading-percent {
            font-size: 1rem;
            font-weight: 700;
            color: #667eea;
            margin-top: 12px;
            direction: ltr;
        }
        .loading-steps {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            direction: rtl;
        }
        .loading-steps .step {
            font-size: 0.65rem;
            color: #94a3b8;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: all 0.5s ease;
        }
        .loading-steps .step.active {
            color: #667eea;
            background: #eef2ff;
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102,126,234,0.2);
        }
        .loading-steps .step.done {
            color: #10b981;
            background: #d1fae5;
            border-color: #10b981;
        }
        .loading-steps .step.done::before {
            content: '✅ ';
        }
        
        /* ===== لودینگ ضربان‌دار (Bouncing Loader) ===== */
        .warehouse-card-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px 20px; min-height: 150px; animation: fadeInLoading 0.3s ease; }
        .bounce-loader { display: flex; align-items: center; justify-content: center; gap: 12px; }
        .bounce-dot { width: 16px; height: 16px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; animation: bounceDot 1.4s ease-in-out infinite both; box-shadow: 0 2px 8px rgba(102,126,234,0.3); }
        .bounce-dot:nth-child(1) { animation-delay: -0.32s; }
        .bounce-dot:nth-child(2) { animation-delay: -0.16s; }
        .bounce-dot:nth-child(3) { animation-delay: 0s; }
        @keyframes bounceDot { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }
        /* ===== پایان لودینگ ضربان‌دار ===== */
        /* ===== دکمه راهنما ===== */
        .help-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #667eea;
            margin-right: 10px;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 50%;
        }
        .help-btn:hover {
            color: #764ba2;
            transform: scale(1.1);
            background: #eef2ff;
        }
        .help-btn:active {
            transform: scale(0.95);
        }
		/* ===== دکمه‌های ناوبری تاریخ ===== */
        .date-nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: #475569;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .date-nav-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: scale(1.05);
        }
        .date-nav-btn:active {
            transform: scale(0.95);
        }
        .date-nav-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }
		
		/* ===== مودال مدیریت دیتابیس ===== */
        #dbManagerModal {
            z-index: 2000;
        }
        
        #dbManagerModal .modal-content {
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            padding: 0;
            border-radius: 20px;
            overflow: hidden;
            background: white;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }
        
        #dbManagerModal .modal-header {
            padding: 18px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
            flex-shrink: 0;
        }
        
        #dbManagerModal .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        #dbManagerModal .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #dbManagerModal .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        #dbManagerModal .modal-body {
            padding: 20px 24px 24px;
            max-height: calc(90vh - 80px);
            overflow-y: auto;
        }
        
        #dbManagerModal .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        #dbManagerModal .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        #dbManagerModal .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        
        #dbManagerModal .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        @media (max-width: 768px) {
            #dbManagerModal .modal-content {
                max-width: 98%;
                max-height: 95vh;
            }
            
            #dbManagerModal .modal-body {
                padding: 16px;
                max-height: calc(95vh - 70px);
            }
            
            #dbManagerModal .modal-header {
                padding: 14px 18px;
            }
            
            #dbManagerModal .modal-header h3 {
                font-size: 0.95rem;
            }
        }
        
        @media (max-width: 480px) {
            #dbManagerModal .modal-header {
                padding: 12px 14px;
            }
            
            #dbManagerModal .modal-header h3 {
                font-size: 0.85rem;
            }
            
            #dbManagerModal .modal-body {
                padding: 12px;
            }
        }
		
        /* ===== هایلایت قرمز (کوچک‌ترین سال در ۳ سال یا بیشتر) ===== */
        .year-highlight.year-pink {
            padding: 0px 2px !important;
            font-weight: 500 !important;
            display: inline !important;
            box-shadow: none !important;
            text-shadow: none !important;
            border: none !important;
            outline: none !important;
            background-image: radial-gradient(ellipse at center, rgba(239, 68, 68, 0.9) 10%, rgba(239, 68, 68, 0.6) 40%, rgba(239, 68, 68, 0.2) 80%, rgba(239, 68, 68, 0.05) 100%) !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            -webkit-mask-image: radial-gradient(ellipse at center, black 10%, black 40%, transparent 80%, transparent 100%) !important;
            mask-image: radial-gradient(ellipse at center, black 10%, black 40%, transparent 80%, transparent 100%) !important;
            transition: all 0.3s ease;
            color: inherit !important;
            background-color: transparent !important;
        }
        
        /* ===== هایلایت فسفری (سال دوم یا کوچک‌ترین در ۲ سال) ===== */
        .year-highlight.year-green {
            padding: 0px 2px !important;
            font-weight: 500 !important;
            display: inline !important;
            box-shadow: none !important;
            text-shadow: none !important;
            border: none !important;
            outline: none !important;
            background-image: radial-gradient(ellipse at center, rgba(0, 255, 136, 0.9) 10%, rgba(0, 255, 136, 0.6) 40%, rgba(0, 255, 136, 0.2) 80%, rgba(0, 255, 136, 0.05) 100%) !important;
            background-size: 100% 100% !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            -webkit-mask-image: radial-gradient(ellipse at center, black 10%, black 40%, transparent 80%, transparent 100%) !important;
            mask-image: radial-gradient(ellipse at center, black 10%, black 40%, transparent 80%, transparent 100%) !important;
            transition: all 0.3s ease;
            color: inherit !important;
            background-color: transparent !important;
        }
        
        /* ===== برای پرینت ===== */
        @media print {
            .year-highlight.year-pink {
                background-image: radial-gradient(ellipse at center, rgba(239, 68, 68, 0.6) 10%, rgba(239, 68, 68, 0.3) 40%, rgba(239, 68, 68, 0.1) 80%, rgba(239, 68, 68, 0) 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color: inherit !important;
            }
            
            .year-highlight.year-green {
                background-image: radial-gradient(ellipse at center, rgba(0, 255, 136, 0.5) 10%, rgba(0, 255, 136, 0.3) 40%, rgba(0, 255, 136, 0.1) 80%, rgba(0, 255, 136, 0) 100%) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color: inherit !important;
            }
        }
		
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
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="reports" onclick="showLeftContent('reports')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-chart-pie"></i></span>
                            <span class="menu-label">گزارش برهان</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item active" data-section="stats" onclick="showLeftContent('stats')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-chart-line"></i></span>
                            <span class="menu-label">آمار کاربران تایید</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="users" onclick="showLeftContent('users')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-users"></i></span>
                            <span class="menu-label">مدیریت کاربران</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="companies" onclick="showLeftContent('companies')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-building"></i></span>
                            <span class="menu-label">مدیریت شرکت‌ها</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="filters" onclick="showLeftContent('filters')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-filter"></i></span>
                            <span class="menu-label">جستجوی اسناد</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="approvals" onclick="showLeftContent('approvals')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-check-double"></i></span>
                            <span class="menu-label">تاییدات نهایی</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" data-section="archive" onclick="showLeftContent('archive')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-archive"></i></span>
                            <span class="menu-label">بایگانی</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" onclick="window.open('export_excel.php', '_blank')">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-file-excel"></i></span>
                            <span class="menu-label">خروجی اکسل</span>
                        </div>
                    </div>
                    <div class="menu-item-wrapper">
                        <div class="menu-item" onclick="showDbManagerModal()">
                            <span class="menu-arrow"><i class="fas fa-chevron-left"></i></span>
                            <span class="menu-icon"><i class="fas fa-database"></i></span>
                            <span class="menu-label">مدیریت دیتابیس</span>
                        </div>
                    </div>
                </div>
                                
                <div id="adminFiltersPanel" class="admin-filter-box">
                    <div class="admin-filter-group"><label><i class="fas fa-user"></i> انتخاب کاربر</label><select id="admin_user_select"><option value="">همه کاربران</option><?php foreach($users_list as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-filter-group"><label><i class="fas fa-hashtag"></i> شماره سند ثابت</label><input type="text" id="admin_filter_number" placeholder="جستجو..."></div>
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
                        <div class="form-group"><label>شماره ثابت</label><input type="text" id="doc_number" placeholder="INV-12345"></div>
                        <div class="form-group" id="date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>><label>تاریخ سند</label><input type="text" id="doc_date" placeholder="1405/02/30"></div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button class="btn-submit" id="submitBtn" onclick="saveDocument()" style="flex: 1; margin-top: 0;">✓ ثبت سند</button>
                            <button class="btn-load" id="loadDocsBtn" onclick="openLoadDocumentsModal()" style="flex: 1; margin-top: 0;">
                                <i class="fas fa-file-import"></i> بارگذاری اسناد تایید
                            </button>
                        </div>
                    </div>
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 0;">
                        <div class="form-group"><label>گزارش / یادداشت</label><textarea id="doc_description" rows="3" placeholder="هرگونه توضیح یا گزارش اضافی..."></textarea></div>
                        <button class="btn-green" id="submitDescriptionBtn" onclick="addDescriptionToDocument()">✏️ ثبت توضیح</button>
                    </div>
                    
                    <!-- فیلدهای جستجو بعد از دکمه ثبت توضیح -->
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 12px;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 12px; padding-right: 4px;"><i class="fas fa-search"></i> جستجوی اسناد</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div class="form-group" style="margin-bottom: 0;"><label>شماره سند ثابت</label><input type="text" id="filter_number" placeholder="شماره سند ثابت..." oninput="autoSearchDocuments()"></div>
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
							<img src="/document_system/assets/logo.png" alt="لوگو" style="max-height: 40px; width: auto; transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); cursor: pointer;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'" onerror="this.style.display='none'">
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
                <div id="approvalsContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-check-double"></i> تایید نهایی اسناد</div><div id="pendingApprovalsTab"><div id="usersPendingList"><div class="empty-state">در حال بارگذاری...</div></div><div id="userDatesContainer" style="display:none; margin-top: 20px;"><div class="left-section-title"><i class="fas fa-calendar"></i> تاریخ‌های تحویل<button class="btn-secondary" onclick="backToUsersList()" style="margin-right:auto; padding:4px 12px;">← بازگشت</button></div><div id="userDatesList"></div></div></div></div>                <div id="archiveContent" class="left-content" style="display:none;"><div class="left-section-title"><i class="fas fa-archive"></i> بایگانی اسناد تایید شده</div><div id="archiveList"><div class="empty-state">در حال بارگذاری...</div></div></div>
                
                <!-- ========== گزارش برهان ========== -->
                <div id="reportsContent" class="left-content" style="display:none;">
                    <!-- ===== انیمیشن لودینگ جدید ===== -->
                    <div id="reportLoading" class="report-loading" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 50px 20px;">
                        <div class="loading-text">
                            در حال بارگذاری گزارش
                            <span class="dot">.</span>
                            <span class="dot" style="animation-delay: 0.3s;">.</span>
                            <span class="dot" style="animation-delay: 0.6s;">.</span>
                        </div>
                        <div class="loading-bar-container">
                            <div class="loading-bar" id="loadingBar">
                                <div class="loading-bar-shine"></div>
                            </div>
                        </div>
                        <div class="loading-percent" id="loadingPercent">۰%</div>
                        <div class="loading-steps" id="loadingSteps">
                            <span class="step active" data-step="1">📁 خواندن فایل</span>
                            <span class="step" data-step="2">📊 پردازش داده</span>
                            <span class="step" data-step="3">📋 ایجاد گزارش</span>
                        </div>
                    </div>
                    <!-- ===== پایان انیمیشن لودینگ ===== -->
                    
                    <!-- ===== محتوای گزارش ===== -->
                    <div id="reportContentWrapper" style="display: none;">
                        <div class="left-section-title">
                            <div style="display:flex; align-items:center; gap:8px; flex:1;">
                                <i class="fas fa-chart-pie"></i> گزارش برهان
                                <span id="reportNewBadge" style="display:none; font-size:0.6rem; background:#ef4444; color:white; padding:2px 10px; border-radius:20px; margin-right:8px;"></span>
                            </div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span id="reportUpdateTime" style="font-size:0.55rem; color:#94a3b8; background:#f1f5f9; padding:2px 10px; border-radius:20px;">
                                    <i class="fas fa-clock" style="margin-left:4px; color:#667eea;"></i> در حال بارگذاری...
                                </span>
                                <button class="btn-secondary" onclick="resetReportFilters()" style="padding:4px 12px; font-size:0.65rem;">
                                    <i class="fas fa-undo"></i> حذف فیلتر
                                </button>
                                <button class="btn-primary" onclick="manualRefreshReport()" style="padding:4px 12px; font-size:0.65rem;">
                                    <i class="fas fa-sync"></i> بروزرسانی
                                </button>
                            </div>
                        </div>
                    
                    <!-- ========== آمار لحظه‌ای ========== -->
                    <div class="report-stats-container">
                        <div class="report-stats-header">
                            <div class="report-stats-title">
                                <i class="fas fa-chart-bar"></i> آمار لحظه‌ای
                            </div>
                            <div class="report-stats-date">
                                <span id="reportDateRangeInfo" style="font-size:0.55rem; color:#94a3b8; background:#f1f5f9; padding:3px 10px; border-radius:20px; white-space:nowrap;"></span>
                                <button class="date-quick-btn" onclick="setReportDate('prev')">تاریخ قبلی</button>
                                <button class="date-quick-btn" onclick="setReportDate('next')">تاریخ بعدی</button>
                                <input type="text" id="report_stats_date" placeholder="1404/01/01" value="" onchange="loadReportStats()">
                            </div>
                        </div>
                        <div class="report-stats-grid" id="reportStatsGrid">
                            <div class="stat-card-new stat-new">
                                <div class="stat-icon"><i class="fas fa-plus-circle"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">ثبت جدید</div>
                                    <div class="stat-number" id="statNewCount">0</div>
                                </div>
                            </div>
                            <div class="stat-card-new stat-edit">
                                <div class="stat-icon"><i class="fas fa-edit"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">ویرایش شده</div>
                                    <div class="stat-number" id="statEditCount">0</div>
                                </div>
                            </div>
                            <div class="stat-card-new stat-delete">
                                <div class="stat-icon"><i class="fas fa-trash-alt"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">حذف شده</div>
                                    <div class="stat-number" id="statDeleteCount">0</div>
                                </div>
                            </div>
                            <div class="stat-card-new stat-total">
                                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">کل ثبت‌ها</div>
                                    <div class="stat-number" id="statTotalCount">0</div>
                                </div>
                            </div>
                            <!-- باکس ورود موفق -->
                            <div class="stat-card-new stat-login-success">
                                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">ورود موفق</div>
                                    <div class="stat-number" id="statLoginSuccessCount">0</div>
                                </div>
                            </div>
                            <!-- باکس ورود ناموفق -->
                            <div class="stat-card-new stat-login-fail">
                                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                                <div class="stat-info">
                                    <div class="stat-label">ورود ناموفق</div>
                                    <div class="stat-number" id="statLoginFailCount">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ========== پایان آمار لحظه‌ای ========== -->
                    
                    <!-- ========== گزارش ثبت/تایید اسناد (حسابداری) ========== -->
                    <div class="report-warehouse-container gradient-blob-card" id="reportWarehouseContainer" style="display: none;">
                        <div class="blob-bg pink"></div>
                        <div class="card-content" style="padding:12px 16px;">
                            <div class="report-warehouse-header">
                                <div class="report-warehouse-title">
                                    <i class="fas fa-file-invoice"></i>
                                    <span id="reportWarehouseTitle">گزارش ثبت سند</span>
                                    <span class="report-warehouse-date" id="reportWarehouseDate"></span>
                                </div>
                                <div class="report-warehouse-header-actions" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <button id="toggleReportTypeBtn" class="toggle-report-btn register-mode" onclick="toggleReportType()">
                                        <i class="fas fa-check-circle"></i> 
                                        <span id="reportToggleBtnText">گزارش تایید اسناد</span>
                                    </button>
                                    <div class="report-warehouse-total" id="reportWarehouseTotal">
                                        مجموع: 0 سند
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ===== لودینگ ضربان‌دار برای کارت‌ها ===== -->
                            <div id="warehouseCardLoading" class="warehouse-card-loading" style="display: none; text-align: center; padding: 30px 20px;">
                                <div class="bounce-loader">
                                    <div class="bounce-dot"></div>
                                    <div class="bounce-dot"></div>
                                    <div class="bounce-dot"></div>
                                </div>
                                <p style="margin-top: 15px; font-size: 0.75rem; color: #94a3b8; font-weight: 500;">
                                    <span id="warehouseLoadingText">در حال بارگذاری گزارش ثبت اسناد...</span>
                                </p>
                            </div>
                            <!-- ===== پایان لودینگ ضربان‌دار ===== -->
                            
                            <!-- پیام خالی -->
                            <div id="reportWarehouseEmpty" style="display: none; text-align: center; padding: 30px 20px; color: #94a3b8;">
                                <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                <p style="font-size: 0.9rem; font-weight: 500;">این تاریخ گزارشی ندارد</p>
                                <p style="font-size: 0.7rem; margin-top: 5px;">هیچ سند حسابداری با موجودیت جدید یافت نشد</p>
                            </div>
                            <div class="report-warehouse-grid" id="reportWarehouseGrid">
                                <!-- کارت‌ها به صورت داینامیک ساخته می‌شوند -->
                            </div>
                        </div>
                    </div>
                    <!-- ========== پایان گزارش ثبت/تایید اسناد ========== -->
                    
                    <!-- فیلترها -->
                    <div class="reports-filters">
                        <div class="filter-group filter-company">
                            <label><i class="fas fa-building"></i> شرکت</label>
                            <select id="report_filter_company" onchange="applyReportFilters()">
                                <option value="">همه شرکت‌ها</option>
                            </select>
                        </div>
                        <div class="filter-group filter-year">
                            <label><i class="fas fa-calendar-alt"></i> سال مالی</label>
                            <select id="report_filter_year" onchange="applyReportFilters()">
                                <option value="">همه سال‌ها</option>
                            </select>
                        </div>
                        <div class="filter-group filter-user">
                            <label><i class="fas fa-user"></i> کاربر</label>
                            <select id="report_filter_user" onchange="applyReportFilters()">
                                <option value="">همه کاربران</option>
                            </select>
                        </div>
                        <div class="filter-group filter-number">
                            <label><i class="fas fa-hashtag"></i> شماره سند ثابت</label>
                            <input type="text" id="report_filter_number" placeholder="1234" oninput="applyReportFilters()">
                        </div>
                        <div class="filter-group filter-type">
                            <label><i class="fas fa-tag"></i> نوع ثبت</label>
                            <select id="report_filter_type" onchange="applyReportFilters()">
                                <option value="">همه</option>
                            </select>
                        </div>
                        <div class="filter-group filter-date">
                            <label><i class="fas fa-calendar-day"></i> از تاریخ</label>
                            <input type="text" id="report_filter_date_from" placeholder="1404/01/01" oninput="formatDateInput(this); applyReportFilters();">
                        </div>
                        <div class="filter-group filter-date">
                            <label><i class="fas fa-calendar-day"></i> تا تاریخ</label>
                            <input type="text" id="report_filter_date_to" placeholder="1404/01/01" oninput="formatDateInput(this); applyReportFilters();">
                        </div>
                        <div class="filter-group filter-doctype">
                            <label><i class="fas fa-file-alt"></i> نوع سند</label>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
                                <label style="font-size:0.6rem; display:flex; align-items:center; gap:4px; cursor:pointer;">
                                    <input type="checkbox" class="doc-type-filter" value="سند حسابداري" checked onchange="applyReportFilters()"> حسابداري
                                </label>
                                <label style="font-size:0.6rem; display:flex; align-items:center; gap:4px; cursor:pointer;">
                                    <input type="checkbox" class="doc-type-filter" value="سند انبار" onchange="applyReportFilters()"> انبار
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- جدول نتایج -->
                    <div id="reportTableContainer" style="margin-top: 15px; display: none;">
                        <div style="overflow-x: auto; max-height: 450px; overflow-y: auto; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <table class="data-table" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>شعبه</th>
                                        <th>سال مالی</th>
                                        <th>کاربر</th>
                                        <th>تاریخ</th>
                                        <th>زمان</th>
                                        <th>نوع ثبت</th>
                                        <th>سیستم</th>
                                        <th>نوع سند</th>
                                        <th>شماره</th>
                                        <th>تاریخ سند</th>
                                        <th>توضیحات</th>
                                    </tr>
                                </thead>
                                <tbody id="reportTableBody">
                                </tbody>
                            </table>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-top: 12px; padding: 8px 12px; background: #f8fafc; border-radius: 10px;">
                            <span style="font-size: 0.7rem; color: #64748b;">
                                <i class="fas fa-file-alt"></i> تعداد کل: <strong id="reportTotalCount">0</strong>
                            </span>
                            <span style="font-size: 0.7rem; color: #64748b;">
                                <i class="fas fa-filter"></i> فیلتر شده: <strong id="reportFilteredCount">0</strong>
                            </span>
                        </div>
                    </div>
                    
                    <div id="reportEmptyState" class="empty-state" style="margin-top: 20px;">
                        <i class="fas fa-file-csv" style="font-size: 2rem; opacity: 0.5;"></i>
                        <p style="margin-top: 10px;">برای مشاهده گزارش، فایل CSV را در مسیر storage/reports/Logs.csv قرار دهید</p>
                    </div>
                </div>
                <!-- ========== پایان گزارش برهان ========== -->
                
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
<div id="editModal" class="modal"><div class="modal-content"><h3>ویرایش سند</h3><input type="hidden" id="edit_id"><div class="form-group"><label>شرکت</label><select id="edit_company_id" style="width:100%;"><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>شماره سند ثابت</label><input type="text" id="edit_number" style="width:100%;"></div><div class="form-group" id="edit_date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>><label>تاریخ سند</label><input type="text" id="edit_date" style="width:100%;" placeholder="1405/02/30"></div><div class="modal-buttons"><button class="btn-primary" onclick="saveEdit()">ذخیره</button><button class="btn-secondary" onclick="closeEditModal()">انصراف</button></div></div></div>
<div id="userModal" class="modal"><div class="modal-content"><h3 id="userModalTitle">افزودن کاربر جدید</h3><input type="hidden" id="edit_user_id"><div class="form-group"><label>نام کاربری</label><input type="text" id="user_username" style="width:100%;"></div><div class="form-group"><label>نام کامل</label><input type="text" id="user_fullname" style="width:100%;"></div><div class="form-group"><label>واحد</label><input type="text" id="user_unit" style="width:100%;"></div><div class="form-group"><label>رمز عبور</label><input type="password" id="user_password" placeholder="برای ویرایش خالی بگذارید" style="width:100%;"></div><div class="form-group"><label><input type="checkbox" id="user_require_date" checked> تاریخ سند اجباری باشد</label></div>
<div class="form-group"><label><input type="checkbox" id="user_can_view_unit_stats"> دسترسی به آمار کاربران واحد خود</label><small style="font-size:0.55rem; color:#94a3b8; display:block; margin-top:4px;">با فعال‌سازی این گزینه، کاربر می‌تواند آمار کاربران هم‌واحد خود را مشاهده کند.</small></div>
<div class="modal-buttons"><button class="btn-primary" onclick="saveUser()">ذخیره</button><button class="btn-secondary" onclick="closeUserModal()">انصراف</button></div></div></div>
<div id="companyModal" class="modal"><div class="modal-content"><h3 id="companyModalTitle">افزودن شرکت جدید</h3><input type="hidden" id="edit_company_id"><div class="form-group"><label>نام شرکت</label><input type="text" id="company_name" style="width:100%;"></div><div class="modal-buttons"><button class="btn-primary" onclick="saveCompany()">ذخیره</button><button class="btn-secondary" onclick="closeCompanyModal()">انصراف</button></div></div></div>

<!-- مودال بارگذاری اسناد تایید -->
<div id="loadDocsModal" class="modal">
    <div class="modal-content">
        <h3>
            <i class="fas fa-file-import"></i> بارگذاری اسناد تایید شده
            <button class="help-btn" onclick="toggleHelp()" title="راهنما" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #667eea; margin-right: 10px;">
                <i class="fas fa-question-circle"></i>
            </button>
        </h3>
        
        <!-- ===== بخش راهنما ===== -->
        <div id="helpContent" style="display: none; background: #f0f4ff; padding: 12px 16px; border-radius: 10px; margin-bottom: 15px; border-right: 3px solid #667eea;">
            <p style="font-size: 0.75rem; color: #1e293b; line-height: 1.8; margin: 0;">
                شما میتوانید اسناد تایید شده خود را به‌صورت یکجا از برهان بارگذاری کنید
                <br><br>
                ✅ <strong>نکات مهم:</strong>
                <br>
                • فیزیک اسناد باید به ترتیب لیست اسناد تایید شده شما مرتب باشد.
                <br>
                • در صورت نیاز، می‌توانید هر سند را پس از کلیک دکمه "تایید و ذخیره" از لیست حذف کنید.
            </p>
        </div>
        <!-- ===== پایان راهنما ===== -->
        
        <div class="form-group">
            <label>نام کاربر (می‌توانید چند کاربر انتخاب کنید)</label>
            <select id="load_user_name" multiple style="width:100%; height: 120px; padding: 8px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: white;">
                <!-- گزینه‌ها با JavaScript پر می‌شوند -->
            </select>
            <small style="font-size: 0.6rem; color: #94a3b8; display: block; margin-top: 4px;">
                <i class="fas fa-info-circle"></i> برای انتخاب چند کاربر، کلید Ctrl  را نگه دارید و کلیک کنید.
            </small>
        </div>
        <div style="display: flex; gap: 10px;">
            <div class="form-group" style="flex: 1;">
                <label>از تاریخ</label>
                <input type="text" id="load_date_from" placeholder="1404/01/01" style="width:100%;">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>تا تاریخ</label>
                <input type="text" id="load_date_to" placeholder="1404/01/01" style="width:100%;">
            </div>
        </div>
        <div class="modal-buttons">
            <button class="btn-primary" id="loadDocsSubmitBtn" onclick="loadConfirmedDocuments()">
                <i class="fas fa-file-import"></i> بارگذاری
            </button>
            <button class="btn-secondary" onclick="closeLoadDocsModal()">انصراف</button>
        </div>
        <div id="loadDocsStatus" style="margin-top: 10px; font-size: 0.7rem; color: #64748b; text-align: center; display: none;"></div>
    </div>
</div>

<!-- ===== مودال مدیریت دیتابیس ===== -->
<div id="dbManagerModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-database"></i> مدیریت دیتابیس</h3>
            <button class="modal-close" onclick="closeDbManagerModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php include 'assets/partials/db_manager_content.php'; ?>
        </div>
    </div>
</div>

<!-- ===== مودال رمز مدیریت دیتابیس ===== -->
<div id="dbPasswordModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:400px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 20px; border-radius: 16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1rem;"><i class="fas fa-lock"></i> ورود به مدیریت دیتابیس</h3>
            <button onclick="closeDbPasswordModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1.2rem;">&times;</button>
        </div>
        <div style="padding:20px;">
            <p style="font-size:0.8rem; color:#64748b; margin-bottom:15px;">برای دسترسی به مدیریت دیتابیس، رمز عبور را وارد کنید:</p>
            <div class="form-group">
                <input type="password" id="dbPasswordInput" placeholder="رمز عبور را وارد کنید" style="width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.85rem;" onkeypress="if(event.key==='Enter') confirmDbPassword();">
            </div>
            <div id="dbPasswordError" style="font-size:0.7rem; min-height:20px; margin-bottom:10px;"></div>
            <div style="display:flex; gap:10px;">
                <button class="btn-primary" id="dbPasswordBtn" onclick="confirmDbPassword()" style="flex:1; padding:10px; border:none; border-radius:10px; background:linear-gradient(135deg, #667eea, #764ba2); color:white; font-weight:600; cursor:pointer; font-size:0.8rem;">
                    <i class="fas fa-unlock"></i> ورود
                </button>
                <button class="btn-secondary" onclick="closeDbPasswordModal()" style="flex:0.5; padding:10px; border:none; border-radius:10px; background:#e2e8f0; color:#475569; font-weight:600; cursor:pointer; font-size:0.8rem;">
                    انصراف
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== مودال نمایش اسناد کاربر ===== -->
<div id="userDocsModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:700px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:15px 20px; border-radius:16px 16px 0 0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1rem;"><i class="fas fa-file-alt"></i> <span id="userDocsModalTitle">اسناد کاربر</span></h3>
            <button onclick="closeUserDocsModal()" style="background:rgba(255,255,255,0.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1.2rem;">&times;</button>
        </div>
        <div style="padding:20px;">
            <div id="userDocsContent" style="max-height:400px; overflow-y:auto;">
                <div class="empty-state">در حال بارگذاری...</div>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
const requireDocDate = <?php echo $require_doc_date ? 'true' : 'false'; ?>;
const apiUrl = 'api/ajax.php';
let searchTimeout;
let currentDeliveryDate = '';
let currentPanel = 'form';
let reportData = [];

// ===== پایش خودکار بروزرسانی فایل CSV گزارش برهان (بدون نیاز به رفرش دستی کاربر) =====
let reportKnownSignature = null;   // آخرین امضای شناخته‌شده فایل (mtime_filesize)
let reportWatcherInterval = null;  // شناسه‌ی setInterval پایشگر
const REPORT_WATCH_INTERVAL_MS = 15000; // هر ۱۵ ثانیه یک درخواست بسیار سبک (فقط filemtime)
let reportType = 'register';
let currentSelectedUserId = null;

// ===== متغیرهای لودینگ =====
let loadingProgress = 0;
let loadingInterval = null;

// ✅ === متغیرهای آمار تایید ===
let confirmStatsDates = [];
let confirmStatsIndex = 0;
let confirmStatsData = {};
let confirmStatsLastTimes = {};
// ===== متغیرهای آمار کاربران واحد =====
let unitStatsType = 'register';
let unitStatsDates = [];
let unitStatsIndex = 0;
let unitStatsLastTimes = {};

// ===== تبدیل حروف عربی به فارسی =====
function toPersianChars(text) {
    if (!text) return text;
    const map = {
        'ي': 'ی',
        'ك': 'ک',
        'ة': 'ه',
        'ء': ''
    };
    return text.replace(/[يكةء]/g, function(m) {
        return map[m] || m;
    });
}

// ===== تبدیل حروف فارسی به عربی =====
function toArabicChars(text) {
    if (!text) return text;
    const map = {
        'ی': 'ي',
        'ک': 'ك',
        'ه': 'ه',  // ✅ اصلاح شد
    };
    return text.replace(/[یک]/g, function(m) {
        return map[m] || m;
    });
}

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
    
    // اگر پیغام قبلی در حال نمایش است، ابتدا مخفی کن
    if (toast.style.display === 'flex') {
        toast.classList.remove('animate__fadeInDownBig', 'animate__fadeOutDown', 'animate__slow');
        toast.style.display = 'none';
        clearTimeout(toast._timeout);
    }
    
    // تنظیم محتوا و رنگ
    toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i> ${msg}`;
    toast.style.background = isError ? '#ef4444' : '#10b981';
    toast.style.display = 'flex';
    
    // حذف کلاس‌های قبلی و اضافه کردن انیمیشن ورود با سرعت آرام
    toast.classList.remove('animate__fadeOutDown', 'animate__fadeInDownBig', 'animate__slow');
    toast.classList.add('animate__animated', 'animate__fadeInDownBig', 'animate__slow');
    
    // تنظیم تایمر برای خروج
    toast._timeout = setTimeout(() => {
        toast.classList.remove('animate__fadeInDownBig', 'animate__slow');
        toast.classList.add('animate__fadeOutDown', 'animate__slow');
        setTimeout(() => {
            toast.style.display = 'none';
        }, 1000); // مدت زمان انیمیشن خروج با سرعت آرام
    }, 2000); // مدت زمان نمایش پیغام (۳.۵ ثانیه)
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

// ========== مودال بارگذاری اسناد تایید ==========
function openLoadDocumentsModal() {
    const modal = document.getElementById('loadDocsModal');
    if (!modal) return;
    
    const btn = document.getElementById('loadDocsBtn');
    const originalText = btn.innerHTML;
    
    // ✅ تغییر حالت دکمه به لودینگ
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال آماده‌سازی...';
    btn.disabled = true;
    btn.style.opacity = '0.7';
    
    // ✅ اگر reportData خالی است، بارگذاری کن
    if (!reportData || reportData.length === 0) {
        fetch('api/ajax.php?action=load_report_data')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    reportData = data.data;
                    fillLoadModal();
                } else {
                    showToast('خطا در بارگذاری داده‌ها', true);
                }
                // ✅ برگرداندن دکمه به حالت عادی
                resetLoadButton(btn, originalText);
            })
            .catch(err => {
                console.error(err);
                showToast('خطا در ارتباط با سرور', true);
                resetLoadButton(btn, originalText);
            });
    } else {
        fillLoadModal();
        resetLoadButton(btn, originalText);
    }
}

// ✅ تابع کمکی برای برگرداندن دکمه به حالت عادی
function resetLoadButton(btn, originalText) {
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        btn.style.opacity = '1';
    }, 300);
}

function closeLoadDocsModal() {
    const modal = document.getElementById('loadDocsModal');
    if (modal) modal.classList.remove('active');
    
    const status = document.getElementById('loadDocsStatus');
    if (status) {
        status.style.display = 'none';
        status.innerHTML = '';
    }
}

// ===== نمایش/مخفی راهنما =====
function toggleHelp() {
    const help = document.getElementById('helpContent');
    if (help) {
        if (help.style.display === 'none' || help.style.display === '') {
            help.style.display = 'block';
        } else {
            help.style.display = 'none';
        }
    }
}

function fillLoadModal() {
    const userSelect = document.getElementById('load_user_name');
    if (userSelect && reportData && reportData.length > 0) {
        const users = [...new Set(
            reportData
                .filter(row => {
                    const desc = row[11] || '';
                    const isAccounting = row[8] === 'سند حسابداري';
                    return isAccounting && (desc.includes('تایید') || desc.includes('تاييد'));
                })
                .map(row => {
                    const rawName = row[3] || '';
                    return rawName.replace(/[0-9]+$/, '').trim();
                })
        )].sort();
        
        // حفظ انتخاب‌های قبلی
        const selectedValues = Array.from(userSelect.selectedOptions).map(opt => opt.value);
        
        userSelect.innerHTML = '';
        users.forEach(user => {
            if (user) {
                const option = document.createElement('option');
                option.value = user;
                option.textContent = user;
                if (selectedValues.includes(user)) {
                    option.selected = true;
                }
                userSelect.appendChild(option);
            }
        });
    }
    
    // تنظیم تاریخ‌ها بر اساس اولین کاربر انتخاب شده
    const selectedUsers = Array.from(document.getElementById('load_user_name').selectedOptions).map(opt => opt.value);
    if (selectedUsers.length > 0 && reportData && reportData.length > 0) {
        const userDates = reportData
            .filter(row => {
                const cleanName = row[3].replace(/[0-9]+$/, '').trim();
                const desc = row[11] || '';
                const isAccounting = row[8] === 'سند حسابداري';
                const hasConfirm = desc.includes('تایید') || desc.includes('تاييد');
                return selectedUsers.includes(cleanName) && isAccounting && hasConfirm;
            })
            .map(row => row[4])
            .sort();
        
        const uniqueDates = [...new Set(userDates)];
        const latestDate = uniqueDates[uniqueDates.length - 1] || '';
        document.getElementById('load_date_from').value = latestDate;
        document.getElementById('load_date_to').value = latestDate;
    } else {
        const dates = [...new Set(reportData.map(row => row[4]))].sort();
        const latestDate = dates[dates.length - 1] || '';
        document.getElementById('load_date_from').value = latestDate;
        document.getElementById('load_date_to').value = latestDate;
    }
    
    document.getElementById('loadDocsModal').classList.add('active');
}

// ========== بارگذاری اسناد تایید شده از فایل اکسل ==========
function loadConfirmedDocuments() {
    const btn = document.getElementById('loadDocsSubmitBtn');
    const status = document.getElementById('loadDocsStatus');
    
    // ✅ دریافت لیست کاربران انتخاب شده
    const userSelect = document.getElementById('load_user_name');
    const selectedUsers = Array.from(userSelect.selectedOptions).map(opt => opt.value);
    
    const dateFrom = document.getElementById('load_date_from').value;
    const dateTo = document.getElementById('load_date_to').value;
    const deliveryDate = document.getElementById('delivery_date').value;
    
    if (selectedUsers.length === 0) {
        showToast('لطفاً حداقل یک کاربر را انتخاب کنید', true);
        return;
    }
    
    if (!dateFrom || !dateTo) {
        showToast('لطفاً بازه تاریخ را مشخص کنید', true);
        return;
    }
    
    if (!deliveryDate) {
        showToast('تاریخ تحویل مشخص نیست', true);
        return;
    }
    
    // ✅ تبدیل نام‌ها به عربی
    const searchNames = selectedUsers.map(name => toArabicChars(name));
    
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...';
    btn.disabled = true;
    
    status.style.display = 'block';
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال خواندن فایل اکسل...';
    
    fetch('api/ajax.php?action=load_confirmed_documents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            user_names: searchNames,
            date_from: dateFrom,
            date_to: dateTo,
            delivery_date: deliveryDate
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.documents && data.documents.length > 0) {
            status.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981;"></i> ${data.documents.length} سند تایید شده یافت شد`;
            renderLoadedDocuments(data.documents);
            setTimeout(() => {
                closeLoadDocsModal();
                showToast(`${data.documents.length} سند با موفقیت بارگذاری شد`, false);
            }, 1000);
        } else {
            status.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> هیچ سند تایید شده‌ای یافت نشد';
            showToast('هیچ سند تایید شده‌ای برای این کاربران و بازه تاریخ یافت نشد', true);
        }
    })
    .catch(err => {
        console.error(err);
        status.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #ef4444;"></i> خطا در ارتباط با سرور';
        showToast('خطا در ارتباط با سرور', true);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ========== رندر اسناد بارگذاری شده ==========
function renderLoadedDocuments(documents) {
    const container = document.getElementById('userDocumentsList');
    if (!container) return;
    
    if (!documents || documents.length === 0) {
        container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
        return;
    }
    
    // ✅ بارگذاری نگاشت شرکت‌ها از فایل JSON
    let companyMap = {};
    fetch('config/company_map.json')
        .then(res => res.json())
        .then(data => {
            companyMap = data;
            renderWithMap(documents, companyMap);
        })
        .catch(() => {
            renderWithMap(documents, {});
        });
}

function renderWithMap(documents, companyMap) {
    const container = document.getElementById('userDocumentsList');
    if (!container) return;
    
    const mappedDocs = documents.map(doc => ({
        ...doc,
        display_company: companyMap[doc.company_name] || doc.company_name || doc.company_full || 'نامشخص'
    }));
    
    window.tempLoadedDocs = mappedDocs;
    
    let html = `
        <div class="doc-group" style="border-color: #38bdf8; border-width: 2px;">
            <div class="group-title" style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom-color: #38bdf8; flex-wrap: wrap; gap: 10px;">
                <div class="group-date">
                    <i class="fas fa-file-import" style="color: #0ea5e9;"></i> 
                    اسناد تایید شده بارگذاری شده
                    <span style="background: #dbeafe; padding: 2px 8px; border-radius: 20px; margin-right: 8px; color: #1e40af;">${mappedDocs.length} سند</span>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button class="btn-success" id="confirmLoadBtn" onclick="confirmLoadedDocuments()" style="background: #10b981; color: white; border: none; padding: 5px 16px; border-radius: 8px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-check-circle"></i> تایید و ذخیره
                    </button>
                    <button class="btn-danger" onclick="cancelLoadedDocuments()" style="background: #ef4444; color: white; border: none; padding: 5px 16px; border-radius: 8px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-times-circle"></i> انصراف
                    </button>
                </div>
            </div>
            <div style="overflow-x:auto; max-height: 400px; overflow-y: auto;">
                <table class="data-table">
                    <thead><tr><th>#</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th><th>تاریخ تحویل</th></tr></thead>
                    <tbody>`;
    
    mappedDocs.forEach((doc, index) => {
        html += `<tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(doc.doc_number)}</td>
            <td>${doc.doc_date || '—'}</td>
            <td>${escapeHtml(doc.display_company)}</td>
            <td>${escapeHtml(doc.delivery_date)}</td>
        </tr>`;
    });
    
    html += `</tbody></table></div></div>`;
    container.innerHTML = html;
}

// ========== تایید و ذخیره اسناد بارگذاری شده ==========
function confirmLoadedDocuments() {
    const docs = window.tempLoadedDocs;
    if (!docs || docs.length === 0) {
        showToast('هیچ سندی برای ذخیره وجود ندارد', true);
        return;
    }
    
    const btn = document.getElementById('confirmLoadBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
    btn.disabled = true;
    
    const docsToSave = docs.map(doc => ({
        ...doc,
        company_name: doc.display_company
    }));
    
    fetch('api/ajax.php?action=save_loaded_documents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            documents: docsToSave
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(`${data.saved_count} سند با موفقیت ذخیره شد`, false);
            window.tempLoadedDocs = null;
            const deliveryDate = docs[0]?.delivery_date || '';
            if (deliveryDate && typeof loadDocumentsForDeliveryDate === 'function') {
                // ✅ حتماً true ارسال شود
                loadDocumentsForDeliveryDate(deliveryDate, true);
            }
            if (typeof loadUserStats === 'function') {
                loadUserStats();
            }
        } else {
            showToast(data.error || 'خطا در ذخیره اسناد', true);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('خطا در ارتباط با سرور', true);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// ========== انصراف از بارگذاری ==========
function cancelLoadedDocuments() {
    if (!confirm('آیا از انصراف اطمینان دارید؟ اسناد بارگذاری شده حذف خواهند شد.')) return;
    
    window.tempLoadedDocs = null;
    
    // بازگشت به حالت عادی
    const container = document.getElementById('userDocumentsList');
    if (container) {
        const currentDate = document.getElementById('delivery_date')?.value || '';
        if (currentDate && typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(currentDate);
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
        }
    }
    
    showToast('عملیات بارگذاری لغو شد', false);
}

// ========== جستجوی آنی برای کاربر عادی ==========
// ⚠️ هشدار: این تعریف از autoSearchDocuments توسط تعریف دومِ آن (پایین‌تر در فایل،
// همان که include_unit=true می‌فرستد) بازنویسی می‌شود و هرگز اجرا نمی‌شود (dead code).
// هر تغییری اینجا اعمال شود، در عمل بی‌اثر خواهد بود. منطق واقعی و فعال را
// در تعریف دوم autoSearchDocuments (نزدیک انتهای فایل) ویرایش کنید، یا این
// نسخه‌ی تکراری را کاملاً حذف کنید تا از سردرگمی جلوگیری شود.
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
                        // ===== تشخیص سال‌ها برای هایلایت =====
                        const yearCounts = {};
                        group.documents.forEach(doc => {
                            const docDate = doc.doc_date;
                            if (docDate && docDate !== '-' && docDate !== '') {
                                const year = docDate.substring(0, 4);
                                if (!yearCounts[year]) {
                                    yearCounts[year] = 0;
                                }
                                yearCounts[year]++;
                            }
                        });
                        
                        const years = Object.keys(yearCounts);
                        const hasMultipleYears = years.length > 1;
                        let minYear = null;
                        let secondMinYear = null;
                        
                        if (years.length >= 3) {
                            const sorted = years.slice().sort((a, b) => {
                                const countA = yearCounts[a];
                                const countB = yearCounts[b];
                                if (countA !== countB) return countA - countB;
                                return parseInt(a) - parseInt(b);
                            });
                            minYear = sorted[0];
                            secondMinYear = sorted[1] || null;
                        } else if (years.length === 2) {
                            const countA = yearCounts[years[0]];
                            const countB = yearCounts[years[1]];
                            if (countA < countB) minYear = years[0];
                            else if (countA > countB) minYear = years[1];
                            else minYear = Math.min(parseInt(years[0]), parseInt(years[1])).toString();
                            secondMinYear = null;
                        } else if (years.length === 1) {
                            minYear = null;
                            secondMinYear = null;
                        }
                        // ===== پایان تشخیص سال =====
                        
                        html += `<div class="doc-group">
                            <div class="group-title">
                                <span>${group.delivery_date}</span>
                                <span>${group.count} سند</span>
                                <button class="print-btn" onclick="viewArchiveDocument('${group.delivery_date}')">مشاهده</button>
                            </div>
                            <table class="data-table">
                                <thead><tr><th>#</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th></tr></thead>
                                <tbody>`;
                        
                        group.documents.forEach((doc, idx) => {
                            let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                            let docYear = doc.doc_date && doc.doc_date !== '-' ? doc.doc_date.substring(0, 4) : '';
                            
                            // ===== هایلایت عدد سال =====
                            let dateCellContent = docDate;
                            if (hasMultipleYears && minYear !== null && docYear === minYear) {
                                if (secondMinYear !== null) {
                                    dateCellContent = docDate.replace(docYear, `<span class="year-highlight year-pink">${docYear}</span>`);
                                } else {
                                    dateCellContent = docDate.replace(docYear, `<span class="year-highlight year-green">${docYear}</span>`);
                                }
                            } else if (hasMultipleYears && secondMinYear !== null && docYear === secondMinYear) {
                                dateCellContent = docDate.replace(docYear, `<span class="year-highlight year-green">${docYear}</span>`);
                            }
                            // ===== پایان هایلایت =====
                            
                            html += `<tr>
                                <td>${idx+1}</td>
                                <td>${escapeHtml(doc.doc_number)}</td>
                                <td>${dateCellContent}</td>
                                <td>${escapeHtml(doc.company_name)}</td>
                            </tr>`;
                        });
                        html += `</tbody></table>`;
                        html += `</div>`;
                    });
                    container.innerHTML = html;
                } else {
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
                            <span class="stat-title">پیشرفت</span>
                        </div>
                        <div class="stat-main">
                            <div class="stat-today">
                                <span class="stat-small-label">لیست آخر:</span>
                                <span class="stat-small-value">${data.today_count}</span>
                            </div>
                            <span class="stat-sep">|</span>
                            <div class="stat-yesterday">
                                <span class="stat-small-label">لیست قبل:</span>
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
                
                <!-- ===== باکس آمار کاربران واحد ===== -->
                <div id="unitUsersStatsContainer" class="stat-card-new" style="display: none; grid-column: span 1;">
                    <div class="stat-header">
                        <span class="stat-icon">👥</span>
                        <span class="stat-title" id="unitUsersTitle">آمار کاربران واحد</span>
                        <button onclick="toggleUnitStatsType()" id="unitStatsToggleBtn" class="btn-report-toggle register-mode">
                            <i class="fas fa-exchange-alt"></i> 
                            <span id="unitStatsBtnText">گزارش تایید</span>
                        </button>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px;">
                        <button class="date-nav-btn" id="unitStatsPrevBtn" onclick="changeUnitStatsDate(-1)" title="روز قبل" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #475569; font-size: 0.7rem; flex-shrink: 0; padding: 0; visibility: visible;">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <div style="text-align: center; flex: 0 1 auto; min-width: 120px;">
                            <div style="font-size: 0.65rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap;" id="unitStatsDateTime">--</div>
                        </div>
                        <button class="date-nav-btn" id="unitStatsNextBtn" onclick="changeUnitStatsDate(1)" title="روز بعد" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #475569; font-size: 0.7rem; flex-shrink: 0; padding: 0; visibility: visible;">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>
                    
                    <!-- ===== لودینگ ===== -->
                    <div id="unitUsersLoading" class="unit-users-loading" style="display: none; text-align: center; padding: 20px;">
                        <div class="bounce-loader" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <div class="bounce-dot" style="width: 12px; height: 12px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; animation: bounceDot 1.4s ease-in-out infinite both; box-shadow: 0 2px 6px rgba(102,126,234,0.3);"></div>
                            <div class="bounce-dot" style="width: 12px; height: 12px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; animation: bounceDot 1.4s ease-in-out infinite both; animation-delay: -0.16s; box-shadow: 0 2px 6px rgba(102,126,234,0.3);"></div>
                            <div class="bounce-dot" style="width: 12px; height: 12px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; animation: bounceDot 1.4s ease-in-out infinite both; animation-delay: 0s; box-shadow: 0 2px 6px rgba(102,126,234,0.3);"></div>
                        </div>
                        <div style="margin-top: 10px; font-size: 0.65rem; color: #94a3b8;">در حال بارگذاری...</div>
                    </div>
                    <!-- ===== پایان لودینگ ===== -->
                    
                    <div id="unitUsersGrid" class="unit-users-grid" style="display: none;">
                        <div class="empty-state">در حال بارگذاری...</div>
                    </div>
                    <div style="margin-top: 8px; font-size: 0.6rem; color: #94a3b8; text-align: center; display: none;" id="unitUsersTotal">
                        مجموع: ۰ سند
                    </div>
                </div>
                
                <!-- ===== دو باکس کنار هم ===== -->
                <div class="stats-grid-new two-cols">
                    <!-- باکس: پیشرفت اسناد تایید شده -->
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
                    
                    <!-- باکس: آمار اسناد تایید شما -->
                    <div class="stat-card-new">
                        <div class="stat-header">
                            <span class="stat-icon">✅</span>
                            <span class="stat-title">تعداد آخرین اسناد تایید شده شما در برهان</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 4px;">
                            <button class="date-nav-btn" id="confirmStatsPrevBtn" onclick="changeConfirmDate(-1)" title="روز قبل" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #475569; font-size: 0.7rem; flex-shrink: 0; padding: 0; visibility: visible;">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <div style="text-align: center; flex: 1;">
                                <div style="font-size: 0.65rem; font-weight: 600; color: #1e293b; display: flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap;" id="confirmStatsDate">--</div>
                                <div style="font-size: 1.1rem; font-weight: 800; color: #10b981;" id="confirmStatsCount">0</div>
                                <div style="font-size: 0.5rem; color: #94a3b8;">سند تایید شده</div>
                            </div>
                            <button class="date-nav-btn" id="confirmStatsNextBtn" onclick="changeConfirmDate(1)" title="روز بعد" style="width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; color: #475569; font-size: 0.7rem; flex-shrink: 0; padding: 0; visibility: visible;">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('userStatsContainer').innerHTML = html;
            
            // ✅ بارگذاری آمار تایید
            loadConfirmStats();
            
            // ✅ بارگذاری آمار کاربران واحد
            loadUnitUsersStats();
        }
    } catch(e) {
        console.error(e);
        document.getElementById('userStatsContainer').innerHTML = '<div class="empty-state">خطا در دریافت آمار</div>';
    }
}


// ========== دریافت آمار تایید ==========
function loadConfirmStats(date) {
    const dateInput = document.getElementById('delivery_date');
    const currentDate = date || (dateInput ? dateInput.value : '');
    
    if (!currentDate) return;
    
    fetch(`api/ajax.php?action=get_confirm_stats&date=${encodeURIComponent(currentDate)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                confirmStatsDates = data.dates || [];
                confirmStatsData = data.stats || {};
                confirmStatsLastTimes = data.last_times || {};
                confirmStatsIndex = confirmStatsDates.indexOf(currentDate);
                if (confirmStatsIndex === -1 && confirmStatsDates.length > 0) {
                    confirmStatsIndex = confirmStatsDates.length - 1;
                }
                updateConfirmStatsDisplay();
                updateConfirmStatsButtons();
            }
        })
        .catch(err => console.error(err));
}

// ========== به‌روزرسانی وضعیت فلش‌های باکس تایید ==========
function updateConfirmStatsButtons() {
    const prevBtn = document.getElementById('confirmStatsPrevBtn');
    const nextBtn = document.getElementById('confirmStatsNextBtn');
    
    if (!prevBtn || !nextBtn) return;
    
    if (!confirmStatsDates || confirmStatsDates.length === 0) {
        prevBtn.style.visibility = 'hidden';
        nextBtn.style.visibility = 'hidden';
        return;
    }
    
    if (confirmStatsDates.length === 1) {
        prevBtn.style.visibility = 'hidden';
        nextBtn.style.visibility = 'hidden';
        return;
    }
    
    if (confirmStatsIndex <= 0) {
        prevBtn.style.visibility = 'hidden';
    } else {
        prevBtn.style.visibility = 'visible';
    }
    
    if (confirmStatsIndex >= confirmStatsDates.length - 1) {
        nextBtn.style.visibility = 'hidden';
    } else {
        nextBtn.style.visibility = 'visible';
    }
}

// ========== به‌روزرسانی نمایش آمار تایید ==========
function updateConfirmStatsDisplay() {
    const dateSpan = document.getElementById('confirmStatsDate');
    const countSpan = document.getElementById('confirmStatsCount');
    
    if (confirmStatsIndex >= 0 && confirmStatsIndex < confirmStatsDates.length) {
        const date = confirmStatsDates[confirmStatsIndex];
        const count = confirmStatsData[date] || 0;
        
        let time = '';
        if (confirmStatsLastTimes && confirmStatsLastTimes[date]) {
            const parts = confirmStatsLastTimes[date].split(' ');
            if (parts.length >= 2) {
                time = ' | ' + parts.slice(1).join(' ');
            }
        }
        
        if (dateSpan) dateSpan.textContent = date + time;
        if (countSpan) countSpan.textContent = count;
    } else {
        if (dateSpan) dateSpan.textContent = '--';
        if (countSpan) countSpan.textContent = '0';
    }
}

// ========== تغییر تاریخ آمار تایید ==========
function changeConfirmDate(direction) {
    if (!confirmStatsDates || confirmStatsDates.length === 0) {
        return;
    }
    
    const newIndex = confirmStatsIndex + direction;
    if (newIndex >= 0 && newIndex < confirmStatsDates.length) {
        confirmStatsIndex = newIndex;
        const date = confirmStatsDates[confirmStatsIndex];
        // ✅ ارسال تاریخ جدید به تابع
        loadConfirmStats(date);
        // ✅ به‌روزرسانی نمایش
        updateConfirmStatsDisplay();
        updateConfirmStatsButtons();
    }
}

// ========== دریافت آمار کاربران واحد ==========
function loadUnitUsersStats() {
    const container = document.getElementById('unitUsersStatsContainer');
    if (!container) return;
    
    fetch('api/ajax.php?action=check_unit_stats_permission')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.can_view) {
                container.style.display = 'block';
                const dateInput = document.getElementById('delivery_date');
                const date = dateInput ? dateInput.value : '';
                // ✅ ارسال تاریخ به fetchUnitUsersStats
                fetchUnitUsersStats(date);
            } else {
                container.style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            container.style.display = 'none';
        });
}

// ========== دریافت آمار کاربران واحد از سرور ==========
function fetchUnitUsersStats(date) {
    const container = document.getElementById('unitUsersStatsContainer');
    const grid = document.getElementById('unitUsersGrid');
    const loading = document.getElementById('unitUsersLoading');
    const totalSpan = document.getElementById('unitUsersTotal');
    
    if (!container || !grid || !loading) return;
    
    container.style.display = 'block';
    
    // ✅ نمایش لودینگ، مخفی کردن گرید
    loading.style.display = 'block';
    grid.style.display = 'none';
    if (totalSpan) totalSpan.style.display = 'none';
    
    if (!date) {
        const dateInput = document.getElementById('delivery_date');
        date = dateInput ? dateInput.value : '';
    }
    
    if (!date) {
        loading.innerHTML = '<div style="font-size: 0.7rem; color: #94a3b8;">تاریخ تحویل مشخص نیست</div>';
        return;
    }
    
    fetch(`api/ajax.php?action=get_unit_users_stats&type=${unitStatsType}&date=${encodeURIComponent(date)}`)
        .then(res => res.json())
        .then(data => {
            // ✅ مخفی کردن لودینگ
            loading.style.display = 'none';
            
            if (data.success) {
                // به‌روزرسانی تاریخ‌ها
                if (data.dates && data.dates.length > 0) {
                    unitStatsDates = data.dates;
                    unitStatsLastTimes = data.last_times || {};
                    unitStatsIndex = unitStatsDates.indexOf(date);
                    if (unitStatsIndex === -1) {
                        unitStatsIndex = unitStatsDates.length - 1;
                    }
                    updateUnitStatsDisplay(unitStatsDates[unitStatsIndex]);
                }
                
                const title = document.getElementById('unitUsersTitle');
                const toggleBtn = document.getElementById('unitStatsToggleBtn');
                
                if (title) {
                    const label = unitStatsType === 'register' ? 'ثبت' : 'تایید';
                    title.textContent = `آمار ${label} کاربران ${data.unit}`;
                }
                if (toggleBtn) {
                    const nextLabel = unitStatsType === 'register' ? 'تایید' : 'ثبت';
                    toggleBtn.innerHTML = `<i class="fas fa-exchange-alt"></i> گزارش ${nextLabel}`;
                }
                
                if (data.users && data.users.length > 0) {
                    // ✅ نمایش گرید
                    grid.style.display = 'flex';
                    if (totalSpan) totalSpan.style.display = 'block';
                    
                    let total = 0;
                    let html = '';
                    const colors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4', '#84cc16'];
                    
                    data.users.forEach((user, index) => {
                        const color = colors[index % colors.length];
                        const firstChar = user.user_name.charAt(0);
                        total += user.count;
                        
                        html += `
                            <div class="unit-user-card" onclick="showUserDocuments(${user.user_id}, '${escapeHtml(user.user_name)}')" style="cursor:pointer;">
                                <div class="unit-user-avatar" style="background: ${color}">
                                    ${firstChar}
                                </div>
                                <span class="unit-user-name">${escapeHtml(user.user_name)}</span>
                                <span class="unit-user-count">${user.count}</span>
                            </div>
                        `;
                    });
                    
                    grid.innerHTML = html;
                    if (totalSpan) totalSpan.textContent = `مجموع: ${total} سند`;
                } else {
                    // ✅ نمایش پیام خالی
                    grid.style.display = 'flex';
                    if (totalSpan) totalSpan.style.display = 'block';
                    grid.innerHTML = `
                        <div class="empty-state" style="padding: 20px; width: 100%;">
                            <i class="fas fa-users" style="font-size: 1.5rem; opacity: 0.5;"></i>
                            <p style="margin-top: 10px;">هیچ کاربری در واحد شما یافت نشد</p>
                        </div>
                    `;
                    if (totalSpan) totalSpan.textContent = 'مجموع: ۰ سند';
                }
                
                updateUnitStatsButtons();
            }
        })
        .catch(err => {
            console.error(err);
            loading.style.display = 'none';
            grid.style.display = 'flex';
            grid.innerHTML = '<div class="empty-state" style="padding: 20px; width: 100%;">خطا در دریافت اطلاعات</div>';
        });
}

// ========== به‌روزرسانی نمایش تاریخ و ساعت ==========
function updateUnitStatsDisplay(date) {
    const display = document.getElementById('unitStatsDateTime');
    if (!display) return;
    
    if (date) {
        let time = '';
        if (unitStatsLastTimes && unitStatsLastTimes[date]) {
            const parts = unitStatsLastTimes[date].split(' ');
            if (parts.length >= 2) {
                time = ' | ' + parts.slice(1).join(' ');
            }
        }
        display.textContent = date + time;
    } else {
        display.textContent = '--';
    }
    
    // ✅ به‌روزرسانی وضعیت فلش‌ها
    updateUnitStatsButtons();
}

// ========== نمایش آمار کاربران واحد ==========
function displayUnitUsersStats(data) {
    const grid = document.getElementById('unitUsersGrid');
    const totalSpan = document.getElementById('unitUsersTotal');
    const title = document.getElementById('unitUsersTitle');
    const toggleBtn = document.getElementById('unitStatsToggleBtn');
    
    if (!grid) return;
    
    if (title) {
        const label = unitStatsType === 'register' ? 'ثبت' : 'تایید';
        title.textContent = `آمار ${label} کاربران ${data.unit}`;
    }
    if (toggleBtn) {
        const nextLabel = unitStatsType === 'register' ? 'تایید' : 'ثبت';
        toggleBtn.innerHTML = `<i class="fas fa-exchange-alt"></i> گزارش ${nextLabel}`;
    }
    
    if (data.users && data.users.length > 0) {
        let total = 0;
        let html = '';
        const colors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4', '#84cc16'];
        
        data.users.forEach((user, index) => {
            const color = colors[index % colors.length];
            const firstChar = user.user_name.charAt(0);
            total += user.count;
            
            html += `
                <div class="unit-user-card">
                    <div class="unit-user-avatar" style="background: ${color}">
                        ${firstChar}
                    </div>
                    <span class="unit-user-name">${escapeHtml(user.user_name)}</span>
                    <span class="unit-user-count">${user.count}</span>
                </div>
            `;
        });
        
        grid.innerHTML = html;
        if (totalSpan) totalSpan.textContent = `مجموع: ${total} سند`;
    } else {
        grid.innerHTML = `
            <div class="empty-state" style="padding: 20px;">
                <i class="fas fa-users" style="font-size: 1.5rem; opacity: 0.5;"></i>
                <p style="margin-top: 10px;">هیچ کاربری در واحد شما یافت نشد</p>
            </div>
        `;
        if (totalSpan) totalSpan.textContent = 'مجموع: ۰ سند';
    }
}

// ========== به‌روزرسانی وضعیت فلش‌ها ==========
function updateUnitStatsButtons() {
    const prevBtn = document.getElementById('unitStatsPrevBtn');
    const nextBtn = document.getElementById('unitStatsNextBtn');
    
    if (!prevBtn || !nextBtn) return;
    
    // اگر لیست تاریخ‌ها خالی است، هر دو فلش را مخفی کن
    if (!unitStatsDates || unitStatsDates.length === 0) {
        prevBtn.style.visibility = 'hidden';
        nextBtn.style.visibility = 'hidden';
        return;
    }
    
    // اگر فقط یک تاریخ وجود دارد، هر دو فلش را مخفی کن
    if (unitStatsDates.length === 1) {
        prevBtn.style.visibility = 'hidden';
        nextBtn.style.visibility = 'hidden';
        return;
    }
    
    // اولین تاریخ
    if (unitStatsIndex <= 0) {
        prevBtn.style.visibility = 'hidden';
    } else {
        prevBtn.style.visibility = 'visible';
    }
    
    // آخرین تاریخ
    if (unitStatsIndex >= unitStatsDates.length - 1) {
        nextBtn.style.visibility = 'hidden';
    } else {
        nextBtn.style.visibility = 'visible';
    }
}

// ========== تغییر تاریخ آمار کاربران واحد ==========
function changeUnitStatsDate(direction) {
    if (!unitStatsDates || unitStatsDates.length === 0) {
        return;
    }
    
    const newIndex = unitStatsIndex + direction;
    if (newIndex >= 0 && newIndex < unitStatsDates.length) {
        unitStatsIndex = newIndex;
        const date = unitStatsDates[unitStatsIndex];
        updateUnitStatsDisplay(date);
        fetchUnitUsersStats(date);
        // ✅ به‌روزرسانی وضعیت فلش‌ها
        updateUnitStatsButtons();
    }
}

// ========== تغییر نوع گزارش (ثبت/تایید) ==========
function toggleUnitStatsType() {
    // تغییر نوع گزارش
    unitStatsType = (unitStatsType === 'register') ? 'confirm' : 'register';
    
    // دریافت دکمه و متن آن با بررسی وجود
    const btn = document.getElementById('unitStatsToggleBtn');
    const btnText = document.getElementById('unitStatsBtnText');
    
    // اگر دکمه وجود نداشت، خارج شو
    if (!btn) {
        console.error('دکمه unitStatsToggleBtn یافت نشد');
        return;
    }
    
    // تغییر کلاس و متن دکمه
    if (unitStatsType === 'register') {
        btn.className = 'btn-report-toggle register-mode';
        if (btnText) btnText.textContent = 'گزارش تایید';
    } else {
        btn.className = 'btn-report-toggle confirm-mode';
        if (btnText) btnText.textContent = 'گزارش ثبت';
    }
    
    // بارگذاری مجدد آمار
    const dateInput = document.getElementById('delivery_date');
    const date = dateInput ? dateInput.value : '';
    
    if (typeof fetchUnitUsersStats === 'function') {
        if (date) {
            fetchUnitUsersStats(date);
        } else {
            fetchUnitUsersStats();
        }
    }
}

// ========== به‌روزرسانی آمار کاربران واحد هنگام تغییر تاریخ ==========
function refreshUnitUsersStats() {
    const container = document.getElementById('unitUsersStatsContainer');
    if (container && container.style.display !== 'none') {
        const dateInput = document.getElementById('delivery_date');
        const date = dateInput ? dateInput.value : '';
        if (date) {
            fetchUnitUsersStats(date);
        } else {
            fetchUnitUsersStats();
        }
    }
}

function saveDocument() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn.disabled) return;
    
    // جلوگیری از ارسال مکرر
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
            
            document.getElementById('doc_number').focus();
            
            const currentDate = document.getElementById('delivery_date').value;
            
            // ✅ اصلاح: ارسال true برای اسکرول به آخرین سند
            loadDocumentsForDeliveryDate(currentDate, false, true);
            
            if (typeof loadUserStats === 'function') {
                loadUserStats();
            }
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✓ ثبت سند';
            
        } else if (data.duplicate) {
            // سند تکراری - چشمک زدن
            submitBtn.classList.add('blink-btn');
            submitBtn.innerHTML = '⚠️ سند تکراری!';
            showToast(data.error || 'این سند قبلاً ثبت شده است', true);
            
            setTimeout(() => {
                submitBtn.classList.remove('blink-btn');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✓ ثبت سند';
            }, 3000);
        } else {
            showToast(data.error || 'خطا در ثبت سند', true);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✓ ثبت سند';
        }
    })
    .catch(err => {
        console.error('خطا در ثبت سند:', err);
        showToast('خطا در ارتباط با سرور', true);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '✓ ثبت سند';
    });
}

// ✅ جلوگیری از ارسال فرم با Shift+Enter
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.shiftKey) {
        e.preventDefault();
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn && !submitBtn.disabled) {
            saveDocument();
        }
    }
});

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
            const currentDate = document.getElementById('delivery_date')?.value || '';
            if (currentDate) {
                loadDocumentsForDeliveryDate(currentDate, false);
            } else {
                autoSearchDocuments();
            }
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
            const currentDate = document.getElementById('delivery_date')?.value || '';
            if (currentDate) {
                loadDocumentsForDeliveryDate(currentDate, false); // ✅ اینجا false است
            } else {
                autoSearchDocuments();
            }
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
    
    // ✅ به‌روزرسانی آمار کاربران واحد
    refreshUnitUsersStats();
}

function loadDocumentsForDeliveryDate(deliveryDate, isLoaded = false, scrollToLast = false) {
    
    const container = document.getElementById('userDocumentsList');
    if (!container) {
        return;
    }
    
    if (!deliveryDate || deliveryDate === '') {
        container.innerHTML = '<div class="empty-state">تاریخ تحویل مشخص نشده است</div>';
        return;
    }
    
    fetch(`api/ajax.php?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.documents && data.documents.length > 0) {
                // ===== تشخیص سال‌ها برای هایلایت =====
                const yearCounts = {};
                data.documents.forEach(doc => {
                    const docDate = doc.doc_date;
                    if (docDate && docDate !== '-' && docDate !== '') {
                        const year = docDate.substring(0, 4);
                        if (!yearCounts[year]) {
                            yearCounts[year] = 0;
                        }
                        yearCounts[year]++;
                    }
                });
                
                const years = Object.keys(yearCounts);
                const hasMultipleYears = years.length > 1;
                let pinkYear = null;
                let greenYear = null;
                
                // مرتب‌سازی سال‌ها بر اساس عدد
                const sortedYears = years.slice().sort((a, b) => parseInt(a) - parseInt(b));
                
                // ===== تعیین قرمز (کوچک‌ترین عددی در ۳ سال یا بیشتر) =====
                if (years.length >= 3) {
                    pinkYear = sortedYears[0];
                }
                
                // ===== تعیین فسفری (کمترین تعداد) =====
                if (years.length >= 2) {
                    let minCount = Infinity;
                    let minCountYear = null;
                    for (const [year, count] of Object.entries(yearCounts)) {
                        if (count < minCount) {
                            minCount = count;
                            minCountYear = year;
                        } else if (count === minCount) {
                            if (parseInt(year) < parseInt(minCountYear)) {
                                minCountYear = year;
                            }
                        }
                    }
                    greenYear = minCountYear;
                    
                    // اگر greenYear با pinkYear یکی بود، سال بعدی را انتخاب کن
                    if (greenYear === pinkYear) {
                        const remainingYears = years.filter(y => y !== pinkYear);
                        if (remainingYears.length > 0) {
                            let minCountRemaining = Infinity;
                            let minCountYearRemaining = null;
                            for (const year of remainingYears) {
                                const count = yearCounts[year];
                                if (count < minCountRemaining) {
                                    minCountRemaining = count;
                                    minCountYearRemaining = year;
                                } else if (count === minCountRemaining) {
                                    if (parseInt(year) < parseInt(minCountYearRemaining)) {
                                        minCountYearRemaining = year;
                                    }
                                }
                            }
                            greenYear = minCountYearRemaining;
                        }
                    }
                }
                // ===== پایان تشخیص سال =====
                
                let html = `<div class="doc-group">
                            <div class="group-title">
                                <div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(deliveryDate)} <span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${data.documents.length} سند</span></div>
                                <div style="display:flex; gap:8px;">
                                    <a href="print.php?delivery_date=${encodeURIComponent(deliveryDate)}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a>
                                </div>
                            </div>
                            <div style="overflow-x:auto; max-height: 400px; overflow-y: auto;" id="documentsTableWrapper">
                                <table class="data-table">
                                    <thead><tr><th>#</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead>
                                    <tbody>`;
                
                for (let i = 0; i < data.documents.length; i++) {
                    let doc = data.documents[i];
                    let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                    let docYear = doc.doc_date && doc.doc_date !== '-' ? doc.doc_date.substring(0, 4) : '';
                    
                    // ===== هایلایت عدد سال =====
                    let dateCellContent = docDate;
                    if (pinkYear !== null && docYear === pinkYear) {
                        dateCellContent = docDate.replace(docYear, `<span class="year-highlight year-pink">${docYear}</span>`);
                    } else if (greenYear !== null && docYear === greenYear && docYear !== pinkYear) {
                        dateCellContent = docDate.replace(docYear, `<span class="year-highlight year-green">${docYear}</span>`);
                    }
                    // ===== پایان هایلایت =====
                    
                    let actions = '';
                    if (doc.can_edit && !data.has_admin_approval) {
                        actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button>
                                   <button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                    }
                    html += `<tr>
                        <td>${i+1}</td>
                        <td>${escapeHtml(doc.doc_number)}</td>
                        <td>${dateCellContent}</td>
                        <td>${escapeHtml(doc.company_name)}</td>
                        <td>${actions}</td>
                    </tr>`;
                }
                html += `</tbody></table></div>`;
                
                // ===== باکس توضیحی هایلایت =====
                if ((pinkYear !== null || greenYear !== null) && hasMultipleYears) {
                    let highlightText = '';
                    if (pinkYear !== null && greenYear !== null) {
                        highlightText = `سال <strong>${pinkYear}</strong> (قرمز) کوچک‌ترین سال عددی است و سال <strong>${greenYear}</strong> (فسفری) کمترین تعداد سند را دارد.`;
                    } else if (greenYear !== null) {
                        highlightText = `سال <strong>${greenYear}</strong> کمترین تعداد سند را دارد و به رنگ فسفری مشخص شده است.`;
                    }
                    html += `
                        <div class="year-info-box" style="background: #fef3c7; padding: 8px 14px; border-radius: 8px; margin-top: 10px; border-right: 4px solid #f59e0b; display:flex; align-items:center; gap:10px;">
                            <i class="fas fa-info-circle" style="color: #f59e0b; font-size:0.9rem;"></i>
                            <span style="font-size: 0.7rem; font-weight: 500; color: #92400e;">
                                ${highlightText}
                            </span>
                        </div>
                    `;
                }
                
                // بخش توضیحات اسناد (بدون تغییر)
                if (!isLoaded) {
                    let descriptions = data.documents.filter(d => d.description && d.description.trim() !== '');
                    if (descriptions.length > 0) {
                        let cols = 4;
                        let maxLength = 0;
                        for (let desc of descriptions) {
                            if (desc.description.length > maxLength) {
                                maxLength = desc.description.length;
                            }
                        }
                        if (maxLength > 80) cols = 2;
                        else if (maxLength > 50) cols = 3;
                        
                        html += `
                            <div class="descriptions-section">
                                <div class="desc-title">
                                    <i class="fas fa-comment-dots"></i> توضیحات اسناد
                                    <span style="font-size:0.55rem; font-weight:400; color:#94a3b8; margin-right:auto;">${descriptions.length} توضیح</span>
                                </div>
                                <div class="desc-grid" style="grid-template-columns: repeat(${cols}, 1fr);">
                        `;
                        for (let desc of descriptions) {
                            const descText = escapeHtml(desc.description);
                            html += `
                                <div class="desc-item">
                                    <span class="desc-number">${escapeHtml(desc.doc_number)}</span>
                                    <span class="desc-text">
                                        ${descText}
                                    </span>
                                </div>
                            `;
                        }
                        html += `
                                </div>
                            </div>
                        `;
                    }
                }
                
                html += `</div>`;
                container.innerHTML = html;
                
                // ===== اسکرول به آخرین ردیف (با تاخیر برای رندر کامل) =====
                if (scrollToLast && data.documents.length > 0) {
                    const wrapper = document.getElementById('documentsTableWrapper');
                    if (wrapper) {
                        setTimeout(() => {
                            wrapper.scrollTop = wrapper.scrollHeight;
                            const lastRow = wrapper.querySelector('tbody tr:last-child');
                            if (lastRow) {
                                lastRow.style.backgroundColor = '#dbeafe';
                                lastRow.style.transition = 'background-color 0.5s ease';
                                setTimeout(() => {
                                    lastRow.style.backgroundColor = '';
                                }, 2000);
                            }
                        }, 200);
                    }
                }
                // ===== پایان اسکرول =====
                
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
            }
        })
        .catch(err => {
            console.error(err);
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
            loadDocumentsForDeliveryDate(currentDate, false);
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
            loadDocumentsForDeliveryDate(currentDate, false); // ✅ false
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
                    html += `<div class="doc-group"><div class="group-title"><div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(group.delivery_date)} ${archiveBadge}<span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${group.count} سند</span></div><a href="${printUrl}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a></div><div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead><tbody>`;
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
        if (data.success && data.documents && data.documents.length > 0) {
            let html = `<div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>تاریخ تحویل</th><th>شرکت</th><th>کاربر</th><th>عملیات</th></tr></thead><tbody>`;
            for (let i = 0; i < data.documents.length; i++) {
                let doc = data.documents[i];
                let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                // ✅ اضافه کردن company_id به openEditModal
                html += `<tr>
                    <td>${i+1}</td>
                    <td>${escapeHtml(doc.doc_number)}</td>
                    <td>${docDate}</td>
                    <td>${escapeHtml(doc.delivery_date)}</td>
                    <td>${escapeHtml(doc.company_name)}</td>
                    <td>${escapeHtml(doc.user_fullname)}</td>
                    <td><button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}', ${doc.company_id})"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button></td>
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
        } else {
            listContainer.innerHTML = '<div class="empty-state">هیچ تاریخی برای این کاربر وجود ندارد</div>';
            // ✅ اگر هیچ تاریخی باقی نمانده، لیست کاربران را به‌روز کن
            backToUsersList();
        }
    } catch(e) { console.error(e); }
}

function backToUsersList() {
    document.getElementById('usersPendingList').style.display = 'block';
    document.getElementById('userDatesContainer').style.display = 'none';
    
    // ✅ بارگذاری مجدد لیست کاربران (بدون کش)
    loadPendingUsersList(true);
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
                
                // ✅ اگر کاربری وجود ندارد، پیام مناسب نمایش بده
                if (!data.users || data.users.length === 0) {
                    document.getElementById('adminUsersStatsList').innerHTML = '<div class="empty-state">هیچ کاربری با سند ثبت‌شده وجود ندارد</div>';
                    return;
                }
                
                let usersHtml = '<div class="user-stats-container">';
                data.users.forEach(user => {
                    usersHtml += `
                        <div class="user-card" onclick="showUserDocuments(${user.id}, '${escapeHtml(user.fullname)}')" style="cursor:pointer;">
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

// ========== نمایش اسناد کاربر ==========
function showUserDocuments(userId, userName, deliveryDate = null) {
    console.log('🔍 showUserDocuments شروع:', { userId, userName, deliveryDate });
    
    const modal = document.getElementById('userDocsModal');
    const title = document.getElementById('userDocsModalTitle');
    const content = document.getElementById('userDocsContent');
    
    if (!modal) {
        console.error('❌ مودال یافت نشد');
        return;
    }
    
    if (title) {
        if (deliveryDate) {
            title.textContent = 'اسناد ' + (userName || 'کاربر') + ' - تاریخ تحویل: ' + deliveryDate;
        } else {
            title.textContent = 'اسناد ' + (userName || 'کاربر');
        }
    }
    
    if (content) {
        content.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    }
    
    modal.style.display = 'flex';
    modal.classList.add('active');
    
    let url = 'api/ajax.php?action=get_user_documents&user_id=' + userId + '&user_name=' + encodeURIComponent(userName);
    if (deliveryDate) {
        url += '&delivery_date=' + encodeURIComponent(deliveryDate);
    }
    
    console.log('📤 ارسال درخواست به:', url);
    
    fetch(url)
        .then(res => {
            console.log('📥 پاسخ دریافت شد، وضعیت:', res.status);
            return res.json();
        })
        .then(data => {
            console.log('📦 داده دریافت شده:', data);
            
            if (!content) return;
            
            if (data.success && data.documents && data.documents.length > 0) {
                let html = `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>شماره سند ثابت</th>
                                <th>تاریخ سند</th>
                                <th>شرکت</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                data.documents.forEach((doc, index) => {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${escapeHtml(doc.doc_number)}</td>
                            <td>${doc.doc_date || '—'}</td>
                            <td>${escapeHtml(doc.company_name)}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table>`;
                content.innerHTML = html;
                console.log('✅ اسناد نمایش داده شدند، تعداد:', data.documents.length);
            } else {
                content.innerHTML = '<div class="empty-state">هیچ سندی برای این کاربر در این تاریخ یافت نشد</div>';
                console.log('❌ سندی یافت نشد');
            }
        })
        .catch(err => {
            console.error('❌ خطا در fetch:', err);
            if (content) {
                content.innerHTML = '<div class="empty-state">خطا در دریافت اطلاعات</div>';
            }
        });
}

// ========== بستن مودال اسناد کاربر ==========
function closeUserDocsModal() {
    const modal = document.getElementById('userDocsModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
}

// ========== بستن مودال با کلیک خارج از آن ==========
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('userDocsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeUserDocsModal();
            }
        });
    }
});

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
    
    let html = '<div style="overflow-x: auto;"><table class="data-table"><thead><tr><th>#</th><th>کاربر</th><th>واحد</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th><th>تاریخ تحویل</th></tr></thead><tbody>';
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

// فرمت تاریخ شمسی
function formatDateInput(input) {
    let raw = input.value.replace(/\D/g, '');
    if (raw.length === 0) { input.value = ''; return; }
    if (raw.length > 8) raw = raw.slice(0, 8);
    if (raw.length <= 4) { input.value = raw; }
    else if (raw.length <= 6) { input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6); }
    else { input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6) + '/' + raw.slice(6, 8); }
}

// بارگذاری فیلترها از فایل CSV
function loadReportFilters() {
    showReportLoading(true);
    
    fetch('api/ajax.php?action=load_report_data')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                reportData = data.data;
                
                // پیدا کردن آخرین تاریخ موجود
                const dates = [...new Set(reportData.map(row => row[4]))].sort();
                const latestDate = dates[dates.length - 1];
                
                // ✅ نمایش بازه اولین و آخرین تاریخ موجود کنار دکمه‌های تاریخ قبلی/بعدی
                updateReportDateRangeInfo();
                
                // تنظیم فیلتر تاریخ روی آخرین تاریخ
                const dateFromInput = document.getElementById('report_filter_date_from');
                const dateToInput = document.getElementById('report_filter_date_to');
                if (dateFromInput) dateFromInput.value = latestDate;
                if (dateToInput) dateToInput.value = latestDate;
                
                // تنظیم فیلد آمار لحظه‌ای روی آخرین تاریخ
                const statsDateInput = document.getElementById('report_stats_date');
                if (statsDateInput) statsDateInput.value = latestDate;
                
                // ===== پر کردن فیلتر شرکت (حذف برش کوه) =====
                const companySelect = document.getElementById('report_filter_company');
                if (companySelect) {
                    const companies = [...new Set(reportData.map(row => row[1]))].sort();
                    const currentValue = companySelect.value;
                    companySelect.innerHTML = '<option value="">همه شرکت‌ها</option>';
                    
                    companies.forEach(company => {
                        if (company && 
                            company !== 'شركت برش كوه آريا پارت (سهامي خاص)' && 
                            company !== 'برش كوه') {
                            const option = document.createElement('option');
                            option.value = company;
                            option.textContent = company;
                            companySelect.appendChild(option);
                        }
                    });
                    
                    if ([...companySelect.options].some(opt => opt.value === currentValue)) {
                        companySelect.value = currentValue;
                    }
                }
                
                // ===== پر کردن فیلتر کاربر =====
                const userSelect = document.getElementById('report_filter_user');
                if (userSelect) {
                    const users = [...new Set(reportData.map(row => row[3]))].sort();
                    const currentValue = userSelect.value;
                    userSelect.innerHTML = '<option value="">همه کاربران</option>';
                    
                    users.forEach(user => {
                        if (user) {
                            const option = document.createElement('option');
                            option.value = user;
                            option.textContent = user;
                            userSelect.appendChild(option);
                        }
                    });
                    
                    if ([...userSelect.options].some(opt => opt.value === currentValue)) {
                        userSelect.value = currentValue;
                    }
                }
                
                // ===== پر کردن فیلتر سال مالی =====
                const yearSelect = document.getElementById('report_filter_year');
                if (yearSelect) {
                    const years = [...new Set(reportData.map(row => row[2]))].sort();
                    const currentValue = yearSelect.value;
                    yearSelect.innerHTML = '<option value="">همه سال‌ها</option>';
                    
                    years.forEach(year => {
                        if (year) {
                            const option = document.createElement('option');
                            option.value = year;
                            option.textContent = year;
                            yearSelect.appendChild(option);
                        }
                    });
                    
                    if ([...yearSelect.options].some(opt => opt.value === currentValue)) {
                        yearSelect.value = currentValue;
                    }
                }
                
                // ===== پر کردن فیلتر نوع ثبت =====
                const typeSelect = document.getElementById('report_filter_type');
                if (typeSelect) {
                    const types = [...new Set(reportData.map(row => row[6]))].sort();
                    const currentValue = typeSelect.value;
                    typeSelect.innerHTML = '<option value="">همه</option>';
                    
                    types.forEach(type => {
                        if (type) {
                            const option = document.createElement('option');
                            option.value = type;
                            option.textContent = type;
                            typeSelect.appendChild(option);
                        }
                    });
                    
                    if ([...typeSelect.options].some(opt => opt.value === currentValue)) {
                        typeSelect.value = currentValue;
                    }
                }
                
                applyReportFilters();
                
                const emptyState = document.getElementById('reportEmptyState');
                if (emptyState) emptyState.style.display = 'none';
                
                // بارگذاری آمار لحظه‌ای (این تابع loadWarehouseReport را صدا می‌زند)
                loadReportStats();
                
            } else {
                showReportLoading(false);
                const emptyState = document.getElementById('reportEmptyState');
                if (emptyState) {
                    emptyState.style.display = 'block';
                    emptyState.innerHTML = `
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; opacity: 0.5; color: #ef4444;"></i>
                        <p style="margin-top: 10px;">خطا در بارگذاری فایل</p>
                        <p style="font-size: 0.7rem; color: #94a3b8;">${data.error || 'فایل CSV یافت نشد'}</p>
                    `;
                }
            }
        })
        .catch(err => {
            console.error(err);
            showReportLoading(false);
            const emptyState = document.getElementById('reportEmptyState');
            if (emptyState) {
                emptyState.style.display = 'block';
                emptyState.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; opacity: 0.5; color: #ef4444;"></i>
                    <p style="margin-top: 10px;">خطا در ارتباط با سرور</p>
                `;
            }
        });
}


function showReportLoading(show) {
    const loading = document.getElementById('reportLoading');
    const content = document.getElementById('reportContentWrapper');
    const bar = document.getElementById('loadingBar');
    const percent = document.getElementById('loadingPercent');
    const steps = document.querySelectorAll('.step');
    
    if (show) {
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
        
        // ریست کامل
        loadingProgress = 0;
        if (bar) bar.style.width = '0%';
        if (percent) percent.textContent = '۰%';
        
        steps.forEach((step, index) => {
            step.classList.remove('active', 'done');
            if (index === 0) step.classList.add('active');
        });
        
        if (loadingInterval) clearInterval(loadingInterval);
        // شروع انیمیشن با تاخیر
        setTimeout(() => {
            loadingInterval = setInterval(() => {
                if (loadingProgress < 90) {
                    loadingProgress += Math.random() * 4 + 1;
                    if (loadingProgress > 90) loadingProgress = 90;
                    updateLoadingProgress(loadingProgress);
                }
            }, 350);
        }, 500);
        
    } else {
        if (loadingInterval) {
            clearInterval(loadingInterval);
            loadingInterval = null;
        }
        loadingProgress = 100;
        updateLoadingProgress(100);
        // ✅ تاخیر 600ms برای نمایش کامل 100% و سپس بسته شدن
        setTimeout(() => {
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        }, 600);
    }
}

function updateLoadingProgress(value) {
    const bar = document.getElementById('loadingBar');
    const percent = document.getElementById('loadingPercent');
    const steps = document.querySelectorAll('.step');
    
    if (bar) bar.style.width = value + '%';
    if (percent) percent.textContent = Math.round(value) + '%';
    
    // به‌روزرسانی مراحل
    const stepProgress = value / 100;
    const totalSteps = steps.length;
    const activeStep = Math.min(Math.floor(stepProgress * totalSteps), totalSteps - 1);
    
    steps.forEach((step, index) => {
        step.classList.remove('active', 'done');
        if (index < activeStep) {
            step.classList.add('done');
        } else if (index === activeStep) {
            step.classList.add('active');
        }
    });
}

function populateReportFilters(data) {
    const companies = [...new Set(data.map(row => row[1]).filter(Boolean))].sort();
    const years = [...new Set(data.map(row => row[2]).filter(Boolean))].sort((a, b) => b - a);
    const users = [...new Set(data.map(row => row[3]).filter(Boolean))].sort();
    const types = [...new Set(data.map(row => row[6]).filter(Boolean))].sort();
    const docTypes = [...new Set(data.map(row => row[8]).filter(Boolean))].sort();
    
    populateSelect('report_filter_company', companies);
    populateSelect('report_filter_year', years);
    populateSelect('report_filter_user', users);
    populateSelect('report_filter_type', types);
    populateSelect('report_filter_doc_type', docTypes);
}

function populateSelect(id, options) {
    const select = document.getElementById(id);
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = `<option value="">همه</option>`;
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt;
        option.textContent = opt;
        select.appendChild(option);
    });
    if (currentValue) select.value = currentValue;
}

// نمایش کوچک اولین و آخرین تاریخ موجود در فایل، کنار دکمه‌های تاریخ قبلی/بعدی
function updateReportDateRangeInfo() {
    const el = document.getElementById('reportDateRangeInfo');
    if (!el) return;
    
    if (!reportData || reportData.length === 0) {
        el.innerHTML = '';
        return;
    }
    
    const dates = [...new Set(reportData.map(row => row[4]).filter(Boolean))].sort();
    if (dates.length === 0) {
        el.innerHTML = '';
        return;
    }
    
    const first = dates[0];
    const last = dates[dates.length - 1];
    el.innerHTML = `<i class="fas fa-calendar-week" style="margin-left:3px;"></i> بازه: ${first} تا ${last}`;
}

// ===== کش کردن فیلترهای قبلی =====
let lastFilterState = '';

// اعمال فیلترها (خودکار)
function applyReportFilters() {
    if (!reportData || reportData.length === 0) return;
    
    const company = document.getElementById('report_filter_company').value;
    const year = document.getElementById('report_filter_year').value;
    const user = document.getElementById('report_filter_user').value;
    const number = document.getElementById('report_filter_number').value.trim();
    const type = document.getElementById('report_filter_type').value;
    const dateFrom = document.getElementById('report_filter_date_from').value.trim();
    const dateTo = document.getElementById('report_filter_date_to').value.trim();
    
    const docTypeCheckboxes = document.querySelectorAll('.doc-type-filter:checked');
    const selectedDocTypes = Array.from(docTypeCheckboxes).map(cb => cb.value);
    
    // ✅ ساخت کلید برای تشخیص تغییر فیلترها
    const currentState = JSON.stringify({ company, year, user, number, type, dateFrom, dateTo, selectedDocTypes });
    if (currentState === lastFilterState) {
        return; // اگر تغییری نکرده، کاری نکن
    }
    lastFilterState = currentState;
    
    // ✅ فیلتر با حلقه for (سریع‌تر از forEach)
    const filtered = [];
    const dataLen = reportData.length;
    
    for (let i = 0; i < dataLen; i++) {
        const row = reportData[i];
        const companyName = row[1] || '';
        
        // حذف برش کوه
        if (companyName === 'برش كوه' || companyName === 'شركت برش كوه آريا پارت (سهامي خاص)') {
            continue;
        }
        
        // فیلترهای معمول با شرط‌های کوتاه
        if (company && row[1] !== company) continue;
        if (year && row[2] !== year) continue;
        if (user && row[3] !== user) continue;
        if (number && !row[9].includes(number)) continue;
        if (type && row[6] !== type) continue;
        if (selectedDocTypes.length > 0 && !selectedDocTypes.includes(row[8])) continue;
        if (dateFrom && row[4] < dateFrom) continue;
        if (dateTo && row[4] > dateTo) continue;
        
        filtered.push(row);
    }
    
    renderReportTable(filtered);
}

// حذف همه فیلترها
function resetReportFilters() {
    const btn = document.querySelector('.btn-secondary[onclick="resetReportFilters()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    document.getElementById('report_filter_company').value = '';
    document.getElementById('report_filter_year').value = '';
    document.getElementById('report_filter_user').value = '';
    document.getElementById('report_filter_number').value = '';
    document.getElementById('report_filter_type').value = '';
    document.getElementById('report_filter_date_from').value = '';
    document.getElementById('report_filter_date_to').value = '';
    
    const docTypeCheckboxes = document.querySelectorAll('.doc-type-filter');
    docTypeCheckboxes.forEach(cb => {
        cb.checked = (cb.value === 'سند حسابداري');
    });
    
    // ✅ بازنشانی کش فیلتر
    lastFilterState = '';
    
    requestAnimationFrame(() => {
        applyReportFilters();
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
// ========== بروزرسانی دستی گزارش برهان ==========
function manualRefreshReport() {
    const btn = document.querySelector('.btn-primary[onclick="manualRefreshReport()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال بروزرسانی...';
    btn.disabled = true;
    
    // ✅ بارگذاری مجدد زمان بروزرسانی (قبل از هر چیز)
    loadFileUpdateTime();
    
    reportData = [];
    loadReportFilters();
    
    // ✅ بارگذاری آمار لحظه‌ای بعد از بروزرسانی
    setTimeout(() => {
        loadReportStats();
        // ✅ دوباره زمان رو به‌روز کن
        loadFileUpdateTime();
    }, 500);
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 1000);
}

// ========== آمار لحظه‌ای گزارش برهان ==========
function loadReportStats() {
    let dateInput = document.getElementById('report_stats_date');
    let date = dateInput ? dateInput.value : '';
    
    if (!date && reportData.length > 0) {
        const dates = [...new Set(reportData.map(row => row[4]))].sort();
        if (dates.length > 0) {
            date = dates[dates.length - 1];
            if (dateInput) dateInput.value = date;
        }
    }
    
    if (!date) {
        document.getElementById('statNewCount').textContent = '0';
        document.getElementById('statEditCount').textContent = '0';
        document.getElementById('statDeleteCount').textContent = '0';
        document.getElementById('statTotalCount').textContent = '0';
        document.getElementById('statLoginSuccessCount').textContent = '0';
        document.getElementById('statLoginFailCount').textContent = '0';
        showReportLoading(false);
        return;
    }
    
    fetch(`api/ajax.php?action=get_report_stats&date=${encodeURIComponent(date)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('statNewCount').textContent = data.new_count || 0;
                document.getElementById('statEditCount').textContent = data.edit_count || 0;
                document.getElementById('statDeleteCount').textContent = data.delete_count || 0;
                document.getElementById('statTotalCount').textContent = data.total || 0;
                document.getElementById('statLoginSuccessCount').textContent = data.login_success_count || 0;
                document.getElementById('statLoginFailCount').textContent = data.login_fail_count || 0;
            }
        })
        .catch(err => console.error(err));
    
    // بارگذاری گزارش ثبت جدید (کارت‌ها)
    if (typeof loadWarehouseReport === 'function') {
        loadWarehouseReport();
    }
}

// ===== کنترل لودینگ چرخشی کارت‌ها =====
function showWarehouseCardLoading(show, type = 'register') {
    const loading = document.getElementById('warehouseCardLoading');
    const grid = document.getElementById('reportWarehouseGrid');
    const emptyMsg = document.getElementById('reportWarehouseEmpty');
    const text = document.getElementById('warehouseLoadingText');
    
    if (show) {
        if (loading) {
            loading.style.display = 'flex';
            if (text) {
                const label = type === 'register' ? 'گزارش ثبت اسناد' : 'گزارش تایید اسناد';
                text.textContent = `در حال بارگذاری ${label}...`;
            }
        }
        if (grid) grid.style.display = 'none';
        if (emptyMsg) emptyMsg.style.display = 'none';
    } else {
        if (loading) loading.style.display = 'none';
        if (grid) grid.style.display = 'flex';
    }
}

// ========== بارگذاری گزارش ثبت/تایید اسناد ==========
function loadWarehouseReport() {
    const container = document.getElementById('reportWarehouseContainer');
    const grid = document.getElementById('reportWarehouseGrid');
    const dateSpan = document.getElementById('reportWarehouseDate');
    const totalSpan = document.getElementById('reportWarehouseTotal');
    const emptyMsg = document.getElementById('reportWarehouseEmpty');
    const loading = document.getElementById('warehouseCardLoading');
    
    const dateInput = document.getElementById('report_stats_date');
    const date = dateInput ? dateInput.value : '';
    
    if (!date) {
        if (container) container.style.display = 'none';
        showReportLoading(false);
        return;
    }
    
    // نمایش لودینگ چرخشی
    showWarehouseCardLoading(true, reportType);
    
    fetch(`api/ajax.php?action=get_warehouse_report&date=${encodeURIComponent(date)}&type=${reportType}`)
        .then(res => res.json())
        .then(data => {
            if (container) container.style.display = 'block';
            if (dateSpan) dateSpan.textContent = date;
            
            if (data.success && data.users && data.users.length > 0) {
                if (emptyMsg) emptyMsg.style.display = 'none';
                if (grid) {
                    grid.style.display = 'flex';
                    grid.style.flexDirection = 'row';
                    grid.style.flexWrap = 'wrap';
                    grid.style.alignItems = 'flex-start';
                    grid.style.gap = '12px';
                }
                
                // ===== گروه‌بندی بر اساس واحد =====
                const groups = {};
                const unitOrder = ['تنخواه', 'خزانه', 'فاکتور', 'پیمانکاران', 'درآمد', 'سایر'];
                const unitIcons = {
                    'تنخواه': '💰',
                    'خزانه': '🏛️',
                    'فاکتور': '📄',
                    'پیمانکاران': '🔧',
                    'درآمد': '📈',
                    'سایر': '📁'
                };
                
                data.users.forEach(user => {
                    const unit = user.unit || 'سایر';
                    if (!groups[unit]) groups[unit] = [];
                    groups[unit].push(user);
                });
                
                const unitCount = unitOrder.filter(u => groups[u] && groups[u].length > 0).length;
                const columnClass = (unitCount >= 6) ? 'six-columns' : '';
                
                let total = 0;
                let html = '';
                const colors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4', '#84cc16'];
                
                unitOrder.forEach(unit => {
                    if (groups[unit] && groups[unit].length > 0) {
                        const unitTotal = groups[unit].reduce((sum, u) => sum + u.count, 0);
                        const sortedUsers = [...groups[unit]].sort((a, b) => b.count - a.count);
                        
                        html += `
                            <div class="unit-column ${columnClass}" data-unit="${unit}">
                                <div class="unit-column-header">
                                    <span class="unit-column-icon">${unitIcons[unit] || '📁'}</span>
                                    <span class="unit-column-name">${unit}</span>
                                    <span class="unit-column-total">${unitTotal}</span>
                                </div>
                                <div class="unit-column-users">
                        `;
                        
                        sortedUsers.forEach((user, index) => {
                            const color = colors[index % colors.length];
                            const firstChar = user.user_name.charAt(0);
                            const userName = user.user_name;
                            
                            html += `
                                <div class="user-card-vertical warehouse-user-card" onclick="showUserDocuments(${user.user_id}, '${escapeHtml(userName)}', '${escapeHtml(date)}')" style="cursor:pointer;">
                                    <div class="user-avatar-vertical" style="background: ${color}">
                                        ${firstChar}
                                    </div>
                                    <span class="user-name-vertical" title="${escapeHtml(userName)}">${escapeHtml(userName)}</span>
                                    <span class="user-count-vertical">${user.count}</span>
                                </div>
                            `;
                            total += user.count;
                        });
                        
                        html += `
                                </div>
                            </div>
                        `;
                    }
                });
                
                if (grid) grid.innerHTML = html;
                if (totalSpan) totalSpan.textContent = `مجموع: ${total} سند`;
                
            } else {
                if (grid) {
                    grid.style.display = 'none';
                    grid.innerHTML = '';
                }
                if (emptyMsg) {
                    emptyMsg.style.display = 'block';
                }
                if (totalSpan) totalSpan.textContent = 'مجموع: ۰ سند';
            }
            
            // ✅ بعد از رندر کامل، لودینگ چرخشی را مخفی کن
            showWarehouseCardLoading(false);
            
            // ✅ بعد از رندر کامل کارت‌ها، لودینگ کلی گزارش را ببند
            setTimeout(() => {
                showReportLoading(false);
            }, 150);
        })
        .catch(err => {
            console.error(err);
            if (container) container.style.display = 'none';
            showWarehouseCardLoading(false);
            showReportLoading(false);
        });
}

// ===== تغییر نوع گزارش =====
function toggleReportType() {
    const toggleBtn = document.getElementById('toggleReportTypeBtn');
    const btnText = document.getElementById('reportToggleBtnText');
    const titleSpan = document.getElementById('reportWarehouseTitle');
    
    // اگر دکمه وجود نداشت، خارج شو
    if (!toggleBtn) {
        console.error('دکمه toggleReportTypeBtn یافت نشد');
        return;
    }
    
    // تغییر نوع گزارش
    const newType = (reportType === 'register') ? 'confirm' : 'register';
    reportType = newType;
    
    // نمایش لودینگ
    showWarehouseCardLoading(true, reportType);
    
    // غیرفعال کردن دکمه
    toggleBtn.disabled = true;
    toggleBtn.style.opacity = '0.7';
    
    // تغییر ظاهر دکمه و عنوان باکس با تاخیر
    setTimeout(() => {
        if (reportType === 'register') {
            // حالت گزارش ثبت
            toggleBtn.className = 'toggle-report-btn register-mode';
            if (btnText) btnText.textContent = 'گزارش تایید اسناد';
            if (titleSpan) titleSpan.textContent = 'گزارش ثبت سند';
        } else {
            // حالت گزارش تایید
            toggleBtn.className = 'toggle-report-btn confirm-mode';
            if (btnText) btnText.textContent = 'گزارش ثبت اسناد';
            if (titleSpan) titleSpan.textContent = 'گزارش تایید سند';
        }
        
        // بارگذاری مجدد
        loadWarehouseReport();
        
        // فعال کردن دکمه
        setTimeout(() => {
            toggleBtn.disabled = false;
            toggleBtn.style.opacity = '1';
        }, 500);
    }, 300);
}

function setReportDate(type) {
    const input = document.getElementById('report_stats_date');
    if (!input) return;
    
    // دریافت لیست تاریخ‌های موجود از reportData
    const dates = [...new Set(reportData.map(row => row[4]))].sort();
    if (dates.length === 0) {
        showToast('هیچ تاریخی موجود نیست', true);
        return;
    }
    
    const currentDate = input.value;
    const currentIndex = dates.indexOf(currentDate);
    
    let newDate;
    if (type === 'prev') {
        if (currentIndex > 0) {
            newDate = dates[currentIndex - 1];
        } else {
            showToast('این اولین تاریخ موجود است', false);
            return;
        }
    } else if (type === 'next') {
        if (currentIndex < dates.length - 1) {
            newDate = dates[currentIndex + 1];
        } else {
            showToast('این آخرین تاریخ موجود است', false);
            return;
        }
    }
    
    if (newDate) {
        // ✅ تنظیم هر سه فیلد با تاریخ جدید
        input.value = newDate;
        
        const dateFromInput = document.getElementById('report_filter_date_from');
        const dateToInput = document.getElementById('report_filter_date_to');
        if (dateFromInput) dateFromInput.value = newDate;
        if (dateToInput) dateToInput.value = newDate;
        
        // بارگذاری آمار لحظه‌ای
        loadReportStats();
        
        // اعمال فیلترها
        applyReportFilters();
    }
}

function renderReportTable(data) {
    const container = document.getElementById('reportTableContainer');
    const tbody = document.getElementById('reportTableBody');
    const totalCount = document.getElementById('reportTotalCount');
    const filteredCount = document.getElementById('reportFilteredCount');
    
    if (data.length === 0) {
        container.style.display = 'none';
        document.getElementById('reportEmptyState').style.display = 'block';
        document.getElementById('reportEmptyState').innerHTML = `
            <i class="fas fa-search" style="font-size: 2rem; opacity: 0.5;"></i>
            <p style="margin-top: 10px;">هیچ رکوردی با فیلترهای انتخاب شده یافت نشد</p>
        `;
        return;
    }
    
    document.getElementById('reportEmptyState').style.display = 'none';
    container.style.display = 'block';
    
    // ✅ ساخت آرایه از ردیف‌ها برای سرعت بیشتر
    const rows = [];
    const rowCount = data.length;
    const escape = escapeHtml;
    
    // ✅ کش کردن badge classes
    const badgeClasses = {
        'موجوديت جديد': 'badge-success',
        'ويرايش': 'badge-warning',
        'حذف': 'badge-danger'
    };
    const defaultBadge = 'badge-neutral';
    
    for (let i = 0; i < rowCount; i++) {
        const row = data[i];
        const type = row[6] || '';
        
        let rowClass = '';
        if (type.includes('موجوديت جديد')) rowClass = 'row-new';
        else if (type.includes('ويرايش')) rowClass = 'row-edit';
        else if (type.includes('حذف')) rowClass = 'row-delete';
        
        let badgeClass = defaultBadge;
        if (type.includes('موجوديت جديد')) badgeClass = 'badge-success';
        else if (type.includes('ويرايش')) badgeClass = 'badge-warning';
        else if (type.includes('حذف')) badgeClass = 'badge-danger';
        
        rows.push(`<tr class="${rowClass}">
            <td>${i + 1}</td>
            <td>${escape(row[1] || '')}</td>
            <td>${escape(row[2] || '')}</td>
            <td>${escape(row[3] || '')}</td>
            <td>${escape(row[4] || '')}</td>
            <td>${escape(row[5] || '')}</td>
            <td><span class="badge ${badgeClass}">${escape(type)}</span></td>
            <td>${escape(row[7] || '')}</td>
            <td>${escape(row[8] || '')}</td>
            <td>${escape(row[9] || '')}</td>
            <td>${escape(row[10] || '')}</td>
            <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escape(row[11] || '')}</td>
        </tr>`);
    }
    
    // ✅ یکبار جایگزینی محتوای tbody با join
    tbody.innerHTML = rows.join('');
    
    totalCount.textContent = reportData.length;
    filteredCount.textContent = rowCount;
}

function getTypeBadgeClass(type) {
    if (!type) return 'badge-neutral';
    if (type.includes('موجوديت جديد')) return 'badge-success';
    if (type.includes('ويرايش')) return 'badge-warning';
    if (type.includes('حذف')) return 'badge-danger';
    return 'badge-neutral';
}

// ========== دریافت زمان بروزرسانی فایل ==========
function loadFileUpdateTime() {
    const updateSpan = document.getElementById('reportUpdateTime');
    if (!updateSpan) return;
    
    // نمایش حالت بارگذاری
    updateSpan.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-left:4px; color:#667eea;"></i> در حال بروزرسانی...';
    
    fetch('api/ajax.php?action=get_file_update_time')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateSpan.innerHTML = '<i class="fas fa-clock" style="margin-left:4px; color:#10b981;"></i> آخرین خروجی گرفته شده از برهان: <span style="font-weight:600; color:#1e293b; background:#dcfce7; padding:1px 8px; border-radius:12px;">' + data.update_time + '</span>';
            } else {
                updateSpan.innerHTML = '<i class="fas fa-clock" style="margin-left:4px; color:#94a3b8;"></i> آخرین خروجی گرفته شده از برهان: <span style="font-weight:600; color:#94a3b8;">نامشخص</span>';
            }
        })
        .catch(err => {
            console.error(err);
            updateSpan.innerHTML = '<i class="fas fa-clock" style="margin-left:4px; color:#ef4444;"></i> آخرین خروجی گرفته شده از برهان: <span style="font-weight:600; color:#ef4444;">خطا</span>';
        });
}

// ========================================================================
// ===== پایش خودکار فایل CSV گزارش برهان (بدون نیاز به رفرش/کلیک کاربر) =====
// ========================================================================
// هر REPORT_WATCH_INTERVAL_MS میلی‌ثانیه، فقط یک درخواست بسیار سبک به سرور
// زده می‌شود (check_report_update) که تنها filemtime فایل را برمی‌گرداند و
// کل CSV را پارس نمی‌کند. فقط وقتی امضای فایل واقعاً تغییر کند، درخواست
// سنگین‌تر load_report_data برای دریافت داده‌های واقعی زده می‌شود.

function checkReportFileUpdate() {
    // ✅ اضافه کردن یک پارامتر همیشه‌متفاوت (timestamp) به URL تا هیچ لایه‌ای
    // (کش مرورگر، کش دیسک، پروکسی/CDN میانی) این درخواست تکراری را کش نکند
    // و همیشه واقعاً به سرور برسد.
    fetch(`api/ajax.php?action=check_report_update&_=${Date.now()}`, { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            
            // ✅ اولین بار فقط مقدار مرجع را ثبت می‌کنیم (بدون رفرش و بدون نمایش پیام)
            if (reportKnownSignature === null) {
                reportKnownSignature = data.signature;
                return;
            }
            
            if (data.signature !== reportKnownSignature) {
                reportKnownSignature = data.signature;
                refreshReportDataFromServer();
            }
        })
        .catch(err => console.error('خطا در بررسی بروزرسانی فایل گزارش:', err));
}

// نوتیفیکیشن ماندگارِ بروزرسانی خودکار گزارش برهان — برخلاف showToast معمولی،
// این یکی خودش بسته نمی‌شود؛ کاربر باید با کلیک روی × آن را ببندد تا مطمئن
// شویم واقعاً متوجه بروزرسانی شده است.
function showReportUpdateNotice() {
    let notice = document.getElementById('reportUpdateNotice');
    
    if (!notice) {
        notice = document.createElement('div');
        notice.id = 'reportUpdateNotice';
        notice.style.cssText = `
            position: fixed;
            top: 90px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            background: white;
            border-right: 4px solid #10b981;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.18);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.75rem;
            max-width: 420px;
            direction: rtl;
        `;
        notice.innerHTML = `
            <i class="fas fa-check-circle" style="color:#10b981; font-size:1.15rem; flex-shrink:0;"></i>
            <span style="flex:1; color:#1e293b; font-weight:500; line-height:1.6;">فایل گزارش برهان بروزرسانی شد و داده‌های جدید نمایش داده شد.</span>
            <button onclick="document.getElementById('reportUpdateNotice')?.remove()" title="بستن"
                style="background:#f1f5f9; border:none; cursor:pointer; color:#64748b; width:24px; height:24px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:0.7rem;">
                <i class="fas fa-times"></i>
            </button>
        `;
        notice.classList.add('animate__animated', 'animate__fadeInDown');
        document.body.appendChild(notice);
    } else {
        // ✅ اگر نوتیفیکیشن قبلی هنوز بسته نشده (مثلاً چند تغییر پشت سر هم رخ داده)،
        // به‌جای انباشتن چند نوتیفیکیشن روی هم، همان یکی را با یک افکت کوچک تاکید می‌کنیم
        notice.classList.remove('animate__fadeInDown');
        void notice.offsetWidth; // ری‌استارت انیمیشن (reflow اجباری)
        notice.classList.add('animate__fadeInDown');
    }
}

function refreshReportDataFromServer() {
    fetch('api/ajax.php?action=load_report_data')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            
            const reportsContent = document.getElementById('reportsContent');
            const isReportsVisible = reportsContent && reportsContent.style.display !== 'none';
            
            if (isReportsVisible) {
                reportData = data.data;
                
                // بازسازی گزینه‌های فیلترها با حفظ انتخاب فعلی کاربر
                populateReportFilters(reportData);
                
                // ✅ بروزرسانی بازه اولین/آخرین تاریخ موجود
                updateReportDateRangeInfo();
                
                // ✅ چون خودِ داده تغییر کرده (نه لزوماً مقدار فیلترها)، کش تشخیص
                // تغییر فیلتر را باطل می‌کنیم تا applyReportFilters() واقعاً جدول
                // را دوباره رندر کند، حتی اگر فیلترهای انتخابی کاربر عوض نشده باشند.
                lastFilterState = '';
                applyReportFilters();
                
                if (typeof loadReportStats === 'function') loadReportStats();
                if (typeof loadFileUpdateTime === 'function') loadFileUpdateTime();
                
                // ✅ اطلاع‌رسانی به کاربر که تا خودش نبندد باقی می‌ماند
                showReportUpdateNotice();
            }
            
            // بروزرسانی نشانگر اعلان (دایره قرمز) روی آیتم منو، چه بخش گزارش باز باشد چه نه
            const menuItem = document.querySelector('.menu-item[data-section="reports"]');
            if (menuItem) {
                const oldDot = menuItem.querySelector('.report-notification');
                if (oldDot) oldDot.remove();
                
                // اگر کاربر همین الان در بخش گزارش است، اعلان لازم نیست (تازه دیده)
                if (!isReportsVisible && data.new_count > 0) {
                    const dot = document.createElement('span');
                    dot.className = 'report-notification';
                    dot.style.cssText = 'display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;margin-right:6px;animation:pulse-dot 1.5s infinite;';
                    menuItem.prepend(dot);
                }
            }
        })
        .catch(err => console.error('خطا در بروزرسانی خودکار گزارش:', err));
}

function startReportFileWatcher() {
    if (reportWatcherInterval) return; // از اجرای موازی چند تایمر جلوگیری می‌کند
    checkReportFileUpdate(); // یک‌بار همین الان هم بررسی کن تا امضای مرجع ثبت شود
    reportWatcherInterval = setInterval(checkReportFileUpdate, REPORT_WATCH_INTERVAL_MS);
}

function stopReportFileWatcher() {
    if (reportWatcherInterval) {
        clearInterval(reportWatcherInterval);
        reportWatcherInterval = null;
    }
}

function showLeftContent(section) {
    // مخفی کردن همه بخش‌ها

    const sections = ['statsContent', 'usersContent', 'companiesContent', 'filtersContent', 'archiveContent', 'userStatsContent', 'approvalsContent', 'reportsContent', 'db_managerContent'];
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
            loadPendingUsersList(true);
        }
    }
    if (section === 'reports') {
        // پاک کردن اعلان گزارش برهان
        fetch('api/ajax.php?action=clear_report_notification', { method: 'POST' });
        
        // حذف دایره اعلان از منو
        const menuItem = document.querySelector('.menu-item[data-section="reports"]');
        if (menuItem) {
            const dot = menuItem.querySelector('.report-notification');
            if (dot) dot.remove();
        }
        
        // بارگذاری فیلترهای گزارش برهان
        if (typeof loadReportFilters === 'function') {
            loadReportFilters();
        }
        
        // بارگذاری آمار لحظه‌ای
        if (typeof loadReportStats === 'function') {
            setTimeout(function() {
                loadReportStats();
            }, 500);
        }
        
        // ✅ بارگذاری زمان بروزرسانی فایل
        if (typeof loadFileUpdateTime === 'function') {
            setTimeout(function() {
                loadFileUpdateTime();
            }, 600);
        }
        
        // ✅ پایشگر خودکار فایل (از بارگذاری صفحه در پس‌زمینه فعال است، همین‌جا هم
        // اطمینان می‌دهیم که روشن باشد). چون همین الان داده‌ی تازه با loadReportFilters()
        // گرفته شد، امضای مرجع را ریست می‌کنیم تا اولین بررسیِ بعدی پایشگر فقط
        // مرجع را ثبت کند و یک رفرش اضافی/غیرضروری انجام ندهد.
        reportKnownSignature = null;
        startReportFileWatcher();
    }
    if (section === 'db_manager') {
        if (typeof loadDbManagerData === 'function') {
            loadDbManagerData();
        }
    }
}

function showApprovalsTab(tab) {
    if (tab === 'pending') {
        document.getElementById('pendingApprovalsTab').style.display = 'block';
        loadPendingUsersList(true);
    }
}

async function loadUsersList() {
    let container = document.getElementById('usersList');
    if (!container) return;
    
    container.innerHTML = '<div class="empty-state">در حال بارگذاری...</div>';
    
    try {
        let res = await fetch(`${apiUrl}?action=get_users`);
        let data = await res.json();
        
        if (data.success && data.users) {
            // ✅ اگر کاربری وجود ندارد، پیام مناسب نمایش بده
            if (data.users.length === 0) {
                container.innerHTML = '<div class="empty-state">هیچ کاربری با سند ثبت‌شده وجود ندارد</div>';
                return;
            }
            
            let html = '';
            
            for (let user of data.users) {
                let permissionCheckbox = user.can_view_all_archives == 1 ? 'checked' : '';
                let unitStatsCheckbox = user.can_view_unit_stats == 1 ? 'checked' : '';
                
                html += `
                    <div class="user-item" onclick="showUserDocuments(${user.id}, '${escapeHtml(user.fullname)}')" style="cursor:pointer;">
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(user.fullname)}</div>
                            <div class="user-unit">
                                ${escapeHtml(user.unit_name)} | ${escapeHtml(user.username)} | کل اسناد: ${user.total_docs}
                            </div>
                            <div class="user-unit" style="margin-top: 5px;">
                                <label style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="archive-permission" data-id="${user.id}" ${permissionCheckbox} onchange="toggleArchivePermission(${user.id}, this.checked)">
                                    دسترسی به بایگانی همه کاربران
                                </label>
                                <label style="font-size: 0.65rem; display: flex; align-items: center; gap: 5px; margin-top: 3px;" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="unit-stats-permission" data-id="${user.id}" ${unitStatsCheckbox} onchange="toggleUnitStatsPermission(${user.id}, this.checked)">
                                    دسترسی به آمار کاربران واحد خود
                                </label>
                            </div>
                        </div>
                        <div onclick="event.stopPropagation();">
                            <button class="action-btn edit-btn" onclick="editUser(${user.id}, '${escapeHtml(user.username)}', '${escapeHtml(user.fullname)}', '${escapeHtml(user.unit_name)}', ${user.require_doc_date}, ${user.can_view_unit_stats})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="deleteUser(${user.id})">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ کاربری یافت نشد</div>';
        }
    } catch(e) {
        console.error(e);
        container.innerHTML = '<div class="empty-state">خطا در دریافت اطلاعات</div>';
    }
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


function showAddUserModal() {
    document.getElementById('userModalTitle').innerText = 'افزودن کاربر جدید';
    document.getElementById('edit_user_id').value = '';
    document.getElementById('user_username').value = '';
    document.getElementById('user_fullname').value = '';
    document.getElementById('user_unit').value = '';
    document.getElementById('user_password').value = '';
    document.getElementById('user_require_date').checked = true;
    document.getElementById('user_can_view_unit_stats').checked = false;
    document.getElementById('userModal').classList.add('active');
}

function editUser(id, username, fullname, unit, requireDate, canViewUnitStats) {
    document.getElementById('userModalTitle').innerText = 'ویرایش کاربر';
    document.getElementById('edit_user_id').value = id;
    document.getElementById('user_username').value = username;
    document.getElementById('user_fullname').value = fullname;
    document.getElementById('user_unit').value = unit;
    document.getElementById('user_password').value = '';
    document.getElementById('user_require_date').checked = requireDate == 1;
    document.getElementById('user_can_view_unit_stats').checked = canViewUnitStats == 1;
    document.getElementById('userModal').classList.add('active');
}

async function saveUser() {
    let id = document.getElementById('edit_user_id').value;
    let username = document.getElementById('user_username').value.trim();
    let fullname = document.getElementById('user_fullname').value.trim();
    let unit = document.getElementById('user_unit').value.trim();
    let password = document.getElementById('user_password').value;
    let requireDate = document.getElementById('user_require_date').checked ? 1 : 0;
    let canViewUnitStats = document.getElementById('user_can_view_unit_stats').checked ? 1 : 0;
    
    if (!username || !fullname || !unit) { showToast('تمامی فیلدها الزامی است', true); return; }
    
    let url = id ? `${apiUrl}?action=update_user` : `${apiUrl}?action=add_user`;
    let body = id ? { 
        id, 
        username, 
        fullname, 
        unit_name: unit, 
        require_doc_date: requireDate, 
        can_view_unit_stats: canViewUnitStats,
        password 
    } : { 
        username, 
        fullname, 
        unit_name: unit, 
        require_doc_date: requireDate, 
        can_view_unit_stats: canViewUnitStats,
        password 
    };
    
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

// ========== تغییر دسترسی آمار کاربران واحد ==========
async function toggleUnitStatsPermission(userId, isChecked) {
    try {
        let res = await fetch(`${apiUrl}?action=toggle_unit_stats_permission`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                can_view_unit_stats: isChecked ? 1 : 0 
            })
        });
        
        let result = await res.json();
        
        if (result.success) {
            showToast('✅ دسترسی با موفقیت به‌روز شد');
            // به‌روزرسانی لیست کاربران بدون رفرش
            loadUsersList();
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

window.openEditModal = function(id, number, date, description, companyId) {
    let editId = document.getElementById('edit_id');
    let editNumber = document.getElementById('edit_number');
    let editDate = document.getElementById('edit_date');
    let editCompany = document.getElementById('edit_company_id');
    
    if (editId) editId.value = id;
    if (editNumber) editNumber.value = number;
    if (editDate) editDate.value = (date === '-' ? '' : date);
    
    if (editCompany) {
        if (companyId && companyId > 0) {
            editCompany.value = companyId;
        } else {
            let options = editCompany.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value > 0) {
                    editCompany.value = options[i].value;
                    break;
                }
            }
        }
    }
    
    let modal = document.getElementById('editModal');
    if (modal) modal.classList.add('active');
}
window.closeEditModal = function() { 
    document.getElementById('editModal').classList.remove('active'); 
}

// ========== ذخیره ویرایش ==========
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
            loadDocumentsForDeliveryDate(currentDate, false);
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

window.closeEditModal = function() { 
    document.getElementById('editModal').classList.remove('active'); 
}

window.deleteDocument = async function(id) {
    if (!confirm('حذف شود؟')) return;
    let res = await fetch(`${apiUrl}?action=delete_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
    let result = await res.json();
    if (result.success) { showToast('حذف شد'); if (document.getElementById('filtersContent').style.display !== 'none') { searchAdminDocuments(); } }
}

loadUsersList();

<?php endif; ?>

function autoSearchDocuments() {
    const doc_number = document.getElementById('filter_number')?.value || '';
    const doc_date = document.getElementById('filter_date')?.value || '';
    const company_id = document.getElementById('filter_company')?.value || '';
    const delivery_date = document.getElementById('filter_delivery')?.value || '';
    
    // ✅ ارسال include_unit=true برای نمایش اسناد هم‌واحد
    fetch(`api/ajax.php?action=get_documents&doc_number=${encodeURIComponent(doc_number)}&doc_date=${encodeURIComponent(doc_date)}&company_id=${encodeURIComponent(company_id)}&delivery_date=${encodeURIComponent(delivery_date)}&include_unit=true`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('userDocumentsList');
                if (!container) return;
                if (data.groups && data.groups.length > 0) {
                    let html = '';
                    data.groups.forEach(group => {
                        // ===== تشخیص سال کم‌تعداد (فقط کوچکترین سال) =====
                        const yearCounts = {};
                        const docYears = {};
                        
                        group.documents.forEach(doc => {
                            const docDate = doc.doc_date;
                            if (docDate && docDate !== '-' && docDate !== '') {
                                const year = docDate.substring(0, 4);
                                docYears[doc.id] = year;
                                
                                if (!yearCounts[year]) {
                                    yearCounts[year] = 0;
                                }
                                yearCounts[year]++;
                            }
                        });
                        
                        // پیدا کردن کمترین تعداد
                        let minCount = Infinity;
                        for (const [year, count] of Object.entries(yearCounts)) {
                            if (count < minCount) {
                                minCount = count;
                            }
                        }
                        
                        // پیدا کردن کوچکترین سالی که کمترین تعداد رو داره
                        let minYear = null;
                        for (const [year, count] of Object.entries(yearCounts)) {
                            if (count === minCount) {
                                if (minYear === null || parseInt(year) < parseInt(minYear)) {
                                    minYear = year;
                                }
                            }
                        }
                        
                        const hasMultipleYears = Object.keys(yearCounts).length > 1;
                        // ===== پایان تشخیص سال =====
                        
                        // ✅ دکمه «مشاهده» فقط وقتی نمایش داده شود که تمام اسناد این گروه
                        // متعلق به خود کاربر باشد؛ برای گروه‌هایی که شامل سند هم‌واحدها هستند
                        // اصلاً رندر نمی‌شود (نه فقط مخفی/غیرفعال با CSS).
                        const canViewGroup = group.group_owned_by_user === true;
                        const viewBtnHtml = canViewGroup
                            ? `<button class="print-btn" onclick="viewArchiveDocument('${group.delivery_date}')">مشاهده</button>`
                            : '';

                        html += `<div class="doc-group">
                            <div class="group-title">
                                <span>${group.delivery_date}</span>
                                <span>${group.count} سند</span>
                                ${viewBtnHtml}
                            </div>
                            <table class="data-table">
                                <thead><tr><th>#</th><th>کاربر</th><th>شماره سند ثابت</th><th>تاریخ سند</th><th>شرکت</th></tr></thead>
                                <tbody>`;
                        
                        group.documents.forEach((doc, idx) => {
                            let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                            let docYear = doc.doc_date && doc.doc_date !== '-' ? doc.doc_date.substring(0, 4) : '';
                            
                            // ===== هایلایت عدد سال (فقط سال minYear) =====
                            let dateCellContent = docDate;
                            if (hasMultipleYears && minYear !== null && docYear === minYear) {
                                dateCellContent = docDate.replace(docYear, `<span class="year-highlight">${docYear}</span>`);
                            }
                            // ===== پایان هایلایت =====
                            
                            html += `<tr>
                                <td>${idx+1}</td>
                                <td>${escapeHtml(doc.user_name || 'نامشخص')}</td>
                                <td>${escapeHtml(doc.doc_number)}</td>
                                <td>${dateCellContent}</td>
                                <td>${escapeHtml(doc.company_name)}</td>
                            </tr>`;
                        });
                        html += `</tbody></table>`;
                        html += `</div>`;
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

document.addEventListener('DOMContentLoaded', function() {
    const filterNumber = document.getElementById('filter_number');
    const filterDate = document.getElementById('filter_date');
    const filterCompany = document.getElementById('filter_company');
    const filterDelivery = document.getElementById('filter_delivery');
    
    if (filterNumber) filterNumber.addEventListener('input', autoSearchDocuments);
    if (filterDate) filterDate.addEventListener('input', autoSearchDocuments);
    if (filterCompany) filterCompany.addEventListener('change', autoSearchDocuments);
    if (filterDelivery) filterDelivery.addEventListener('input', autoSearchDocuments);
    
    autoSearchDocuments();
    
    if (document.getElementById('userStatsContainer')) {
        loadUserStats();
    }
    
    const dateMinus = document.getElementById('dateMinus');
    const datePlus = document.getElementById('datePlus');
    const deliveryDateInput = document.getElementById('delivery_date');
    
    if (dateMinus && datePlus && deliveryDateInput) {
        dateMinus.addEventListener('click', function() { addDaysToDelivery(-1); });
        datePlus.addEventListener('click', function() { addDaysToDelivery(1); });
        
        if (typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(deliveryDateInput.value);
        }
    }
    
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
    
    if (document.getElementById('userArchiveList')) {
        loadUserArchiveList();
    }
    
    if (document.getElementById('adminFiltersPanel')) {
        if (typeof bindAdminSearchEvents === 'function') bindAdminSearchEvents();
        if (typeof adminAutoSearch === 'function') adminAutoSearch();
    }
    
    if (document.getElementById('companiesList')) {
        if (typeof loadCompaniesList === 'function') loadCompaniesList();
    }
    
    if (document.getElementById('adminUsersStatsList')) {
        if (typeof loadAdminUsersStats === 'function') loadAdminUsersStats();
    }
    
    if (document.getElementById('adminArchiveList')) {
        if (typeof loadAdminArchiveList === 'function') loadAdminArchiveList();
    }
    
    if (document.getElementById('reportsContent')) {
        loadReportFilters();
        loadFileUpdateTime();
    }
    
    // ✅ پایش خودکار فایل CSV گزارش برهان (جایگزین fetch سنگین هر ۵ دقیقه‌ی قبلی).
    // این پایشگر همین الان شروع می‌شود و تا پایان نشست کاربر در پس‌زمینه فعال
    // می‌ماند: هر ۱۵ ثانیه فقط زمان تغییر فایل (filemtime) را بررسی می‌کند - نه
    // کل فایل CSV را - و فقط زمانی که واقعاً فایل عوض شده باشد، داده‌های تازه را
    // می‌گیرد. اگر کاربر همان لحظه در بخش گزارش باشد، جدول/فیلترها/آمار بدون نیاز
    // به رفرش صفحه به‌روز می‌شوند و یک پیام کوتاه نمایش داده می‌شود؛ در غیر این
    // صورت فقط نشانگر اعلان روی منو به‌روز می‌گردد.
    if (document.querySelector('.menu-item[data-section="reports"]')) {
        startReportFileWatcher();
    }
});
</script>

<!-- ===== جاوااسکریپت مدیریت دیتابیس ===== -->
<script src="assets/js/db_manager.js"></script>

</body>
</html>