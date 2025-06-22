<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$error = '';

// 处理设置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'site_url' => trim($_POST['site_url'] ?? ''),
            'max_featured_readers' => (int)($_POST['max_featured_readers'] ?? 6),
            'registration_link_hours' => (int)($_POST['registration_link_hours'] ?? 24),
            'readers_per_page' => (int)($_POST['readers_per_page'] ?? 12),
            'admin_items_per_page' => (int)($_POST['admin_items_per_page'] ?? 20)
        ];
        
        $updated = 0;
        foreach ($settings as $key => $value) {
            if (setSetting($key, $value)) {
                $updated++;
            }
        }
        
        if ($updated > 0) {
            $success = "已更新 {$updated} 项设置";
        } else {
            $error = '设置更新失败';
        }
    }
    
    elseif ($action === 'clear_cache') {
        // 清理缓存
        if (class_exists('SimpleCache')) {
            $cache = new SimpleCache();
            $cache->clear();
            $success = '缓存已清理';
        } else {
            $error = '缓存系统不可用';
        }
    }
    
    elseif ($action === 'cleanup_logs') {
        // 清理日志
        $logDir = '../logs';
        $cleaned = 0;
        
        if (is_dir($logDir)) {
            $files = glob($logDir . '/*.log');
            $cutoffTime = time() - (30 * 24 * 3600); // 30天前
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        $success = "已清理 {$cleaned} 个旧日志文件";
    }
    
    elseif ($action === 'cleanup_login_attempts') {
        // 清理登录尝试记录
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $cleaned = $stmt->rowCount();
        
        $success = "已清理 {$cleaned} 条旧登录记录";
    }
}

// 获取当前设置
$currentSettings = [];
$settings = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settings as $setting) {
    $currentSettings[$setting['setting_key']] = $setting['setting_value'];
}

// 默认值
$defaults = [
    'site_name' => '塔罗师展示平台',
    'site_description' => '专业塔罗师展示平台',
    'site_url' => SITE_URL,
    'max_featured_readers' => '6',
    'registration_link_hours' => '24',
    'readers_per_page' => '12',
    'admin_items_per_page' => '20'
];

// 合并设置
foreach ($defaults as $key => $value) {
    if (!isset($currentSettings[$key])) {
        $currentSettings[$key] = $value;
    }
}

// 获取系统信息
$systemInfo = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $db->fetchOne("SELECT VERSION() as version")['version'] ?? 'Unknown',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

// 获取存储信息
$storageInfo = [];
$uploadDir = '../uploads';
if (is_dir($uploadDir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
    $totalSize = 0;
    $fileCount = 0;
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $totalSize += $file->getSize();
            $fileCount++;
        }
    }
    
    $storageInfo['upload_files'] = $fileCount;
    $storageInfo['upload_size'] = round($totalSize / 1024 / 1024, 2) . ' MB';
} else {
    $storageInfo['upload_files'] = 0;
    $storageInfo['upload_size'] = '0 MB';
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 管理后台</title>
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';">
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
            <h1>系统设置</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo h($success); ?></div>
            <?php endif; ?>
            
            <!-- 基本设置 -->
            <div class="settings-section">
                <h2>基本设置</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="setting-item">
                        <div class="setting-label">网站名称</div>
                        <div class="setting-control">
                            <input type="text" name="site_name" value="<?php echo h($currentSettings['site_name']); ?>" required>
                        </div>
                        <div class="setting-description">显示在网站标题和头部的名称</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">网站描述</div>
                        <div class="setting-control">
                            <input type="text" name="site_description" value="<?php echo h($currentSettings['site_description']); ?>">
                        </div>
                        <div class="setting-description">网站的简短描述</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">网站URL</div>
                        <div class="setting-control">
                            <input type="url" name="site_url" value="<?php echo h($currentSettings['site_url']); ?>" required>
                        </div>
                        <div class="setting-description">网站的完整URL地址</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">推荐塔罗师数量</div>
                        <div class="setting-control">
                            <input type="number" name="max_featured_readers" value="<?php echo h($currentSettings['max_featured_readers']); ?>" min="1" max="20">
                        </div>
                        <div class="setting-description">首页显示的推荐塔罗师数量</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">注册链接有效期</div>
                        <div class="setting-control">
                            <input type="number" name="registration_link_hours" value="<?php echo h($currentSettings['registration_link_hours']); ?>" min="1" max="168">
                        </div>
                        <div class="setting-description">塔罗师注册链接的有效期（小时）</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">每页塔罗师数量</div>
                        <div class="setting-control">
                            <input type="number" name="readers_per_page" value="<?php echo h($currentSettings['readers_per_page']); ?>" min="6" max="50">
                        </div>
                        <div class="setting-description">塔罗师列表页每页显示的数量</div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-label">后台每页条目数</div>
                        <div class="setting-control">
                            <input type="number" name="admin_items_per_page" value="<?php echo h($currentSettings['admin_items_per_page']); ?>" min="10" max="100">
                        </div>
                        <div class="setting-description">管理后台列表页每页显示的条目数</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">保存设置</button>
                </form>
            </div>
            
            <!-- 系统维护 -->
            <div class="settings-section">
                <h2>系统维护</h2>
                
                <div class="setting-item">
                    <div class="setting-label">清理缓存</div>
                    <div class="setting-description">清理所有缓存文件，提高系统性能</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="btn btn-secondary">清理缓存</button>
                        </form>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">清理日志</div>
                    <div class="setting-description">删除30天前的日志文件</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cleanup_logs">
                            <button type="submit" class="btn btn-secondary">清理日志</button>
                        </form>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">清理登录记录</div>
                    <div class="setting-description">删除7天前的登录尝试记录</div>
                    <div class="setting-control">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="cleanup_login_attempts">
                            <button type="submit" class="btn btn-secondary">清理记录</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 系统信息 -->
            <div class="settings-section">
                <h2>系统信息</h2>
                
                <div class="setting-item">
                    <div class="setting-label">PHP版本</div>
                    <div class="setting-description"><?php echo $systemInfo['php_version']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">MySQL版本</div>
                    <div class="setting-description"><?php echo $systemInfo['mysql_version']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">上传限制</div>
                    <div class="setting-description">
                        文件大小: <?php echo $systemInfo['upload_max_filesize']; ?> | 
                        POST大小: <?php echo $systemInfo['post_max_size']; ?>
                    </div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">内存限制</div>
                    <div class="setting-description"><?php echo $systemInfo['memory_limit']; ?></div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">执行时间限制</div>
                    <div class="setting-description"><?php echo $systemInfo['max_execution_time']; ?> 秒</div>
                </div>
                
                <div class="setting-item">
                    <div class="setting-label">上传文件</div>
                    <div class="setting-description">
                        文件数量: <?php echo $storageInfo['upload_files']; ?> | 
                        占用空间: <?php echo $storageInfo['upload_size']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
