<?php
session_start();
require_once '../config/config.php';

$errors = [];
$success = '';

// 如果已登录，重定向到首页
if (isLoggedIn()) {
    redirect('../index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? '')
    ];
    
    $result = registerUser($data);
    if ($result['success']) {
        $success = '注册成功！请登录您的账户。';
    } else {
        $errors = $result['errors'];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-form">
            <h1>用户注册</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo h($success); ?>
                    <p><a href="login.php">立即登录</a></p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">用户名</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo h($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱地址</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo h($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">姓名</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">手机号码（可选）</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" required>
                        <small>至少<?php echo PASSWORD_MIN_LENGTH; ?>个字符</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">确认密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">注册</button>
                </form>
            <?php endif; ?>
            
            <div class="auth-links">
                <p>已有账户？<a href="login.php">立即登录</a></p>
                <p><a href="../index.php">返回首页</a></p>
            </div>
        </div>
    </div>
</body>
</html>
