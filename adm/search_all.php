<?php
session_start();
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit;
}
require_once '../config/database.php';
require_once '../config/jdatetime.class.php';

$users = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY unit_name")->fetchAll();
$companies = $db->query("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")->fetchAll();

$selected_user = $_GET['user_id'] ?? '';
$doc_number = $_GET['doc_number'] ?? '';
$doc_date = $_GET['doc_date'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$delivery_date_from = $_GET['delivery_date_from'] ?? '';
$delivery_date_to = $_GET['delivery_date_to'] ?? '';

$sql = "SELECT d.*, c.name as company_name, u.fullname as user_fullname, u.unit_name 
        FROM documents d 
        JOIN companies c ON d.company_id = c.id 
        JOIN users u ON d.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($selected_user) {
    $sql .= " AND d.user_id = ?";
    $params[] = $selected_user;
}
if ($doc_number) {
    $sql .= " AND d.doc_number LIKE ?";
    $params[] = "%$doc_number%";
}
if ($doc_date) {
    $sql .= " AND d.doc_date = ?";
    $params[] = $doc_date;
}
if ($company_id) {
    $sql .= " AND d.company_id = ?";
    $params[] = $company_id;
}
if ($delivery_date_from) {
    $sql .= " AND d.delivery_date >= ?";
    $params[] = $delivery_date_from;
}
if ($delivery_date_to) {
    $sql .= " AND d.delivery_date <= ?";
    $params[] = $delivery_date_to;
}

$sql .= " ORDER BY d.delivery_date DESC, d.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>جستجوی کل اسناد</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #0f2027, #203a43);
            padding: 0.8rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h2 { color: white; font-size: 1.1rem; }
        .header a { color: white; text-decoration: none; background: rgba(255,255,255,0.15); padding: 5px 12px; border-radius: 30px; font-size: 0.75rem; }
        .filter-box {
            padding: 1rem;
            background: #f8fafc;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 0.65rem; font-weight: 600; }
        .filter-group input, .filter-group select { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.75rem; min-width: 130px; }
        button { background: #6366f1; color: white; border: none; padding: 6px 16px; border-radius: 30px; cursor: pointer; font-size: 0.75rem; }
        .results { padding: 1rem; }
        .group-box {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .group-header {
            background: #f1f5f9;
            padding: 0.5rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .print-link { background: #10b981; color: white; padding: 3px 10px; border-radius: 30px; text-decoration: none; font-size: 0.65rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
        th, td { padding: 8px 6px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        th { background: #eef2ff; }
        .badge { background: #e2e8f0; padding: 2px 8px; border-radius: 20px; font-size: 0.6rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2><i class="fas fa-globe"></i> جستجوی کل اسناد (پنل مدیریت)</h2>
        <div><a href="../index.php"><i class="fas fa-arrow-right"></i> بازگشت</a></div>
    </div>
    
    <form method="GET" class="filter-box">
        <div class="filter-group"><label>👤 کاربر</label><select name="user_id"><option value="">همه</option><?php foreach($users as $u): ?><option value="<?php echo $u['id']; ?>" <?php echo $selected_user == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['fullname'] . ' (' . $u['unit_name'] . ')'); ?></option><?php endforeach; ?></select></div>
        <div class="filter-group"><label>🔢 شماره سند</label><input type="text" name="doc_number" value="<?php echo htmlspecialchars($doc_number); ?>"></div>
        <div class="filter-group"><label>📆 تاریخ سند</label><input type="text" name="doc_date" value="<?php echo htmlspecialchars($doc_date); ?>"></div>
        <div class="filter-group"><label>🏢 شرکت</label><select name="company_id"><option value="">همه</option><?php foreach($companies as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $company_id == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option><?php endforeach; ?></select></div>
        <div class="filter-group"><label>📅 تاریخ تحویل از</label><input type="text" name="delivery_date_from" value="<?php echo htmlspecialchars($delivery_date_from); ?>"></div>
        <div class="filter-group"><label>📅 تاریخ تحویل تا</label><input type="text" name="delivery_date_to" value="<?php echo htmlspecialchars($delivery_date_to); ?>"></div>
        <div class="filter-group"><button type="submit"><i class="fas fa-search"></i> جستجو</button></div>
    </form>
    
    <div class="results">
        <?php if(count($documents) > 0): ?>
            <?php $grouped = []; foreach($documents as $d) { $grouped[$d['delivery_date']][] = $d; } ?>
            <?php foreach($grouped as $date => $group): ?>
                <div class="group-box">
                    <div class="group-header">
                        <span><i class="fas fa-calendar-day"></i> <?php echo $date; ?> <span class="badge"><?php echo count($group); ?> سند</span></span>
                        <a href="../print.php?delivery_date=<?php echo urlencode($date); ?>" target="_blank" class="print-link"><i class="fas fa-print"></i> پرینت</a>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr><th>#</th><th>شماره سند</th><th>تاریخ سند</th><th>شرکت</th><th>تحویل‌دهنده</th><th>واحد</th></tr></thead>
                            <tbody>
                                <?php foreach($group as $idx => $d): ?>
                                    <tr><td><?php echo $idx+1; ?></td><td><?php echo htmlspecialchars($d['doc_number']); ?></td><td><?php echo $d['doc_date'] == '-' ? '—' : htmlspecialchars($d['doc_date']); ?></td><td><?php echo htmlspecialchars($d['company_name']); ?></td><td><?php echo htmlspecialchars($d['user_fullname']); ?></td><td><?php echo htmlspecialchars($d['unit_name']); ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif(isset($_GET['user_id']) || isset($_GET['doc_number'])): ?>
            <div class="empty-state" style="text-align:center; padding:40px;">❌ هیچ سندی یافت نشد</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>