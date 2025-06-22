<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$message = '';
$error = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'clear_all_attempts':
                $result = $db->query("DELETE FROM login_attempts");
                $message = "已清除所有登录尝试记录";
                break;
                
            case 'clear_failed_attempts':
                $result = $db->query("DELETE FROM login_attempts WHERE success = 0");
                $message = "已清除所有失败的登录尝试记录";
                break;
                
            case 'clear_old_attempts':
                $result = $db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $message = "已清除15分钟前的登录尝试记录";
                break;
                
            case 'clear_by_username':
                $username = trim($_POST['username'] ?? '');
                if (!empty($username)) {
                    $result = $db->query("DELETE FROM login_attempts WHERE username = ?", [$username]);
                    $message = "已清除用户 '{$username}' 的登录尝试记录";
                } else {
                    $error = "请输入用户名";
                }
                break;
                
            case 'clear_by_ip':
                $ip = trim($_POST['ip_address'] ?? '');
                if (!empty($ip)) {
                    $result = $db->query("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]);
                    $message = "已清除IP '{$ip}' 的登录尝试记录";
                } else {
                    $error = "请输入IP地址";
                }
                break;
                
            default:
                $error = "无效的操作";
        }
    } catch (Exception $e) {
        $error = "操作失败: " . $e->getMessage();
    }
}

// 获取当前登录尝试统计
try {
    $attemptStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_attempts,
            COUNT(DISTINCT username) as unique_users,
            COUNT(DISTINCT ip_address) as unique_ips,
            COUNT(CASE WHEN attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 END) as recent_attempts,
            COUNT(CASE WHEN success = 0 THEN 1 END) as failed_attempts,
            COUNT(CASE WHEN success = 1 THEN 1 END) as success_attempts
         FROM login_attempts"
    );
    
    $recentAttempts = $db->fetchAll(
        "SELECT username, ip_address, attempted_at, success 
         FROM login_attempts 
         WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
         ORDER BY attempted_at DESC 
         LIMIT 20"
    );
    
    $lockedAccounts = $db->fetchAll(
        "SELECT username, ip_address, COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
         FROM login_attempts 
         WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
         GROUP BY username, ip_address
         HAVING failed_count >= 5
         ORDER BY failed_count DESC"
    );
} catch (Exception $e) {
    $error = "获取统计信息失败: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录安全管理 - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .security-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #d4af37;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .warning-stat .stat-number {
            color: #dc3545;
        }
        
        .success-stat .stat-number {
            color: #28a745;
        }
        
        .action-section {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .quick-action {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .quick-action h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
        }
        
        .quick-action p {
            margin: 0 0 15px 0;
            opacity: 0.9;
        }
        
        .btn-large {
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .table-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-header h3 {
            margin: 0;
            color: #333;
        }
        
        .table-body {
            padding: 20px;
        }
        
        .status-success {
            color: #28a745;
            font-weight: 500;
        }
        
        .status-failed {
            color: #dc3545;
            font-weight: 500;
        }
        
        .locked-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .security-stats {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                align-items: stretch;
            }
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
            <h1>🔐 登录安全管理</h1>
            <p>管理登录尝试记录，解决登录锁定问题</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <strong>错误：</strong><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <strong>成功：</strong><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- 统计信息 -->
            <div class="security-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attemptStats['total_attempts'] ?? 0; ?></div>
                    <div class="stat-label">总登录尝试</div>
                </div>
                <div class="stat-card <?php echo ($attemptStats['recent_attempts'] ?? 0) >= 10 ? 'warning-stat' : ''; ?>">
                    <div class="stat-number"><?php echo $attemptStats['recent_attempts'] ?? 0; ?></div>
                    <div class="stat-label">15分钟内尝试</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attemptStats['failed_attempts'] ?? 0; ?></div>
                    <div class="stat-label">失败尝试</div>
                </div>
                <div class="stat-card success-stat">
                    <div class="stat-number"><?php echo $attemptStats['success_attempts'] ?? 0; ?></div>
                    <div class="stat-label">成功尝试</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attemptStats['unique_users'] ?? 0; ?></div>
                    <div class="stat-label">涉及用户数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attemptStats['unique_ips'] ?? 0; ?></div>
                    <div class="stat-label">涉及IP数</div>
                </div>

            <!-- 快速解决方案 -->
            <div class="quick-action">
                <h3>🚀 快速解决登录锁定问题</h3>
                <p>如果遇到"登录尝试次数过多，请15分钟后再试"的提示，点击下方按钮立即解决</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_failed_attempts">
                    <button type="submit" class="btn btn-primary btn-large" onclick="return confirm('确定要清除所有失败的登录尝试记录吗？这将解除所有登录锁定。')">
                        🔓 立即解除登录锁定
                    </button>
                </form>
            </div>

            <!-- 清理操作 -->
            <div class="action-section">
                <h2>🧹 清理操作</h2>

                <div class="action-buttons" style="margin-bottom: 30px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_all_attempts">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('确定要清除所有登录尝试记录吗？这将删除所有历史记录。')">
                            🗑️ 清除所有记录
                        </button>
                    </form>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_old_attempts">
                        <button type="submit" class="btn btn-secondary">
                            ⏰ 清除15分钟前的记录
                        </button>
                    </form>
                </div>

                <!-- 清理特定IP -->
                <h3>🎯 清理特定IP的记录</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ip_address">IP地址:</label>
                            <input type="text" id="ip_address" name="ip_address" placeholder="例如: 192.168.1.1">
                        </div>
                        <input type="hidden" name="action" value="clear_by_ip">
                        <button type="submit" class="btn btn-primary">清理此IP记录</button>
                    </div>
                </form>

                <!-- 清理特定用户名 -->
                <h3>👤 清理特定用户的记录</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">用户名:</label>
                            <input type="text" id="username" name="username" placeholder="例如: admin">
                        </div>
                        <input type="hidden" name="action" value="clear_by_username">
                        <button type="submit" class="btn btn-primary">清理此用户记录</button>
                    </div>
                </form>
            </div>

            <!-- 被锁定的账户 -->
            <?php if (!empty($lockedAccounts)): ?>
            <div class="locked-warning">
                <h3>🚨 当前被锁定的账户/IP</h3>
                <p>以下账户或IP在15分钟内失败尝试超过5次，可能被锁定：</p>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>被锁定的账户列表</h3>
                </div>
                <div class="table-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>用户名</th>
                                    <th>IP地址</th>
                                    <th>失败次数</th>
                                    <th>最后尝试时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lockedAccounts as $account): ?>
                                    <tr>
                                        <td><?php echo h($account['username']); ?></td>
                                        <td><?php echo h($account['ip_address']); ?></td>
                                        <td><span class="status-failed"><?php echo $account['failed_count']; ?></span></td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($account['last_attempt'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="clear_by_username">
                                                <input type="hidden" name="username" value="<?php echo h($account['username']); ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">解锁用户</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="clear_by_ip">
                                                <input type="hidden" name="ip_address" value="<?php echo h($account['ip_address']); ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">解锁IP</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 最近的登录尝试 -->
            <div class="table-container">
                <div class="table-header">
                    <h3>📋 最近1小时的登录尝试记录</h3>
                </div>
                <div class="table-body">
                    <?php if (empty($recentAttempts)): ?>
                        <p class="no-data">暂无登录尝试记录</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>IP地址</th>
                                        <th>尝试时间</th>
                                        <th>结果</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttempts as $attempt): ?>
                                        <tr>
                                            <td><?php echo h($attempt['username']); ?></td>
                                            <td><?php echo h($attempt['ip_address']); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($attempt['attempted_at'])); ?></td>
                                            <td>
                                                <?php if ($attempt['success']): ?>
                                                    <span class="status-success">✅ 成功</span>
                                                <?php else: ?>
                                                    <span class="status-failed">❌ 失败</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 使用说明 -->
            <div class="action-section">
                <h2>📖 使用说明</h2>
                <div class="info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h3>🚀 快速解决登录锁定</h3>
                        <ul>
                            <li>点击"立即解除登录锁定"按钮</li>
                            <li>清除所有失败的登录尝试记录</li>
                            <li>立即可以重新尝试登录</li>
                        </ul>
                    </div>
                    <div>
                        <h3>🎯 针对性解决</h3>
                        <ul>
                            <li>清理特定IP或用户的记录</li>
                            <li>解锁被锁定的特定账户</li>
                            <li>保留其他正常的登录记录</li>
                        </ul>
                    </div>
                    <div>
                        <h3>📊 监控和分析</h3>
                        <ul>
                            <li>查看登录尝试统计信息</li>
                            <li>监控被锁定的账户</li>
                            <li>分析登录安全状况</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
            </div>
