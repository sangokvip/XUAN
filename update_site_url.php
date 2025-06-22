<?php
// 更新网站URL设置
require_once 'config/database_config.php';

$newSiteUrl = 'http://t.xuan.mom'; // 请根据实际情况修改

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 更新或插入网站URL设置
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('site_url', ?, '网站URL') ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$newSiteUrl, $newSiteUrl]);
    
    echo "✓ 数据库中的网站URL已更新为: {$newSiteUrl}\n";
    
    // 更新配置文件
    $configContent = "<?php\n";
    $configContent .= "// 网站配置\n";
    $configContent .= "define('SITE_URL', '{$newSiteUrl}');\n";
    $configContent .= "define('INSTALLED', true);\n";
    
    file_put_contents('config/site_config.php', $configContent);
    echo "✓ 配置文件已更新\n";
    
    echo "\n现在您可以访问: {$newSiteUrl}\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
}
?>
