-- 业余无线电呼号记录表
-- 用于存储用户发送的呼号信息

CREATE TABLE IF NOT EXISTS callsign_records (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
    userId VARCHAR(255) NOT NULL COMMENT '用户ID',
    username VARCHAR(255) NOT NULL COMMENT '用户名',
    callsigns TEXT NOT NULL COMMENT '呼号列表（多个呼号用逗号分隔）',
    device VARCHAR(255) NULL COMMENT '设备信息',
    antenna VARCHAR(255) NULL COMMENT '天线信息',
    power VARCHAR(255) NULL COMMENT '功率信息',
    location VARCHAR(255) NULL COMMENT '位置信息',
    timestamp DATETIME NOT NULL COMMENT '记录时间',
    messageContent TEXT NOT NULL COMMENT '原始消息内容',
    isComplete BOOLEAN DEFAULT FALSE COMMENT '是否为完整记录（包含附加信息）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='业余无线电呼号记录表';

-- 创建索引以提高查询性能
CREATE INDEX idx_userId ON callsign_records(userId);
CREATE INDEX idx_timestamp ON callsign_records(timestamp);
CREATE INDEX idx_callsigns ON callsign_records(callsigns(100));
CREATE INDEX idx_isComplete ON callsign_records(isComplete);
