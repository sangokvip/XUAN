<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
if (!isAdminLoggedIn()) {
    header('Location: ../auth/admin_login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$error = '';

// 获取当前管理员信息
$adminId = $_SESSION['admin_id'];
$admin = $db->fetchOne("SELECT * FROM admins WHERE id = ?", [$adminId]);

if ($_POST['action'] ?? '' === 'test_password') {
    $testPassword = $_POST['test_password'] ?? '';
    
    if (empty($testPassword)) {
        $error = '请输入要测试的密码';
    } else {
        if (verifyPassword($testPassword, $admin['password_hash'])) {
            $message = "✅ 密码验证成功！输入的密码与当前密码匹配。";
        } else {
            $error = "❌ 密码验证失败！输入的密码与当前密码不匹配。";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码修改测试 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .test-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
            border-left: 4px solid #007bff;
        }
        
        .test-form {
            background: #fff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>管理员密码修改测试</h1>
        
        <div class="instructions">
            <h3>📋 测试说明</h3>
            <p>此页面用于测试管理员密码修改功能是否正常工作。您可以：</p>
            <ul>
                <li>查看当前管理员账户信息</li>
                <li>测试密码验证功能</li>
                <li>访问密码修改页面</li>
            </ul>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="admin-info">
            <h3>👤 当前管理员信息</h3>
            <p><strong>ID：</strong><?php echo $admin['id']; ?></p>
            <p><strong>用户名：</strong><?php echo h($admin['username']); ?></p>
            <p><strong>邮箱：</strong><?php echo h($admin['email']); ?></p>
            <p><strong>姓名：</strong><?php echo h($admin['full_name']); ?></p>
            <p><strong>创建时间：</strong><?php echo $admin['created_at']; ?></p>
            <p><strong>密码哈希：</strong><code style="font-size: 12px; word-break: break-all;"><?php echo substr($admin['password_hash'], 0, 50) . '...'; ?></code></p>
        </div>
        
        <div class="test-form">
            <h3>🔐 密码验证测试</h3>
            <p>输入密码来测试当前密码是否正确：</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="test_password">
                
                <div class="form-group">
                    <label for="test_password">输入密码进行验证</label>
                    <input type="password" id="test_password" name="test_password" 
                           placeholder="输入您认为正确的密码" required>
                </div>
                
                <button type="submit" class="btn btn-primary">验证密码</button>
            </form>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="settings.php" class="btn btn-primary">前往密码修改页面</a>
            <a href="dashboard.php" class="btn btn-secondary">返回管理后台</a>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">
            <h3>🛠️ 功能测试步骤</h3>
            <ol>
                <li><strong>验证当前密码：</strong>在上面的表单中输入您的当前密码，确认验证功能正常</li>
                <li><strong>修改密码：</strong>点击"前往密码修改页面"按钮，在设置页面修改密码</li>
                <li><strong>重新登录：</strong>密码修改成功后，系统会自动退出，需要用新密码重新登录</li>
                <li><strong>验证新密码：</strong>重新登录后，再次访问此页面验证新密码</li>
            </ol>
            
            <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-radius: 4px;">
                <strong>💡 提示：</strong>如果您忘记了当前密码，可以通过数据库直接重置，或者联系系统管理员。
            </div>
        </div>
    </div>
</body>
</html>
