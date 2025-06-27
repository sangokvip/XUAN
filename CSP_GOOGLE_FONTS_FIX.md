# CSP Google Fonts 修复说明

## 问题描述

浏览器开发者工具显示以下CSP错误：

```
Some resources are blocked because their origin is not listed in your site's Content Security Policy (CSP).

资源：https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap
状态：已屏蔽
指令：style-src-elem
```

这导致Google Fonts无法加载，可能影响网站的字体显示和整体样式。

## 根本原因

网站的Content Security Policy (CSP)配置中缺少对Google Fonts域名的支持：

### 原始CSP配置
```
style-src 'self' 'unsafe-inline';
font-src 'self' data: *.alicdn.com chrome-extension:;
```

### 问题分析
1. **style-src指令**：只允许来自同源('self')和内联样式('unsafe-inline')，不包含fonts.googleapis.com
2. **font-src指令**：只允许同源、data URI、阿里CDN和浏览器扩展，不包含fonts.gstatic.com

Google Fonts需要两个域名：
- `fonts.googleapis.com` - 用于加载CSS样式文件
- `fonts.gstatic.com` - 用于加载字体文件

## 解决方案

### 1. 修改 config/security.php

更新CSP配置以包含Google Fonts域名：

```php
// 内容安全策略
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
       "style-src 'self' 'unsafe-inline' fonts.googleapis.com; " .
       "img-src 'self' data: blob:; " .
       "font-src 'self' data: *.alicdn.com fonts.gstatic.com chrome-extension:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none';";
```

**关键变更**：
- `style-src`: 添加了 `fonts.googleapis.com`
- `font-src`: 添加了 `fonts.gstatic.com`

### 2. 修改 .htaccess

同步更新Apache配置中的CSP头部：

```apache
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; img-src 'self' data:; font-src 'self' data: *.alicdn.com fonts.gstatic.com; connect-src 'self'; frame-ancestors 'none';"
```

## 修改的文件

### 1. config/security.php
- **第34行**：在 `style-src` 指令中添加 `fonts.googleapis.com`
- **第36行**：在 `font-src` 指令中添加 `fonts.gstatic.com`

### 2. .htaccess
- **第55行**：同步更新Apache头部中的CSP配置

## CSP指令说明

### style-src指令
控制样式表的来源：
- `'self'` - 允许同源样式
- `'unsafe-inline'` - 允许内联样式
- `fonts.googleapis.com` - 允许Google Fonts CSS

### font-src指令
控制字体文件的来源：
- `'self'` - 允许同源字体
- `data:` - 允许data URI字体
- `*.alicdn.com` - 允许阿里CDN字体
- `fonts.gstatic.com` - 允许Google Fonts字体文件
- `chrome-extension:` - 允许浏览器扩展字体

## 安全考虑

### 为什么允许Google Fonts是安全的

1. **可信来源**：Google Fonts是Google官方提供的可信服务
2. **HTTPS加密**：所有Google Fonts资源都通过HTTPS提供
3. **只读资源**：字体文件是只读资源，不包含可执行代码
4. **广泛使用**：被全球数百万网站使用，安全性经过验证

### 最小权限原则

CSP配置遵循最小权限原则：
- 只添加必需的域名
- 不使用通配符 `*`
- 保持其他安全限制不变

## 测试验证

### 1. 浏览器开发者工具
- 打开F12开发者工具
- 查看Console标签页
- 确认没有CSP相关错误

### 2. 网络标签页
- 检查fonts.googleapis.com请求状态
- 确认字体文件正常加载

### 3. 样式检查
- 验证Inter字体是否正确应用
- 检查页面字体显示效果

## 需要上传的文件

1. **`config/security.php`** - PHP CSP配置修复
2. **`.htaccess`** - Apache CSP头部修复

## 预期效果

修复后应该能够：
1. **正常加载Google Fonts**：Inter字体正确显示
2. **消除CSP错误**：浏览器控制台无CSP警告
3. **保持安全性**：其他安全限制保持不变
4. **改善用户体验**：页面字体显示更加美观

## 故障排除

如果修复后仍有问题：

1. **清除浏览器缓存**：强制刷新页面(Ctrl+F5)
2. **检查服务器配置**：确认.htaccess文件生效
3. **验证PHP配置**：确认security.php被正确包含
4. **检查网络连接**：确认能够访问Google服务

## 后续建议

1. **监控CSP报告**：考虑添加CSP报告功能
2. **定期审查**：定期检查CSP配置的有效性
3. **性能优化**：考虑本地化字体文件以提高加载速度
4. **备用方案**：为Google Fonts添加本地备用字体
