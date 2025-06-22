<?php
// 重置管理员密码
require_once 'config/database_config.php';

$newPassword = 'admin123'; // 新密码

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = 1");
    $stmt->execute([$hashedPassword]);
    
    echo "管理员密码已重置为: {$newPassword}\n";
    echo "请使用邮箱和新密码登录管理后台\n";
    
} catch (Exception $e) {
    echo "重置失败: " . $e->getMessage() . "\n";
}
?>
