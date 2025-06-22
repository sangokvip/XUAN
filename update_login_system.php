<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录系统更新</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #d4af37; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn:hover { background: #b8860b; }
    </style>
</head>
<body>
    <div class="container">
        <h1>登录系统更新工具</h1>
        
        <div class="info">
            <h3>更新内容：</h3>
            <ul>
                <li>将登录方式从邮箱+密码改为用户名+密码</li>
                <li>更新数据库表结构</li>
                <li>修改所有登录页面</li>
            </ul>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo "<h2>更新进度</h2>";
            
            try {
                require_once 'config/database_config.php';
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // 1. 检查并更新 login_attempts 表结构
                echo "<h3>1. 更新数据库表结构</h3>";
                
                // 检查是否存在 email 字段
                $columns = $pdo->query("SHOW COLUMNS FROM login_attempts")->fetchAll(PDO::FETCH_COLUMN);
                
                if (in_array('email', $columns)) {
                    // 重命名字段
                    $pdo->exec("ALTER TABLE login_attempts CHANGE email username VARCHAR(100) NOT NULL");
                    echo "<div class='status success'>✓ login_attempts 表的 email 字段已重命名为 username</div>";
                    
                    // 更新索引
                    $pdo->exec("DROP INDEX idx_email_time ON login_attempts");
                    $pdo->exec("CREATE INDEX idx_username_time ON login_attempts (username, attempted_at)");
                    echo "<div class='status success'>✓ 索引已更新</div>";
                } else {
                    echo "<div class='status success'>✓ login_attempts 表结构已是最新版本</div>";
                }
                
                // 2. 显示当前管理员信息
                echo "<h3>2. 当前管理员账户信息</h3>";
                $admins = $pdo->query("SELECT id, username, email, full_name FROM admins")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($admins)) {
                    echo "<div class='status info'>";
                    echo "<p><strong>管理员登录信息：</strong></p>";
                    foreach ($admins as $admin) {
                        echo "<p>用户名: <strong>{$admin['username']}</strong> | 姓名: {$admin['full_name']} | 邮箱: {$admin['email']}</p>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='status error'>没有找到管理员账户</div>";
                }
                
                // 3. 显示塔罗师信息
                echo "<h3>3. 塔罗师账户信息</h3>";
                $readers = $pdo->query("SELECT id, username, email, full_name FROM readers LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($readers)) {
                    echo "<div class='status info'>";
                    echo "<p><strong>塔罗师登录信息（前5个）：</strong></p>";
                    foreach ($readers as $reader) {
                        echo "<p>用户名: <strong>{$reader['username']}</strong> | 姓名: {$reader['full_name']} | 邮箱: {$reader['email']}</p>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='status info'>暂无塔罗师账户</div>";
                }
                
                // 4. 显示用户信息
                echo "<h3>4. 用户账户信息</h3>";
                $users = $pdo->query("SELECT id, username, email, full_name FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($users)) {
                    echo "<div class='status info'>";
                    echo "<p><strong>用户登录信息（前5个）：</strong></p>";
                    foreach ($users as $user) {
                        echo "<p>用户名: <strong>{$user['username']}</strong> | 姓名: {$user['full_name']} | 邮箱: {$user['email']}</p>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='status info'>暂无普通用户账户</div>";
                }
                
                echo "<div class='status success'>";
                echo "<h3>更新完成！</h3>";
                echo "<p>登录系统已成功更新为用户名+密码模式。</p>";
                echo "<p>现在您可以使用用户名和密码登录：</p>";
                echo "<ul>";
                echo "<li><a href='auth/admin_login.php' target='_blank'>管理员登录</a></li>";
                echo "<li><a href='auth/reader_login.php' target='_blank'>塔罗师登录</a></li>";
                echo "<li><a href='auth/login.php' target='_blank'>用户登录</a></li>";
                echo "</ul>";
                echo "</div>";
                
                echo "<div class='status warning'>";
                echo "<p><strong>重要提醒：</strong></p>";
                echo "<ul>";
                echo "<li>所有登录页面现在使用用户名而不是邮箱</li>";
                echo "<li>请通知所有用户这个变更</li>";
                echo "<li>更新完成后请删除此文件 (update_login_system.php)</li>";
                echo "</ul>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='status error'>更新失败: " . $e->getMessage() . "</div>";
            }
            
        } else {
            ?>
            
            <div class="warning">
                <p><strong>注意：</strong>此操作将修改数据库表结构和登录方式。请确保已备份数据库。</p>
            </div>
            
            <form method="POST">
                <button type="submit" class="btn">开始更新登录系统</button>
            </form>
            
            <div class="info">
                <h3>更新后的变化：</h3>
                <ul>
                    <li><strong>管理员登录：</strong>使用用户名 + 密码</li>
                    <li><strong>塔罗师登录：</strong>使用用户名 + 密码</li>
                    <li><strong>用户登录：</strong>使用用户名 + 密码</li>
                    <li><strong>数据库：</strong>login_attempts 表的 email 字段改为 username</li>
                </ul>
            </div>
            
            <?php
        }
        ?>
    </div>
</body>
</html>
