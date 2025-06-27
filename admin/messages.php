<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 处理在线留言操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            try {
                $db->query("UPDATE contact_messages SET status = 'read' WHERE id = ?", [$messageId]);
                $success = '留言已标记为已读';
            } catch (Exception $e) {
                $errors[] = '操作失败：' . $e->getMessage();
            }
        }
    }

    elseif ($action === 'reply_message') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');

        if ($messageId > 0 && !empty($reply)) {
            try {
                $db->query(
                    "UPDATE contact_messages SET status = 'replied', admin_reply = ?, replied_by = ?, replied_at = NOW() WHERE id = ?",
                    [$reply, $_SESSION['admin_id'], $messageId]
                );
                $success = '回复已保存';
            } catch (Exception $e) {
                $errors[] = '回复失败：' . $e->getMessage();
            }
        } else {
            $errors[] = '请输入回复内容';
        }
    }

    elseif ($action === 'delete_contact_message') {
        $messageId = (int)($_POST['message_id'] ?? 0);
        if ($messageId > 0) {
            try {
                $db->query("DELETE FROM contact_messages WHERE id = ?", [$messageId]);
                $success = '留言已删除';
            } catch (Exception $e) {
                $errors[] = '删除失败：' . $e->getMessage();
            }
        }
    }
}

// 处理发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $targetType = $_POST['target_type'] ?? '';
    
    if (empty($title)) {
        $errors[] = '消息标题不能为空';
    }
    
    if (empty($content)) {
        $errors[] = '消息内容不能为空';
    }
    
    if (!in_array($targetType, ['user', 'reader', 'all'])) {
        $errors[] = '请选择有效的目标类型';
    }
    
    if (empty($errors)) {
        try {
            $messageId = $db->insert('admin_messages', [
                'title' => $title,
                'content' => $content,
                'target_type' => $targetType,
                'created_by' => $_SESSION['admin_id']
            ]);
            
            if ($messageId) {
                $success = '消息发送成功！';
            } else {
                $errors[] = '消息发送失败，请重试';
            }
        } catch (Exception $e) {
            $errors[] = '发送失败：' . $e->getMessage();
        }
    }
}

// 获取消息列表
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$messages = $db->fetchAll(
    "SELECT m.*, 
            (SELECT COUNT(*) FROM message_reads mr WHERE mr.message_id = m.id) as read_count,
            (SELECT COUNT(*) FROM users WHERE 1=1) as total_users,
            (SELECT COUNT(*) FROM readers WHERE 1=1) as total_readers
     FROM admin_messages m 
     ORDER BY m.created_at DESC 
     LIMIT ? OFFSET ?",
    [$limit, $offset]
);

// 计算每条消息的目标用户数和已读率
foreach ($messages as &$message) {
    switch ($message['target_type']) {
        case 'user':
            $message['target_count'] = $message['total_users'];
            break;
        case 'reader':
            $message['target_count'] = $message['total_readers'];
            break;
        case 'all':
            $message['target_count'] = $message['total_users'] + $message['total_readers'];
            break;
    }
    $message['read_rate'] = $message['target_count'] > 0 ? round(($message['read_count'] / $message['target_count']) * 100, 1) : 0;
}

$totalCount = $db->fetchOne("SELECT COUNT(*) as count FROM admin_messages")['count'];
$totalPages = ceil($totalCount / $limit);

// 获取在线留言
$contactPage = max(1, (int)($_GET['contact_page'] ?? 1));
$contactLimit = 10;
$contactOffset = ($contactPage - 1) * $contactLimit;

$contactMessages = $db->fetchAll(
    "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$contactLimit, $contactOffset]
);

$contactTotalCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages")['count'];
$contactTotalPages = ceil($contactTotalCount / $contactLimit);

// 统计在线留言状态
$contactStats = $db->fetchOne("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as `read`,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
    FROM contact_messages
");

$pageTitle = '消息管理';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - 管理后台</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .messages-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .send-message-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }
        
        .messages-list-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
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
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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
        
        .message-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .message-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .message-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }
        
        .message-meta {
            text-align: right;
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .message-content {
            color: #4b5563;
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .message-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f3f4f6;
        }
        
        .target-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .target-user {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .target-reader {
            background: #fef3c7;
            color: #92400e;
        }
        
        .target-all {
            background: #d1fae5;
            color: #065f46;
        }
        
        .read-stats {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .read-rate {
            font-weight: 600;
            color: #059669;
        }
        
        /* 标签页样式 */
        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.3s ease;
            position: relative;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: #f3f4f6;
            color: #374151;
        }

        .unread-badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            margin-left: 8px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 在线留言样式 */
        .contact-message-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .contact-message-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .contact-message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .contact-message-info h4 {
            margin: 0 0 5px 0;
            color: #1f2937;
            font-size: 1.1rem;
        }

        .contact-message-meta {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .contact-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-unread {
            background: #fef3c7;
            color: #92400e;
        }

        .status-read {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-replied {
            background: #d1fae5;
            color: #065f46;
        }

        .contact-message-content {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .contact-message-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-read {
            background: #3b82f6;
            color: white;
        }

        .btn-reply {
            background: #10b981;
            color: white;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .reply-form {
            margin-top: 15px;
            padding: 15px;
            background: #f0f9ff;
            border-radius: 8px;
            display: none;
        }

        .reply-form.active {
            display: block;
        }

        .reply-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            margin-bottom: 10px;
            resize: vertical;
            min-height: 80px;
        }

        .admin-reply {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 8px 8px 0;
        }

        .reply-meta {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .message-meta {
                text-align: left;
                margin-top: 5px;
            }

            .message-stats {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .tab-navigation {
                flex-direction: column;
                gap: 8px;
            }

            .contact-message-header {
                flex-direction: column;
                gap: 10px;
            }

            .contact-message-actions {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>
    
    <div class="messages-container">
        <div class="page-header">
            <h1>📢 消息管理</h1>
            <p>向用户和塔罗师发送通知消息，查看在线留言</p>
        </div>

        <!-- 标签页导航 -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="showTab('admin-messages')">📢 系统消息</button>
            <button class="tab-btn" onclick="showTab('contact-messages')">💌 在线留言
                <?php if ($contactStats['unread'] > 0): ?>
                    <span class="unread-badge"><?php echo $contactStats['unread']; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
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
        
        <!-- 系统消息标签页 -->
        <div id="admin-messages" class="tab-content active">
            <div class="content-grid">
            <!-- 发送消息 -->
            <div class="send-message-card">
                <h3 style="margin-top: 0;">📝 发送新消息</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="title">消息标题 *</label>
                        <input type="text" id="title" name="title" required placeholder="输入消息标题">
                    </div>
                    
                    <div class="form-group">
                        <label for="target_type">发送对象 *</label>
                        <select id="target_type" name="target_type" required>
                            <option value="">请选择</option>
                            <option value="user">普通用户</option>
                            <option value="reader">塔罗师</option>
                            <option value="all">所有人</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">消息内容 *</label>
                        <textarea id="content" name="content" required placeholder="输入消息内容"></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="btn-primary">
                        📤 发送消息
                    </button>
                </form>
            </div>
            
            <!-- 消息列表 -->
            <div class="messages-list-card">
                <h3 style="margin-top: 0;">📋 消息记录</h3>
                
                <?php if (empty($messages)): ?>
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📭</div>
                        <p>暂无消息记录</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <h4 class="message-title"><?php echo h($message['title']); ?></h4>
                                <div class="message-meta">
                                    <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="message-content">
                                <?php echo nl2br(h($message['content'])); ?>
                            </div>
                            
                            <div class="message-stats">
                                <div>
                                    <span class="target-type target-<?php echo $message['target_type']; ?>">
                                        <?php
                                        $targetNames = [
                                            'user' => '普通用户',
                                            'reader' => '塔罗师',
                                            'all' => '所有人'
                                        ];
                                        echo $targetNames[$message['target_type']];
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="read-stats">
                                    已读：<span class="read-rate"><?php echo $message['read_count']; ?>/<?php echo $message['target_count']; ?></span>
                                    (<span class="read-rate"><?php echo $message['read_rate']; ?>%</span>)
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <!-- 在线留言标签页 -->
        <div id="contact-messages" class="tab-content">
            <div style="background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                    <h3 style="margin: 0;">💌 在线留言管理</h3>
                    <div style="display: flex; gap: 20px; font-size: 0.9rem;">
                        <span>总计：<strong><?php echo $contactStats['total']; ?></strong></span>
                        <span style="color: #f59e0b;">未读：<strong><?php echo $contactStats['unread']; ?></strong></span>
                        <span style="color: #3b82f6;">已读：<strong><?php echo $contactStats['read']; ?></strong></span>
                        <span style="color: #10b981;">已回复：<strong><?php echo $contactStats['replied']; ?></strong></span>
                    </div>
                </div>

                <?php if (empty($contactMessages)): ?>
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📭</div>
                        <p>暂无在线留言</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($contactMessages as $msg): ?>
                        <div class="contact-message-item">
                            <div class="contact-message-header">
                                <div class="contact-message-info">
                                    <h4><?php echo h($msg['name']); ?> - <?php echo h($msg['subject']); ?></h4>
                                    <div class="contact-message-meta">
                                        📧 <?php echo h($msg['email']); ?> |
                                        🕒 <?php echo date('Y-m-d H:i:s', strtotime($msg['created_at'])); ?>
                                        <?php if ($msg['ip_address']): ?>
                                            | 🌐 <?php echo h($msg['ip_address']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="contact-status status-<?php echo $msg['status']; ?>">
                                    <?php
                                    $statusNames = [
                                        'unread' => '未读',
                                        'read' => '已读',
                                        'replied' => '已回复'
                                    ];
                                    echo $statusNames[$msg['status']];
                                    ?>
                                </span>
                            </div>

                            <div class="contact-message-content">
                                <strong>留言内容：</strong><br>
                                <?php echo nl2br(h($msg['message'])); ?>
                            </div>

                            <?php if ($msg['admin_reply']): ?>
                                <div class="admin-reply">
                                    <div class="reply-meta">
                                        管理员回复 - <?php echo date('Y-m-d H:i:s', strtotime($msg['replied_at'])); ?>
                                    </div>
                                    <?php echo nl2br(h($msg['admin_reply'])); ?>
                                </div>
                            <?php endif; ?>

                            <div class="contact-message-actions">
                                <?php if ($msg['status'] === 'unread'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                        <button type="submit" class="btn-small btn-read">标记已读</button>
                                    </form>
                                <?php endif; ?>

                                <button type="button" class="btn-small btn-reply" onclick="toggleReplyForm(<?php echo $msg['id']; ?>)">
                                    <?php echo $msg['admin_reply'] ? '修改回复' : '回复'; ?>
                                </button>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条留言吗？')">
                                    <input type="hidden" name="action" value="delete_contact_message">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <button type="submit" class="btn-small btn-delete">删除</button>
                                </form>
                            </div>

                            <div id="reply-form-<?php echo $msg['id']; ?>" class="reply-form">
                                <form method="POST">
                                    <input type="hidden" name="action" value="reply_message">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                    <textarea name="reply" placeholder="输入回复内容..." required><?php echo h($msg['admin_reply'] ?? ''); ?></textarea>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn-small btn-reply">保存回复</button>
                                        <button type="button" class="btn-small" onclick="toggleReplyForm(<?php echo $msg['id']; ?>)" style="background: #6b7280; color: white;">取消</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- 分页 -->
                    <?php if ($contactTotalPages > 1): ?>
                        <div style="text-align: center; margin-top: 30px;">
                            <?php for ($i = 1; $i <= $contactTotalPages; $i++): ?>
                                <a href="?contact_page=<?php echo $i; ?>"
                                   style="display: inline-block; padding: 8px 12px; margin: 0 2px; text-decoration: none; border-radius: 4px; <?php echo $i === $contactPage ? 'background: #667eea; color: white;' : 'background: #f3f4f6; color: #374151;'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            // 隐藏所有标签页内容
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // 移除所有标签按钮的active类
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });

            // 显示选中的标签页内容
            document.getElementById(tabId).classList.add('active');

            // 激活对应的标签按钮
            event.target.classList.add('active');
        }

        function toggleReplyForm(messageId) {
            const form = document.getElementById('reply-form-' + messageId);
            form.classList.toggle('active');
        }
    </script>

    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
