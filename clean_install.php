<?php
// 清理安装脚本 - 用于彻底清理所有配置文件和会话
session_start();

// 要清理的文件列表
$filesToRemove = [
    'config/database_config.php',
    'config/site_config.php',
    'config/installed.lock'
];

$removedFiles = [];
$failedFiles = [];

// 清理文件
foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            $removedFiles[] = $file;
        } else {
            $failedFiles[] = $file;
        }
    }
}

// 清理会话
session_destroy();
session_start();

// 设置强制安装标记
$_SESSION['force_install'] = true;
$_SESSION['clean_install'] = true;

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清理安装 - 塔罗师展示平台</title>
    <style>
        body { 
            font-family: 'Microsoft YaHei', Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; 
            padding: 50px; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 600px; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); 
            text-align: center; 
        }
        .btn { 
            background: linear-gradient(135deg, #667eea, #764ba2); 
            color: white; 
            padding: 12px 30px; 
            text-decoration: none; 
            border-radius: 5px; 
            margin: 10px; 
            display: inline-block; 
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success { background: linear-gradient(135deg, #28a745, #20c997); }
        .alert { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 20px 0; 
            border-left: 4px solid #28a745;
        }
        .alert-warning { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            color: #856404; 
            border-left-color: #ffc107;
        }
        .file-list {
            text-align: left;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        h3 { color: #333; margin: 15px 0 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 清理安装完成</h1>
        
        <?php if (!empty($removedFiles)): ?>
            <div class="alert">
                <h3>✓ 成功清理的文件：</h3>
                <div class="file-list">
                    <?php foreach ($removedFiles as $file): ?>
                        <div>• <?php echo htmlspecialchars($file); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($failedFiles)): ?>
            <div class="alert alert-warning">
                <h3>⚠️ 清理失败的文件：</h3>
                <div class="file-list">
                    <?php foreach ($failedFiles as $file): ?>
                        <div>• <?php echo htmlspecialchars($file); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="alert">
            <p><strong>清理完成！</strong></p>
            <p>所有配置文件和会话数据已清理，现在可以开始全新安装。</p>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="install.php?step=1" class="btn btn-success">开始全新安装</a>
            <a href="index.php" class="btn">返回首页</a>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 5px; font-size: 14px; color: #6c757d;">
            <p><strong>提示：</strong>安装完成后请删除此清理脚本文件 (clean_install.php)</p>
        </div>
    </div>
</body>
</html>
