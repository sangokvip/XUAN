<?php
session_start();
require_once '../config/config.php';
require_once '../includes/TataCoinManager.php';

// 检查用户登录
requireLogin('../auth/login.php');

$db = Database::getInstance();
$tataCoinManager = new TataCoinManager();

$userId = $_SESSION['user_id'];
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    logout();
    redirect('../auth/login.php');
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 获取交易记录
$transactions = $tataCoinManager->getTransactionHistory($userId, 'user', $limit, $offset);

// 获取总记录数
$totalCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM tata_coin_transactions WHERE user_id = ? AND user_type = 'user'",
    [$userId]
)['count'];

$totalPages = ceil($totalCount / $limit);

// 获取当前余额
$currentBalance = $tataCoinManager->getBalance($userId, 'user');

$pageTitle = 'Tata Coin交易记录';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo getSiteName(); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .transactions-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .transactions-header {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .transactions-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .balance-summary {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .balance-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .balance-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: #374151;
        }
        
        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #f59e0b;
        }
        
        .transactions-list {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .transactions-table th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .transactions-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        
        .transactions-table tr:last-child td {
            border-bottom: none;
        }
        
        .transaction-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .type-earn {
            background: #d1fae5;
            color: #065f46;
        }
        
        .type-spend {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .type-admin-add {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .type-admin-subtract {
            background: #fef3c7;
            color: #92400e;
        }
        
        .amount-positive {
            color: #10b981;
            font-weight: 600;
        }
        
        .amount-negative {
            color: #ef4444;
            font-weight: 600;
        }
        
        .amount-neutral {
            color: #6b7280;
            font-weight: 600;
        }
        
        .transaction-description {
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .transaction-time {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .related-user {
            font-size: 0.85rem;
            color: #6b7280;
            font-style: italic;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .pagination a {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .pagination a:hover {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
        }
        
        .pagination .current {
            background: #f59e0b;
            color: white;
            border: 1px solid #f59e0b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #374151;
        }
        
        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-1px);
            color: white;
        }
        
        @media (max-width: 768px) {
            .transactions-container {
                margin: 20px auto;
                padding: 15px;
            }
            
            .transactions-header {
                padding: 20px 15px;
            }
            
            .balance-summary {
                padding: 20px 15px;
            }
            
            .balance-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .transactions-table {
                font-size: 0.9rem;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 12px 15px;
            }
            
            .transactions-table th:nth-child(3),
            .transactions-table td:nth-child(3),
            .transactions-table th:nth-child(4),
            .transactions-table td:nth-child(4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="transactions-container">
        <div class="transactions-header">
            <h1>💳 Tata Coin交易记录</h1>
            <p>查看您的所有Tata Coin收支明细</p>
        </div>
        
        <a href="index.php" class="btn-back">← 返回用户中心</a>
        
        <div class="balance-summary">
            <div class="balance-info">
                <span class="balance-label">💰 当前余额</span>
                <span class="balance-amount"><?php echo number_format($currentBalance); ?> 枚</span>
            </div>
        </div>
        
        <div class="transactions-list">
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">💸</div>
                    <h3>暂无交易记录</h3>
                    <p>您还没有任何Tata Coin交易记录</p>
                </div>
            <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>交易详情</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>余额</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <div class="transaction-description">
                                        <?php echo h($transaction['description']); ?>
                                    </div>
                                    <?php if ($transaction['related_user_id']): ?>
                                        <div class="related-user">
                                            关联用户ID: <?php echo $transaction['related_user_id']; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="transaction-type type-<?php echo str_replace('_', '-', $transaction['transaction_type']); ?>">
                                        <?php
                                        $typeNames = [
                                            'earn' => '收入',
                                            'spend' => '支出',
                                            'admin_add' => '管理员增加',
                                            'admin_subtract' => '管理员减少',
                                            'transfer' => '转账'
                                        ];
                                        echo $typeNames[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : ($transaction['amount'] < 0 ? 'amount-negative' : 'amount-neutral'); ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo number_format($transaction['balance_after']); ?>
                                </td>
                                <td>
                                    <div class="transaction-time">
                                        <?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">← 上一页</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">下一页 →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
