<?php
session_start();
require_once 'config/config.php';

echo "<h1>占卜师权限测试</h1>";

// 检查当前登录状态
if (!isset($_SESSION['reader_id'])) {
    echo "<p style='color: red;'>❌ 请先登录占卜师账户</p>";
    echo "<p><a href='auth/reader_login.php'>点击这里登录</a></p>";
    exit;
}

$currentReaderId = $_SESSION['reader_id'];
echo "<h2>当前登录的占卜师ID: $currentReaderId</h2>";

// 获取一些测试用的占卜师ID
$db = Database::getInstance();
$readers = $db->fetchAll("SELECT id, full_name FROM readers WHERE is_active = 1 LIMIT 5");

echo "<h2>测试场景：</h2>";

echo "<h3>1. 查看自己的页面（应该免费显示联系方式）</h3>";
echo "<ul>";
echo "<li><a href='reader.php?id=$currentReaderId' target='_blank'>查看自己的页面 (ID: $currentReaderId)</a></li>";
echo "<li>预期：显示'这是您的个人页面，可直接查看联系方式'</li>";
echo "<li>预期：直接显示联系方式内容</li>";
echo "</ul>";

echo "<h3>2. 查看其他占卜师页面（应该需要付费）</h3>";
echo "<ul>";
foreach ($readers as $reader) {
    if ($reader['id'] != $currentReaderId) {
        echo "<li><a href='reader.php?id={$reader['id']}' target='_blank'>查看 {$reader['full_name']} (ID: {$reader['id']})</a></li>";
    }
}
echo "<li>预期：显示付费提示和Tata Coin余额</li>";
echo "<li>预期：需要点击按钮并支付才能查看联系方式</li>";
echo "</ul>";

// 显示当前Tata Coin余额
require_once 'includes/TataCoinManager.php';
$tataCoinManager = new TataCoinManager();
$balance = $tataCoinManager->getBalance($currentReaderId, 'reader');

echo "<h2>当前Tata Coin余额：</h2>";
echo "<p style='font-size: 18px; color: #d4af37;'>💰 $balance 枚</p>";

echo "<h2>功能说明：</h2>";
echo "<ul>";
echo "<li>✅ 占卜师可以免费查看自己页面的联系方式</li>";
echo "<li>💰 占卜师查看其他占卜师需要付费（和普通用户一样）</li>";
echo "<li>📊 推荐占卜师：30 Tata Coin</li>";
echo "<li>📊 普通占卜师：10 Tata Coin</li>";
echo "</ul>";

echo "<h2>调试链接：</h2>";
echo "<ul>";
echo "<li><a href='debug_session.php'>Session 调试</a></li>";
echo "<li><a href='debug_reader_vars.php?id=$currentReaderId'>调试自己页面变量</a></li>";
if (!empty($readers)) {
    $otherId = $readers[0]['id'] != $currentReaderId ? $readers[0]['id'] : ($readers[1]['id'] ?? $readers[0]['id']);
    echo "<li><a href='debug_reader_vars.php?id=$otherId'>调试其他占卜师页面变量</a></li>";
}
echo "</ul>";
?>
