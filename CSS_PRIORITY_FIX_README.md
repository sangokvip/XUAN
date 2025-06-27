# CSS优先级冲突修复说明

## 问题描述

1. **tag_readers.php 页面标签过小**：标签显示得非常小，几乎看不清
2. **reader.php 页面标签大小不一致**：主要身份标签和技能标签大小不同
3. **CSS优先级冲突**：多个CSS规则相互冲突，导致样式不生效

## 根本原因分析

### CSS优先级冲突

发现了多个冲突的CSS规则：

1. **基础样式冲突**：
   - `.divination-tag` (第45行) - 基础样式
   - `.divination-tag.primary-tag` (第90行) - 主要标签样式
   - `.divination-tag.skill-tag` (第117行) - 技能标签样式

2. **通用页面样式冲突**：
   - `.reader-card .divination-tag` (第247行) - 通用卡片样式
   - 移动端通用样式 (第167-182行)

3. **选择器优先级不足**：
   - 特定页面的样式被通用样式覆盖
   - `!important` 声明不够强力

## 解决方案

### 1. 移除冲突的通用样式

移除了第247-250行的冲突样式：
```css
/* 移除这个冲突的样式 */
.reader-card .divination-tag {
    font-size: 0.75rem;  /* 这个导致标签过小 */
    padding: 3px 10px;
}
```

### 2. 增强CSS选择器优先级

为每个页面添加更具体的选择器：

**标签页面卡片**：
```css
.reader-card .divination-tags-container .divination-tag.primary-tag,
.reader-card .divination-tags-container .divination-tag.skill-tag,
.reader-card .divination-tag.primary-tag,
.reader-card .divination-tag.skill-tag {
    font-size: 10px !important;
    /* ... 其他样式 ... */
    opacity: 1 !important;  /* 确保不被透明度影响 */
}
```

**占卜师详情页**：
```css
.reader-info-section h1 .divination-tag.primary-tag,
.reader-info-section h1 .divination-tag.skill-tag,
.reader-info-section h1 a.divination-tag.primary-tag,
.reader-info-section h1 a.divination-tag.skill-tag {
    font-size: 10px !important;
    /* ... 其他样式 ... */
}
```

**占卜师列表页**：
```css
.readers-grid .divination-tags-container .divination-tag.primary-tag,
.readers-grid .divination-tags-container .divination-tag.skill-tag,
.readers-grid .divination-tag.primary-tag,
.readers-grid .divination-tag.skill-tag,
.readers-grid a.divination-tag.primary-tag,
.readers-grid a.divination-tag.skill-tag {
    font-size: 10px !important;
    /* ... 其他样式 ... */
}
```

### 3. 关键改进点

1. **多重选择器**：同时覆盖容器内和直接子元素
2. **链接元素支持**：添加 `a.divination-tag` 选择器
3. **透明度重置**：添加 `opacity: 1 !important`
4. **更高优先级**：使用更具体的选择器路径

## 修改的文件

### assets/css/divination-tags.css

1. **移除冲突样式**：删除了通用的 `.reader-card .divination-tag` 样式
2. **增强选择器优先级**：为所有页面添加更具体的CSS选择器
3. **添加透明度重置**：确保标签不被 `opacity` 属性影响

## 样式优先级层级

### 最高优先级（特定页面）
- `.reader-card .divination-tags-container .divination-tag.primary-tag`
- `.reader-info-section h1 a.divination-tag.primary-tag`
- `.readers-grid .divination-tags-container .divination-tag.skill-tag`

### 中等优先级（页面类型）
- `.reader-card .divination-tag.primary-tag`
- `.readers-grid .divination-tag.skill-tag`

### 低优先级（通用样式）
- `.divination-tag.primary-tag`
- `.divination-tag.skill-tag`
- `.divination-tag`

## 需要上传的文件

- **`assets/css/divination-tags.css`** - 重要的CSS优先级修复

## 预期效果

1. **tag_readers.php 页面**：
   - 标签大小恢复正常（10px）
   - 主要身份标签和技能标签大小完全一致

2. **reader.php 页面**：
   - 所有标签大小统一（10px）
   - 标签清晰可见，对齐良好

3. **所有其他页面**：
   - 标签样式稳定，不受通用样式干扰
   - 移动端和桌面端都正常显示

## 测试建议

1. 清除浏览器缓存后测试
2. 检查所有页面的标签显示效果
3. 在不同设备上验证响应式效果
4. 确认标签点击功能正常
