<?php
/**
 * 管理员账户创建工具
 * 用于手动创建管理员账户
 */

// 检查是否已有管理员
require_once '../config/config.php';

$success = '';
$errors = [];

// 检查数据库连接
try {
    $db = Database::getInstance();
    
    // 检查是否已有管理员
    $adminCount = $db->fetchOne("SELECT COUNT(*) as count FROM admins WHERE is_active = 1")['count'];
    
    if ($adminCount > 0 && !isset($_GET['force'])) {
        die('系统已有管理员账户！如需创建新管理员，请在URL后添加 ?force=1');
    }
    
} catch (Exception $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    
    // 验证输入
    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        $errors[] = '请填写所有字段';
    } elseif (strlen($password) < 6) {
        $errors[] = '密码长度至少6位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确';
    } else {
        try {
            // 检查用户名和邮箱是否已存在
            $existingUser = $db->fetchOne("SELECT id FROM admins WHERE username = ? OR email = ?", [$username, $email]);
            
            if ($existingUser) {
                $errors[] = '用户名或邮箱已存在';
            } else {
                // 创建管理员账户
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $db->insert('admins', [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'full_name' => $fullName,
                    'is_active' => 1
                ]);
                
                $success = '管理员账户创建成功！';
            }
            
        } catch (Exception $e) {
            $errors[] = '创建失败：' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建管理员账户</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', sans-serif; background: #f5f5f5; padding: 50px 20px; }
        .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { padding: 40px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus { border-color: #667eea; outline: none; }
        .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn:hover { background: #5a67d8; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #1565c0; }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: #667eea; text-decoration: none; margin: 0 10px; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👑 创建管理员账户</h1>
            <p>塔罗师展示平台</p>
        </div>
        
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
                
                <div class="links">
                    <a href="dashboard.php">进入管理后台</a>
                    <a href="../index.php">访问网站首页</a>
                </div>
            <?php else: ?>
                
                <?php if ($adminCount > 0): ?>
                    <div class="info">
                        <p><strong>注意：</strong>系统已有 <?php echo $adminCount; ?> 个管理员账户。</p>
                        <p>您正在创建额外的管理员账户。</p>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱 *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? 'admin@example.com'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">姓名 *</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? '系统管理员'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码 *</label>
                        <input type="password" id="password" name="password" required>
                        <small style="color: #666;">密码长度至少6位</small>
                    </div>
                    
                    <button type="submit" class="btn">创建管理员账户</button>
                </form>
                
                <div class="links">
                    <a href="../index.php">返回网站首页</a>
                    <?php if ($adminCount > 0): ?>
                        <a href="dashboard.php">管理后台登录</a>
                    <?php endif; ?>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
