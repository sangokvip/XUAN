<?php
/**
 * 测试数据库连接和照片路径
 */

// 显示所有错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <title>数据库连接测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 数据库连接和照片路径测试</h1>";

// 1. 检查文件是否存在
echo "<h2>📁 文件检查</h2>";

$files = [
    'config/config.php',
    'includes/Database.php',
    'includes/functions.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<div class='success'>✅ $file 存在</div>";
    } else {
        echo "<div class='error'>❌ $file 不存在</div>";
    }
}

// 2. 引入配置文件
echo "<h2>⚙️ 配置加载</h2>";

try {
    require_once 'config/config.php';
    echo "<div class='success'>✅ config.php 加载成功</div>";
    
    // 显示一些配置信息
    echo "<div class='info'>
            <strong>数据库配置:</strong><br>
            主机: " . DB_HOST . "<br>
            数据库: " . DB_NAME . "<br>
            用户: " . DB_USER . "<br>
            照片路径: " . PHOTO_PATH . "
          </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ config.php 加载失败: " . $e->getMessage() . "</div>";
    exit;
}

// 3. 加载数据库类
echo "<h2>🗄️ 数据库类加载</h2>";

try {
    require_once 'includes/Database.php';
    echo "<div class='success'>✅ Database.php 加载成功</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Database.php 加载失败: " . $e->getMessage() . "</div>";
    exit;
}

// 4. 测试数据库连接
echo "<h2>🔌 数据库连接测试</h2>";

try {
    $db = new Database();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 测试简单查询
    $result = $db->fetchOne("SELECT COUNT(*) as count FROM readers");
    echo "<div class='info'>📊 占卜师总数: " . $result['count'] . "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 数据库连接失败: " . $e->getMessage() . "</div>";
    echo "<div class='code'>错误详情: " . $e->getTraceAsString() . "</div>";
    exit;
}

// 5. 查询照片数据
echo "<h2>📸 照片数据查询</h2>";

try {
    $sql = "SELECT id, full_name, photo, photo_circle FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '') LIMIT 5";
    $readers = $db->fetchAll($sql);
    
    echo "<div class='success'>✅ 查询成功，找到 " . count($readers) . " 条记录</div>";
    
    if (!empty($readers)) {
        echo "<table border='1' style='width: 100%; border-collapse: collapse; margin: 10px 0;'>
                <tr style='background: #f8f9fa;'>
                    <th style='padding: 8px;'>ID</th>
                    <th style='padding: 8px;'>姓名</th>
                    <th style='padding: 8px;'>照片路径</th>
                    <th style='padding: 8px;'>圆形照片</th>
                </tr>";
        
        foreach ($readers as $reader) {
            echo "<tr>
                    <td style='padding: 8px;'>{$reader['id']}</td>
                    <td style='padding: 8px;'>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td style='padding: 8px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($reader['photo'] ?: '无') . "</td>
                    <td style='padding: 8px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($reader['photo_circle'] ?: '无') . "</td>
                  </tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 查询失败: " . $e->getMessage() . "</div>";
}

// 6. 检查照片目录
echo "<h2>📁 照片目录检查</h2>";

$photoDir = PHOTO_PATH;
$fullPhotoDir = __DIR__ . '/' . $photoDir;

echo "<div class='info'>
        <strong>照片目录配置:</strong><br>
        相对路径: $photoDir<br>
        绝对路径: $fullPhotoDir
      </div>";

if (is_dir($fullPhotoDir)) {
    echo "<div class='success'>✅ 照片目录存在</div>";
    
    // 检查目录权限
    if (is_readable($fullPhotoDir)) {
        echo "<div class='success'>✅ 目录可读</div>";
    } else {
        echo "<div class='error'>❌ 目录不可读</div>";
    }
    
    if (is_writable($fullPhotoDir)) {
        echo "<div class='success'>✅ 目录可写</div>";
    } else {
        echo "<div class='error'>❌ 目录不可写</div>";
    }
    
    // 列出目录中的文件
    $files = scandir($fullPhotoDir);
    $imageFiles = array_filter($files, function($file) {
        return !in_array($file, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
    });
    
    echo "<div class='info'>📊 目录中的图片文件: " . count($imageFiles) . " 个</div>";
    
    if (count($imageFiles) > 0) {
        echo "<div class='code'>";
        foreach (array_slice($imageFiles, 0, 5) as $file) {
            echo "• $file<br>";
        }
        if (count($imageFiles) > 5) {
            echo "... 还有 " . (count($imageFiles) - 5) . " 个文件";
        }
        echo "</div>";
    }
    
} else {
    echo "<div class='error'>❌ 照片目录不存在</div>";
    echo "<div class='info'>尝试创建目录...</div>";
    
    if (mkdir($fullPhotoDir, 0755, true)) {
        echo "<div class='success'>✅ 目录创建成功</div>";
    } else {
        echo "<div class='error'>❌ 目录创建失败</div>";
    }
}

// 7. 路径修复建议
echo "<h2>🔧 修复建议</h2>";

echo "<div class='info'>
        <h4>如果一切正常，可以：</h4>
        <ul>
            <li>使用 <a href='fix_photo_paths_simple.php'>fix_photo_paths_simple.php</a> 修复路径</li>
            <li>在占卜师后台上传新照片测试</li>
            <li>检查前台页面照片显示</li>
        </ul>
      </div>";

echo "<div style='text-align: center; margin: 20px 0;'>
        <a href='fix_photo_paths_simple.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px;'>
            🔧 运行路径修复
        </a>
        <a href='index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 5px;'>
            🏠 返回首页
        </a>
      </div>";

echo "
    </div>
</body>
</html>";
?>
