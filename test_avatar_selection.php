<?php
// 测试头像选择功能
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>头像选择功能测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .avatar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .avatar-item {
            text-align: center;
        }
        .avatar-item img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
        }
        .avatar-item.exists img {
            border-color: #10b981;
        }
        .avatar-item.missing img {
            border-color: #ef4444;
        }
        .status {
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .exists { color: #10b981; }
        .missing { color: #ef4444; }
        .info {
            background: #e0f2fe;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <h1>🖼️ 头像选择功能测试</h1>
    
    <div class="info">
        <h3>📋 测试说明</h3>
        <p>此页面用于测试新上传的8张头像文件是否正确放置在img目录中，以及头像选择功能是否正常工作。</p>
    </div>

    <div class="test-section">
        <h2>👨 男性占卜师头像</h2>
        <div class="avatar-grid">
            <?php
            $maleAvatars = ['m1.jpg', 'm2.jpg', 'm3.jpg', 'm4.jpg'];
            foreach ($maleAvatars as $avatar) {
                $path = "img/{$avatar}";
                $exists = file_exists($path);
                $class = $exists ? 'exists' : 'missing';
                $status = $exists ? '✅ 存在' : '❌ 缺失';
                echo "<div class='avatar-item {$class}'>";
                echo "<img src='{$path}' alt='{$avatar}' onerror='this.src=\"data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2780%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23ddd%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3E缺失%3C/text%3E%3C/svg%3E\"'>";
                echo "<div class='status {$class}'>{$status}</div>";
                echo "<div>{$avatar}</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <div class="test-section">
        <h2>👩 女性占卜师头像</h2>
        <div class="avatar-grid">
            <?php
            $femaleAvatars = ['f1.jpg', 'f2.jpg', 'f3.jpg', 'f4.jpg'];
            foreach ($femaleAvatars as $avatar) {
                $path = "img/{$avatar}";
                $exists = file_exists($path);
                $class = $exists ? 'exists' : 'missing';
                $status = $exists ? '✅ 存在' : '❌ 缺失';
                echo "<div class='avatar-item {$class}'>";
                echo "<img src='{$path}' alt='{$avatar}' onerror='this.src=\"data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2780%27%3E%3Crect width=%27100%25%27 height=%27100%25%27 fill=%27%23ddd%27/%3E%3Ctext x=%2750%25%27 y=%2750%25%27 text-anchor=%27middle%27 dy=%27.3em%27%3E缺失%3C/text%3E%3C/svg%3E\"'>";
                echo "<div class='status {$class}'>{$status}</div>";
                echo "<div>{$avatar}</div>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <div class="test-section">
        <h2>🔗 功能链接</h2>
        <p><a href="auth/reader_register.php" target="_blank">🔗 测试塔罗师注册页面</a> - 查看新的头像选择功能</p>
        <p><a href="admin/readers.php" target="_blank">🔗 管理员后台</a> - 测试一键注册功能</p>
    </div>

    <div class="test-section">
        <h2>📊 测试结果总结</h2>
        <?php
        $allAvatars = array_merge($maleAvatars, $femaleAvatars);
        $existingCount = 0;
        $missingAvatars = [];
        
        foreach ($allAvatars as $avatar) {
            if (file_exists("img/{$avatar}")) {
                $existingCount++;
            } else {
                $missingAvatars[] = $avatar;
            }
        }
        
        echo "<p><strong>总计：</strong> {$existingCount}/8 个头像文件存在</p>";
        
        if (count($missingAvatars) > 0) {
            echo "<p class='missing'><strong>缺失的文件：</strong> " . implode(', ', $missingAvatars) . "</p>";
            echo "<p>请确保将这些文件上传到 img/ 目录中。</p>";
        } else {
            echo "<p class='exists'><strong>✅ 所有头像文件都已正确放置！</strong></p>";
            echo "<p>现在可以测试塔罗师注册页面的头像选择功能了。</p>";
        }
        ?>
    </div>
</body>
</html>
