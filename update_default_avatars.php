<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// 更新默认头像脚本
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>更新默认头像</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .btn { padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #005a87; }
    </style>
</head>
<body>";

echo "<h1>🔄 更新默认头像</h1>";

$updateMode = isset($_GET['update']) && $_GET['update'] === 'true';

if (!$updateMode) {
    echo "<div class='info'>
            <h3>📋 检查模式</h3>
            <p>当前处于检查模式，不会修改数据库。点击下方按钮执行更新。</p>
            <a href='?update=true' class='btn'>执行更新</a>
          </div>";
} else {
    echo "<div class='warning'>
            <h3>⚠️ 更新模式</h3>
            <p>正在更新数据库中的默认头像...</p>
          </div>";
}

try {
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 查询使用旧默认头像的占卜师
    echo "<div class='section'>
            <h2>👥 占卜师默认头像更新</h2>";
    
    $oldAvatarReaders = $db->fetchAll("SELECT id, full_name, photo, gender FROM readers WHERE photo IN ('img/tm.jpg', 'img/tf.jpg', '../img/tm.jpg', '../img/tf.jpg')");
    
    if (empty($oldAvatarReaders)) {
        echo "<div class='info'>ℹ️ 没有找到使用旧默认头像的占卜师</div>";
    } else {
        echo "<div class='info'>📊 找到 " . count($oldAvatarReaders) . " 个占卜师使用旧默认头像</div>";
        
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>性别</th>
                    <th>当前头像</th>
                    <th>新头像</th>
                    <th>状态</th>
                </tr>";
        
        $updatedCount = 0;
        
        foreach ($oldAvatarReaders as $reader) {
            // 计算新的默认头像
            $avatarNum = (($reader['id'] - 1) % 4) + 1;
            $newAvatar = $reader['gender'] === 'female' ? "img/f{$avatarNum}.jpg" : "img/m{$avatarNum}.jpg";
            
            echo "<tr>
                    <td>{$reader['id']}</td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td>{$reader['gender']}</td>
                    <td><code>{$reader['photo']}</code></td>
                    <td><code>$newAvatar</code></td>
                    <td>";
            
            if ($updateMode) {
                // 执行更新
                $result = $db->update('readers', ['photo' => $newAvatar], 'id = ?', [$reader['id']]);
                if ($result) {
                    echo "<span class='success'>✅ 已更新</span>";
                    $updatedCount++;
                } else {
                    echo "<span class='error'>❌ 更新失败</span>";
                }
            } else {
                echo "<span class='warning'>⏳ 待更新</span>";
            }
            
            echo "</td></tr>";
        }
        
        echo "</table>";
        
        if ($updateMode) {
            echo "<div class='success'>✅ 成功更新 $updatedCount 个占卜师的默认头像</div>";
        }
    }
    echo "</div>";
    
    // 检查新默认头像文件是否存在
    echo "<div class='section'>
            <h2>🖼️ 新默认头像文件检查</h2>";
    
    $newAvatars = [];
    for ($i = 1; $i <= 4; $i++) {
        $newAvatars["img/m{$i}.jpg"] = "男性占卜师默认头像{$i}";
        $newAvatars["img/f{$i}.jpg"] = "女性占卜师默认头像{$i}";
    }
    
    echo "<table>
            <tr>
                <th>文件路径</th>
                <th>描述</th>
                <th>状态</th>
            </tr>";
    
    $missingFiles = [];
    foreach ($newAvatars as $path => $description) {
        $fileExists = file_exists($path);
        echo "<tr>
                <td><code>$path</code></td>
                <td>$description</td>
                <td>" . ($fileExists ? "<span class='success'>✅ 存在</span>" : "<span class='error'>❌ 缺失</span>") . "</td>
              </tr>";
        
        if (!$fileExists) {
            $missingFiles[] = $path;
        }
    }
    echo "</table>";
    
    if (!empty($missingFiles)) {
        echo "<div class='error'>
                <h4>❌ 缺失的头像文件</h4>
                <p>请确保以下文件存在于服务器上：</p>
                <ul>";
        foreach ($missingFiles as $file) {
            echo "<li><code>$file</code></li>";
        }
        echo "</ul>
              </div>";
    } else {
        echo "<div class='success'>✅ 所有新默认头像文件都存在</div>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div class='section'>
        <h2>📝 更新说明</h2>
        <p>本脚本将：</p>
        <ul>
            <li>将使用 <code>img/tm.jpg</code> 的男性占卜师更新为 <code>img/m1.jpg</code> - <code>img/m4.jpg</code></li>
            <li>将使用 <code>img/tf.jpg</code> 的女性占卜师更新为 <code>img/f1.jpg</code> - <code>img/f4.jpg</code></li>
            <li>根据占卜师ID循环选择对应的头像编号</li>
        </ul>
        <p><strong>更新完成后，请删除此脚本文件。</strong></p>
      </div>";

echo "</body></html>";
?>
