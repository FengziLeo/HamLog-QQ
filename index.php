<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// 载入配置
$config = require __DIR__ . '/config.php';

// 简单鉴权，使用 X-API-Key 头
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== (string)$config['api_key']) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

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
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db_connect_failed', 'message' => $e->getMessage()]);
  exit;
}

// 路由解析
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
// 标准化路径
$path = rtrim($path, '/');
if ($path === '') $path = '/';

switch ($path) {
  case '/qsoapi/index.php/callsign/record':
  case '/callsign/record':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
      break;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    // 校验与规范化
    $userId = (string)($data['userId'] ?? '');
    $username = (string)($data['username'] ?? '');
    $callsigns = (string)($data['callsigns'] ?? '');
    $device = isset($data['device']) ? (string)$data['device'] : null;
    $antenna = isset($data['antenna']) ? (string)$data['antenna'] : null;
    $power = isset($data['power']) ? (string)$data['power'] : null;
    $location = isset($data['location']) ? (string)$data['location'] : null;
    $timestamp = (string)($data['timestamp'] ?? '');
    $messageContent = (string)($data['messageContent'] ?? '');
    $isComplete = !empty($data['isComplete']) ? 1 : 0;

    if ($userId === '' || $callsigns === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_params']);
      break;
    }

    // 解析时间，兼容 ISO8601
    $ts = $timestamp !== '' ? date('Y-m-d H:i:s', strtotime($timestamp)) : date('Y-m-d H:i:s');

    try {
      if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // 更新现有记录
        $sql = 'UPDATE callsign_records SET
          username = :username,
          device = :device,
          antenna = :antenna,
          power = :power,
          location = :location,
          timestamp = :timestamp,
          messageContent = :messageContent,
          isComplete = :isComplete
          WHERE userId = :userId AND callsigns = :callsigns
          ORDER BY timestamp DESC LIMIT 1';
      } else {
        // 插入新记录
        $sql = 'INSERT INTO callsign_records
          (userId, username, callsigns, device, antenna, power, location, timestamp, messageContent, isComplete)
          VALUES (:userId, :username, :callsigns, :device, :antenna, :power, :location, :timestamp, :messageContent, :isComplete)';
      }
      
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':userId' => $userId,
        ':username' => $username,
        ':callsigns' => $callsigns,
        ':device' => $device,
        ':antenna' => $antenna,
        ':power' => $power,
        ':location' => $location,
        ':timestamp' => $ts,
        ':messageContent' => $messageContent,
        ':isComplete' => $isComplete,
      ]);
      echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => $_SERVER['REQUEST_METHOD'] === 'PUT' ? 'update_failed' : 'insert_failed', 'message' => $e->getMessage()]);
    }
    break;

  case '/qsoapi/index.php/callsign/query':
  case '/callsign/query':
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
      break;
    }
    $userId = isset($_GET['userId']) ? (string)$_GET['userId'] : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit <= 0) $limit = 10;
    if ($limit > (int)$config['max_limit']) $limit = (int)$config['max_limit'];

    if ($userId === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_params']);
      break;
    }

    try {
      $callsign = isset($_GET['callsign']) ? (string)$_GET['callsign'] : '';
      $sql = 'SELECT userId, username, callsigns, device, antenna, power, location, timestamp, messageContent, isComplete
              FROM callsign_records WHERE userId = :userId' . 
              ($callsign !== '' ? ' AND callsigns = :callsign' : '') . '
              ORDER BY timestamp DESC LIMIT :limit';
      $stmt = $pdo->prepare($sql);
      $stmt->bindValue(':userId', $userId, PDO::PARAM_STR);
      if ($callsign !== '') {
        $stmt->bindValue(':callsign', $callsign, PDO::PARAM_STR);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll();
      echo json_encode($rows);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'query_failed', 'message' => $e->getMessage()]);
    }
    break;

  default:
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
}


