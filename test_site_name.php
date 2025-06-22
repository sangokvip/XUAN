<?php
require_once 'config/config.php';

echo "<h1>网站名称动态读取测试</h1>";

try {
    $db = Database::getInstance();
    echo "<p>✓ 数据库连接成功</p>";
    
    echo "<h2>1. 当前设置状态</h2>";
    
    // 检查数据库中的site_name设置
    $siteNameFromDB = getSetting('site_name');
    echo "<p>数据库中的site_name: " . htmlspecialchars($siteNameFromDB ?: '(未设置)') . "</p>";
    
    // 检查默认值
    $defaultName = defined('SITE_NAME_DEFAULT') ? SITE_NAME_DEFAULT : '未定义';
    echo "<p>默认值 SITE_NAME_DEFAULT: " . htmlspecialchars($defaultName) . "</p>";
    
    // 测试getSiteName函数
    $dynamicName = getSiteName();
    echo "<p>getSiteName() 返回: " . htmlspecialchars($dynamicName) . "</p>";
    
    echo "<h2>2. 测试设置更新</h2>";
    
    // 保存当前值
    $originalName = $siteNameFromDB;
    
    // 设置一个测试值
    $testName = '测试网站名称 - ' . date('H:i:s');
    echo "<p>设置测试名称: " . htmlspecialchars($testName) . "</p>";
    
    $result = setSetting('site_name', $testName);
    echo "<p>setSetting 结果: " . ($result ? '成功' : '失败') . "</p>";
    
    // 清除静态缓存（重新获取）
    $newName = getSetting('site_name');
    echo "<p>更新后从数据库读取: " . htmlspecialchars($newName) . "</p>";
    
    // 测试getSiteName函数（注意：由于静态缓存，可能需要刷新页面才能看到变化）
    echo "<p><strong>注意：</strong>getSiteName() 使用了静态缓存，需要刷新页面才能看到变化</p>";
    
    echo "<h2>3. 恢复原值</h2>";
    
    // 恢复原值
    if ($originalName) {
        setSetting('site_name', $originalName);
        echo "<p>已恢复原值: " . htmlspecialchars($originalName) . "</p>";
    } else {
        // 如果原来没有设置，插入默认值
        setSetting('site_name', $defaultName);
        echo "<p>设置为默认值: " . htmlspecialchars($defaultName) . "</p>";
    }
    
    echo "<h2>4. 页面测试</h2>";
    echo "<p>请测试以下页面，查看网站名称是否正确显示：</p>";
    echo "<ul>";
    echo "<li><a href='index.php' target='_blank'>首页</a> - 检查标题和header</li>";
    echo "<li><a href='readers.php' target='_blank'>占卜师页面</a> - 检查header</li>";
    echo "<li><a href='admin/dashboard.php' target='_blank'>管理后台</a> - 检查标题和header</li>";
    echo "</ul>";
    
    echo "<h2>5. 管理后台设置测试</h2>";
    echo "<p><a href='admin/settings.php' target='_blank' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>测试管理员设置页面</a></p>";
    echo "<p>在管理员设置页面修改网站名称，然后回到首页查看是否生效</p>";
    
    echo "<h2>6. 数据库设置表内容</h2>";
    $settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE '%site%' ORDER BY setting_key");
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
        echo "<p>没有找到网站相关设置</p>";
    }
    
    echo "<h2>7. 解决方案说明</h2>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>✅ 已完成的修改：</h3>";
    echo "<ul>";
    echo "<li>将硬编码的 SITE_NAME 常量改为 SITE_NAME_DEFAULT</li>";
    echo "<li>添加了 getSiteName() 函数，从数据库动态读取网站名称</li>";
    echo "<li>修改了所有使用 SITE_NAME 的地方，改为使用 getSiteName()</li>";
    echo "<li>包括：header.php, footer.php, 各个页面的title等</li>";
    echo "</ul>";
    echo "</div>";
    
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
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>
