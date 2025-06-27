<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// 测试图片优化功能
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>图片优化测试</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        .image-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .image-card { border: 1px solid #eee; padding: 15px; border-radius: 8px; text-align: center; }
        .image-card img { max-width: 100%; height: auto; border-radius: 4px; }
        .image-info { font-size: 12px; color: #666; margin-top: 10px; }
        .config-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .config-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .status-enabled { color: #28a745; font-weight: bold; }
        .status-disabled { color: #dc3545; font-weight: bold; }
        .btn { padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px; }
        .btn:hover { background: #005a87; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>";

echo "<h1>🖼️ 图片优化测试</h1>";

try {
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 显示当前配置
    echo "<div class='test-section'>
            <h2>⚙️ 当前配置</h2>
            <div class='config-grid'>
                <div class='config-item'>
                    <strong>图片优化：</strong>
                    <span class='" . (IMAGE_OPTIMIZATION_ENABLED ? 'status-enabled' : 'status-disabled') . "'>
                        " . (IMAGE_OPTIMIZATION_ENABLED ? '已启用' : '已禁用') . "
                    </span>
                </div>
                <div class='config-item'>
                    <strong>头像最大尺寸：</strong>
                    " . AVATAR_MAX_WIDTH . " × " . AVATAR_MAX_HEIGHT . " px
                </div>
                <div class='config-item'>
                    <strong>压缩质量：</strong>
                    " . AVATAR_QUALITY . "%
                </div>
                <div class='config-item'>
                    <strong>WebP支持：</strong>
                    <span class='" . (WEBP_ENABLED ? 'status-enabled' : 'status-disabled') . "'>
                        " . (WEBP_ENABLED ? '已启用' : '已禁用') . "
                    </span>
                </div>
                <div class='config-item'>
                    <strong>缩略图生成：</strong>
                    <span class='" . (THUMBNAIL_ENABLED ? 'status-enabled' : 'status-disabled') . "'>
                        " . (THUMBNAIL_ENABLED ? '已启用' : '已禁用') . "
                    </span>
                </div>
                <div class='config-item'>
                    <strong>最大文件大小：</strong>
                    " . formatBytes(MAX_FILE_SIZE) . "
                </div>
            </div>
          </div>";
    
    // 测试占卜师头像优化
    echo "<div class='test-section'>
            <h2>👥 占卜师头像优化测试</h2>";
    
    $readers = $db->fetchAll("SELECT id, full_name, photo, gender FROM readers WHERE photo IS NOT NULL AND photo != '' AND photo NOT LIKE 'img/%' LIMIT 6");
    
    if (!empty($readers)) {
        echo "<div class='image-grid'>";
        
        foreach ($readers as $reader) {
            echo "<div class='image-card'>
                    <h4>" . htmlspecialchars($reader['full_name']) . "</h4>";
            
            // 原图
            $originalPath = $reader['photo'];
            $originalExists = file_exists($originalPath);
            $originalSize = $originalExists ? filesize($originalPath) : 0;
            
            echo "<div>
                    <strong>原图：</strong><br>";
            if ($originalExists) {
                echo "<img src='" . htmlspecialchars($originalPath) . "' alt='原图' style='max-height: 150px;'>
                      <div class='image-info'>大小: " . formatBytes($originalSize) . "</div>";
            } else {
                echo "<div class='error'>❌ 文件不存在</div>";
            }
            echo "</div>";
            
            // 检查优化版本
            $pathInfo = pathinfo($originalPath);
            $baseName = $pathInfo['filename'];
            $extension = $pathInfo['extension'];
            $directory = $pathInfo['dirname'];
            
            $thumbnails = ['small' => [80, 80], 'medium' => [150, 150], 'large' => [300, 300]];
            
            foreach ($thumbnails as $size => $dimensions) {
                $thumbPath = $directory . '/' . $baseName . '_' . $size . '.' . $extension;
                $webpPath = $directory . '/' . $baseName . '_' . $size . '.webp';
                
                echo "<div style='margin-top: 10px;'>
                        <strong>{$size} ({$dimensions[0]}x{$dimensions[1]}):</strong><br>";
                
                if (file_exists($thumbPath)) {
                    $thumbSize = filesize($thumbPath);
                    echo "<img src='" . htmlspecialchars($thumbPath) . "' alt='{$size}缩略图' style='max-height: 100px;'>
                          <div class='image-info'>大小: " . formatBytes($thumbSize) . "</div>";
                } else {
                    echo "<div class='warning'>⚠️ 缩略图不存在</div>";
                }
                
                if (file_exists($webpPath)) {
                    $webpSize = filesize($webpPath);
                    echo "<div class='image-info'>WebP: " . formatBytes($webpSize) . "</div>";
                } else {
                    echo "<div class='image-info'>WebP: 未生成</div>";
                }
                
                echo "</div>";
            }
            
            echo "</div>";
        }
        
        echo "</div>";
    } else {
        echo "<div class='info'>ℹ️ 没有找到上传的占卜师头像</div>";
    }
    echo "</div>";
    
    // 测试用户头像优化
    echo "<div class='test-section'>
            <h2>👤 用户头像优化测试</h2>";
    
    $users = $db->fetchAll("SELECT id, full_name, avatar, gender FROM users WHERE avatar IS NOT NULL AND avatar != '' AND avatar NOT LIKE 'img/%' LIMIT 6");
    
    if (!empty($users)) {
        echo "<div class='image-grid'>";
        
        foreach ($users as $user) {
            echo "<div class='image-card'>
                    <h4>" . htmlspecialchars($user['full_name']) . "</h4>";
            
            // 原图
            $originalPath = $user['avatar'];
            $originalExists = file_exists($originalPath);
            $originalSize = $originalExists ? filesize($originalPath) : 0;
            
            echo "<div>
                    <strong>原图：</strong><br>";
            if ($originalExists) {
                echo "<img src='" . htmlspecialchars($originalPath) . "' alt='原图' style='max-height: 150px;'>
                      <div class='image-info'>大小: " . formatBytes($originalSize) . "</div>";
            } else {
                echo "<div class='error'>❌ 文件不存在</div>";
            }
            echo "</div>";
            
            echo "</div>";
        }
        
        echo "</div>";
    } else {
        echo "<div class='info'>ℹ️ 没有找到上传的用户头像</div>";
    }
    echo "</div>";
    
    // 响应式图片测试
    echo "<div class='test-section'>
            <h2>📱 响应式图片测试</h2>
            <p>以下展示了使用新的响应式图片函数的效果：</p>";
    
    if (!empty($readers)) {
        $testReader = $readers[0];
        echo "<div class='image-grid'>
                <div class='image-card'>
                    <h4>传统img标签</h4>
                    <img src='" . htmlspecialchars($testReader['photo']) . "' alt='传统方式' style='max-height: 150px;'>
                </div>
                <div class='image-card'>
                    <h4>优化的响应式图片</h4>";
        
        echo getReaderOptimizedAvatar($testReader, 'medium', false, '', ['style' => 'max-height: 150px;']);
        
        echo "</div>
              </div>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 错误: " . htmlspecialchars($e->getMessage()) . "</div>";
}

/**
 * 格式化字节数
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

echo "<div class='test-section'>
        <h2>🔧 管理工具</h2>
        <p>使用以下工具管理图片优化：</p>
        <a href='admin/image_optimizer.php' class='btn'>图片优化管理</a>
        <a href='user/upload_avatar.php' class='btn btn-secondary'>测试用户头像上传</a>
        <a href='reader/settings.php' class='btn btn-secondary'>测试占卜师头像上传</a>
      </div>";

echo "<div class='test-section'>
        <h2>📝 优化说明</h2>
        <p>本次图片优化包括以下功能：</p>
        <ul>
            <li>✅ 自动压缩和调整图片尺寸</li>
            <li>✅ 生成多种尺寸的缩略图（small: 80x80, medium: 150x150, large: 300x300）</li>
            <li>✅ 支持WebP格式以减少文件大小</li>
            <li>✅ 响应式图片显示，自动选择最适合的尺寸</li>
            <li>✅ 懒加载支持，提升页面加载速度</li>
            <li>✅ 图片加载失败时的降级处理</li>
        </ul>
        <p><strong>测试完成后，请删除此测试文件。</strong></p>
      </div>";

echo "<script src='assets/js/lazy-loading.js'></script>";
echo "</body></html>";
?>
