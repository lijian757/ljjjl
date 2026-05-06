<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$host = '127.0.0.1';
$dbname = 'sfydb_6308069';
$user = 'sfydb_6308069';
$pass = 'Lj19991026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => '数据库连接失败']);
    exit;
}

// 自动建表
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    time VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS bucket_list (
    item_index INT PRIMARY KEY,
    checked TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 密码验证
$DELETE_PASSWORD = '1314';

$action = $_GET['action'] ?? '';

switch ($action) {
    // ========== 留言板 ==========
    case 'get_messages':
        $stmt = $pdo->query("SELECT * FROM messages ORDER BY id ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'add_message':
        $data = json_decode(file_get_contents('php://input'), true);
        $author = trim($data['author'] ?? '');
        $content = trim($data['content'] ?? '');
        if (!$author || !$content) {
            echo json_encode(['error' => '姓名和内容不能为空']);
            break;
        }
        $time = date('Y/m/d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO messages (author, content, time) VALUES (?, ?, ?)");
        $stmt->execute([$author, $content, $time]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        break;

    case 'delete_message':
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['password'] ?? '') !== $DELETE_PASSWORD) {
            echo json_encode(['error' => '密码错误']);
            break;
        }
        $id = intval($data['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    // ========== 爱情清单 ==========
    case 'get_bucket':
        $stmt = $pdo->query("SELECT * FROM bucket_list");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['item_index']] = (bool)$row['checked'];
        }
        echo json_encode($result);
        break;

    case 'toggle_bucket':
        $data = json_decode(file_get_contents('php://input'), true);
        $index = intval($data['index'] ?? 0);
        $checked = intval($data['checked'] ?? 0);
        $stmt = $pdo->prepare("INSERT INTO bucket_list (item_index, checked) VALUES (?, ?) ON DUPLICATE KEY UPDATE checked = ?");
        $stmt->execute([$index, $checked, $checked]);
        echo json_encode(['success' => true]);
        break;

    // ========== 照片 ==========
    case 'get_photos':
        $stmt = $pdo->query("SELECT * FROM photos ORDER BY id ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'upload_photo':
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['password'] ?? '') !== $DELETE_PASSWORD) {
            echo json_encode(['error' => '密码错误']);
            break;
        }
        $base64 = $data['data'] ?? '';
        if (!$base64) {
            echo json_encode(['error' => '没有图片数据']);
            break;
        }
        $filename = 'photo_' . time() . '_' . mt_rand(1000, 9999) . '.jpg';
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
        if ($imageData === false) {
            echo json_encode(['error' => '图片解码失败']);
            break;
        }
        file_put_contents($uploadDir . $filename, $imageData);
        $stmt = $pdo->prepare("INSERT INTO photos (filename) VALUES (?)");
        $stmt->execute([$filename]);
        echo json_encode(['success' => true, 'filename' => $filename]);
        break;

    case 'delete_photo':
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['password'] ?? '') !== $DELETE_PASSWORD) {
            echo json_encode(['error' => '密码错误']);
            break;
        }
        $id = intval($data['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT filename FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $filepath = __DIR__ . '/uploads/' . $row['filename'];
            if (file_exists($filepath)) unlink($filepath);
            $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => '未知操作']);
        break;
}
