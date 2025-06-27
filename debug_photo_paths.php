<?php
require_once 'config/config.php';
require_once 'includes/Database.php';

// 初始化数据库连接
try {
    $db = new Database();
} catch (Exception $e) {
    die("数据库连接失败: " . $e->getMessage());
}

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>照片路径调试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #d4af37; padding-bottom: 10px; }
        .debug-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .path-info { margin: 10px 0; padding: 10px; background: white; border-left: 4px solid #007bff; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .photo-preview { max-width: 100px; max-height: 100px; object-fit: cover; border-radius: 5px; }
        .path-test { font-family: monospace; background: #e9ecef; padding: 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 占卜师照片路径调试</h1>";

// 显示配置信息
echo "<div class='debug-section'>
        <h2>📋 配置信息</h2>
        <div class='path-info'>
            <strong>UPLOAD_PATH:</strong> <span class='path-test'>" . UPLOAD_PATH . "</span>
        </div>
        <div class='path-info'>
            <strong>PHOTO_PATH:</strong> <span class='path-test'>" . PHOTO_PATH . "</span>
        </div>
        <div class='path-info'>
            <strong>网站根目录:</strong> <span class='path-test'>" . __DIR__ . "</span>
        </div>
        <div class='path-info'>
            <strong>照片目录绝对路径:</strong> <span class='path-test'>" . __DIR__ . '/' . PHOTO_PATH . "</span>
        </div>
      </div>";

// 检查目录是否存在
$photoDir = __DIR__ . '/' . PHOTO_PATH;
if (is_dir($photoDir)) {
    echo "<div class='success'>✅ 照片目录存在: $photoDir</div>";
    
    // 列出目录中的文件
    $files = scandir($photoDir);
    $imageFiles = array_filter($files, function($file) {
        return !in_array($file, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
    });
    
    if (!empty($imageFiles)) {
        echo "<div class='debug-section'>
                <h3>📁 目录中的图片文件 (" . count($imageFiles) . " 个)</h3>
                <ul>";
        foreach ($imageFiles as $file) {
            $filePath = $photoDir . $file;
            $fileSize = filesize($filePath);
            $fileTime = date('Y-m-d H:i:s', filemtime($filePath));
            echo "<li><strong>$file</strong> - " . number_format($fileSize/1024, 2) . " KB - $fileTime</li>";
        }
        echo "</ul></div>";
    } else {
        echo "<div class='warning'>⚠️ 照片目录为空</div>";
    }
} else {
    echo "<div class='error'>❌ 照片目录不存在: $photoDir</div>";
}

// 查询数据库中的占卜师照片信息
try {
    $readers = $db->fetchAll("SELECT id, full_name, photo, photo_circle FROM readers WHERE photo IS NOT NULL AND photo != '' ORDER BY id DESC LIMIT 10");
    
    if (!empty($readers)) {
        echo "<div class='debug-section'>
                <h2>👥 数据库中的占卜师照片信息</h2>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>姓名</th>
                        <th>照片路径</th>
                        <th>文件存在</th>
                        <th>预览</th>
                        <th>路径测试</th>
                    </tr>";
        
        foreach ($readers as $reader) {
            $photoPath = $reader['photo'];
            $fullPath = __DIR__ . '/' . $photoPath;
            $fileExists = file_exists($fullPath);
            
            echo "<tr>
                    <td>{$reader['id']}</td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td><span class='path-test'>$photoPath</span></td>
                    <td>" . ($fileExists ? "<span style='color: green;'>✅ 存在</span>" : "<span style='color: red;'>❌ 不存在</span>") . "</td>
                    <td>";
            
            if ($fileExists) {
                echo "<img src='$photoPath' alt='照片' class='photo-preview'>";
            } else {
                echo "无法显示";
            }
            
            echo "</td>
                    <td>";
            
            // 测试不同的路径格式
            $testPaths = [
                $photoPath,
                './' . $photoPath,
                '../' . $photoPath
            ];
            
            foreach ($testPaths as $testPath) {
                $testExists = file_exists(__DIR__ . '/' . $testPath);
                echo "<div><span class='path-test'>$testPath</span> - " . ($testExists ? "✅" : "❌") . "</div>";
            }
            
            echo "</td></tr>";
        }
        
        echo "</table></div>";
    } else {
        echo "<div class='warning'>⚠️ 数据库中没有找到有照片的占卜师</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 数据库查询错误: " . $e->getMessage() . "</div>";
}

// 测试路径解析
echo "<div class='debug-section'>
        <h2>🧪 路径解析测试</h2>";

// 模拟前台页面的路径处理
$testPhotoPath = 'uploads/photos/test.jpg';
echo "<div class='path-info'>
        <strong>测试路径:</strong> <span class='path-test'>$testPhotoPath</span><br>
        <strong>前台显示路径:</strong> <span class='path-test'>$testPhotoPath</span> (直接使用)<br>
        <strong>后台显示路径:</strong> <span class='path-test'>../$testPhotoPath</span> (添加../前缀)
      </div>";

echo "<div class='warning'>
        <h4>⚠️ 可能的问题:</h4>
        <ul>
            <li>后台上传时保存的路径格式与前台显示时期望的格式不一致</li>
            <li>路径中可能包含多余的../前缀</li>
            <li>文件实际位置与数据库记录的路径不匹配</li>
        </ul>
      </div>";

echo "</div>";

// 提供修复建议
echo "<div class='debug-section'>
        <h2>🔧 修复建议</h2>
        <div class='success'>
            <h4>解决方案:</h4>
            <ol>
                <li><strong>统一路径格式</strong>: 确保数据库中存储的路径格式一致</li>
                <li><strong>修复显示逻辑</strong>: 前台页面正确处理照片路径</li>
                <li><strong>清理重复前缀</strong>: 移除路径中多余的../前缀</li>
                <li><strong>验证文件存在</strong>: 显示前检查文件是否存在</li>
            </ol>
        </div>
      </div>";

echo "
    </div>
</body>
</html>";
?>
