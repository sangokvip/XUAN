<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查管理员权限
requireAdminLogin();

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();

$success = '';
$errors = [];

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adjust_balance') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $userType = $_POST['user_type'] ?? 'user';
        $amount = (int)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (!$userId) {
            $errors[] = '请选择用户';
        } elseif ($amount == 0) {
            $errors[] = '调整金额不能为0';
        } elseif (empty($description)) {
            $errors[] = '请输入调整说明';
        } else {
            try {
                $tataCoinManager->adminAdjust($userId, $userType, $amount, $description);
                $success = '用户余额调整成功！';
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($action === 'update_settings') {
        $newUserCoin = (int)($_POST['new_user_tata_coin'] ?? 100);
        $featuredCost = (int)($_POST['featured_reader_cost'] ?? 30);
        $normalCost = (int)($_POST['normal_reader_cost'] ?? 10);
        $commissionRate = (int)($_POST['reader_commission_rate'] ?? 50);
        $invitationCommissionRate = (float)($_POST['invitation_commission_rate'] ?? 5);
        $readerInvitationCommissionRate = (float)($_POST['reader_invitation_commission_rate'] ?? 20);

        if ($newUserCoin < 0 || $featuredCost < 0 || $normalCost < 0 || $commissionRate < 0 || $commissionRate > 100 || $invitationCommissionRate < 0 || $invitationCommissionRate > 100 || $readerInvitationCommissionRate < 0 || $readerInvitationCommissionRate > 100) {
            $errors[] = '设置值无效';
        } else {
            try {
                $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'new_user_tata_coin'", [$newUserCoin]);
                $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'featured_reader_cost'", [$featuredCost]);
                $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'normal_reader_cost'", [$normalCost]);
                $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'reader_commission_rate'", [$commissionRate]);

                // 更新或插入邀请返点设置
                $existingInvitationSetting = $db->fetchOne("SELECT * FROM settings WHERE setting_key = 'invitation_commission_rate'");
                if ($existingInvitationSetting) {
                    $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'invitation_commission_rate'", [$invitationCommissionRate]);
                } else {
                    $db->query("INSERT INTO settings (setting_key, setting_value, description) VALUES ('invitation_commission_rate', ?, '邀请返点比例（百分比）')", [$invitationCommissionRate]);
                }

                // 更新或插入塔罗师邀请返点设置
                $existingReaderInvitationSetting = $db->fetchOne("SELECT * FROM settings WHERE setting_key = 'reader_invitation_commission_rate'");
                if ($existingReaderInvitationSetting) {
                    $db->query("UPDATE settings SET setting_value = ? WHERE setting_key = 'reader_invitation_commission_rate'", [$readerInvitationCommissionRate]);
                } else {
                    $db->query("INSERT INTO settings (setting_key, setting_value, description) VALUES ('reader_invitation_commission_rate', ?, '塔罗师邀请塔罗师返点比例（百分比）')", [$readerInvitationCommissionRate]);
                }

                $success = 'Tata Coin设置更新成功！';

                // 调试：记录更新的值
                if (isset($_GET['debug'])) {
                    $success .= " [调试] 邀请返点: {$invitationCommissionRate}, 塔罗师邀请返点: {$readerInvitationCommissionRate}";
                }
            } catch (Exception $e) {
                $errors[] = '设置更新失败：' . $e->getMessage();
            }
        }
    }
}

// 获取当前设置
$settings = [];
$settingsData = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('new_user_tata_coin', 'featured_reader_cost', 'normal_reader_cost', 'reader_commission_rate', 'invitation_commission_rate', 'reader_invitation_commission_rate')");
foreach ($settingsData as $setting) {
    // 邀请返点比例可能是小数，其他的是整数
    if (in_array($setting['setting_key'], ['invitation_commission_rate', 'reader_invitation_commission_rate'])) {
        $settings[$setting['setting_key']] = (float)$setting['setting_value'];
    } else {
        $settings[$setting['setting_key']] = (int)$setting['setting_value'];
    }
}

// 获取用户列表（用于余额调整）
$users = $db->fetchAll("SELECT id, username, full_name, tata_coin FROM users ORDER BY full_name");
$readers = $db->fetchAll("SELECT id, username, full_name, tata_coin FROM readers ORDER BY full_name");

// 获取统计数据
$stats = $db->fetchOne("
    SELECT
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM readers) as total_readers,
        (SELECT SUM(tata_coin) FROM users) as total_user_coins,
        (SELECT SUM(tata_coin) FROM readers) as total_reader_coins,
        (SELECT COUNT(*) FROM tata_coin_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_transactions
");

// 获取注册系统赠送统计
$registrationGifts = $db->fetchOne("
    SELECT
        COUNT(*) as gift_count,
        SUM(amount) as total_amount
    FROM tata_coin_transactions
    WHERE description = '新用户注册赠送'
");

// 获取管理员赠送统计
$adminGifts = $db->fetchOne("
    SELECT
        COUNT(*) as gift_count,
        SUM(amount) as total_amount
    FROM tata_coin_transactions
    WHERE transaction_type IN ('admin_add', 'admin_subtract') AND amount > 0
");

// 获取用户Top10
$topUsers = $db->fetchAll("
    SELECT u.id, u.full_name, u.email, u.tata_coin, u.created_at
    FROM users u
    WHERE u.tata_coin > 0
    ORDER BY u.tata_coin DESC
    LIMIT 10
");

// 获取塔罗师Top10
$topReaders = $db->fetchAll("
    SELECT r.id, r.full_name, r.email, r.tata_coin, r.created_at
    FROM readers r
    WHERE r.tata_coin > 0
    ORDER BY r.tata_coin DESC
    LIMIT 10
");

// 获取最近的交易记录
$recentTransactions = $db->fetchAll("
    SELECT t.*, 
           CASE 
               WHEN t.user_type = 'user' THEN u.full_name 
               ELSE r.full_name 
           END as user_name
    FROM tata_coin_transactions t
    LEFT JOIN users u ON t.user_id = u.id AND t.user_type = 'user'
    LEFT JOIN readers r ON t.user_id = r.id AND t.user_type = 'reader'
    ORDER BY t.created_at DESC
    LIMIT 10
");

$pageTitle = 'Tata Coin管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        
        .page-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
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
            border: 1px solid #e5e7eb;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .management-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .management-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #f59e0b;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .transactions-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .amount-positive {
            color: #10b981;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .management-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .management-card {
                padding: 20px;
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
            <h1>💰 Tata Coin管理</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
        <?php endif; ?>


        
        <!-- 统计数据 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">普通用户总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_readers']); ?></div>
                <div class="stat-label">塔罗师总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_user_coins']); ?></div>
                <div class="stat-label">用户总Tata Coin</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_reader_coins']); ?></div>
                <div class="stat-label">塔罗师总Tata Coin</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($registrationGifts['total_amount'] ?? 0); ?></div>
                <div class="stat-label">注册系统赠送总额 (<?php echo number_format($registrationGifts['gift_count'] ?? 0); ?>人)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($adminGifts['total_amount'] ?? 0); ?></div>
                <div class="stat-label">管理员赠送总额</div>
            </div>
        </div>
        
        <!-- 管理功能 -->
        <div class="management-grid">
            <!-- 余额调整 -->
            <div class="management-card">
                <h3 class="card-title">💳 用户余额调整</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="adjust_balance">
                    
                    <div class="form-group">
                        <label for="user_type">用户类型</label>
                        <select id="user_type" name="user_type" required onchange="updateUserList()">
                            <option value="user">普通用户</option>
                            <option value="reader">塔罗师</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">选择用户</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">请选择用户</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" data-balance="<?php echo $user['tata_coin']; ?>">
                                    <?php echo h($user['full_name']); ?> (<?php echo h($user['username']); ?>) - 余额: <?php echo $user['tata_coin']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">调整金额</label>
                        <input type="number" id="amount" name="amount" required placeholder="正数为增加，负数为减少">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">调整说明</label>
                        <textarea id="description" name="description" required placeholder="请输入调整原因"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-primary">💰 调整余额</button>
                </form>
            </div>
            
            <!-- 系统设置 -->
            <div class="management-card">
                <h3 class="card-title">⚙️ Tata Coin设置</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-group">
                        <label for="new_user_tata_coin">新用户赠送Tata Coin</label>
                        <input type="number" id="new_user_tata_coin" name="new_user_tata_coin" 
                               value="<?php echo $settings['new_user_tata_coin'] ?? 100; ?>" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="featured_reader_cost">推荐塔罗师查看费用</label>
                        <input type="number" id="featured_reader_cost" name="featured_reader_cost" 
                               value="<?php echo $settings['featured_reader_cost'] ?? 30; ?>" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="normal_reader_cost">普通塔罗师查看费用</label>
                        <input type="number" id="normal_reader_cost" name="normal_reader_cost" 
                               value="<?php echo $settings['normal_reader_cost'] ?? 10; ?>" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reader_commission_rate">塔罗师分成比例 (%)</label>
                        <input type="number" id="reader_commission_rate" name="reader_commission_rate"
                               value="<?php echo $settings['reader_commission_rate'] ?? 50; ?>" min="0" max="100" required>
                    </div>

                    <div class="form-group">
                        <label for="invitation_commission_rate">邀请返点比例 (%)</label>
                        <input type="number" id="invitation_commission_rate" name="invitation_commission_rate"
                               value="<?php echo $settings['invitation_commission_rate'] ?? 5; ?>" min="0" max="100" step="0.1" required>
                        <small style="color: #6b7280; font-size: 0.9rem;">被邀请人每次消费时，邀请人获得的返点比例</small>
                    </div>

                    <div class="form-group">
                        <label for="reader_invitation_commission_rate">塔罗师邀请返点比例 (%)</label>
                        <input type="number" id="reader_invitation_commission_rate" name="reader_invitation_commission_rate"
                               value="<?php echo $settings['reader_invitation_commission_rate'] ?? 20; ?>" min="0" max="100" step="0.1" required>
                        <small style="color: #6b7280; font-size: 0.9rem;">被邀请塔罗师有收益时，邀请塔罗师获得的返点比例（四舍五入取整数）</small>
                    </div>

                    <button type="submit" class="btn-primary">💾 保存设置</button>
                </form>
            </div>
        </div>
        
        <!-- 最近交易记录 -->
        <div class="management-card">
            <h3 class="card-title">📊 最近交易记录</h3>
            <?php if (empty($recentTransactions)): ?>
                <p style="text-align: center; color: #6b7280; padding: 40px;">暂无交易记录</p>
            <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>描述</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td>
                                    <?php echo h($transaction['user_name']); ?>
                                    <small style="color: #6b7280;">(<?php echo $transaction['user_type'] === 'user' ? '用户' : '塔罗师'; ?>)</small>
                                </td>
                                <td>
                                    <?php
                                    $typeNames = [
                                        'earn' => '收入',
                                        'spend' => '支出',
                                        'admin_add' => '管理员增加',
                                        'admin_subtract' => '管理员减少'
                                    ];
                                    echo $typeNames[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                    ?>
                                </td>
                                <td>
                                    <span class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                    </span>
                                </td>
                                <td><?php echo h($transaction['description']); ?></td>
                                <td><?php echo date('m-d H:i', strtotime($transaction['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="tata_coin_transactions.php" class="btn-primary">查看全部交易记录</a>
                    <a href="tata_coin_users.php" class="btn-primary" style="margin-left: 15px;">用户余额管理</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top10排行榜 -->
        <div class="management-grid">
            <!-- 用户Top10 -->
            <div class="management-card">
                <h3 class="card-title">🏆 用户Tata Coin Top10</h3>
                <?php if (empty($topUsers)): ?>
                    <p style="text-align: center; color: #6b7280; padding: 40px;">暂无数据</p>
                <?php else: ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>用户名</th>
                                <th>邮箱</th>
                                <th>Tata Coin</th>
                                <th>注册时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsers as $index => $user): ?>
                                <tr>
                                    <td>
                                        <span style="font-weight: 600; color: <?php echo $index < 3 ? '#f59e0b' : '#6b7280'; ?>;">
                                            #<?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($user['full_name']); ?></td>
                                    <td><?php echo h($user['email']); ?></td>
                                    <td>
                                        <span class="amount-positive"><?php echo number_format($user['tata_coin']); ?></span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- 塔罗师Top10 -->
            <div class="management-card">
                <h3 class="card-title">🌟 塔罗师Tata Coin Top10</h3>
                <?php if (empty($topReaders)): ?>
                    <p style="text-align: center; color: #6b7280; padding: 40px;">暂无数据</p>
                <?php else: ?>
                    <table class="transactions-table">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>塔罗师名</th>
                                <th>邮箱</th>
                                <th>Tata Coin</th>
                                <th>注册时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topReaders as $index => $reader): ?>
                                <tr>
                                    <td>
                                        <span style="font-weight: 600; color: <?php echo $index < 3 ? '#f59e0b' : '#6b7280'; ?>;">
                                            #<?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($reader['full_name']); ?></td>
                                    <td><?php echo h($reader['email']); ?></td>
                                    <td>
                                        <span class="amount-positive"><?php echo number_format($reader['tata_coin']); ?></span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($reader['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // 用户列表数据
        const usersData = <?php echo json_encode($users); ?>;
        const readersData = <?php echo json_encode($readers); ?>;
        
        function updateUserList() {
            const userType = document.getElementById('user_type').value;
            const userSelect = document.getElementById('user_id');
            const data = userType === 'reader' ? readersData : usersData;
            
            userSelect.innerHTML = '<option value="">请选择用户</option>';
            
            data.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.full_name} (${user.username}) - 余额: ${user.tata_coin}`;
                option.setAttribute('data-balance', user.tata_coin);
                userSelect.appendChild(option);
            });
        }
        
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            updateUserList();
        });
    </script>
        </div>
    </div>

    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
