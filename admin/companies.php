<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullname = $_POST['fullname'];
        $gender = $_POST['gender'];
        $unit_name = $_POST['unit_name'];
        $require_doc_date = isset($_POST['require_doc_date']) ? 1 : 0;
        
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, gender, unit_name, require_doc_date) VALUES (?,?,?,?,?,?)");
        if ($stmt->execute([$username, $password, $fullname, $gender, $unit_name, $require_doc_date])) {
            $message = "کاربر با موفقیت اضافه شد";
        } else {
            $message = "خطا در افزودن کاربر";
        }
    } elseif (isset($_POST['toggle_require'])) {
        $id = $_POST['user_id'];
        $require = $_POST['require_doc_date'];
        $db->prepare("UPDATE users SET require_doc_date = ? WHERE id = ?")->execute([$require, $id]);
        $message = "تنظیمات به‌روز شد";
    }
}

$users = $db->query("SELECT * FROM users WHERE is_admin = 0 ORDER BY unit_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>مدیریت کاربران</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f0f4f8; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 32px; padding: 2rem; }
        h1 { color: #1e293b; }
        form { background: #f8fafc; padding: 20px; border-radius: 24px; margin-bottom: 30px; }
        input, select { padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 16px; margin: 5px; }
        button { background: #4361ee; color: white; border: none; padding: 8px 20px; border-radius: 30px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: center; }
        th { background: #eef2ff; }
        .message { background: #06d6a0; padding: 10px; border-radius: 16px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>👥 مدیریت کاربران</h1>
    <a href="../index.php" style="color:#4361ee;">← بازگشت</a>
    <?php if($message): ?><div class="message"><?php echo $message; ?></div><?php endif; ?>
    
    <form method="POST">
        <h3>➕ افزودن کاربر جدید</h3>
        <input type="text" name="username" placeholder="نام کاربری" required>
        <input type="password" name="password" placeholder="رمز عبور" required>
        <input type="text" name="fullname" placeholder="نام و نام خانوادگی" required>
        <select name="gender"><option value="male">آقا</option><option value="female">خانم</option></select>
        <input type="text" name="unit_name" placeholder="واحد (درآمد/فاکتور/خزانه/پیمانکاران)" required>
        <label><input type="checkbox" name="require_doc_date" value="1" checked> تاریخ سند اجباری باشد</label>
        <button type="submit" name="add">افزودن کاربر</button>
    </form>
    
    <h3>📋 لیست کاربران</h3>
    <table>
        <thead><tr><th>نام کاربری</th><th>نام کامل</th><th>واحد</th><th>جنسیت</th><th>تاریخ سند اجباری</th><th>عملیات</th></tr></thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                <td><?php echo htmlspecialchars($user['unit_name']); ?></td>
                <td><?php echo $user['gender'] == 'male' ? 'آقا' : 'خانم'; ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="require_doc_date" value="<?php echo $user['require_doc_date'] ? 0 : 1; ?>">
                        <button type="submit" name="toggle_require" style="background:<?php echo $user['require_doc_date'] ? '#06d6a0' : '#e11d48'; ?>">
                            <?php echo $user['require_doc_date'] ? 'فعال' : 'غیرفعال'; ?>
                        </button>
                    </form>
                </td>
                <td>
                    <form method="POST" onsubmit="return confirm('حذف کاربر؟')" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                        <button type="submit" name="delete" style="background:#e11d48;">حذف</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
if (isset($_POST['delete'])) {
    $db->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0")->execute([$_POST['delete_id']]);
    header('Location: users.php');
}
?>
</body>
</html>