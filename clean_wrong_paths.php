<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清理错误路径</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        .warning { background: #fff3cd; color: #856404; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>清理错误路径工具</h1>
        
        <?php
        require_once 'config/config.php';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'move_files') {
                echo "<h2>移动文件到正确位置</h2>";
                
                try {
                    $db = Database::getInstance();
                    
                    // 检查 reader/uploads 目录
                    $readerUploadsDir = 'reader/uploads/';
                    $correctUploadsDir = 'uploads/';
                    
                    if (is_dir($readerUploadsDir)) {
                        echo "<div class='info'>";
                        echo "<h3>发现 reader/uploads 目录</h3>";
                        
                        // 移动 photos
                        $readerPhotosDir = $readerUploadsDir . 'photos/';
                        $correctPhotosDir = $correctUploadsDir . 'photos/';
                        
                        if (is_dir($readerPhotosDir)) {
                            // 确保目标目录存在
                            if (!is_dir($correctPhotosDir)) {
                                mkdir($correctPhotosDir, 0777, true);
                            }
                            
                            $files = glob($readerPhotosDir . '*');
                            $movedPhotos = 0;
                            
                            foreach ($files as $file) {
                                if (is_file($file)) {
                                    $fileName = basename($file);
                                    $targetFile = $correctPhotosDir . $fileName;
                                    
                                    if (rename($file, $targetFile)) {
                                        echo "<p>✓ 移动照片: {$fileName}</p>";
                                        $movedPhotos++;
                                    } else {
                                        echo "<p>✗ 移动失败: {$fileName}</p>";
                                    }
                                }
                            }
                            
                            echo "<p class='success'>移动了 {$movedPhotos} 个照片文件</p>";
                        }
                        
                        // 移动 price_lists
                        $readerPriceListsDir = $readerUploadsDir . 'price_lists/';
                        $correctPriceListsDir = $correctUploadsDir . 'price_lists/';
                        
                        if (is_dir($readerPriceListsDir)) {
                            // 确保目标目录存在
                            if (!is_dir($correctPriceListsDir)) {
                                mkdir($correctPriceListsDir, 0777, true);
                            }
                            
                            $files = glob($readerPriceListsDir . '*');
                            $movedPriceLists = 0;
                            
                            foreach ($files as $file) {
                                if (is_file($file)) {
                                    $fileName = basename($file);
                                    $targetFile = $correctPriceListsDir . $fileName;
                                    
                                    if (rename($file, $targetFile)) {
                                        echo "<p>✓ 移动价格列表: {$fileName}</p>";
                                        $movedPriceLists++;
                                    } else {
                                        echo "<p>✗ 移动失败: {$fileName}</p>";
                                    }
                                }
                            }
                            
                            echo "<p class='success'>移动了 {$movedPriceLists} 个价格列表文件</p>";
                        }
                        
                        echo "</div>";
                        
                        // 尝试删除空的 reader/uploads 目录
                        if (is_dir($readerPhotosDir) && count(glob($readerPhotosDir . '*')) === 0) {
                            rmdir($readerPhotosDir);
                            echo "<p>删除空目录: {$readerPhotosDir}</p>";
                        }
                        
                        if (is_dir($readerPriceListsDir) && count(glob($readerPriceListsDir . '*')) === 0) {
                            rmdir($readerPriceListsDir);
                            echo "<p>删除空目录: {$readerPriceListsDir}</p>";
                        }
                        
                        if (is_dir($readerUploadsDir) && count(glob($readerUploadsDir . '*')) === 0) {
                            rmdir($readerUploadsDir);
                            echo "<p>删除空目录: {$readerUploadsDir}</p>";
                        }
                        
                    } else {
                        echo "<p class='info'>没有发现 reader/uploads 目录</p>";
                    }
                    
                } catch (Exception $e) {
                    echo "<p class='error'>操作错误: " . $e->getMessage() . "</p>";
                }
            }
            
            elseif ($action === 'check_files') {
                echo "<h2>检查文件分布</h2>";
                
                $directories = [
                    'uploads/photos/' => '正确的照片目录',
                    'uploads/price_lists/' => '正确的价格列表目录',
                    'reader/uploads/photos/' => '错误的照片目录',
                    'reader/uploads/price_lists/' => '错误的价格列表目录'
                ];
                
                foreach ($directories as $dir => $description) {
                    echo "<div class='info'>";
                    echo "<h3>{$description} ({$dir})</h3>";
                    
                    if (is_dir($dir)) {
                        $files = glob($dir . '*');
                        $fileCount = 0;
                        
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                $fileCount++;
                            }
                        }
                        
                        echo "<p>✓ 目录存在，包含 {$fileCount} 个文件</p>";
                        
                        if ($fileCount > 0) {
                            echo "<h4>文件列表:</h4>";
                            echo "<ul>";
                            foreach ($files as $file) {
                                if (is_file($file)) {
                                    $fileName = basename($file);
                                    $fileSize = round(filesize($file) / 1024, 2);
                                    echo "<li>{$fileName} ({$fileSize} KB)</li>";
                                }
                            }
                            echo "</ul>";
                        }
                    } else {
                        echo "<p>✗ 目录不存在</p>";
                    }
                    echo "</div>";
                }
            }
            
            elseif ($action === 'update_database_paths') {
                echo "<h2>更新数据库路径</h2>";
                
                try {
                    $db = Database::getInstance();
                    
                    // 更新照片路径
                    $readers = $db->fetchAll("SELECT id, full_name, photo FROM readers WHERE photo LIKE 'reader/uploads/%'");
                    $updatedPhotos = 0;
                    
                    foreach ($readers as $reader) {
                        $oldPath = $reader['photo'];
                        $newPath = str_replace('reader/uploads/', 'uploads/', $oldPath);
                        
                        if (file_exists($newPath)) {
                            $db->update('readers', ['photo' => $newPath], 'id = ?', [$reader['id']]);
                            echo "<p>✓ 更新 {$reader['full_name']} 的照片路径: {$oldPath} → {$newPath}</p>";
                            $updatedPhotos++;
                        } else {
                            echo "<p>✗ 文件不存在，跳过: {$newPath}</p>";
                        }
                    }
                    
                    // 更新价格列表路径
                    $priceReaders = $db->fetchAll("SELECT id, full_name, price_list_image FROM readers WHERE price_list_image LIKE 'reader/uploads/%'");
                    $updatedPriceLists = 0;
                    
                    foreach ($priceReaders as $reader) {
                        $oldPath = $reader['price_list_image'];
                        $newPath = str_replace('reader/uploads/', 'uploads/', $oldPath);
                        
                        if (file_exists($newPath)) {
                            $db->update('readers', ['price_list_image' => $newPath], 'id = ?', [$reader['id']]);
                            echo "<p>✓ 更新 {$reader['full_name']} 的价格列表路径: {$oldPath} → {$newPath}</p>";
                            $updatedPriceLists++;
                        } else {
                            echo "<p>✗ 文件不存在，跳过: {$newPath}</p>";
                        }
                    }
                    
                    echo "<div class='success'>";
                    echo "<p>更新完成!</p>";
                    echo "<p>更新了 {$updatedPhotos} 个照片路径</p>";
                    echo "<p>更新了 {$updatedPriceLists} 个价格列表路径</p>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<p class='error'>数据库操作错误: " . $e->getMessage() . "</p>";
                }
            }
        }
        ?>
        
        <h2>清理操作</h2>
        
        <!-- 检查文件分布 -->
        <div class="info">
            <h3>1. 检查文件分布</h3>
            <p>查看文件在哪些目录中</p>
            <form method="POST">
                <input type="hidden" name="action" value="check_files">
                <button type="submit" class="btn">检查文件分布</button>
            </form>
        </div>
        
        <!-- 移动文件 -->
        <div class="warning">
            <h3>2. 移动文件到正确位置</h3>
            <p>将 reader/uploads 中的文件移动到 uploads 目录</p>
            <form method="POST">
                <input type="hidden" name="action" value="move_files">
                <button type="submit" class="btn" onclick="return confirm('确定要移动文件吗？')">移动文件</button>
            </form>
        </div>
        
        <!-- 更新数据库路径 -->
        <div class="warning">
            <h3>3. 更新数据库路径</h3>
            <p>更新数据库中的文件路径记录</p>
            <form method="POST">
                <input type="hidden" name="action" value="update_database_paths">
                <button type="submit" class="btn" onclick="return confirm('确定要更新数据库路径吗？')">更新数据库路径</button>
            </form>
        </div>
        
        <div class="info">
            <h3>操作顺序建议</h3>
            <ol>
                <li>先点击"检查文件分布"了解当前状况</li>
                <li>然后点击"移动文件"将文件移动到正确位置</li>
                <li>最后点击"更新数据库路径"修正数据库记录</li>
            </ol>
        </div>
        
        <p><small>清理完成后请删除此文件 (clean_wrong_paths.php)</small></p>
    </div>
</body>
</html>
