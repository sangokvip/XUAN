<?php
/**
 * 简化版照片路径修复脚本
 */

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入必要文件
require_once 'config/config.php';
require_once 'includes/Database.php';
require_once 'includes/functions.php';

// 初始化数据库连接
try {
    $db = new Database();
    echo "✅ 数据库连接成功<br>";
} catch (Exception $e) {
    die("❌ 数据库连接失败: " . $e->getMessage());
}

$fixMode = isset($_GET['fix']) && $_GET['fix'] === 'true';

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>照片路径修复</title>
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
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .path-old { color: #dc3545; font-family: monospace; font-size: 12px; }
        .path-new { color: #28a745; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 照片路径修复工具</h1>";

if (!$fixMode) {
    echo "<div class='info'>📋 检查模式 - 不会修改数据库</div>";
} else {
    echo "<div class='warning'>⚠️ 修复模式 - 正在修改数据库</div>";
}

try {
    // 查询所有有照片的占卜师
    $sql = "SELECT id, full_name, photo, photo_circle FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '')";
    $readers = $db->fetchAll($sql);
    
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
                    <th>照片路径</th>
                    <th>状态</th>
                </tr>";
        
        foreach ($readers as $reader) {
            $needsFix = false;
            $originalPhoto = $reader['photo'];
            $originalCircle = $reader['photo_circle'];
            $newPhoto = $originalPhoto;
            $newCircle = $originalCircle;
            
            // 检查普通照片路径
            if (!empty($originalPhoto)) {
                $cleanPhoto = $originalPhoto;
                
                // 移除../前缀
                if (strpos($cleanPhoto, '../') === 0) {
                    $cleanPhoto = substr($cleanPhoto, 3);
                    $needsFix = true;
                }
                
                // 移除开头的斜杠
                $cleanPhoto = ltrim($cleanPhoto, '/');
                
                if ($cleanPhoto !== $originalPhoto) {
                    $newPhoto = $cleanPhoto;
                    $needsFix = true;
                }
            }
            
            // 检查圆形照片路径
            if (!empty($originalCircle)) {
                $cleanCircle = $originalCircle;
                
                // 移除../前缀
                if (strpos($cleanCircle, '../') === 0) {
                    $cleanCircle = substr($cleanCircle, 3);
                    $needsFix = true;
                }
                
                // 移除开头的斜杠
                $cleanCircle = ltrim($cleanCircle, '/');
                
                if ($cleanCircle !== $originalCircle) {
                    $newCircle = $cleanCircle;
                    $needsFix = true;
                }
            }
            
            if ($needsFix) {
                $needsFixing[] = [
                    'id' => $reader['id'],
                    'name' => $reader['full_name'],
                    'photo_old' => $originalPhoto,
                    'photo_new' => $newPhoto,
                    'circle_old' => $originalCircle,
                    'circle_new' => $newCircle
                ];
                
                // 如果是修复模式，执行数据库更新
                if ($fixMode) {
                    $updateData = [];
                    if (!empty($originalPhoto) && $newPhoto !== $originalPhoto) {
                        $updateData['photo'] = $newPhoto;
                    }
                    if (!empty($originalCircle) && $newCircle !== $originalCircle) {
                        $updateData['photo_circle'] = $newCircle;
                    }
                    
                    if (!empty($updateData)) {
                        $result = $db->update('readers', $updateData, 'id = ?', [$reader['id']]);
                        if ($result) {
                            $fixedCount++;
                        }
                    }
                }
            }
            
            echo "<tr>
                    <td>{$reader['id']}</td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td>";
            
            if (!empty($originalPhoto)) {
                if ($needsFix) {
                    echo "<div class='path-old'>旧: $originalPhoto</div>";
                    echo "<div class='path-new'>新: $newPhoto</div>";
                } else {
                    echo "<span style='color: #28a745;'>$originalPhoto</span>";
                }
            } else {
                echo "<span style='color: #6c757d;'>无照片</span>";
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
            } else {
                echo "<div class='info'>ℹ️ 没有需要修复的路径。</div>";
            }
        } else {
            $needsFixCount = count($needsFixing);
            if ($needsFixCount > 0) {
                echo "<div class='warning'>⚠️ 发现 $needsFixCount 个占卜师的照片路径需要修复。</div>";
                echo "<div style='text-align: center; margin: 20px 0;'>
                        <a href='?fix=true' class='btn btn-danger' onclick='return confirm(\"确定要修复这些路径吗？\")'>
                            🔧 执行修复
                        </a>
                      </div>";
            } else {
                echo "<div class='success'>✅ 所有照片路径都是正确的，无需修复。</div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 操作失败: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='error'>详细错误: " . htmlspecialchars($e->getTraceAsString()) . "</div>";
}

echo "<div style='margin-top: 20px; text-align: center;'>
        <a href='index.php' class='btn'>🏠 返回首页</a>
        <a href='reader/dashboard.php' class='btn'>👤 占卜师后台</a>
      </div>";

echo "
    </div>
</body>
</html>";
?>
