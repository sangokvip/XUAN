<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件上传调试工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .btn { background: #d4af37; color: #000; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 5px; }
        .btn:hover { background: #b8860b; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>文件上传调试工具</h1>
        
        <?php
        require_once 'config/config.php';
        
        echo "<h2>1. PHP上传配置检查</h2>";
        $uploadSettings = [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: '系统默认',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
        
        foreach ($uploadSettings as $setting => $value) {
            $status = ($setting === 'file_uploads') ? ($value ? '启用' : '禁用') : $value;
            echo "<p><strong>{$setting}:</strong> {$status}</p>";
        }
        
        echo "<h2>2. 目录检查和创建</h2>";
        $directories = [
            'uploads/' => '主上传目录',
            PHOTO_PATH => '头像目录',
            PRICE_LIST_PATH => '价格列表目录'
        ];
        
        foreach ($directories as $dir => $name) {
            echo "<h3>{$name} ({$dir})</h3>";
            
            // 检查目录是否存在
            if (!is_dir($dir)) {
                echo "<p class='warning'>目录不存在，尝试创建...</p>";
                if (mkdir($dir, 0755, true)) {
                    echo "<p class='success'>✓ 目录创建成功</p>";
                } else {
                    echo "<p class='error'>✗ 目录创建失败</p>";
                    continue;
                }
            } else {
                echo "<p class='success'>✓ 目录已存在</p>";
            }
            
            // 检查权限
            echo "<p>可读: " . (is_readable($dir) ? '✓' : '✗') . "</p>";
            echo "<p>可写: " . (is_writable($dir) ? '✓' : '✗') . "</p>";
            echo "<p>权限: " . substr(sprintf('%o', fileperms($dir)), -4) . "</p>";
            
            // 检查所有者
            if (function_exists('posix_getpwuid')) {
                $owner = posix_getpwuid(fileowner($dir));
                echo "<p>所有者: " . ($owner['name'] ?? '未知') . "</p>";
            }
            
            // 列出文件
            $files = glob($dir . '*');
            echo "<p>文件数量: " . count($files) . "</p>";
            if (!empty($files)) {
                echo "<p>文件列表:</p><ul>";
                foreach (array_slice($files, 0, 5) as $file) {
                    $size = is_file($file) ? filesize($file) : 0;
                    echo "<li>" . basename($file) . " (" . round($size/1024, 2) . " KB)</li>";
                }
                if (count($files) > 5) {
                    echo "<li>... 还有 " . (count($files) - 5) . " 个文件</li>";
                }
                echo "</ul>";
            }
        }
        
        echo "<h2>3. 上传函数测试</h2>";
        echo "<p>uploadFile函数: " . (function_exists('uploadFile') ? '✓ 存在' : '✗ 不存在') . "</p>";
        
        if (function_exists('uploadFile')) {
            echo "<h3>函数源码检查</h3>";
            $reflection = new ReflectionFunction('uploadFile');
            echo "<p>文件: " . $reflection->getFileName() . "</p>";
            echo "<p>行数: " . $reflection->getStartLine() . " - " . $reflection->getEndLine() . "</p>";
        }
        
        echo "<h2>4. 测试文件上传</h2>";
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
            echo "<h3>上传测试结果</h3>";
            
            echo "<h4>$_FILES 内容:</h4>";
            echo "<pre>" . print_r($_FILES['test_file'], true) . "</pre>";
            
            $file = $_FILES['test_file'];
            
            echo "<h4>上传状态检查:</h4>";
            $uploadErrors = [
                UPLOAD_ERR_OK => '上传成功',
                UPLOAD_ERR_INI_SIZE => '文件大小超过 upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过 MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                UPLOAD_ERR_EXTENSION => 'PHP扩展停止了文件上传'
            ];
            
            $errorCode = $file['error'];
            echo "<p>错误代码: {$errorCode} - " . ($uploadErrors[$errorCode] ?? '未知错误') . "</p>";
            
            if ($errorCode === UPLOAD_ERR_OK) {
                echo "<p class='success'>✓ 文件上传到临时目录成功</p>";
                echo "<p>临时文件: {$file['tmp_name']}</p>";
                echo "<p>文件大小: " . round($file['size']/1024, 2) . " KB</p>";
                echo "<p>文件类型: {$file['type']}</p>";
                
                // 测试 uploadFile 函数
                if (function_exists('uploadFile')) {
                    echo "<h4>uploadFile 函数测试:</h4>";
                    try {
                        $result = uploadFile($file, PHOTO_PATH);
                        echo "<pre>" . print_r($result, true) . "</pre>";
                        
                        if ($result['success']) {
                            $uploadedFile = PHOTO_PATH . $result['filename'];
                            echo "<p class='success'>✓ 文件上传成功: {$uploadedFile}</p>";
                            
                            if (file_exists($uploadedFile)) {
                                echo "<p class='success'>✓ 文件确实存在于服务器</p>";
                                echo "<p>文件大小: " . round(filesize($uploadedFile)/1024, 2) . " KB</p>";
                                
                                // 显示图片预览
                                if (in_array(strtolower(pathinfo($uploadedFile, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
                                    echo "<p>图片预览:</p>";
                                    echo "<img src='{$uploadedFile}' style='max-width: 200px; height: auto; border: 1px solid #ddd;'>";
                                }
                            } else {
                                echo "<p class='error'>✗ 文件不存在于服务器</p>";
                            }
                        } else {
                            echo "<p class='error'>✗ uploadFile 函数返回失败: {$result['message']}</p>";
                        }
                    } catch (Exception $e) {
                        echo "<p class='error'>✗ uploadFile 函数异常: " . $e->getMessage() . "</p>";
                        echo "<pre>" . $e->getTraceAsString() . "</pre>";
                    }
                }
            } else {
                echo "<p class='error'>✗ 文件上传失败</p>";
            }
        }
        ?>
        
        <form method="POST" enctype="multipart/form-data">
            <h3>上传测试文件</h3>
            <p>
                <label for="test_file">选择图片文件:</label><br>
                <input type="file" id="test_file" name="test_file" accept="image/*" required>
            </p>
            <p>
                <button type="submit" class="btn">测试上传</button>
            </p>
        </form>
        
        <?php
        echo "<h2>5. 数据库检查</h2>";
        try {
            $db = Database::getInstance();
            
            // 检查readers表结构
            $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
            echo "<h3>readers表字段:</h3>";
            foreach ($columns as $col) {
                echo "<p>{$col['Field']} - {$col['Type']} - " . ($col['Null'] === 'YES' ? '可空' : '非空') . "</p>";
            }
            
            // 检查当前登录的塔罗师
            session_start();
            if (isset($_SESSION['reader_id'])) {
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$_SESSION['reader_id']]);
                if ($reader) {
                    echo "<h3>当前塔罗师信息:</h3>";
                    echo "<p>ID: {$reader['id']}</p>";
                    echo "<p>姓名: {$reader['full_name']}</p>";
                    echo "<p>头像路径: " . ($reader['photo'] ?: '未设置') . "</p>";
                    echo "<p>价格列表路径: " . ($reader['price_list_image'] ?: '未设置') . "</p>";
                    
                    if ($reader['photo']) {
                        echo "<p>头像文件存在: " . (file_exists($reader['photo']) ? '✓' : '✗') . "</p>";
                    }
                    if ($reader['price_list_image']) {
                        echo "<p>价格列表文件存在: " . (file_exists($reader['price_list_image']) ? '✓' : '✗') . "</p>";
                    }
                }
            } else {
                echo "<p class='warning'>未登录塔罗师账户</p>";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>数据库错误: " . $e->getMessage() . "</p>";
        }
        
        echo "<h2>6. 解决方案</h2>";
        echo "<div class='info'>";
        echo "<h3>如果上传失败，请检查:</h3>";
        echo "<ol>";
        echo "<li>服务器PHP配置是否允许文件上传</li>";
        echo "<li>uploads目录权限是否正确 (建议755或777)</li>";
        echo "<li>文件大小是否超过限制</li>";
        echo "<li>uploadFile函数是否正常工作</li>";
        echo "<li>数据库更新是否成功</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<p><small>调试完成后请删除此文件 (debug_upload.php)</small></p>";
        ?>
    </div>
</body>
</html>
