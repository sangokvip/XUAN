<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>塔罗师后台功能测试</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container" style="padding: 40px 20px;">
        <h1>塔罗师后台功能测试</h1>
        
        <?php
        require_once 'config/config.php';
        
        echo "<h2>1. 检查页面文件</h2>";
        $readerPages = [
            'reader/view_records.php' => '查看记录',
            'reader/settings.php' => '账户设置',
            'reader/profile.php' => '个人资料',
            'reader/dashboard.php' => '后台首页'
        ];
        
        foreach ($readerPages as $file => $name) {
            echo "<p>{$name}: " . (file_exists($file) ? '✓ 存在' : '✗ 不存在') . "</p>";
        }
        
        echo "<h2>2. 检查上传目录</h2>";
        $uploadDirs = [
            PHOTO_PATH => '头像目录',
            PRICE_LIST_PATH => '价格列表目录'
        ];
        
        foreach ($uploadDirs as $dir => $name) {
            if (!is_dir($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "<p>{$name}: ✓ 创建成功 ({$dir})</p>";
                } else {
                    echo "<p>{$name}: ✗ 创建失败 ({$dir})</p>";
                }
            } else {
                echo "<p>{$name}: ✓ 已存在 ({$dir})</p>";
            }
            
            echo "<p>{$name}权限: " . (is_writable($dir) ? '可写' : '不可写') . "</p>";
        }
        
        echo "<h2>3. 检查函数</h2>";
        $functions = [
            'requireReaderLogin' => '塔罗师登录检查',
            'uploadFile' => '文件上传',
            'deleteFile' => '文件删除',
            'getReaderById' => '获取塔罗师信息'
        ];
        
        foreach ($functions as $func => $name) {
            echo "<p>{$name}: " . (function_exists($func) ? '✓ 存在' : '✗ 不存在') . "</p>";
        }
        
        echo "<h2>4. 检查数据库表</h2>";
        try {
            $db = Database::getInstance();
            
            // 检查contact_views表
            $viewsCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_views");
            echo "<p>查看记录表: ✓ 存在，共 {$viewsCount['count']} 条记录</p>";
            
            // 检查readers表字段
            $columns = $db->fetchAll("SHOW COLUMNS FROM readers");
            $columnNames = array_column($columns, 'Field');
            
            $requiredColumns = ['photo', 'price_list_image', 'specialties', 'description'];
            foreach ($requiredColumns as $col) {
                echo "<p>readers.{$col}: " . (in_array($col, $columnNames) ? '✓ 存在' : '✗ 不存在') . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>✗ 数据库错误: " . $e->getMessage() . "</p>";
        }
        
        echo "<h2>5. 测试塔罗师登录</h2>";
        session_start();
        if (isset($_SESSION['reader_id'])) {
            echo "<p>✓ 当前已登录塔罗师ID: {$_SESSION['reader_id']}</p>";
            
            try {
                $reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$_SESSION['reader_id']]);
                if ($reader) {
                    echo "<p>✓ 塔罗师信息: {$reader['full_name']} ({$reader['email']})</p>";
                    echo "<p>头像: " . (!empty($reader['photo']) ? '已设置' : '未设置') . "</p>";
                    echo "<p>价格列表: " . (!empty($reader['price_list_image']) ? '已设置' : '未设置') . "</p>";
                } else {
                    echo "<p>✗ 找不到塔罗师信息</p>";
                }
            } catch (Exception $e) {
                echo "<p>✗ 查询错误: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>✗ 未登录塔罗师账户</p>";
            echo "<p><a href='auth/reader_login.php'>点击登录</a></p>";
        }
        
        echo "<h2>6. 测试链接</h2>";
        if (isset($_SESSION['reader_id'])) {
            foreach ($readerPages as $file => $name) {
                if (file_exists($file)) {
                    echo "<p><a href='{$file}' target='_blank'>{$name}</a></p>";
                }
            }
        } else {
            echo "<p>请先登录塔罗师账户后测试</p>";
        }
        
        echo "<h2>7. 价格列表显示测试</h2>";
        if (isset($_SESSION['reader_id']) && isset($reader) && !empty($reader['price_list_image'])) {
            $priceListPath = $reader['price_list_image'];
            echo "<p>价格列表路径: {$priceListPath}</p>";
            echo "<p>文件存在: " . (file_exists($priceListPath) ? '✓ 是' : '✗ 否') . "</p>";
            
            if (file_exists($priceListPath)) {
                echo "<div style='margin: 20px 0; text-align: center;'>";
                echo "<h3>当前价格列表预览</h3>";
                echo "<img src='{$priceListPath}' alt='价格列表' style='max-width: 400px; height: auto; border: 1px solid #ddd; border-radius: 8px;'>";
                echo "</div>";
            }
        } else {
            echo "<p>暂无价格列表或未登录</p>";
        }
        
        echo "<p><small>测试完成后请删除此文件 (test_reader_backend.php)</small></p>";
        ?>
    </div>
</body>
</html>
