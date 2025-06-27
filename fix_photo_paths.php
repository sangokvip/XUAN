<?php
/**
 * 修复数据库中的照片路径
 * 清理可能存在的../前缀和其他路径问题
 */

require_once 'config/config.php';
require_once 'includes/Database.php';

// 初始化数据库连接
try {
    $db = new Database();
} catch (Exception $e) {
    die("数据库连接失败: " . $e->getMessage());
}

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>修复照片路径</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #d4af37; padding-bottom: 10px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #d4af37; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; border: none; cursor: pointer; }
        .btn:hover { background: #b8941f; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background-color: #f2f2f2; }
        .path-old { color: #dc3545; font-family: monospace; }
        .path-new { color: #28a745; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 修复占卜师照片路径</h1>";

$fixMode = isset($_GET['fix']) && $_GET['fix'] === 'true';

if (!$fixMode) {
    echo "<div class='info'>
            <h3>📋 检查模式</h3>
            <p>当前处于检查模式，不会修改数据库。点击下方按钮执行修复。</p>
          </div>";
} else {
    echo "<div class='warning'>
            <h3>⚠️ 修复模式</h3>
            <p>正在修复数据库中的照片路径...</p>
          </div>";
}

try {
    // 查询所有有照片的占卜师
    $readers = $db->fetchAll("SELECT id, full_name, photo, photo_circle FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '')");
    
    if (empty($readers)) {
        echo "<div class='info'>ℹ️ 没有找到需要检查的占卜师照片记录</div>";
    } else {
        echo "<div class='info'>📊 找到 " . count($readers) . " 个占卜师有照片记录</div>";
        
        $needsFixing = [];
        $fixedCount = 0;
        
        echo "<table>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>照片路径</th>
                    <th>圆形照片路径</th>
                    <th>状态</th>
                </tr>";
        
        foreach ($readers as $reader) {
            $photoFixed = false;
            $circleFixed = false;
            $originalPhoto = $reader['photo'];
            $originalCircle = $reader['photo_circle'];
            $newPhoto = $originalPhoto;
            $newCircle = $originalCircle;
            
            // 检查并修复普通照片路径
            if (!empty($originalPhoto)) {
                $cleanPhoto = $originalPhoto;
                
                // 移除../前缀
                if (strpos($cleanPhoto, '../') === 0) {
                    $cleanPhoto = substr($cleanPhoto, 3);
                    $photoFixed = true;
                }
                
                // 移除开头的斜杠
                $cleanPhoto = ltrim($cleanPhoto, '/');
                
                if ($cleanPhoto !== $originalPhoto) {
                    $newPhoto = $cleanPhoto;
                    $photoFixed = true;
                }
            }
            
            // 检查并修复圆形照片路径
            if (!empty($originalCircle)) {
                $cleanCircle = $originalCircle;
                
                // 移除../前缀
                if (strpos($cleanCircle, '../') === 0) {
                    $cleanCircle = substr($cleanCircle, 3);
                    $circleFixed = true;
                }
                
                // 移除开头的斜杠
                $cleanCircle = ltrim($cleanCircle, '/');
                
                if ($cleanCircle !== $originalCircle) {
                    $newCircle = $cleanCircle;
                    $circleFixed = true;
                }
            }
            
            $needsFix = $photoFixed || $circleFixed;
            
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
                    if ($photoFixed) {
                        $updateData['photo'] = $newPhoto;
                    }
                    if ($circleFixed) {
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
                if ($photoFixed) {
                    echo "<div class='path-old'>旧: $originalPhoto</div>";
                    echo "<div class='path-new'>新: $newPhoto</div>";
                } else {
                    echo "<span style='color: #28a745;'>$originalPhoto</span>";
                }
            } else {
                echo "<span style='color: #6c757d;'>无</span>";
            }
            
            echo "</td><td>";
            
            if (!empty($originalCircle)) {
                if ($circleFixed) {
                    echo "<div class='path-old'>旧: $originalCircle</div>";
                    echo "<div class='path-new'>新: $newCircle</div>";
                } else {
                    echo "<span style='color: #28a745;'>$originalCircle</span>";
                }
            } else {
                echo "<span style='color: #6c757d;'>无</span>";
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
                echo "<div class='success'>
                        <h3>✅ 修复完成！</h3>
                        <p>成功修复了 $fixedCount 个占卜师的照片路径。</p>
                      </div>";
            } else {
                echo "<div class='info'>ℹ️ 没有需要修复的路径。</div>";
            }
        } else {
            $needsFixCount = count($needsFixing);
            if ($needsFixCount > 0) {
                echo "<div class='warning'>
                        <h3>⚠️ 发现问题</h3>
                        <p>找到 $needsFixCount 个占卜师的照片路径需要修复。</p>
                      </div>";
                
                echo "<div style='text-align: center; margin: 20px 0;'>
                        <a href='?fix=true' class='btn btn-danger' onclick='return confirm(\"确定要修复这些路径吗？此操作不可撤销。\")'>
                            🔧 执行修复
                        </a>
                      </div>";
            } else {
                echo "<div class='success'>
                        <h3>✅ 路径正常</h3>
                        <p>所有占卜师的照片路径都是正确的，无需修复。</p>
                      </div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ 数据库操作失败: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
        <h3>🔍 修复说明</h3>
        <ul>
            <li><strong>问题原因</strong>: 后台上传时可能保存了包含../前缀的路径</li>
            <li><strong>修复内容</strong>: 移除路径中的../前缀和多余的斜杠</li>
            <li><strong>影响范围</strong>: 仅修复photo和photo_circle字段的路径格式</li>
            <li><strong>安全性</strong>: 不会删除或移动实际文件，只修正数据库记录</li>
        </ul>
      </div>";

echo "<div style='text-align: center; margin: 20px 0;'>
        <a href='debug_photo_paths.php' class='btn'>📋 查看调试信息</a>
        <a href='index.php' class='btn'>🏠 返回首页</a>
      </div>";

echo "
    </div>
</body>
</html>";
?>
