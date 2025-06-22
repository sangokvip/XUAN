-- 为塔罗师表添加圆形头像字段
ALTER TABLE readers 
ADD COLUMN photo_circle VARCHAR(255) DEFAULT NULL COMMENT '圆形头像（用于首页展示）' AFTER photo;

-- 查看更新后的表结构
DESCRIBE readers;
