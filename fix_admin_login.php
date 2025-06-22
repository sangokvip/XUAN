<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修复管理员登录</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #d4af37; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 5px; }
        .btn:hover { background: #b8860b; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        .admin-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>管理员登录修复工具</h1>
        
        <?php
        require_once 'config/database_config.php';
        
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 处理表单提交
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                
                if ($action === 'create_admin') {
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $password = $_POST['password'];
                    $fullName = trim($_POST['full_name']);
                    
                    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
                        echo "<div class='status error'>请填写所有字段</div>";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO admins (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$username, $email, $hashedPassword, $fullName]);
                            echo "<div class='status success'>✓ 管理员账户创建成功！</div>";
                        } catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                echo "<div class='status error'>用户名或邮箱已存在</div>";
                            } else {
                                echo "<div class='status error'>创建失败: " . $e->getMessage() . "</div>";
                            }
                        }
                    }
                }
                
                elseif ($action === 'update_admin') {
                    $adminId = (int)$_POST['admin_id'];
                    $username = trim($_POST['username']);
                    $password = $_POST['password'];
                    
                    if (empty($username)) {
                        echo "<div class='status error'>用户名不能为空</div>";
                    } else {
                        if (!empty($password)) {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE admins SET username = ?, password_hash = ? WHERE id = ?");
                            $stmt->execute([$username, $hashedPassword, $adminId]);
                            echo "<div class='status success'>✓ 管理员用户名和密码已更新</div>";
                        } else {
                            $stmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
                            $stmt->execute([$username, $adminId]);
                            echo "<div class='status success'>✓ 管理员用户名已更新</div>";
                        }
                    }
                }
            }
            
            // 获取当前管理员信息
            echo "<h2>当前管理员账户</h2>";
            $admins = $pdo->query("SELECT * FROM admins ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($admins)) {
                echo "<div class='status warning'>没有找到管理员账户！需要创建新的管理员。</div>";
            } else {
                foreach ($admins as $admin) {
                    echo "<div class='admin-info'>";
                    echo "<h3>管理员 #{$admin['id']}</h3>";
                    echo "<p><strong>用户名:</strong> " . ($admin['username'] ?: '未设置') . "</p>";
                    echo "<p><strong>邮箱:</strong> {$admin['email']}</p>";
                    echo "<p><strong>姓名:</strong> {$admin['full_name']}</p>";
                    echo "<p><strong>状态:</strong> " . ($admin['is_active'] ? '激活' : '禁用') . "</p>";
                    echo "<p><strong>创建时间:</strong> {$admin['created_at']}</p>";
                    echo "</div>";
                }
            }
            
            // 测试登录功能
            echo "<h2>测试登录功能</h2>";
            if (!empty($admins)) {
                echo "<div class='info'>";
                echo "<p>请尝试使用以下信息登录管理后台：</p>";
                echo "<ul>";
                foreach ($admins as $admin) {
                    if (!empty($admin['username'])) {
                        echo "<li><strong>用户名:</strong> {$admin['username']} | <a href='auth/admin_login.php' target='_blank'>登录测试</a></li>";
                    }
                }
                echo "</ul>";
                echo "</div>";
            }
            
            ?>
            
            <!-- 更新现有管理员 -->
            <?php if (!empty($admins)): ?>
                <h2>更新现有管理员</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_admin">
                    
                    <div class="form-group">
                        <label for="admin_id">选择管理员:</label>
                        <select name="admin_id" id="admin_id" required>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?php echo $admin['id']; ?>">
                                    <?php echo $admin['full_name']; ?> (<?php echo $admin['email']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">新用户名:</label>
                        <input type="text" id="username" name="username" required>
                        <small>建议使用简单易记的用户名，如: admin</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">新密码 (可选):</label>
                        <input type="password" id="password" name="password">
                        <small>留空则不修改密码</small>
                    </div>
                    
                    <button type="submit" class="btn">更新管理员信息</button>
                </form>
            <?php endif; ?>
            
            <!-- 创建新管理员 -->
            <h2>创建新管理员</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_admin">
                
                <div class="form-group">
                    <label for="new_username">用户名:</label>
                    <input type="text" id="new_username" name="username" required>
                    <small>建议使用: admin</small>
                </div>
                
                <div class="form-group">
                    <label for="new_email">邮箱:</label>
                    <input type="email" id="new_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">密码:</label>
                    <input type="password" id="new_password" name="password" required>
                    <small>建议使用: admin123</small>
                </div>
                
                <div class="form-group">
                    <label for="new_full_name">姓名:</label>
                    <input type="text" id="new_full_name" name="full_name" required>
                </div>
                
                <button type="submit" class="btn">创建管理员</button>
            </form>
            
            <?php
            
        } catch (Exception $e) {
            echo "<div class='status error'>数据库连接失败: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <div class="status warning">
            <h3>使用说明：</h3>
            <ol>
                <li>如果现有管理员没有用户名，请使用"更新现有管理员"功能设置用户名</li>
                <li>建议用户名设置为简单的 <code>admin</code></li>
                <li>建议密码设置为 <code>admin123</code>（测试用）</li>
                <li>设置完成后，使用用户名和密码登录管理后台</li>
                <li>修复完成后请删除此文件 (fix_admin_login.php)</li>
            </ol>
        </div>
        
        <div class="status info">
            <p><strong>快速修复建议：</strong></p>
            <p>如果您想快速解决问题，建议：</p>
            <ul>
                <li>用户名设置为: <code>admin</code></li>
                <li>密码设置为: <code>admin123</code></li>
                <li>然后访问: <a href="auth/admin_login.php" target="_blank">管理员登录页面</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
