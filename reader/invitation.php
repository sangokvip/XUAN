<?php
session_start();
require_once '../config/config.php';
require_once '../includes/InvitationManager.php';

// 检查塔罗师登录
requireReaderLogin('../auth/reader_login.php');

$readerId = $_SESSION['reader_id'];
$invitationManager = new InvitationManager();

// 检查邀请系统是否已安装
if (!$invitationManager->isInstalled()) {
    die('邀请系统尚未安装，请联系管理员。');
}

// 获取或生成邀请链接
$invitationToken = $invitationManager->getInvitationLink($readerId, 'reader');
if (!$invitationToken) {
    $invitationToken = $invitationManager->generateInvitationLink($readerId, 'reader');
}

$invitationUrl = SITE_URL . '/auth/register.php?invite=' . $invitationToken;
$readerInvitationUrl = SITE_URL . '/auth/reader_register.php?invite=' . $invitationToken;

// 获取邀请统计
$stats = $invitationManager->getInvitationStats($readerId, 'reader');

// 获取返点记录
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$commissionHistory = $invitationManager->getCommissionHistory($readerId, 'reader', $limit, $offset);

// 获取被邀请用户的详细信息
$invitedUsersDetails = $invitationManager->getInvitedUsersDetails($readerId, 'reader');

$pageTitle = '邀请管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reader.css">
    <style>
        .invitation-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #8b5cf6;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .invitation-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .link-group {
            margin-bottom: 25px;
        }
        
        .link-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            display: block;
        }
        
        .link-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .link-input input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            background: #f9fafb;
        }
        
        .copy-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            background: #7c3aed;
        }
        
        .commission-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .commission-table th,
        .commission-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .commission-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .amount-positive {
            color: #10b981;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .tips-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .tips-title {
            font-weight: 600;
            color: #92400e;
            margin-bottom: 10px;
        }
        
        .tips-list {
            color: #92400e;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .tips-list li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .invitation-container {
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .link-input {
                flex-direction: column;
            }
            
            .link-input input {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="invitation-container">
        <div class="page-header">
            <h1>🎯 邀请管理</h1>
            <p>邀请新用户和塔罗师注册，获得返点奖励</p>
        </div>
        
        <!-- 统计数据 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['invited_users']); ?></div>
                <div class="stat-label">邀请用户数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['invited_readers']); ?></div>
                <div class="stat-label">邀请塔罗师数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_commission']); ?></div>
                <div class="stat-label">累计返点收益</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['monthly_commission']); ?></div>
                <div class="stat-label">本月返点收益</div>
            </div>
        </div>
        
        <!-- 邀请链接 -->
        <div class="invitation-card">
            <h3 class="card-title">🔗 我的邀请链接</h3>
            
            <div class="link-group">
                <label class="link-label">邀请用户注册链接</label>
                <div class="link-input">
                    <input type="text" value="<?php echo h($invitationUrl); ?>" readonly id="userInviteLink">
                    <button class="copy-btn" onclick="copyToClipboard('userInviteLink')">复制链接</button>
                </div>
            </div>
            
            <div class="link-group">
                <label class="link-label">邀请塔罗师注册链接</label>
                <div class="link-input">
                    <input type="text" value="<?php echo h($readerInvitationUrl); ?>" readonly id="readerInviteLink">
                    <button class="copy-btn" onclick="copyToClipboard('readerInviteLink')">复制链接</button>
                </div>
            </div>
        </div>
        
        <!-- 返点说明 -->
        <div class="tips-card">
            <div class="tips-title">💡 返点规则说明</div>
            <ul class="tips-list">
                <li>邀请用户注册：被邀请用户每次消费，您获得 <?php echo $invitationManager->getCommissionRate(); ?>% 返点</li>
                <li>邀请塔罗师注册：被邀请塔罗师每次收益，您获得 <?php echo $invitationManager->getReaderInvitationCommissionRate(); ?>% 返点（四舍五入取整数）</li>
                <li>返点会自动发放到您的Tata Coin账户</li>
                <li>邀请链接永久有效，可重复使用</li>
            </ul>
        </div>
        
        <!-- 被邀请用户详情 -->
        <div class="invitation-card">
            <h3 class="card-title">👥 被邀请用户详情</h3>

            <?php if (empty($invitedUsersDetails['users']) && empty($invitedUsersDetails['readers'])): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <h3>暂无被邀请用户</h3>
                    <p>快去邀请朋友注册吧！</p>
                </div>
            <?php else: ?>
                <?php if (!empty($invitedUsersDetails['users'])): ?>
                    <h4 style="color: #3b82f6; margin-bottom: 15px;">📱 被邀请用户 (<?php echo count($invitedUsersDetails['users']); ?>人)</h4>
                    <table class="commission-table">
                        <thead>
                            <tr>
                                <th>用户名</th>
                                <th>邮箱</th>
                                <th>注册时间</th>
                                <th>消费总额</th>
                                <th>消费次数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitedUsersDetails['users'] as $user): ?>
                                <tr>
                                    <td><?php echo h($user['full_name']); ?></td>
                                    <td><?php echo h($user['email']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <span style="color: #dc3545; font-weight: bold;">
                                            <?php echo number_format($user['total_spent']); ?> 币
                                        </span>
                                    </td>
                                    <td><?php echo $user['transaction_count']; ?> 次</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if (!empty($invitedUsersDetails['readers'])): ?>
                    <h4 style="color: #f59e0b; margin: 25px 0 15px 0;">🔮 被邀请塔罗师 (<?php echo count($invitedUsersDetails['readers']); ?>人)</h4>
                    <table class="commission-table">
                        <thead>
                            <tr>
                                <th>塔罗师名</th>
                                <th>邮箱</th>
                                <th>注册时间</th>
                                <th>收益总额</th>
                                <th>收益次数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invitedUsersDetails['readers'] as $reader): ?>
                                <tr>
                                    <td><?php echo h($reader['full_name']); ?></td>
                                    <td><?php echo h($reader['email']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($reader['created_at'])); ?></td>
                                    <td>
                                        <span style="color: #28a745; font-weight: bold;">
                                            <?php echo number_format($reader['total_earned']); ?> 币
                                        </span>
                                    </td>
                                    <td><?php echo $reader['transaction_count']; ?> 次</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- 返点记录 -->
        <div class="invitation-card">
            <h3 class="card-title">📊 返点记录</h3>
            
            <?php if (empty($commissionHistory)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💰</div>
                    <h3>暂无返点记录</h3>
                    <p>快去邀请朋友注册吧！</p>
                </div>
            <?php else: ?>
                <table class="commission-table">
                    <thead>
                        <tr>
                            <th>被邀请人</th>
                            <th>类型</th>
                            <th>返点金额</th>
                            <th>返点比例</th>
                            <th>原始金额</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commissionHistory as $record): ?>
                            <tr>
                                <td><?php echo h($record['invitee_name']); ?></td>
                                <td>
                                    <span style="color: <?php echo $record['invitee_type'] === 'user' ? '#3b82f6' : '#f59e0b'; ?>;">
                                        <?php echo $record['invitee_type'] === 'user' ? '用户' : '塔罗师'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="amount-positive">+<?php echo number_format($record['commission_amount']); ?></span>
                                </td>
                                <td><?php echo $record['commission_rate']; ?>%</td>
                                <td><?php echo number_format($record['original_amount']); ?></td>
                                <td><?php echo date('m-d H:i', strtotime($record['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                alert('链接已复制到剪贴板！');
            } catch (err) {
                alert('复制失败，请手动复制链接');
            }
        }
    </script>
</body>
</html>
