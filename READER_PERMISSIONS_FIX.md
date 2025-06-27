# 占卜师权限修复总结

## 🎯 修复的问题

### 1. ❌ 占卜师查看其他reader页面时出现"登录后可以查看和发表评价"
**修复方案**：
- 修改评价表单显示条件：`($isAdmin || isset($_SESSION['user_id'])) && !$isReader`
- 修改提问表单显示条件：`($isAdmin || isset($_SESSION['user_id'])) && !$isReader`
- 修改点赞功能显示条件：`($isAdmin || isset($_SESSION['user_id'])) && !$isReader`

### 2. ❌ 占卜师查看其他占卜师联系方式时需要付款
**修复方案**：
- 修改联系方式查看权限逻辑
- 占卜师只能免费查看自己的联系方式
- 查看其他占卜师需要付费（与普通用户相同）
- 更新TataCoinManager支持占卜师付费

### 3. ❌ 占卜师浏览reader页面时显示"占卜师模式"横幅
**修复方案**：
- 完全移除占卜师模式横幅显示
- 保留管理员模式横幅

### 4. ❌ 占卜师导航栏用户名颜色需要改为金色
**修复方案**：
- 为占卜师用户名添加特殊CSS类：`reader-name-gold`
- 设置金色样式：`color: #d4af37`
- 悬停效果：`color: #ffd700`

## ✅ 具体修复内容

### 1. 修复评价权限逻辑 (reader.php)

**修复前**：
```php
} elseif ($isReader) {
    // 占卜师权限：可以查看评论，但不能评价自己
    if ($_SESSION['reader_id'] != $readerId) {
        $hasReviewed = $reviewManager->hasUserReviewed($currentUserId, $readerId);
        $canReview = !$hasReviewed;
        $hasPurchased = true; // 占卜师视为已购买
    } else {
        $canReview = false; // 不能评价自己
        $hasPurchased = false;
    }
}
```

**修复后**：
```php
} elseif ($isReader) {
    // 占卜师权限：可以查看评论，但不能评价其他占卜师
    $canReview = false; // 占卜师不能评价其他占卜师
    $hasPurchased = false; // 占卜师不视为已购买
}
```

### 2. 修复联系方式查看权限 (reader.php)

**修复前**：
```php
} elseif (isset($_SESSION['reader_id'])) {
    // 占卜师登录状态
    $isReader = true;
    $currentReader = getReaderById($_SESSION['reader_id']);
    $canViewContact = true;
    $hasViewedContact = true; // 占卜师可以查看所有联系方式
```

**修复后**：
```php
} elseif (isset($_SESSION['reader_id'])) {
    // 占卜师登录状态
    $isReader = true;
    $currentReader = getReaderById($_SESSION['reader_id']);
    
    // 占卜师只能免费查看自己的联系方式，查看其他占卜师需要付费
    if ($_SESSION['reader_id'] == $readerId) {
        $canViewContact = true;
        $hasViewedContact = true; // 可以查看自己的联系方式
    } else {
        $canViewContact = true;
        // 检查是否已经付费查看过其他占卜师
        $db = Database::getInstance();
        $existingRecord = $db->fetchOne(
            "SELECT * FROM user_browse_history WHERE user_id = ? AND reader_id = ? AND browse_type = 'paid'",
            [$_SESSION['reader_id'], $readerId]
        );
        $hasViewedContact = (bool)$existingRecord;
        $contactCost = $reader['is_featured'] ? 30 : 10;
        $userTataCoinBalance = $tataCoinManager->getBalance($_SESSION['reader_id'], 'reader');
    }
```

### 3. 移除占卜师模式横幅 (reader.php)

**修复前**：
```php
<?php elseif ($isReader): ?>
    <div class="reader-mode-banner">
        <div class="reader-banner-content">
            <span class="reader-icon">🔮</span>
            <span class="reader-text">占卜师模式</span>
            <span class="reader-note">您正以占卜师身份浏览，可查看所有联系方式</span>
            <a href="<?php echo SITE_URL; ?>/reader/dashboard.php" class="reader-link">返回后台</a>
        </div>
    </div>
<?php endif; ?>
```

**修复后**：
```php
<?php endif; ?>
```

### 4. 设置占卜师用户名金色样式

**header.php修改**：
```php
// 桌面端
<span class="user-name reader-name-gold"><?php echo h($currentReader['full_name']); ?></span>

// 移动端
<a href="<?php echo SITE_URL; ?>/reader/dashboard.php" class="mobile-user-name reader-name-gold"><?php echo h($currentReader['full_name']); ?></a>
```

**CSS样式添加 (style.css)**：
```css
/* 占卜师用户名金色样式 */
.reader-name-gold {
    color: #d4af37 !important;
    font-weight: 600 !important;
}

.reader-name-gold:hover {
    color: #ffd700 !important;
}
```

### 5. 更新TataCoinManager支持占卜师付费

**方法签名修改**：
```php
public function viewReaderContact($userId, $readerId, $userType = 'user')
```

**关键修改**：
- 支持`user_type`参数区分用户类型
- 更新余额检查逻辑：`$this->getBalance($userId, $userType)`
- 更新余额扣除逻辑：根据用户类型更新不同表
- 更新浏览历史记录：添加`user_type`字段

### 6. 数据库结构更新

**添加user_type字段**：
```sql
ALTER TABLE user_browse_history ADD COLUMN user_type ENUM('user', 'reader') DEFAULT 'user' COMMENT '用户类型：user-普通用户，reader-占卜师';
```

**创建索引**：
```sql
ALTER TABLE user_browse_history ADD INDEX idx_user_type (user_type);
ALTER TABLE user_browse_history ADD INDEX idx_user_reader_type (user_id, reader_id, user_type);
```

## 🔧 修改的文件列表

### 主要文件：
1. **reader.php** - 占卜师页面权限逻辑
2. **includes/header.php** - 导航栏用户名样式
3. **assets/css/style.css** - 占卜师用户名金色样式
4. **includes/TataCoinManager.php** - 支持占卜师付费功能
5. **database_update_reader_browse.php** - 数据库更新脚本

### 修改内容：
- ✅ 评价权限：占卜师不能评价其他占卜师
- ✅ 联系方式权限：占卜师查看其他占卜师需要付费
- ✅ 界面显示：移除占卜师模式横幅
- ✅ 用户名样式：占卜师用户名显示为金色
- ✅ 数据库支持：添加user_type字段支持占卜师付费

## 🧪 测试建议

### 功能测试：
1. **占卜师登录测试**：
   - 确认导航栏用户名显示为金色
   - 确认不显示占卜师模式横幅

2. **权限测试**：
   - 占卜师查看自己页面：免费查看联系方式
   - 占卜师查看其他占卜师：需要付费查看联系方式
   - 占卜师不能评价其他占卜师
   - 占卜师不能对评价点赞
   - 占卜师不能提问

3. **付费功能测试**：
   - 占卜师余额充足时可以付费查看
   - 占卜师余额不足时显示提示
   - 付费后可以查看联系方式
   - 交易记录正确记录

### 数据库测试：
1. 运行数据库更新脚本
2. 确认user_browse_history表有user_type字段
3. 测试占卜师付费查看功能
4. 检查交易记录是否正确

## 🎯 修复效果

### 修复前的问题：
- ❌ 占卜师看到"登录后可以查看和发表评价"提示
- ❌ 占卜师可以免费查看所有联系方式
- ❌ 显示不必要的"占卜师模式"横幅
- ❌ 占卜师用户名颜色与普通用户相同

### 修复后的效果：
- ✅ 占卜师不会看到评价相关提示
- ✅ 占卜师查看其他占卜师需要付费
- ✅ 不显示占卜师模式横幅
- ✅ 占卜师用户名显示为金色
- ✅ 占卜师只能查看评价，不能参与互动
- ✅ 权限控制更加精确和合理

## 📊 总结

这次修复完善了占卜师在前台的权限控制：

✅ **权限分离**：占卜师和普通用户权限明确区分
✅ **付费机制**：占卜师查看其他占卜师也需要付费
✅ **界面优化**：移除不必要的提示，用户名金色显示
✅ **功能限制**：占卜师不能参与评价和问答互动

占卜师现在拥有合理的前台浏览权限，既能查看信息又不会干扰用户体验！
