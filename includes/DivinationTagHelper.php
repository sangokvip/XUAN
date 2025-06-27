<?php
/**
 * 占卜师标签显示助手类
 * 用于生成和显示占卜师的身份标签
 */
require_once 'DivinationConfig.php';

class DivinationTagHelper {
    
    /**
     * 生成占卜师主要身份标签HTML
     * @param array $reader 占卜师数据
     * @param bool $showTooltip 是否显示提示信息
     * @param bool $clickable 是否可点击
     * @return string HTML
     */
    public static function generatePrimaryTag($reader, $showTooltip = true, $clickable = false) {
        if (empty($reader['primary_identity'])) {
            return '';
        }

        $typeName = DivinationConfig::getDivinationTypeName($reader['primary_identity']);
        $class = DivinationConfig::getDivinationTagClass($reader['primary_identity']);
        $tooltip = $showTooltip ? 'title="主要身份标签"' : '';

        if ($clickable) {
            $url = "tag_readers.php?tag=" . urlencode($reader['primary_identity']);
            return "<a href=\"{$url}\" class=\"divination-tag {$class} primary-tag clickable\" {$tooltip}>{$typeName}</a>";
        }

        return "<span class=\"divination-tag {$class} primary-tag\" {$tooltip}>{$typeName}</span>";
    }
    
    /**
     * 生成占卜师所有标签HTML
     * @param array $reader 占卜师数据
     * @param bool $showPrimary 是否显示主要标签
     * @param bool $showSkills 是否显示技能标签
     * @param int $maxSkills 最大技能标签数量
     * @param bool $clickable 是否可点击
     * @return string HTML
     */
    public static function generateAllTags($reader, $showPrimary = true, $showSkills = true, $maxSkills = 2, $clickable = false) {
        $html = '';
        $divinationTypes = [];

        // 解析占卜类型
        if (!empty($reader['divination_types'])) {
            $divinationTypes = json_decode($reader['divination_types'], true) ?: [];
        }

        if (empty($divinationTypes)) {
            return '';
        }

        // 主要身份标签
        if ($showPrimary && !empty($reader['primary_identity'])) {
            $html .= self::generatePrimaryTag($reader, true, $clickable);
        }

        // 技能标签
        if ($showSkills) {
            $skillTags = [];
            $skillCount = 0;

            foreach ($divinationTypes as $type) {
                if ($type !== $reader['primary_identity'] && $skillCount < $maxSkills) {
                    $typeName = DivinationConfig::getDivinationTypeName($type);
                    $class = DivinationConfig::getDivinationTagClass($type);

                    if ($clickable) {
                        $url = "tag_readers.php?tag=" . urlencode($type);
                        $skillTags[] = "<a href=\"{$url}\" class=\"divination-tag {$class} skill-tag clickable\" title=\"技能标签\">{$typeName}</a>";
                    } else {
                        $skillTags[] = "<span class=\"divination-tag {$class} skill-tag\" title=\"技能标签\">{$typeName}</span>";
                    }
                    $skillCount++;
                }
            }

            if (!empty($skillTags)) {
                $html .= implode('', $skillTags);
            }
        }

        return $html;
    }
    
    /**
     * 生成标签容器HTML
     * @param array $reader 占卜师数据
     * @param string $alignment 对齐方式 left|center|right
     * @param bool $showPrimary 是否显示主要标签
     * @param bool $showSkills 是否显示技能标签
     * @param int $maxSkills 最大技能标签数量
     * @param bool $clickable 是否可点击
     * @return string HTML
     */
    public static function generateTagsContainer($reader, $alignment = 'center', $showPrimary = true, $showSkills = true, $maxSkills = 2, $clickable = false) {
        $tags = self::generateAllTags($reader, $showPrimary, $showSkills, $maxSkills, $clickable);

        if (empty($tags)) {
            return '';
        }

        return "<div class=\"divination-tags-container {$alignment}\">{$tags}</div>";
    }
    
    /**
     * 生成简化的主要标签（仅用于列表显示）
     * @param array $reader 占卜师数据
     * @return string HTML
     */
    public static function generateSimpleTag($reader) {
        if (empty($reader['primary_identity'])) {
            return '';
        }
        
        $typeName = DivinationConfig::getDivinationTypeName($reader['primary_identity']);
        $class = DivinationConfig::getDivinationTagClass($reader['primary_identity']);
        
        return "<span class=\"divination-tag {$class}\">{$typeName}</span>";
    }
    
    /**
     * 获取占卜师的身份类别颜色
     * @param array $reader 占卜师数据
     * @return string 颜色值
     */
    public static function getIdentityColor($reader) {
        if (empty($reader['identity_category'])) {
            return '#6b7280'; // 默认灰色
        }
        
        switch ($reader['identity_category']) {
            case 'western':
                return '#8b5cf6'; // 紫色
            case 'eastern':
                return '#374151'; // 黑色
            default:
                return '#6b7280'; // 灰色
        }
    }
    
    /**
     * 生成标签说明HTML
     * @return string HTML
     */
    public static function generateTagsLegend() {
        return '
        <div class="divination-tags-legend">
            <div class="divination-tags-legend-item">
                <span class="divination-tag divination-tag-western">西玄</span>
                <span>西方玄学</span>
            </div>
            <div class="divination-tags-legend-item">
                <span class="divination-tag divination-tag-eastern">东玄</span>
                <span>东方玄学</span>
            </div>
            <div class="divination-tags-legend-item">
                <span style="font-weight: bold;">粗体</span>
                <span>主要身份</span>
            </div>
        </div>';
    }
    
    /**
     * 检查占卜师是否有有效的标签
     * @param array $reader 占卜师数据
     * @return bool
     */
    public static function hasValidTags($reader) {
        return !empty($reader['primary_identity']) || !empty($reader['divination_types']);
    }
    
    /**
     * 获取占卜师的所有占卜类型名称
     * @param array $reader 占卜师数据
     * @return array 类型名称数组
     */
    public static function getDivinationTypeNames($reader) {
        $names = [];
        
        if (!empty($reader['divination_types'])) {
            $types = json_decode($reader['divination_types'], true) ?: [];
            foreach ($types as $type) {
                $names[] = DivinationConfig::getDivinationTypeName($type);
            }
        }
        
        return $names;
    }
    
    /**
     * 生成管理后台用的标签HTML
     * @param array $reader 占卜师数据
     * @return string HTML
     */
    public static function generateAdminTags($reader) {
        $html = '';
        
        // 主要身份
        if (!empty($reader['primary_identity'])) {
            $typeName = DivinationConfig::getDivinationTypeName($reader['primary_identity']);
            $class = DivinationConfig::getDivinationTagClass($reader['primary_identity']);
            $html .= "<span class=\"divination-tag {$class} primary-tag\">{$typeName}</span>";
        }
        
        // 其他技能
        if (!empty($reader['divination_types'])) {
            $types = json_decode($reader['divination_types'], true) ?: [];
            foreach ($types as $type) {
                if ($type !== $reader['primary_identity']) {
                    $typeName = DivinationConfig::getDivinationTypeName($type);
                    $class = DivinationConfig::getDivinationTagClass($type);
                    $html .= "<span class=\"divination-tag {$class} skill-tag\">{$typeName}</span>";
                }
            }
        }
        
        return $html;
    }
    
    /**
     * 生成可编辑的标签HTML（用于管理后台）
     * @param array $reader 占卜师数据
     * @param string $formPrefix 表单字段前缀
     * @return string HTML
     */
    public static function generateEditableTags($reader, $formPrefix = '') {
        $html = '<div class="divination-tags-edit">';
        
        // 当前选择的类型
        $selectedTypes = [];
        if (!empty($reader['divination_types'])) {
            $selectedTypes = json_decode($reader['divination_types'], true) ?: [];
        }
        
        $allTypes = DivinationConfig::getAllDivinationTypes();
        
        foreach ($allTypes as $category => $categoryData) {
            $html .= "<h5>{$categoryData['name']}</h5>";
            $html .= '<div class="divination-grid">';
            
            foreach ($categoryData['types'] as $typeKey => $typeName) {
                $checked = in_array($typeKey, $selectedTypes) ? 'checked' : '';
                $isPrimary = $reader['primary_identity'] === $typeKey;
                $primaryChecked = $isPrimary ? 'checked' : '';
                
                $html .= "
                <div class=\"divination-card {$checked}\">
                    <input type=\"checkbox\" name=\"{$formPrefix}divination_types[]\" value=\"{$typeKey}\" {$checked}>
                    <span class=\"divination-text\">{$typeName}</span>
                    <div class=\"primary-radio\">
                        <input type=\"radio\" name=\"{$formPrefix}primary_identity\" value=\"{$typeKey}\" {$primaryChecked}>
                        <label>主要</label>
                    </div>
                </div>";
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 生成JSON格式的标签数据（用于API）
     * @param array $reader 占卜师数据
     * @return array
     */
    public static function generateTagsData($reader) {
        $data = [
            'primary_identity' => null,
            'primary_name' => null,
            'primary_category' => null,
            'skills' => [],
            'all_types' => []
        ];
        
        if (!empty($reader['primary_identity'])) {
            $data['primary_identity'] = $reader['primary_identity'];
            $data['primary_name'] = DivinationConfig::getDivinationTypeName($reader['primary_identity']);
            $data['primary_category'] = DivinationConfig::getDivinationCategory($reader['primary_identity']);
        }
        
        if (!empty($reader['divination_types'])) {
            $types = json_decode($reader['divination_types'], true) ?: [];
            foreach ($types as $type) {
                $typeName = DivinationConfig::getDivinationTypeName($type);
                $category = DivinationConfig::getDivinationCategory($type);
                
                $typeData = [
                    'key' => $type,
                    'name' => $typeName,
                    'category' => $category,
                    'is_primary' => $type === $reader['primary_identity']
                ];
                
                $data['all_types'][] = $typeData;
                
                if ($type !== $reader['primary_identity']) {
                    $data['skills'][] = $typeData;
                }
            }
        }
        
        return $data;
    }
}
?>
