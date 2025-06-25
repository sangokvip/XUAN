<?php
/**
 * 简化的防刷机制调试页面
 */
session_start();
require_once 'config/config.php';

$testReaderId = 1; // 测试用的塔罗师ID
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        if (isset($_POST['create_table'])) {
            // 创建表
            $sql = "CREATE TABLE IF NOT EXISTS reader_view_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reader_id INT NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                session_id VARCHAR(100) DEFAULT NULL,
                user_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reader_ip (reader_id, ip_address),
                INDEX idx_created_at (created_at),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->query($sql);
            $messages[] = "✅ 表创建成功";
            
            // 检查readers表是否有view_count字段
            try {
                $db->fetchOne("SELECT view_count FROM readers LIMIT 1");
                $messages[] = "✅ readers表已有view_count字段";
            } catch (Exception $e) {
                $db->query("ALTER TABLE readers ADD COLUMN view_count INT DEFAULT 0");
                $messages[] = "✅ 为readers表添加view_count字段";
            }
        }
        
        if (isset($_POST['test_simple']) || isset($_POST['test_short'])) {
            $cooldownMinutes = isset($_POST['test_short']) ? 0.5 : 30; // 30秒或30分钟
            // 简单的防刷测试
            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionId = session_id();
            $userId = $_SESSION['user_id'] ?? null;

            $messages[] = "🔍 当前信息：";
            $messages[] = "  IP: $ip";
            $messages[] = "  Session: " . substr($sessionId, 0, 10) . "...";
            $messages[] = "  User: " . ($userId ? $userId : '未登录');
            $messages[] = "  冷却时间: {$cooldownMinutes} 分钟";

            // 使用数据库时间检查冷却时间内是否有记录
            $sql = "SELECT COUNT(*) as count FROM reader_view_logs
                    WHERE reader_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)";
            $params = [$testReaderId, $ip, $cooldownMinutes];

            $messages[] = "🔍 执行查询: $sql";
            $messages[] = "🔍 查询参数: " . json_encode($params);

            $existing = $db->fetchOne($sql, $params);
            $existingCount = $existing['count'] ?? 0;
            $messages[] = "⏰ 查询结果: {$cooldownMinutes}分钟内已有 $existingCount 条记录";

            // 显示最近的记录用于调试
            $recentRecords = $db->fetchAll(
                "SELECT id, ip_address, created_at FROM reader_view_logs
                 WHERE reader_id = ? AND ip_address = ?
                 ORDER BY created_at DESC LIMIT 3",
                [$testReaderId, $ip]
            );

            if (!empty($recentRecords)) {
                $messages[] = "📝 该IP最近的记录:";
                foreach ($recentRecords as $record) {
                    $timeDiff = time() - strtotime($record['created_at']);
                    $messages[] = "  - ID: {$record['id']}, 时间: {$record['created_at']} (距现在 {$timeDiff} 秒)";
                }
            }

            if ($existingCount > 0) {
                $messages[] = "❌ 在冷却期内，不记录查看";
            } else {
                // 记录新的查看
                $insertId = $db->insert('reader_view_logs', [
                    'reader_id' => $testReaderId,
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'session_id' => $sessionId,
                    'user_id' => $userId
                ]);

                $messages[] = "✅ 记录插入成功，ID: $insertId";

                // 更新查看次数
                $db->query(
                    "UPDATE readers SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?",
                    [$testReaderId]
                );

                $messages[] = "✅ 查看次数更新成功";
            }
        }
        
        if (isset($_POST['check_data'])) {
            // 检查数据
            $readerData = $db->fetchOne("SELECT view_count FROM readers WHERE id = ?", [$testReaderId]);
            $messages[] = "📊 当前查看次数: " . ($readerData['view_count'] ?? 0);
            
            $logs = $db->fetchAll(
                "SELECT * FROM reader_view_logs WHERE reader_id = ? ORDER BY created_at DESC LIMIT 10",
                [$testReaderId]
            );
            
            $messages[] = "📝 查看记录 (最近10条):";
            foreach ($logs as $log) {
                $messages[] = "  - {$log['created_at']}: IP {$log['ip_address']}";
            }
        }
        
        if (isset($_POST['clear_data'])) {
            $db->query("DELETE FROM reader_view_logs WHERE reader_id = ?", [$testReaderId]);
            $db->query("UPDATE readers SET view_count = 0 WHERE id = ?", [$testReaderId]);
            $messages[] = "🗑️ 数据清理完成";
        }

        if (isset($_POST['debug_sql'])) {
            // 调试SQL查询
            $ip = $_SERVER['REMOTE_ADDR'];
            $cooldownTime = date('Y-m-d H:i:s', time() - (30 * 60));

            $messages[] = "🔍 SQL调试信息：";
            $messages[] = "  当前IP: $ip";
            $messages[] = "  PHP当前时间: " . date('Y-m-d H:i:s');
            $messages[] = "  PHP冷却时间点: $cooldownTime";

            // 获取数据库当前时间
            $dbTime = $db->fetchOne("SELECT NOW() as db_time")['db_time'];
            $messages[] = "  数据库当前时间: $dbTime";

            // 计算数据库的冷却时间点
            $dbCooldownTime = $db->fetchOne("SELECT DATE_SUB(NOW(), INTERVAL 30 MINUTE) as cooldown_time")['cooldown_time'];
            $messages[] = "  数据库冷却时间点: $dbCooldownTime";

            // 执行查询并显示详细结果
            $allRecords = $db->fetchAll(
                "SELECT id, ip_address, created_at
                 FROM reader_view_logs
                 WHERE reader_id = ?
                 ORDER BY created_at DESC LIMIT 5",
                [$testReaderId]
            );

            $messages[] = "📝 最近5条记录详情：";
            foreach ($allRecords as $record) {
                $isMatch = ($record['ip_address'] === $ip) ? '✅' : '❌';
                // 计算时间差
                $recordTime = strtotime($record['created_at']);
                $currentTime = time();
                $secondsAgo = $currentTime - $recordTime;
                $isRecent = ($secondsAgo < 1800) ? '🔥' : '❄️'; // 30分钟 = 1800秒
                $messages[] = "  {$isMatch}{$isRecent} ID:{$record['id']}, IP:{$record['ip_address']}, 时间:{$record['created_at']}, 距今:{$secondsAgo}秒";
            }

            // 使用PHP时间的查询（有问题的方式）
            $testQuery1 = $db->fetchOne(
                "SELECT COUNT(*) as count FROM reader_view_logs
                 WHERE reader_id = ? AND ip_address = ? AND created_at > ?",
                [$testReaderId, $ip, $cooldownTime]
            );

            // 使用数据库时间的查询（正确的方式）
            $testQuery2 = $db->fetchOne(
                "SELECT COUNT(*) as count FROM reader_view_logs
                 WHERE reader_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
                [$testReaderId, $ip]
            );

            $messages[] = "🎯 使用PHP时间的冷却检查: " . ($testQuery1['count'] ?? 0) . " 条匹配记录";
            $messages[] = "🎯 使用数据库时间的冷却检查: " . ($testQuery2['count'] ?? 0) . " 条匹配记录";
        }
        
    } catch (Exception $e) {
        $messages[] = "❌ 错误: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>防刷机制调试</title>
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
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .messages {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            white-space: pre-line;
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
        <h1>🔧 防刷机制调试工具</h1>
        
        <div class="warning">
            <strong>⚠️ 调试工具</strong><br>
            这是一个简化的调试工具，用于测试防刷机制的核心逻辑。<br>
            测试塔罗师ID: <?php echo $testReaderId; ?>
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages"><?php echo implode("\n", $messages); ?></div>
        <?php endif; ?>
        
        <div style="margin: 20px 0;">
            <form method="POST" style="display: inline;">
                <button type="submit" name="create_table" class="btn">
                    🏗️ 创建表结构
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="test_simple" class="btn">
                    🧪 测试防刷机制 (30分钟)
                </button>
            </form>

            <form method="POST" style="display: inline;">
                <button type="submit" name="test_short" class="btn">
                    ⚡ 测试防刷机制 (30秒)
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check_data" class="btn">
                    📊 检查数据
                </button>
            </form>

            <form method="POST" style="display: inline;">
                <button type="submit" name="debug_sql" class="btn">
                    🔍 SQL调试
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear_data" class="btn btn-danger" 
                      onclick="return confirm('确定清理数据吗？')">
                    🗑️ 清理数据
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>📋 测试步骤：</h3>
            <ol>
                <li>点击"创建表结构"确保数据库表存在</li>
                <li>点击"测试防刷机制"第一次应该成功</li>
                <li>再次点击"测试防刷机制"应该失败（冷却期内）</li>
                <li>点击"检查数据"查看记录</li>
                <li>等待30分钟后再测试，或者清理数据重新测试</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="test_view_count.php" class="btn">返回完整测试页面</a>
            <a href="admin/dashboard.php" class="btn">管理后台</a>
        </div>
    </div>
</body>
</html>
