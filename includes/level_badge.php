<?php
/**
 * 等级标签显示组件
 * 用于在各个地方显示用户和塔罗师的等级标签
 */

/**
 * 获取用户等级标签HTML
 * @param int $userId 用户ID
 * @param string $userType 用户类型 'user' 或 'reader'
 * @param string $size 尺寸 'small', 'medium', 'large'
 * @return string HTML标签
 */
function getUserLevelBadge($userId, $userType = 'user', $size = 'small') {
    if (!$userId) return '';
    
    try {
        require_once __DIR__ . '/TataCoinManager.php';
        $tataCoinManager = new TataCoinManager();
        $levelInfo = $tataCoinManager->getUserLevel($userId, $userType);
        
        if ($userType === 'user') {
            return getUserLevelBadgeHTML($levelInfo['level'], $levelInfo['level_name'], $size);
        } else {
            return getReaderLevelBadgeHTML($levelInfo['level_name'], $size);
        }
    } catch (Exception $e) {
        return '';
    }
}

/**
 * 生成用户等级标签HTML
 * @param int $level 等级数字
 * @param string $levelName 等级名称
 * @param string $size 尺寸
 * @return string HTML
 */
function getUserLevelBadgeHTML($level, $levelName, $size = 'small') {
    $sizeClass = 'level-badge-' . $size;
    $levelClass = 'level-' . $level;
    
    return "<span class=\"user-level-badge {$sizeClass} {$levelClass}\">{$levelName}</span>";
}

/**
 * 生成塔罗师等级标签HTML
 * @param string $levelName 等级名称
 * @param string $size 尺寸
 * @return string HTML
 */
function getReaderLevelBadgeHTML($levelName, $size = 'small') {
    $sizeClass = 'level-badge-' . $size;
    $typeClass = $levelName === '推荐塔罗师' ? 'reader-featured' : 'reader-normal';
    
    return "<span class=\"reader-level-badge {$sizeClass} {$typeClass}\">{$levelName}</span>";
}

/**
 * 输出等级标签CSS样式
 */
function outputLevelBadgeCSS() {
    echo '<style>
    /* 用户等级标签基础样式 */
    .user-level-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: bold;
        font-size: 10px;
        color: white;
        text-shadow: 0 1px 1px rgba(0,0,0,0.3);
        margin-left: 5px;
        vertical-align: middle;
    }
    
    /* 用户等级颜色 */
    .user-level-badge.level-1 {
        background: linear-gradient(135deg, #9e9e9e, #757575); /* 灰色 - L1 */
    }
    
    .user-level-badge.level-2 {
        background: linear-gradient(135deg, #4caf50, #66bb6a); /* 绿色 - L2 */
    }
    
    .user-level-badge.level-3 {
        background: linear-gradient(135deg, #2196f3, #42a5f5); /* 蓝色 - L3 */
    }
    
    .user-level-badge.level-4 {
        background: linear-gradient(135deg, #9c27b0, #ba68c8); /* 紫色 - L4 */
    }
    
    .user-level-badge.level-5 {
        background: linear-gradient(135deg, #ff9800, #ffb74d); /* 金色 - L5 */
    }
    
    /* 塔罗师等级标签 */
    .reader-level-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: bold;
        font-size: 10px;
        color: white;
        text-shadow: 0 1px 1px rgba(0,0,0,0.3);
        margin-left: 5px;
        vertical-align: middle;
    }
    
    .reader-level-badge.reader-normal {
        background: linear-gradient(135deg, #607d8b, #78909c); /* 蓝灰色 - 普通塔罗师 */
    }
    
    .reader-level-badge.reader-featured {
        background: linear-gradient(135deg, #e91e63, #f06292); /* 粉红色 - 推荐塔罗师 */
    }
    
    /* 尺寸变体 */
    .level-badge-small {
        font-size: 10px;
        padding: 2px 6px;
    }
    
    .level-badge-medium {
        font-size: 12px;
        padding: 3px 8px;
    }
    
    .level-badge-large {
        font-size: 14px;
        padding: 4px 10px;
    }
    
    /* 响应式调整 */
    @media (max-width: 768px) {
        .user-level-badge, .reader-level-badge {
            font-size: 9px;
            padding: 1px 4px;
        }
        
        .level-badge-medium {
            font-size: 10px;
            padding: 2px 6px;
        }
        
        .level-badge-large {
            font-size: 12px;
            padding: 3px 8px;
        }
    }
    </style>';
}

/**
 * 获取等级说明文本
 * @param string $userType 用户类型
 * @return string 说明文本
 */
function getLevelDescription($userType = 'user') {
    if ($userType === 'user') {
        return '
        <div class="level-description">
            <h4>用户等级说明</h4>
            <ul>
                <li><span class="user-level-badge level-badge-medium level-1">L1</span> 0-100 coin</li>
                <li><span class="user-level-badge level-badge-medium level-2">L2</span> 101-200 coin，享受5%折扣</li>
                <li><span class="user-level-badge level-badge-medium level-3">L3</span> 201-500 coin，享受10%折扣</li>
                <li><span class="user-level-badge level-badge-medium level-4">L4</span> 501-999 coin，享受15%折扣</li>
                <li><span class="user-level-badge level-badge-medium level-5">L5</span> 1000+ coin，享受20%折扣</li>
            </ul>
            <p><small>等级基于累计消费的Tata Coin数量，等级越高享受的折扣越多。</small></p>
        </div>';
    } else {
        return '
        <div class="level-description">
            <h4>塔罗师等级说明</h4>
            <ul>
                <li><span class="reader-level-badge level-badge-medium reader-normal">塔罗师</span> 普通塔罗师</li>
                <li><span class="reader-level-badge level-badge-medium reader-featured">推荐塔罗师</span> 平台推荐的优质塔罗师</li>
            </ul>
            <p><small>推荐塔罗师由平台根据服务质量和用户评价进行认定。</small></p>
        </div>';
    }
}
?>
