<?php
/**
 * 快速照片路径修复
 * 直接在数据库中修复照片路径
 */

// 基本的数据库连接（不依赖其他文件）
$host = 'localhost';
$dbname = 'diviners_pro';  // 请根据实际数据库名修改
$username = 'diviners_pro';  // 请根据实际用户名修改
$password = 'your_password';  // 请根据实际密码修改

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <title>快速照片修复</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn-danger { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 快速照片路径修复</h1>";

$fixMode = isset($_GET['fix']) && $_GET['fix'] === 'true';

if (!$fixMode) {
    echo "<div class='info'>📋 检查模式 - 点击下方按钮执行修复</div>";
} else {
    echo "<div class='warning'>⚠️ 修复模式 - 正在修改数据库</div>";
}

try {
    // 创建PDO连接
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='success'>✅ 数据库连接成功</div>";
    
    // 查询有照片的占卜师
    $sql = "SELECT id, full_name, photo, photo_circle FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '')";
    $stmt = $pdo->query($sql);
    $readers = $stmt->fetchAll();
    
    echo "<div class='info'>📊 找到 " . count($readers) . " 个占卜师有照片记录</div>";
    
    if (empty($readers)) {
        echo "<div class='warning'>没有找到需要检查的照片记录</div>";
    } else {
        $needsFixing = [];
        $fixedCount = 0;
        
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>原始路径</th>
                    <th>修复后路径</th>
                    <th>状态</th>
                </tr>";
        
        foreach ($readers as $reader) {
            $needsFix = false;
            $updates = [];
            
            // 检查普通照片路径
            if (!empty($reader['photo'])) {
                $originalPath = $reader['photo'];
                $cleanPath = $originalPath;
                
                // 移除../前缀
                if (strpos($cleanPath, '../') === 0) {
                    $cleanPath = substr($cleanPath, 3);
                    $needsFix = true;
                }
                
                // 移除开头的斜杠
                $cleanPath = ltrim($cleanPath, '/');
                
                if ($cleanPath !== $originalPath) {
                    $updates['photo'] = $cleanPath;
                    $needsFix = true;
                }
            }
            
            // 检查圆形照片路径
            if (!empty($reader['photo_circle'])) {
                $originalCircle = $reader['photo_circle'];
                $cleanCircle = $originalCircle;
                
                // 移除../前缀
                if (strpos($cleanCircle, '../') === 0) {
                    $cleanCircle = substr($cleanCircle, 3);
                    $needsFix = true;
                }
                
                // 移除开头的斜杠
                $cleanCircle = ltrim($cleanCircle, '/');
                
                if ($cleanCircle !== $originalCircle) {
                    $updates['photo_circle'] = $cleanCircle;
                    $needsFix = true;
                }
            }
            
            if ($needsFix) {
                $needsFixing[] = [
                    'id' => $reader['id'],
                    'updates' => $updates
                ];
                
                // 如果是修复模式，执行数据库更新
                if ($fixMode && !empty($updates)) {
                    $setParts = [];
                    $values = [];
                    
                    foreach ($updates as $field => $value) {
                        $setParts[] = "$field = ?";
                        $values[] = $value;
                    }
                    
                    $values[] = $reader['id'];
                    
                    $updateSql = "UPDATE readers SET " . implode(', ', $setParts) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    
                    if ($updateStmt->execute($values)) {
                        $fixedCount++;
                    }
                }
            }
            
            echo "<tr>
                    <td>{$reader['id']}</td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td style='font-family: monospace; font-size: 12px;'>";
            
            if (!empty($reader['photo'])) {
                echo "照片: " . htmlspecialchars($reader['photo']) . "<br>";
            }
            if (!empty($reader['photo_circle'])) {
                echo "圆形: " . htmlspecialchars($reader['photo_circle']);
            }
            
            echo "</td><td style='font-family: monospace; font-size: 12px;'>";
            
            if ($needsFix) {
                foreach ($updates as $field => $value) {
                    echo "$field: " . htmlspecialchars($value) . "<br>";
                }
            } else {
                echo "<span style='color: #28a745;'>无需修复</span>";
            }
            
            echo "</td><td>";
            
            if ($needsFix) {
                if ($fixMode) {
                    echo "<span style='color: #28a745;'>✅ 已修复</span>";
                } else {
                    echo "<span style='color: #ffc107;'>⚠️ 需要修复</span>";
                }
            } else {
                echo "<span style='color: #28a745;'>✅ 正常</span>";
            }
            
            echo "</td></tr>";
        }
        
        echo "</table>";
        
        if ($fixMode) {
            if ($fixedCount > 0) {
                echo "<div class='success'>✅ 修复完成！成功修复了 $fixedCount 个占卜师的照片路径。</div>";
                echo "<div class='info'>现在可以测试前台页面的照片显示效果了。</div>";
            } else {
                echo "<div class='info'>ℹ️ 没有需要修复的路径。</div>";
            }
        } else {
            $needsFixCount = count($needsFixing);
            if ($needsFixCount > 0) {
                echo "<div class='warning'>⚠️ 发现 $needsFixCount 个占卜师的照片路径需要修复。</div>";
                echo "<div style='text-align: center; margin: 20px 0;'>
                        <a href='?fix=true' class='btn btn-danger' onclick='return confirm(\"确定要修复这些路径吗？此操作会直接修改数据库。\")'>
                            🔧 执行修复
                        </a>
                      </div>";
            } else {
                echo "<div class='success'>✅ 所有照片路径都是正确的，无需修复。</div>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ 数据库操作失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='warning'>
            <h4>请检查数据库配置:</h4>
            <p>请编辑此文件的第7-10行，填入正确的数据库连接信息：</p>
            <ul>
                <li>数据库主机 (通常是 localhost)</li>
                <li>数据库名称</li>
                <li>数据库用户名</li>
                <li>数据库密码</li>
            </ul>
          </div>";
}

echo "<div style='margin-top: 20px;'>
        <h3>📋 使用说明</h3>
        <ol>
            <li><strong>修改数据库配置</strong>: 编辑此文件第7-10行的数据库连接信息</li>
            <li><strong>检查路径</strong>: 首次访问查看需要修复的路径</li>
            <li><strong>执行修复</strong>: 点击修复按钮更新数据库</li>
            <li><strong>测试效果</strong>: 访问前台页面查看照片显示</li>
        </ol>
      </div>";

echo "<div style='text-align: center; margin: 20px 0;'>
        <a href='index.php' class='btn'>🏠 返回首页</a>
        <a href='reader/dashboard.php' class='btn'>👤 占卜师后台</a>
      </div>";

echo "
    </div>
</body>
</html>";
?>
