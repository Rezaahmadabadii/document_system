<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/database.php';
require_once 'config/jdatetime.class.php';

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;
$require_doc_date = $_SESSION['require_doc_date'] ?? 1;
$lock_delivery_date = $_SESSION['lock_delivery_date'] ?? 0;
$fullname = $_SESSION['fullname'] ?? 'کاربر';
$unit_name = $_SESSION['unit_name'] ?? 'واحد نامشخص';

// تبدیل اعداد فارسی به لاتین
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
    <!-- حذف لینک CSS -->
    <!-- <link rel="stylesheet" href="assets/css/all.min.css"> -->
    
    <!-- اضافه کردن JS محلی Font Awesome -->
    <script defer src="assets/js/all.min.js"></script>
    
    <!-- فونت محلی -->
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif !important;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-size: 14px;
        }
        
        .main-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e2e8f0;
        }
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 12px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .logo h1 { font-size: 1.2rem; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo p { font-size: 0.6rem; color: #6c86a3; }
        .main-content {
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 28px;
        }
        .documents-layout {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        .right-panel-panel {
            width: 360px;
            position: sticky;
            top: 90px;
            align-self: flex-start;
        }
        .left-panel-list {
            flex: 1;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
        }
        .main-panel-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .profile-section {
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar-large {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .user-info-header h3 { font-size: 0.9rem; margin-bottom: 3px; }
        .user-info-header p { font-size: 0.65rem; opacity: 0.9; }
        .header-actions {
            margin-right: auto;
            display: flex;
            gap: 8px;
        }
        .icon-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.3); }
        
        .admin-menu {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            margin: 4px 0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.75rem;
            font-weight: 500;
            color: #475569;
        }
        .menu-item i {
            width: 24px;
            font-size: 0.9rem;
            color: #667eea;
        }
        .menu-item:hover {
            background: #eef2ff;
            color: #667eea;
        }
        .menu-item.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .menu-item.active i { color: white; }
        
        .left-content { padding: 16px; }
        .left-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1a2c3e;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8fafc, #fff);
            border-radius: 12px;
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #eef2f5;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-value {
            font-size: 1.1rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label { font-size: 0.55rem; color: #6c86a3; margin-top: 3px; }
        .stat-small { font-size: 0.6rem; color: #475569; margin-top: 6px; padding-top: 6px; border-top: 1px solid #eef2f5; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;
        }
        .data-table th {
            background: #f1f5f9;
            padding: 10px 8px;
            border: 1px solid #e2e8f0;
            font-weight: 700;
            color: #475569;
            text-align: center;
        }
        .data-table td {
            padding: 8px 6px;
            border: 1px solid #e2e8f0;
            text-align: center;
            color: #334155;
        }
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        .docs-list { padding: 16px; }
        .doc-group {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #eef2f5;
        }
        .group-title { 
            padding: 12px 16px; 
            background: linear-gradient(135deg, #f8fafc, #fff);
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid #667eea;
            flex-wrap: wrap; 
            gap: 8px; 
        }
        .group-date { 
            font-weight: 700; 
            font-size: 0.85rem; 
            color: #667eea;
        }
        .print-btn { 
            background: linear-gradient(135deg, #10b981, #059669); 
            color: white; 
            padding: 5px 14px; 
            border-radius: 20px; 
            text-decoration: none; 
            font-size: 0.65rem;
            font-weight: 600;
        }
        .print-btn:hover { background: linear-gradient(135deg, #059669, #047857); }
        
        .action-btn { background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; }
        .edit-btn { color: #667eea; }
        .delete-btn { color: #ef4444; }
        .empty-state { text-align: center; padding: 40px; color: #94a3b8; font-size: 0.75rem; }
        
        .form-section { padding: 14px 16px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { 
            display: block; 
            font-size: 0.7rem; 
            font-weight: 600; 
            color: #475569; 
            margin-bottom: 5px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; 
            padding: 8px 12px; 
            background: #f8fafc; 
            border: 1.5px solid #e2e8f0; 
            border-radius: 12px; 
            font-size: 0.75rem;
        }
        .btn-submit, .btn-green {
            width: 100%; 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white;
            border: none; 
            padding: 9px; 
            border-radius: 12px; 
            font-size: 0.8rem; 
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 8px;
        }
        .btn-green {
            background: linear-gradient(135deg, #10b981, #059669);
            margin-top: 0;
        }
        .btn-green:hover { background: linear-gradient(135deg, #059669, #047857); }
        
        .admin-filter-box { 
            padding: 14px 16px; 
            background: #f8fafc; 
            border-bottom: 1px solid #eef2f5;
            display: none;
        }
        .admin-filter-box.visible { display: block; }
        .admin-filter-group { margin-bottom: 12px; }
        .admin-filter-group label { 
            display: block; 
            font-size: 0.65rem; 
            font-weight: 600; 
            color: #6c86a3; 
            margin-bottom: 4px;
        }
        .admin-filter-group select, .admin-filter-group input { 
            width: 100%; 
            padding: 8px 12px; 
            background: white; 
            border: 1px solid #e2e8f0; 
            border-radius: 10px; 
            font-size: 0.7rem;
        }
        
        .user-panel-buttons {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f5;
        }
        .user-panel-btn {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            background: #e2e8f0;
            color: #475569;
            transition: all 0.2s;
        }
        .user-panel-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .user-form-panel { display: block; }
        .user-search-panel { display: none; }
        
        .user-select-for-stats {
            padding: 12px 16px;
            border-bottom: 1px solid #eef2f5;
            background: #f8fafc;
        }
        .user-select-for-stats label {
            font-size: 0.7rem;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }
        .user-select-for-stats select {
            width: 100%;
            padding: 8px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .descriptions-section {
            margin-top: 15px;
            padding: 12px;
            background: #fefce8;
            border-radius: 12px;
            border-right: 3px solid #eab308;
        }
        .desc-title {
            font-size: 0.7rem;
            font-weight: 700;
            color: #854d0e;
            margin-bottom: 8px;
        }
        .desc-item {
            background: #fffbeb;
            padding: 6px 10px;
            border-radius: 8px;
            margin-bottom: 5px;
            font-size: 0.65rem;
            color: #78350f;
        }
        
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content { 
            background: white; 
            border-radius: 24px; 
            padding: 24px; 
            width: 500px; 
            max-width: 90%; 
            max-height: 80vh; 
            overflow-y: auto;
        }
        .modal-content h3 { margin-bottom: 15px; font-size: 1rem; }
        .modal-buttons { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white; 
            padding: 8px 16px; 
            border-radius: 10px; 
            border: none; 
            cursor: pointer;
            font-size: 0.7rem;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
            padding: 8px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 0.7rem;
        }
        .toast { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            background: #1a2c3e; 
            color: white; 
            padding: 8px 18px; 
            border-radius: 12px; 
            font-size: 0.7rem; 
            z-index: 1100; 
            display: none;
        }
        .scrollable-list { max-height: 450px; overflow-y: auto; }
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 10px;
        }
        .company-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            border: 1px solid #eef2f5;
        }
        .company-card .company-name {
            font-size: 0.7rem;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
        }
        .user-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .user-info { font-size: 0.7rem; }
        .user-name { font-weight: 700; color: #1a2c3e; }
        .user-unit { color: #6c86a3; font-size: 0.6rem; }
        
        .date-spinner:hover {
            background: #667eea !important;
            color: white !important;
            border-color: #667eea !important;
        }
        
        @media (max-width: 1400px) { 
            .companies-grid { grid-template-columns: repeat(6, 1fr); }
        }
        
        @media (max-width: 1200px) { 
            .companies-grid { grid-template-columns: repeat(4, 1fr); }
        }
        
        @media (max-width: 900px) { 
            .documents-layout { flex-direction: column; } 
            .right-panel-panel { width: 100%; position: relative; top: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .companies-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 500px) { 
            .companies-grid { grid-template-columns: repeat(1, 1fr); }
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
                    <div class="menu-item active" data-section="stats" onclick="showLeftContent('stats')">
                        <i class="fas fa-chart-line"></i> آمار و تاریخچه
                    </div>
                    <div class="menu-item" data-section="users" onclick="showLeftContent('users')">
                        <i class="fas fa-users"></i> مدیریت کاربران
                    </div>
                    <div class="menu-item" data-section="companies" onclick="showLeftContent('companies')">
                        <i class="fas fa-building"></i> مدیریت شرکت‌ها
                    </div>
                    <div class="menu-item" data-section="filters" onclick="showLeftContent('filters')">
                        <i class="fas fa-filter"></i> جستجوی اسناد
                    </div>
                    <div class="menu-item" data-section="approvals" onclick="showLeftContent('approvals')">
                        <i class="fas fa-check-double"></i> تاییدات نهایی
                    </div>
                    <div class="menu-item" data-section="archive" onclick="showLeftContent('archive')">
                        <i class="fas fa-archive"></i> بایگانی
                    </div>
                </div>
                
                <div id="userSelectForStats" class="user-select-for-stats">
                    <label><i class="fas fa-user"></i> انتخاب کاربر برای آمار</label>
                    <select id="stats_user_select">
                        <option value="">همه کاربران</option>
                        <?php foreach($users_list as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="adminFiltersPanel" class="admin-filter-box">
                    <div class="admin-filter-group">
                        <label><i class="fas fa-user"></i> انتخاب کاربر</label>
                        <select id="admin_user_select">
                            <option value="">همه کاربران</option>
                            <?php foreach($users_list as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <label><i class="fas fa-hashtag"></i> شماره سند</label>
                        <input type="text" id="admin_filter_number" placeholder="جستجو...">
                    </div>
                    <div class="admin-filter-group">
                        <label><i class="fas fa-calendar"></i> تاریخ سند</label>
                        <input type="text" id="admin_filter_date" placeholder="1404/01/01">
                    </div>
                    <div class="admin-filter-group">
                        <label><i class="fas fa-building"></i> شرکت</label>
                        <select id="admin_filter_company">
                            <option value="">همه شرکت‌ها</option>
                            <?php 
                            $companies_list = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
                            foreach($companies_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-filter-group">
                        <label><i class="fas fa-calendar-check"></i> تاریخ تحویل</label>
                        <input type="text" id="admin_filter_delivery" placeholder="1404/01/01">
                    </div>
                </div>
                
                <?php else: ?>
                <div class="user-panel-buttons">
                    <button class="user-panel-btn active" onclick="toggleUserPanel('form')">📝 ثبت سند جدید</button>
                    <button class="user-panel-btn" onclick="toggleUserPanel('search')">🔍 جستجوی اسناد</button>
                </div>
                
                <div id="userFormPanel" class="user-form-panel">
                    <div class="form-section">
                        <div class="form-group">
                            <label>تاریخ تحویل</label>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <button type="button" id="dateMinus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">−</button>
                                <input type="text" id="delivery_date" readonly style="flex: 1; text-align: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 8px 12px; font-size: 0.9rem; font-weight: 600;">
                                <button type="button" id="datePlus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">+</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="number" id="company_number" min="1" max="21" style="width: 70px; text-align: center; padding: 8px; border: 1.5px solid #e2e8f0; border-radius: 12px;" placeholder="#">
                                <select id="company_id" style="flex: 1;">
                                    <?php foreach($companies as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>شماره سند</label>
                            <input type="text" id="doc_number" placeholder="INV-12345">
                        </div>
                        <div class="form-group" id="date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>>
                            <label>تاریخ سند</label>
                            <input type="text" id="doc_date" placeholder="1405/02/30">
                        </div>
                        <button class="btn-submit" id="submitBtn">✓ ثبت سند</button>
                    </div>
                    
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 0;">
                        <div class="form-group">
                            <label>گزارش / یادداشت</label>
                            <textarea id="doc_description" rows="3" placeholder="هرگونه توضیح یا گزارش اضافی..."></textarea>
                        </div>
                        <button class="btn-green" id="submitDescriptionBtn">✏️ ثبت توضیح</button>
                    </div>
                </div>
                
                <div id="userSearchPanel" class="user-search-panel">
                    <div style="padding: 14px 16px;">
                        <div class="form-group">
                            <label>شماره سند</label>
                            <input type="text" id="filter_number" placeholder="جستجو..." oninput="autoSearchDocuments()">
                        </div>
                        <div class="form-group">
                            <label>تاریخ سند</label>
                            <input type="text" id="filter_date" placeholder="1404/01/01" oninput="autoSearchDocuments()">
                        </div>
                        <div class="form-group">
                            <label>شرکت</label>
                            <select id="filter_company" onchange="autoSearchDocuments()">
                                <option value="">همه شرکت‌ها</option>
                                <?php foreach($companies as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>تاریخ تحویل</label>
                            <input type="text" id="filter_delivery" placeholder="1404/01/01" oninput="autoSearchDocuments()">
                        </div>
                        <button class="btn-secondary" style="width:100%; margin-top:8px;" onclick="resetUserFilters()"><i class="fas fa-undo"></i> نمایش همه</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="left-panel-list">
            <div id="leftContentArea">
                <?php if($is_admin): ?>
                <div id="statsContent" class="left-content">
                    <div class="left-section-title"><i class="fas fa-chart-line"></i> آمار کلی</div>
                    <div id="statsContainer"><div class="empty-state">در حال بارگذاری...</div></div>
                    <div class="left-section-title"><i class="fas fa-history"></i> آخرین اسناد ثبت شده :</div>
                    <div id="recentDocsContainer"><div class="empty-state">در حال بارگذاری...</div></div>
                </div>
                
                <div id="usersContent" class="left-content" style="display:none;">
                    <div class="left-section-title">
                        <i class="fas fa-users"></i> مدیریت کاربران
                        <button class="btn-primary" style="padding:4px 12px; font-size:0.65rem; margin-right:auto;" onclick="showAddUserModal()">+ افزودن کاربر</button>
                    </div>
                    <div class="scrollable-list" id="usersList"><div class="empty-state">در حال بارگذاری...</div></div>
                </div>
                
                <div id="companiesContent" class="left-content" style="display:none;">
                    <div class="left-section-title">
                        <i class="fas fa-building"></i> مدیریت شرکت‌ها
                        <button class="btn-primary" style="padding:4px 12px; font-size:0.65rem; margin-right:auto;" onclick="showAddCompanyModal()">+ افزودن شرکت</button>
                    </div>
                    <div class="companies-grid" id="companiesList">
                        <?php foreach($companies as $c): ?>
                        <div class="company-card">
                            <span class="company-name"><?php echo htmlspecialchars($c['name']); ?></span>
                            <div>
                                <button class="action-btn edit-btn" onclick="editCompany(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['name']); ?>')"><i class="fas fa-edit"></i></button>
                                <button class="action-btn delete-btn" onclick="toggleCompany(<?php echo $c['id']; ?>, 0)"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div id="filtersContent" class="left-content" style="display:none;">
                    <div class="left-section-title"><i class="fas fa-list-ul"></i> نتیجه جستجو</div>
                    <div id="adminDocumentsList" class="docs-list"><div class="empty-state">برای جستجو از فیلدهای سمت راست استفاده کنید</div></div>
                </div>
                
                <div id="approvalsContent" class="left-content" style="display:none;">
                    <div class="left-section-title">
                        <i class="fas fa-check-double"></i> تایید نهایی اسناد
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                        <button class="tab-btn active" onclick="showApprovalsTab('pending')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#667eea; border-bottom:2px solid #667eea;">⏳ در انتظار تایید</button>
                        <button class="tab-btn" onclick="showApprovalsTab('revert')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#475569;">🔄 درخواست بازیابی</button>
                        <button class="tab-btn" onclick="showApprovalsTab('approved')" style="background:none; border:none; padding:8px 16px; cursor:pointer; font-weight:500; color:#475569;">✅ تایید شده‌ها</button>
                    </div>
                    
                    <div id="pendingApprovalsTab">
                        <div id="usersPendingList"><div class="empty-state">در حال بارگذاری...</div></div>
                        <div id="userDatesContainer" style="display:none; margin-top: 20px;">
                            <div class="left-section-title">
                                <i class="fas fa-calendar"></i> تاریخ‌های تحویل
                                <button class="btn-secondary" onclick="backToUsersList()" style="margin-right:auto; padding:4px 12px;">← بازگشت</button>
                            </div>
                            <div id="userDatesList"></div>
                        </div>
                    </div>
                    
                    <div id="revertRequestsTab" style="display:none;">
                        <div id="revertRequestsList"><div class="empty-state">در حال بارگذاری...</div></div>
                    </div>
                    
                    <div id="approvedApprovalsTab" style="display:none;">
                        <div id="approvedApprovalsList"><div class="empty-state">در حال بارگذاری...</div></div>
                    </div>
                </div>
                
                <div id="archiveContent" class="left-content" style="display:none;">
                    <div class="left-section-title">
                        <i class="fas fa-archive"></i> بایگانی اسناد تایید شده
                    </div>
                    <div id="archiveList"><div class="empty-state">در حال بارگذاری...</div></div>
                </div>
                
                <?php else: ?>
                <div id="userDocsContainer">
                    <div class="docs-list" id="documents_list"><div class="empty-state">در حال بارگذاری...</div></div>
                </div>
                <?php endif; ?>
            </div>
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
    if (raw.length === 0) {
        input.value = '';
        return;
    }
    if (raw.length > 8) raw = raw.slice(0, 8);
    if (raw.length <= 4) {
        input.value = raw;
    } else if (raw.length <= 6) {
        input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6);
    } else {
        input.value = raw.slice(0, 4) + '/' + raw.slice(4, 6) + '/' + raw.slice(6, 8);
    }
}

let docDateInput = document.getElementById('doc_date');
if (docDateInput) {
    docDateInput.addEventListener('input', function(e) {
        formatJalali(this);
    });
    docDateInput.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' || e.key === 'Delete') {
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('submitBtn').focus();
        }
    });
}

// ========== کاربر عادی ==========
<?php if(!$is_admin): ?>

document.addEventListener('DOMContentLoaded', function() {
    function getDaysInMonth(year, month) {
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
        if (dateInput) {
            dateInput.value = deliveryYear + '/' + monthStr + '/' + dayStr;
        }
    }

    function addDaysToDelivery(days) {
        deliveryDay += days;
        let daysInMonth = getDaysInMonth(deliveryYear, deliveryMonth);
        if (deliveryDay > daysInMonth) {
            deliveryDay = 1;
            deliveryMonth++;
            if (deliveryMonth > 12) {
                deliveryMonth = 1;
                deliveryYear++;
            }
        }
        if (deliveryDay < 1) {
            deliveryMonth--;
            if (deliveryMonth < 1) {
                deliveryMonth = 12;
                deliveryYear--;
            }
            deliveryDay = getDaysInMonth(deliveryYear, deliveryMonth);
        }
        updateDeliveryDate();
    }

    let initialDelivery = '<?php echo $today; ?>'.split('/');
    if (initialDelivery.length === 3) {
        deliveryYear = parseInt(initialDelivery[0]);
        deliveryMonth = parseInt(initialDelivery[1]);
        deliveryDay = parseInt(initialDelivery[2]);
    }
    updateDeliveryDate();

    let minusBtn = document.getElementById('dateMinus');
    let plusBtn = document.getElementById('datePlus');
    if (minusBtn) minusBtn.addEventListener('click', function() { addDaysToDelivery(-1); });
    if (plusBtn) plusBtn.addEventListener('click', function() { addDaysToDelivery(1); });
    // ========== اضافه کردن رویداد برای دکمه ثبت توضیح ==========
    const descBtn = document.getElementById('submitDescriptionBtn');
    if (descBtn) {
        descBtn.addEventListener('click', function(e) {
            e.preventDefault();
            saveReport();
        });
    }
});
// ========== انتخاب خودکار شرکت با شماره ==========
const companyNumberInput = document.getElementById('company_number');
const companySelect = document.getElementById('company_id');

if (companyNumberInput && companySelect) {
    // گرفتن لیست شرکت‌ها از PHP
    const companiesList = <?php 
        $list = [];
        foreach($companies as $c) {
            $list[] = ['id' => $c['id'], 'name' => $c['name']];
        }
        echo json_encode($list); 
    ?>;
    
    companyNumberInput.addEventListener('input', function() {
        let num = parseInt(this.value);
        if (isNaN(num)) return;
        
        if (num > companiesList.length) {
            num = companiesList.length;
        }
        if (num < 1) num = 1;
        
        const selectedCompany = companiesList[num - 1];
        if (selectedCompany) {
            companySelect.value = selectedCompany.id;
        }
    });
    
    companyNumberInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('doc_number').focus();
        }
    });
}

async function revertApproval(deliveryDate) {
    if (!confirm('آیا از لغو امضای خود اطمینان دارید؟ پس از بازیابی می‌توانید اسناد را ویرایش کنید.')) return;
    
    let res = await fetch(`${apiUrl}?action=revert_approval&delivery_date=${encodeURIComponent(deliveryDate)}`, { method: 'POST' });
    let result = await res.json();
    
    if (result.success) {
        showToast('✅ امضا لغو شد. اکنون می‌توانید اسناد را ویرایش کنید');
        location.reload();
    } else {
        showToast(result.error || '❌ خطا در بازیابی', true);
    }
}

async function requestRevert() {
    if (!confirm('اسناد شما تایید نهایی شده است. آیا از ادمین درخواست بازیابی می‌کنید؟')) return;
    
    let res = await fetch(`${apiUrl}?action=request_revert`, { method: 'POST' });
    let result = await res.json();
    if (result.success) {
        showToast('درخواست بازیابی برای ادمین ارسال شد');
        location.reload();
    } else {
        showToast(result.error || 'خطا در ارسال درخواست', true);
    }
}

document.getElementById('revertApprovalBtn')?.addEventListener('click', revertApproval);

let currentPanel = 'form';



async function loadLastDeliveryDate() {
    try {
        let res = await fetch(`${apiUrl}?action=get_last_delivery_date`);
        let text = await res.text();
        
        // بررسی اگر پاسخ HTML است
        if (text.trim().startsWith('<')) {
            console.error("Server returned HTML:", text.substring(0, 200));
            let today = '<?php echo $today; ?>';
            currentDeliveryDate = today;
            document.getElementById('delivery_date').value = today;
            await loadDocumentsForDisplay(today);
            return;
        }
        
        let data = JSON.parse(text);
        if (data.success && data.last_date) {
            currentDeliveryDate = data.last_date;
            document.getElementById('delivery_date').value = currentDeliveryDate;
            await loadDocumentsForDisplay(currentDeliveryDate);
        } else {
            let today = '<?php echo $today; ?>';
            currentDeliveryDate = today;
            document.getElementById('delivery_date').value = today;
            await loadDocumentsForDisplay(today);
        }
    } catch(e) { 
        console.error(e);
        let today = '<?php echo $today; ?>';
        currentDeliveryDate = today;
        document.getElementById('delivery_date').value = today;
        await loadDocumentsForDisplay(today);
    }
}

async function loadDocumentsForDisplay(deliveryDate) {
    if (!deliveryDate) return;
    currentDeliveryDate = deliveryDate;
    try {
        let res = await fetch(`${apiUrl}?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`);
        let data = await res.json();
        let container = document.getElementById('documents_list');
        if (data.success && data.documents && data.documents.length > 0) {
            // ساخت دکمه بازیابی در صورت نیاز
            let revertButton = '';
            if (data.has_user_approved && !data.has_admin_approved) {
                revertButton = `<button class="print-btn" onclick="revertApproval()" style="background:#f59e0b;"><i class="fas fa-undo"></i> بازیابی</button>`;
            } else if (data.has_user_approved && data.has_admin_approved) {
                revertButton = `<button class="print-btn" onclick="requestRevert()" style="background:#ef4444;"><i class="fas fa-envelope"></i> درخواست بازیابی</button>`;
            }
            
            let html = `<div class="doc-group"><div class="group-title"><div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(deliveryDate)} <span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${data.documents.length} سند</span></div><div style="display:flex; gap:8px;"><a href="print.php?delivery_date=${encodeURIComponent(deliveryDate)}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a>${revertButton}</div></div><div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead><tbody>`;
            for (let i = 0; i < data.documents.length; i++) {
                let doc = data.documents[i];
                let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                let actions = '';
                if (doc.can_edit) {
                    actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                }
                html += `<tr><td>${i+1}</td><td>${escapeHtml(doc.doc_number)}</td><td>${docDate}</td><td>${escapeHtml(doc.company_name)}</td><td>${actions}</td></tr>`;
            }
            html += `</tbody></table></div>`;
            let descriptions = data.documents.filter(d => d.description && d.description.trim() !== '');
            if (descriptions.length > 0) {
                html += `<div class="descriptions-section"><div class="desc-title"><i class="fas fa-comment-dots"></i> توضیحات اسناد</div>`;
                for (let desc of descriptions) {
                    html += `<div class="desc-item"><strong>${escapeHtml(desc.doc_number)}:</strong> ${escapeHtml(desc.description.substring(0, 100))}${desc.description.length > 100 ? '...' : ''}</div>`;
                }
                html += `</div>`;
            }
            html += `</div>`;
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سندی در این تاریخ وجود ندارد</div>';
        }
    } catch(e) { console.error(e); }
}

async function submitDocument() {
    let delivery_date = document.getElementById('delivery_date').value;
    let company_id = document.getElementById('company_id').value;
    let doc_number = document.getElementById('doc_number').value.trim();
    let doc_date = document.getElementById('doc_date')?.value.trim() || '';
    if (!doc_number) { showToast('شماره سند الزامی است', true); return; }
    if (requireDocDate && !doc_date) { showToast('تاریخ سند الزامی است', true); return; }
    let res = await fetch(`${apiUrl}?action=save_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({delivery_date, company_id, doc_number, doc_date: doc_date || '-', description: ''}) });
    let result = await res.json();
    if (result.success) { 
        showToast('سند با موفقیت ثبت شد'); 
        document.getElementById('doc_number').value = ''; 
        if (requireDocDate) document.getElementById('doc_date').value = ''; 
        await loadDocumentsForDisplay(delivery_date);
        document.getElementById('doc_number').focus();
    } else showToast(result.error || 'خطا', true);
}

async function saveReport() {
    let reportText = document.getElementById('doc_description')?.value.trim() || '';
    
    if (!reportText) {
        showToast('لطفاً متن توضیحات را وارد کنید', true);
        return;
    }
    
    let btn = document.getElementById('submitDescriptionBtn');
    let originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ذخیره...';
    btn.disabled = true;
    
    try {
        let res = await fetch(`${apiUrl}?action=save_report`, { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'}, 
            body: JSON.stringify({report: reportText})
        });
        let result = await res.json();
        
        if (result.success) { 
            showToast('✅ توضیحات با موفقیت ذخیره شد');
            document.getElementById('doc_description').value = '';
        } else { 
            showToast('❌ خطا: ' + (result.error || 'خطا در ذخیره توضیحات'), true); 
        }
    } catch(e) {
        console.error(e);
        showToast('❌ خطا در ارتباط با سرور', true);
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// ========== فوکوس اولیه روی شماره شرکت ==========
if (document.getElementById('company_number')) {
    document.getElementById('company_number').focus();
} else {
    document.getElementById('company_id').focus();
}

// ========== جابجایی با Enter بین فیلدها ==========
// فیلد 1: شماره شرکت → شماره سند
var companyNumberElem = document.getElementById('company_number');
if (companyNumberElem) {
    companyNumberElem.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('doc_number').focus();
        }
    });
}

// فیلد 2: شماره سند → تاریخ سند (اگر اجباری باشد) یا برگشت به شماره شرکت
document.getElementById('doc_number').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (requireDocDate && document.getElementById('doc_date')) {
            document.getElementById('doc_date').focus();
        } else {
            if (document.getElementById('company_number')) {
                document.getElementById('company_number').focus();
            } else {
                document.getElementById('company_id').focus();
            }
        }
    }
});

// فیلد 3: تاریخ سند (اگر اجباری باشد) → برگشت به شماره شرکت
if (requireDocDate) {
    document.getElementById('doc_date').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (document.getElementById('company_number')) {
                document.getElementById('company_number').focus();
            } else {
                document.getElementById('company_id').focus();
            }
        }
    });
}

// جلوگیری از فوکوس روی select شرکت با Enter
var companySelectElem = document.getElementById('company_id');
if (companySelectElem) {
    companySelectElem.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('doc_number').focus();
        }
    });
}

// جلوگیری از فوکوس روی دکمه ثبت
document.getElementById('submitBtn').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        return false;
    }
});

// ثبت فقط با Shift+Enter
document.addEventListener('keydown', async function(e) {
    if (e.key === 'Enter' && e.shiftKey) {
        e.preventDefault();
        if (currentPanel === 'form') {
            await submitDocument();
        }
    }
});

// رویداد کلیک دکمه ثبت سند
document.getElementById('submitBtn').addEventListener('click', function() {
    submitDocument();
    setTimeout(function() {
        document.getElementById('doc_number').focus();
    }, 100);
});

loadLastDeliveryDate();

window.openEditModal = function(id, number, date, description) {
    console.log("Edit Modal Called - ID:", id, "Number:", number, "Date:", date);
    
    let editId = document.getElementById('edit_id');
    let editNumber = document.getElementById('edit_number');
    let editDate = document.getElementById('edit_date');
    
    if (editId) editId.value = id;
    if (editNumber) editNumber.value = number;
    if (editDate) editDate.value = (date === '-' ? '' : date);
    
    let modal = document.getElementById('editModal');
    if (modal) modal.classList.add('active');
}
window.closeEditModal = function() { document.getElementById('editModal').classList.remove('active'); }
window.saveEdit = async function() {
    let id = document.getElementById('edit_id').value;
    let number = document.getElementById('edit_number').value.trim();
    let date = document.getElementById('edit_date').value.trim();
    
    console.log("Save Edit - ID:", id, "Number:", number, "Date:", date);
    
    if (!id) {
        showToast('شناسه سند یافت نشد', true);
        return;
    }
    if (!number) {
        showToast('شماره سند الزامی است', true);
        return;
    }
    
    let res = await fetch(`${apiUrl}?action=update_document`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            doc_number: number,
            doc_date: date || '-'
        })
    });
    
    let result = await res.json();
    if (result.success) {
        showToast('ویرایش شد');
        closeEditModal();
        await loadDocumentsForDisplay(currentDeliveryDate);
    } else {
        showToast(result.error || 'خطا در ویرایش', true);
    }
}
window.deleteDocument = async function(id) {
    if (!confirm('حذف شود؟')) return;
    let res = await fetch(`${apiUrl}?action=delete_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
    let result = await res.json();
    if (result.success) { showToast('حذف شد'); await loadDocumentsForDisplay(currentDeliveryDate); }
}

function toggleUserPanel(panel) {
    currentPanel = panel;
    let formPanel = document.getElementById('userFormPanel');
    let searchPanel = document.getElementById('userSearchPanel');
    let btns = document.querySelectorAll('.user-panel-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    if (panel === 'form') {
        formPanel.style.display = 'block';
        searchPanel.style.display = 'none';
        btns[0].classList.add('active');
        loadLastDeliveryDate();
        document.getElementById('company_id').focus();
    } else {
        formPanel.style.display = 'none';
        searchPanel.style.display = 'block';
        btns[1].classList.add('active');
        loadSearchDocuments();
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
                html += `<div class="doc-group"><div class="group-title"><div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(group.delivery_date)} <span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${group.count} سند</span></div><a href="${printUrl}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a></div><div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead><tbody>`;
                for (let doc of group.documents) {
                    let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                    let actions = '';
                    if (doc.can_edit) {
                        actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                    }
                    html += `<tr><td>${doc.row_num}</td><td>${escapeHtml(doc.doc_number)}</td><td>${docDate}</td><td>${escapeHtml(doc.company_name)}</td><td>${actions}</td></tr>`;
                }
                html += `</tbody></table></div>`;
                let descriptions = group.documents.filter(d => d.description && d.description.trim() !== '');
                if (descriptions.length > 0) {
                    html += `<div class="descriptions-section"><div class="desc-title"><i class="fas fa-comment-dots"></i> توضیحات اسناد</div>`;
                    for (let desc of descriptions) {
                        html += `<div class="desc-item"><strong>${escapeHtml(desc.doc_number)}:</strong> ${escapeHtml(desc.description.substring(0, 100))}${desc.description.length > 100 ? '...' : ''}</div>`;
                    }
                    html += `</div>`;
                }
                html += `</div>`;
            }
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
        }
    } catch(e) { console.error(e); }
}

function autoSearchDocuments() { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => loadSearchDocuments(), 400); }
function resetUserFilters() {
    document.getElementById('filter_number').value = '';
    document.getElementById('filter_date').value = '';
    document.getElementById('filter_company').value = '';
    document.getElementById('filter_delivery').value = '';
    loadSearchDocuments();
}
function showChangePasswordModal() { document.getElementById('passwordModal').classList.add('active'); }
function closePasswordModal() { document.getElementById('passwordModal').classList.remove('active'); }

async function changePassword() {
    let old_pass = document.getElementById('old_password').value;
    let new_pass = document.getElementById('new_password').value;
    let confirm_pass = document.getElementById('confirm_password').value;
    if (!old_pass || !new_pass) { showToast('تمامی فیلدها را پر کنید', true); return; }
    if (new_pass !== confirm_pass) { showToast('رمز جدید مطابقت ندارد', true); return; }
    let res = await fetch(`${apiUrl}?action=change_password`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({old_password: old_pass, new_password: new_pass}) });
    let result = await res.json();
    if (result.success) { showToast('رمز تغییر کرد'); closePasswordModal(); document.getElementById('old_password').value = ''; document.getElementById('new_password').value = ''; document.getElementById('confirm_password').value = ''; }
    else { showToast(result.error || 'خطا', true); }
}

<?php else: ?>
// ========== ادمین ==========

let currentAdminUserId = '';

async function loadAdminLastDocuments() {
    let userId = document.getElementById('stats_user_select')?.value || '';
    currentAdminUserId = userId;
    
    try {
        let res = await fetch(`${apiUrl}?action=get_admin_stats&user_id=${userId}`);
        let data = await res.json();
        if (data.success) {
            let statsHtml = `<div class="stats-grid">
                <div class="stat-card"><div class="stat-value">${data.total_docs}</div><div class="stat-label">کل اسناد</div></div>
                <div class="stat-card"><div class="stat-value">${data.total_users}</div><div class="stat-label">کاربران</div></div>
                <div class="stat-card"><div class="stat-value">${data.total_companies}</div><div class="stat-label">شرکت‌ها</div></div>
                <div class="stat-card"><div class="stat-value">${data.today_count}</div><div class="stat-label">اسناد امروز</div></div>
            </div>
            <div class="stat-card"><div class="stat-small">📅 از ${data.first_date} تا ${data.today_date}</div>
            <div class="stat-small">📌 آخرین ثبت: ${data.last_date}</div></div>`;
            document.getElementById('statsContainer').innerHTML = statsHtml;
            
            if (data.recent_docs && data.recent_docs.length > 0) {
                let html = `<div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>کاربر</th>
                            </tr>
                        </thead>
                        <tbody>`;
                for (let i = 0; i < data.recent_docs.length; i++) {
                    let doc = data.recent_docs[i];
                    html += `<tr><td>${i+1}</td><td>${escapeHtml(doc.doc_number)}</td><td>${doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date)}</td><td>${escapeHtml(doc.company_name)}</td><td>${escapeHtml(doc.user_name || '')}</td></tr>`;
                }
                html += `</tbody></table></div>`;
                document.getElementById('recentDocsContainer').innerHTML = html;
            } else {
                document.getElementById('recentDocsContainer').innerHTML = '<div class="empty-state">هیچ سندی ثبت نشده است</div>';
            }
        }
    } catch(e) { console.error(e); }
}

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
            let html = `<div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>تاریخ تحویل</th><th>شرکت</th><th>کاربر</th><th>عملیات</th></tr></thead><tbody>`;
            for (let i = 0; i < data.documents.length; i++) {
                let doc = data.documents[i];
                let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                html += `<tr>
                    <td>${i+1}</td>
                    <td>${escapeHtml(doc.doc_number)}</td>
                    <td>${docDate}</td>
                    <td>${escapeHtml(doc.delivery_date)}</td>
                    <td>${escapeHtml(doc.company_name)}</td>
                    <td>${escapeHtml(doc.user_fullname)}</td>
                    <td>
                        <button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button>
                        <button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>`;
            }
            html += `</tbody></table></div>`;
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سندی یافت نشد</div>';
        }
    } catch(e) { console.error(e); }
}

// ========== لیست کاربران دارای تایید pending ==========
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
                html += `
                    <div class="user-pending-card" style="background:#f8fafc; border-radius:12px; padding:15px; cursor:pointer; transition:all 0.2s; border:1px solid #e2e8f0;" onclick="loadUserPendingDates(${user.user_id}, '${escapeHtml(user.fullname)}')">
                        <div style="font-weight:bold; font-size:0.85rem;">${escapeHtml(user.fullname)}</div>
                        <div style="font-size:0.65rem; color:#6c86a3; margin-top:5px;">${escapeHtml(user.unit_name)}</div>
                        <div style="margin-top:8px;"><span style="background:#f59e0b; color:white; padding:2px 8px; border-radius:20px; font-size:0.6rem;">در انتظار تایید</span></div>
                    </div>
                `;
            }
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">✅ هیچ درخواست تایید جدیدی وجود ندارد</div>';
        }
    } catch(e) {
        console.error(e);
        container.innerHTML = '<div class="empty-state">خطا در بارگذاری اطلاعات</div>';
    }
}

// ========== بارگذاری درخواست‌های بازیابی ==========
async function loadRevertRequests() {
    let container = document.getElementById('revertRequestsList');
    if (!container) return;
    
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    
    try {
        let res = await fetch(`${apiUrl}?action=get_revert_requests`);
        let data = await res.json();
        
        if (data.success && data.requests && data.requests.length > 0) {
            let html = '<div style="overflow-x:auto;"><table class="data-table"><thead><tr>';
            html += '<th>کاربر</th><th>واحد</th><th>تاریخ درخواست</th><th>عملیات</th>';
            html += '</thead><tbody>';
            
            for (let item of data.requests) {
                html += `<tr>
                    <td>${escapeHtml(item.fullname)}</td>
                    <td>${escapeHtml(item.unit_name)}</td>
                    <td>${escapeHtml(item.requested_at)}</td>
                    <td>
                        <button class="action-btn edit-btn" onclick="approveRevert(${item.user_id})" style="color:#10b981;">✅ تایید</button>
                        <button class="action-btn delete-btn" onclick="rejectRevert(${item.user_id})" style="color:#ef4444;">❌ رد</button>
                    </td>
                </tr>`;
            }
            html += '</tbody>}</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">✅ هیچ درخواست بازیابی جدیدی وجود ندارد</div>';
        }
    } catch(e) {
        console.error(e);
        container.innerHTML = '<div class="empty-state">خطا در بارگذاری اطلاعات</div>';
    }
}

async function loadApprovedApprovals() {
    let container = document.getElementById('approvedApprovalsList');
    if (!container) return;
    
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    
    try {
        let res = await fetch(`${apiUrl}?action=get_approved_documents`);
        let data = await res.json();
        
        if (data.success && data.approvals && data.approvals.length > 0) {
            let html = '<div style="overflow-x:auto;"><table class="data-table"><thead><tr>';
            html += '<th>کاربر</th><th>واحد</th><th>تاریخ تایید ادمین</th><th>عملیات</th>';
            html += '</thead><tbody>';
            
            for (let item of data.approvals) {
                let adminDate = item.admin_approved_at_fa || item.admin_approved_at || '-';
                
                html += `<tr>
                    <td>${escapeHtml(item.fullname)}</td>
                    <td>${escapeHtml(item.unit_name)}</td>
                    <td dir="ltr">${escapeHtml(adminDate)}</td>
                    <td>
                        <a href="print.php?user_id=${item.user_id}&delivery_date=${encodeURIComponent(item.delivery_date)}" class="btn-primary" style="padding:4px 12px; text-decoration:none; font-size:0.65rem;">مشاهده</a>
                    </td>
                </tr>`;
            }
            html += '</tbody>}</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای وجود ندارد</div>';
        }
    } catch(e) {
        console.error(e);
        container.innerHTML = '<div class="empty-state">خطا در بارگذاری اطلاعات</div>';
    }
}

// ========== تابع بارگذاری بایگانی تایید شده‌ها ==========
async function loadArchive() {
    let container = document.getElementById('archiveList');
    if (!container) return;
    
    container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    
    try {
        let res = await fetch(`${apiUrl}?action=get_approved_documents`);
        let data = await res.json();
        
        if (data.success && data.approvals && data.approvals.length > 0) {
            let html = '<div style="overflow-x:auto;"><table class="data-table"><thead><tr>';
            html += '<th>کاربر</th><th>واحد</th><th>تاریخ تایید کاربر</th><th>تاریخ تایید ادمین</th><th>تعداد اسناد</th><th>عملیات</th>';
            html += '</thead><tbody>';
            
            for (let item of data.approvals) {
                // استفاده از تاریخ‌های شمسی
                let userDate = item.user_approved_at_fa || item.user_approved_at || '-';
                let adminDate = item.admin_approved_at_fa || item.admin_approved_at || '-';
                
                html += `<tr>
                    <td>${escapeHtml(item.fullname)}</td>
                    <td>${escapeHtml(item.unit_name)}</td>
                    <td dir="ltr">${escapeHtml(userDate)}</td>
                    <td dir="ltr">${escapeHtml(adminDate)}</td>
                    <td>${item.total_docs} عدد</td>
                    <td>
                        <a href="print.php?user_id=${item.user_id}&delivery_date=${encodeURIComponent(item.delivery_date)}" class="btn-primary" style="padding:4px 12px; text-decoration:none; font-size:0.65rem; display:inline-block; margin:2px;">مشاهده</a>
                        <button class="action-btn" onclick="downloadArchivePDF(${item.user_id}, '${item.delivery_date}')" style="color:#10b981;">📄 PDF</button>
                    </td>
                </tr>`;
            }
            html += '</tbody>}</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state">هیچ سند تایید شده‌ای وجود ندارد</div>';
        }
    } catch(e) {
        console.error(e);
        container.innerHTML = '<div class="empty-state">خطا در بارگذاری اطلاعات</div>';
    }
}

function downloadArchivePDF(userId, deliveryDate) {
    window.open(`print.php?user_id=${userId}&delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
}

async function approveRevert(userId) {
    if (!confirm('آیا از تایید بازیابی این کاربر اطمینان دارید؟ پس از تایید، کاربر می‌تواند اسناد خود را ویرایش کند.')) return;
    
    let res = await fetch(`${apiUrl}?action=approve_revert&user_id=${userId}`);
    let data = await res.json();
    if (data.success) {
        showToast('بازیابی تایید شد');
        loadRevertRequests();
    } else {
        showToast('خطا در تایید بازیابی', true);
    }
}

async function rejectRevert(userId) {
    if (!confirm('آیا از رد بازیابی این کاربر اطمینان دارید؟')) return;
    
    let res = await fetch(`${apiUrl}?action=reject_revert&user_id=${userId}`);
    let data = await res.json();
    if (data.success) {
        showToast('درخواست بازیابی رد شد');
        loadRevertRequests();
    } else {
        showToast('خطا در رد بازیابی', true);
    }
}

async function loadUserPendingDates(userId, userName) {
    let usersContainer = document.getElementById('usersPendingList');
    let datesContainer = document.getElementById('userDatesContainer');
    let datesList = document.getElementById('userDatesList');
    
    if (!usersContainer || !datesContainer || !datesList) return;
    
    usersContainer.style.display = 'none';
    datesContainer.style.display = 'block';
    
    datesList.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> در حال بارگذاری...</div>';
    
    try {
        let res = await fetch(`${apiUrl}?action=get_user_pending_dates&user_id=${userId}`);
        let data = await res.json();
        
        if (data.success && data.dates && data.dates.length > 0) {
            let html = `<div style="margin-bottom:15px;"><strong>کاربر: ${escapeHtml(userName)}</strong></div>`;
            html += '<div class="dates-grid" style="display:flex; flex-direction:column; gap:10px;">';
            for (let item of data.dates) {
                // اصلاح: اضافه کردن delivery_date به لینک
                let printUrl = `print.php?user_id=${userId}&delivery_date=${encodeURIComponent(item.delivery_date)}`;
                html += `
                    <div style="background:#f8fafc; border-radius:12px; padding:12px 15px; display:flex; justify-content:space-between; align-items:center; border:1px solid #e2e8f0;">
                        <div>
                            <div style="font-weight:bold; font-size:0.85rem;">📅 ${escapeHtml(item.delivery_date)}</div>
                            <div style="font-size:0.65rem; color:#6c86a3;">تاریخ ثبت: ${escapeHtml(item.user_approved_at)}</div>
                        </div>
                        <a href="${printUrl}" class="btn-primary" style="padding:8px 16px; text-decoration:none; font-size:0.7rem;">تایید نهایی</a>
                    </div>
                `;
            }
            html += '</div>';
            datesList.innerHTML = html;
        } else {
            datesList.innerHTML = '<div class="empty-state">هیچ تاریخ تایید نشده‌ای برای این کاربر وجود ندارد</div>';
        }
    } catch(e) {
        console.error(e);
        datesList.innerHTML = '<div class="empty-state">خطا در بارگذاری اطلاعات</div>';
    }
}

function backToUsersList() {
    let usersContainer = document.getElementById('usersPendingList');
    let datesContainer = document.getElementById('userDatesContainer');
    if (usersContainer && datesContainer) {
        usersContainer.style.display = 'block';
        datesContainer.style.display = 'none';
        loadUsersPendingList();
    }
}

// ========== تب‌های تاییدات نهایی ==========
let currentApprovalsTab = 'pending';

function showApprovalsTab(tab) {
    currentApprovalsTab = tab;
    
    // مخفی کردن همه تب‌ها
    document.getElementById('pendingApprovalsTab').style.display = 'none';
    document.getElementById('revertRequestsTab').style.display = 'none';
    document.getElementById('approvedApprovalsTab').style.display = 'none';
    
    // تغییر استایل دکمه‌ها
    let btns = document.querySelectorAll('.tab-btn');
    btns.forEach((btn, index) => {
        btn.style.color = '#475569';
        btn.style.borderBottom = 'none';
        if ((tab === 'pending' && index === 0) ||
            (tab === 'revert' && index === 1) ||
            (tab === 'approved' && index === 2)) {
            btn.style.color = '#667eea';
            btn.style.borderBottom = '2px solid #667eea';
        }
    });
    
    if (tab === 'pending') {
        document.getElementById('pendingApprovalsTab').style.display = 'block';
        loadUsersPendingList();
    } else if (tab === 'revert') {
        document.getElementById('revertRequestsTab').style.display = 'block';
        loadRevertRequests();
    } else if (tab === 'approved') {
        document.getElementById('approvedApprovalsTab').style.display = 'block';
        loadApprovedApprovals();
    }
}

function showLeftContent(section) {
    document.querySelectorAll('.menu-item').forEach((item) => item.classList.remove('active'));
    document.querySelector(`.menu-item[data-section="${section}"]`).classList.add('active');
    
    // مخفی کردن همه بخش‌ها
    document.getElementById('statsContent').style.display = 'none';
    document.getElementById('usersContent').style.display = 'none';
    document.getElementById('companiesContent').style.display = 'none';
    document.getElementById('filtersContent').style.display = 'none';
    document.getElementById('approvalsContent').style.display = 'none';
    document.getElementById('archiveContent').style.display = 'none';
    
    let userSelectDiv = document.getElementById('userSelectForStats');
    let filterPanel = document.getElementById('adminFiltersPanel');
    
    if (section === 'filters') {
        filterPanel.classList.add('visible');
        if (userSelectDiv) userSelectDiv.style.display = 'none';
        document.getElementById('filtersContent').style.display = 'block';
        searchAdminDocuments();
        setupAdminAutoSearch();
    } 
    else if (section === 'approvals') {
        filterPanel.classList.remove('visible');
        if (userSelectDiv) userSelectDiv.style.display = 'none';
        document.getElementById('approvalsContent').style.display = 'block';
        showApprovalsTab('pending');
    }
    else if (section === 'archive') {
        filterPanel.classList.remove('visible');
        if (userSelectDiv) userSelectDiv.style.display = 'none';
        document.getElementById('archiveContent').style.display = 'block';
        loadArchive();
    }
    else {
        filterPanel.classList.remove('visible');
        if (section === 'stats') {
            if (userSelectDiv) userSelectDiv.style.display = 'block';
            document.getElementById('statsContent').style.display = 'block';
            loadAdminLastDocuments();
        } else {
            if (userSelectDiv) userSelectDiv.style.display = 'none';
            if (section === 'users') {
                document.getElementById('usersContent').style.display = 'block';
                loadUsersList();
            } else if (section === 'companies') {
                document.getElementById('companiesContent').style.display = 'block';
            }
        }
    }
}

function setupAdminAutoSearch() {
    let inputs = ['admin_filter_number', 'admin_filter_date', 'admin_filter_company', 'admin_filter_delivery', 'admin_user_select'];
    inputs.forEach(id => {
        let el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => searchAdminDocuments(), 400);
            });
            if (el.tagName === 'SELECT') el.addEventListener('change', () => searchAdminDocuments());
        }
    });
}

async function loadUsersList() {
    let res = await fetch(`${apiUrl}?action=get_users`);
    let data = await res.json();
    let container = document.getElementById('usersList');
    if (data.success && data.users) {
        let html = '';
        for (let u of data.users) {
            html += `<div class="user-item"><div class="user-info"><div class="user-name">${escapeHtml(u.fullname)}</div><div class="user-unit">${escapeHtml(u.unit_name)} | 📄 ${u.total_docs} سند</div></div><div><button class="action-btn edit-btn" onclick="editUser(${u.id}, '${escapeHtml(u.username)}', '${escapeHtml(u.fullname)}', '${escapeHtml(u.unit_name)}', ${u.require_doc_date})"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteUser(${u.id})"><i class="fas fa-trash-alt"></i></button></div></div>`;
        }
        container.innerHTML = html;
    }
}

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
    if (!username || !fullname || !unit) { showToast('لطفاً تمام فیلدها را پر کنید', true); return; }
    let url = id ? `${apiUrl}?action=update_user` : `${apiUrl}?action=add_user`;
    let res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, username, fullname, unit_name: unit, password, require_doc_date: requireDate}) });
    let result = await res.json();
    if (result.success) { showToast(id ? 'کاربر ویرایش شد' : 'کاربر اضافه شد'); closeUserModal(); loadUsersList(); setTimeout(() => location.reload(), 1000); }
    else showToast(result.error || 'خطا', true);
}

async function deleteUser(id) {
    if (!confirm('آیا از حذف این کاربر مطمئن هستید؟')) return;
    let res = await fetch(`${apiUrl}?action=delete_user`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
    let result = await res.json();
    if (result.success) { showToast('کاربر حذف شد'); loadUsersList(); setTimeout(() => location.reload(), 1000); }
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
    let res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, name}) });
    let result = await res.json();
    if (result.success) { showToast(id ? 'شرکت ویرایش شد' : 'شرکت اضافه شد'); closeCompanyModal(); location.reload(); }
}

async function toggleCompany(id, isActive) {
    if (!confirm('غیرفعال کردن شرکت؟')) return;
    let res = await fetch(`${apiUrl}?action=toggle_company`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id, is_active: isActive}) });
    let result = await res.json();
    if (result.success) { showToast('وضعیت شرکت تغییر کرد'); location.reload(); }
}

function closeUserModal() { document.getElementById('userModal').classList.remove('active'); }
function closeCompanyModal() { document.getElementById('companyModal').classList.remove('active'); }
function showChangePasswordModal() { document.getElementById('passwordModal').classList.add('active'); }
function closePasswordModal() { document.getElementById('passwordModal').classList.remove('active'); }

async function changePassword() {
    let old_pass = document.getElementById('old_password').value;
    let new_pass = document.getElementById('new_password').value;
    let confirm_pass = document.getElementById('confirm_password').value;
    if (!old_pass || !new_pass) { showToast('تمامی فیلدها را پر کنید', true); return; }
    if (new_pass !== confirm_pass) { showToast('رمز جدید مطابقت ندارد', true); return; }
    let res = await fetch(`${apiUrl}?action=change_password`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({old_password: old_pass, new_password: new_pass}) });
    let result = await res.json();
    if (result.success) { showToast('رمز تغییر کرد'); closePasswordModal(); }
    else showToast(result.error || 'خطا', true);
}

window.openEditModal = function(id, companyId, number, date) {
    let idElem = document.getElementById('edit_id');
    let companyElem = document.getElementById('edit_company_id');
    let numberElem = document.getElementById('edit_number');
    let dateElem = document.getElementById('edit_date');
    
    if (idElem) idElem.value = id;
    if (companyElem) companyElem.value = companyId;
    if (numberElem) numberElem.value = number;
    if (dateElem) dateElem.value = (date === '-' ? '' : date);
    
    document.getElementById('editModal').classList.add('active');
}
window.closeEditModal = function() { document.getElementById('editModal').classList.remove('active'); }
window.saveEdit = async function() {
    let id = document.getElementById('edit_id').value;
    let company_id = document.getElementById('edit_company_id').value;
    let number = document.getElementById('edit_number').value.trim();
    let date = document.getElementById('edit_date').value.trim();
    
    if (!number) { showToast('شماره سند الزامی است', true); return; }
    
    let res = await fetch(`${apiUrl}?action=update_document`, { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'}, 
        body: JSON.stringify({
            id: id, 
            company_id: company_id,
            doc_number: number, 
            doc_date: date || '-'
        }) 
    });
    let result = await res.json();
    if (result.success) { 
        showToast('ویرایش شد'); 
        closeEditModal(); 
        await loadDocumentsForDisplay(currentDeliveryDate);
    }
}
window.deleteDocument = async function(id) {
    if (!confirm('حذف شود؟')) return;
    let res = await fetch(`${apiUrl}?action=delete_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
    let result = await res.json();
    if (result.success) { showToast('حذف شد'); if (document.getElementById('filtersContent').style.display !== 'none') searchAdminDocuments(); loadAdminLastDocuments(); }
}

document.getElementById('stats_user_select')?.addEventListener('change', () => loadAdminLastDocuments());
showLeftContent('stats');

<?php endif; ?>
</script>
</body>
</html>