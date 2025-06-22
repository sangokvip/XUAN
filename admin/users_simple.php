<?php
// 简化版用户管理页面
echo "<!DOCTYPE html>";
echo "<html><head><title>用户管理</title></head><body>";
echo "<h1>用户管理页面</h1>";

try {
    session_start();
    require_once '../config/config.php';
    
    echo "<p>✓ 配置加载成功</p>";
    
    // 检查管理员登录
    if (!isset($_SESSION['admin_id'])) {
        echo "<p>❌ 请先登录管理员账户</p>";
        echo "<a href='../auth/admin_login.php'>点击登录</a>";
        exit;
    }
    
    echo "<p>✓ 管理员已登录</p>";
    
    $db = Database::getInstance();
    echo "<p>✓ 数据库连接成功</p>";
    
    $users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
    echo "<p>✓ 查询用户数据成功，共 " . count($users) . " 个用户</p>";
    
    echo "<h2>用户列表</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>用户名</th><th>姓名</th><th>邮箱</th><th>状态</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . ($user['is_active'] ? '激活' : '禁用') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>❌ 错误: " . $e->getMessage() . "</p>";
}

echo "<p><a href='dashboard.php'>返回后台首页</a></p>";
echo "</body></html>";
?>
