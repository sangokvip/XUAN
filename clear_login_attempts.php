<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔓 清除登录尝试记录工具 - 解决登录锁定问题</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        h1 {
            color: #d4af37;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
        }
        h3 {
            color: #555;
            margin-top: 25px;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .btn {
            background: #d4af37;
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .btn:hover {
            background: #b8860b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .text-center {
            text-align: center;
        }
        .intro {
            text-align: center;
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            body { margin: 10px; }
            .container { padding: 20px; }
            h1 { font-size: 2rem; }
            table { font-size: 14px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔓 清除登录尝试记录工具</h1>
        <div class="intro">
            <p>解决"登录尝试次数过多，请15分钟后再试"的问题</p>
            <p>安全、快速、有效的登录锁定解决方案</p>
        </div>
        
        <?php
        session_start();
        require_once 'config/config.php';

        // 安全验证 - 只允许管理员或本地访问
        $allowedIPs = ['127.0.0.1', '::1', 'localhost'];
        $currentIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $isLocalhost = in_array($currentIP, $allowedIPs) ||
                       strpos($currentIP, '192.168.') === 0 ||
                       strpos($currentIP, '10.') === 0;

        $hasAccess = false;
        if (isset($_SESSION['admin_id']) || $isLocalhost) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            echo "<div class='status error'>❌ 访问被拒绝：只有管理员或本地访问可以使用此工具</div>";
            echo "<div class='status info'>💡 如果您是管理员，请先<a href='auth/admin_login.php'>登录管理后台</a></div>";
            echo "</div></body></html>";
            exit;
        }

        try {
            $db = Database::getInstance();
            
            // 处理清理操作
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? '';

                if ($action === 'clear_all') {
                    $result = $db->query("DELETE FROM login_attempts");
                    echo "<div class='status success'>✓ 已清理所有登录尝试记录</div>";
                }

                elseif ($action === 'clear_failed') {
                    $result = $db->query("DELETE FROM login_attempts WHERE success = 0");
                    echo "<div class='status success'>✓ 已清理所有失败的登录尝试记录</div>";
                }

                elseif ($action === 'clear_old') {
                    $result = $db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                    echo "<div class='status success'>✓ 已清理15分钟前的登录尝试记录</div>";
                }

                elseif ($action === 'clear_ip') {
                    $ip = trim($_POST['ip_address'] ?? '');
                    if (!empty($ip)) {
                        $result = $db->query("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]);
                        echo "<div class='status success'>✓ 已清理IP {$ip} 的登录尝试记录</div>";
                    } else {
                        echo "<div class='status error'>❌ 请输入有效的IP地址</div>";
                    }
                }

                elseif ($action === 'clear_username') {
                    $username = trim($_POST['username'] ?? '');
                    if (!empty($username)) {
                        $result = $db->query("DELETE FROM login_attempts WHERE username = ?", [$username]);
                        echo "<div class='status success'>✓ 已清理用户 {$username} 的登录尝试记录</div>";
                    } else {
                        echo "<div class='status error'>❌ 请输入有效的用户名</div>";
                    }
                }

                elseif ($action === 'unblock_all_ips') {
                    echo "<div class='status info'>💡 当前系统使用简单的IP封锁机制，无需数据库解封操作</div>";
                }

                elseif ($action === 'unblock_ip') {
                    echo "<div class='status info'>💡 当前系统使用简单的IP封锁机制，无需数据库解封操作</div>";
                }
            }
            
            // 获取当前登录尝试统计
            echo "<h2>📊 当前登录尝试统计</h2>";

            $totalAttempts = $db->fetchOne("SELECT COUNT(*) as count FROM login_attempts")['count'] ?? 0;
            $failedAttempts = $db->fetchOne("SELECT COUNT(*) as count FROM login_attempts WHERE success = 0")['count'] ?? 0;
            $successAttempts = $db->fetchOne("SELECT COUNT(*) as count FROM login_attempts WHERE success = 1")['count'] ?? 0;
            $recentFailed = $db->fetchOne("SELECT COUNT(*) as count FROM login_attempts WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)")['count'] ?? 0;
            
            echo "<div class='info'>";
            echo "<p><strong>📈 总登录尝试次数:</strong> {$totalAttempts}</p>";
            echo "<p><strong>❌ 失败尝试次数:</strong> {$failedAttempts}</p>";
            echo "<p><strong>✅ 成功尝试次数:</strong> {$successAttempts}</p>";
            echo "<p><strong>⏰ 最近15分钟失败次数:</strong> {$recentFailed}</p>";
            if ($recentFailed >= 5) {
                echo "<p style='color: red;'><strong>🚨 警告：</strong>最近15分钟失败次数过多，可能导致登录锁定</p>";
            }
            echo "</div>";
            
            // 显示最近的登录尝试
            echo "<h2>📋 最近的登录尝试记录</h2>";
            $recentAttempts = $db->fetchAll("
                SELECT username, success, ip_address, attempted_at
                FROM login_attempts
                ORDER BY attempted_at DESC
                LIMIT 20
            ");
            
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
            echo "<h2>🔒 当前被锁定的账户/IP</h2>";
            $lockedAccounts = $db->fetchAll("
                SELECT username, ip_address, COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
                FROM login_attempts
                WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                GROUP BY username, ip_address
                HAVING failed_count >= 5
                ORDER BY failed_count DESC
            ");
            
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

            <h2>🧹 清理操作</h2>

            <div class="warning">
                <p><strong>⚠️ 注意：</strong>清理操作将永久删除登录尝试记录，请谨慎操作。</p>
            </div>

            <!-- 快速解决方案 -->
            <div class="success" style="margin-bottom: 20px;">
                <h3>🚀 快速解决登录锁定问题</h3>
                <p><strong>推荐操作：</strong>点击下方"清理失败记录"按钮，然后立即尝试登录。</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_failed">
                    <button type="submit" class="btn" style="background: #28a745; color: white; font-size: 16px; padding: 15px 30px;" onclick="return confirm('确定要清理所有失败的登录尝试记录吗？这将解除所有登录锁定。')">
                        🔓 立即解除登录锁定
                    </button>
                </form>
            </div>

            <!-- 其他清理选项 -->
            <h3>其他清理选项</h3>

            <!-- 清理所有记录 -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger" onclick="return confirm('确定要清理所有登录尝试记录吗？这将删除所有历史记录。')">
                    🗑️ 清理所有记录
                </button>
            </form>

            <!-- 清理旧记录 -->
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="clear_old">
                <button type="submit" class="btn">
                    ⏰ 清理15分钟前的记录
                </button>
            </form>



            <!-- 清理特定IP -->
            <div style="margin-top: 20px;">
                <h3>🎯 清理特定IP的记录</h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: end; margin-bottom: 15px;">
                    <div style="flex: 1;">
                        <label for="ip_address">IP地址:</label>
                        <input type="text" id="ip_address" name="ip_address" placeholder="例如: 192.168.1.1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <input type="hidden" name="action" value="clear_ip">
                    <button type="submit" class="btn">清理此IP记录</button>
                </form>
            </div>

            <!-- 清理特定用户名 -->
            <div style="margin-top: 20px;">
                <h3>👤 清理特定用户的记录</h3>
                <form method="POST" style="display: flex; gap: 10px; align-items: end;">
                    <div style="flex: 1;">
                        <label for="username">用户名:</label>
                        <input type="text" id="username" name="username" placeholder="例如: admin" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <input type="hidden" name="action" value="clear_username">
                    <button type="submit" class="btn">清理此用户记录</button>
                </form>
            </div>
            
            <?php

        } catch (Exception $e) {
            echo "<div class='status error'>❌ 数据库操作失败: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<div class='status info'>💡 请检查数据库连接配置或联系技术支持。</div>";
        }
        ?>

        <div class="status info">
            <h3>📖 解决登录锁定的步骤：</h3>
            <ol>
                <li><strong>快速解决：</strong>点击上方绿色的"🔓 立即解除登录锁定"按钮</li>
                <li><strong>清理完成后：</strong>立即访问 <a href="auth/admin_login.php" target="_blank" style="color: #d4af37; font-weight: bold;">管理员登录页面</a> 重新登录</li>
                <li><strong>如果仍有问题：</strong>检查用户名和密码是否正确</li>
                <li><strong>持续问题：</strong>可以尝试"清理所有记录"进行完全重置</li>
            </ol>
        </div>

        <div class="status success">
            <h3>🎯 常见问题解决方案：</h3>
            <ul>
                <li><strong>管理员登录被锁：</strong>点击"立即解除登录锁定"</li>
                <li><strong>特定IP被封：</strong>使用"清理特定IP"功能</li>
                <li><strong>用户反馈无法登录：</strong>清理该用户的登录记录</li>
                <li><strong>系统整体登录异常：</strong>清理所有失败记录</li>
            </ul>
        </div>

        <div class="status warning">
            <h3>⚠️ 安全提醒</h3>
            <p><strong>使用完毕后请立即删除此文件 (clear_login_attempts.php) 以确保网站安全。</strong></p>
            <p>此工具仅供紧急情况使用，不建议长期保留在服务器上。</p>
        </div>

        <div class="status info">
            <p><strong>🔗 相关链接：</strong></p>
            <p>
                <a href="auth/admin_login.php" target="_blank" style="color: #d4af37;">管理员登录</a> |
                <a href="admin/dashboard.php" target="_blank" style="color: #d4af37;">管理后台</a> |
                <a href="index.php" target="_blank" style="color: #d4af37;">网站首页</a>
            </p>
        </div>
    </div>
</body>
</html>
