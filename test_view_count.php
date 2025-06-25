<?php
/**
 * 测试查看次数防刷机制
 */
session_start();
require_once 'config/config.php';
require_once 'includes/ViewCountManager.php';

// 测试用的塔罗师ID（请替换为实际存在的ID）
$testReaderId = 1;

$viewCountManager = new ViewCountManager();
$messages = [];

// 处理测试请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_view'])) {
        $messages[] = "🔍 开始测试查看记录...";

        // 显示当前状态
        $beforeViews = $viewCountManager->getViewCount($testReaderId);
        $messages[] = "📊 测试前查看次数: $beforeViews";

        // 获取当前访客信息
        $currentIP = $viewCountManager->getClientIP();
        $currentSession = session_id();
        $currentUser = $_SESSION['user_id'] ?? null;
        $messages[] = "🌐 当前IP: $currentIP";
        $messages[] = "🔑 当前Session: " . substr($currentSession, 0, 10) . "...";
        $messages[] = "👤 当前用户: " . ($currentUser ? $currentUser : '未登录');

        // 检查是否在冷却期
        try {
            $db = Database::getInstance();
            $cooldownTime = date('Y-m-d H:i:s', time() - (30 * 60));
            $recentViews = $db->fetchAll(
                "SELECT * FROM reader_view_logs
                 WHERE reader_id = ? AND ip_address = ? AND created_at > ?
                 ORDER BY created_at DESC",
                [$testReaderId, $currentIP, $cooldownTime]
            );

            if (!empty($recentViews)) {
                $messages[] = "⏰ 发现 " . count($recentViews) . " 条30分钟内的记录:";
                foreach ($recentViews as $view) {
                    $messages[] = "  - 时间: {$view['created_at']}, IP: {$view['ip_address']}";
                }
            } else {
                $messages[] = "✅ 30分钟内无访问记录，应该可以记录";
            }
        } catch (Exception $e) {
            $messages[] = "❌ 检查冷却期时出错: " . $e->getMessage();
        }

        // 执行记录
        $result = $viewCountManager->recordView($testReaderId, 30);

        // 显示结果
        $afterViews = $viewCountManager->getViewCount($testReaderId);
        $messages[] = "📊 测试后查看次数: $afterViews";
        $messages[] = "📈 查看次数变化: " . ($afterViews - $beforeViews);

        if ($result) {
            $messages[] = "✅ recordView() 返回 true - 记录成功";
        } else {
            $messages[] = "❌ recordView() 返回 false - 记录失败（应该在冷却期内）";
        }
    }
    
    if (isset($_POST['check_table'])) {
        try {
            $db = Database::getInstance();

            // 检查reader_view_logs表
            $tableExists = $db->fetchOne("SHOW TABLES LIKE 'reader_view_logs'");
            if ($tableExists) {
                $messages[] = "✅ reader_view_logs表存在";

                $count = $db->fetchOne("SELECT COUNT(*) as count FROM reader_view_logs")['count'];
                $messages[] = "📊 表中共有 {$count} 条记录";

                // 检查表结构
                $columns = $db->fetchAll("DESCRIBE reader_view_logs");
                $messages[] = "🏗️ 表结构：";
                foreach ($columns as $col) {
                    $messages[] = "  - {$col['Field']}: {$col['Type']}";
                }

                $recentLogs = $db->fetchAll(
                    "SELECT * FROM reader_view_logs WHERE reader_id = ? ORDER BY created_at DESC LIMIT 5",
                    [$testReaderId]
                );

                if (!empty($recentLogs)) {
                    $messages[] = "📝 最近的查看记录：";
                    foreach ($recentLogs as $log) {
                        $messages[] = "  - ID: {$log['id']}, IP: {$log['ip_address']}, 时间: {$log['created_at']}, Session: " . substr($log['session_id'], 0, 10) . "...";
                    }
                } else {
                    $messages[] = "⚠️ 没有找到该塔罗师的查看记录";
                }
            } else {
                $messages[] = "❌ reader_view_logs表不存在";
            }

            // 检查readers表的view_count字段
            try {
                $readerData = $db->fetchOne("SELECT id, view_count FROM readers WHERE id = ?", [$testReaderId]);
                if ($readerData) {
                    $messages[] = "✅ 塔罗师 {$testReaderId} 存在，当前view_count: {$readerData['view_count']}";
                } else {
                    $messages[] = "❌ 塔罗师 {$testReaderId} 不存在";
                }
            } catch (Exception $e) {
                $messages[] = "❌ 检查readers表时出错: " . $e->getMessage();
            }

        } catch (Exception $e) {
            $messages[] = "❌ 检查表时出错: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['clear_logs'])) {
        try {
            $db = Database::getInstance();
            $result = $db->query("DELETE FROM reader_view_logs WHERE reader_id = ?", [$testReaderId]);
            $messages[] = "🗑️ 清理了该塔罗师的查看记录";
        } catch (Exception $e) {
            $messages[] = "❌ 清理记录时出错: " . $e->getMessage();
        }
    }
}

// 获取当前状态
$currentViews = $viewCountManager->getViewCount($testReaderId);
$viewStats = $viewCountManager->getViewStats($testReaderId);

// 获取当前IP和Session信息
$currentIP = $viewCountManager->getClientIP();
$currentSession = session_id();
$currentUser = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看次数防刷测试</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .section h3 {
            margin-top: 0;
            color: #333;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .messages {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .messages p {
            margin: 5px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 14px;
        }
        
        .info-value {
            font-size: 18px;
            color: #333;
            margin-top: 5px;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 查看次数防刷机制测试</h1>
        
        <div class="warning">
            <strong>⚠️ 注意：</strong>这是测试页面，请在测试完成后删除此文件。
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages">
                <?php foreach ($messages as $message): ?>
                    <p><?php echo htmlspecialchars($message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- 当前状态 -->
        <div class="section">
            <h3>📊 当前状态</h3>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">测试塔罗师ID</div>
                    <div class="info-value"><?php echo $testReaderId; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">当前查看次数</div>
                    <div class="info-value"><?php echo $currentViews; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">今日查看</div>
                    <div class="info-value"><?php echo $viewStats['today_views']; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">独立访客</div>
                    <div class="info-value"><?php echo $viewStats['unique_visitors']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- 访客信息 -->
        <div class="section">
            <h3>🌐 当前访客信息</h3>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">IP地址</div>
                    <div class="info-value"><?php echo htmlspecialchars($currentIP); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Session ID</div>
                    <div class="info-value"><?php echo htmlspecialchars(substr($currentSession, 0, 10)) . '...'; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">用户ID</div>
                    <div class="info-value"><?php echo $currentUser ? $currentUser : '未登录'; ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">User-Agent</div>
                    <div class="info-value" style="font-size: 12px; word-break: break-all;">
                        <?php echo htmlspecialchars(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 50)) . '...'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 测试操作 -->
        <div class="section">
            <h3>🧪 测试操作</h3>
            <form method="POST" style="display: inline;">
                <button type="submit" name="test_view" class="btn">
                    📈 模拟查看页面
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_table" class="btn">
                    🔍 检查数据库表
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear_logs" class="btn btn-danger" 
                        onclick="return confirm('确定要清理测试记录吗？')">
                    🗑️ 清理测试记录
                </button>
            </form>
        </div>
        
        <!-- 测试说明 -->
        <div class="section">
            <h3>📝 测试说明</h3>
            <ol>
                <li><strong>点击"模拟查看页面"</strong> - 第一次应该成功，后续30分钟内应该失败</li>
                <li><strong>检查数据库表</strong> - 查看是否正确创建了记录</li>
                <li><strong>多次点击测试</strong> - 验证防刷机制是否生效</li>
                <li><strong>等待30分钟后再测试</strong> - 验证冷却时间是否正确</li>
            </ol>
            
            <h4>预期行为：</h4>
            <ul>
                <li>✅ 第一次访问：查看次数+1，记录成功</li>
                <li>❌ 30分钟内再次访问：查看次数不变，记录失败</li>
                <li>✅ 30分钟后访问：查看次数+1，记录成功</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="admin/dashboard.php" class="btn">返回管理后台</a>
            <a href="index.php" class="btn">返回首页</a>
        </div>
    </div>
</body>
</html>
