<?php
// backend/router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$allowed_origin = "http://localhost:5173";

// Preflight
if ($method === 'OPTIONS') {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    http_response_code(204);
    exit;
}

// CORS для всех ответов
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// 🔹 КЛЮЧЕВОЙ ФИКС: передаём путь без /api префикса
if (str_starts_with($uri, '/api')) {
    // Убираем /api из пути, чтобы в index.php было /courses, а не /api/courses
    $_SERVER['PATH_INFO'] = substr($uri, 4);  // "/api/courses" → "/courses"
    
    header("Content-Type: application/json; charset=UTF-8");
    require __DIR__ . '/index.php';
    return true;
}

// Прямые .php файлы
if (str_ends_with($uri, '.php') && file_exists(__DIR__ . $uri)) {
    require __DIR__ . $uri;
    return true;
}

// Статические файлы
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Остальное → index.php
header("Content-Type: application/json; charset=UTF-8");
require __DIR__ . '/index.php';
return true;