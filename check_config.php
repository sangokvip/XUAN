<?php
// 配置检查页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配置检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #d4af37; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        code { background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        .config-item { margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>塔罗师平台 - 配置检查</h1>
        
        <?php
        echo "<h2>1. 配置文件检查</h2>";
        
        $configFiles = [
            'config/database_config.php' => '数据库配置文件',
            'config/site_config.php' => '网站配置文件',
            'config/installed.lock' => '安装锁定文件'
        ];
        
        foreach ($configFiles as $file => $desc) {
            if (file_exists($file)) {
                echo "<div class='status success'>✓ {$desc} 存在</div>";
            } else {
                echo "<div class='status error'>✗ {$desc} 不存在</div>";
            }
        }
        
        echo "<h2>2. 数据库配置</h2>";
        
        // 加载配置
        if (file_exists('config/database_config.php')) {
            require_once 'config/database_config.php';
            
            echo "<div class='config-item'>";
            echo "<strong>数据库主机:</strong> " . (defined('DB_HOST') ? DB_HOST : '未定义') . "<br>";
            echo "<strong>数据库名称:</strong> " . (defined('DB_NAME') ? DB_NAME : '未定义') . "<br>";
            echo "<strong>数据库用户:</strong> " . (defined('DB_USER') ? DB_USER : '未定义') . "<br>";
            echo "<strong>密码设置:</strong> " . (defined('DB_PASS') ? (empty(DB_PASS) ? '无密码' : '已设置密码') : '未定义') . "<br>";
            echo "</div>";
            
            echo "<h2>3. 数据库连接测试</h2>";
            
            try {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USER,
                    DB_PASS
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo "<div class='status success'>✓ 数据库连接成功</div>";
                
                // 检查表结构
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $requiredTables = ['users', 'admins', 'readers', 'settings', 'reader_registration_links', 'contact_views', 'login_attempts'];
                
                echo "<h2>4. 数据库表检查</h2>";
                foreach ($requiredTables as $table) {
                    if (in_array($table, $tables)) {
                        echo "<div class='status success'>✓ 表 {$table} 存在</div>";
                    } else {
                        echo "<div class='status error'>✗ 表 {$table} 不存在</div>";
                    }
                }
                
                // 检查管理员账户
                $adminCount = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
                if ($adminCount > 0) {
                    echo "<div class='status success'>✓ 管理员账户已创建 ({$adminCount} 个)</div>";
                } else {
                    echo "<div class='status error'>✗ 没有管理员账户</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='status error'>✗ 数据库连接失败: " . $e->getMessage() . "</div>";
                
                echo "<h2>解决方案</h2>";
                echo "<div class='info'>";
                echo "<p>请检查以下项目：</p>";
                echo "<ol>";
                echo "<li>确认数据库 <code>" . DB_NAME . "</code> 已创建</li>";
                echo "<li>确认用户 <code>" . DB_USER . "</code> 有访问该数据库的权限</li>";
                echo "<li>如果数据库有密码，请编辑 <code>config/database_config.php</code> 文件</li>";
                echo "<li>确认已导入 <code>database/install.sql</code> 文件</li>";
                echo "</ol>";
                echo "</div>";
            }
        } else {
            echo "<div class='status error'>✗ 数据库配置文件不存在</div>";
        }
        ?>
        
        <h2>5. 下一步操作</h2>
        <div class="info">
            <p>如果所有检查都通过，您可以：</p>
            <ul>
                <li><a href="index.php">访问网站首页</a></li>
                <li><a href="auth/admin_login.php">访问管理后台</a></li>
            </ul>
            
            <p>如果有问题，请：</p>
            <ol>
                <li>检查数据库用户密码是否正确</li>
                <li>确认数据库已创建并导入了表结构</li>
                <li>检查用户权限设置</li>
            </ol>
        </div>
        
        <p><small>检查完成后，为了安全请删除此文件 (check_config.php)</small></p>
    </div>
</body>
</html>
