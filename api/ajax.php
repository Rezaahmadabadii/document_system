<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
        $params[':doc_number'] = "%$doc_number%";
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
    
    $data = json_decode(file_get_contents('php://input'), true);
    $delivery_date = $data['delivery_date'] ?? jDateTime::date('Y/m/d');
    $company_id = $data['company_id'] ?? 0;
    $doc_number = $data['doc_number'] ?? '';
    $doc_date = $data['doc_date'] ?? '-';
    $description = $data['description'] ?? '';
    
    if (empty($doc_number)) {
        echo json_encode(['success' => false, 'error' => 'شماره سند الزامی است']);
        exit;
    }
    
    if ($doc_date !== '-') {
        $doc_date = toEnglishNumber($doc_date);
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
    
    $can_edit = $is_admin || (time() - strtotime($doc['created_at'])) <= (2 * 86400);
    if (!$can_edit) {
        echo json_encode(['success' => false, 'error' => 'امکان ویرایش بعد از ۲ روز وجود ندارد']);
        exit;
    }
    
    if ($doc_date !== '-') {
        $doc_date = toEnglishNumber($doc_date);
    }
    
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

// ========== آمار کاربر عادی ==========
if ($action == 'get_user_stats') {
    $today = jDateTime::date('Y/m/d');
    $yesterday = jDateTime::date('Y/m/d', strtotime('-1 days'));
    
    // اسناد امروز
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$user_id, $today]);
    $today_count = $stmt->fetchColumn();
    
    // اسناد دیروز
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
    $stmt->execute([$user_id, $yesterday]);
    $yesterday_count = $stmt->fetchColumn();
    
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
    
    // ========== هفته جاری (7 روز اخیر) ==========
    $week_ago = jDateTime::date('Y/m/d', strtotime('-7 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $week_ago, $today]);
    $week_count = $stmt->fetchColumn();
    
    // هفته قبل (7 روز قبل از هفته جاری)
    $prev_week_start = jDateTime::date('Y/m/d', strtotime('-14 days'));
    $prev_week_end = jDateTime::date('Y/m/d', strtotime('-8 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_week_start, $prev_week_end]);
    $prev_week_count = $stmt->fetchColumn();
    
    // ========== ماه جاری (30 روز اخیر) ==========
    $month_ago = jDateTime::date('Y/m/d', strtotime('-30 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $month_ago, $today]);
    $month_count = $stmt->fetchColumn();
    
    // ماه قبل (30 روز قبل از ماه جاری)
    $prev_month_start = jDateTime::date('Y/m/d', strtotime('-60 days'));
    $prev_month_end = jDateTime::date('Y/m/d', strtotime('-31 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_month_start, $prev_month_end]);
    $prev_month_count = $stmt->fetchColumn();
    
    // ========== سال جاری (365 روز اخیر) ==========
    $year_ago = jDateTime::date('Y/m/d', strtotime('-365 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $year_ago, $today]);
    $year_count = $stmt->fetchColumn();
    
    // سال قبل (365 روز قبل از سال جاری)
    $prev_year_start = jDateTime::date('Y/m/d', strtotime('-730 days'));
    $prev_year_end = jDateTime::date('Y/m/d', strtotime('-366 days'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$user_id, $prev_year_start, $prev_year_end]);
    $prev_year_count = $stmt->fetchColumn();
    
    // ========== تغییرات ==========
    
    // تغییر نسبت به دیروز
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
    
    // تغییر هفته
    $week_change = '---';
    $week_change_class = 'avg-change-neutral';
    if ($prev_week_count > 0) {
        $diff = $week_count - $prev_week_count;
        if ($diff > 0) {
            $week_change = "▲ +{$diff}";
            $week_change_class = 'avg-change-up';
        } elseif ($diff < 0) {
            $week_change = "▼ {$diff}";
            $week_change_class = 'avg-change-down';
        } else {
            $week_change = "● 0";
            $week_change_class = 'avg-change-neutral';
        }
    }
    
    // تغییر ماه
    $month_change = '---';
    $month_change_class = 'avg-change-neutral';
    if ($prev_month_count > 0) {
        $diff = $month_count - $prev_month_count;
        if ($diff > 0) {
            $month_change = "▲ +{$diff}";
            $month_change_class = 'avg-change-up';
        } elseif ($diff < 0) {
            $month_change = "▼ {$diff}";
            $month_change_class = 'avg-change-down';
        } else {
            $month_change = "● 0";
            $month_change_class = 'avg-change-neutral';
        }
    }
    
    // تغییر سال
    $year_change = '---';
    $year_change_class = 'avg-change-neutral';
    if ($prev_year_count > 0) {
        $diff = $year_count - $prev_year_count;
        if ($diff > 0) {
            $year_change = "▲ +{$diff}";
            $year_change_class = 'avg-change-up';
        } elseif ($diff < 0) {
            $year_change = "▼ {$diff}";
            $year_change_class = 'avg-change-down';
        } else {
            $year_change = "● 0";
            $year_change_class = 'avg-change-neutral';
        }
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
    
    $today = jDateTime::date('Y/m/d');
    $yesterday = jDateTime::date('Y/m/d', strtotime('-1 days'));
    
    $stmt = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY fullname");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($users as $user) {
        $user_id_val = $user['id'];
        
        // دریافت آخرین تاریخ تحویل این کاربر
        $stmt = $db->prepare("SELECT MAX(delivery_date) as last_delivery_date FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $last_date = $stmt->fetchColumn();
        
        // اسناد امروز
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id_val, $today]);
        $today_count = $stmt->fetchColumn();
        
        // اسناد دیروز
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id_val, $yesterday]);
        $yesterday_count = $stmt->fetchColumn();
        
        // کل اسناد ثبت شده
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $total_docs = $stmt->fetchColumn();
        
        // هفته جاری (7 روز اخیر)
        $week_ago = jDateTime::date('Y/m/d', strtotime('-7 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $week_ago, $today]);
        $week_count = $stmt->fetchColumn();
        
        // هفته قبل (7 روز قبل از هفته جاری)
        $prev_week_start = jDateTime::date('Y/m/d', strtotime('-14 days'));
        $prev_week_end = jDateTime::date('Y/m/d', strtotime('-8 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_week_start, $prev_week_end]);
        $prev_week_count = $stmt->fetchColumn();
        
        // ماه جاری (30 روز اخیر)
        $month_ago = jDateTime::date('Y/m/d', strtotime('-30 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $month_ago, $today]);
        $month_count = $stmt->fetchColumn();
        
        // ماه قبل (30 روز قبل از ماه جاری)
        $prev_month_start = jDateTime::date('Y/m/d', strtotime('-60 days'));
        $prev_month_end = jDateTime::date('Y/m/d', strtotime('-31 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_month_start, $prev_month_end]);
        $prev_month_count = $stmt->fetchColumn();
        
        // سال جاری (365 روز اخیر)
        $year_ago = jDateTime::date('Y/m/d', strtotime('-365 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $year_ago, $today]);
        $year_count = $stmt->fetchColumn();
        
        // سال قبل (365 روز قبل از سال جاری)
        $prev_year_start = jDateTime::date('Y/m/d', strtotime('-730 days'));
        $prev_year_end = jDateTime::date('Y/m/d', strtotime('-366 days'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_year_start, $prev_year_end]);
        $prev_year_count = $stmt->fetchColumn();
        
        // تغییرات نسبت به دیروز
        $trend_diff = $today_count - $yesterday_count;
        $trend_text = $trend_diff > 0 ? "▲ +{$trend_diff}" : ($trend_diff < 0 ? "▼ {$trend_diff}" : "● 0");
        $trend_class = $trend_diff > 0 ? 'trend-up' : ($trend_diff < 0 ? 'trend-down' : 'trend-neutral');
        
        // تغییرات هفته
        $week_change = '---';
        $week_change_class = 'avg-change-neutral';
        if ($prev_week_count > 0) {
            $diff = $week_count - $prev_week_count;
            $week_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $week_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        // تغییرات ماه
        $month_change = '---';
        $month_change_class = 'avg-change-neutral';
        if ($prev_month_count > 0) {
            $diff = $month_count - $prev_month_count;
            $month_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $month_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        // تغییرات سال
        $year_change = '---';
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
            'year_change_class' => $year_change_class
        ];
    }
    
    // مرتب‌سازی بر اساس آخرین تاریخ تحویل (جدیدترین در بالا)
    usort($result, function($a, $b) {
        if ($a['last_delivery_date'] == $b['last_delivery_date']) return 0;
        if ($a['last_delivery_date'] == null) return 1;
        if ($b['last_delivery_date'] == null) return -1;
        return strcmp($b['last_delivery_date'], $a['last_delivery_date']);
    });
    
    $max_user = !empty($result) ? $result[0] : null;
    $min_user = !empty($result) ? $result[count($result) - 1] : null;
    
    echo json_encode([
        'success' => true,
        'users' => $result,
        'max_user' => $max_user,
        'min_user' => $min_user,
        'today_date' => toPersianNumber($today)
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
            LIMIT 2";
    
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
        $params[':doc_number'] = "%$doc_number%";
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