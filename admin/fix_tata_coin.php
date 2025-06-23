<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_balances'])) {
    try {
        // 检查是否存在Tata Coin相关表
        $tablesExist = true;
        try {
            $db->fetchOne("SELECT 1 FROM tata_coin_transactions LIMIT 1");
        } catch (Exception $e) {
            $tablesExist = false;
            $errors[] = "Tata Coin系统尚未安装，请先执行数据库更新。";
        }

        if ($tablesExist) {
            $db->beginTransaction();

            // 1. 修复用户余额：将所有用户余额重置为正确值
            // 首先找出所有有重复赠送的用户
            $duplicateUsers = $db->fetchAll("
                SELECT user_id, COUNT(*) as count
                FROM tata_coin_transactions
                WHERE user_type = 'user' AND description = '新用户注册赠送'
                GROUP BY user_id
                HAVING count > 1
            ");

            $fixedUsers = 0;
            foreach ($duplicateUsers as $userInfo) {
                $userId = $userInfo['user_id'];

                // 删除多余的注册赠送记录，只保留第一条
                $transactions = $db->fetchAll("
                    SELECT id FROM tata_coin_transactions
                    WHERE user_id = ? AND user_type = 'user' AND description = '新用户注册赠送'
                    ORDER BY created_at ASC
                ", [$userId]);

                // 删除除第一条外的所有记录
                for ($i = 1; $i < count($transactions); $i++) {
                    $db->query("DELETE FROM tata_coin_transactions WHERE id = ?", [$transactions[$i]['id']]);
                }

                // 重新计算用户余额
                $userTransactions = $db->fetchAll("
                    SELECT amount FROM tata_coin_transactions
                    WHERE user_id = ? AND user_type = 'user'
                    ORDER BY created_at ASC
                ", [$userId]);

                $balance = 0;
                foreach ($userTransactions as $trans) {
                    $balance += $trans['amount'];
                }

                // 更新用户余额
                $db->query("UPDATE users SET tata_coin = ? WHERE id = ?", [$balance, $userId]);

                // 更新所有交易记录的余额
                $runningBalance = 0;
                $allTransactions = $db->fetchAll("
                    SELECT id, amount FROM tata_coin_transactions
                    WHERE user_id = ? AND user_type = 'user'
                    ORDER BY created_at ASC
                ", [$userId]);

                foreach ($allTransactions as $trans) {
                    $runningBalance += $trans['amount'];
                    $db->query("UPDATE tata_coin_transactions SET balance_after = ? WHERE id = ?", [$runningBalance, $trans['id']]);
                }

                $fixedUsers++;
            }

            // 2. 修复字段默认值（如果还是100的话）
            try {
                $columnInfo = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
                if ($columnInfo && isset($columnInfo['Default']) && $columnInfo['Default'] == '100') {
                    $db->query("ALTER TABLE users ALTER COLUMN tata_coin SET DEFAULT 0");
                }
            } catch (Exception $e) {
                // 忽略字段修改错误
            }

            $db->commit();
            $success = "修复完成！共修复了 {$fixedUsers} 个用户的余额问题。";
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        $errors[] = "修复失败：" . $e->getMessage();
    }
}

// 检查当前问题
$problemUsers = [];
try {
    // 检查tata_coin字段是否存在
    $columnExists = $db->fetchOne("SHOW COLUMNS FROM users LIKE 'tata_coin'");
    if ($columnExists) {
        $problemUsers = $db->fetchAll("
            SELECT u.id, u.username, u.full_name, u.tata_coin, COUNT(t.id) as gift_count
            FROM users u
            LEFT JOIN tata_coin_transactions t ON u.id = t.user_id AND t.user_type = 'user' AND t.description = '新用户注册赠送'
            GROUP BY u.id
            HAVING gift_count > 1 OR (gift_count = 1 AND u.tata_coin > 100)
            ORDER BY u.id
        ");
    }
} catch (Exception $e) {
    // 如果表不存在，忽略错误
}

$pageTitle = 'Tata Coin修复工具';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .fix-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
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
        
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        
        .problem-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .warning-box h4 {
            color: #92400e;
            margin: 0 0 10px 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    
    <div class="fix-container">
        <div class="page-header">
            <h1>🔧 Tata Coin修复工具</h1>
            <p>修复用户余额和重复赠送问题</p>
        </div>
        
        <a href="tata_coin.php" class="btn btn-secondary" style="margin-bottom: 20px;">← 返回Tata Coin管理</a>
        
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
        
        <?php if (!empty($problemUsers)): ?>
            <div class="alert alert-warning">
                <strong>⚠️ 发现问题用户</strong><br>
                检测到 <?php echo count($problemUsers); ?> 个用户存在余额异常，可能是重复赠送导致的。
            </div>
            
            <div class="problem-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>用户ID</th>
                            <th>用户名</th>
                            <th>姓名</th>
                            <th>当前余额</th>
                            <th>赠送次数</th>
                            <th>问题</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($problemUsers as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo h($user['username']); ?></td>
                                <td><?php echo h($user['full_name']); ?></td>
                                <td><?php echo $user['tata_coin']; ?></td>
                                <td><?php echo $user['gift_count']; ?></td>
                                <td>
                                    <?php if ($user['gift_count'] > 1): ?>
                                        <span style="color: #ef4444;">重复赠送</span>
                                    <?php elseif ($user['tata_coin'] > 100): ?>
                                        <span style="color: #f59e0b;">余额异常</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="warning-box">
                <h4>⚠️ 修复说明</h4>
                <p>此操作将：</p>
                <ul>
                    <li>删除重复的注册赠送记录</li>
                    <li>重新计算所有用户的正确余额</li>
                    <li>修复交易记录中的余额字段</li>
                    <li>将用户表的tata_coin字段默认值改为0</li>
                </ul>
                <p><strong>注意：此操作不可逆，请确保已备份数据库！</strong></p>
            </div>
            
            <form method="POST" onsubmit="return confirm('确定要执行修复操作吗？此操作不可逆！')">
                <button type="submit" name="fix_balances" class="btn btn-danger">
                    🔧 执行修复
                </button>
            </form>
            
        <?php else: ?>
            <div class="alert alert-success">
                <strong>✅ 系统正常</strong><br>
                未发现Tata Coin余额问题，系统运行正常。
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/admin_footer.php'; ?>
</body>
</html>
