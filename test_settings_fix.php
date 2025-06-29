<?php
session_start();
require_once 'config/config.php';

// 检查塔罗师权限
requireReaderLogin();

$db = Database::getInstance();
$readerId = $_SESSION['reader_id'];

// 获取当前塔罗师信息
$reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Settings图片修复测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-container { max-width: 1200px; margin: 0 auto; }
        .test-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .image-test { display: flex; gap: 20px; align-items: center; margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .image-preview { max-width: 120px; max-height: 120px; border: 2px solid #ddd; border-radius: 8px; }
        .image-info { flex: 1; }
        .path-list { background: #e9ecef; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .path-item { margin: 5px 0; font-family: monospace; font-size: 12px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>";

echo "<div class='test-container'>";
echo "<h1>🔧 Settings页面图片修复测试</h1>";
echo "<p>测试时间: " . date('Y-m-d H:i:s') . " | 用户ID: $readerId</p>";

// 测试个人照片
echo "<div class='test-section'>
        <h2>📷 个人照片测试</h2>";

if (!empty($reader['photo'])) {
    $photoPath = $reader['photo'];
    echo "<p><strong>数据库路径:</strong> <code>" . htmlspecialchars($photoPath) . "</code></p>";
    
    // 使用与settings.php相同的逻辑
    $possiblePaths = [
        '../' . ltrim($photoPath, './'),
        '../uploads/photos/' . basename($photoPath),
        $photoPath,
        '../' . $photoPath
    ];
    
    $displayPath = null;
    foreach ($possiblePaths as $testPath) {
        if (file_exists($testPath)) {
            $displayPath = $testPath;
            break;
        }
    }
    
    echo "<div class='image-test'>";
    if ($displayPath) {
        echo "<img src='" . htmlspecialchars($displayPath) . "' alt='个人照片' class='image-preview'>";
        echo "<div class='image-info'>
                <div class='success'>✅ 图片显示正常</div>
                <p><strong>使用路径:</strong> <code>" . htmlspecialchars($displayPath) . "</code></p>
                <p><strong>文件大小:</strong> " . filesize($displayPath) . " bytes</p>
              </div>";
    } else {
        echo "<div style='width: 120px; height: 120px; background: #f8f9fa; border: 2px dashed #dc3545; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #dc3545;'>❌</div>";
        echo "<div class='image-info'>
                <div class='error'>❌ 图片文件不存在</div>
                <p>尝试的路径:</p>
                <div class='path-list'>";
        foreach ($possiblePaths as $path) {
            $exists = file_exists($path) ? '✅' : '❌';
            echo "<div class='path-item'>$exists <code>" . htmlspecialchars($path) . "</code></div>";
        }
        echo "</div></div>";
    }
    echo "</div>";
} else {
    echo "<div class='warning'>⚠️ 数据库中没有个人照片路径</div>";
}

echo "</div>";

// 测试价格列表
echo "<div class='test-section'>
        <h2>💰 价格列表测试</h2>";

if (!empty($reader['price_list_image'])) {
    $priceListPath = $reader['price_list_image'];
    echo "<p><strong>数据库路径:</strong> <code>" . htmlspecialchars($priceListPath) . "</code></p>";
    
    // 使用与settings.php相同的逻辑
    $possiblePaths = [
        '../' . ltrim($priceListPath, './'),
        '../uploads/price_lists/' . basename($priceListPath),
        $priceListPath,
        '../' . $priceListPath
    ];
    
    $displayPath = null;
    foreach ($possiblePaths as $testPath) {
        if (file_exists($testPath)) {
            $displayPath = $testPath;
            break;
        }
    }
    
    echo "<div class='image-test'>";
    if ($displayPath) {
        echo "<img src='" . htmlspecialchars($displayPath) . "' alt='价格列表' class='image-preview'>";
        echo "<div class='image-info'>
                <div class='success'>✅ 图片显示正常</div>
                <p><strong>使用路径:</strong> <code>" . htmlspecialchars($displayPath) . "</code></p>
                <p><strong>文件大小:</strong> " . filesize($displayPath) . " bytes</p>
              </div>";
    } else {
        echo "<div style='width: 120px; height: 120px; background: #f8f9fa; border: 2px dashed #dc3545; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #dc3545;'>❌</div>";
        echo "<div class='image-info'>
                <div class='error'>❌ 图片文件不存在</div>
                <p>尝试的路径:</p>
                <div class='path-list'>";
        foreach ($possiblePaths as $path) {
            $exists = file_exists($path) ? '✅' : '❌';
            echo "<div class='path-item'>$exists <code>" . htmlspecialchars($path) . "</code></div>";
        }
        echo "</div></div>";
    }
    echo "</div>";
} else {
    echo "<div class='warning'>⚠️ 数据库中没有价格列表路径</div>";
}

echo "</div>";

// 测试证书
echo "<div class='test-section'>
        <h2>🏆 证书测试</h2>";

if (!empty($reader['certificates'])) {
    $certificates = json_decode($reader['certificates'], true) ?: [];
    echo "<p><strong>证书数量:</strong> " . count($certificates) . "</p>";
    
    foreach ($certificates as $index => $certificate) {
        echo "<h4>证书 " . ($index + 1) . "</h4>";
        echo "<p><strong>数据库路径:</strong> <code>" . htmlspecialchars($certificate) . "</code></p>";
        
        // 使用与settings.php相同的逻辑
        $possiblePaths = [
            '../' . ltrim($certificate, './'),
            '../uploads/certificates/' . basename($certificate),
            $certificate,
            '../' . $certificate
        ];
        
        $displayPath = null;
        foreach ($possiblePaths as $testPath) {
            if (file_exists($testPath)) {
                $displayPath = $testPath;
                break;
            }
        }
        
        echo "<div class='image-test'>";
        if ($displayPath) {
            echo "<img src='" . htmlspecialchars($displayPath) . "' alt='证书" . ($index + 1) . "' class='image-preview'>";
            echo "<div class='image-info'>
                    <div class='success'>✅ 图片显示正常</div>
                    <p><strong>使用路径:</strong> <code>" . htmlspecialchars($displayPath) . "</code></p>
                    <p><strong>文件大小:</strong> " . filesize($displayPath) . " bytes</p>
                  </div>";
        } else {
            echo "<div style='width: 120px; height: 120px; background: #f8f9fa; border: 2px dashed #dc3545; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #dc3545;'>❌</div>";
            echo "<div class='image-info'>
                    <div class='error'>❌ 图片文件不存在</div>
                    <p>尝试的路径:</p>
                    <div class='path-list'>";
            foreach ($possiblePaths as $path) {
                $exists = file_exists($path) ? '✅' : '❌';
                echo "<div class='path-item'>$exists <code>" . htmlspecialchars($path) . "</code></div>";
            }
            echo "</div></div>";
        }
        echo "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ 数据库中没有证书</div>";
}

echo "</div>";

// 操作按钮
echo "<div class='test-section'>
        <h2>🔗 操作</h2>
        <a href='reader/settings.php' class='btn'>返回设置页面</a>
        <a href='debug_settings_images.php' class='btn'>详细调试信息</a>
        <a href='test_image_display_fix.php' class='btn'>图片显示修复测试</a>
      </div>";

echo "</div>";
echo "</body></html>";
?>
