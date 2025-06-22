<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
if (!isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_POST['action'] ?? '' === 'clear_cache') {
    try {
        // 清除塔罗师相关缓存
        clearReaderCache();
        
        // 清除其他缓存文件
        $cacheDir = 'cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.cache');
            $deletedCount = 0;
            
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
            
            $message = "成功清除 {$deletedCount} 个缓存文件。推荐塔罗师排序已更新。";
        } else {
            $message = "缓存目录不存在，无需清理。";
        }
        
    } catch (Exception $e) {
        $error = "清除缓存时发生错误: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清除缓存 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .cache-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .cache-info h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .cache-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .clear-button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
        }
        
        .clear-button:hover {
            background: #c82333;
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="admin-container">
        <div class="admin-content">
            <h1>清除缓存</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo h($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo h($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>缓存管理</h2>
                
                <div class="cache-info">
                    <h3>关于缓存清理</h3>
                    <p>当您修改了以下内容时，建议清除缓存以确保更改立即生效：</p>
                    <ul>
                        <li>塔罗师排序规则（如按查看次数排序）</li>
                        <li>推荐塔罗师设置</li>
                        <li>塔罗师信息更新</li>
                        <li>网站设置修改</li>
                    </ul>
                    
                    <p><strong>注意：</strong>清除缓存后，下次访问页面时可能会稍慢，因为需要重新生成缓存。</p>
                </div>
                
                <div class="warning">
                    <strong>⚠️ 重要提醒：</strong>
                    <p>我们刚刚更新了首页推荐塔罗师的排序逻辑，现在按查看次数排序。清除缓存后，首页将立即显示按人气排序的推荐塔罗师。</p>
                </div>
                
                <?php
                // 检查缓存文件数量
                $cacheDir = 'cache';
                $cacheCount = 0;
                if (is_dir($cacheDir)) {
                    $files = glob($cacheDir . '/*.cache');
                    $cacheCount = count($files);
                }
                ?>
                
                <p>当前缓存文件数量: <strong><?php echo $cacheCount; ?></strong> 个</p>
                
                <form method="POST" onsubmit="return confirm('确定要清除所有缓存吗？');">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="clear-button">清除所有缓存</button>
                </form>
                
                <div style="margin-top: 30px;">
                    <h3>缓存清理后的效果</h3>
                    <ul>
                        <li>✅ 首页推荐塔罗师将按查看次数（人气）排序</li>
                        <li>✅ 塔罗师列表页面将显示最新的排序结果</li>
                        <li>✅ 所有数据将实时更新，不受旧缓存影响</li>
                    </ul>
                </div>
            </div>
            
            <div class="admin-actions">
                <a href="readers.php" class="btn btn-secondary">返回塔罗师管理</a>
                <a href="../index.php" class="btn btn-info" target="_blank">查看首页效果</a>
            </div>
        </div>
    </div>
</body>
</html>
