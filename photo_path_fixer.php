<?php
/**
 * 照片路径修复工具 - 最终版本
 * 使用正确的数据库连接方式
 */

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入配置文件（包含数据库连接）
require_once 'config/config.php';

$fixMode = isset($_GET['fix']) && $_GET['fix'] === 'true';

echo "<!DOCTYPE html>
<html lang='zh-CN'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>照片路径修复工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #d4af37; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 6px; margin: 12px 0; border-left: 4px solid #17a2b8; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 8px 5px; transition: background 0.3s; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #1e7e34; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .path-old { color: #dc3545; font-family: 'Courier New', monospace; font-size: 11px; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        .path-new { color: #28a745; font-family: 'Courier New', monospace; font-size: 11px; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        .status-icon { font-size: 16px; margin-right: 5px; }
        .progress { background: #e9ecef; border-radius: 4px; height: 20px; margin: 10px 0; }
        .progress-bar { background: #28a745; height: 100%; border-radius: 4px; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 照片路径修复工具</h1>";

if (!$fixMode) {
    echo "<div class='info'>
            <span class='status-icon'>📋</span>
            <strong>检查模式</strong> - 扫描需要修复的路径，不会修改数据库
          </div>";
} else {
    echo "<div class='warning'>
            <span class='status-icon'>⚠️</span>
            <strong>修复模式</strong> - 正在修改数据库中的照片路径
          </div>";
}

try {
    // 使用正确的数据库连接方式
    $db = Database::getInstance();
    echo "<div class='success'>
            <span class='status-icon'>✅</span>
            数据库连接成功
          </div>";
    
    // 查询所有有照片的占卜师
    $sql = "SELECT id, full_name, photo, photo_circle FROM readers WHERE (photo IS NOT NULL AND photo != '') OR (photo_circle IS NOT NULL AND photo_circle != '')";
    $readers = $db->fetchAll($sql);
    
    echo "<div class='info'>
            <span class='status-icon'>📊</span>
            找到 <strong>" . count($readers) . "</strong> 个占卜师有照片记录
          </div>";
    
    if (empty($readers)) {
        echo "<div class='warning'>
                <span class='status-icon'>📷</span>
                没有找到需要检查的照片记录
              </div>";
    } else {
        $needsFixing = [];
        $fixedCount = 0;
        $totalCount = count($readers);
        
        echo "<table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>占卜师姓名</th>
                        <th>照片路径状态</th>
                        <th>修复状态</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($readers as $reader) {
            $needsFix = false;
            $updates = [];
            $pathIssues = [];
            
            // 检查普通照片路径
            if (!empty($reader['photo'])) {
                $originalPath = $reader['photo'];
                $cleanPath = $originalPath;
                $hasIssue = false;
                
                // 移除../前缀
                if (strpos($cleanPath, '../') === 0) {
                    $cleanPath = substr($cleanPath, 3);
                    $hasIssue = true;
                }
                
                // 移除开头的斜杠
                $cleanPath = ltrim($cleanPath, '/');
                
                if ($cleanPath !== $originalPath) {
                    $updates['photo'] = $cleanPath;
                    $needsFix = true;
                    $pathIssues[] = [
                        'type' => '普通照片',
                        'old' => $originalPath,
                        'new' => $cleanPath
                    ];
                }
            }
            
            // 检查圆形照片路径
            if (!empty($reader['photo_circle'])) {
                $originalCircle = $reader['photo_circle'];
                $cleanCircle = $originalCircle;
                
                // 移除../前缀
                if (strpos($cleanCircle, '../') === 0) {
                    $cleanCircle = substr($cleanCircle, 3);
                }
                
                // 移除开头的斜杠
                $cleanCircle = ltrim($cleanCircle, '/');
                
                if ($cleanCircle !== $originalCircle) {
                    $updates['photo_circle'] = $cleanCircle;
                    $needsFix = true;
                    $pathIssues[] = [
                        'type' => '圆形照片',
                        'old' => $originalCircle,
                        'new' => $cleanCircle
                    ];
                }
            }
            
            if ($needsFix) {
                $needsFixing[] = [
                    'id' => $reader['id'],
                    'updates' => $updates,
                    'issues' => $pathIssues
                ];
                
                // 如果是修复模式，执行数据库更新
                if ($fixMode && !empty($updates)) {
                    try {
                        $result = $db->update('readers', $updates, 'id = ?', [$reader['id']]);
                        if ($result) {
                            $fixedCount++;
                        }
                    } catch (Exception $e) {
                        echo "<div class='error'>修复ID {$reader['id']} 失败: " . $e->getMessage() . "</div>";
                    }
                }
            }
            
            echo "<tr>
                    <td><strong>{$reader['id']}</strong></td>
                    <td>" . htmlspecialchars($reader['full_name']) . "</td>
                    <td>";
            
            if (!empty($pathIssues)) {
                foreach ($pathIssues as $issue) {
                    echo "<div style='margin: 5px 0;'>
                            <strong>{$issue['type']}:</strong><br>
                            <span class='path-old'>旧: {$issue['old']}</span><br>
                            <span class='path-new'>新: {$issue['new']}</span>
                          </div>";
                }
            } else {
                echo "<span style='color: #28a745;'>
                        <span class='status-icon'>✅</span>
                        路径格式正确
                      </span>";
            }
            
            echo "</td><td>";
            
            if ($needsFix) {
                if ($fixMode) {
                    echo "<span style='color: #28a745;'>
                            <span class='status-icon'>✅</span>
                            已修复
                          </span>";
                } else {
                    echo "<span style='color: #ffc107;'>
                            <span class='status-icon'>⚠️</span>
                            需要修复
                          </span>";
                }
            } else {
                echo "<span style='color: #28a745;'>
                        <span class='status-icon'>✅</span>
                        正常
                      </span>";
            }
            
            echo "</td></tr>";
        }
        
        echo "</tbody></table>";
        
        // 显示修复结果
        if ($fixMode) {
            if ($fixedCount > 0) {
                $percentage = round(($fixedCount / $totalCount) * 100, 1);
                echo "<div class='success'>
                        <span class='status-icon'>🎉</span>
                        <strong>修复完成！</strong> 成功修复了 <strong>$fixedCount</strong> 个占卜师的照片路径。
                      </div>";
                
                echo "<div class='progress'>
                        <div class='progress-bar' style='width: {$percentage}%'></div>
                      </div>";
                
                echo "<div class='info'>
                        <h4>📋 后续步骤：</h4>
                        <ol>
                            <li>测试前台页面照片显示</li>
                            <li>在占卜师后台上传新照片测试</li>
                            <li>检查所有页面的照片显示效果</li>
                        </ol>
                      </div>";
            } else {
                echo "<div class='info'>
                        <span class='status-icon'>ℹ️</span>
                        没有需要修复的路径，所有照片路径都是正确的。
                      </div>";
            }
        } else {
            $needsFixCount = count($needsFixing);
            if ($needsFixCount > 0) {
                echo "<div class='warning'>
                        <span class='status-icon'>⚠️</span>
                        发现 <strong>$needsFixCount</strong> 个占卜师的照片路径需要修复。
                      </div>";
                
                echo "<div style='text-align: center; margin: 25px 0;'>
                        <a href='?fix=true' class='btn btn-danger' onclick='return confirm(\"确定要修复这些路径吗？\\n\\n此操作会直接修改数据库中的照片路径。\\n建议先备份数据库。\")'>
                            <span class='status-icon'>🔧</span>
                            执行修复 ($needsFixCount 个)
                        </a>
                      </div>";
            } else {
                echo "<div class='success'>
                        <span class='status-icon'>🎉</span>
                        <strong>恭喜！</strong> 所有照片路径都是正确的，无需修复。
                      </div>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>
            <span class='status-icon'>❌</span>
            <strong>操作失败:</strong> " . htmlspecialchars($e->getMessage()) . "
          </div>";
    
    if (DEBUG) {
        echo "<div class='error'>
                <strong>详细错误信息:</strong><br>
                <pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;'>" . 
                htmlspecialchars($e->getTraceAsString()) . 
                "</pre>
              </div>";
    }
}

echo "<div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;'>
        <h3>🔗 相关链接</h3>
        <div style='text-align: center;'>
            <a href='index.php' class='btn btn-success'>
                <span class='status-icon'>🏠</span>
                返回首页
            </a>
            <a href='reader/dashboard.php' class='btn'>
                <span class='status-icon'>👤</span>
                占卜师后台
            </a>
            <a href='readers.php' class='btn'>
                <span class='status-icon'>👥</span>
                占卜师列表
            </a>
        </div>
      </div>";

echo "
    </div>
</body>
</html>";
?>
