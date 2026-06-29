<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);
session_name('doc_system');
session_start();
require_once '../config/database.php';
require_once '../config/jdatetime.class.php';

// ========== توابع تبدیل ==========
function toEnglishNumber($str) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($persian, $english, $str);
}

function toPersianNumber($str) {
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace($english, $persian, $str);
}
// =================================

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;
$fullname = $_SESSION['fullname'] ?? '';
$action = $_GET['action'] ?? '';

// ========== ذخیره امضای کاربر (جایگزینی) ==========
if ($action == 'save_user_signature') {
    $data = json_decode(file_get_contents('php://input'), true);
    $signature_data = $data['signature_data'] ?? '';
    $delivery_date = $data['delivery_date'] ?? '';
    
    if (empty($signature_data)) {
        echo json_encode(['success' => false, 'error' => 'داده امضا یافت نشد']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE users SET signature_data = ? WHERE id = ?");
    $result = $stmt->execute([$signature_data, $user_id]);
    
    if ($result) {
        $stmt = $db->prepare("INSERT INTO delivery_approvals (user_id, delivery_date, user_approved_at) 
                              VALUES (?, ?, NOW()) 
                              ON DUPLICATE KEY UPDATE user_approved_at = NOW()");
        $stmt->execute([$user_id, $delivery_date]);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در ذخیره امضا']);
    }
    exit;
}

// ========== دریافت امضای کاربر برای نمایش ==========
if ($action == 'get_user_signature') {
    $stmt = $db->prepare("SELECT signature_data FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $signature = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'signature' => $signature]);
    exit;
}

// ========== دریافت آخرین تاریخ تحویل کاربر ==========
if ($action == 'get_last_delivery_date') {
    $stmt = $db->prepare("SELECT delivery_date FROM documents WHERE user_id = ? ORDER BY delivery_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $last_date = $stmt->fetchColumn();
    
    if (!$last_date) {
        $last_date = jDateTime::date('Y/m/d');
    }
    
    echo json_encode(['success' => true, 'last_date' => $last_date]);
    exit;
}

// ========== دریافت تاریخ امروز ==========
if ($action == 'get_today_date') {
    $today = jDateTime::date('Y/m/d');
    echo json_encode(['success' => true, 'today_date' => $today]);
    exit;
}

// ========== دریافت اسناد برای نمایش در لیست (تاریخ مشخص) ==========
if ($action == 'get_documents_for_display') {
    $target_user_id = isset($_GET['admin_user_id']) && $is_admin && $_GET['admin_user_id'] !== '' ? (int)$_GET['admin_user_id'] : $user_id;
    $delivery_date = $_GET['delivery_date'] ?? '';
    $delivery_date = toEnglishNumber($delivery_date);
    
    if (empty($delivery_date)) {
        echo json_encode(['success' => false, 'error' => 'تاریخ تحویل مشخص نشده']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT user_approved_at, admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$target_user_id, $delivery_date]);
    $approval = $stmt->fetch();
    
    $has_user_approval = $approval && !empty($approval['user_approved_at']);
    $has_admin_approval = $approval && !empty($approval['admin_approved_at']);
    
    $sql = "SELECT d.*, c.name as company_name 
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            WHERE d.user_id = :user_id AND d.delivery_date = :delivery_date 
            ORDER BY d.id ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $target_user_id, ':delivery_date' => $delivery_date]);
    $docs = $stmt->fetchAll();
    
    $can_edit = $is_admin;
    if (!$is_admin && !$has_admin_approval) {
        $can_edit = true;
    }
    
    $result = [];
    foreach ($docs as $idx => $doc) {
        $result[] = [
            'id' => $doc['id'],
            'doc_number' => $doc['doc_number'],
            'doc_date' => $doc['doc_date'],
            'company_name' => $doc['company_name'],
            'description' => $doc['description'],
            'row_num' => $idx + 1,
            'can_edit' => $can_edit
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'documents' => $result, 
        'delivery_date' => toPersianNumber($delivery_date),
        'has_user_approval' => $has_user_approval,
        'has_admin_approval' => $has_admin_approval
    ]);
    exit;
}

// ========== جستجوی اسناد (کاربر عادی) ==========
if ($action == 'get_documents') {
    $doc_number = $_GET['doc_number'] ?? '';
    $doc_date = $_GET['doc_date'] ?? '';
    $company_id = $_GET['company_id'] ?? '';
    $delivery_date = $_GET['delivery_date'] ?? '';
    $search_user_id = $user_id;

    $sql = "SELECT d.*, c.name as company_name 
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            WHERE d.user_id = :user_id";
    $params = [':user_id' => $search_user_id];

    if (!empty($doc_number)) {
        $sql .= " AND d.doc_number LIKE :doc_number";
        $params[':doc_number'] = "$doc_number%";
    }
    if (!empty($doc_date)) {
        $doc_date = toEnglishNumber($doc_date);
        $sql .= " AND d.doc_date = :doc_date";
        $params[':doc_date'] = $doc_date;
    }
    if (!empty($company_id)) {
        $sql .= " AND d.company_id = :company_id";
        $params[':company_id'] = $company_id;
    }
    if (!empty($delivery_date)) {
        $delivery_date = toEnglishNumber($delivery_date);
        $sql .= " AND d.delivery_date = :delivery_date";
        $params[':delivery_date'] = $delivery_date;
    }

    $sql .= " ORDER BY d.delivery_date DESC, d.id DESC";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($docs as $doc) {
        $date = toPersianNumber($doc['delivery_date']);
        if (!isset($groups[$date])) {
            $stmt_check = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
            $stmt_check->execute([$search_user_id, $doc['delivery_date']]);
            $is_archived = !empty($stmt_check->fetchColumn());

            $groups[$date] = [
                'delivery_date' => $date,
                'count' => 0,
                'is_archived' => $is_archived,
                'documents' => []
            ];
        }
        $groups[$date]['documents'][] = [
            'id' => $doc['id'],
            'doc_number' => $doc['doc_number'],
            'doc_date' => $doc['doc_date'] == '-' ? '-' : toPersianNumber($doc['doc_date']),
            'company_name' => $doc['company_name'],
            'description' => $doc['description'],
            'row_num' => count($groups[$date]['documents']),
            'can_edit' => true
        ];
        $groups[$date]['count']++;
    }

    echo json_encode(['success' => true, 'groups' => array_values($groups)]);
    exit;
}

// ========== ثبت سند جدید ==========
if ($action == 'save_document') {
    if ($is_admin) {
        echo json_encode(['success' => false, 'error' => 'Admin cannot save documents directly']);
        exit;
    }
    
    // دریافت تنظیمات کاربر برای اجباری بودن تاریخ سند
    $stmt = $db->prepare("SELECT require_doc_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $require_doc_date = $stmt->fetchColumn();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $delivery_date = $data['delivery_date'] ?? jDateTime::date('Y/m/d');
    $company_id = $data['company_id'] ?? 0;
    $doc_number = $data['doc_number'] ?? '';
    $doc_date = $data['doc_date'] ?? '';
    $description = $data['description'] ?? '';
    
    if (empty($doc_number)) {
        echo json_encode(['success' => false, 'error' => 'شماره سند الزامی است']);
        exit;
    }
    
    // بررسی اجباری بودن تاریخ سند
    if ($require_doc_date == 1) {
        if (empty($doc_date) || $doc_date == '-') {
            echo json_encode(['success' => false, 'error' => 'تاریخ سند الزامی است']);
            exit;
        }
        // تبدیل تاریخ به عدد انگلیسی
        $doc_date = toEnglishNumber($doc_date);
    } else {
        // اگر اختیاری است و خالی بود، خط تیره بگذار
        if (empty($doc_date)) {
            $doc_date = '-';
        } else {
            $doc_date = toEnglishNumber($doc_date);
        }
    }
    
    $stmt = $db->prepare("INSERT INTO documents (user_id, company_id, doc_number, doc_date, delivery_date, description) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$user_id, $company_id, $doc_number, $doc_date, $delivery_date, $description]);
    
    echo json_encode(['success' => $result, 'delivery_date' => $delivery_date]);
    exit;
}

// ========== افزودن توضیحات به سند ==========
if ($action == 'add_description') {
    $data = json_decode(file_get_contents('php://input'), true);
    $doc_id = $data['doc_id'] ?? 0;
    $description = $data['description'] ?? '';
    
    if (empty($description)) {
        echo json_encode(['success' => false, 'error' => 'توضیحات نمی‌تواند خالی باشد']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT user_id, created_at, description FROM documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        exit;
    }
    
    $can_edit = $is_admin || (time() - strtotime($doc['created_at'])) <= (2 * 86400);
    if (!$can_edit) {
        echo json_encode(['success' => false, 'error' => 'امکان اضافه کردن توضیحات بعد از ۲ روز وجود ندارد']);
        exit;
    }
    
    $old_desc = $doc['description'];
    $new_desc = $old_desc ? $old_desc . "\n---\n" . $description : $description;
    
    $stmt = $db->prepare("UPDATE documents SET description = ? WHERE id = ?");
    $result = $stmt->execute([$new_desc, $doc_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== ذخیره گزارش برای چاپ ==========
if ($action == 'save_report') {
    $data = json_decode(file_get_contents('php://input'), true);
    $report = $data['report'] ?? '';
    
    $_SESSION['print_report'] = $report;
    
    echo json_encode(['success' => true]);
    exit;
}

// ========== ویرایش سند ==========
if ($action == 'update_document') {
    $data = json_decode(file_get_contents('php://input'), true);
    $doc_id = $data['id'] ?? 0;
    $doc_number = $data['doc_number'] ?? '';
    $doc_date = $data['doc_date'] ?? '-';
    
    $stmt = $db->prepare("SELECT user_id, created_at FROM documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        exit;
    }
    
    if (!$is_admin && $doc['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به ویرایش این سند ندارید']);
        exit;
    }
    
    $can_edit = $is_admin || (time() - strtotime($doc['created_at'])) <= (2 * 86400);
    if (!$can_edit) {
        echo json_encode(['success' => false, 'error' => 'امکان ویرایش بعد از ۲ روز وجود ندارد']);
        exit;
    }
    
    if ($doc_date !== '-') {
        $doc_date = toEnglishNumber($doc_date);
    }
    
    // ✅ فقط شماره سند و تاریخ سند
    $stmt = $db->prepare("UPDATE documents SET doc_number = ?, doc_date = ? WHERE id = ?");
    $result = $stmt->execute([$doc_number, $doc_date, $doc_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== حذف سند ==========
if ($action == 'delete_document') {
    $data = json_decode(file_get_contents('php://input'), true);
    $doc_id = $data['id'] ?? 0;
    
    $stmt = $db->prepare("SELECT user_id, created_at FROM documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        exit;
    }
    
    // ✅ اضافه کردن این ۴ خط - بررسی مالکیت سند
    if (!$is_admin && $doc['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به حذف این سند ندارید']);
        exit;
    }
    
    $can_delete = $is_admin || (time() - strtotime($doc['created_at'])) <= (2 * 86400);
    if (!$can_delete) {
        echo json_encode(['success' => false, 'error' => 'امکان حذف بعد از ۲ روز وجود ندارد']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    $result = $stmt->execute([$doc_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== تغییر رمز عبور ==========
if ($action == 'change_password') {
    $data = json_decode(file_get_contents('php://input'), true);
    $old_password = $data['old_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!password_verify($old_password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'رمز فعلی اشتباه است']);
        exit;
    }
    
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $stmt->execute([$new_hash, $user_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== تابع کمکی برای تبدیل تاریخ شمسی به تایم‌استمپ ==========
function shamsi_to_timestamp($shamsi_date) {
    list($y, $m, $d) = explode('/', $shamsi_date);
    $g = jDateTime::toGregorian($y, $m, $d);
    if (is_array($g)) {
        return strtotime($g[0] . '-' . str_pad($g[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($g[2], 2, '0', STR_PAD_LEFT));
    }
    return strtotime($g);
}

// ========== تابع کمکی برای محاسبه هفته بر اساس تاریخ شروع ==========
function get_week_range_based_on_start($start_date, $target_date) {
    $start_timestamp = shamsi_to_timestamp($start_date);
    $target_timestamp = shamsi_to_timestamp($target_date);
    
    // محاسبه روز هفته برای تاریخ شروع (0=شنبه تا 6=جمعه)
    $start_weekday = date('w', $start_timestamp);
    $start_weekday = ($start_weekday + 1) % 7;
    
    // محاسبه روز هفته برای تاریخ هدف
    $target_weekday = date('w', $target_timestamp);
    $target_weekday = ($target_weekday + 1) % 7;
    
    // محاسبه هفته جاری بر اساس تاریخ شروع
    $days_since_start = floor(($target_timestamp - $start_timestamp) / (60 * 60 * 24));
    $current_week_number = floor($days_since_start / 7) + 1;
    
    // شروع هفته جاری
    $current_week_start_timestamp = $start_timestamp + ($current_week_number - 1) * 7 * 24 * 60 * 60;
    $current_week_start = jDateTime::date('Y/m/d', $current_week_start_timestamp);
    $current_week_end_timestamp = $current_week_start_timestamp + 6 * 24 * 60 * 60;
    $current_week_end = jDateTime::date('Y/m/d', $current_week_end_timestamp);
    
    // هفته قبل
    $prev_week_start_timestamp = $current_week_start_timestamp - 7 * 24 * 60 * 60;
    $prev_week_start = jDateTime::date('Y/m/d', $prev_week_start_timestamp);
    $prev_week_end_timestamp = $prev_week_start_timestamp + 6 * 24 * 60 * 60;
    $prev_week_end = jDateTime::date('Y/m/d', $prev_week_end_timestamp);
    
    return [
        'current_week_start' => $current_week_start,
        'current_week_end' => $current_week_end,
        'prev_week_start' => $prev_week_start,
        'prev_week_end' => $prev_week_end,
        'current_week_number' => $current_week_number
    ];
}

// ========== آمار کاربر عادی ==========
if ($action == 'get_user_stats') {
    
    // دریافت تاریخ امروز شمسی
    $today_shamsi = jDateTime::date('Y/m/d');
    
    // ========== دریافت اولین تاریخ ثبت کاربر ==========
    $stmt = $db->prepare("SELECT MIN(delivery_date) as first_date FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_first_date = $stmt->fetchColumn();
    
    // اگر کاربر سند ندارد
    if (!$user_first_date) {
        echo json_encode([
            'success' => true,
            'today_count' => 0,
            'yesterday_count' => 0,
            'total_docs' => 0,
            'first_date' => '-',
            'last_date' => '-',
            'most_company' => '-',
            'most_count' => 0,
            'least_company' => '-',
            'least_count' => 0,
            'trend_text' => '● 0',
            'trend_class' => 'color: #f59e0b;',
            'trend_icon' => '➖',
            'week_change' => '⏳',
            'week_change_class' => 'avg-change-neutral',
            'month_change' => '⏳',
            'month_change_class' => 'avg-change-neutral',
            'year_change' => '⏳',
            'year_change_class' => 'avg-change-neutral'
        ]);
        exit;
    }
    
    // ========== محاسبه هفته بر اساس اولین تاریخ ثبت کاربر ==========
    $week_info = get_week_range_based_on_start($user_first_date, $today_shamsi);
    $current_week_start = $week_info['current_week_start'];
    $current_week_end = $week_info['current_week_end'];
    $prev_week_start = $week_info['prev_week_start'];
    $prev_week_end = $week_info['prev_week_end'];
    $current_week_number = $week_info['current_week_number'];
    
    // ========== محاسبه ماه جاری و ماه قبل (بر اساس تقویم عادی) ==========
    $current_month_start = jDateTime::date('Y/m/01');
    $current_month_end = jDateTime::date('Y/m/t');
    
    $prev_month_start = jDateTime::date('Y/m/01', strtotime('-1 month'));
    $prev_month_end = jDateTime::date('Y/m/t', strtotime('-1 month'));
    
    // ========== محاسبه سال جاری و سال قبل ==========
    $current_year_start = jDateTime::date('Y/01/01');
    $prev_year_start = jDateTime::date('Y/01/01', strtotime('-1 year'));
    $prev_year_end = jDateTime::date('Y/12/30', strtotime('-1 year'));
    
    // ========== تعیین کامل بودن هفته جاری ==========
    // هفته کامل شده است؟ (آیا امروز >= آخرین روز هفته جاری)
    $current_week_end_timestamp = shamsi_to_timestamp($current_week_end);
    $today_timestamp = shamsi_to_timestamp($today_shamsi);
    $is_week_complete = ($today_timestamp >= $current_week_end_timestamp);
    
    // ========== تعیین کامل بودن ماه ==========
    $current_day = (int)jDateTime::date('j');
    $current_month_last_day = (int)jDateTime::date('t');
    $is_month_complete = ($current_day == $current_month_last_day);
    
    // ========== تعیین کامل بودن سال ==========
    $current_month_num = (int)jDateTime::date('m');
    $is_year_complete = ($current_month_num == 12 && $current_day >= 29);
    
    // دریافت آخرین تاریخ تحویل کاربر
    $stmt = $db->prepare("SELECT MAX(delivery_date) as last_date FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $last_date = $stmt->fetchColumn();
    
    if ($last_date) {
        // تعداد اسناد در آخرین تاریخ تحویل (لیست آخر)
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $last_date]);
        $today_count = $stmt->fetchColumn();
        
        // دریافت تاریخ قبل از آخرین تاریخ تحویل
        $stmt = $db->prepare("SELECT DISTINCT delivery_date FROM documents WHERE user_id = ? AND delivery_date < ? ORDER BY delivery_date DESC");
        $stmt->execute([$user_id, $last_date]);
        $prev_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($prev_dates)) {
            $prev_date = $prev_dates[0];
            $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
            $stmt->execute([$user_id, $prev_date]);
            $yesterday_count = $stmt->fetchColumn();
        } else {
            $yesterday_count = 0;
        }
    } else {
        $today_count = 0;
        $yesterday_count = 0;
        $last_date = null;
    }
    
    // کل اسناد
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_docs = $stmt->fetchColumn();
    
    // اولین و آخرین تاریخ
    $stmt = $db->prepare("SELECT MIN(delivery_date) as first_date, MAX(delivery_date) as last_date FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $dates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // بیشترین و کمترین شرکت
    $stmt = $db->prepare("
        SELECT c.name as company_name, COUNT(d.id) as doc_count 
        FROM documents d 
        JOIN companies c ON d.company_id = c.id 
        WHERE d.user_id = ? 
        GROUP BY d.company_id 
        ORDER BY doc_count DESC
    ");
    $stmt->execute([$user_id]);
    $company_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $most_company = !empty($company_stats) ? $company_stats[0]['company_name'] : '-';
    $most_count = !empty($company_stats) ? $company_stats[0]['doc_count'] : 0;
    $least_company = !empty($company_stats) ? $company_stats[count($company_stats)-1]['company_name'] : '-';
    $least_count = !empty($company_stats) ? $company_stats[count($company_stats)-1]['doc_count'] : 0;
    
    // ========== محاسبه هفته جاری (تا امروز) و هفته کامل قبل ==========
    // هفته کامل قبل
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_week_start, $prev_week_end]);
    $prev_week_count = $stmt->fetchColumn();
    
    // هفته جاری (تا امروز)
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $current_week_start, $today_shamsi]);
    $week_count = $stmt->fetchColumn();
    
    // ========== محاسبه ماه جاری (تا امروز) و ماه کامل قبل ==========
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_month_start, $prev_month_end]);
    $prev_month_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $current_month_start, $today_shamsi]);
    $month_count = $stmt->fetchColumn();
    
    // ========== محاسبه سال جاری (تا امروز) و سال کامل قبل ==========
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_year_start, $prev_year_end]);
    $prev_year_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $current_year_start, $today_shamsi]);
    $year_count = $stmt->fetchColumn();
    
    // ========== تغییرات ==========
    
    // تغییر نسبت به لیست قبل
    if ($yesterday_count == 0 && $today_count > 0) {
        $trend_text = "▲ +{$today_count}";
        $trend_class = "color: #10b981;";
        $trend_icon = "📈";
    } elseif ($yesterday_count > 0) {
        $trend_diff = $today_count - $yesterday_count;
        if ($trend_diff > 0) {
            $trend_text = "▲ +{$trend_diff}";
            $trend_class = "color: #10b981;";
            $trend_icon = "📈";
        } elseif ($trend_diff < 0) {
            $trend_text = "▼ {$trend_diff}";
            $trend_class = "color: #ef4444;";
            $trend_icon = "📉";
        } else {
            $trend_text = "● 0";
            $trend_class = "color: #f59e0b;";
            $trend_icon = "➖";
        }
    } else {
        $trend_text = "● 0";
        $trend_class = "color: #f59e0b;";
        $trend_icon = "➖";
    }
    
    // تغییر هفته (بر اساس هفته‌های کاربر)
    $has_week_doc = ($week_count > 0);
    
    // اگر هفته اول کاربر است و کامل نشده، ساعت شنی نشان بده
    if ($current_week_number == 1 && !$is_week_complete) {
        $week_change = '⏳';
        $week_change_class = 'avg-change-neutral';
    } elseif ($is_week_complete && $has_week_doc) {
        $week_diff = $week_count - $prev_week_count;
        if ($week_diff > 0) {
            $week_change = "▲ +{$week_diff}";
            $week_change_class = 'avg-change-up';
        } elseif ($week_diff < 0) {
            $week_change = "▼ {$week_diff}";
            $week_change_class = 'avg-change-down';
        } else {
            $week_change = "● 0";
            $week_change_class = 'avg-change-neutral';
        }
    } else {
        $week_change = '⏳';
        $week_change_class = 'avg-change-neutral';
    }
    
    // تغییر ماه
    $has_month_doc = ($month_count > 0);
    
    if ($is_month_complete && $has_month_doc) {
        $month_diff = $month_count - $prev_month_count;
        if ($month_diff > 0) {
            $month_change = "▲ +{$month_diff}";
            $month_change_class = 'avg-change-up';
        } elseif ($month_diff < 0) {
            $month_change = "▼ {$month_diff}";
            $month_change_class = 'avg-change-down';
        } else {
            $month_change = "● 0";
            $month_change_class = 'avg-change-neutral';
        }
    } else {
        $month_change = '⏳';
        $month_change_class = 'avg-change-neutral';
    }
    
    // تغییر سال
    $has_year_doc = ($year_count > 0);
    
    if ($is_year_complete && $has_year_doc) {
        $year_diff = $year_count - $prev_year_count;
        if ($year_diff > 0) {
            $year_change = "▲ +{$year_diff}";
            $year_change_class = 'avg-change-up';
        } elseif ($year_diff < 0) {
            $year_change = "▼ {$year_diff}";
            $year_change_class = 'avg-change-down';
        } else {
            $year_change = "● 0";
            $year_change_class = 'avg-change-neutral';
        }
    } else {
        $year_change = '⏳';
        $year_change_class = 'avg-change-neutral';
    }
    
    echo json_encode([
        'success' => true,
        'today_count' => $today_count,
        'yesterday_count' => $yesterday_count,
        'total_docs' => $total_docs,
        'first_date' => $dates['first_date'] ? toPersianNumber($dates['first_date']) : '-',
        'last_date' => $dates['last_date'] ? toPersianNumber($dates['last_date']) : '-',
        'most_company' => $most_company,
        'most_count' => $most_count,
        'least_company' => $least_company,
        'least_count' => $least_count,
        'trend_text' => $trend_text,
        'trend_class' => $trend_class,
        'trend_icon' => $trend_icon,
        'week_change' => $week_change,
        'week_change_class' => $week_change_class,
        'month_change' => $month_change,
        'month_change_class' => $month_change_class,
        'year_change' => $year_change,
        'year_change_class' => $year_change_class
    ]);
    exit;
}

// ========== آمار ادمین - دریافت کاربران ==========
if ($action == 'get_admin_users_stats') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // دریافت تاریخ امروز شمسی
    $today_shamsi = jDateTime::date('Y/m/d');
    
    // ========== محاسبه خودکار هفته جاری و هفته قبل ==========
    $timestamp = shamsi_to_timestamp($today_shamsi);
    $weekday = date('w', $timestamp); // 0=یکشنبه, 6=شنبه
    $weekday = ($weekday + 1) % 7; // تبدیل به شنبه=0
    
    // اول هفته جاری (شنبه)
    $current_week_start_timestamp = strtotime("-$weekday days", $timestamp);
    $current_week_start = jDateTime::date('Y/m/d', $current_week_start_timestamp);
    $current_week_end = jDateTime::date('Y/m/d', strtotime('+6 days', $current_week_start_timestamp));
    
    // هفته قبل
    $prev_week_start_timestamp = strtotime('-7 days', $current_week_start_timestamp);
    $prev_week_start = jDateTime::date('Y/m/d', $prev_week_start_timestamp);
    $prev_week_end = jDateTime::date('Y/m/d', strtotime('+6 days', $prev_week_start_timestamp));
    
    // ========== محاسبه آمار کل اسناد ثبت شده ==========
    $stmt = $db->query("SELECT COUNT(*) FROM documents");
    $total_approved = $stmt->fetchColumn();
    
    // دریافت تمام تاریخ‌های تحویل موجود به ترتیب نزولی
    $stmt = $db->query("SELECT DISTINCT delivery_date FROM documents ORDER BY delivery_date DESC");
    $all_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($all_dates) >= 1) {
        $last_date = $all_dates[0];
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date = ?");
        $stmt->execute([$last_date]);
        $today_approved = $stmt->fetchColumn();
        
        if (count($all_dates) >= 2) {
            $prev_last_date = $all_dates[1];
            $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date = ?");
            $stmt->execute([$prev_last_date]);
            $yesterday_approved = $stmt->fetchColumn();
        } else {
            $yesterday_approved = 0;
        }
    } else {
        $today_approved = 0;
        $yesterday_approved = 0;
    }
    
    // محاسبه کل اسناد هفته جاری
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$current_week_start, $current_week_end]);
    $week_approved = (int)$stmt->fetchColumn();
    
    // محاسبه کل اسناد هفته قبل
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$prev_week_start, $prev_week_end]);
    $prev_week_approved = (int)$stmt->fetchColumn();
    
    // ========== محاسبه ماه جاری و ماه قبل ==========
    $current_month_start = jDateTime::date('Y/m/01');
    $current_month_end = jDateTime::date('Y/m/t');
    
    $prev_month_start = jDateTime::date('Y/m/01', strtotime('-1 month'));
    $prev_month_end = jDateTime::date('Y/m/t', strtotime('-1 month'));
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$current_month_start, $current_month_end]);
    $month_approved = (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$prev_month_start, $prev_month_end]);
    $prev_month_approved = (int)$stmt->fetchColumn();
    
    // ========== ساخت متن برای باکس کل اسناد ==========
    $vs_yesterday = $today_approved . ' سند | لیست قبلی :  ' . $yesterday_approved;
    $vs_yesterday_class = $today_approved > $yesterday_approved ? 'up' : ($today_approved < $yesterday_approved ? 'down' : 'neutral');
    
    $vs_last_week = ($week_approved > 0 ? $week_approved . ' سند' : '⏳') . ' | هفته قبل :  ' . $prev_week_approved;
    $vs_last_week_class = $week_approved > $prev_week_approved ? 'up' : ($week_approved < $prev_week_approved ? 'down' : 'neutral');
    
    $vs_last_month = ($month_approved > 0 ? $month_approved . ' سند' : '⏳') . ' | ماه قبل :  ' . ($prev_month_approved > 0 ? $prev_month_approved : '⏳');
    $vs_last_month_class = $month_approved > $prev_month_approved ? 'up' : ($month_approved < $prev_month_approved ? 'down' : 'neutral');
    
    $stmt = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY fullname");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($users as $user) {
        $user_id_val = $user['id'];
        
        $stmt = $db->prepare("SELECT MAX(delivery_date) as last_delivery_date FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $last_date = $stmt->fetchColumn();
        
        if ($last_date) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
            $stmt->execute([$user_id_val, $last_date]);
            $today_count = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT DISTINCT delivery_date FROM documents WHERE user_id = ? AND delivery_date < ? ORDER BY delivery_date DESC");
            $stmt->execute([$user_id_val, $last_date]);
            $prev_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($prev_dates)) {
                $prev_date = $prev_dates[0];
                $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
                $stmt->execute([$user_id_val, $prev_date]);
                $yesterday_count = $stmt->fetchColumn();
            } else {
                $yesterday_count = 0;
            }
        } else {
            $today_count = 0;
            $yesterday_count = 0;
            $last_date = null;
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $total_docs = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT MIN(delivery_date) as first_date FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $first_date = $stmt->fetchColumn();
        if (!$first_date) $first_date = '-';
        
        // اسناد این هفته برای کاربر
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $current_week_start, $current_week_end]);
        $week_count = $stmt->fetchColumn();
        
        // اسناد هفته قبل برای کاربر
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_week_start, $prev_week_end]);
        $prev_week_count = $stmt->fetchColumn();
        
        // اسناد این ماه برای کاربر
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $current_month_start, $current_month_end]);
        $month_count = $stmt->fetchColumn();
        
        // اسناد ماه قبل برای کاربر
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_month_start, $prev_month_end]);
        $prev_month_count = $stmt->fetchColumn();
        
        // تغییرات نسبت به لیست قبل
        if ($yesterday_count == 0 && $today_count > 0) {
            $trend_text = "▲ +{$today_count}";
            $trend_class = 'trend-up';
        } elseif ($yesterday_count > 0) {
            $trend_diff = $today_count - $yesterday_count;
            $trend_text = $trend_diff > 0 ? "▲ +{$trend_diff}" : ($trend_diff < 0 ? "▼ {$trend_diff}" : "● 0");
            $trend_class = $trend_diff > 0 ? 'trend-up' : ($trend_diff < 0 ? 'trend-down' : 'trend-neutral');
        } else {
            $trend_text = "● 0";
            $trend_class = 'trend-neutral';
        }
        
        // تغییرات هفته
        $week_change = '⏳';
        $week_change_class = 'avg-change-neutral';
        if ($prev_week_count > 0) {
            $diff = $week_count - $prev_week_count;
            $week_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $week_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        // تغییرات ماه
        $month_change = '⏳';
        $month_change_class = 'avg-change-neutral';
        if ($prev_month_count > 0) {
            $diff = $month_count - $prev_month_count;
            $month_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $month_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        // تغییرات سال
        $year_change = '⏳';
        $year_change_class = 'avg-change-neutral';
        if ($prev_year_count > 0) {
            $diff = $year_count - $prev_year_count;
            $year_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $year_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        $result[] = [
            'id' => $user_id_val,
            'fullname' => $user['fullname'],
            'unit_name' => $user['unit_name'],
            'pending_today' => $today_count,
            'total_docs' => $total_docs,
            'yesterday_count' => $yesterday_count,
            'last_delivery_date' => $last_date,
            'trend_text' => $trend_text,
            'trend_class' => $trend_class,
            'week_change' => $week_change,
            'week_change_class' => $week_change_class,
            'month_change' => $month_change,
            'month_change_class' => $month_change_class,
            'year_change' => $year_change,
            'year_change_class' => $year_change_class,
            'first_date' => $first_date
        ];
    }
    
    // پیدا کردن بیشترین و کمترین
    $max_user = null;
    $min_users_count = 0;
    $min_docs = null;
    $min_users_names = [];
    
    if (!empty($result)) {
        $max_docs = max(array_column($result, 'total_docs'));
        $min_docs = min(array_column($result, 'total_docs'));
        
        foreach ($result as $user) {
            if ($user['total_docs'] == $max_docs) {
                $max_user = $user;
            }
            if ($user['total_docs'] == $min_docs) {
                $min_users_count++;
                $min_users_names[] = $user['fullname'];
            }
        }
    }
    
    $min_user_info = null;
    if ($min_users_count > 0) {
        if ($min_users_count == 1) {
            $first_date_min = '-';
            foreach ($result as $user) {
                if ($user['total_docs'] == $min_docs) {
                    $first_date_min = $user['first_date'];
                    break;
                }
            }
            $min_user_info = [
                'fullname' => $min_users_names[0],
                'total_docs' => $min_docs,
                'first_date' => $first_date_min,
                'count' => 1
            ];
        } else {
            $min_user_info = [
                'fullname' => count($min_users_names) . ' کاربر',
                'total_docs' => $min_docs,
                'first_date' => '-',
                'count' => $min_users_count,
                'users_list' => $min_users_names
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'users' => $result,
        'max_user' => $max_user,
        'min_user' => $min_user_info,
        'today_date' => toPersianNumber($today_shamsi),
        'total_approved' => (int)$total_approved,
        'today_approved' => (int)$today_approved,
        'yesterday_approved' => (int)$yesterday_approved,
        'week_approved' => (int)$week_approved,
        'prev_week_approved' => (int)$prev_week_approved,
        'month_approved' => (int)$month_approved,
        'prev_month_approved' => (int)$prev_month_approved,
        'vs_yesterday' => $vs_yesterday,
        'vs_yesterday_class' => $vs_yesterday_class,
        'vs_last_week' => $vs_last_week,
        'vs_last_week_class' => $vs_last_week_class,
        'vs_last_month' => $vs_last_month,
        'vs_last_month_class' => $vs_last_month_class
    ]);
    exit;
}

// ========== آمار ادمین - دریافت آمار یک کاربر خاص ==========
if ($action == 'get_admin_user_detail_stats') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $target_user_id = $_GET['user_id'] ?? 0;
    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        exit;
    }
    
    $today = jDateTime::date('Y/m/d');
    $yesterday = jDateTime::date('Y/m/d', strtotime('-1 days'));
    $week_ago = jDateTime::date('Y/m/d', strtotime('-7 days'));
    $month_ago = jDateTime::date('Y/m/d', strtotime('-30 days'));
    $year_ago = jDateTime::date('Y/m/d', strtotime('-365 days'));
    
    $stmt = $db->prepare("SELECT fullname, unit_name FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$target_user_id, $today]);
    $pending_today = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $total_docs = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$target_user_id, $yesterday]);
    $yesterday_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$target_user_id, $week_ago, $today]);
    $week_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$target_user_id, $month_ago, $today]);
    $month_count = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$target_user_id, $year_ago, $today]);
    $year_count = $stmt->fetchColumn();
    
    $trend = '';
    $trend_class = '';
    if ($yesterday_count > 0) {
        $change = $total_docs - $yesterday_count;
        $trend = $change > 0 ? "▲ +{$change}" : ($change < 0 ? "▼ {$change}" : "● بدون تغییر");
        $trend_class = $change > 0 ? "color: #10b981;" : ($change < 0 ? "color: #ef4444;" : "color: #f59e0b;");
    } else {
        $trend = $total_docs > 0 ? "▲ +{$total_docs}" : "● بدون سند";
        $trend_class = $total_docs > 0 ? "color: #10b981;" : "color: #6c86a3;";
    }
    
    echo json_encode([
        'success' => true,
        'user_info' => $user_info,
        'pending_today' => $pending_today,
        'total_docs' => $total_docs,
        'yesterday_count' => $yesterday_count,
        'week_count' => $week_count,
        'month_count' => $month_count,
        'year_count' => $year_count,
        'trend' => $trend,
        'trend_class' => $trend_class,
        'today_date' => toPersianNumber($today)
    ]);
    exit;
}

// ========== دریافت تاریخ‌های بایگانی شده برای کاربر عادی ==========
if ($action == 'get_archived_delivery_dates') {
    $sql = "SELECT DISTINCT da.delivery_date 
            FROM delivery_approvals da 
            WHERE da.user_id = :user_id 
            AND da.user_approved_at IS NOT NULL 
            AND da.admin_approved_at IS NOT NULL 
            ORDER BY da.delivery_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'delivery_date' => toPersianNumber($row['delivery_date']),
            'delivery_date_raw' => $row['delivery_date']
        ];
    }
    
    echo json_encode(['success' => true, 'dates' => $formatted]);
    exit;
}

// ========== دریافت تمام تاریخ‌های بایگانی شده برای ادمین ==========
if ($action == 'get_all_archived_dates') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $sql = "SELECT DISTINCT da.delivery_date, da.user_id, u.fullname as user_name 
            FROM delivery_approvals da 
            JOIN users u ON da.user_id = u.id
            WHERE da.user_approved_at IS NOT NULL 
            AND da.admin_approved_at IS NOT NULL 
            ORDER BY da.delivery_date DESC";
    
    $stmt = $db->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'delivery_date' => toPersianNumber($row['delivery_date']),
            'delivery_date_raw' => $row['delivery_date'],
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name']
        ];
    }
    
    echo json_encode(['success' => true, 'dates' => $formatted]);
    exit;
}

// ========== ذخیره توضیحات برای تاریخ تحویل ==========
if ($action == 'add_delivery_description') {
    $data = json_decode(file_get_contents('php://input'), true);
    $delivery_date = $data['delivery_date'] ?? '';
    $description = $data['description'] ?? '';
    
    if (empty($delivery_date)) {
        echo json_encode(['success' => false, 'error' => 'تاریخ تحویل مشخص نیست']);
        exit;
    }
    
    // اطمینان از وجود رکورد
    $stmt = $db->prepare("INSERT IGNORE INTO delivery_approvals (user_id, delivery_date) VALUES (?, ?)");
    $stmt->execute([$user_id, $delivery_date]);
    
    // ذخیره توضیح در دیتابیس
    $stmt = $db->prepare("UPDATE delivery_approvals SET description = ? WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$description, $user_id, $delivery_date]);
    
    echo json_encode(['success' => true]);
    exit;
}

// ========== دریافت توضیحات برای تاریخ تحویل ==========
if ($action == 'get_delivery_description') {
    $delivery_date = $_GET['delivery_date'] ?? '';
    
    $stmt = $db->prepare("SELECT description FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$user_id, $delivery_date]);
    $description = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'description' => $description]);
    exit;
}

// ========== دریافت دسترسی کاربر به بایگانی دیگران ==========
if ($action == 'get_user_archive_permission') {
    $stmt = $db->prepare("SELECT can_view_all_archives FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $can_view = $stmt->fetchColumn();
    
    echo json_encode(['success' => true, 'can_view_all' => $can_view == 1]);
    exit;
}

// دریافت لیست همه کاربران برای انتخاب
if ($action == 'get_all_users_for_archive') {
    $stmt = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY fullname");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ========== دریافت بایگانی یک کاربر خاص (فقط ۲ تاریخ آخر) ==========
if ($action == 'get_archived_delivery_dates_for_user') {
    $target_user_id = $_GET['user_id'] ?? 0;
    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        exit;
    }
    
    // فقط ۲ تاریخ آخر
    $sql = "SELECT DISTINCT da.delivery_date 
            FROM delivery_approvals da 
            WHERE da.user_id = :user_id 
            AND da.user_approved_at IS NOT NULL 
            AND da.admin_approved_at IS NOT NULL 
            ORDER BY da.delivery_date DESC 
            LIMIT 3";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $target_user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'delivery_date' => toPersianNumber($row['delivery_date']),
            'delivery_date_raw' => $row['delivery_date']
        ];
    }
    
    echo json_encode(['success' => true, 'dates' => $formatted]);
    exit;
}

// ========== دریافت بایگانی خود کاربر (همه تاریخ‌ها) ==========
if ($action == 'get_my_archived_dates') {
    $sql = "SELECT DISTINCT da.delivery_date 
            FROM delivery_approvals da 
            WHERE da.user_id = :user_id 
            AND da.user_approved_at IS NOT NULL 
            AND da.admin_approved_at IS NOT NULL 
            ORDER BY da.delivery_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'delivery_date' => toPersianNumber($row['delivery_date']),
            'delivery_date_raw' => $row['delivery_date']
        ];
    }
    
    echo json_encode(['success' => true, 'dates' => $formatted]);
    exit;
}

// تابع کمکی برای اصلاح نام شرکت
function cleanCompanyName($name) {
    // حذف "شرکت" یا "شركت" از ابتدا (با هر دو شکل ک/ك)
    $name = preg_replace('/^شرکت\s+|^شركت\s+/', '', $name);
    // حذف متن داخل پرانتز
    $name = preg_replace('/\s*\([^)]*\)\s*$/', '', $name);
    // حذف فاصله‌های اضافی
    $name = trim($name);
    
    // گرفتن دو کلمه اول
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return $words[0] . ' ' . $words[1];
    } elseif (count($words) == 1) {
        return $words[0];
    }
    return $name;
}

// ========== بارگذاری داده‌های گزارش برهان ==========
if ($action == 'load_report_data') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    $check_file = __DIR__ . '/../storage/reports/last_check.txt';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    // خواندن زمان آخرین بررسی از فایل (مقدار پیش‌فرض: خیلی قدیمی)
    $last_check = '1400/01/01 00:00:00';
    if (file_exists($check_file)) {
        $last_check = trim(file_get_contents($check_file));
    }
    
    $data = [];
    $new_records = [];
    $last_record_datetime = $last_check; // مقدار پیش‌فرض
    
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'error' => 'خطا در باز کردن فایل']);
        exit;
    }
    
    // نادیده گرفتن هدر اول
    fgetcsv($handle, 0, ',');
    // خواندن هدر دوم
    $header = fgetcsv($handle, 0, ',');
    
    // خواندن همه رکوردها
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if (count($row) > 1 && !empty(trim($row[1]))) {
            $row[1] = cleanCompanyName($row[1]);
            
            // تاریخ/زمان رکورد
            $record_datetime = $row[4] . ' ' . $row[5];
            
            // ذخیره آخرین تاریخ/زمان
            if ($record_datetime > $last_record_datetime) {
                $last_record_datetime = $record_datetime;
            }
            
            // بررسی جدید بودن نسبت به last_check
            $is_new = ($record_datetime > $last_check);
            $row['is_new'] = $is_new;
            
            if ($is_new) {
                $new_records[] = $row;
            }
            
            $data[] = $row;
        }
    }
    fclose($handle);
    
    // ✅ ذخیره آخرین تاریخ/زمان موجود در فایل CSV (نه زمان سیستم)
    file_put_contents($check_file, $last_record_datetime);
    
    $_SESSION['report_new_count'] = count($new_records);
    $_SESSION['report_new_data'] = $new_records;
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => count($data),
        'new_count' => count($new_records),
        'new_records' => $new_records,
        'last_check' => $last_check,
        'last_record_datetime' => $last_record_datetime
    ]);
    exit;
}

// ========== دریافت تعداد اعلان‌های گزارش ==========
if ($action == 'get_report_notification') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $new_count = $_SESSION['report_new_count'] ?? 0;
    echo json_encode(['success' => true, 'new_count' => $new_count]);
    exit;
}

// ========== پاک کردن اعلان گزارش ==========
if ($action == 'clear_report_notification') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $_SESSION['report_new_count'] = 0;
    $_SESSION['report_new_data'] = [];
    echo json_encode(['success' => true]);
    exit;
}

// ========== دریافت آمار لحظه‌ای گزارش برهان ==========
if ($action == 'get_report_stats') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $new_count = 0;
    $edit_count = 0;
    $delete_count = 0;
    $login_success_count = 0;
    $login_fail_count = 0;
    $total = 0;
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 4 && !empty(trim($row[4]))) {
                if ($row[4] == $date) {
                    $type = trim($row[6] ?? '');
                    
                    if (strpos($type, 'موجوديت جديد') !== false) {
                        $new_count++;
                        $total++;
                    } elseif (strpos($type, 'ويرايش') !== false) {
                        $edit_count++;
                        $total++;
                    } elseif (strpos($type, 'حذف') !== false) {
                        $delete_count++;
                        $total++;
                    } elseif (strpos($type, 'ورود موفق') !== false) {
                        $login_success_count++;
                    } elseif (strpos($type, 'ناموفق') !== false) {
                        $login_fail_count++;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    echo json_encode([
        'success' => true,
        'new_count' => $new_count,
        'edit_count' => $edit_count,
        'delete_count' => $delete_count,
        'login_success_count' => $login_success_count,
        'login_fail_count' => $login_fail_count,
        'total' => $total,
        'date' => $date
    ]);
    exit;
}

// ========== دریافت گزارش ثبت جدید (فقط حسابداری) ==========
if ($action == 'get_warehouse_report') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $user_counts = [];
    $raw_users = []; // برای دیباگ
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        // نادیده گرفتن هدر اول
        fgetcsv($handle, 0, ',');
        // خواندن هدر دوم
        fgetcsv($handle, 0, ',');
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // حداقل ستون‌های مورد نیاز را داشته باشد
            if (count($row) > 8) {
                $row_date = trim($row[4] ?? '');
                $system = trim($row[7] ?? '');
                $doc_type = trim($row[8] ?? '');
                $type = trim($row[6] ?? '');
                $user_raw = trim($row[3] ?? '');
                
                // شرط: تاریخ مورد نظر + سیستم حسابداری + نوع سند حسابداری + موجودیت جدید
                if ($row_date == $date && 
                    $system == 'حسابداري' && 
                    $doc_type == 'سند حسابداري' &&
                    strpos($type, 'موجوديت جديد') !== false) {
                    
                    // استخراج نام اصلی (حذف اعداد انتهای نام)
                    $clean_name = preg_replace('/[0-9]+$/', '', $user_raw);
                    if (empty($clean_name)) $clean_name = $user_raw;
                    
                    if (!isset($user_counts[$clean_name])) {
                        $user_counts[$clean_name] = 0;
                    }
                    $user_counts[$clean_name]++;
                }
            }
        }
        fclose($handle);
    }
    
    // مرتب‌سازی بر اساس تعداد (بیشترین اول)
    arsort($user_counts);
    
    $result = [];
    foreach ($user_counts as $name => $count) {
        $result[] = [
            'user_name' => $name,
            'count' => $count
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $result,
        'date' => $date,
        'total' => array_sum($user_counts),
        'raw_count' => count($user_counts) // تعداد کاربران
    ]);
    exit;
}

// ============================================================
// ========== فقط ادمین از اینجا به بعد =======================
// ============================================================
if (!$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// ========== آمار ادمین ==========
if ($action == 'get_admin_stats') {
    $target_user_id = isset($_GET['user_id']) && !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    
    if ($target_user_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
        $totalDocs = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT MIN(delivery_date) as first_date, MAX(delivery_date) as last_date FROM documents WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
        $dates = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date = ? AND user_id = ?");
        $stmt->execute([jDateTime::date('Y/m/d'), $target_user_id]);
        $todayCount = $stmt->fetchColumn();
        
        $stmt = $db->prepare("
            SELECT d.*, c.name as company_name, u.fullname as user_name
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            JOIN users u ON d.user_id = u.id
            WHERE d.user_id = ? 
            ORDER BY d.id DESC LIMIT 100
        ");
        $stmt->execute([$target_user_id]);
    } else {
        $totalDocs = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $dates = $db->query("SELECT MIN(delivery_date) as first_date, MAX(delivery_date) as last_date FROM documents")->fetch(PDO::FETCH_ASSOC);
        $todayCount = $db->query("SELECT COUNT(*) FROM documents WHERE delivery_date = '" . jDateTime::date('Y/m/d') . "'")->fetchColumn();
        $stmt = $db->query("
            SELECT d.*, c.name as company_name, u.fullname as user_name
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            JOIN users u ON d.user_id = u.id
            ORDER BY d.id DESC LIMIT 100
        ");
    }
    
    $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0")->fetchColumn();
    $totalCompanies = $db->query("SELECT COUNT(*) FROM companies WHERE is_active = 1")->fetchColumn();
    $recentDocs = $stmt->fetchAll();
    
    $formattedDocs = [];
    foreach ($recentDocs as $doc) {
        $formattedDocs[] = [
            'doc_number' => $doc['doc_number'],
            'doc_date' => $doc['doc_date'] == '-' ? '-' : toPersianNumber($doc['doc_date']),
            'company_name' => $doc['company_name'],
            'user_name' => $doc['user_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'total_docs' => $totalDocs,
        'total_users' => $totalUsers,
        'total_companies' => $totalCompanies,
        'today_count' => $todayCount,
        'first_date' => $dates['first_date'] ?? 'هیچ سندی ثبت نشده',
        'last_date' => $dates['last_date'] ?? '-',
        'today_date' => jDateTime::date('Y/m/d'),
        'recent_docs' => $formattedDocs
    ]);
    exit;
}

// ========== دریافت کاربران ==========
if ($action == 'get_users') {
    $stmt = $db->query("SELECT id, username, fullname, unit_name, require_doc_date, lock_delivery_date, can_view_all_archives,
                        (SELECT COUNT(*) FROM documents WHERE user_id = users.id) as total_docs 
                        FROM users WHERE is_admin = 0 ORDER BY unit_name");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ========== افزودن کاربر ==========
if ($action == 'add_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = password_hash($data['password'] ?? '', PASSWORD_DEFAULT);
    $fullname = $data['fullname'] ?? '';
    $unit_name = $data['unit_name'] ?? '';
    $require_doc_date = $data['require_doc_date'] ?? 1;
    
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'نام کاربری تکراری است']);
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO users (username, password, fullname, unit_name, require_doc_date, is_admin, can_view_all_archives) 
                          VALUES (?, ?, ?, ?, ?, 0, 0)");
    $result = $stmt->execute([$username, $password, $fullname, $unit_name, $require_doc_date]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== ویرایش کاربر ==========
if ($action == 'update_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $fullname = $data['fullname'] ?? '';
    $username = $data['username'] ?? '';
    $unit_name = $data['unit_name'] ?? '';
    $require_doc_date = $data['require_doc_date'] ?? 0;
    $can_view_all_archives = $data['can_view_all_archives'] ?? 0;
    $password = $data['password'] ?? '';
    
    $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check->execute([$username, $id]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'نام کاربری تکراری است']);
        exit;
    }
    
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET username=?, fullname=?, unit_name=?, require_doc_date=?, can_view_all_archives=?, password=? WHERE id=? AND is_admin=0");
        $result = $stmt->execute([$username, $fullname, $unit_name, $require_doc_date, $can_view_all_archives, $hashed_password, $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET username=?, fullname=?, unit_name=?, require_doc_date=?, can_view_all_archives=? WHERE id=? AND is_admin=0");
        $result = $stmt->execute([$username, $fullname, $unit_name, $require_doc_date, $can_view_all_archives, $id]);
    }
    echo json_encode(['success' => $result]);
    exit;
}

// ========== تغییر دسترسی بایگانی کاربر ==========
if ($action == 'toggle_archive_permission') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $data['user_id'] ?? 0;
    $can_view_all_archives = $data['can_view_all_archives'] ?? 0;
    
    $stmt = $db->prepare("UPDATE users SET can_view_all_archives = ? WHERE id = ? AND is_admin = 0");
    $result = $stmt->execute([$can_view_all_archives, $target_user_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== حذف کاربر ==========
if ($action == 'delete_user') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    $db->prepare("DELETE FROM documents WHERE user_id = ?")->execute([$id]);
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
    $result = $stmt->execute([$id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

// ========== شرکت‌ها ==========
if ($action == 'get_companies') {
    $stmt = $db->query("SELECT * FROM companies WHERE is_active = 1 ORDER BY id ASC");
    $companies = $stmt->fetchAll();
    echo json_encode(['success' => true, 'companies' => $companies]);
    exit;
}

if ($action == 'add_company') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    $stmt = $db->prepare("INSERT INTO companies (name, is_active) VALUES (?, 1)");
    $result = $stmt->execute([$name]);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action == 'edit_company') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $name = $data['name'] ?? '';
    $stmt = $db->prepare("UPDATE companies SET name = ? WHERE id = ?");
    $result = $stmt->execute([$name, $id]);
    echo json_encode(['success' => $result]);
    exit;
}

if ($action == 'toggle_company') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $is_active = $data['is_active'] ?? 1;
    $stmt = $db->prepare("UPDATE companies SET is_active = ? WHERE id = ?");
    $result = $stmt->execute([$is_active, $id]);
    echo json_encode(['success' => $result]);
    exit;
}

// ========== جستجوی اسناد ادمین ==========
if ($action == 'search_admin_documents') {
    $admin_user_id = isset($_GET['admin_user_id']) && $_GET['admin_user_id'] !== '' ? (int)$_GET['admin_user_id'] : null;
    
    $sql = "SELECT d.*, c.name as company_name, u.fullname as user_fullname, u.unit_name as user_unit 
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            JOIN users u ON d.user_id = u.id
            WHERE 1=1";
    $params = [];
    
    if ($admin_user_id) {
        $sql .= " AND d.user_id = :user_id";
        $params[':user_id'] = $admin_user_id;
    }
    
    $doc_number = $_GET['doc_number'] ?? '';
    $doc_date = $_GET['doc_date'] ?? '';
    $company_id = $_GET['company_id'] ?? '';
    $delivery_date = $_GET['delivery_date'] ?? '';
    
    if (!empty($doc_number)) {
        $sql .= " AND d.doc_number LIKE :doc_number";
        $params[':doc_number'] = "$doc_number%";
    }
    if (!empty($doc_date)) {
        $sql .= " AND d.doc_date LIKE :doc_date";
        $params[':doc_date'] = "%$doc_date%";
    }
    if (!empty($company_id)) {
        $sql .= " AND d.company_id = :company_id";
        $params[':company_id'] = $company_id;
    }
    if (!empty($delivery_date)) {
        $sql .= " AND d.delivery_date = :delivery_date";
        $params[':delivery_date'] = $delivery_date;
    }
    
    $sql .= " ORDER BY d.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $docs = $stmt->fetchAll();
    
    $result = [];
    foreach ($docs as $doc) {
        $result[] = [
            'id' => $doc['id'],
            'doc_number' => $doc['doc_number'],
            'doc_date' => $doc['doc_date'],
            'delivery_date' => $doc['delivery_date'],
            'company_name' => $doc['company_name'],
            'description' => $doc['description'],
            'user_fullname' => $doc['user_fullname'],
            'user_unit' => $doc['user_unit'],
            'created_at' => $doc['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'documents' => $result]);
    exit;
}

// ========== دریافت لیست کاربران دارای تایید pending ==========
if ($action == 'get_users_with_pending_approvals') {
    $stmt = $db->query("
        SELECT DISTINCT da.user_id, u.fullname, u.unit_name, COUNT(da.id) as pending_count
        FROM delivery_approvals da
        JOIN users u ON da.user_id = u.id
        WHERE da.user_approved_at IS NOT NULL AND da.admin_approved_at IS NULL
        GROUP BY da.user_id
        ORDER BY u.fullname ASC
    ");
    $users = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ========== دریافت لیست تاریخ‌های تایید نشده یک کاربر خاص ==========
if ($action == 'get_user_pending_dates') {
    $target_user_id = $_GET['user_id'] ?? 0;
    if (empty($target_user_id)) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        exit;
    }
    
    $stmt = $db->prepare("
        SELECT delivery_date, user_approved_at 
        FROM delivery_approvals 
        WHERE user_id = ? AND user_approved_at IS NOT NULL AND admin_approved_at IS NULL
        ORDER BY delivery_date DESC
    ");
    $stmt->execute([$target_user_id]);
    $dates = $stmt->fetchAll();
    
    foreach ($dates as &$date) {
        $date['delivery_date_persian'] = toPersianNumber($date['delivery_date']);
        $date['has_user_approval'] = true;
        $date['user_approved'] = true;
    }
    
    echo json_encode(['success' => true, 'dates' => $dates]);
    exit;
}

// ========== دریافت اسناد تایید شده نهایی (بایگانی ادمین) ==========
if ($action == 'get_all_approved_approvals') {
    $stmt = $db->query("
        SELECT da.*, u.fullname, u.unit_name, u.id as user_id
        FROM delivery_approvals da
        JOIN users u ON da.user_id = u.id
        WHERE da.user_approved_at IS NOT NULL AND da.admin_approved_at IS NOT NULL
        ORDER BY da.admin_approved_at DESC
        LIMIT 200
    ");
    $approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($approvals as &$app) {
        $app['delivery_date_persian'] = toPersianNumber($app['delivery_date']);
        // تبدیل تاریخ میلادی دیتابیس به شمسی
        if (!empty($app['admin_approved_at'])) {
            $timestamp = strtotime($app['admin_approved_at']);
            $app['admin_approved_at_fa'] = jDateTime::date('Y/m/d', $timestamp);
        } else {
            $app['admin_approved_at_fa'] = '-';
        }
    }
    
    echo json_encode(['success' => true, 'approvals' => $approvals]);
    exit;
}

// ========== بررسی تایید ادمین برای یک تاریخ تحویل ==========
if ($action == 'check_admin_approval') {
    $delivery_date = $_GET['delivery_date'] ?? '';
    
    if (empty($delivery_date)) {
        echo json_encode(['has_admin_approval' => false]);
        exit;
    }
    
    $stmt = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$user_id, $delivery_date]);
    $admin_approved = $stmt->fetchColumn();
    
    echo json_encode(['has_admin_approval' => !empty($admin_approved)]);
    exit;
}

// ========== بررسی تاریخ‌های تایید شده برای کاربر ==========
if ($action == 'get_locked_dates') {
    $stmt = $db->prepare("SELECT delivery_date FROM delivery_approvals WHERE user_id = ? AND admin_approved_at IS NOT NULL");
    $stmt->execute([$user_id]);
    $locked_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode(['success' => true, 'locked_dates' => $locked_dates]);
    exit;
}

// ========== دریافت اطلاعات کامل برای بخش بایگانی (ادمین) ==========
if ($action == 'get_archive_list') {
    $stmt = $db->query("
        SELECT da.*, u.fullname, u.unit_name,
               (SELECT COUNT(*) FROM documents WHERE user_id = da.user_id AND delivery_date = da.delivery_date) as total_docs
        FROM delivery_approvals da
        JOIN users u ON da.user_id = u.id
        WHERE da.admin_approved_at IS NOT NULL
        ORDER BY da.admin_approved_at DESC
    ");
    $approvals = $stmt->fetchAll();
    
    foreach ($approvals as &$item) {
        if (!empty($item['admin_approved_at'])) {
            $timestamp = strtotime($item['admin_approved_at']);
            $item['admin_approved_at_fa'] = jDateTime::date('Y/m/d', $timestamp);
        } else {
            $item['admin_approved_at_fa'] = '-';
        }
    }
    
    echo json_encode(['success' => true, 'approvals' => $approvals]);
    exit;
}

// ========== تغییر دسترسی بایگانی کاربر ==========
if ($action == 'toggle_archive_permission') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $target_user_id = $data['user_id'] ?? 0;
    $can_view_all_archives = $data['can_view_all_archives'] ?? 0;
    
    $stmt = $db->prepare("UPDATE users SET can_view_all_archives = ? WHERE id = ? AND is_admin = 0");
    $result = $stmt->execute([$can_view_all_archives, $target_user_id]);
    
    echo json_encode(['success' => $result]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>