<?php
session_start();
require_once '../config/config.php';

$error = '';
$success = '';

// 检查URL参数中的消息
if (isset($_GET['message'])) {
    $success = $_GET['message'];
}

// 如果已登录，重定向到管理后台
if (isAdminLoggedIn()) {
    redirect('../admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请填写所有字段';
    } else {
        $result = loginAdmin($username, $password);
        if ($result['success']) {
            $redirectTo = $_GET['redirect'] ?? '../admin/dashboard.php';
            redirect($redirectTo);
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-form">
            <h1>管理员登录</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required
                           value="<?php echo h($_POST['username'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full">登录</button>
            </form>
            
            <div class="auth-links">
                <p><a href="../index.php">返回首页</a></p>
            </div>
        </div>
    </div>
</body>
</html>
