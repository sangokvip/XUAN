<?php
/**
 * 最简单的防刷测试
 */
session_start();
require_once 'config/config.php';

$testReaderId = 1;
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (isset($_POST['test'])) {
            $messages[] = "🔍 开始测试...";
            $messages[] = "当前IP: $ip";
            
            // 检查30秒内是否有记录
            $recentCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM reader_view_logs 
                 WHERE reader_id = ? AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)",
                [$testReaderId, $ip]
            )['count'] ?? 0;
            
            $messages[] = "30秒内已有记录: $recentCount 条";
            
            if ($recentCount > 0) {
                $messages[] = "❌ 在冷却期内，不记录";
            } else {
                // 插入新记录
                $insertId = $db->insert('reader_view_logs', [
                    'reader_id' => $testReaderId,
                    'ip_address' => $ip,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'session_id' => session_id(),
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
                
                // 更新查看次数
                $db->query(
                    "UPDATE readers SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?",
                    [$testReaderId]
                );
                
                $messages[] = "✅ 记录成功，插入ID: $insertId";
            }
        }
        
        if (isset($_POST['check'])) {
            // 检查当前状态
            $readerData = $db->fetchOne("SELECT view_count FROM readers WHERE id = ?", [$testReaderId]);
            $messages[] = "当前查看次数: " . ($readerData['view_count'] ?? 0);
            
            $logs = $db->fetchAll(
                "SELECT id, ip_address, created_at FROM reader_view_logs 
                 WHERE reader_id = ? ORDER BY created_at DESC LIMIT 5",
                [$testReaderId]
            );
            
            $messages[] = "最近5条记录:";
            foreach ($logs as $log) {
                $messages[] = "  ID:{$log['id']}, IP:{$log['ip_address']}, 时间:{$log['created_at']}";
            }
        }
        
        if (isset($_POST['clear'])) {
            $db->query("DELETE FROM reader_view_logs WHERE reader_id = ?", [$testReaderId]);
            $db->query("UPDATE readers SET view_count = 0 WHERE id = ?", [$testReaderId]);
            $messages[] = "数据已清理";
        }
        
    } catch (Exception $e) {
        $messages[] = "错误: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>简单防刷测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
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
        
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 简单防刷测试</h1>
        
        <div class="info">
            <strong>测试说明：</strong><br>
            - 使用30秒冷却时间（便于快速测试）<br>
            - 测试塔罗师ID: <?php echo $testReaderId; ?><br>
            - 当前IP: <?php echo $_SERVER['REMOTE_ADDR']; ?>
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages"><?php echo implode("\n", $messages); ?></div>
        <?php endif; ?>
        
        <div>
            <form method="POST" style="display: inline;">
                <button type="submit" name="test" class="btn">
                    🧪 测试防刷 (30秒冷却)
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="check" class="btn">
                    📊 检查状态
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <button type="submit" name="clear" class="btn btn-danger" 
                      onclick="return confirm('确定清理数据吗？')">
                    🗑️ 清理数据
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <h3>测试步骤：</h3>
            <ol>
                <li>点击"测试防刷"按钮 - 第一次应该成功</li>
                <li>立即再次点击"测试防刷"按钮 - 应该显示"在冷却期内"</li>
                <li>等待30秒后再次点击 - 应该又能成功</li>
                <li>点击"检查状态"查看记录</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="debug_view_count.php" class="btn">返回详细调试</a>
        </div>
    </div>
</body>
</html>
