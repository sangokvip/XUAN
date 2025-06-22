<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器文件检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        .warning { background: #fff3cd; color: #856404; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>服务器文件检查工具</h1>
        
        <?php
        require_once 'config/config.php';
        
        // 检查目录结构
        echo "<h2>1. 目录结构检查</h2>";
        $dirs = [
            'uploads/' => '主上传目录',
            'uploads/photos/' => '头像目录',
            'uploads/price_lists/' => '价格列表目录'
        ];
        
        foreach ($dirs as $dir => $name) {
            echo "<div class='info'>";
            echo "<h3>{$name} ({$dir})</h3>";
            
            if (is_dir($dir)) {
                echo "<p class='success'>✓ 目录存在</p>";
                echo "<p>可读: " . (is_readable($dir) ? '✓' : '✗') . "</p>";
                echo "<p>可写: " . (is_writable($dir) ? '✓' : '✗') . "</p>";
                echo "<p>权限: " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";
                
                // 列出文件
                $files = glob($dir . '*');
                echo "<p>文件数量: " . count($files) . "</p>";
                
                if (!empty($files)) {
                    echo "<h4>文件列表:</h4>";
                    echo "<table>";
                    echo "<tr><th>文件名</th><th>大小</th><th>修改时间</th><th>权限</th></tr>";
                    foreach (array_slice($files, 0, 10) as $file) {
                        if (is_file($file)) {
                            $size = filesize($file);
                            $time = date('Y-m-d H:i:s', filemtime($file));
                            $perms = substr(sprintf('%o', fileperms($file)), -4);
                            echo "<tr>";
                            echo "<td>" . basename($file) . "</td>";
                            echo "<td>" . round($size/1024, 2) . " KB</td>";
                            echo "<td>{$time}</td>";
                            echo "<td>{$perms}</td>";
                            echo "</tr>";
                        }
                    }
                    echo "</table>";
                    
                    if (count($files) > 10) {
                        echo "<p>... 还有 " . (count($files) - 10) . " 个文件</p>";
                    }
                }
            } else {
                echo "<p class='error'>✗ 目录不存在</p>";
                
                // 尝试创建目录
                if (mkdir($dir, 0777, true)) {
                    echo "<p class='success'>✓ 目录创建成功</p>";
                } else {
                    echo "<p class='error'>✗ 目录创建失败</p>";
                }
            }
            echo "</div>";
        }
        
        // 检查数据库中的文件路径
        echo "<h2>2. 数据库文件路径检查</h2>";
        try {
            $db = Database::getInstance();
            
            // 检查塔罗师的照片和价格列表
            $readers = $db->fetchAll("
                SELECT id, full_name, photo, price_list_image 
                FROM readers 
                WHERE photo IS NOT NULL OR price_list_image IS NOT NULL
                ORDER BY id DESC
                LIMIT 20
            ");
            
            if (!empty($readers)) {
                echo "<table>";
                echo "<tr><th>ID</th><th>姓名</th><th>照片路径</th><th>照片存在</th><th>价格列表路径</th><th>价格列表存在</th></tr>";
                
                foreach ($readers as $reader) {
                    echo "<tr>";
                    echo "<td>{$reader['id']}</td>";
                    echo "<td>" . htmlspecialchars($reader['full_name']) . "</td>";
                    
                    // 检查照片
                    if ($reader['photo']) {
                        $photoExists = file_exists($reader['photo']);
                        echo "<td>" . htmlspecialchars($reader['photo']) . "</td>";
                        echo "<td>" . ($photoExists ? '✓' : '✗') . "</td>";
                    } else {
                        echo "<td>-</td><td>-</td>";
                    }
                    
                    // 检查价格列表
                    if ($reader['price_list_image']) {
                        $priceExists = file_exists($reader['price_list_image']);
                        echo "<td>" . htmlspecialchars($reader['price_list_image']) . "</td>";
                        echo "<td>" . ($priceExists ? '✓' : '✗') . "</td>";
                    } else {
                        echo "<td>-</td><td>-</td>";
                    }
                    
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='info'>数据库中没有找到文件记录</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>数据库查询错误: " . $e->getMessage() . "</p>";
        }
        
        // 检查当前登录的塔罗师
        echo "<h2>3. 当前登录塔罗师检查</h2>";
        session_start();
        if (isset($_SESSION['reader_id'])) {
            try {
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$_SESSION['reader_id']]);
                if ($reader) {
                    echo "<div class='info'>";
                    echo "<h3>当前塔罗师信息</h3>";
                    echo "<p>ID: {$reader['id']}</p>";
                    echo "<p>姓名: " . htmlspecialchars($reader['full_name']) . "</p>";
                    echo "<p>照片路径: " . ($reader['photo'] ?: '未设置') . "</p>";
                    echo "<p>价格列表路径: " . ($reader['price_list_image'] ?: '未设置') . "</p>";
                    
                    if ($reader['photo']) {
                        $photoPath = $reader['photo'];
                        echo "<p>照片文件存在: " . (file_exists($photoPath) ? '✓' : '✗') . "</p>";
                        echo "<p>照片完整路径: " . realpath($photoPath ?: '') . "</p>";
                        
                        if (file_exists($photoPath)) {
                            echo "<p>照片大小: " . filesize($photoPath) . " bytes</p>";
                            echo "<p>照片权限: " . substr(sprintf('%o', fileperms($photoPath)), -4) . "</p>";
                            echo "<p>照片预览:</p>";
                            echo "<img src='{$photoPath}' style='max-width: 200px; height: auto; border: 1px solid #ddd;'>";
                        }
                    }
                    
                    if ($reader['price_list_image']) {
                        $pricePath = $reader['price_list_image'];
                        echo "<p>价格列表文件存在: " . (file_exists($pricePath) ? '✓' : '✗') . "</p>";
                        echo "<p>价格列表完整路径: " . realpath($pricePath ?: '') . "</p>";
                        
                        if (file_exists($pricePath)) {
                            echo "<p>价格列表大小: " . filesize($pricePath) . " bytes</p>";
                            echo "<p>价格列表权限: " . substr(sprintf('%o', fileperms($pricePath)), -4) . "</p>";
                            echo "<p>价格列表预览:</p>";
                            echo "<img src='{$pricePath}' style='max-width: 200px; height: auto; border: 1px solid #ddd;'>";
                        }
                    }
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>查询当前塔罗师信息错误: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='warning'>未登录塔罗师账户</p>";
        }
        
        // 系统信息
        echo "<h2>4. 系统信息</h2>";
        echo "<div class='info'>";
        echo "<h3>PHP配置</h3>";
        echo "<p>file_uploads: " . (ini_get('file_uploads') ? '启用' : '禁用') . "</p>";
        echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
        echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
        echo "<p>upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: '系统默认') . "</p>";
        
        echo "<h3>服务器信息</h3>";
        echo "<p>当前工作目录: " . getcwd() . "</p>";
        echo "<p>脚本路径: " . __FILE__ . "</p>";
        echo "<p>服务器时间: " . date('Y-m-d H:i:s') . "</p>";
        echo "<p>磁盘可用空间: " . round(disk_free_space('.') / 1024 / 1024, 2) . " MB</p>";
        echo "</div>";
        
        // 修复建议
        echo "<h2>5. 修复建议</h2>";
        echo "<div class='warning'>";
        echo "<h3>如果文件不显示，请尝试:</h3>";
        echo "<ol>";
        echo "<li>检查uploads目录权限: <code>chmod 777 uploads/ -R</code></li>";
        echo "<li>检查文件是否真的存在于服务器</li>";
        echo "<li>检查文件路径是否正确（相对路径vs绝对路径）</li>";
        echo "<li>检查Web服务器配置是否允许访问uploads目录</li>";
        echo "<li>检查.htaccess文件是否阻止了文件访问</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<p><small>检查完成后请删除此文件 (check_server_files.php)</small></p>";
        ?>
    </div>
</body>
</html>
