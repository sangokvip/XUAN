<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$success = [];
$errors = [];
$upgradeCompleted = false;

// 处理升级请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_upgrade'])) {
    try {
        $db = Database::getInstance();
        
        // 1. 创建查看记录表
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='塔罗师页面查看记录表'";
        
        $db->query($sql);
        $success[] = "✓ 创建reader_view_logs表成功";
        
        // 2. 检查readers表是否有view_count字段
        try {
            $db->fetchOne("SELECT view_count FROM readers LIMIT 1");
            $success[] = "✓ readers表已有view_count字段";
        } catch (Exception $e) {
            // 添加view_count字段
            $db->query("ALTER TABLE readers ADD COLUMN view_count INT DEFAULT 0 COMMENT '页面查看次数'");
            $success[] = "✓ 为readers表添加view_count字段";
        }
        
        // 3. 为现有塔罗师初始化查看次数（如果为NULL）
        $db->query("UPDATE readers SET view_count = 0 WHERE view_count IS NULL");
        $success[] = "✓ 初始化现有塔罗师的查看次数";
        
        // 4. 检查是否有旧的contact_views表数据可以迁移
        try {
            $oldViews = $db->fetchAll(
                "SELECT reader_id, COUNT(*) as count 
                 FROM contact_views 
                 GROUP BY reader_id"
            );
            
            if (!empty($oldViews)) {
                foreach ($oldViews as $view) {
                    $db->query(
                        "UPDATE readers SET view_count = view_count + ? WHERE id = ?",
                        [$view['count'], $view['reader_id']]
                    );
                }
                $success[] = "✓ 从contact_views表迁移了 " . count($oldViews) . " 个塔罗师的查看数据";
            }
        } catch (Exception $e) {
            $success[] = "⚠ 未找到contact_views表，跳过数据迁移";
        }
        
        // 5. 创建清理过期记录的存储过程
        try {
            // 先删除存储过程（如果存在）
            $db->query("DROP PROCEDURE IF EXISTS CleanupViewLogs");

            // 创建新的存储过程
            $cleanupProcedure = "
            CREATE PROCEDURE CleanupViewLogs(IN days_to_keep INT)
            BEGIN
                DELETE FROM reader_view_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);

                SELECT ROW_COUNT() as deleted_rows;
            END";

            $db->query($cleanupProcedure);
            $success[] = "✓ 创建清理存储过程成功";
        } catch (Exception $e) {
            // 存储过程创建失败不影响主要功能
            $success[] = "⚠ 创建存储过程失败，但不影响主要功能: " . $e->getMessage();
        }
        
        // 6. 验证升级结果
        $tableCheck = $db->fetchOne("SHOW TABLES LIKE 'reader_view_logs'");
        if ($tableCheck) {
            $success[] = "✓ 验证：reader_view_logs表存在";
        } else {
            $errors[] = "❌ 验证失败：reader_view_logs表不存在";
        }
        
        $readerCount = $db->fetchOne("SELECT COUNT(*) as count FROM readers WHERE view_count IS NOT NULL")['count'];
        $success[] = "✓ 验证：{$readerCount} 个塔罗师已有查看次数字段";
        
        if (empty($errors)) {
            $upgradeCompleted = true;
        }
        
    } catch (Exception $e) {
        $errors[] = "升级失败：" . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>升级查看次数系统 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .upgrade-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .upgrade-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 20px 0;
        }
        
        .upgrade-btn:hover {
            background: #218838;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
        }
        
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .feature-list li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="upgrade-container">
        <h1>🚀 升级查看次数系统</h1>
        
        <a href="dashboard.php" class="btn-back">← 返回管理后台</a>
        
        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <h4>❌ 升级过程中出现错误：</h4>
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-box">
                <h4>✅ 升级进度：</h4>
                <?php foreach ($success as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($upgradeCompleted): ?>
            <div class="success-box">
                <h3>🎉 升级完成！</h3>
                <p><strong>新功能已启用：</strong></p>
                <ul>
                    <li>✅ 防刷新查看次数系统</li>
                    <li>✅ 详细的访问日志记录</li>
                    <li>✅ 30分钟冷却时间机制</li>
                    <li>✅ 管理员查看次数管理功能</li>
                    <li>✅ 自动清理过期记录</li>
                </ul>
                
                <p><strong>现在可以：</strong></p>
                <ul>
                    <li>1. 访问塔罗师页面测试防刷功能</li>
                    <li>2. 使用管理后台查看统计数据</li>
                    <li>3. 管理和重置查看次数</li>
                </ul>
                
                <p>
                    <a href="view_count_management.php" class="btn-back" style="background: #28a745;">查看次数管理</a>
                    <a href="dashboard.php" class="btn-back">返回仪表板</a>
                </p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h4>⚠️ 发现的问题：</h4>
                <p>当前系统存在查看次数被恶意刷新的问题：</p>
                <ul>
                    <li>每次刷新塔罗师页面都会增加查看次数</li>
                    <li>没有防刷机制，容易被恶意利用</li>
                    <li>缺乏详细的访问记录和统计</li>
                </ul>
            </div>
            
            <div class="feature-list">
                <h4>🛡️ 本次升级将添加以下功能：</h4>
                <ul>
                    <li><strong>防刷机制：</strong>30分钟冷却时间，防止恶意刷新</li>
                    <li><strong>智能检测：</strong>基于IP地址、Session ID、用户ID的多重检测</li>
                    <li><strong>访问日志：</strong>详细记录每次访问的IP、User-Agent、时间等</li>
                    <li><strong>管理功能：</strong>查看统计、重置次数、清理记录等</li>
                    <li><strong>数据迁移：</strong>保留现有的查看次数数据</li>
                    <li><strong>自动清理：</strong>定期清理过期的访问记录</li>
                </ul>
            </div>
            
            <div class="warning-box">
                <h4>🔧 升级内容：</h4>
                <ul>
                    <li>创建reader_view_logs表记录访问日志</li>
                    <li>确保readers表有view_count字段</li>
                    <li>迁移现有的查看数据（如果有）</li>
                    <li>创建清理过期记录的存储过程</li>
                    <li>验证升级完整性</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="confirm_upgrade" class="upgrade-btn" 
                        onclick="return confirm('确定要升级查看次数系统吗？这将创建新的数据库表。')">
                    🚀 开始升级
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
