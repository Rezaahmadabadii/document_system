<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// ========== تنظیم از پرینت ==========
if (isset($_GET['action']) && $_GET['action'] == 'set_from_print') {
    session_name('doc_system');
    session_start();
    $_SESSION['from_print'] = true;
    echo json_encode(['success' => true]);
    exit;
}

// ========== شروع سشن ==========
session_name('doc_system');
session_start();

// ========== دریافت action ==========
$action = $_GET['action'] ?? ''; // ✅ اینجا تعریف شود

// ========== تست سشن ==========
if ($action == 'test_session') {
    echo json_encode([
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'is_admin' => $_SESSION['is_admin'] ?? 'not set',
        'fullname' => $_SESSION['fullname'] ?? 'not set'
    ]);
    exit;
}

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

// ========== دریافت واحد کاربر از فایل JSON ==========
function getUserUnit($username) {
    $file = __DIR__ . '/../config/user_units.json';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (is_array($data)) {
            $clean_name = preg_replace('/[0-9]+$/', '', $username);
            return $data[$clean_name] ?? $data[$username] ?? 'سایر';
        }
    }
    return 'سایر';
}

// ========== دریافت اطلاعات کاربر جاری ==========
if ($action == 'get_user_info') {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, fullname, unit_name, can_view_unit_stats FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'fullname' => $user['fullname'],
        'unit_name' => $user['unit_name'],
        'can_view_unit_stats' => $user['can_view_unit_stats'] == 1
    ]);
    exit;
}

// ===== دریافت کاربران هم‌واحد =====
if ($action == 'get_users_by_unit') {
    $unit_name = isset($_GET['unit_name']) ? trim($_GET['unit_name']) : '';
    
    if (empty($unit_name)) {
        echo json_encode(['success' => false, 'error' => 'واحد مشخص نشده است']);
        exit;
    }
    
    // فقط کاربر معمولی می‌تواند هم‌واحدهای خود را ببیند (ادمین نیازی ندارد)
    if ($is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied for admin']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT id, fullname, unit_name FROM users WHERE unit_name = ? AND is_admin = 0 AND id != ?");
        $stmt->execute([$unit_name, $user_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'unit_name' => $unit_name,
            'current_user_id' => $user_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ========== بررسی دسترسی آمار کاربران واحد ==========
if ($action == 'check_unit_stats_permission') {
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'can_view' => false]);
        exit;
    }
    
    $stmt = $db->prepare("SELECT can_view_unit_stats FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $can_view = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'can_view' => $can_view == 1
    ]);
    exit;
}

// ========== دریافت آمار کاربران واحد ==========
if ($action == 'get_unit_users_stats') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $type = $_GET['type'] ?? 'register';
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit;
    }
    
    // دریافت excel_name کاربر جاری
    $stmt = $db->prepare("SELECT excel_name, can_view_unit_stats FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info || !$user_info['can_view_unit_stats']) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $current_excel_name = $user_info['excel_name'];
    
    if (empty($current_excel_name)) {
        echo json_encode(['success' => false, 'error' => 'excel_name کاربر مشخص نیست']);
        exit;
    }
    
    // دریافت واحد کاربر جاری
    $current_unit = getUserUnit($current_excel_name);
    
    if (empty($current_unit)) {
        echo json_encode(['success' => false, 'error' => 'واحد کاربر مشخص نیست']);
        exit;
    }
    
    // خواندن فایل اکسل
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    $user_counts = [];
    $all_dates = [];
    $last_times = [];
    $user_ids = [];
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $row_date = trim($row[4]);
                $row_time = trim($row[5]);
                $raw_name = trim($row[3]);
                $clean_name = preg_replace('/[0-9]+$/', '', $raw_name);
                if (empty($clean_name)) $clean_name = $raw_name;
                
                // فقط کاربرانی که واحدشان با واحد کاربر جاری مطابقت دارد
                $user_unit = getUserUnit($clean_name);
                if ($user_unit != $current_unit) {
                    continue;
                }
                
                // جمع‌آوری همه تاریخ‌ها برای این واحد
                if (!in_array($row_date, $all_dates)) {
                    $all_dates[] = $row_date;
                }
                
                // ذخیره آخرین زمان برای هر تاریخ
                $current_datetime = $row_date . ' ' . $row_time;
                if (!isset($last_times[$row_date]) || $current_datetime > $last_times[$row_date]) {
                    $last_times[$row_date] = $current_datetime;
                }
                
                // فقط برای تاریخ انتخاب شده، آمار کاربران را محاسبه کن
                if ($row_date != $date) {
                    continue;
                }
                
                // شرط: فقط حسابداری
                if ($row[8] != 'سند حسابداري') {
                    continue;
                }
                
                // حذف شرکت برش کوه
                $company = trim($row[1] ?? '');
                if ($company == 'شركت برش كوه آريا پارت (سهامي خاص)') {
                    continue;
                }
                
                // نوع گزارش: ثبت یا تایید
                $is_valid = false;
                if ($type == 'register') {
                    $is_valid = (strpos($row[6], 'موجوديت جديد') !== false);
                } else {
                    $desc = trim($row[11] ?? '');
                    $is_valid = (strpos($desc, 'تایید') !== false || strpos($desc, 'تاييد') !== false);
                }
                
                if (!$is_valid) continue;
                
                if (!isset($user_counts[$clean_name])) {
                    $user_counts[$clean_name] = 0;
                    $user_ids[$clean_name] = null;
                }
                $user_counts[$clean_name]++;
            }
        }
        fclose($handle);
    }
    
    // ✅ دریافت user_id برای هر نام کاربر از دیتابیس
    if (!empty($user_counts)) {
        $names = array_keys($user_counts);
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $db->prepare("SELECT id, fullname FROM users WHERE fullname IN ($placeholders)");
        $stmt->execute($names);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $user_ids[$user['fullname']] = $user['id'];
        }
    }
    
    arsort($user_counts);
    
    $result = [];
    foreach ($user_counts as $name => $count) {
        $result[] = [
            'user_name' => $name,
            'user_id' => $user_ids[$name] ?? 0,
            'count' => $count
        ];
    }
    
    sort($all_dates);
    
    echo json_encode([
        'success' => true,
        'users' => $result,
        'unit' => $current_unit,
        'type' => $type,
        'dates' => $all_dates,
        'last_times' => $last_times
    ]);
    exit;
}

// ========== بررسی لاگین ==========
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

// ========== جستجوی اسناد (کاربر عادی با نمایش اسناد هم‌واحد) ==========
if ($action == 'get_documents') {
    $doc_number = $_GET['doc_number'] ?? '';
    $doc_date = $_GET['doc_date'] ?? '';
    $company_id = $_GET['company_id'] ?? '';
    $delivery_date = $_GET['delivery_date'] ?? '';
    $include_unit = isset($_GET['include_unit']) && $_GET['include_unit'] == 'true';
    
    // دریافت اطلاعات کاربر جاری
    $stmt = $db->prepare("SELECT unit_name, excel_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $sql = "SELECT d.*, c.name as company_name, u.fullname as user_name 
            FROM documents d 
            JOIN companies c ON d.company_id = c.id 
            JOIN users u ON d.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // ===== تصمیم‌گیری درباره فیلتر کاربر =====
    $use_unit_filter = false;
    $unit_users = [];
    
    if ($include_unit && !empty($user['unit_name'])) {
        // دریافت همه کاربران هم‌واحد
        $stmt = $db->prepare("SELECT id FROM users WHERE unit_name = ? AND is_admin = 0");
        $stmt->execute([$user['unit_name']]);
        $unit_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // اگر هم‌واحدی وجود دارد، از فیلتر هم‌واحد استفاده کن
        if (!empty($unit_users)) {
            $use_unit_filter = true;
        }
    }
    
    if ($use_unit_filter && !empty($unit_users)) {
        // نمایش اسناد همه کاربران هم‌واحد
        $placeholders = implode(',', array_fill(0, count($unit_users), '?'));
        $sql .= " AND d.user_id IN ($placeholders)";
        $params = array_merge($params, $unit_users);
    } else {
        // ✅ فقط اسناد خود کاربر را نمایش بده
        $sql .= " AND d.user_id = ?";
        $params[] = $user_id;
    }
    
    // اضافه کردن فیلترهای جستجو
    if (!empty($doc_number)) {
        $sql .= " AND d.doc_number LIKE ?";
        $params[] = "$doc_number%";
    }
    if (!empty($doc_date)) {
        $doc_date = toEnglishNumber($doc_date);
        $sql .= " AND d.doc_date = ?";
        $params[] = $doc_date;
    }
    if (!empty($company_id)) {
        $sql .= " AND d.company_id = ?";
        $params[] = $company_id;
    }
    if (!empty($delivery_date)) {
        $delivery_date = toEnglishNumber($delivery_date);
        $sql .= " AND d.delivery_date = ?";
        $params[] = $delivery_date;
    }

    $sql .= " ORDER BY d.delivery_date DESC, d.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($docs as $doc) {
        $date = toPersianNumber($doc['delivery_date']);
        if (!isset($groups[$date])) {
            $stmt_check = $db->prepare("SELECT admin_approved_at FROM delivery_approvals WHERE user_id = ? AND delivery_date = ?");
            $stmt_check->execute([$doc['user_id'], $doc['delivery_date']]);
            $is_archived = !empty($stmt_check->fetchColumn());

            $groups[$date] = [
                'delivery_date' => $date,
                'count' => 0,
                'is_archived' => $is_archived,
                // ✅ تا وقتی خلافش ثابت نشود، فرض می‌کنیم گروه متعلق به خود کاربر است
                'group_owned_by_user' => true,
                'documents' => []
            ];
        }

        $is_own_doc = ($doc['user_id'] == $user_id);

        // ✅ اگر حتی یک سند از این گروه متعلق به کاربر دیگری (هم‌واحد) باشد،
        // کل گروه دیگر «متعلق به خود کاربر» محسوب نمی‌شود
        if (!$is_own_doc) {
            $groups[$date]['group_owned_by_user'] = false;
        }

        $groups[$date]['documents'][] = [
            'id' => $doc['id'],
            'user_name' => $doc['user_name'],
            'doc_number' => $doc['doc_number'],
            'doc_date' => $doc['doc_date'] == '-' ? '-' : toPersianNumber($doc['doc_date']),
            'company_name' => $doc['company_name'],
            'description' => $doc['description'],
            'row_num' => count($groups[$date]['documents']),
            'can_edit' => $is_own_doc
        ];
        $groups[$date]['count']++;
    }

    echo json_encode(['success' => true, 'groups' => array_values($groups)]);
    exit;
}

// ========== ثبت سند جدید (با بررسی تکراری) ==========
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
        $doc_date = toEnglishNumber($doc_date);
    } else {
        if (empty($doc_date)) {
            $doc_date = '-';
        } else {
            $doc_date = toEnglishNumber($doc_date);
        }
    }
    
    // ========== بررسی تکراری بودن سند ==========
    // شرط: شماره سند + شرکت + تاریخ تحویل
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents 
                          WHERE doc_number = ? 
                          AND company_id = ? 
                          AND delivery_date = ? 
                          AND user_id = ?");
    $stmt->execute([$doc_number, $company_id, $delivery_date, $user_id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode([
            'success' => false, 
            'duplicate' => true,
            'error' => 'این سند قبلاً برای این شرکت و این تاریخ تحویل ثبت شده است'
        ]);
        exit;
    }
    // ==========================================
    
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
    
    if (!$is_admin && $doc['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'شما دسترسی به حذف این سند ندارید']);
        exit;
    }
    
    $can_delete = $is_admin || (time() - strtotime($doc['created_at'])) <= (20 * 86400);
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

// ========== دریافت آمار تایید ==========
if ($action == 'get_confirm_stats') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file) || $user_id == 0) {
        echo json_encode(['success' => true, 'dates' => [], 'stats' => [], 'last_times' => []]);
        exit;
    }
    
    $stmt = $db->prepare("SELECT excel_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $excel_name = $stmt->fetchColumn();
    
    if (empty($excel_name)) {
        echo json_encode(['success' => true, 'dates' => [], 'stats' => [], 'last_times' => []]);
        exit;
    }
    
    $stats = [];
    $dates = [];
    $last_times = []; // ✅ ذخیره آخرین زمان برای هر تاریخ
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ',');
        fgetcsv($handle, 0, ',');
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $row_date = trim($row[4]);
                $row_time = trim($row[5]);
                $full_name = trim($row[3]);
                $clean_name = preg_replace('/[0-9]+$/', '', $full_name);
                $description = trim($row[11] ?? '');
                
                if ($row[8] == 'سند حسابداري' &&
                    (strpos($description, 'تایید') !== false || strpos($description, 'تاييد') !== false) &&
                    $clean_name == $excel_name) {
                    
                    if (!isset($stats[$row_date])) {
                        $stats[$row_date] = 0;
                    }
                    $stats[$row_date]++;
                    
                    if (!in_array($row_date, $dates)) {
                        $dates[] = $row_date;
                    }
                    
                    // ✅ ذخیره آخرین زمان برای هر تاریخ
                    $current_datetime = $row_date . ' ' . $row_time;
                    if (!isset($last_times[$row_date]) || $current_datetime > $last_times[$row_date]) {
                        $last_times[$row_date] = $current_datetime;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    sort($dates);
    
    echo json_encode([
        'success' => true,
        'dates' => $dates,
        'stats' => $stats,
        'last_times' => $last_times // ✅ ارسال زمان‌ها برای هر تاریخ
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
    $weekday = date('w', $timestamp);
    $weekday = ($weekday + 1) % 7;
    
    $current_week_start_timestamp = strtotime("-$weekday days", $timestamp);
    $current_week_start = jDateTime::date('Y/m/d', $current_week_start_timestamp);
    $current_week_end = jDateTime::date('Y/m/d', strtotime('+6 days', $current_week_start_timestamp));
    
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
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$current_week_start, $current_week_end]);
    $week_approved = (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE delivery_date >= ? AND delivery_date <= ?");
    $stmt->execute([$prev_week_start, $prev_week_end]);
    $prev_week_approved = (int)$stmt->fetchColumn();
    
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
    
    $vs_yesterday = $today_approved . ' سند | لیست قبلی : ' . $yesterday_approved;
    $vs_yesterday_class = $today_approved > $yesterday_approved ? 'up' : ($today_approved < $yesterday_approved ? 'down' : 'neutral');
    
    $vs_last_week = ($week_approved > 0 ? $week_approved . ' سند' : '⏳') . ' | هفته قبل : ' . $prev_week_approved;
    $vs_last_week_class = $week_approved > $prev_week_approved ? 'up' : ($week_approved < $prev_week_approved ? 'down' : 'neutral');
    
    $vs_last_month = ($month_approved > 0 ? $month_approved . ' سند' : '⏳') . ' | ماه قبل : ' . ($prev_month_approved > 0 ? $prev_month_approved : '⏳');
    $vs_last_month_class = $month_approved > $prev_month_approved ? 'up' : ($month_approved < $prev_month_approved ? 'down' : 'neutral');
    
    $stmt = $db->query("SELECT id, fullname, unit_name FROM users WHERE is_admin = 0 ORDER BY fullname");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($users as $user) {
        $user_id_val = $user['id'];
        
        // ✅ بررسی تعداد کل اسناد کاربر
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $total_docs = $stmt->fetchColumn();
        
        // ✅ اگر کاربر سندی ندارد، رد کن
        if ($total_docs == 0) {
            continue;
        }
        
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
        
        $stmt = $db->prepare("SELECT MIN(delivery_date) as first_date FROM documents WHERE user_id = ?");
        $stmt->execute([$user_id_val]);
        $first_date = $stmt->fetchColumn();
        if (!$first_date) $first_date = '-';
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $current_week_start, $current_week_end]);
        $week_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_week_start, $prev_week_end]);
        $prev_week_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $current_month_start, $current_month_end]);
        $month_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_month_start, $prev_month_end]);
        $prev_month_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $current_year_start ?? '1400/01/01', $today_shamsi]);
        $year_count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE user_id = ? AND delivery_date >= ? AND delivery_date <= ?");
        $stmt->execute([$user_id_val, $prev_year_start ?? '1399/01/01', $prev_year_end ?? '1399/12/29']);
        $prev_year_count = $stmt->fetchColumn();
        
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
        
        $week_change = '⏳';
        $week_change_class = 'avg-change-neutral';
        if ($prev_week_count > 0) {
            $diff = $week_count - $prev_week_count;
            $week_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $week_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
        $month_change = '⏳';
        $month_change_class = 'avg-change-neutral';
        if ($prev_month_count > 0) {
            $diff = $month_count - $prev_month_count;
            $month_change = $diff > 0 ? "▲ +{$diff}" : ($diff < 0 ? "▼ {$diff}" : "● 0");
            $month_change_class = $diff > 0 ? 'avg-change-up' : ($diff < 0 ? 'avg-change-down' : 'avg-change-neutral');
        }
        
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
    // حذف کلمات اضافی (سهامي خاص, تهران, بيرجند, بيستون, پارس, آمود, كوير, شرق)
    $name = preg_replace('/\s+(سهامي خاص|تهران|بيرجند|بيستون|پارس|آمود|كوير|شرق)\s*/', '', $name);
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

// ========== دریافت آخرین تاریخ و زمان بروزرسانی فایل CSV ==========
if ($action == 'get_file_update_time') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل یافت نشد']);
        exit;
    }
    
    $max_datetime = '';
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $date = trim($row[4]);
                $time = trim($row[5]);
                if (!empty($date) && !empty($time)) {
                    $current_datetime = $date . ' ' . $time;
                    // ✅ مقایسه برای پیدا کردن جدیدترین
                    if ($current_datetime > $max_datetime) {
                        $max_datetime = $current_datetime;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    if (empty($max_datetime)) {
        $max_datetime = jDateTime::date('Y/m/d H:i:s');
    }
    
    echo json_encode([
        'success' => true,
        'update_time' => $max_datetime
    ]);
    exit;
}

// ========== بررسی سبک تغییر فایل CSV (برای پایش خودکار بدون رفرش) ==========
// این اکشن به‌جای خواندن و پارس کل فایل CSV، فقط زمان آخرین تغییر فایل را
// از سیستم فایل می‌خواند (filemtime) و برمی‌گرداند؛ خیلی سبک‌تر از load_report_data
// است و مناسب برای صدا زدن مکرر (Polling) از سمت کلاینت.
if ($action == 'check_report_update') {
    // ✅ جلوگیری از کش شدن این درخواست توسط مرورگر/پروکسی؛ چون این اکشن هر
    // چند ثانیه یک‌بار Polling می‌شود، اگر کش شود جواب همیشه همان مقدار اول
    // را برمی‌گرداند و تغییر فایل هرگز تشخیص داده نمی‌شود.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';

    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }

    // ✅ پاک کردن کش stat در PHP تا مقدار filemtime همیشه واقعی و به‌روز باشد
    // (بدون این خط، PHP ممکن است مقدار قدیمی کش‌شده را برای درخواست‌های بعدی برگرداند)
    clearstatcache(true, $csv_file);

    $mtime = filemtime($csv_file);
    $filesize = filesize($csv_file);

    echo json_encode([
        'success' => true,
        // ترکیب mtime و اندازه فایل به عنوان یک «امضا»ی سبک برای تشخیص تغییر،
        // تا حتی در سیستم‌فایل‌هایی با دقت پایین mtime هم تغییر واقعی تشخیص داده شود
        'signature' => $mtime . '_' . $filesize,
        'mtime' => $mtime
    ]);
    exit;
}

// ========== بارگذاری داده‌های گزارش برهان ==========
if ($action == 'load_report_data') {
    // ✅ حذف شرط ادمین - دسترسی برای همه کاربران
    // if (!$is_admin) { ... } را حذف کنید
    
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    $check_file = __DIR__ . '/../storage/reports/last_check.txt';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    // خواندن زمان آخرین بررسی از فایل
    $last_check = '1400/01/01 00:00:00';
    if (file_exists($check_file)) {
        $last_check = trim(file_get_contents($check_file));
    }
    
    $data = [];
    $new_records = [];
    $last_record_datetime = $last_check;
    
    $handle = fopen($csv_file, 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'error' => 'خطا در باز کردن فایل']);
        exit;
    }
    
    fgetcsv($handle, 0, ','); // هدر اول
    $header = fgetcsv($handle, 0, ','); // هدر دوم
    
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
        if (count($row) > 1 && !empty(trim($row[1]))) {
            $row[1] = cleanCompanyName($row[1]);
            $record_datetime = $row[4] . ' ' . $row[5];
            
            if ($record_datetime > $last_record_datetime) {
                $last_record_datetime = $record_datetime;
            }
            
            $is_new = ($record_datetime > $last_check);
            $row['is_new'] = $is_new;
            
            if ($is_new) {
                $new_records[] = $row;
            }
            
            $data[] = $row;
        }
    }
    fclose($handle);
    
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

// ========== دریافت آمار لحظه‌ای ==========
if ($action == 'get_report_stats') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode([
            'success' => true,
            'new_count' => 0,
            'edit_count' => 0,
            'delete_count' => 0,
            'total' => 0,
            'login_success_count' => 0,
            'login_fail_count' => 0
        ]);
        exit;
    }
    
    $new_count = 0;
    $edit_count = 0;
    $delete_count = 0;
    $login_success_count = 0;
    $login_fail_count = 0;
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $row_date = trim($row[4]);
                $operation = trim($row[6]);
                $doc_type = trim($row[8] ?? '');
                $company = trim($row[1] ?? '');
                
                // ===== آمار ثبت، ویرایش، حذف =====
                if ($row_date == $date && 
                    $doc_type == 'سند حسابداري' && 
                    $company != 'شركت برش كوه آريا پارت (سهامي خاص)') {
                    
                    if (strpos($operation, 'موجوديت جديد') !== false) {
                        $new_count++;
                    } elseif (strpos($operation, 'ويرايش موجوديت') !== false) {
                        $edit_count++;
                    } elseif (strpos($operation, 'حذف موجوديت') !== false) {
                        $delete_count++;
                    }
                }
                
                // ===== آمار ورود موفق و ناموفق (مستقل از شرکت) =====
                if ($row_date == $date) {
                    if (strpos($operation, 'ورود موفق') !== false) {
                        $login_success_count++;
                    } elseif (strpos($operation, 'ورود ناموفق') !== false) {
                        $login_fail_count++;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    $total = $new_count + $edit_count + $delete_count;
    
    echo json_encode([
        'success' => true,
        'new_count' => $new_count,
        'edit_count' => $edit_count,
        'delete_count' => $delete_count,
        'total' => $total,
        'login_success_count' => $login_success_count,
        'login_fail_count' => $login_fail_count
    ]);
    exit;
}

// ========== دریافت گزارش ثبت/تایید اسناد (حسابداری) ==========
if ($action == 'get_warehouse_report') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $date = $_GET['date'] ?? jDateTime::date('Y/m/d');
    $type = $_GET['type'] ?? 'register';
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $user_counts = [];
    $user_units = [];
    $user_ids = [];
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                if ($row[4] == $date && $row[8] == 'سند حسابداري') {
                    
                    $company = trim($row[1] ?? '');
                    if ($company == 'شركت برش كوه آريا پارت (سهامي خاص)') {
                        continue;
                    }
                    
                    $is_valid = false;
                    if ($type == 'register') {
                        $is_valid = (strpos($row[6], 'موجوديت جديد') !== false);
                    } else {
                        $desc = trim($row[11] ?? '');
                        $is_valid = (strpos($desc, 'تایید') !== false || strpos($desc, 'تاييد') !== false);
                    }
                    
                    if (!$is_valid) continue;
                    
                    $raw_name = trim($row[3]);
                    $clean_name = preg_replace('/[0-9]+$/', '', $raw_name);
                    if (empty($clean_name)) $clean_name = $raw_name;
                    
                    if (!isset($user_counts[$clean_name])) {
                        $user_counts[$clean_name] = 0;
                        $user_units[$clean_name] = getUserUnit($clean_name);
                        $user_ids[$clean_name] = null;
                    }
                    $user_counts[$clean_name]++;
                }
            }
        }
        fclose($handle);
    }
    
    // ✅ دریافت user_id برای هر نام کاربر از دیتابیس
    if (!empty($user_counts)) {
        $names = array_keys($user_counts);
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $db->prepare("SELECT id, fullname FROM users WHERE fullname IN ($placeholders)");
        $stmt->execute($names);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($users as $user) {
            $user_ids[$user['fullname']] = $user['id'];
        }
    }
    
    arsort($user_counts);
    
    $result = [];
    foreach ($user_counts as $name => $count) {
        $result[] = [
            'user_name' => $name,
            'user_id' => $user_ids[$name] ?? 0,
            'count' => $count,
            'unit' => $user_units[$name] ?? 'سایر'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $result,
        'date' => $date,
        'total' => array_sum($user_counts),
        'type' => $type
    ]);
    exit;
}

// ========== بارگذاری اسناد تایید شده از فایل اکسل ==========
if ($action == 'load_confirmed_documents') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_names = $data['user_names'] ?? []; // ✅ آرایه از نام‌ها
    $date_from = $data['date_from'] ?? '';
    $date_to = $data['date_to'] ?? '';
    $delivery_date = $data['delivery_date'] ?? '';
    
    if (empty($user_names) || empty($date_from) || empty($date_to) || empty($delivery_date)) {
        echo json_encode(['success' => false, 'error' => 'نام کاربر(ان)، بازه تاریخ و تاریخ تحویل الزامی است']);
        exit;
    }
    
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $map_file = __DIR__ . '/../config/company_map.json';
    $company_map = [];
    if (file_exists($map_file)) {
        $company_map = json_decode(file_get_contents($map_file), true) ?: [];
    }
    
    $documents = [];
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $date = trim($row[4]);
                $full_name = trim($row[3]);
                $clean_name = preg_replace('/[0-9]+$/', '', $full_name);
                $description = trim($row[11] ?? '');
                
                // ✅ بررسی اینکه آیا نام کاربر در آرایه انتخاب شده‌ها هست
                if (in_array($clean_name, $user_names) && 
                    $date >= $date_from && 
                    $date <= $date_to &&
                    $row[8] == 'سند حسابداري' &&
                    (strpos($description, 'تایید') !== false || strpos($description, 'تاييد') !== false)) {
                    
                    $company_full = trim($row[1] ?? '');
                    if ($company_full == 'برش كوه' || $company_full == 'شركت برش كوه آريا پارت (سهامي خاص)') {
                        continue;
                    }
                    
                    $company_short = $company_map[$company_full] ?? $company_full;
                    
                    $documents[] = [
                        'doc_number' => trim($row[9] ?? ''),
                        'doc_date' => trim($row[10] ?? ''),
                        'company_name' => $company_short,
                        'company_full' => $company_full,
                        'delivery_date' => $delivery_date,
                        'description' => '',
                        'user_name' => $full_name,
                        'date' => $date,
                        'time' => trim($row[5] ?? '')
                    ];
                }
            }
        }
        fclose($handle);
    }
    
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'count' => count($documents)
    ]);
    exit;
}

// ========== دریافت لیست کاربران دارای تایید ==========
if ($action == 'get_approved_users_list') {
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $users = [];
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $desc = trim($row[11] ?? '');
                $isAccounting = ($row[8] ?? '') == 'سند حسابداري';
                
                if ($isAccounting && (strpos($desc, 'تایید') !== false || strpos($desc, 'تاييد') !== false)) {
                    $rawName = trim($row[3]);
                    $cleanName = preg_replace('/[0-9]+$/', '', $rawName);
                    if (!empty($cleanName)) {
                        $users[] = $cleanName;
                    }
                }
            }
        }
        fclose($handle);
    }
    
    $users = array_unique($users);
    sort($users);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    exit;
}

// ========== ذخیره اسناد بارگذاری شده ==========
if ($action == 'save_loaded_documents') {
    $data = json_decode(file_get_contents('php://input'), true);
    $documents = $data['documents'] ?? [];
    
    if (empty($documents)) {
        echo json_encode(['success' => false, 'error' => 'هیچ سندی برای ذخیره وجود ندارد']);
        exit;
    }
    
    $map_file = __DIR__ . '/../config/company_map.json';
    $company_map = [];
    if (file_exists($map_file)) {
        $company_map = json_decode(file_get_contents($map_file), true) ?: [];
    }
    
    $stmt = $db->prepare("SELECT id FROM companies WHERE name = ?");
    
    $saved_count = 0;
    $errors = [];
    
    foreach ($documents as $doc) {
        $company_name = $doc['company_name'] ?? '';
        $doc_number = $doc['doc_number'] ?? '';
        $doc_date = $doc['doc_date'] ?? '-';
        $delivery_date = $doc['delivery_date'] ?? '';
        
        if (empty($doc_number) || empty($delivery_date)) {
            continue;
        }
        
        $stmt->execute([$company_name]);
        $company_id = $stmt->fetchColumn();
        
        if (!$company_id) {
            $mapped_name = array_search($company_name, $company_map);
            if ($mapped_name) {
                $stmt->execute([$mapped_name]);
                $company_id = $stmt->fetchColumn();
            }
        }
        
        if (!$company_id) {
            $errors[] = "شرکت '$company_name' برای سند '$doc_number' یافت نشد";
            continue;
        }
        
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM documents 
                                    WHERE doc_number = ? AND company_id = ? AND delivery_date = ? AND user_id = ?");
        $stmt_check->execute([$doc_number, $company_id, $delivery_date, $user_id]);
        $count = $stmt_check->fetchColumn();
        
        if ($count > 0) {
            $errors[] = "سند '$doc_number' قبلاً ثبت شده است";
            continue;
        }
        
        // ✅ description را خالی ذخیره کن (نه توضیحات اکسل)
        $stmt_insert = $db->prepare("INSERT INTO documents (user_id, company_id, doc_number, doc_date, delivery_date, description) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt_insert->execute([$user_id, $company_id, $doc_number, $doc_date, $delivery_date, '']);
        
        if ($result) {
            $saved_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'saved_count' => $saved_count,
        'errors' => $errors
    ]);
    exit;
}

// ============================================================
// ===== توابع مدیریت دیتابیس =====
// ============================================================

// ============================================================
// ===== دریافت آخرین تاریخ تحویل کاربر =====
// اکشن: get_user_last_delivery_date
// پارامترها: user_id
// خروجی: last_date, user_signed, admin_approved, doc_count
// ============================================================
// ===== دریافت آخرین تاریخ تحویل کاربر =====
if ($action == 'get_user_last_delivery_date') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'کاربر نامعتبر']);
        exit;
    }
    
    try {
        // اگر تاریخ خاصی داده نشده، آخرین تاریخ رو بگیر
        if (!$delivery_date) {
            // اول از documents بگیر
            $stmt = $db->prepare("
                SELECT delivery_date 
                FROM documents 
                WHERE user_id = ? 
                ORDER BY delivery_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $delivery_date = $stmt->fetchColumn();
            
            // اگه نبود، از delivery_approvals بگیر
            if (!$delivery_date) {
                $stmt = $db->prepare("
                    SELECT delivery_date 
                    FROM delivery_approvals 
                    WHERE user_id = ? 
                    ORDER BY delivery_date DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $delivery_date = $stmt->fetchColumn();
            }
        }
        
        if (!$delivery_date) {
            echo json_encode([
                'success' => true,
                'last_date' => null,
                'doc_count' => 0,
                'user_signed' => false,
                'admin_approved' => false
            ]);
            exit;
        }
        
        // دریافت تعداد اسناد برای این تاریخ
        $stmt = $db->prepare("
            SELECT COUNT(*) as doc_count 
            FROM documents 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        $doc_count = $stmt->fetchColumn();
        
        // دریافت وضعیت امضا/تایید از delivery_approvals
        $stmt = $db->prepare("
            SELECT user_approved_at, admin_approved_at 
            FROM delivery_approvals 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'last_date' => $delivery_date,
            'doc_count' => (int)$doc_count,
            'user_signed' => $approval && !empty($approval['user_approved_at']),
            'admin_approved' => $approval && !empty($approval['admin_approved_at'])
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ===== دریافت سند بر اساس شماره سند =====
// اکشن: get_document_by_number
// پارامترها: user_id, delivery_date, doc_number
// خروجی: document {id, company_name, company_id}
// ============================================================
// ===== دریافت سند بر اساس شماره سند =====
if ($action == 'get_document_by_number') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : '';
    $doc_number = isset($_GET['doc_number']) ? trim($_GET['doc_number']) : '';
    
    if (!$user_id || !$delivery_date || !$doc_number) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT d.id, d.company_id, c.name as company_name
            FROM documents d
            LEFT JOIN companies c ON d.company_id = c.id
            WHERE d.user_id = ? AND d.delivery_date = ? AND d.doc_number = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $delivery_date, $doc_number]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'document' => [
                    'id' => $result['id'],
                    'company_id' => $result['company_id'],
                    'company_name' => $result['company_name'] ?? 'نامشخص'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===== دریافت اطلاعات کامل سند برای ویرایش =====
if ($action == 'get_document_full_info') {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : '';
    $doc_number = isset($_GET['doc_number']) ? trim($_GET['doc_number']) : '';
    
    if (!$user_id || !$delivery_date || !$doc_number) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT d.id, d.company_id, d.doc_number, d.doc_date, c.name as company_name
            FROM documents d
            LEFT JOIN companies c ON d.company_id = c.id
            WHERE d.user_id = ? AND d.delivery_date = ? AND d.doc_number = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $delivery_date, $doc_number]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'document' => [
                    'id' => $result['id'],
                    'company_id' => $result['company_id'],
                    'company_name' => $result['company_name'] ?? 'نامشخص',
                    'doc_number' => $result['doc_number'],
                    'doc_date' => $result['doc_date'] ?? '-'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===== به‌روزرسانی اطلاعات سند =====
if ($action == 'update_document_info') {
    $input = json_decode(file_get_contents('php://input'), true);
    $doc_id = isset($input['doc_id']) ? intval($input['doc_id']) : 0;
    $doc_number = isset($input['doc_number']) ? trim($input['doc_number']) : '';
    $doc_date = isset($input['doc_date']) ? trim($input['doc_date']) : '-';
    $company_id = isset($input['company_id']) ? intval($input['company_id']) : 0;
    
    if (!$doc_id || !$doc_number || !$company_id) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("SELECT id FROM companies WHERE id = ? AND is_active = 1");
        $stmt->execute([$company_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'شرکت نامعتبر']);
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE documents 
            SET doc_number = ?, doc_date = ?, company_id = ? 
            WHERE id = ?
        ");
        $result = $stmt->execute([$doc_number, $doc_date, $company_id, $doc_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'اطلاعات سند با موفقیت تغییر یافت'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'سند یافت نشد یا تغییری انجام نشد']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ===== برگشت امضای کاربر بر اساس تاریخ =====
// اکشن: revert_user_signature_by_date
// پارامترها: user_id, delivery_date
// خروجی: success, message
// ============================================================
if ($action == 'revert_user_signature_by_date') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $delivery_date = isset($input['delivery_date']) ? trim($input['delivery_date']) : '';
    
    if (!$user_id || !$delivery_date) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        // بررسی اینکه آیا امضا وجود دارد
        $stmt = $db->prepare("
            SELECT user_approved_at 
            FROM delivery_approvals 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['user_approved_at'])) {
            echo json_encode(['success' => false, 'error' => 'این تاریخ امضا نشده است']);
            exit;
        }
        
        // برگشت امضا (حذف user_approved_at)
        $stmt = $db->prepare("
            UPDATE delivery_approvals 
            SET user_approved_at = NULL 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        
        echo json_encode([
            'success' => true,
            'message' => 'امضای کاربر با موفقیت برگشت داده شد'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ===== برگشت امضای ادمین بر اساس تاریخ =====
// اکشن: revert_admin_signature_by_date
// پارامترها: user_id, delivery_date
// خروجی: success, message
// ============================================================
if ($action == 'revert_admin_signature_by_date') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $delivery_date = isset($input['delivery_date']) ? trim($input['delivery_date']) : '';
    
    if (!$user_id || !$delivery_date) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        // بررسی اینکه آیا تایید ادمین وجود دارد
        $stmt = $db->prepare("
            SELECT admin_approved_at 
            FROM delivery_approvals 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || empty($result['admin_approved_at'])) {
            echo json_encode(['success' => false, 'error' => 'این تاریخ تایید نشده است']);
            exit;
        }
        
        // برگشت تایید ادمین (حذف admin_approved_at)
        $stmt = $db->prepare("
            UPDATE delivery_approvals 
            SET admin_approved_at = NULL 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$user_id, $delivery_date]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تایید ادمین با موفقیت برگشت داده شد'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ===== تغییر شرکت سند =====
// اکشن: change_document_company
// پارامترها: doc_id, company_id
// خروجی: success, message
// ============================================================
if ($action == 'change_document_company') {
    $input = json_decode(file_get_contents('php://input'), true);
    $doc_id = isset($input['doc_id']) ? intval($input['doc_id']) : 0;
    $company_id = isset($input['company_id']) ? intval($input['company_id']) : 0;
    
    if (!$doc_id || !$company_id) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        // بررسی وجود شرکت
        $stmt = $db->prepare("SELECT id FROM companies WHERE id = ? AND is_active = 1");
        $stmt->execute([$company_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'شرکت نامعتبر']);
            exit;
        }
        
        // تغییر شرکت سند
        $stmt = $db->prepare("UPDATE documents SET company_id = ? WHERE id = ?");
        $stmt->execute([$company_id, $doc_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'نام شرکت با موفقیت تغییر یافت'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'سند یافت نشد']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// ===== تغییر تاریخ تحویل کاربر =====
// اکشن: change_user_delivery_date
// پارامترها: user_id, old_date, new_date
// خروجی: success, doc_count, message
// ============================================================
if ($action == 'change_user_delivery_date') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
    $old_date = isset($input['old_date']) ? trim($input['old_date']) : '';
    $new_date = isset($input['new_date']) ? trim($input['new_date']) : '';
    
    if (!$user_id || !$old_date || !$new_date) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    if ($old_date === $new_date) {
        echo json_encode(['success' => false, 'error' => 'تاریخ جدید با تاریخ فعلی یکسان است']);
        exit;
    }
    
    try {
        // بررسی تعداد اسناد
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $old_date]);
        $doc_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($doc_count == 0) {
            echo json_encode(['success' => false, 'error' => 'هیچ سندی برای این تاریخ وجود ندارد']);
            exit;
        }
        
        // بررسی اینکه تاریخ جدید قبلاً برای این کاربر وجود دارد
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE user_id = ? AND delivery_date = ?");
        $stmt->execute([$user_id, $new_date]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            echo json_encode(['success' => false, 'error' => 'تاریخ جدید قبلاً برای این کاربر وجود دارد']);
            exit;
        }
        
        // شروع تراکنش
        $db->beginTransaction();
        
        // 1. به‌روزرسانی تاریخ تحویل در جدول documents
        $stmt = $db->prepare("
            UPDATE documents 
            SET delivery_date = ? 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$new_date, $user_id, $old_date]);
        
        // 2. به‌روزرسانی تاریخ تحویل در جدول delivery_approvals
        $stmt = $db->prepare("
            UPDATE delivery_approvals 
            SET delivery_date = ? 
            WHERE user_id = ? AND delivery_date = ?
        ");
        $stmt->execute([$new_date, $user_id, $old_date]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'doc_count' => $doc_count,
            'message' => 'تاریخ تحویل با موفقیت تغییر یافت'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
// ===== به‌روزرسانی اطلاعات سند =====
if ($action == 'update_document_info') {
    $input = json_decode(file_get_contents('php://input'), true);
    $doc_id = isset($input['doc_id']) ? intval($input['doc_id']) : 0;
    $doc_number = isset($input['doc_number']) ? trim($input['doc_number']) : '';
    $doc_date = isset($input['doc_date']) ? trim($input['doc_date']) : '-';
    $company_id = isset($input['company_id']) ? intval($input['company_id']) : 0;
    
    if (!$doc_id || !$doc_number || !$company_id) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    try {
        // بررسی وجود شرکت
        $stmt = $db->prepare("SELECT id FROM companies WHERE id = ? AND is_active = 1");
        $stmt->execute([$company_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'شرکت نامعتبر']);
            exit;
        }
        
        // به‌روزرسانی سند
        $stmt = $db->prepare("
            UPDATE documents 
            SET doc_number = ?, doc_date = ?, company_id = ? 
            WHERE id = ?
        ");
        $result = $stmt->execute([$doc_number, $doc_date, $company_id, $doc_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'اطلاعات سند با موفقیت تغییر یافت'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'سند یافت نشد یا تغییری انجام نشد']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ===== دریافت اسناد یک کاربر خاص (از فایل CSV) =====
if ($action == 'get_user_documents') {
    $target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user_name = isset($_GET['user_name']) ? trim($_GET['user_name']) : '';
    $delivery_date = isset($_GET['delivery_date']) ? trim($_GET['delivery_date']) : null;
    
    // ✅ لاگ برای دیباگ
    error_log("get_user_documents: user_id=$target_user_id, user_name=$user_name, delivery_date=$delivery_date");
    
    if (!$target_user_id || empty($user_name)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات ناقص']);
        exit;
    }
    
    $csv_file = __DIR__ . '/../storage/reports/Logs.csv';
    $documents = [];
    
    if (!file_exists($csv_file)) {
        echo json_encode(['success' => false, 'error' => 'فایل CSV یافت نشد']);
        exit;
    }
    
    $handle = fopen($csv_file, 'r');
    if ($handle) {
        fgetcsv($handle, 0, ','); // هدر اول
        fgetcsv($handle, 0, ','); // هدر دوم
        
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) > 8 && !empty(trim($row[4]))) {
                $row_date = trim($row[4]);
                $raw_name = trim($row[3]);
                $clean_name = preg_replace('/[0-9]+$/', '', $raw_name);
                if (empty($clean_name)) $clean_name = $raw_name;
                
                if ($clean_name != $user_name) {
                    continue;
                }
                
                if ($delivery_date && $row_date != $delivery_date) {
                    continue;
                }
                
                if ($row[8] != 'سند حسابداري') {
                    continue;
                }
                
                $company = trim($row[1] ?? '');
                if ($company == 'شركت برش كوه آريا پارت (سهامي خاص)') {
                    continue;
                }
                
                $documents[] = [
                    'doc_number' => trim($row[9] ?? ''),
                    'doc_date' => trim($row[10] ?? ''),
                    'delivery_date' => $row_date,
                    'company_name' => $company,
                    'user_name' => $clean_name,
                    'time' => trim($row[5] ?? '')
                ];
            }
        }
        fclose($handle);
    }
    
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'count' => count($documents),
        'delivery_date' => $delivery_date,
        'user_name' => $user_name
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

// ===== بررسی رمز مدیریت دیتابیس =====
if ($action == 'check_db_password') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    // رمز پیش‌فرض - این رو در یک فایل جداگانه یا دیتابیس ذخیره کنید
    $correctPassword = '001009';
    
    if ($password === $correctPassword) {
        $_SESSION['db_manager_access'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'رمز عبور اشتباه است']);
    }
    exit;
}

// ===== بررسی دسترسی به مدیریت دیتابیس =====
if ($action == 'check_db_access') {
    $hasAccess = isset($_SESSION['db_manager_access']) && $_SESSION['db_manager_access'] === true;
    echo json_encode(['success' => true, 'hasAccess' => $hasAccess]);
    exit;
}

// ===== پاک کردن دسترسی مدیریت دیتابیس =====
if ($action == 'clear_db_access') {
    unset($_SESSION['db_manager_access']);
    echo json_encode(['success' => true]);
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
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    // ✅ اضافه کردن can_view_unit_stats به SELECT
    $stmt = $db->query("SELECT id, username, fullname, unit_name, require_doc_date, lock_delivery_date, can_view_all_archives, can_view_unit_stats,
                        (SELECT COUNT(*) FROM documents WHERE user_id = users.id) as total_docs 
                        FROM users WHERE is_admin = 0 ORDER BY unit_name");
    $users = $stmt->fetchAll();
    
    // ❌ فیلتر حذف شد - همه کاربران نمایش داده می‌شوند
    // $filteredUsers = array_filter($users, function($user) {
    //     return $user['total_docs'] > 0;
    // });
    // $filteredUsers = array_values($filteredUsers);
    
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

// ========== افزودن کاربر جدید ==========
if ($action == 'add_user') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $fullname = $data['fullname'] ?? '';
    $unit_name = $data['unit_name'] ?? '';
    $password = $data['password'] ?? '';
    $require_doc_date = $data['require_doc_date'] ?? 1;
    $can_view_unit_stats = $data['can_view_unit_stats'] ?? 0;
    
    if (empty($username) || empty($fullname) || empty($unit_name) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'تمامی فیلدها الزامی است']);
        exit;
    }
    
    // بررسی تکراری بودن نام کاربری
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'نام کاربری تکراری است']);
        exit;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (username, fullname, unit_name, password, require_doc_date, can_view_unit_stats) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([$username, $fullname, $unit_name, $hashed_password, $require_doc_date, $can_view_unit_stats]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت اضافه شد']);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در افزودن کاربر']);
    }
    exit;
}

// ========== ویرایش کاربر ==========
if ($action == 'update_user') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $username = $data['username'] ?? '';
    $fullname = $data['fullname'] ?? '';
    $unit_name = $data['unit_name'] ?? '';
    $password = $data['password'] ?? '';
    $require_doc_date = $data['require_doc_date'] ?? 1;
    $can_view_unit_stats = $data['can_view_unit_stats'] ?? 0;
    
    if (empty($id) || empty($username) || empty($fullname) || empty($unit_name)) {
        echo json_encode(['success' => false, 'error' => 'تمامی فیلدها الزامی است']);
        exit;
    }
    
    // بررسی تکراری بودن نام کاربری (به جز خود کاربر)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'نام کاربری تکراری است']);
        exit;
    }
    
    // اگر رمز عبور جدید ارسال شده باشد، هش کنید
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET 
                              username = ?, 
                              fullname = ?, 
                              unit_name = ?, 
                              password = ?, 
                              require_doc_date = ?, 
                              can_view_unit_stats = ? 
                              WHERE id = ?");
        $result = $stmt->execute([$username, $fullname, $unit_name, $hashed_password, $require_doc_date, $can_view_unit_stats, $id]);
    } else {
        // بدون تغییر رمز عبور
        $stmt = $db->prepare("UPDATE users SET 
                              username = ?, 
                              fullname = ?, 
                              unit_name = ?, 
                              require_doc_date = ?, 
                              can_view_unit_stats = ? 
                              WHERE id = ?");
        $result = $stmt->execute([$username, $fullname, $unit_name, $require_doc_date, $can_view_unit_stats, $id]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت ویرایش شد']);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در ویرایش کاربر']);
    }
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

// ========== تغییر دسترسی آمار کاربران واحد ==========
if ($action == 'toggle_unit_stats_permission') {
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    $can_view_unit_stats = $data['can_view_unit_stats'] ?? 0;
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'error' => 'شناسه کاربر الزامی است']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE users SET can_view_unit_stats = ? WHERE id = ?");
    $result = $stmt->execute([$can_view_unit_stats, $user_id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'دسترسی با موفقیت به‌روز شد']);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در به‌روزرسانی دسترسی']);
    }
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