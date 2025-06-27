<?php
session_start();
require_once 'config/config.php';

// 模拟登录状态（仅用于测试）
if (!isset($_SESSION['reader_id'])) {
    $_SESSION['reader_id'] = 1; // 假设存在ID为1的占卜师
    $_SESSION['user_type'] = 'reader';
    $_SESSION['user_id'] = 1;
}

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>签到功能测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; border: none; cursor: pointer; }
        .btn:hover { background: #005a8b; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 签到功能测试</h1>";

echo "<div class='info'>
    <strong>当前会话信息：</strong><br>
    用户ID: " . ($_SESSION['user_id'] ?? 'null') . "<br>
    用户类型: " . ($_SESSION['user_type'] ?? 'null') . "<br>
    占卜师ID: " . ($_SESSION['reader_id'] ?? 'null') . "
</div>";

// 测试数据库连接
try {
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 检查表是否存在
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'daily_checkins'");
    if ($tableExists) {
        echo "<div class='success'>✅ daily_checkins 表存在</div>";
        
        // 显示表结构
        $columns = $db->fetchAll("SHOW COLUMNS FROM daily_checkins");
        echo "<h3>表结构：</h3><pre>";
        foreach ($columns as $column) {
            echo $column['Field'] . " - " . $column['Type'] . "\n";
        }
        echo "</pre>";
        
    } else {
        echo "<div class='error'>❌ daily_checkins 表不存在</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 数据库连接失败：" . $e->getMessage() . "</div>";
}

// 测试签到API
if (isset($_POST['test_checkin'])) {
    echo "<h3>测试签到API</h3>";
    
    $userId = $_SESSION['user_id'];
    $userType = $_SESSION['user_type'];
    
    try {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        echo "<div class='info'>
            <strong>测试参数：</strong><br>
            用户ID: $userId<br>
            用户类型: $userType<br>
            今日日期: $today<br>
            IP地址: $ipAddress
        </div>";
        
        // 检查今天是否已签到
        if ($userType === 'user') {
            $existingCheckin = $db->fetchOne(
                "SELECT id FROM daily_checkins WHERE user_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        } else {
            $existingCheckin = $db->fetchOne(
                "SELECT id FROM daily_checkins WHERE reader_id = ? AND user_type = ? AND checkin_date = ?",
                [$userId, $userType, $today]
            );
        }
        
        if ($existingCheckin) {
            echo "<div class='error'>今日已签到，记录ID: " . $existingCheckin['id'] . "</div>";
        } else {
            // 尝试插入签到记录
            if ($userType === 'user') {
                $insertData = [
                    'user_id' => $userId,
                    'reader_id' => null,
                    'user_type' => $userType,
                    'checkin_date' => $today,
                    'consecutive_days' => 1,
                    'reward_amount' => 5,
                    'ip_address' => $ipAddress
                ];
            } else {
                $insertData = [
                    'user_id' => null,
                    'reader_id' => $userId,
                    'user_type' => $userType,
                    'checkin_date' => $today,
                    'consecutive_days' => 1,
                    'reward_amount' => 5,
                    'ip_address' => $ipAddress
                ];
            }
            
            echo "<div class='info'><strong>准备插入的数据：</strong><pre>" . print_r($insertData, true) . "</pre></div>";
            
            $db->insert('daily_checkins', $insertData);
            echo "<div class='success'>✅ 签到成功！</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ 签到失败：" . $e->getMessage() . "</div>";
    }
}

// 显示最近的签到记录
try {
    $db = Database::getInstance();
    $records = $db->fetchAll(
        "SELECT * FROM daily_checkins ORDER BY created_at DESC LIMIT 5"
    );
    
    if (!empty($records)) {
        echo "<h3>最近的签到记录：</h3>";
        echo "<pre>";
        foreach ($records as $record) {
            echo "ID: {$record['id']}, 用户: {$record['user_id']}/{$record['reader_id']}, 类型: {$record['user_type']}, 日期: {$record['checkin_date']}, 时间: {$record['created_at']}\n";
        }
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "<div class='error'>获取记录失败：" . $e->getMessage() . "</div>";
}

echo "
        <hr>
        <form method='post'>
            <button type='submit' name='test_checkin' class='btn'>测试签到功能</button>
        </form>
        
        <p><a href='index.php' class='btn'>返回首页</a></p>
    </div>
</body>
</html>";
?>
