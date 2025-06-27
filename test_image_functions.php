<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// 简单测试图片优化功能
echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>图片功能测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>";

echo "<h1>🔧 图片功能测试</h1>";

// 测试配置常量
echo "<div class='test-section'>
        <h2>⚙️ 配置测试</h2>";

$configs = [
    'IMAGE_OPTIMIZATION_ENABLED' => defined('IMAGE_OPTIMIZATION_ENABLED') ? IMAGE_OPTIMIZATION_ENABLED : '未定义',
    'AVATAR_MAX_WIDTH' => defined('AVATAR_MAX_WIDTH') ? AVATAR_MAX_WIDTH : '未定义',
    'AVATAR_MAX_HEIGHT' => defined('AVATAR_MAX_HEIGHT') ? AVATAR_MAX_HEIGHT : '未定义',
    'AVATAR_QUALITY' => defined('AVATAR_QUALITY') ? AVATAR_QUALITY : '未定义',
    'WEBP_ENABLED' => defined('WEBP_ENABLED') ? WEBP_ENABLED : '未定义',
    'THUMBNAIL_ENABLED' => defined('THUMBNAIL_ENABLED') ? THUMBNAIL_ENABLED : '未定义'
];

foreach ($configs as $name => $value) {
    $status = $value !== '未定义' ? 'success' : 'error';
    echo "<div class='$status'>$name: $value</div>";
}
echo "</div>";

// 测试PHP扩展
echo "<div class='test-section'>
        <h2>🔍 PHP扩展检查</h2>";

$extensions = [
    'GD (必需)' => extension_loaded('gd'),
    'ImageMagick (可选)' => extension_loaded('imagick'),
    'WebP支持' => function_exists('imagewebp')
];

foreach ($extensions as $name => $loaded) {
    if (strpos($name, '(必需)') !== false) {
        $status = $loaded ? 'success' : 'error';
        $text = $loaded ? '✅ 已加载' : '❌ 未加载 - 必需安装';
    } else {
        $status = $loaded ? 'success' : 'info';
        $text = $loaded ? '✅ 已加载' : 'ℹ️ 未加载 - 可选扩展';
    }
    echo "<div class='$status'>$name: $text</div>";
}

// 添加说明
echo "<div class='info' style='margin-top: 10px; padding: 10px; background: #e7f3ff; border-radius: 4px;'>
        <strong>📝 说明：</strong><br>
        • <strong>GD扩展</strong>：必需，用于基本的图片处理功能<br>
        • <strong>ImageMagick</strong>：可选，提供更高质量的图片处理，但GD扩展已足够使用<br>
        • <strong>WebP支持</strong>：可选，用于生成更小的WebP格式图片
      </div>";

echo "</div>";

// 测试函数存在性
echo "<div class='test-section'>
        <h2>📋 函数检查</h2>";

$functions = [
    'uploadOptimizedImage',
    'optimizeImage',
    'convertToWebP',
    'getOptimizedImageUrl',
    'generateResponsiveImage',
    'getReaderOptimizedAvatar',
    'getUserOptimizedAvatar'
];

foreach ($functions as $func) {
    $exists = function_exists($func);
    $status = $exists ? 'success' : 'error';
    $text = $exists ? '✅ 存在' : '❌ 不存在';
    echo "<div class='$status'>$func(): $text</div>";
}
echo "</div>";

// 测试目录权限
echo "<div class='test-section'>
        <h2>📁 目录权限检查</h2>";

$directories = [
    PHOTO_PATH,
    PRICE_LIST_PATH,
    CERTIFICATES_PATH
];

foreach ($directories as $dir) {
    $exists = is_dir($dir);
    $writable = $exists ? is_writable($dir) : false;
    
    echo "<div>";
    echo "<strong>$dir:</strong> ";
    if ($exists) {
        echo "<span class='success'>✅ 存在</span> ";
        if ($writable) {
            echo "<span class='success'>✅ 可写</span>";
        } else {
            echo "<span class='error'>❌ 不可写</span>";
        }
    } else {
        echo "<span class='error'>❌ 不存在</span>";
    }
    echo "</div>";
}
echo "</div>";

// 测试数据库连接
echo "<div class='test-section'>
        <h2>🗄️ 数据库连接测试</h2>";

try {
    $db = Database::getInstance();
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 查询一些示例数据
    $readerCount = $db->fetchOne("SELECT COUNT(*) as count FROM readers WHERE photo IS NOT NULL AND photo != ''");
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE avatar IS NOT NULL AND avatar != ''");
    
    echo "<div class='info'>📊 有头像的占卜师: {$readerCount['count']} 个</div>";
    echo "<div class='info'>📊 有头像的用户: {$userCount['count']} 个</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 数据库连接失败: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

// 测试默认头像文件
echo "<div class='test-section'>
        <h2>🖼️ 默认头像文件检查</h2>";

$defaultAvatars = [
    'img/m1.jpg', 'img/m2.jpg', 'img/m3.jpg', 'img/m4.jpg',
    'img/f1.jpg', 'img/f2.jpg', 'img/f3.jpg', 'img/f4.jpg',
    'img/nm.jpg', 'img/nf.jpg'
];

foreach ($defaultAvatars as $avatar) {
    $exists = file_exists($avatar);
    $status = $exists ? 'success' : 'error';
    $text = $exists ? '✅ 存在' : '❌ 不存在';
    echo "<div class='$status'>$avatar: $text</div>";
}
echo "</div>";

// 测试头像URL生成函数
if (function_exists('getReaderPhotoUrl') && function_exists('getUserAvatarUrl')) {
    echo "<div class='test-section'>
            <h2>🔗 头像URL生成测试</h2>";
    
    // 测试占卜师头像URL
    $testReader = ['id' => 1, 'gender' => 'male', 'photo' => '', 'photo_circle' => ''];
    $readerUrl = getReaderPhotoUrl($testReader);
    echo "<div class='info'>测试占卜师头像URL: $readerUrl</div>";
    
    // 测试用户头像URL
    $testUser = ['id' => 1, 'gender' => 'female', 'avatar' => ''];
    $userUrl = getUserAvatarUrl($testUser);
    echo "<div class='info'>测试用户头像URL: $userUrl</div>";
    
    echo "</div>";
}

echo "<div class='test-section'>
        <h2>📝 测试结果总结</h2>
        <div style='background: #f0f8ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>
            <h3>✅ 必需组件检查</h3>
            <p>以下组件必须正常才能使用图片优化功能：</p>
            <ul>
                <li>GD扩展：✅ 必需（用于基本图片处理）</li>
                <li>配置常量：✅ 必需（IMAGE_OPTIMIZATION_ENABLED等）</li>
                <li>上传目录：✅ 必需（可写权限）</li>
                <li>默认头像：✅ 必需（m1-m4.jpg, f1-f4.jpg等）</li>
            </ul>
        </div>

        <div style='background: #fff8e1; padding: 15px; border-radius: 8px; margin: 10px 0;'>
            <h3>ℹ️ 可选组件说明</h3>
            <ul>
                <li><strong>ImageMagick</strong>：可选扩展，提供更高质量的图片处理，但不是必需的</li>
                <li><strong>WebP支持</strong>：可选功能，用于生成更小的WebP格式图片</li>
            </ul>
            <p><em>即使这些可选组件未加载，图片优化功能仍然可以正常工作。</em></p>
        </div>

        <p><strong>🚀 下一步操作：</strong></p>
        <ol>
            <li>访问管理后台的 <a href='admin/image_optimizer.php'>图片优化管理页面</a></li>
            <li>测试用户和占卜师头像上传功能</li>
            <li>检查是否生成了缩略图文件</li>
            <li>验证图片加载速度是否有改善</li>
        </ol>

        <div style='background: #ffebee; padding: 10px; border-radius: 4px; margin: 10px 0;'>
            <strong>⚠️ 注意：</strong>测试完成后请删除此文件以确保安全。
        </div>
      </div>";

echo "</body></html>";
?>
