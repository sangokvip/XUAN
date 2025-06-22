<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清理登录尝试记录</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #d4af37; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #b8860b; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>清理登录尝试记录</h1>
        
        <?php
        require_once 'config/database_config.php';
        
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 处理清理操作
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';
                
                if ($action === 'clear_all') {
                    $stmt = $pdo->prepare("DELETE FROM login_attempts");
                    $stmt->execute();
                    $deletedCount = $stmt->rowCount();
                    echo "<div class='status success'>✓ 已清理所有登录尝试记录，共删除 {$deletedCount} 条记录</div>";
                }
                
                elseif ($action === 'clear_failed') {
                    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE success = 0");
                    $stmt->execute();
                    $deletedCount = $stmt->rowCount();
                    echo "<div class='status success'>✓ 已清理所有失败的登录尝试记录，共删除 {$deletedCount} 条记录</div>";
                }
                
                elseif ($action === 'clear_old') {
                    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                    $stmt->execute();
                    $deletedCount = $stmt->rowCount();
                    echo "<div class='status success'>✓ 已清理1小时前的登录尝试记录，共删除 {$deletedCount} 条记录</div>";
                }
                
                elseif ($action === 'clear_ip') {
                    $ip = $_POST['ip_address'] ?? '';
                    if (!empty($ip)) {
                        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                        $stmt->execute([$ip]);
                        $deletedCount = $stmt->rowCount();
                        echo "<div class='status success'>✓ 已清理IP {$ip} 的登录尝试记录，共删除 {$deletedCount} 条记录</div>";
                    }
                }
            }
            
            // 获取当前登录尝试统计
            echo "<h2>当前登录尝试统计</h2>";
            
            $totalAttempts = $pdo->query("SELECT COUNT(*) FROM login_attempts")->fetchColumn();
            $failedAttempts = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0")->fetchColumn();
            $successAttempts = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 1")->fetchColumn();
            $recentFailed = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetchColumn();
            
            echo "<div class='info'>";
            echo "<p><strong>总登录尝试次数:</strong> {$totalAttempts}</p>";
            echo "<p><strong>失败尝试次数:</strong> {$failedAttempts}</p>";
            echo "<p><strong>成功尝试次数:</strong> {$successAttempts}</p>";
            echo "<p><strong>最近15分钟失败次数:</strong> {$recentFailed}</p>";
            echo "</div>";
            
            // 显示最近的登录尝试
            echo "<h2>最近的登录尝试记录</h2>";
            $recentAttempts = $pdo->query("
                SELECT username, success, ip_address, attempted_at 
                FROM login_attempts 
                ORDER BY attempted_at DESC 
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($recentAttempts)) {
                echo "<table>";
                echo "<tr><th>用户名</th><th>状态</th><th>IP地址</th><th>尝试时间</th></tr>";
                foreach ($recentAttempts as $attempt) {
                    $status = $attempt['success'] ? '<span style="color: green;">成功</span>' : '<span style="color: red;">失败</span>';
                    echo "<tr>";
                    echo "<td>{$attempt['username']}</td>";
                    echo "<td>{$status}</td>";
                    echo "<td>{$attempt['ip_address']}</td>";
                    echo "<td>{$attempt['attempted_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info'>没有登录尝试记录</div>";
            }
            
            // 显示被锁定的IP和用户
            echo "<h2>当前被锁定的账户/IP</h2>";
            $lockedAccounts = $pdo->query("
                SELECT username, ip_address, COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
                FROM login_attempts 
                WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                GROUP BY username, ip_address
                HAVING failed_count >= 5
                ORDER BY failed_count DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($lockedAccounts)) {
                echo "<div class='warning'>";
                echo "<p><strong>以下账户/IP被锁定：</strong></p>";
                echo "<table>";
                echo "<tr><th>用户名</th><th>IP地址</th><th>失败次数</th><th>最后尝试时间</th></tr>";
                foreach ($lockedAccounts as $account) {
                    echo "<tr>";
                    echo "<td>{$account['username']}</td>";
                    echo "<td>{$account['ip_address']}</td>";
                    echo "<td>{$account['failed_count']}</td>";
                    echo "<td>{$account['last_attempt']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            } else {
                echo "<div class='success'>✓ 当前没有被锁定的账户或IP</div>";
            }
            
            ?>
            
            <h2>清理操作</h2>
            
            <div class="warning">
                <p><strong>注意：</strong>清理操作将永久删除登录尝试记录，请谨慎操作。</p>
            </div>
            
            <!-- 清理所有记录 -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger" onclick="return confirm('确定要清理所有登录尝试记录吗？')">
                    清理所有记录
                </button>
            </form>
            
            <!-- 清理失败记录 -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_failed">
                <button type="submit" class="btn" onclick="return confirm('确定要清理所有失败的登录尝试记录吗？')">
                    清理失败记录
                </button>
            </form>
            
            <!-- 清理旧记录 -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_old">
                <button type="submit" class="btn">
                    清理1小时前的记录
                </button>
            </form>
            
            <!-- 清理特定IP -->
            <div style="margin-top: 20px;">
                <h3>清理特定IP的记录</h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: end;">
                    <div style="flex: 1;">
                        <label for="ip_address">IP地址:</label>
                        <input type="text" id="ip_address" name="ip_address" placeholder="例如: 192.168.1.1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <input type="hidden" name="action" value="clear_ip">
                    <button type="submit" class="btn">清理此IP记录</button>
                </form>
            </div>
            
            <?php
            
        } catch (Exception $e) {
            echo "<div class='status error'>数据库连接失败: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <div class="status info">
            <h3>解决登录锁定的步骤：</h3>
            <ol>
                <li>点击"清理失败记录"按钮清理所有失败的登录尝试</li>
                <li>或者点击"清理所有记录"完全重置登录尝试记录</li>
                <li>清理完成后，立即尝试登录管理后台</li>
                <li>如果仍有问题，检查用户名和密码是否正确</li>
            </ol>
        </div>
        
        <div class="status success">
            <p><strong>推荐操作：</strong></p>
            <p>点击"清理失败记录"按钮，然后访问 <a href="auth/admin_login.php" target="_blank">管理员登录页面</a> 重新登录。</p>
        </div>
        
        <div class="status warning">
            <p><strong>完成后请删除此文件 (clear_login_attempts.php) 以确保安全。</strong></p>
        </div>
    </div>
</body>
</html>
