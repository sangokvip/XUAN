<?php
session_start();
require_once '../config/config.php';
require_once '../includes/ViewCountManager.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$viewCountManager = new ViewCountManager();
$success = '';
$error = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'reset_view_count':
                $readerId = (int)$_POST['reader_id'];
                $newCount = (int)$_POST['new_count'];
                if ($viewCountManager->resetViewCount($readerId, $newCount)) {
                    $success = "塔罗师查看次数重置成功";
                } else {
                    $error = "重置失败，请重试";
                }
                break;
                
            case 'cleanup_logs':
                $daysToKeep = (int)$_POST['days_to_keep'];
                $deletedCount = $viewCountManager->cleanupOldRecords($daysToKeep);
                $success = "清理完成，删除了 {$deletedCount} 条过期记录";
                break;
        }
    }
}

// 获取查看次数排行榜
$allTimeRanking = $viewCountManager->getViewRanking(20, 'all');
$weekRanking = $viewCountManager->getViewRanking(10, 'week');

// 获取所有塔罗师列表（用于重置功能）
$db = Database::getInstance();
$allReaders = $db->fetchAll("SELECT id, full_name, view_count FROM readers WHERE is_active = 1 ORDER BY full_name");
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看次数管理 - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* 查看次数管理页面特定样式 */
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .ranking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .ranking-table th,
        .ranking-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .ranking-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .ranking-table tr:hover {
            background: #f8f9fa;
        }
        
        .featured-badge {
            background: #e91e63;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <div class="admin-sidebar">
            <?php include '../includes/admin_sidebar.php'; ?>
        </div>

        <div class="admin-content">
            <h1>📊 查看次数管理</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- 统计概览 -->
        <div class="section">
            <h2>📈 统计概览</h2>
            <div class="stats-grid">
                <?php
                $totalReaders = count($allReaders);
                $totalViews = array_sum(array_column($allReaders, 'view_count'));
                $avgViews = $totalReaders > 0 ? round($totalViews / $totalReaders, 1) : 0;
                $topReader = !empty($allTimeRanking) ? $allTimeRanking[0] : null;
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalReaders; ?></div>
                    <div class="stat-label">活跃塔罗师</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($totalViews); ?></div>
                    <div class="stat-label">总查看次数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $avgViews; ?></div>
                    <div class="stat-label">平均查看次数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $topReader ? number_format($topReader['view_count']) : 0; ?></div>
                    <div class="stat-label">最高查看次数</div>
                </div>
            </div>
        </div>
        
        <!-- 查看次数排行榜 -->
        <div class="section">
            <h2>🏆 查看次数排行榜</h2>
            
            <h3>总排行榜（前20名）</h3>
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>排名</th>
                        <th>塔罗师</th>
                        <th>类型</th>
                        <th>查看次数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTimeRanking as $index => $reader): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($reader['full_name']); ?></td>
                            <td>
                                <?php if ($reader['is_featured']): ?>
                                    <span class="featured-badge">推荐</span>
                                <?php else: ?>
                                    普通
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($reader['view_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h3 style="margin-top: 30px;">本周排行榜（前10名）</h3>
            <table class="ranking-table">
                <thead>
                    <tr>
                        <th>排名</th>
                        <th>塔罗师</th>
                        <th>类型</th>
                        <th>本周查看</th>
                        <th>总查看</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weekRanking as $index => $reader): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($reader['full_name']); ?></td>
                            <td>
                                <?php if ($reader['is_featured']): ?>
                                    <span class="featured-badge">推荐</span>
                                <?php else: ?>
                                    普通
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($reader['period_views'] ?? 0); ?></td>
                            <td><?php echo number_format($reader['view_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 管理操作 -->
        <div class="section">
            <h2>🔧 管理操作</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <!-- 重置查看次数 -->
                <div>
                    <h3>重置塔罗师查看次数</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_view_count">
                        <div class="form-group">
                            <label>选择塔罗师：</label>
                            <select name="reader_id" required>
                                <option value="">请选择塔罗师</option>
                                <?php foreach ($allReaders as $reader): ?>
                                    <option value="<?php echo $reader['id']; ?>">
                                        <?php echo htmlspecialchars($reader['full_name']); ?> 
                                        (当前: <?php echo number_format($reader['view_count']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>新的查看次数：</label>
                            <input type="number" name="new_count" value="0" min="0" required>
                        </div>
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('确定要重置该塔罗师的查看次数吗？')">
                            重置查看次数
                        </button>
                    </form>
                </div>
                
                <!-- 清理查看记录 -->
                <div>
                    <h3>清理查看记录</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="cleanup_logs">
                        <div class="form-group">
                            <label>保留天数：</label>
                            <select name="days_to_keep" required>
                                <option value="30">30天</option>
                                <option value="60">60天</option>
                                <option value="90" selected>90天</option>
                                <option value="180">180天</option>
                                <option value="365">365天</option>
                            </select>
                        </div>
                        <p style="color: #666; font-size: 14px;">
                            清理超过指定天数的查看记录，不影响塔罗师的总查看次数。
                        </p>
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('确定要清理过期的查看记录吗？')">
                            清理记录
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- 防刷机制说明 -->
        <div class="section">
            <h2>🛡️ 防刷机制说明</h2>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                <h4>当前防刷设置：</h4>
                <ul>
                    <li><strong>冷却时间：</strong>30分钟</li>
                    <li><strong>检测方式：</strong>IP地址 + Session ID + 用户ID（如果已登录）</li>
                    <li><strong>防刷逻辑：</strong>同一访客在30分钟内多次访问同一塔罗师页面，只计算1次查看</li>
                    <li><strong>记录保存：</strong>详细的访问日志，包括IP、User-Agent、时间等</li>
                </ul>
                
                <h4 style="margin-top: 20px;">技术特点：</h4>
                <ul>
                    <li>✅ 防止恶意刷新增加查看次数</li>
                    <li>✅ 支持真实IP检测（CDN环境）</li>
                    <li>✅ 区分登录用户和游客</li>
                    <li>✅ 完整的访问日志记录</li>
                    <li>✅ 自动清理过期记录</li>
                </ul>
            </div>
        </div>
    </div>

    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
