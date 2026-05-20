<?php
// backend/test.php

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Content-Type: application/json; charset=UTF-8");

while (ob_get_level()) { ob_end_clean(); }

try {
    // 🔹 ВАЖНО: присваиваем результат return из config.php
    $config = require __DIR__ . '/config.php';
    
    require_once __DIR__ . '/src/Database.php';
    
    $db = new Database($config['db']);
    $pdo = $db->pdo();
    
    $stmt = $pdo->query("SELECT id, title, price FROM courses LIMIT 3");
    $courses = $stmt->fetchAll();
    
    echo json_encode([
        "ok" => true,
        "count" => count($courses),
        "sample" => $courses,
        "config_loaded" => true
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine(),
        "config_loaded" => isset($config)
    ], JSON_UNESCAPED_UNICODE);
}