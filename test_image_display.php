<?php
// 简单的图片显示测试页面
session_start();
require_once 'config/config.php';

// 检查是否有占卜师登录
if (!isset($_SESSION['reader_id'])) {
    die('请先登录占卜师账户');
}

$db = Database::getInstance();
$readerId = $_SESSION['reader_id'];
$reader = $db->fetchOne("SELECT * FROM readers WHERE id = ?", [$readerId]);

if (!$reader) {
    die('找不到占卜师信息');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片显示测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .image-test { max-width: 300px; max-height: 300px; border: 2px solid #ddd; margin: 10px; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        .path-info { font-family: monospace; background: #f8f9fa; padding: 5px; margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖼️ 图片显示测试</h1>
        
        <!-- 个人照片测试 -->
        <div class="test-section">
            <h2>📷 个人照片测试</h2>
            <?php if (!empty($reader['photo'])): ?>
                <?php
                $photoPath = trim($reader['photo']);
                if (!str_starts_with($photoPath, 'uploads/')) {
                    $photoPath = 'uploads/photos/' . basename($photoPath);
                }
                $displayPath = '../' . $photoPath;
                ?>
                <div class="path-info">数据库路径: <?php echo htmlspecialchars($reader['photo']); ?></div>
                <div class="path-info">标准化路径: <?php echo htmlspecialchars($photoPath); ?></div>
                <div class="path-info">显示路径: <?php echo htmlspecialchars($displayPath); ?></div>
                
                <h3>从 reader/ 目录访问（实际使用的路径）：</h3>
                <img src="<?php echo htmlspecialchars($displayPath); ?>" alt="个人照片" class="image-test" 
                     onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                     onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                <div>⏳ 加载中...</div>
                
                <h3>从根目录访问（对比测试）：</h3>
                <img src="<?php echo htmlspecialchars($photoPath); ?>" alt="个人照片" class="image-test"
                     onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                     onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                <div>⏳ 加载中...</div>
            <?php else: ?>
                <div class="error">未设置个人照片</div>
            <?php endif; ?>
        </div>

        <!-- 价格列表测试 -->
        <div class="test-section">
            <h2>💰 价格列表测试</h2>
            <?php if (!empty($reader['price_list_image'])): ?>
                <?php
                $priceListPath = trim($reader['price_list_image']);
                if (!str_starts_with($priceListPath, 'uploads/')) {
                    $priceListPath = 'uploads/price_lists/' . basename($priceListPath);
                }
                $displayPath = '../' . $priceListPath;
                ?>
                <div class="path-info">数据库路径: <?php echo htmlspecialchars($reader['price_list_image']); ?></div>
                <div class="path-info">标准化路径: <?php echo htmlspecialchars($priceListPath); ?></div>
                <div class="path-info">显示路径: <?php echo htmlspecialchars($displayPath); ?></div>
                
                <h3>从 reader/ 目录访问（实际使用的路径）：</h3>
                <img src="<?php echo htmlspecialchars($displayPath); ?>" alt="价格列表" class="image-test"
                     onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                     onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                <div>⏳ 加载中...</div>
                
                <h3>从根目录访问（对比测试）：</h3>
                <img src="<?php echo htmlspecialchars($priceListPath); ?>" alt="价格列表" class="image-test"
                     onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                     onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                <div>⏳ 加载中...</div>
            <?php else: ?>
                <div class="error">未设置价格列表</div>
            <?php endif; ?>
        </div>

        <!-- 证书测试 -->
        <div class="test-section">
            <h2>🏆 证书测试</h2>
            <?php if (!empty($reader['certificates'])): ?>
                <?php
                $certificates = json_decode($reader['certificates'], true) ?: [];
                if (!empty($certificates)):
                    foreach ($certificates as $index => $certificate):
                        $certificatePath = '';
                        if (is_string($certificate)) {
                            $certificatePath = $certificate;
                        } elseif (is_array($certificate) && isset($certificate['file'])) {
                            $certificatePath = $certificate['file'];
                        }
                        
                        if (!empty($certificatePath)):
                            if (!str_starts_with($certificatePath, 'uploads/')) {
                                $certificatePath = 'uploads/certificates/' . basename($certificatePath);
                            }
                            $displayPath = '../' . $certificatePath;
                ?>
                            <h3>证书 #<?php echo $index + 1; ?></h3>
                            <div class="path-info">原始数据: <?php echo htmlspecialchars(is_array($certificate) ? json_encode($certificate) : $certificate); ?></div>
                            <div class="path-info">提取路径: <?php echo htmlspecialchars($certificatePath); ?></div>
                            <div class="path-info">显示路径: <?php echo htmlspecialchars($displayPath); ?></div>
                            
                            <h4>从 reader/ 目录访问（实际使用的路径）：</h4>
                            <img src="<?php echo htmlspecialchars($displayPath); ?>" alt="证书<?php echo $index + 1; ?>" class="image-test"
                                 onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                                 onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                            <div>⏳ 加载中...</div>
                            
                            <h4>从根目录访问（对比测试）：</h4>
                            <img src="<?php echo htmlspecialchars($certificatePath); ?>" alt="证书<?php echo $index + 1; ?>" class="image-test"
                                 onload="this.style.border='2px solid green'; this.nextElementSibling.innerHTML='✅ 加载成功';"
                                 onerror="this.style.border='2px solid red'; this.nextElementSibling.innerHTML='❌ 加载失败';">
                            <div>⏳ 加载中...</div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="error">未设置证书</div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="reader/settings.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">返回设置页面</a>
        </div>
    </div>

    <script>
        // 统计加载结果
        window.addEventListener('load', function() {
            setTimeout(function() {
                const images = document.querySelectorAll('.image-test');
                let successCount = 0;
                let failCount = 0;
                
                images.forEach(img => {
                    if (img.style.borderColor === 'green') {
                        successCount++;
                    } else if (img.style.borderColor === 'red') {
                        failCount++;
                    }
                });
                
                console.log(`图片加载统计: 成功 ${successCount}, 失败 ${failCount}`);
                
                if (successCount > 0) {
                    document.title = `✅ 图片测试 - ${successCount}成功/${failCount}失败`;
                } else {
                    document.title = `❌ 图片测试 - 全部失败`;
                }
            }, 3000); // 等待3秒让图片加载完成
        });
    </script>
</body>
</html>
