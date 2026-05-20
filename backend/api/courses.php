<?php

// === Отключаем вывод ошибок в ответ (чтобы не ломать JSON) ===
error_reporting(E_ALL);
ini_set('display_errors', 0);  // ❗ Ключевая строка!
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// === Очистка буфера вывода (на случай случайного вывода) ===
while (ob_get_level()) { ob_end_clean(); }

// === CORS HEADERS ===
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// === Обработка preflight ===
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === Подключение к БД с полной проверкой ===
$databasePath = __DIR__ . '/../src/Database.php';

if (!file_exists($databasePath)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Database.php not found",
        "debug" => ["expected_path" => $databasePath, "realpath" => realpath(__DIR__)]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

include_once $databasePath;

if (!class_exists('Database')) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Class 'Database' not found"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db || !($db instanceof PDO)) {
        throw new Exception("getConnection() failed");
    }
} catch (Exception $e) {
    error_log("DB Error: " . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        "success" => false, 
        "error" => "Database connection failed",
        "debug" => $_SERVER['APP_DEBUG'] === 'true' ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
    exit;

}

// === Запрос курсов ===
try {
    $query = "SELECT id, title, description, teacher_id, status, price, created_at FROM courses";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "db_connected" => true,
        "courses" => $courses,
        "count" => count($courses)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => "Query failed",
        "debug" => $_SERVER['APP_DEBUG'] === 'true' ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
}
?>