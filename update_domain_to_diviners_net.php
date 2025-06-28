<?php
/**
 * 域名更新脚本：从 diviners.pro 更新到 diviners.net
 * 全面更新配置文件和数据库中的域名设置
 */

session_start();
require_once 'config/config.php';

// 检查管理员权限
if (!isAdminLoggedIn()) {
    die('需要管理员权限才能执行此操作');
}

$oldDomain = 'diviners.pro';
$newDomain = 'diviners.net';
$newSiteUrl = 'https://' . $newDomain;

try {
    $db = Database::getInstance();
    
    echo "<h2>🔄 域名更新脚本</h2>";
    echo "<p>正在将域名从 <strong>{$oldDomain}</strong> 更新到 <strong>{$newDomain}</strong>...</p>";
    
    // 1. 更新数据库中的网站URL设置
    echo "<h3>1. 更新数据库设置</h3>";
    
    try {
        // 更新site_url设置
        $result = $db->query(
            "UPDATE settings SET setting_value = ? WHERE setting_key = 'site_url'",
            [$newSiteUrl]
        );
        
        if ($result) {
            echo "<p style='color: green;'>✅ 数据库中的site_url已更新为: {$newSiteUrl}</p>";
        } else {
            // 如果更新失败，可能是记录不存在，尝试插入
            $db->query(
                "INSERT INTO settings (setting_key, setting_value, description) VALUES ('site_url', ?, '网站URL')",
                [$newSiteUrl]
            );
            echo "<p style='color: green;'>✅ 已插入新的site_url设置: {$newSiteUrl}</p>";
        }
        
        // 检查其他可能包含旧域名的设置
        $domainSettings = $db->fetchAll(
            "SELECT setting_key, setting_value FROM settings WHERE setting_value LIKE ?",
            ["%{$oldDomain}%"]
        );
        
        if (!empty($domainSettings)) {
            echo "<p style='color: orange;'>⚠️ 发现包含旧域名的其他设置：</p>";
            foreach ($domainSettings as $setting) {
                $newValue = str_replace($oldDomain, $newDomain, $setting['setting_value']);
                $db->query(
                    "UPDATE settings SET setting_value = ? WHERE setting_key = ?",
                    [$newValue, $setting['setting_key']]
                );
                echo "<p style='color: green;'>✅ 已更新 {$setting['setting_key']}: {$newValue}</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 数据库更新失败: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 2. 更新配置文件
    echo "<h3>2. 更新配置文件</h3>";
    
    $configFile = 'config/site_config.php';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        $newConfigContent = str_replace($oldDomain, $newDomain, $configContent);
        
        if (file_put_contents($configFile, $newConfigContent)) {
            echo "<p style='color: green;'>✅ 已更新配置文件: {$configFile}</p>";
        } else {
            echo "<p style='color: red;'>❌ 配置文件更新失败: {$configFile}</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ 配置文件不存在: {$configFile}</p>";
    }
    
    // 3. 检查其他可能包含旧域名的文件
    echo "<h3>3. 检查其他文件</h3>";
    
    $filesToCheck = [
        'update_site_url.php',
        'install_simple.php',
        'README.md',
        'INSTALL.md'
    ];
    
    foreach ($filesToCheck as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, $oldDomain) !== false) {
                echo "<p style='color: orange;'>⚠️ 文件 {$file} 中包含旧域名，建议手动检查</p>";
            } else {
                echo "<p style='color: green;'>✅ 文件 {$file} 无需更新</p>";
            }
        }
    }
    
    // 4. 检查数据库中的其他表
    echo "<h3>4. 检查数据库中的其他数据</h3>";
    
    $tablesToCheck = [
        'reader_registration_links' => ['token'],
        'invitation_links' => ['link_url'],
        'admin_messages' => ['content'],
        'contact_messages' => ['message']
    ];
    
    foreach ($tablesToCheck as $table => $columns) {
        try {
            // 检查表是否存在
            $tableExists = $db->fetchOne("SHOW TABLES LIKE '{$table}'");
            if (!$tableExists) {
                echo "<p style='color: blue;'>ℹ️ 表 {$table} 不存在，跳过检查</p>";
                continue;
            }
            
            foreach ($columns as $column) {
                $records = $db->fetchAll(
                    "SELECT id, {$column} FROM {$table} WHERE {$column} LIKE ?",
                    ["%{$oldDomain}%"]
                );
                
                if (!empty($records)) {
                    echo "<p style='color: orange;'>⚠️ 表 {$table} 的 {$column} 字段中发现 " . count($records) . " 条包含旧域名的记录</p>";
                    
                    foreach ($records as $record) {
                        $newValue = str_replace($oldDomain, $newDomain, $record[$column]);
                        $db->query(
                            "UPDATE {$table} SET {$column} = ? WHERE id = ?",
                            [$newValue, $record['id']]
                        );
                    }
                    echo "<p style='color: green;'>✅ 已更新表 {$table} 中的 " . count($records) . " 条记录</p>";
                } else {
                    echo "<p style='color: green;'>✅ 表 {$table} 的 {$column} 字段无需更新</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 检查表 {$table} 失败: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    // 5. 清理缓存
    echo "<h3>5. 清理缓存</h3>";
    
    // 清理可能的缓存文件
    $cacheFiles = [
        'cache/site_settings.cache',
        'cache/config.cache',
        'tmp/site_url.cache'
    ];
    
    foreach ($cacheFiles as $cacheFile) {
        if (file_exists($cacheFile)) {
            if (unlink($cacheFile)) {
                echo "<p style='color: green;'>✅ 已清理缓存文件: {$cacheFile}</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ 清理缓存文件失败: {$cacheFile}</p>";
            }
        }
    }
    
    // 6. 验证更新结果
    echo "<h3>6. 验证更新结果</h3>";
    
    // 检查当前的SITE_URL常量
    echo "<p>当前SITE_URL常量: <strong>" . (defined('SITE_URL') ? SITE_URL : '未定义') . "</strong></p>";
    
    // 检查数据库中的设置
    $currentSiteUrl = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
    if ($currentSiteUrl) {
        echo "<p>数据库中的site_url: <strong>{$currentSiteUrl['setting_value']}</strong></p>";
    }
    
    // 检查getSetting函数
    if (function_exists('getSetting')) {
        $settingSiteUrl = getSetting('site_url', 'N/A');
        echo "<p>getSetting('site_url'): <strong>{$settingSiteUrl}</strong></p>";
    }
    
    echo "<h3>🎉 域名更新完成！</h3>";
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #c3e6cb;'>";
    echo "<h4 style='color: #155724; margin: 0 0 10px 0;'>✅ 更新总结：</h4>";
    echo "<ul style='color: #155724; margin: 0;'>";
    echo "<li>配置文件已更新为新域名</li>";
    echo "<li>数据库设置已更新为新域名</li>";
    echo "<li>相关数据记录已更新</li>";
    echo "<li>缓存文件已清理</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border: 1px solid #ffeaa7;'>";
    echo "<h4 style='color: #856404; margin: 0 0 10px 0;'>📋 后续步骤：</h4>";
    echo "<ul style='color: #856404; margin: 0;'>";
    echo "<li>清除浏览器缓存</li>";
    echo "<li>重启Web服务器（如果需要）</li>";
    echo "<li>测试网站各项功能</li>";
    echo "<li>检查邮件模板中的链接</li>";
    echo "<li>更新外部服务的回调URL</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='{$newSiteUrl}' style='display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;' target='_blank'>访问新域名网站</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ 更新失败</h3>";
    echo "<p style='color: red;'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>请检查数据库连接和文件权限。</p>";
}
?>
