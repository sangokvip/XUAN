<?php
/**
 * 测试清除登录尝试功能
 */

session_start();
require_once 'config/config.php';

echo "<h1>测试清除登录尝试功能</h1>";

try {
    $db = Database::getInstance();
    
    // 测试查询方法
    echo "<h2>1. 测试数据库连接</h2>";
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM login_attempts");
    echo "<p>✓ 数据库连接成功</p>";
    echo "<p>当前登录尝试记录数: " . ($result['count'] ?? 0) . "</p>";
    
    // 测试插入一条测试记录
    echo "<h2>2. 测试插入记录</h2>";
    $testData = [
        'username' => 'test_user_' . time(),
        'success' => 0,
        'ip_address' => '127.0.0.1',
        'attempted_at' => date('Y-m-d H:i:s')
    ];
    
    $insertId = $db->insert('login_attempts', $testData);
    echo "<p>✓ 插入测试记录成功，ID: {$insertId}</p>";
    
    // 测试查询方法
    echo "<h2>3. 测试查询方法</h2>";
    $stmt = $db->query("SELECT * FROM login_attempts WHERE id = ?", [$insertId]);
    $record = $stmt->fetch();
    echo "<p>✓ 查询方法正常，记录: " . json_encode($record) . "</p>";
    
    // 测试删除方法
    echo "<h2>4. 测试删除方法</h2>";
    $deleteStmt = $db->query("DELETE FROM login_attempts WHERE id = ?", [$insertId]);
    echo "<p>✓ 删除方法正常，影响行数: " . $deleteStmt->rowCount() . "</p>";
    
    echo "<h2>5. 测试结果</h2>";
    echo "<p style='color: green;'>✓ 所有测试通过！clear_login_attempts.php 应该可以正常工作了。</p>";
    echo "<p><a href='clear_login_attempts.php'>点击这里访问清除工具</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 测试失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><strong>注意：</strong>测试完成后请删除此文件 (test_clear_login.php)</p>";
?>
