<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// 筛选参数
$userType = $_GET['user_type'] ?? '';
$transactionType = $_GET['transaction_type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if ($userType) {
    $whereConditions[] = "t.user_type = ?";
    $params[] = $userType;
}

if ($transactionType) {
    $whereConditions[] = "t.transaction_type = ?";
    $params[] = $transactionType;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(t.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(t.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取交易记录
$sql = "
    SELECT t.*, 
           CASE 
               WHEN t.user_type = 'user' THEN u.full_name 
               ELSE r.full_name 
           END as user_name,
           CASE 
               WHEN t.user_type = 'user' THEN u.username 
               ELSE r.username 
           END as username,
           CASE 
               WHEN t.related_user_type = 'user' THEN ru.full_name 
               WHEN t.related_user_type = 'reader' THEN rr.full_name
               ELSE NULL
           END as related_user_name
    FROM tata_coin_transactions t
    LEFT JOIN users u ON t.user_id = u.id AND t.user_type = 'user'
    LEFT JOIN readers r ON t.user_id = r.id AND t.user_type = 'reader'
    LEFT JOIN users ru ON t.related_user_id = ru.id AND t.related_user_type = 'user'
    LEFT JOIN readers rr ON t.related_user_id = rr.id AND t.related_user_type = 'reader'
    {$whereClause}
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$transactions = $db->fetchAll($sql, $params);

// 获取总记录数
$countSql = "SELECT COUNT(*) as count FROM tata_coin_transactions t {$whereClause}";
$countParams = array_slice($params, 0, -2); // 移除limit和offset参数
$totalCount = $db->fetchOne($countSql, $countParams)['count'];
$totalPages = ceil($totalCount / $limit);

// 获取统计数据
$statsSql = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expense,
        COUNT(CASE WHEN transaction_type = 'spend' THEN 1 END) as spend_count,
        COUNT(CASE WHEN transaction_type = 'earn' THEN 1 END) as earn_count
    FROM tata_coin_transactions t {$whereClause}
";
$stats = $db->fetchOne($statsSql, $countParams);

$pageTitle = 'Tata Coin交易记录';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .transactions-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .filters-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            box-sizing: border-box;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.8rem;
        }
        
        .transactions-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
        
        .transaction-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .type-earn { background: #d1fae5; color: #065f46; }
        .type-spend { background: #fee2e2; color: #991b1b; }
        .type-admin-add { background: #dbeafe; color: #1e40af; }
        .type-admin-subtract { background: #fef3c7; color: #92400e; }
        
        .amount-positive { color: #10b981; font-weight: 600; }
        .amount-negative { color: #ef4444; font-weight: 600; }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .pagination a {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
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
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table th:nth-child(n+4),
            .table td:nth-child(n+4) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="transactions-container">
        <div class="page-header">
            <h1>📊 Tata Coin交易记录</h1>
            <p>查看和分析所有Tata Coin交易</p>
        </div>
        
        <a href="tata_coin.php" class="btn btn-secondary" style="margin-bottom: 20px;">← 返回Tata Coin管理</a>
        
        <!-- 筛选器 -->
        <div class="filters-card">
            <h3 style="margin-top: 0;">🔍 筛选条件</h3>
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="user_type">用户类型</label>
                        <select id="user_type" name="user_type">
                            <option value="">全部</option>
                            <option value="user" <?php echo $userType === 'user' ? 'selected' : ''; ?>>普通用户</option>
                            <option value="reader" <?php echo $userType === 'reader' ? 'selected' : ''; ?>>塔罗师</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_type">交易类型</label>
                        <select id="transaction_type" name="transaction_type">
                            <option value="">全部</option>
                            <option value="earn" <?php echo $transactionType === 'earn' ? 'selected' : ''; ?>>收入</option>
                            <option value="spend" <?php echo $transactionType === 'spend' ? 'selected' : ''; ?>>支出</option>
                            <option value="admin_add" <?php echo $transactionType === 'admin_add' ? 'selected' : ''; ?>>管理员增加</option>
                            <option value="admin_subtract" <?php echo $transactionType === 'admin_subtract' ? 'selected' : ''; ?>>管理员减少</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">开始日期</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo h($dateFrom); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">结束日期</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo h($dateTo); ?>">
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn btn-primary">🔍 筛选</button>
                    <a href="tata_coin_transactions.php" class="btn btn-secondary">🔄 重置</a>
                </div>
            </form>
        </div>
        
        <!-- 统计数据 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_transactions']); ?></div>
                <div class="stat-label">总交易数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_income']); ?></div>
                <div class="stat-label">总收入</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_expense']); ?></div>
                <div class="stat-label">总支出</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['earn_count']); ?></div>
                <div class="stat-label">收入交易</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['spend_count']); ?></div>
                <div class="stat-label">支出交易</div>
            </div>
        </div>
        
        <!-- 交易记录表格 -->
        <div class="transactions-table">
            <?php if (empty($transactions)): ?>
                <div style="text-align: center; padding: 60px; color: #6b7280;">
                    <div style="font-size: 3rem; margin-bottom: 20px;">📊</div>
                    <h3>暂无交易记录</h3>
                    <p>没有找到符合条件的交易记录</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>余额</th>
                            <th>描述</th>
                            <th>关联用户</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($transaction['user_name']); ?></strong><br>
                                    <small style="color: #6b7280;">
                                        <?php echo h($transaction['username']); ?> 
                                        (<?php echo $transaction['user_type'] === 'user' ? '用户' : '塔罗师'; ?>)
                                    </small>
                                </td>
                                <td>
                                    <span class="transaction-type type-<?php echo str_replace('_', '-', $transaction['transaction_type']); ?>">
                                        <?php
                                        $typeNames = [
                                            'earn' => '收入',
                                            'spend' => '支出',
                                            'admin_add' => '管理员增加',
                                            'admin_subtract' => '管理员减少'
                                        ];
                                        echo $typeNames[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $transaction['amount'] > 0 ? 'amount-positive' : 'amount-negative'; ?>">
                                        <?php echo $transaction['amount'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['amount']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($transaction['balance_after']); ?></td>
                                <td><?php echo h($transaction['description']); ?></td>
                                <td>
                                    <?php if ($transaction['related_user_name']): ?>
                                        <?php echo h($transaction['related_user_name']); ?>
                                        <small style="color: #6b7280;">
                                            (<?php echo $transaction['related_user_type'] === 'user' ? '用户' : '塔罗师'; ?>)
                                        </small>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">← 上一页</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页 →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
