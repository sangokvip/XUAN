<?php
/**
 * 修复签到功能数据库表结构
 * 添加缺失的 ip_address 字段
 */

require_once 'config/config.php';

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>修复签到数据库</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; }
        .btn:hover { background: #005a8b; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 修复签到功能数据库</h1>";

try {
    $db = Database::getInstance();
    
    echo "<div class='info'>开始检查和修复数据库表结构...</div>";
    
    // 1. 检查 daily_checkins 表是否存在
    echo "<h3>1. 检查 daily_checkins 表</h3>";
    
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'daily_checkins'");
    
    if (!$tableExists) {
        echo "<div class='warning'>daily_checkins 表不存在，正在创建...</div>";
        
        $createTableSQL = "CREATE TABLE daily_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL COMMENT '用户ID',
            reader_id INT DEFAULT NULL COMMENT '占卜师ID',
            user_type ENUM('user', 'reader') NOT NULL COMMENT '用户类型',
            checkin_date DATE NOT NULL COMMENT '签到日期',
            consecutive_days INT DEFAULT 1 COMMENT '连续签到天数',
            reward_amount INT DEFAULT 0 COMMENT '奖励金额',
            ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_checkin (user_id, reader_id, user_type, checkin_date),
            INDEX idx_user_type_date (user_type, checkin_date),
            INDEX idx_user_id (user_id),
            INDEX idx_reader_id (reader_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='每日签到记录表'";
        
        $db->query($createTableSQL);
        echo "<div class='success'>✅ daily_checkins 表创建成功！</div>";
    } else {
        echo "<div class='success'>✅ daily_checkins 表已存在</div>";
        
        // 2. 检查 ip_address 字段是否存在
        echo "<h3>2. 检查 ip_address 字段</h3>";
        
        $columns = $db->fetchAll("SHOW COLUMNS FROM daily_checkins");
        $hasIpAddress = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'ip_address') {
                $hasIpAddress = true;
                break;
            }
        }
        
        if (!$hasIpAddress) {
            echo "<div class='warning'>ip_address 字段不存在，正在添加...</div>";
            
            $addColumnSQL = "ALTER TABLE daily_checkins ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址' AFTER reward_amount";
            $db->query($addColumnSQL);
            
            echo "<div class='success'>✅ ip_address 字段添加成功！</div>";
        } else {
            echo "<div class='success'>✅ ip_address 字段已存在</div>";
        }
    }
    
    // 3. 显示当前表结构
    echo "<h3>3. 当前表结构</h3>";
    $columns = $db->fetchAll("SHOW COLUMNS FROM daily_checkins");
    
    echo "<pre>";
    echo "字段名\t\t类型\t\t\t空值\t键\t默认值\t\t备注\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($columns as $column) {
        printf("%-15s %-20s %-8s %-8s %-15s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'] ?? 'NULL',
            $column['Extra']
        );
    }
    echo "</pre>";
    
    // 4. 测试签到功能
    echo "<h3>4. 测试签到功能</h3>";
    
    if (isset($_GET['test_checkin'])) {
        echo "<div class='info'>正在测试签到功能...</div>";
        
        // 模拟一个测试签到
        $testData = [
            'user_id' => null,
            'reader_id' => 1, // 假设存在ID为1的占卜师
            'user_type' => 'reader',
            'checkin_date' => date('Y-m-d'),
            'consecutive_days' => 1,
            'reward_amount' => 5,
            'ip_address' => '127.0.0.1'
        ];
        
        try {
            // 先删除今天的测试记录（如果存在）
            $db->query("DELETE FROM daily_checkins WHERE reader_id = 1 AND user_type = 'reader' AND checkin_date = ?", [date('Y-m-d')]);
            
            // 插入测试记录
            $db->insert('daily_checkins', $testData);
            
            echo "<div class='success'>✅ 签到功能测试成功！数据库可以正常插入记录。</div>";
            
            // 清理测试数据
            $db->query("DELETE FROM daily_checkins WHERE reader_id = 1 AND user_type = 'reader' AND checkin_date = ?", [date('Y-m-d')]);
            echo "<div class='info'>测试数据已清理</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ 签到功能测试失败：" . $e->getMessage() . "</div>";
        }
    } else {
        echo "<a href='?test_checkin=1' class='btn'>测试签到功能</a>";
    }
    
    echo "<div class='success'><strong>🎉 数据库修复完成！</strong></div>";
    echo "<div class='info'>现在可以正常使用每日签到功能了。</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 修复过程中出现错误：" . $e->getMessage() . "</div>";
}

echo "
        <hr>
        <p><a href='index.php' class='btn'>返回首页</a></p>
    </div>
</body>
</html>";
?>
