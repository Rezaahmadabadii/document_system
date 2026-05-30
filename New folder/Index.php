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
    <script defer src="assets/js/all.min.js"></script>
    <link rel="stylesheet" href="assets/css/vazirmatn.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif !important; }
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
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #f8fafc, #fff); border-radius: 12px; padding: 8px 6px; text-align: center; border: 1px solid #eef2f5; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stat-value { font-size: 1.1rem; font-weight: 800; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-label { font-size: 0.55rem; color: #6c86a3; margin-top: 3px; }
        .stat-small { font-size: 0.6rem; color: #475569; margin-top: 6px; padding-top: 6px; border-top: 1px solid #eef2f5; }
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
        .companies-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px; }
        .company-card { background: #f8fafc; border-radius: 12px; padding: 10px; text-align: center; border: 1px solid #eef2f5; }
        .company-card .company-name { font-size: 0.7rem; font-weight: 600; display: block; margin-bottom: 8px; }
        .user-item { background: #f8fafc; border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .user-info { font-size: 0.7rem; }
        .user-name { font-weight: 700; color: #1a2c3e; }
        .user-unit { color: #6c86a3; font-size: 0.6rem; }
        .date-spinner:hover { background: #667eea !important; color: white !important; border-color: #667eea !important; }
        @media (max-width: 1400px) { .companies-grid { grid-template-columns: repeat(6, 1fr); } }
        @media (max-width: 1200px) { .companies-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 900px) { .documents-layout { flex-direction: column; } .right-panel-panel { width: 100%; position: relative; top: 0; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .companies-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .companies-grid { grid-template-columns: repeat(1, 1fr); } }
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
                    <div class="menu-item active" data-section="stats" onclick="showLeftContent('stats')"><i class="fas fa-chart-line"></i> آمار و تاریخچه</div>
                    <div class="menu-item" data-section="users" onclick="showLeftContent('users')"><i class="fas fa-users"></i> مدیریت کاربران</div>
                    <div class="menu-item" data-section="companies" onclick="showLeftContent('companies')"><i class="fas fa-building"></i> مدیریت شرکت‌ها</div>
                    <div class="menu-item" data-section="filters" onclick="showLeftContent('filters')"><i class="fas fa-filter"></i> جستجوی اسناد</div>
                    <div class="menu-item" data-section="approvals" onclick="showLeftContent('approvals')"><i class="fas fa-check-double"></i> تاییدات نهایی</div>
                    <div class="menu-item" data-section="archive" onclick="showLeftContent('archive')"><i class="fas fa-archive"></i> بایگانی</div>
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
                    <div class="admin-filter-group"><label><i class="fas fa-user"></i> انتخاب کاربر</label><select id="admin_user_select"><option value="">همه کاربران</option><?php foreach($users_list as $u): ?><option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-filter-group"><label><i class="fas fa-hashtag"></i> شماره سند</label><input type="text" id="admin_filter_number" placeholder="جستجو..."></div>
                    <div class="admin-filter-group"><label><i class="fas fa-calendar"></i> تاریخ سند</label><input type="text" id="admin_filter_date" placeholder="1404/01/01"></div>
                    <div class="admin-filter-group"><label><i class="fas fa-building"></i> شرکت</label><select id="admin_filter_company"><option value="">همه شرکت‌ها</option><?php $companies_list = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY id ASC")->fetchAll(); foreach($companies_list as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
                    <div class="admin-filter-group"><label><i class="fas fa-calendar-check"></i> تاریخ تحویل</label><input type="text" id="admin_filter_delivery" placeholder="1404/01/01"></div>
                </div>
                
                <?php else: ?>
                <div class="user-panel-buttons">
                    <button class="user-panel-btn active" onclick="toggleUserPanel('form')">📝 ثبت سند جدید</button>
                    <button class="user-panel-btn" onclick="toggleUserPanel('archive')">📦 بایگانی</button>
                </div>
                
                <div id="userFormPanel" class="user-form-panel">
                    <div class="form-section">
                        <div class="form-group"><label>تاریخ تحویل</label><div style="display: flex; align-items: center; gap: 8px;"><button type="button" id="dateMinus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">−</button><input type="text" id="delivery_date" readonly style="flex: 1; text-align: center; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 8px 12px; font-size: 0.9rem; font-weight: 600;"><button type="button" id="datePlus" class="date-spinner" style="width: 40px; height: 40px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1.3rem; font-weight: bold;">+</button></div></div>
                        <div class="form-group"><div style="display: flex; gap: 8px; align-items: center;"><input type="number" id="company_number" min="1" max="<?php echo count($companies); ?>" style="width: 70px; text-align: center; padding: 8px; border: 1.5px solid #e2e8f0; border-radius: 12px;" placeholder="#"><select id="company_id" style="flex: 1;"><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div></div>
                        <div class="form-group"><label>شماره سند</label><input type="text" id="doc_number" placeholder="INV-12345"></div>
                        <div class="form-group" id="date_group" <?php echo $require_doc_date ? '' : 'style="display:none;"'; ?>><label>تاریخ سند</label><input type="text" id="doc_date" placeholder="1405/02/30"></div>
                        <button class="btn-submit" id="submitBtn">✓ ثبت سند</button>
                    </div>
                    <div class="form-section" style="border-top: 1px solid #eef2f5; margin-top: 0;">
                        <div class="form-group"><label>گزارش / یادداشت</label><textarea id="doc_description" rows="3" placeholder="هرگونه توضیح یا گزارش اضافی..."></textarea></div>
                        <button class="btn-green" id="submitDescriptionBtn">✏️ ثبت توضیح</button>
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
                        <div class="left-section-title" style="margin-top:0;"><i class="fas fa-archive"></i> تاریخ‌های تایید شده</div>
                        <div id="userArchiveList"><div class="empty-state">در حال بارگذاری...</div></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="left-panel-list">
            <div id="leftContentArea">
                <?php if($is_admin): ?>
                <div id="statsContent" class="left-content"><div class="left-section-title"><i class="fas fa-chart-line"></i> آمار کلی</div><div id="statsContainer"><div class="empty-state">در حال بارگذاری...</div></div><div class="left-section-title"><i class="fas fa-history"></i> آخرین اسناد ثبت شده :</div><div id="recentDocsContainer"><div class="empty-state">در حال بارگذاری...</div></div></div>
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
                <div id="userDocumentsList" class="docs-list" style="margin-top: 15px;">
                    <div class="empty-state">برای جستجو از فیلدهای سمت راست استفاده کنید</div>
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
let currentPanel = 'form';

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

// ========== توابع عمومی ==========
function toggleUserPanel(panel) {
    currentPanel = panel;
    let formPanel = document.getElementById('userFormPanel');
    let searchPanel = document.getElementById('userSearchPanel');
    let archivePanel = document.getElementById('userArchivePanel');
    let btns = document.querySelectorAll('.user-panel-btn');
    
    btns.forEach(btn => btn.classList.remove('active'));
    if (formPanel) formPanel.style.display = 'none';
    if (searchPanel) searchPanel.style.display = 'none';
    if (archivePanel) archivePanel.style.display = 'none';
    
    if (panel === 'form') {
        if (formPanel) formPanel.style.display = 'block';
        if (btns[0]) btns[0].classList.add('active');
        if (typeof loadLastDeliveryDate === 'function') loadLastDeliveryDate();
        if (document.getElementById('company_id')) document.getElementById('company_id').focus();
    } else if (panel === 'search') {
        if (searchPanel) searchPanel.style.display = 'block';
        if (btns[1]) btns[1].classList.add('active');
        if (typeof loadSearchDocuments === 'function') loadSearchDocuments();
    } else if (panel === 'archive') {
        if (archivePanel) archivePanel.style.display = 'block';
        if (btns[2]) btns[2].classList.add('active');
        if (typeof loadUserArchive === 'function') loadUserArchive();
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
                dateRange = `<div style="font-size: 0.55rem; color: #6c86a3; margin-top: 3px;">از ${data.first_date} تا ${data.last_date}</div>`;
            }
            
            let html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">${data.today_count}</div>
                        <div class="stat-label">اسناد امروز</div>
                        <div class="stat-small" style="${data.trend_class} margin-top: 4px; font-size: 0.55rem;">${data.trend_icon} ${data.trend}</div>
                        <div class="stat-label" style="font-size: 0.55rem; margin-top: 2px;">نسبت به دیروز (${data.yesterday_count})</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${data.total_docs}</div>
                        <div class="stat-label">کل اسناد</div>
                        ${dateRange}
                    </div>
                    <div class="stat-card">
                        <div style="text-align: right;">
                            <div style="margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;">
                                <span style="font-weight: 600; font-size: 0.7rem;">بیشترین:</span>
                                <span style="font-weight: 700; font-size: 0.8rem; color: #10b981; margin-right: 4px;">${data.most_company}</span>
                                <span style="font-size: 0.65rem;">(${data.most_count})</span>
                            </div>
                            <div>
                                <span style="font-weight: 600; font-size: 0.7rem;">کمترین:</span>
                                <span style="font-weight: 700; font-size: 0.8rem; color: #ef4444; margin-right: 4px;">${data.least_company}</span>
                                <span style="font-size: 0.65rem;">(${data.least_count})</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="stat-card" style="margin-top: 10px;">
                    <div class="stat-label">میانگین اسناد</div>
                    <div style="display: flex; justify-content: space-around; margin-top: 8px;">
                        <div><span style="font-weight: 700; font-size: 0.9rem;">${data.week_count}</span><br><span style="font-size: 0.55rem;">هفته گذشته</span></div>
                        <div><span style="font-weight: 700; font-size: 0.9rem;">${data.month_count}</span><br><span style="font-size: 0.55rem;">ماه گذشته</span></div>
                        <div><span style="font-weight: 700; font-size: 0.9rem;">${data.year_count}</span><br><span style="font-size: 0.55rem;">سال گذشته</span></div>
                    </div>
                </div>
            `;
            document.getElementById('userStatsContainer').innerHTML = html;
        } else {
            document.getElementById('userStatsContainer').innerHTML = '<div class="empty-state">خطا در دریافت آمار</div>';
        }
    } catch(e) {
        console.error(e);
        document.getElementById('userStatsContainer').innerHTML = '<div class="empty-state">خطا در دریافت آمار</div>';
    }
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

// ========== بارگذاری اسناد بر اساس تاریخ تحویل ==========
function loadDocumentsForDeliveryDate(deliveryDate) {
    fetch(`api/ajax.php?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('userDocumentsList');
            if (!container) return;
            
            if (data.success && data.documents && data.documents.length > 0) {
                let html = '';
                let revertButton = '';
                if (data.has_user_approval && !data.has_admin_approval) {
                    revertButton = `<button class="print-btn" onclick="revertApproval('${deliveryDate}')" style="background:#f59e0b;"><i class="fas fa-undo"></i> بازیابی</button>`;
                } else if (data.has_user_approval && data.has_admin_approval) {
                    revertButton = `<button class="print-btn" onclick="requestRevert('${deliveryDate}')" style="background:#ef4444;"><i class="fas fa-envelope"></i> درخواست بازیابی</button>`;
                }
                
                html = `<div class="doc-group">
                            <div class="group-title">
                                <div class="group-date"><i class="fas fa-calendar-day"></i> ${escapeHtml(deliveryDate)} <span style="background:#eef2ff;padding:2px 8px;border-radius:20px;margin-right:8px;">${data.documents.length} سند</span></div>
                                <div style="display:flex; gap:8px;">
                                    <a href="print.php?delivery_date=${encodeURIComponent(deliveryDate)}" target="_blank" class="print-btn"><i class="fas fa-print"></i> پرینت</a>
                                    ${revertButton}
                                </div>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="data-table">
                                    <thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>عملیات</th></tr></thead>
                                    <tbody>`;
                for (let i = 0; i < data.documents.length; i++) {
                    let doc = data.documents[i];
                    let docDate = doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date);
                    let actions = '';
                    if (doc.can_edit && !data.has_admin_approval) {
                        actions = `<button class="action-btn edit-btn" onclick="openEditModal(${doc.id}, '${escapeHtml(doc.doc_number)}', '${escapeHtml(doc.doc_date)}', '${escapeHtml(doc.description || '')}')"><i class="fas fa-edit"></i></button>
                                   <button class="action-btn delete-btn" onclick="deleteDocument(${doc.id})"><i class="fas fa-trash-alt"></i></button>`;
                    }
                    html += `<tr><td style="padding: 8px 6px;">${i+1}</td><td style="padding: 8px 6px;">${escapeHtml(doc.doc_number)}</td><td style="padding: 8px 6px;">${docDate}</td><td style="padding: 8px 6px;">${escapeHtml(doc.company_name)}</td><td style="padding: 8px 6px;">${actions}</td></tr>`;
                }
                html += `</tbody>\\n<table>\\n</div>`;
                
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
                // اگر سندی در این تاریخ وجود ندارد
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
            document.getElementById('userDocumentsList').innerHTML = '<div class="empty-state">خطا در دریافت اسناد</div>';
        });
}

// اتصال رویدادها و اجرای اولیه
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM آماده است');
    
    const filterNumber = document.getElementById('filter_number');
    const filterDate = document.getElementById('filter_date');
    const filterCompany = document.getElementById('filter_company');
    const filterDelivery = document.getElementById('filter_delivery');
    
    if (filterNumber) {
        filterNumber.addEventListener('input', autoSearchDocuments);
        console.log('event attached to filter_number');
    }
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
        
        // بارگذاری اولیه اسناد برای تاریخ فعلی
        if (typeof loadDocumentsForDeliveryDate === 'function') {
            loadDocumentsForDeliveryDate(deliveryDateInput.value);
        }
    }
});

function viewArchiveDocument(deliveryDate) {
    window.open(`print.php?delivery_date=${encodeURIComponent(deliveryDate)}`, '_blank');
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

document.addEventListener('DOMContentLoaded', function() {
    function getDaysInMonth(year, month) {
        if (month >= 1 && month <= 6) return 31;
        if (month >= 7 && month <= 11) return 30;
        if (month === 12) { let isLeap = (year % 33 === 1 || year % 33 === 5 || year % 33 === 9 || year % 33 === 13 || year % 33 === 17 || year % 33 === 22 || year % 33 === 26 || year % 33 === 30); return isLeap ? 30 : 29; }
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
    
    async function checkLockStatus(deliveryDate) {
        try {
            let res = await fetch(`${apiUrl}?action=get_documents_for_display&delivery_date=${encodeURIComponent(deliveryDate)}`);
            let data = await res.json();
            if (data.has_admin_approval === true) {
                document.getElementById('doc_number').disabled = true;
                document.getElementById('doc_description').disabled = true;
            } else {
                document.getElementById('doc_number').disabled = false;
                document.getElementById('doc_description').disabled = false;
            }
        } catch(e) { console.error(e); }
    }
    
    function addDaysToDelivery(days) {
        deliveryDay += days;
        let daysInMonth = getDaysInMonth(deliveryYear, deliveryMonth);
        if (deliveryDay > daysInMonth) { deliveryDay = 1; deliveryMonth++; if (deliveryMonth > 12) { deliveryMonth = 1; deliveryYear++; } }
        if (deliveryDay < 1) { deliveryMonth--; if (deliveryMonth < 1) { deliveryMonth = 12; deliveryYear--; } deliveryDay = getDaysInMonth(deliveryYear, deliveryMonth); }
        updateDeliveryDate();
        let newDate = deliveryYear + '/' + (deliveryMonth < 10 ? '0'+deliveryMonth : deliveryMonth) + '/' + (deliveryDay < 10 ? '0'+deliveryDay : deliveryDay);
        checkLockStatus(newDate);
        loadDocumentsForDisplay(newDate);
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

    async function revertApproval(deliveryDate) {
        if (!confirm('آیا از لغو امضای خود اطمینان دارید؟ پس از بازیابی می‌توانید اسناد را ویرایش کنید.')) return;
        let res = await fetch(`${apiUrl}?action=revert_approval&delivery_date=${encodeURIComponent(deliveryDate)}`, { method: 'POST' });
        let result = await res.json();
        if (result.success) { showToast('✅ امضا لغو شد. اکنون می‌توانید اسناد را ویرایش کنید'); location.reload(); }
        else { showToast(result.error || '❌ خطا در بازیابی', true); }
    }

    async function requestRevert(deliveryDate) {
        if (!confirm('اسناد شما تایید نهایی شده است. آیا از ادمین درخواست بازیابی می‌کنید؟')) return;
        let res = await fetch(`${apiUrl}?action=request_revert&delivery_date=${encodeURIComponent(deliveryDate)}`, { method: 'POST' });
        let result = await res.json();
        if (result.success) { showToast('درخواست بازیابی برای ادمین ارسال شد'); }
        else { showToast(result.error || 'خطا در ارسال درخواست', true); }
    }

async function loadLastDeliveryDate() {
    try {
        let res = await fetch(`${apiUrl}?action=get_last_delivery_date`);
        let text = await res.text();
        if (text.trim().startsWith('<')) {
            let today = '<?php echo $today; ?>';
            document.getElementById('delivery_date').value = today;
            autoSearchDocuments();
            if (typeof checkLockStatus === 'function') await checkLockStatus(today);
            return;
        }
        let data = JSON.parse(text);
        if (data.success && data.last_date) {
            document.getElementById('delivery_date').value = data.last_date;
            autoSearchDocuments();
            if (typeof checkLockStatus === 'function') await checkLockStatus(data.last_date);
        } else {
            let today = '<?php echo $today; ?>';
            document.getElementById('delivery_date').value = today;
            autoSearchDocuments();
            if (typeof checkLockStatus === 'function') await checkLockStatus(today);
        }
    } catch(e) {
        let today = '<?php echo $today; ?>';
        document.getElementById('delivery_date').value = today;
        autoSearchDocuments();
        if (typeof checkLockStatus === 'function') await checkLockStatus(today);
    }
}

    async function submitDocument() {
        let delivery_date = document.getElementById('delivery_date').value;
        let checkRes = await fetch(`${apiUrl}?action=get_documents_for_display&delivery_date=${encodeURIComponent(delivery_date)}`);
        let checkData = await checkRes.json();
        if (checkData.has_admin_approval === true) {
            showToast('این تاریخ تحویل تایید نهایی شده است. امکان ثبت سند وجود ندارد.', true);
            return;
        }
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
    document.addEventListener('keydown', async function(e) { if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); if (currentPanel === 'form') { await submitDocument(); } } });
    if (document.getElementById('submitBtn')) {
        document.getElementById('submitBtn').addEventListener('click', function() { submitDocument(); setTimeout(function() { if (document.getElementById('doc_number')) document.getElementById('doc_number').focus(); }, 100); });
    }
    loadLastDeliveryDate();

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
        let id = document.getElementById('edit_id')?.value || '', number = document.getElementById('edit_number')?.value.trim() || '', date = document.getElementById('edit_date')?.value.trim() || '';
        if (!id) { showToast('شناسه سند یافت نشد', true); return; }
        if (!number) { showToast('شماره سند الزامی است', true); return; }
        let res = await fetch(`${apiUrl}?action=update_document`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, doc_number: number, doc_date: date || '-' }) });
        let result = await res.json();
        if (result.success) { showToast('ویرایش شد'); closeEditModal(); await loadDocumentsForDisplay(currentDeliveryDate); }
        else { showToast(result.error || 'خطا در ویرایش', true); }
    }
    window.deleteDocument = async function(id) {
        if (!confirm('حذف شود؟')) return;
        let res = await fetch(`${apiUrl}?action=delete_document`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({id}) });
        let result = await res.json();
        if (result.success) { showToast('حذف شد'); await loadDocumentsForDisplay(currentDeliveryDate); }
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
});

<?php endif; ?>

// ========== ادمین ==========
<?php if($is_admin): ?>

let currentAdminUserId = '';

async function loadAdminLastDocuments() {
    let userId = document.getElementById('stats_user_select')?.value || '';
    currentAdminUserId = userId;
    try {
        let res = await fetch(`${apiUrl}?action=get_admin_stats&user_id=${userId}`);
        let data = await res.json();
        if (data.success) {
            let statsHtml = `<div class="stats-grid"><div class="stat-card"><div class="stat-value">${data.total_docs}</div><div class="stat-label">کل اسناد</div></div><div class="stat-card"><div class="stat-value">${data.total_users}</div><div class="stat-label">کاربران</div></div><div class="stat-card"><div class="stat-value">${data.total_companies}</div><div class="stat-label">شرکت‌ها</div></div><div class="stat-card"><div class="stat-value">${data.today_count}</div><div class="stat-label">اسناد امروز</div></div></div><div class="stat-card"><div class="stat-small">📅 از ${data.first_date} تا ${data.today_date}</div><div class="stat-small">📌 آخرین ثبت: ${data.last_date}</div></div>`;
            document.getElementById('statsContainer').innerHTML = statsHtml;
            if (data.recent_docs && data.recent_docs.length > 0) {
                let html = `<div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>کاربر</th></tr></thead><tbody>`;
                for (let i = 0; i < data.recent_docs.length; i++) {
                    let doc = data.recent_docs[i];
                    html += `<tr><td>${i+1}</td><td>${escapeHtml(doc.doc_number)}</td><td>${doc.doc_date === '-' ? '—' : escapeHtml(doc.doc_date)}</td><td>${escapeHtml(doc.company_name)}</td><td>${escapeHtml(doc.user_name || '')}</td></tr>`;
                }
                html += `</tbody></table></div>`;
                document.getElementById('recentDocsContainer').innerHTML = html;
            } else { document.getElementById('recentDocsContainer').innerHTML = '<div class="empty-state">هیچ سندی ثبت نشده است</div>'; }
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
                html += `<div class="archive-item" style="margin-bottom:10px;"><div><div style="font-weight:bold;">${escapeHtml(app.fullname)} (${escapeHtml(app.unit_name)})</div><div style="font-size:0.65rem;">تاریخ تحویل: ${escapeHtml(app.delivery_date)}</div><div style="font-size:0.6rem; color:#10b981;">تایید شده در: ${escapeHtml(app.admin_approved_at)}</div></div><div class="archive-actions"><button class="archive-btn view" onclick="window.open('print.php?user_id=${app.user_id}&delivery_date=${encodeURIComponent(app.delivery_date)}', '_blank')"><i class="fas fa-print"></i> پرینت</button></div></div>`;
            }
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state">هیچ تایید نهایی ثبت نشده است</div>'; }
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

function showLeftContent(section) {
    document.querySelectorAll('#leftContentArea > div').forEach(div => div.style.display = 'none');
    if (section === 'stats') document.getElementById('statsContent').style.display = 'block';
    else if (section === 'users') document.getElementById('usersContent').style.display = 'block';
    else if (section === 'companies') document.getElementById('companiesContent').style.display = 'block';
    else if (section === 'filters') document.getElementById('filtersContent').style.display = 'block';
    else if (section === 'approvals') document.getElementById('approvalsContent').style.display = 'block';
    else if (section === 'archive') document.getElementById('archiveContent').style.display = 'block';
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
    document.querySelector(`.menu-item[data-section="${section}"]`).classList.add('active');
    if (section === 'stats') loadAdminLastDocuments();
    else if (section === 'users') loadUsersList();
    else if (section === 'filters') searchAdminDocuments();
    else if (section === 'approvals') { loadUsersPendingList(); loadRevertRequests(); loadApprovedApprovals(); }
    else if (section === 'archive') loadAdminArchive();
}

function showApprovalsTab(tab) {
    document.getElementById('pendingApprovalsTab').style.display = 'none';
    document.getElementById('revertRequestsTab').style.display = 'none';
    document.getElementById('approvedApprovalsTab').style.display = 'none';
    document.querySelectorAll('.tab-btn').forEach(btn => { btn.style.color = '#475569'; btn.style.borderBottom = 'none'; });
    if (tab === 'pending') {
        document.getElementById('pendingApprovalsTab').style.display = 'block';
        document.querySelector('.tab-btn:first-child').style.color = '#667eea';
        document.querySelector('.tab-btn:first-child').style.borderBottom = '2px solid #667eea';
        loadUsersPendingList();
    } else if (tab === 'revert') {
        document.getElementById('revertRequestsTab').style.display = 'block';
        document.querySelector('.tab-btn:nth-child(2)').style.color = '#667eea';
        document.querySelector('.tab-btn:nth-child(2)').style.borderBottom = '2px solid #667eea';
        loadRevertRequests();
    } else if (tab === 'approved') {
        document.getElementById('approvedApprovalsTab').style.display = 'block';
        document.querySelector('.tab-btn:nth-child(3)').style.color = '#667eea';
        document.querySelector('.tab-btn:nth-child(3)').style.borderBottom = '2px solid #667eea';
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
                html += `<div class="user-item"><div class="user-info"><div class="user-name">${escapeHtml(user.fullname)}</div><div class="user-unit">${escapeHtml(user.unit_name)} | ${escapeHtml(user.username)} | کل اسناد: ${user.total_docs}</div></div><div><button class="action-btn edit-btn" onclick="editUser(${user.id}, '${escapeHtml(user.username)}', '${escapeHtml(user.fullname)}', '${escapeHtml(user.unit_name)}', ${user.require_doc_date})"><i class="fas fa-edit"></i></button><button class="action-btn delete-btn" onclick="deleteUser(${user.id})"><i class="fas fa-trash-alt"></i></button></div></div>`;
            }
            container.innerHTML = html;
        }
    } catch(e) { console.error(e); }
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

document.getElementById('stats_user_select')?.addEventListener('change', loadAdminLastDocuments);
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

loadAdminLastDocuments();
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

// اتصال رویدادهای جستجوی آنی برای کاربر عادی
document.addEventListener('DOMContentLoaded', function() {
    const filterNumber = document.getElementById('filter_number');
    const filterDate = document.getElementById('filter_date');
    const filterCompany = document.getElementById('filter_company');
    const filterDelivery = document.getElementById('filter_delivery');
    
    if (filterNumber) filterNumber.addEventListener('input', autoSearchDocuments);
    if (filterDate) filterDate.addEventListener('input', autoSearchDocuments);
    if (filterCompany) filterCompany.addEventListener('change', autoSearchDocuments);
    if (filterDelivery) filterDelivery.addEventListener('input', autoSearchDocuments);
    
    // ایجاد container برای نتایج جستجو اگر وجود ندارد
    if (!document.getElementById('userDocumentsList') && document.getElementById('userSearchPanel')) {
        const div = document.createElement('div');
        div.id = 'userDocumentsList';
        div.className = 'docs-list';
        div.style.marginTop = '15px';
        document.getElementById('userSearchPanel').appendChild(div);
    }
});
</script>
</body>
</html>