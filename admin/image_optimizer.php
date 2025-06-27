<?php
session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$pageTitle = '图片优化管理';

// 处理批量优化请求
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clean_cache') {
        $cleaned = cleanImageCache();
        $message = "缓存清理完成！删除了 {$cleaned['count']} 个文件，释放空间 " . formatBytes($cleaned['freed_space']);
        $messageType = 'success';
    }
}

// 获取图片统计信息
$stats = getImageStats();

/**
 * 获取图片统计信息
 */
function getImageStats() {
    $stats = [
        'total_images' => 0,
        'total_size' => 0,
        'optimized_images' => 0,
        'unoptimized_images' => 0,
        'webp_images' => 0,
        'thumbnails' => 0
    ];
    
    $directories = ['../' . PHOTO_PATH, '../' . PRICE_LIST_PATH, '../' . CERTIFICATES_PATH];

    foreach ($directories as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $stats['total_images']++;
                    $stats['total_size'] += filesize($file);
                    
                    $filename = basename($file);
                    if (strpos($filename, '_small') !== false || 
                        strpos($filename, '_medium') !== false || 
                        strpos($filename, '_large') !== false) {
                        $stats['thumbnails']++;
                    }
                    
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
                        $stats['webp_images']++;
                    }
                    
                    if (strpos($filename, '_optimized') !== false) {
                        $stats['optimized_images']++;
                    } else {
                        $stats['unoptimized_images']++;
                    }
                }
            }
        }
    }
    
    return $stats;
}



/**
 * 清理图片缓存
 */
function cleanImageCache() {
    $count = 0;
    $freedSpace = 0;
    
    $cacheDir = '../cache/thumbnails';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $freedSpace += filesize($file);
                unlink($file);
                $count++;
            }
        }
    }
    
    return ['count' => $count, 'freed_space' => $freedSpace];
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

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>

        <div class="admin-content">
            <h1><?php echo $pageTitle; ?></h1>
            <p>管理和优化网站图片，提升加载速度</p>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- 统计信息 -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>总图片数</h3>
            <div class="stat-number"><?php echo $stats['total_images']; ?></div>
        </div>
        <div class="stat-card">
            <h3>总大小</h3>
            <div class="stat-number"><?php echo formatBytes($stats['total_size']); ?></div>
        </div>
        <div class="stat-card">
            <h3>WebP图片</h3>
            <div class="stat-number"><?php echo $stats['webp_images']; ?></div>
        </div>
        <div class="stat-card">
            <h3>缩略图</h3>
            <div class="stat-number"><?php echo $stats['thumbnails']; ?></div>
        </div>
    </div>

    <!-- 操作面板 -->
    <div class="card">
        <div class="card-header">
            <h2>优化操作</h2>
        </div>
        <div class="card-body">
            <div class="action-grid">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clean_cache">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('确定要清理图片缓存吗？')">
                        🗑️ 清理缓存
                    </button>
                    <p class="help-text">删除过期的缩略图缓存文件</p>
                </form>

                <div style="display: inline-block;">
                    <p><strong>💡 图片优化说明：</strong></p>
                    <p class="help-text">新上传的图片会自动优化。现有图片可以通过重新上传来优化。</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 配置信息 -->
    <div class="card">
        <div class="card-header">
            <h2>当前配置</h2>
        </div>
        <div class="card-body">
            <div class="config-grid">
                <div class="config-item">
                    <strong>图片优化：</strong>
                    <span class="<?php echo IMAGE_OPTIMIZATION_ENABLED ? 'status-enabled' : 'status-disabled'; ?>">
                        <?php echo IMAGE_OPTIMIZATION_ENABLED ? '已启用' : '已禁用'; ?>
                    </span>
                </div>
                <div class="config-item">
                    <strong>最大尺寸：</strong>
                    <?php echo AVATAR_MAX_WIDTH; ?> × <?php echo AVATAR_MAX_HEIGHT; ?> px
                </div>
                <div class="config-item">
                    <strong>压缩质量：</strong>
                    <?php echo AVATAR_QUALITY; ?>%
                </div>
                <div class="config-item">
                    <strong>WebP支持：</strong>
                    <span class="<?php echo WEBP_ENABLED ? 'status-enabled' : 'status-disabled'; ?>">
                        <?php echo WEBP_ENABLED ? '已启用' : '已禁用'; ?>
                    </span>
                </div>
                <div class="config-item">
                    <strong>缩略图生成：</strong>
                    <span class="<?php echo THUMBNAIL_ENABLED ? 'status-enabled' : 'status-disabled'; ?>">
                        <?php echo THUMBNAIL_ENABLED ? '已启用' : '已禁用'; ?>
                    </span>
                </div>
                <div class="config-item">
                    <strong>最大文件大小：</strong>
                    <?php echo formatBytes(MAX_FILE_SIZE); ?>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 10px 0;
    color: #666;
    font-size: 14px;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.config-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.status-enabled {
    color: #28a745;
    font-weight: bold;
}

.status-disabled {
    color: #dc3545;
    font-weight: bold;
}

.help-text {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
</style>

</body>
</html>
