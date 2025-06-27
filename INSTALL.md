# 占卜师展示平台 - 安装指南

## 🚀 快速安装

### 方法一：使用安装向导（推荐）

1. **上传文件**
   - 将所有文件上传到您的Web服务器
   - 确保 `config/`、`uploads/`、`cache/`、`logs/` 目录可写

2. **运行安装向导**
   - 在浏览器中访问：`http://您的域名/install_complete.php`
   - 按照向导步骤完成安装

3. **安全设置**
   - 安装完成后删除 `install_complete.php` 文件
   - 确保敏感目录不能通过Web访问

### 方法二：手动安装

1. **配置数据库**
   - 创建MySQL数据库
   - 导入 `database/complete_schema.sql` 文件

2. **配置文件**
   - 复制 `config/database.php` 为 `config/database_config.php`
   - 修改数据库连接信息：
   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_NAME', '您的数据库名');
   define('DB_USER', '数据库用户名');
   define('DB_PASS', '数据库密码');
   define('DB_CHARSET', 'utf8mb4');
   ```

3. **创建管理员账户**
   - 访问 `admin/create_admin.php` 创建管理员账户
   - 或直接在数据库中插入管理员记录

## 📋 系统要求

### 服务器环境
- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本（推荐 8.0+）
- **Web服务器**: Apache 或 Nginx

### PHP扩展
- PDO
- PDO MySQL
- GD
- JSON
- mbstring
- fileinfo

### 目录权限
以下目录需要可写权限（755 或 777）：
- `config/`
- `uploads/`
- `uploads/photos/`
- `uploads/price_lists/`
- `uploads/certificates/`
- `cache/`
- `logs/`

## 🗄️ 数据库结构

系统包含以下主要功能模块：

### 用户管理系统
- `users` - 普通用户表
- `admins` - 管理员表
- `readers` - 占卜师表
- `reader_registration_links` - 占卜师注册链接

### Tata Coin虚拟货币系统
- `tata_coin_transactions` - 交易记录
- `user_browse_history` - 用户浏览记录
- `contact_views` - 联系方式查看记录

### 评价和问答系统
- `reader_reviews` - 占卜师评价
- `reader_review_likes` - 评价点赞
- `reader_questions` - 问大家功能
- `reader_question_answers` - 问题回答

### 邀请返点系统
- `invitation_links` - 邀请链接
- `invitation_relations` - 邀请关系
- `invitation_commissions` - 返点记录

### 消息通知系统
- `admin_messages` - 管理员消息
- `message_reads` - 消息阅读记录

### 系统管理
- `settings` - 系统设置
- `login_attempts` - 登录尝试记录

## ⚙️ 配置说明

### 基本配置
编辑 `config/config.php` 文件：

```php
// 网站基本信息
define('SITE_URL', 'http://您的域名');
define('SITE_NAME_DEFAULT', '占卜师展示平台');

// 文件上传配置
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_CERTIFICATES', 10); // 最多上传10个证书

// 安全配置
define('PASSWORD_MIN_LENGTH', 6);
define('SESSION_TIMEOUT', 3600); // 1小时

// 分页配置
define('READERS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);
```

### 虚拟货币配置
- 新用户注册默认获得 100 Tata Coin
- 查看推荐占卜师联系方式：30 Tata Coin
- 查看普通占卜师联系方式：10 Tata Coin
- 占卜师获得查看费用的 50%

## 🔧 功能特性

### 前台功能
- 占卜师展示和搜索
- 用户注册和登录
- Tata Coin虚拟货币系统
- 占卜师评价和问答
- 邀请返点系统

### 占卜师后台
- 个人资料管理
- 联系方式设置
- 收入统计
- 邀请链接管理

### 管理员后台
- 用户和占卜师管理
- 系统设置
- 数据统计
- 消息通知
- Tata Coin管理

## 🛡️ 安全建议

1. **文件权限**
   - 确保 PHP 文件不可写
   - 敏感目录添加 `.htaccess` 保护

2. **数据库安全**
   - 使用强密码
   - 定期备份数据库
   - 限制数据库用户权限

3. **定期维护**
   - 及时更新系统
   - 监控日志文件
   - 清理过期数据

## 📞 技术支持

如果您在安装过程中遇到问题：

1. 检查服务器环境是否满足要求
2. 确认目录权限设置正确
3. 查看错误日志文件
4. 检查数据库连接配置

## 📝 更新日志

### v1.0.0
- 初始版本发布
- 完整的用户管理系统
- Tata Coin虚拟货币系统
- 评价和问答功能
- 邀请返点系统
- 消息通知系统

---

**注意**: 安装完成后请及时删除安装文件，确保系统安全。
