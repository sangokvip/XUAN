<?php
session_start();
require_once '../config/config.php';
require_once '../includes/MessageManager.php';

// 检查塔罗师登录
requireReaderLogin();

$db = Database::getInstance();
$messageManager = new MessageManager();

$readerId = $_SESSION['reader_id'];
$reader = getReaderById($readerId);

// 检查消息系统是否已安装
if (!$messageManager->isInstalled()) {
    redirect('dashboard.php');
}

// 处理标记为已读
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    if ($messageId) {
        $messageManager->markAsRead($messageId, $readerId, 'reader');
    }
}

// 处理批量标记为已读
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $allMessages = $messageManager->getUserMessages($readerId, 'reader', 1000, 0);
    $messageIds = array_column($allMessages, 'id');
    $messageManager->markMultipleAsRead($messageIds, $readerId, 'reader');
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// 获取消息列表
$messages = $messageManager->getUserMessages($readerId, 'reader', $limit, $offset);

// 自动标记所有未读消息为已读
$unreadMessages = array_filter($messages, function($msg) { return !$msg['is_read']; });
if (!empty($unreadMessages)) {
    $unreadMessageIds = array_column($unreadMessages, 'id');
    $messageManager->markMultipleAsRead($unreadMessageIds, $readerId, 'reader');

    // 重新获取消息列表以显示更新后的状态
    $messages = $messageManager->getUserMessages($readerId, 'reader', $limit, $offset);
}

// 获取未读消息数量
$unreadCount = $messageManager->getUnreadCount($readerId, 'reader');

$pageTitle = '消息通知';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 塔罗师后台</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/reader-new.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .messages-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        
        .messages-header {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #374151;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .messages-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .messages-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .unread-info {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .unread-count {
            color: #ef4444;
            font-weight: 600;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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
        
        .message-item {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .message-item.unread {
            border-left: 4px solid #ef4444;
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .message-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .message-meta {
            text-align: right;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .message-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .status-unread {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-read {
            background: #d1fae5;
            color: #065f46;
        }
        
        .message-content {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .message-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
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
        
        @media (max-width: 768px) {
            .messages-container {
                padding: 15px;
            }
            
            .messages-header {
                padding: 20px 15px;
            }
            
            .messages-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .message-item {
                padding: 20px 15px;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .message-meta {
                text-align: left;
            }
            
            .message-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/reader_header.php'; ?>
    
    <!-- 移动端导航 -->
    <div class="mobile-nav">
        <?php include '../includes/reader_mobile_nav.php'; ?>
    </div>

    <div class="reader-container">
        <div class="reader-sidebar">
            <div class="reader-sidebar-header">
                <h3>占卜师后台</h3>
            </div>
            <div class="reader-sidebar-nav">
                <?php include '../includes/reader_sidebar.php'; ?>
            </div>
        </div>

        <div class="reader-content">
            <div class="reader-content-inner">
            <div class="messages-container">
                <div class="messages-header">
                    <h1>📢 消息通知</h1>
                    <p>查看管理员发送的重要消息</p>
                </div>
                
                <div class="messages-actions">
                    <div class="unread-info">
                        <?php if ($unreadCount > 0): ?>
                            您有 <span class="unread-count"><?php echo $unreadCount; ?></span> 条未读消息
                        <?php else: ?>
                            所有消息已读
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <a href="dashboard.php" class="btn btn-secondary">← 返回后台首页</a>
                        <?php if ($unreadCount > 0): ?>
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="mark_all_read" class="btn btn-primary">
                                    ✓ 全部标记为已读
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>暂无消息</h3>
                        <p>您还没有收到任何消息通知</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?php echo $message['is_read'] ? '' : 'unread'; ?>">
                            <div class="message-header">
                                <h3 class="message-title"><?php echo h($message['title']); ?></h3>
                                <div class="message-meta">
                                    <div class="message-status <?php echo $message['is_read'] ? 'status-read' : 'status-unread'; ?>">
                                        <?php echo $message['is_read'] ? '已读' : '未读'; ?>
                                    </div>
                                    <div><?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></div>
                                    <?php if ($message['is_read'] && $message['read_at']): ?>
                                        <div style="font-size: 0.75rem;">已读于 <?php echo date('m-d H:i', strtotime($message['read_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="message-content">
                                <?php echo nl2br(h($message['content'])); ?>
                            </div>
                            
                            <?php if (!$message['is_read']): ?>
                                <div class="message-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-primary btn-sm">
                                            ✓ 标记为已读
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
    
    <footer class="reader-footer">
        <div class="container">
            <p>&copy; 2024 塔罗师展示平台. 保留所有权利.</p>
        </div>
    </footer>
</body>
</html>
