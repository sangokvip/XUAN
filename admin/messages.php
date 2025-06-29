<?php
session_start();
require_once '../config/config.php';

// 检查管理员权限
requireAdminLogin('../auth/admin_login.php');

$db = Database::getInstance();
$success = '';
$errors = [];

// 获取当前标签页
$activeTab = $_GET['tab'] ?? 'messages';

// 处理联系留言回复
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_contact'])) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $reply = trim($_POST['admin_reply'] ?? '');

    if (empty($reply)) {
        $errors[] = '回复内容不能为空';
    }

    if (empty($errors) && $messageId > 0) {
        try {
            $stmt = $db->prepare("
                UPDATE contact_messages
                SET admin_reply = ?, status = 'replied', replied_by = ?, replied_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$reply, $_SESSION['admin_id'], $messageId]);

            if ($result) {
                $success = '回复发送成功！';
            } else {
                $errors[] = '回复发送失败，请重试';
            }
        } catch (Exception $e) {
            $errors[] = '回复失败：' . $e->getMessage();
        }
    }
}

// 处理联系留言状态更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact_status'])) {
    $messageId = (int)($_POST['message_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['unread', 'read', 'replied']) && $messageId > 0) {
        try {
            $stmt = $db->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            $result = $stmt->execute([$status, $messageId]);

            if ($result) {
                $success = '状态更新成功！';
            } else {
                $errors[] = '状态更新失败';
            }
        } catch (Exception $e) {
            $errors[] = '状态更新失败：' . $e->getMessage();
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

// 获取联系留言列表
$contactMessages = [];
$contactTotalCount = 0;
$contactTotalPages = 0;

if ($activeTab === 'contact') {
    $contactMessages = $db->fetchAll(
        "SELECT cm.*, a.full_name as replied_by_name
         FROM contact_messages cm
         LEFT JOIN admins a ON cm.replied_by = a.id
         ORDER BY cm.created_at DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    $contactTotalCount = $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages")['count'];
    $contactTotalPages = ceil($contactTotalCount / $limit);
}

// 获取联系留言统计
$contactStats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages")['count'],
    'unread' => $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'")['count'],
    'read' => $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'read'")['count'],
    'replied' => $db->fetchOne("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'replied'")['count']
];

$pageTitle = '消息管理';
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            text-align: center;
        }

        .tab-navigation {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .tab-button {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .tab-button:hover:not(.active) {
            background: #f3f4f6;
            color: #374151;
        }

        .tab-badge {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .tab-button.active .tab-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
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
        }

        /* 联系留言样式 */
        .contact-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #e5e7eb;
        }

        .stat-card.unread {
            border-left-color: #ef4444;
        }

        .stat-card.read {
            border-left-color: #3b82f6;
        }

        .stat-card.replied {
            border-left-color: #10b981;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }

        .contact-messages-list {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .contact-message-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .contact-message-item.status-unread {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }

        .contact-message-item.status-read {
            border-left: 4px solid #3b82f6;
        }

        .contact-message-item.status-replied {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .message-info h4 {
            margin: 0 0 8px 0;
            color: #1f2937;
            font-size: 1.1rem;
        }

        .message-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .message-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.status-unread {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge.status-read {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-badge.status-replied {
            background: #dcfce7;
            color: #16a34a;
        }

        .admin-reply {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .admin-reply h5 {
            margin: 0 0 10px 0;
            color: #1e40af;
        }

        .reply-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .no-messages {
            text-align: center;
            padding: 40px;
            color: #6b7280;
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
            <h1>📢 消息管理</h1>

        <!-- 标签页导航 -->
        <div class="tab-navigation">
            <a href="?tab=messages" class="tab-button <?php echo $activeTab === 'messages' ? 'active' : ''; ?>">
                📢 系统消息
            </a>
            <a href="?tab=contact" class="tab-button <?php echo $activeTab === 'contact' ? 'active' : ''; ?>">
                💌 联系留言
                <?php if ($contactStats['unread'] > 0): ?>
                    <span class="tab-badge"><?php echo $contactStats['unread']; ?></span>
                <?php endif; ?>
            </a>
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
        
        <?php if ($activeTab === 'messages'): ?>
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
        <?php endif; ?>

        <?php if ($activeTab === 'contact'): ?>
        <!-- 联系留言管理 -->
        <div class="contact-messages-section">
            <div class="contact-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $contactStats['total']; ?></div>
                    <div class="stat-label">总留言</div>
                </div>
                <div class="stat-card unread">
                    <div class="stat-number"><?php echo $contactStats['unread']; ?></div>
                    <div class="stat-label">未读</div>
                </div>
                <div class="stat-card read">
                    <div class="stat-number"><?php echo $contactStats['read']; ?></div>
                    <div class="stat-label">已读</div>
                </div>
                <div class="stat-card replied">
                    <div class="stat-number"><?php echo $contactStats['replied']; ?></div>
                    <div class="stat-label">已回复</div>
                </div>
            </div>

            <div class="contact-messages-list">
                <h3>💌 联系留言列表</h3>

                <?php if (empty($contactMessages)): ?>
                    <div class="no-messages">
                        <p>暂无联系留言</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($contactMessages as $message): ?>
                        <div class="contact-message-item status-<?php echo $message['status']; ?>">
                            <div class="message-header">
                                <div class="message-info">
                                    <h4><?php echo h($message['subject']); ?></h4>
                                    <div class="message-meta">
                                        <span class="sender">👤 <?php echo h($message['name']); ?></span>
                                        <span class="email">📧 <?php echo h($message['email']); ?></span>
                                        <span class="time">🕒 <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="message-status">
                                    <span class="status-badge status-<?php echo $message['status']; ?>">
                                        <?php
                                        $statusNames = [
                                            'unread' => '未读',
                                            'read' => '已读',
                                            'replied' => '已回复'
                                        ];
                                        echo $statusNames[$message['status']];
                                        ?>
                                    </span>
                                    <form method="POST" style="display: inline-block; margin-left: 10px;">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="unread" <?php echo $message['status'] === 'unread' ? 'selected' : ''; ?>>未读</option>
                                            <option value="read" <?php echo $message['status'] === 'read' ? 'selected' : ''; ?>>已读</option>
                                            <option value="replied" <?php echo $message['status'] === 'replied' ? 'selected' : ''; ?>>已回复</option>
                                        </select>
                                        <button type="submit" name="update_contact_status" style="display: none;"></button>
                                    </form>
                                </div>
                            </div>

                            <div class="message-content">
                                <p><?php echo nl2br(h($message['message'])); ?></p>
                            </div>

                            <?php if ($message['admin_reply']): ?>
                                <div class="admin-reply">
                                    <h5>管理员回复：</h5>
                                    <p><?php echo nl2br(h($message['admin_reply'])); ?></p>
                                    <small>回复人：<?php echo h($message['replied_by_name'] ?? '未知'); ?> |
                                           回复时间：<?php echo date('Y-m-d H:i', strtotime($message['replied_at'])); ?></small>
                                </div>
                            <?php endif; ?>

                            <?php if ($message['status'] !== 'replied'): ?>
                                <div class="reply-form">
                                    <form method="POST">
                                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                        <div class="form-group">
                                            <label for="reply_<?php echo $message['id']; ?>">回复留言：</label>
                                            <textarea name="admin_reply" id="reply_<?php echo $message['id']; ?>"
                                                      placeholder="输入回复内容..." rows="3"></textarea>
                                        </div>
                                        <button type="submit" name="reply_contact" class="btn-primary">发送回复</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/admin_footer.php'; ?>
</body>
</html>
