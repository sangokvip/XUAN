<?php
// 测试页面
echo "<h1>管理员后台测试页面</h1>";

echo "<h2>1. 检查文件包含</h2>";
try {
    require_once '../config/config.php';
    echo "✓ config.php 加载成功<br>";
} catch (Exception $e) {
    echo "✗ config.php 加载失败: " . $e->getMessage() . "<br>";
}

echo "<h2>2. 检查常量</h2>";
echo "ADMIN_ITEMS_PER_PAGE: " . (defined('ADMIN_ITEMS_PER_PAGE') ? ADMIN_ITEMS_PER_PAGE : '未定义') . "<br>";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : '未定义') . "<br>";

echo "<h2>3. 检查函数</h2>";
echo "requireAdminLogin: " . (function_exists('requireAdminLogin') ? '存在' : '不存在') . "<br>";
echo "setSetting: " . (function_exists('setSetting') ? '存在' : '不存在') . "<br>";
echo "Database::getInstance: " . (class_exists('Database') ? '存在' : '不存在') . "<br>";

echo "<h2>4. 检查会话</h2>";
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Admin ID: " . ($_SESSION['admin_id'] ?? '未设置') . "<br>";
echo "User Type: " . ($_SESSION['user_type'] ?? '未设置') . "<br>";

echo "<h2>5. 检查数据库连接</h2>";
try {
    $db = Database::getInstance();
    echo "✓ 数据库连接成功<br>";
    
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM users");
    echo "✓ 用户表查询成功，共 " . $result['count'] . " 个用户<br>";
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "<br>";
}

echo "<h2>6. 测试链接</h2>";
echo "<a href='users.php'>用户管理</a> | ";
echo "<a href='statistics.php'>数据统计</a> | ";
echo "<a href='settings.php'>系统设置</a><br>";

echo "<h2>7. 文件存在性检查</h2>";
$files = ['users.php', 'statistics.php', 'settings.php'];
foreach ($files as $file) {
    echo "{$file}: " . (file_exists($file) ? '存在' : '不存在') . "<br>";
}
?>
