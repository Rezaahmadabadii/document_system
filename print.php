<?php
session_start();
require_once 'config/database.php';
require_once 'config/jdatetime.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// دریافت تاریخ تحویل
$delivery_date = isset($_GET['delivery_date']) ? $_GET['delivery_date'] : '';

if (empty($delivery_date)) {
    die("تاریخ تحویل مشخص نشده است");
}

// ========== تعیین user_id ==========
$logged_in_user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;

// بررسی دسترسی کاربر به بایگانی کاربران
$can_view_all = false;
if (!$is_admin) {
    $stmt = $db->prepare("SELECT can_view_all_archives FROM users WHERE id = ?");
    $stmt->execute([$logged_in_user_id]);
    $can_view_all = $stmt->fetchColumn() == 1;
}

// اولویت 1: user_id در URL (اگر ادمین است یا دسترسی دارد)
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $url_user_id = (int)$_GET['user_id'];
    if ($is_admin || $can_view_all) {
        $user_id = $url_user_id;
    } else {
        $user_id = $logged_in_user_id;
    }
} else {
    // اولویت 2: از سشن (کاربر لاگین شده)
    $user_id = $logged_in_user_id;
}

// دریافت اطلاعات کاربر
$fullname = '';
$unit_name = '';
$stmt = $db->prepare("SELECT fullname, unit_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
if ($userInfo) {
    $fullname = $userInfo['fullname'];
    $unit_name = $userInfo['unit_name'];
}

// دریافت اسناد کاربر بر اساس تاریخ تحویل
$stmt = $db->prepare("SELECT d.*, c.name as company_name 
                      FROM documents d 
                      JOIN companies c ON d.company_id = c.id 
                      WHERE d.user_id = ? AND d.delivery_date = ? 
                      ORDER BY d.id ASC");
$stmt->execute([$user_id, $delivery_date]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($documents) == 0) {
    die("هیچ سندی برای این کاربر در تاریخ تحویل '$delivery_date' یافت نشد");
}

// دریافت اطلاعات کاربر
$fullname = '';
$unit_name = '';
$stmt = $db->prepare("SELECT fullname, unit_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
if ($userInfo) {
    $fullname = $userInfo['fullname'];
    $unit_name = $userInfo['unit_name'];
}

$is_admin = $_SESSION['is_admin'] ?? 0;

// دریافت اسناد کاربر بر اساس تاریخ تحویل
$stmt = $db->prepare("SELECT d.*, c.name as company_name 
                      FROM documents d 
                      JOIN companies c ON d.company_id = c.id 
                      WHERE d.user_id = ? AND d.delivery_date = ? 
                      ORDER BY d.id ASC");
$stmt->execute([$user_id, $delivery_date]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($documents) == 0) {
    die("هیچ سندی برای این کاربر در تاریخ تحویل '$delivery_date' یافت نشد");
}

// محاسبه آمار هر شرکت
$company_stats = [];
foreach ($documents as $doc) {
    $company_name = $doc['company_name'];
    if (!isset($company_stats[$company_name])) {
        $company_stats[$company_name] = 0;
    }
    $company_stats[$company_name]++;
}

// تقسیم اسناد به صفحات (هر صفحه حداکثر 40 ردیف)
$rows_per_page = 40;
$total_rows = count($documents);
$total_pages = ceil($total_rows / $rows_per_page);

// ایجاد صفحات
$pages = [];
for ($page = 0; $page < $total_pages; $page++) {
    $start = $page * $rows_per_page;
    $page_docs = array_slice($documents, $start, $rows_per_page);
    $page_count = count($page_docs);
    $start_number = $start + 1;
    
    if ($page_count <= 20) {
        $pages[] = [
            'type' => 'single',
            'docs' => $page_docs,
            'start_row' => $start_number,
            'end_row' => $start_number + $page_count - 1
        ];
    } else {
        $first_half = array_slice($page_docs, 0, 20);
        $second_half = array_slice($page_docs, 20);
        $pages[] = [
            'type' => 'double',
            'left' => $first_half,
            'right' => $second_half,
            'start_row' => $start_number,
            'end_row' => $start_number + $page_count - 1
        ];
    }
}

// توضیحات اسناد
$descriptions = [];
foreach ($documents as $doc) {
    if (!empty($doc['description']) && trim($doc['description']) !== '') {
        $descriptions[] = [
            'doc_number' => $doc['doc_number'],
            'description' => $doc['description']
        ];
    }
}

// ========== دریافت توضیحات از دیتابیس ==========
$stmt = $db->prepare("SELECT description FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
$stmt->execute([$user_id, $delivery_date]);
$report = $stmt->fetchColumn();
if (!$report) $report = '';
// ============================================

// ========== تعیین وضعیت امضا ==========
$user_signature_file = 'storage/signatures/users/' . $user_id . '.png';
$admin_signature_file = 'storage/signatures/admin/admin.png';

// بررسی تأیید کاربر برای این تاریخ
$stmt = $db->prepare("SELECT user_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
$stmt->execute([$user_id, $delivery_date]);
$user_approved = $stmt->fetchColumn();

// بررسی تأیید ادمین برای این تاریخ
$stmt = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
$stmt->execute([$user_id, $delivery_date]);
$admin_approved = $stmt->fetchColumn();

$has_user_approval = file_exists($user_signature_file) && !empty($user_approved);
$has_admin_approval = file_exists($admin_signature_file) && !empty($admin_approved);
// =========================================
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>پرینت اسناد - <?php echo htmlspecialchars($delivery_date); ?></title>
    
    <!-- Font Awesome -->
    <script defer src="assets/js/all.min.js"></script>
    
    <!-- فونت Vazirmatn -->
    <link href="assets/css/vazirmatn.css" rel="stylesheet" type="text/css" />
    
    <!-- کتابخانه‌ها -->
    <script src="assets/js/html2canvas.min.js"></script>
    <script src="assets/js/jspdf.umd.min.js"></script>
    <script src="assets/js/jszip.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Vazirmatn', 'Segoe UI', Tahoma, sans-serif !important; }
        body { background: #e5e7eb; padding: 20px; }
        .print-container { max-width: 210mm; margin: 0 auto; background: white; padding: 8mm; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .page-break { page-break-before: always; break-before: page; margin-top: 0; }
        .header { text-align: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 3px solid #2c5f8a; }
        .header h2 { font-size: 1.4rem; margin-bottom: 5px; color: #1a2c3e; font-weight: 800; }
        .header p { color: #6c86a3; font-size: 0.7rem; }
        .info-row { display: flex; justify-content: space-between; margin: 15px 0; padding: 10px 15px; background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 10px; flex-wrap: wrap; gap: 10px; }
        .info-row div { font-size: 0.75rem; font-weight: 500; }
        .info-row strong { color: #2c5f8a; }
        .company-stats { margin: 15px 0; padding: 8px 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; }
        .company-stats-title { font-weight: bold; font-size: 0.7rem; color: #166534; border-right: 3px solid #22c55e; padding-right: 8px; white-space: nowrap; }
        .company-stats-items { display: flex; flex-wrap: wrap; gap: 8px; }
        .company-stat-item { background: white; padding: 2px 8px; border-radius: 16px; font-size: 0.6rem; border: 1px solid #bbf7d0; color: #166534; }
        .company-stat-item strong { font-weight: bold; color: #2c5f8a; }
        .two-columns { display: flex; gap: 20px; margin-top: 15px; }
        .column { flex: 1; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .single-column { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-top: 15px; }
        .section-title { background: linear-gradient(135deg, #1a2c3e, #2c5f8a); color: white; padding: 8px 12px; font-weight: 700; font-size: 0.75rem; text-align: center; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #38bdf8; padding: 10px 8px; border: 1px solid #7dd3fc; font-size: 0.75rem; font-weight: 700; color: white; text-align: center; }
        .data-table td { padding: 8px 6px; border: 1px solid #e2e8f0; text-align: center; font-size: 0.7rem; color: #1a2c3e; background: #fff; }
        .data-table tbody tr:nth-child(even) td { background: #f0f9ff; }
        .data-table tbody tr:nth-child(odd) td { background: #fff; }
        .descriptions-section { margin-top: 25px; page-break-inside: avoid; break-inside: avoid; }
        .descriptions-title { background: #38bdf8; color: white; padding: 6px 12px; border-radius: 8px; margin-bottom: 10px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .desc-item { background: #f8fafc; margin-bottom: 6px; padding: 8px 12px; border-radius: 8px; border-right: 3px solid #38bdf8; }
        .desc-text { font-size: 0.7rem; color: #1a2c3e; line-height: 1.5; }
        .report-section { margin: 25px 0 15px; padding: 12px; background: #f0f9ff; border-radius: 10px; border-right: 3px solid #38bdf8; }
        .report-title { font-weight: 700; margin-bottom: 8px; font-size: 0.75rem; color: #0284c7; }
        .report-content { line-height: 1.6; font-size: 0.7rem; color: #1a2c3e; }
        .signatures { display: flex; justify-content: space-between; margin-top: 35px; padding-top: 20px; border-top: 1px dashed #cbd5e1; gap: 40px; }
        .sign-box { flex: 1; text-align: center; }
        .sign-line { border-top: 1px solid #1a2c3e; width: 80%; margin: 25px auto 8px auto; }
        .sign-label { font-size: 0.65rem; color: #475569; font-weight: 500; }
        .sign-img { max-width: 150px; margin-top: 10px; max-height: 60px; }
        .sign-btn { display: inline-block; margin-top: 10px; padding: 6px 12px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 8px; font-size: 0.65rem; }
        .btn-print, .btn-close { padding: 8px 20px; border-radius: 10px; cursor: pointer; font-size: 0.75rem; margin-bottom: 15px; border: none; font-weight: 600; }
        .btn-print { background: #2c5f8a; color: white; }
        .btn-close { background: #64748b; color: white; margin-right: 10px; }
        .doc-count-badge { background: #fef08a; color: #854d0e; font-weight: bold; font-size: 0.9rem; padding: 3px 10px; border-radius: 20px; display: inline-block; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; margin: 0; }
            .print-container { padding: 0; margin: 0; width: 100%; max-width: 100%; box-shadow: none; }
            .signatures { display: flex !important; justify-content: space-between !important; flex-direction: row !important; gap: 40px !important; }
            .sign-box { flex: 1 !important; text-align: center !important; }
            .info-row { display: flex !important; justify-content: space-between !important; flex-direction: row !important; }
            .two-columns { display: flex !important; flex-direction: row !important; gap: 20px !important; }
            .column { flex: 1 !important; }
            .company-stats { display: block !important; }
            .company-stats-items { display: flex !important; flex-wrap: wrap !important; }
        }
        
        @media (max-width: 768px) {
            .signatures { flex-direction: column; gap: 20px; }
            .info-row { flex-direction: column; }
            .two-columns { flex-direction: column; }
            .company-stats-items { justify-content: center; }
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 15px;" class="no-print">
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> چاپ</button>
        <button class="btn-print" id="downloadPdfBtn" style="background:#dc2626;"><i class="fas fa-file-pdf"></i> دانلود PDF</button>
        <button class="btn-print" id="downloadImageBtn" style="background:#059669;"><i class="fas fa-image"></i> <span id="downloadBtnText">دانلود عکس</span></button>
        <button class="btn-close" onclick="closePrintWindow()"><i class="fas fa-times"></i> بستن</button>
    </div>
    
    <?php if(isset($_GET['error']) && $_GET['error'] == 'locked'): ?>
    <div style="background:#fee2e2; color:#dc2626; padding:12px; border-radius:10px; margin-bottom:15px; text-align:center; max-width:210mm; margin-left:auto; margin-right:auto;">
        <i class="fas fa-lock"></i> این تاریخ تحویل قبلاً توسط ادمین تایید نهایی شده است. امکان ثبت امضا وجود ندارد.
    </div>
    <?php endif; ?>
    
    <?php foreach($pages as $page_index => $page): ?>
        <?php $current_page_num = $page_index + 1; ?>
        
        <div class="print-container <?php echo ($page_index > 0) ? 'page-break' : ''; ?>">
            <div class="header">
                <h2><i class="fas fa-file-alt"></i> لیست تحویل اسناد</h2>
                <p>بایگانی مرکزی <?php if($total_pages > 1) echo '- صفحه <span style="color:#ef4444; font-weight:bold;">' . $current_page_num . ' از ' . $total_pages . '</span>'; ?></p>
            </div>
            <div class="info-row">
                <div><strong><i class="fas fa-user"></i> تحویل‌دهنده:</strong> <?php echo htmlspecialchars($fullname); ?></div>
                <div><strong><i class="fas fa-building"></i> واحد:</strong> <?php echo htmlspecialchars($unit_name); ?></div>
                <div><strong><i class="fas fa-calendar-check"></i> تاریخ تحویل:</strong> <?php echo htmlspecialchars($delivery_date); ?></div>
                <div>
                    <strong><i class="fas fa-file"></i> تعداد کل اسناد:</strong>
                    <span class="doc-count-badge"><?php echo count($documents); ?> عدد</span>
                </div>
            </div>
            
            <?php if($page_index == 0 && !empty($company_stats)): ?>
            <div class="company-stats">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="company-stats-title">
                        <i class="fas fa-chart-pie"></i> تفکیک اسناد بر اساس شرکت
                    </div>
                    <div class="company-stats-items">
                        <?php foreach($company_stats as $comp_name => $count): ?>
                        <span class="company-stat-item">
                            <strong><?php echo htmlspecialchars($comp_name); ?>:</strong> <?php echo $count; ?> سند
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($page['type'] == 'single'): ?>
            <div class="single-column">
                <div class="section-title">
                    <i class="fas fa-list"></i> لیست اسناد (ردیف <?php echo $page['start_row']; ?> تا <?php echo $page['end_row']; ?>)
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>شماره سند</th>
                            <th>تاریخ سند</th>
                            <th>شرکت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($page['docs'] as $index => $doc): ?>
                        <tr>
                            <td><?php echo $page['start_row'] + $index; ?></td>
                            <td><?php echo htmlspecialchars($doc['doc_number']); ?></td>
                            <td><?php echo $doc['doc_date'] == '-' ? '---' : htmlspecialchars($doc['doc_date']); ?></td>
                            <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="two-columns">
                <div class="column">
                    <div class="section-title">
                        <i class="fas fa-list"></i> بخش اول (ردیف <?php echo $page['start_row']; ?> تا <?php echo $page['start_row'] + 19; ?>)
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>شماره سند</th>
                                <th>تاریخ سند</th>
                                <th>شرکت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($page['left'] as $index => $doc): ?>
                            <tr>
                                <td><?php echo $page['start_row'] + $index; ?></td>
                                <td><?php echo htmlspecialchars($doc['doc_number']); ?></td>
                                <td><?php echo $doc['doc_date'] == '-' ? '---' : htmlspecialchars($doc['doc_date']); ?></td>
                                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="column">
                    <div class="section-title">
                        <i class="fas fa-list"></i> بخش دوم (ردیف <?php echo $page['start_row'] + 20; ?> تا <?php echo $page['end_row']; ?>)
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>شماره سند</th>
                                <th>تاریخ سند</th>
                                <th>شرکت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($page['right'] as $index => $doc): ?>
                            <tr>
                                <td><?php echo $page['start_row'] + 20 + $index; ?></td>
                                <td><?php echo htmlspecialchars($doc['doc_number']); ?></td>
                                <td><?php echo $doc['doc_date'] == '-' ? '---' : htmlspecialchars($doc['doc_date']); ?></td>
                                <td><?php echo htmlspecialchars($doc['company_name']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    <td>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($page_index == $total_pages - 1): ?>
            
            <?php if(!empty($descriptions)): ?>
            <div class="descriptions-section">
                <div class="descriptions-title">
                    <i class="fas fa-align-left"></i> توضیحات اسناد
                </div>
                <?php foreach($descriptions as $desc): ?>
                <div class="desc-item">
                    <div class="desc-text">
                        <strong><?php echo htmlspecialchars($desc['doc_number']); ?>:</strong><br>
                        <?php echo nl2br(htmlspecialchars($desc['description'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($report)): ?>
            <div class="report-section">
                <div class="report-title">
                    <i class="fas fa-pen-alt"></i> گزارش / یادداشت:
                </div>
                <div class="report-content">
                    <?php echo nl2br(htmlspecialchars($report)); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="signatures">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">امضاء تحویل‌دهنده</div>
                    <?php if(file_exists($user_signature_file) && $has_user_approval): ?>
                        <img src="<?php echo $user_signature_file . '?t=' . time(); ?>" class="sign-img">
                    <?php else: ?>
                        <?php if(!$is_admin && !$has_user_approval): ?>
                            <a href="signature_upload.php?delivery_date=<?php echo urlencode($delivery_date); ?>" class="sign-btn sign-btn-user"><i class="fas fa-pen"></i> ثبت امضا</a>
                        <?php elseif(!$is_admin && $has_user_approval): ?>
                            <span class="pending-text">در انتظار تایید نهایی</span>
                        <?php else: ?>
                            <span class="pending-text">در انتظار امضای تحویل‌دهنده</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">امضاء بایگانی</div>
                    <?php if(file_exists($admin_signature_file) && $has_admin_approval): ?>
                        <img src="<?php echo $admin_signature_file . '?t=' . time(); ?>" class="sign-img">
                    <?php else: ?>
                        <?php if($is_admin && $has_user_approval && !$has_admin_approval): ?>
                            <a href="admin_approve.php?user_id=<?php echo $user_id; ?>&delivery_date=<?php echo urlencode($delivery_date); ?>" class="sign-btn sign-btn-admin" onclick="return confirm('آیا از تایید نهایی این اسناد اطمینان دارید؟')"><i class="fas fa-check-circle"></i> تایید نهایی</a>
                        <?php elseif($is_admin && !$has_user_approval): ?>
                            <span class="pending-text">در انتظار امضای تحویل‌دهنده</span>
                        <?php else: ?>
                            <span class="pending-text">در انتظار تایید نهایی</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    <?php endforeach; ?>
    
    <script>
    // ========== توابع کمکی ==========
    function escapeHtml(str) {
        if(!str) return '';
        return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
    }
    
    function closePrintWindow() {
        window.close();
    }
    
    // ========== تابع دانلود PDF ==========
async function downloadAsPDF() {
    const pages = document.querySelectorAll('.print-container');
    const btn = document.getElementById('downloadPdfBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال آماده‌سازی PDF...';
    btn.disabled = true;
    
    const { jsPDF } = window.jspdf;
    const pdfWidth = 297;
    let pdf = null;
    
    for (let i = 0; i < pages.length; i++) {
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> در حال پردازش صفحه ${i+1} از ${pages.length}...`;
        
        const noPrintElements = pages[i].querySelectorAll('.no-print');
        noPrintElements.forEach(el => el.style.display = 'none');
        
        try {
            const canvas = await html2canvas(pages[i], {
                scale: 3,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false
            });
            
            noPrintElements.forEach(el => el.style.display = '');
            
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = pdfWidth;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            if (pdf === null) {
                pdf = new jsPDF('l', 'mm', 'a4');
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            } else {
                pdf.addPage('l', 'mm', 'a4');
                pdf.addImage(imgData, 'PNG', 0, 0, imgWidth, imgHeight);
            }
        } catch (err) {
            console.error(err);
            noPrintElements.forEach(el => el.style.display = '');
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('❌ خطا در پردازش صفحه ' + (i+1));
            return;
        }
    }
    
    if (pdf !== null) {
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        pdf.save(`document-${timestamp}.pdf`);
    }
    
    btn.innerHTML = originalText;
    btn.disabled = false;
    alert('✅ PDF با موفقیت دانلود شد');
}
    
    // ========== تابع دانلود عکس ==========
async function downloadAllPagesAsImage() {
    const pages = document.querySelectorAll('.print-container');
    const btn = document.getElementById('downloadImageBtn');
    const btnText = document.getElementById('downloadBtnText');
    const originalText = btnText.innerHTML;
    
    if (pages.length === 0) {
        alert('هیچ صفحه‌ای یافت نشد');
        return;
    }
    
    btnText.innerHTML = 'در حال آماده‌سازی...';
    btn.disabled = true;
    
    try {
        // اگر فقط یک صفحه باشد، مستقیماً PNG دانلود کن
        if (pages.length === 1) {
            const noPrintElements = pages[0].querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.style.display = 'none');
            
            const canvas = await html2canvas(pages[0], {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true
            });
            
            noPrintElements.forEach(el => el.style.display = '');
            
            const link = document.createElement('a');
            link.download = `document_page_1.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            alert('✅ تصویر با موفقیت دانلود شد');
        } 
        // اگر چند صفحه باشد، فایل ZIP دانلود کن
        else {
            const zip = new JSZip();
            let successCount = 0;
            
            for (let i = 0; i < pages.length; i++) {
                btnText.innerHTML = `در حال پردازش صفحه ${i+1} از ${pages.length}...`;
                
                const noPrintElements = pages[i].querySelectorAll('.no-print');
                noPrintElements.forEach(el => el.style.display = 'none');
                
                try {
                    const canvas = await html2canvas(pages[i], {
                        scale: 2,
                        backgroundColor: '#ffffff',
                        useCORS: true
                    });
                    
                    noPrintElements.forEach(el => el.style.display = '');
                    
                    const imgData = canvas.toDataURL('image/png');
                    const base64Data = imgData.split(',')[1];
                    zip.file(`page_${i+1}.png`, base64Data, { base64: true });
                    successCount++;
                } catch (err) {
                    console.error(err);
                    noPrintElements.forEach(el => el.style.display = '');
                }
            }
            
            if (successCount > 0) {
                const content = await zip.generateAsync({ type: 'blob' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(content);
                link.href = url;
                link.download = `documents_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.zip`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                alert(`✅ ${successCount} تصویر با موفقیت دانلود شد`);
            } else {
                alert('❌ خطا در ایجاد تصاویر');
            }
        }
    } catch (err) {
        console.error(err);
        alert('❌ خطا در ایجاد تصویر');
    } finally {
        btnText.innerHTML = originalText;
        btn.disabled = false;
    }
}
    
    // ========== اتصال رویدادها به دکمه‌ها ==========
    document.getElementById('downloadImageBtn').addEventListener('click', downloadAllPagesAsImage);
    document.getElementById('downloadPdfBtn').addEventListener('click', downloadAsPDF);
    </script>
</body>
</html>