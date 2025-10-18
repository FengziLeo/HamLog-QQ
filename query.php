<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 载入配置
$config = require __DIR__ . '/config.php';

// 创建 PDO 连接
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['db']['host'],
    (int)$config['db']['port'],
    $config['db']['database'],
    $config['db']['charset']
);

try {
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => '数据库连接失败']);
    exit;
}

// 获取呼号参数
$callsign = isset($_GET['callsign']) ? trim($_GET['callsign']) : '';

if (empty($callsign)) {
    echo json_encode(['error' => '请输入呼号']);
    exit;
}

try {
    // 查询呼号记录
    $sql = 'SELECT callsigns, timestamp 
            FROM callsign_records 
            WHERE callsigns = :callsign
            ORDER BY timestamp DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':callsign', $callsign, PDO::PARAM_STR);
    $stmt->execute();
    $records = $stmt->fetchAll();

    echo json_encode($records);
} catch (Throwable $e) {
    echo json_encode(['error' => '查询失败: ' . $e->getMessage()]);
}