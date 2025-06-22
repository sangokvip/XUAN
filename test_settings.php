<?php
require_once 'config/config.php';

echo "<h1>设置功能测试</h1>";

try {
    $db = Database::getInstance();
    echo "<p>✓ 数据库连接成功</p>";
    
    echo "<h2>1. 当前设置</h2>";
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    if (!empty($settings)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th style='padding: 8px; background: #f0f0f0;'>设置键</th><th style='padding: 8px; background: #f0f0f0;'>设置值</th></tr>";
        foreach ($settings as $setting) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($setting['setting_key']) . "</td>";
            echo "<td style='padding: 8px;'>" . htmlspecialchars($setting['setting_value']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>没有找到设置记录</p>";
    }
    
    echo "<h2>2. 测试setSetting函数</h2>";
    
    // 测试更新现有设置
    $testKey = 'site_name';
    $oldValue = getSetting($testKey, '默认值');
    echo "<p>当前 {$testKey}: " . htmlspecialchars($oldValue) . "</p>";
    
    $newValue = '塔罗师展示平台 - 测试更新 ' . date('H:i:s');
    echo "<p>尝试更新为: " . htmlspecialchars($newValue) . "</p>";
    
    $result = setSetting($testKey, $newValue);
    echo "<p>setSetting 返回值: " . ($result ? 'true' : 'false') . "</p>";
    
    $updatedValue = getSetting($testKey);
    echo "<p>更新后的值: " . htmlspecialchars($updatedValue) . "</p>";
    
    if ($updatedValue === $newValue) {
        echo "<p style='color: green;'>✓ 设置更新成功！</p>";
    } else {
        echo "<p style='color: red;'>✗ 设置更新失败</p>";
    }
    
    // 恢复原值
    setSetting($testKey, $oldValue);
    echo "<p>已恢复原值: " . htmlspecialchars(getSetting($testKey)) . "</p>";
    
    echo "<h2>3. 测试新设置创建</h2>";
    
    $testNewKey = 'test_setting_' . time();
    $testNewValue = '测试新设置值';
    
    echo "<p>创建新设置: {$testNewKey} = {$testNewValue}</p>";
    $result = setSetting($testNewKey, $testNewValue);
    echo "<p>setSetting 返回值: " . ($result ? 'true' : 'false') . "</p>";
    
    $retrievedValue = getSetting($testNewKey);
    echo "<p>获取到的值: " . htmlspecialchars($retrievedValue) . "</p>";
    
    if ($retrievedValue === $testNewValue) {
        echo "<p style='color: green;'>✓ 新设置创建成功！</p>";
    } else {
        echo "<p style='color: red;'>✗ 新设置创建失败</p>";
    }
    
    // 清理测试设置
    $db->delete('settings', 'setting_key = ?', [$testNewKey]);
    echo "<p>已清理测试设置</p>";
    
    echo "<h2>4. 数据库操作测试</h2>";
    
    // 测试Database类的update方法
    $testResult = $db->update('settings', ['setting_value' => $oldValue], 'setting_key = ?', [$testKey]);
    echo "<p>Database::update 返回类型: " . gettype($testResult) . "</p>";
    echo "<p>Database::update 返回值: " . get_class($testResult) . "</p>";
    
    if ($testResult instanceof PDOStatement) {
        echo "<p>rowCount(): " . $testResult->rowCount() . "</p>";
        echo "<p>rowCount() > 0: " . ($testResult->rowCount() > 0 ? 'true' : 'false') . "</p>";
    }
    
    echo "<h2>5. 管理员后台设置页面测试</h2>";
    echo "<p><a href='admin/settings.php' target='_blank' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>测试管理员设置页面</a></p>";
    echo "<p>请在新窗口中测试设置更新功能</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>错误: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>错误详情: " . htmlspecialchars($e->getTraceAsString()) . "</p>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 1000px; 
    margin: 0 auto; 
    padding: 20px; 
    line-height: 1.6;
}
h1, h2 { color: #333; }
table { width: 100%; }
th, td { text-align: left; }
</style>
