<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// 测试头像路径修复
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>头像路径测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        img { max-width: 100px; max-height: 100px; margin: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>";

echo "<h1>🔧 头像路径修复测试</h1>";

try {
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 测试占卜师头像
    echo "<div class='test-section'>
            <h2>👥 占卜师头像测试</h2>";
    
    $readers = $db->fetchAll("SELECT id, full_name, photo, photo_circle, gender FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '') LIMIT 5");
    
    if (!empty($readers)) {
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>性别</th>
                    <th>数据库路径</th>
                    <th>处理后路径</th>
                    <th>预览</th>
                    <th>状态</th>
                </tr>";
        
        foreach ($readers as $reader) {
            $dbPath = $reader['photo'];
            $processedPath = getReaderPhotoUrl($reader);
            $fileExists = file_exists($processedPath);
            
            echo "<tr>
                    <td>{$reader['id']}</td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td>{$reader['gender']}</td>
                    <td><code>$dbPath</code></td>
                    <td><code>$processedPath</code></td>
                    <td>";
            
            if ($fileExists) {
                echo "<img src='$processedPath' alt='头像'>";
            } else {
                echo "❌ 文件不存在";
            }
            
            echo "</td>
                    <td>" . ($fileExists ? "<span class='success'>✅ 正常</span>" : "<span class='error'>❌ 错误</span>") . "</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ 没有找到有头像的占卜师</div>";
    }
    echo "</div>";
    
    // 测试用户头像
    echo "<div class='test-section'>
            <h2>👤 用户头像测试</h2>";
    
    $users = $db->fetchAll("SELECT id, full_name, avatar, gender FROM users WHERE avatar IS NOT NULL AND avatar != '' LIMIT 5");
    
    if (!empty($users)) {
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>性别</th>
                    <th>数据库路径</th>
                    <th>处理后路径</th>
                    <th>预览</th>
                    <th>状态</th>
                </tr>";
        
        foreach ($users as $user) {
            $dbPath = $user['avatar'];
            $processedPath = getUserAvatarUrl($user);
            $fileExists = file_exists($processedPath);
            
            echo "<tr>
                    <td>{$user['id']}</td>
                    <td>" . htmlspecialchars($user['full_name']) . "</td>
                    <td>{$user['gender']}</td>
                    <td><code>$dbPath</code></td>
                    <td><code>$processedPath</code></td>
                    <td>";
            
            if ($fileExists) {
                echo "<img src='$processedPath' alt='头像'>";
            } else {
                echo "❌ 文件不存在";
            }
            
            echo "</td>
                    <td>" . ($fileExists ? "<span class='success'>✅ 正常</span>" : "<span class='error'>❌ 错误</span>") . "</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ 没有找到有头像的用户</div>";
    }
    echo "</div>";
    
    // 测试默认头像
    echo "<div class='test-section'>
            <h2>🖼️ 默认头像测试</h2>";
    
    $defaultAvatars = [
        'img/m1.jpg' => '男性占卜师默认头像1',
        'img/m2.jpg' => '男性占卜师默认头像2',
        'img/m3.jpg' => '男性占卜师默认头像3',
        'img/m4.jpg' => '男性占卜师默认头像4',
        'img/f1.jpg' => '女性占卜师默认头像1',
        'img/f2.jpg' => '女性占卜师默认头像2',
        'img/f3.jpg' => '女性占卜师默认头像3',
        'img/f4.jpg' => '女性占卜师默认头像4',
        'img/nm.jpg' => '男性用户默认头像',
        'img/nf.jpg' => '女性用户默认头像'
    ];
    
    echo "<table>
            <tr>
                <th>文件路径</th>
                <th>描述</th>
                <th>预览</th>
                <th>状态</th>
            </tr>";
    
    foreach ($defaultAvatars as $path => $description) {
        $fileExists = file_exists($path);
        echo "<tr>
                <td><code>$path</code></td>
                <td>$description</td>
                <td>";
        
        if ($fileExists) {
            echo "<img src='$path' alt='$description'>";
        } else {
            echo "❌ 文件不存在";
        }
        
        echo "</td>
                <td>" . ($fileExists ? "<span class='success'>✅ 存在</span>" : "<span class='error'>❌ 缺失</span>") . "</td>
              </tr>";
    }
    echo "</table>";
    echo "</div>";

    // 测试新的默认头像选择逻辑
    echo "<div class='test-section'>
            <h2>🎲 默认头像选择逻辑测试</h2>";

    echo "<table>
            <tr>
                <th>占卜师ID</th>
                <th>性别</th>
                <th>选择的默认头像</th>
                <th>预览</th>
                <th>状态</th>
            </tr>";

    // 测试不同ID的默认头像选择
    for ($testId = 1; $testId <= 8; $testId++) {
        foreach (['male', 'female'] as $gender) {
            $testReader = ['id' => $testId, 'gender' => $gender, 'photo' => '', 'photo_circle' => ''];
            $defaultAvatar = getReaderPhotoUrl($testReader);
            $fileExists = file_exists($defaultAvatar);

            echo "<tr>
                    <td>$testId</td>
                    <td>$gender</td>
                    <td><code>$defaultAvatar</code></td>
                    <td>";

            if ($fileExists) {
                echo "<img src='$defaultAvatar' alt='默认头像' style='width: 50px; height: 50px; border-radius: 50%;'>";
            } else {
                echo "❌ 文件不存在";
            }

            echo "</td>
                    <td>" . ($fileExists ? "<span class='success'>✅ 正常</span>" : "<span class='error'>❌ 错误</span>") . "</td>
                  </tr>";
        }
    }
    echo "</table>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div class='test-section'>
        <h2>📝 修复说明</h2>
        <p>本次修复包括以下内容：</p>
        <ul>
            <li>✅ 统一了头像路径处理函数</li>
            <li>✅ 修复了占卜师后台头像上传路径问题</li>
            <li>✅ 修复了前台页面头像显示路径问题</li>
            <li>✅ 修复了用户中心头像显示路径问题</li>
            <li>✅ 修复了管理员后台头像显示路径问题</li>
            <li>✅ 更新默认头像系统：占卜师使用m1-m4/f1-f4，基于ID循环选择</li>
            <li>✅ 移除了旧的tm.jpg/tf.jpg默认头像引用</li>
        </ul>
        <p><strong>新的默认头像规则：</strong></p>
        <ul>
            <li>男性占卜师：img/m1.jpg - img/m4.jpg（基于ID循环选择）</li>
            <li>女性占卜师：img/f1.jpg - img/f4.jpg（基于ID循环选择）</li>
            <li>男性用户：img/nm.jpg</li>
            <li>女性用户：img/nf.jpg</li>
        </ul>
        <p><strong>如果测试显示正常，请删除此测试文件。</strong></p>
      </div>";

echo "</body></html>";
?>
