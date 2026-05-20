<?php

// === НАСТРОЙКИ ===
ob_start(); // 🔹 Захват вывода (предотвращает "headers already sent")
error_reporting(E_ALL);
ini_set('display_errors', 0); // ⛔ Не выводим ошибки в браузер
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Database.php';

// ============================================================================
// === 🔹 ОТЛАДОЧНАЯ ФУНКЦИЯ ===
// ============================================================================
function debugLog($label, $data = null, $force = false) {
    // В продакшене отключаем (оставьте $force = true для отладки)
    if (!defined('DEBUG_MODE') && !$force) return;
    
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'unknown';
    
    $entry = "\n=== [$timestamp] $label (from $caller) ===\n";
    
    if ($data === null) {
        $entry .= "NULL\n";
    } elseif (is_string($data) || is_numeric($data)) {
        $entry .= "$data\n";
    } elseif (is_array($data) || is_object($data)) {
        $entry .= print_r($data, true) . "\n";
    } elseif (is_bool($data)) {
        $entry .= $data ? 'true' : 'false' . "\n";
    } else {
        $entry .= gettype($data) . "\n";
    }
    
    $entry .= "=== END $label ===\n";
    
    // Пишем в файл (с блокировкой для безопасности)
    $fp = fopen($logFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $entry);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// 🔹 Включите отладку (удалите или закомментируйте в продакшене!)
define('DEBUG_MODE', true);


// === 1. ПОДКЛЮЧЕНИЕ ЗАВИСИМОСТЕЙ (ОБЯЗАТЕЛЬНО ПЕРВЫМ!) ===
require_once __DIR__ . '/src/helpers.php';  // ← getJsonBody, jsonResponse и др.
require_once __DIR__ . '/src/Database.php';  // ← класс Database

// === 2. CORS 
$allowed_origin = "http://localhost:5173";
header("Access-Control-Allow-Origin: $allowed_origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
header("Access-Control-Max-Age: 86400"); // Кэшируем preflight на 24 часа
header("Content-Type: application/json; charset=UTF-8");

// 🔹 Обработка preflight запроса — НЕМЕДЛЕННЫЙ ВЫХОД
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === 3. Очистка буфера ===
while (ob_get_level()) { ob_end_clean(); }

// === 4. Загрузка конфига и БД ===
$config = require __DIR__ . '/config.php';
$db = new Database($config['db']);
$pdo = $db->pdo();

// === Получение метода и пути ===
$method = $_SERVER['REQUEST_METHOD'];

// 🔹 Берём путь из PATH_INFO (который передал router.php)
$path = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Если путь всё ещё содержит /api — убираем его
if (str_starts_with($path, '/api')) {
    $path = substr($path, 4);
}

// Получаем тело запроса
$body = getJsonBody();

error_log("🔍 Routing: method=$method, path=$path");
function currentUser(PDO $pdo): ?array
{
    $token = getBearerToken();
    if (!$token) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.is_teacher, u.is_private, u.bio, u.about
         FROM sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token = :token AND s.expires_at > NOW()'
    );
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireRole(PDO $pdo, array $roles): array
{
    $user = currentUser($pdo);
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    if (!in_array($user['role'], $roles, true)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    return $user;
}

// === УДАЛЕНИЕ КУРСА ПРЕПОДАВАТЕЛЕМ: DELETE /courses/{id} ===
if ($method === 'DELETE' && preg_match('#^/courses/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $courseId = (int)$matches[1];

    try {
        // 🔹 Если не админ — проверяем владение курсом
        if ($user['role'] !== 'admin') {
            $checkStmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :teacher_id');
            $checkStmt->execute(['id' => $courseId, 'teacher_id' => $user['id']]);
            if (!$checkStmt->fetch()) {
                jsonResponse(['error' => 'Course not found or access denied'], 403);
            }
        }

        // 🔹 Удаляем курс (CASCADE удалит модули, уроки и т.д.)
        $stmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute(['id' => $courseId]);

        jsonResponse(['message' => 'Course deleted']);
    } catch (PDOException $e) {
        error_log("Delete course error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete course'], 500);
    }
    exit;
}

// === Получение структуры курса для обучения ===
if ($method === 'GET' && preg_match('#^/courses/(\d+)/structure$#', $path, $matches)) {
    $courseId = (int)$matches[1];
    $user = currentUser($pdo);
    
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    try {
        // Получаем модули с уроками
        $sql = "SELECT m.id as module_id, m.title as module_title, m.sort_order as module_order,
                       l.id as lesson_id, l.title as lesson_title, l.lesson_type, l.sort_order as lesson_order,
                       lp.completed_at
                FROM course_modules m
                LEFT JOIN lessons l ON l.module_id = m.id
                LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = :user_id
                WHERE m.course_id = :course_id
                ORDER BY m.sort_order, l.sort_order";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['course_id' => $courseId, 'user_id' => $user['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Группируем по модулям
        $modules = [];
        foreach ($items as $item) {
            $moduleId = $item['module_id'];
            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'title' => $item['module_title'],
                    'sort_order' => $item['module_order'],
                    'lessons' => []
                ];
            }
            if ($item['lesson_id']) {
                $modules[$moduleId]['lessons'][] = [
                    'id' => $item['lesson_id'],
                    'title' => $item['lesson_title'],
                    'lesson_type' => $item['lesson_type'] ?? 'text',
                    'sort_order' => $item['lesson_order'],
                    'completed' => $item['completed_at'] !== null,
                ];
            }
        }
        
        // Получаем информацию о курсе
        $courseStmt = $pdo->prepare(
            'SELECT id, title, description FROM courses WHERE id = :id'
        );
        $courseStmt->execute(['id' => $courseId]);
        $course = $courseStmt->fetch();
        
        // Считаем прогресс
        $totalLessons = 0;
        $completedLessons = 0;
        foreach ($modules as $module) {
            foreach ($module['lessons'] as $lesson) {
                $totalLessons++;
                if ($lesson['completed']) $completedLessons++;
            }
        }
        
        jsonResponse([
            'course' => $course,
            'modules' => array_values($modules),
            'progress' => [
                'total' => $totalLessons,
                'completed' => $completedLessons,
                'percent' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0,
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Course structure error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load course structure'], 500);
    }
}

// === Получение содержимого урока ===
if ($method === 'GET' && preg_match('#^/lessons/(\d+)$#', $path, $matches)) {
    error_log("🎯 GET /lessons/" . $matches[1]);
    
    $lessonId = (int)$matches[1];
    $user = currentUser($pdo);
    
    if (!$user) {
        error_log("❌ No user for lesson request");
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    try {
        $stmt = $pdo->prepare(
            'SELECT l.id, l.title, l.content_type, l.lesson_type, l.content, l.video_url, l.sort_order,
                    m.id as module_id, m.title as module_title, m.course_id
             FROM lessons l
             JOIN course_modules m ON m.id = l.module_id
             WHERE l.id = :id'
        );
        $stmt->execute(['id' => $lessonId]);
        $lesson = $stmt->fetch();
        
        if (!$lesson) {
            error_log("❌ Lesson not found: $lessonId");
            jsonResponse(['error' => 'Lesson not found'], 404);
        }
        
        error_log("✅ Lesson loaded: " . $lesson['title']);
        
        $quiz = null;
        if ($lesson['lesson_type'] === 'quiz') {
            $quizStmt = $pdo->prepare(
                'SELECT id, question, options, correct_answer FROM quiz_answers WHERE lesson_id = :lesson_id'
            );
            $quizStmt->execute(['lesson_id' => $lessonId]);
            $quiz = $quizStmt->fetchAll();
        }
        
        $progressStmt = $pdo->prepare(
            'SELECT completed_at FROM lesson_progress WHERE lesson_id = :id AND user_id = :user_id'
        );
        $progressStmt->execute(['id' => $lessonId, 'user_id' => $user['id']]);
        $progress = $progressStmt->fetch();
        
        jsonResponse([
            'lesson' => $lesson,
            'quiz' => $quiz,
            'completed' => $progress !== null,
        ]);
        
    } catch (PDOException $e) {
        error_log("❌ Lesson error: " . $e->getMessage());
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

// === ЗАВЕРШЕНИЕ УРОКА: ПОЛНАЯ ОТЛАДКА ===
if ($method === 'POST' && preg_match('#^/lessons/(\d+)/complete$#', $path, $matches)) {
    try {
        $user = requireRole($pdo, ['user', 'teacher', 'admin']);
        $lessonId = (int)$matches[1];
        
        $rawBody = file_get_contents('php://input');
        $body = $rawBody ? json_decode($rawBody, true) : [];
        if (!is_array($body)) $body = [];
        
        // Проверка урока
        $lessonStmt = $pdo->prepare("SELECT id, lesson_type FROM lessons WHERE id = ?");
        $lessonStmt->execute([$lessonId]);
        $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lesson) {
            jsonResponse(['error' => 'Lesson not found'], 404);
            exit;
        }

        if ($lesson['lesson_type'] === 'quiz') {
            $answers = $body['answers'] ?? [];
            if (!is_array($answers)) $answers = [];
            
            $qStmt = $pdo->prepare("SELECT id, correct_answer FROM quiz_answers WHERE lesson_id = ?");
            $qStmt->execute([$lessonId]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalQuestions = count($questions);
            $correctQuestions = 0;
            $totalCorrectOptions = 0;
            $userCorrectOptions = 0;
            
            foreach ($questions as $q) {
                $qid = $q['id'];
                $raw = $q['correct_answer'] ?? '0';

                // Чистка от двойного JSON
                $correctIndices = @json_decode($raw, true);
                $depth = 0;
                while (is_string($correctIndices) && $depth < 3) {
                    $decoded = @json_decode($correctIndices, true);
                    if ($decoded === null) break;
                    $correctIndices = $decoded;
                    $depth++;
                }
                if (!is_array($correctIndices)) $correctIndices = [$correctIndices];
                $correctIndices = array_map('intval', array_filter($correctIndices));
                $correctIndices = array_values($correctIndices);
                sort($correctIndices);

                // Ответ пользователя
                $userRaw = $answers[$qid] ?? [];
                if (!is_array($userRaw)) $userRaw = [$userRaw];
                $userIndices = array_map('intval', array_filter($userRaw, fn($v) => $v !== null && $v !== '' && $v !== 'undefined'));
                $userIndices = array_values($userIndices);
                sort($userIndices);

                // Подсчёт
                $totalCorrectOptions += count($correctIndices);
                $overlap = array_intersect($correctIndices, $userIndices);
                $userCorrectOptions += count($overlap);

                // Строгая проверка вопроса
                $isQuestionCorrect = ($correctIndices === $userIndices);
                if ($isQuestionCorrect) $correctQuestions++;
                
            }
            
            $success = $totalCorrectOptions > 0 && ($userCorrectOptions / $totalCorrectOptions) >= 0.7;
            
            $completedAt = $success ? date('Y-m-d H:i:s') : null;
            
        } else {
            $success = true;
            $completedAt = date('Y-m-d H:i:s');
        }

        // Сохранение прогресса
        $sql = "INSERT INTO lesson_progress (lesson_id, user_id, completed_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE completed_at = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$lessonId, $user['id'], $completedAt, $completedAt]);
        
        // 🔹 ОТВЕТ С ПОЛНОЙ СТАТИСТИКОЙ ДЛЯ ОТЛАДКИ
        $response = [
            'success' => $success,
            'correct_count' => $userCorrectOptions,
            'total' => $totalCorrectOptions,
            'message' => $success ? 'Тест пройден!' : 'Попробуйте ещё раз'
        ];
        
        
        ob_end_clean();
        jsonResponse($response);
        exit;
        
    } catch (PDOException $e) {
        debugLog('FATAL PDO ERROR', [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], true);
        
        ob_end_clean();
        jsonResponse(['error' => 'Database error', 'debug' => $e->getMessage()], 500);
        exit;
        
    } catch (Exception $e) {
        debugLog('FATAL ERROR', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], true);
        
        ob_end_clean();
        jsonResponse(['error' => 'Server error'], 500);
        exit;
    }
}

// === ОБНОВЛЕНИЕ ПРОФИЛЯ: PUT /profile ===
if ($method === 'PUT' && $path === '/profile') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $body = getJsonBody();

    try {
        //  ТОЧНОЕ ИМЯ КОЛОНКИ ПАРОЛЯ В ВАШЕЙ БД
        $passwordColumn = 'password_hash'; 
        
        // === 1. Обработка роли ===
        $newRole = $user['role']; 
        if (isset($body['role']) && in_array($body['role'], ['teacher', 'user'], true)) {
            $newRole = $body['role'];
        } elseif (isset($body['is_teacher'])) {
            $newRole = $body['is_teacher'] ? 'teacher' : 'user';
        }

        // === 2. Проверка уникальности email ===
        $newEmail = trim($body['email'] ?? $user['email']);
        if ($newEmail !== $user['email']) {
            $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailCheck->execute([$newEmail, $user['id']]);
            if ($emailCheck->fetch()) {
                if (ob_get_level()) ob_end_clean();
                jsonResponse(['error' => 'Этот email уже занят'], 422);
                exit;
            }
        }

        // === 3. Проверка пароля (ТОЛЬКО если пользователь хочет его сменить) ===
        if (!empty($body['new_password'])) {
            if (empty($body['current_password'])) {
                if (ob_get_level()) ob_end_clean();
                jsonResponse(['error' => 'Укажите текущий пароль'], 422);
                exit;
            }
            if (strlen($body['new_password']) < 6) {
                if (ob_get_level()) ob_end_clean();
                jsonResponse(['error' => 'Новый пароль должен содержать минимум 6 символов'], 422);
                exit;
            }
            
            // 🔹 Запрашиваем хеш из колонки password_hash
            $passStmt = $pdo->prepare("SELECT `$passwordColumn` FROM users WHERE id = ?");
            $passStmt->execute([$user['id']]);
            $passRow = $passStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$passRow || !password_verify($body['current_password'], $passRow[$passwordColumn])) {
                if (ob_get_level()) ob_end_clean();
                jsonResponse(['error' => 'Неверный текущий пароль'], 401);
                exit;
            }
        }

        // === 4. Обновление данных ===
        $updateFields = [
            'first_name' => trim($body['first_name'] ?? $user['first_name']),
            'last_name' => trim($body['last_name'] ?? $user['last_name']),
            'email' => $newEmail,
            'bio' => $body['bio'] ?? $user['bio'],
            'about' => $body['about'] ?? $user['about'],
            'is_private' => (int)($body['is_private'] ?? $user['is_private']),
            'role' => $newRole,
            'id' => $user['id'],
        ];
        
        //  Если есть новый пароль — хешируем и добавляем в массив
        if (!empty($body['new_password'])) {
            $updateFields[$passwordColumn] = password_hash($body['new_password'], PASSWORD_DEFAULT);
        }

        // Формируем динамический SQL с экранированием имён колонок
        $setParts = [];
        foreach (array_keys($updateFields) as $field) {
            if ($field !== 'id') {
                $setParts[] = "`$field` = :$field";
            }
        }
        $setParts[] = "updated_at = NOW()";
        
        $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateFields);

        // Возвращаем обновлённые данные (без пароля)
        $updatedStmt = $pdo->prepare('SELECT id, first_name, last_name, email, bio, about, is_private, role, avatar_url FROM users WHERE id = ?');
        $updatedStmt->execute([$user['id']]);
        $updatedUser = $updatedStmt->fetch(PDO::FETCH_ASSOC);

        if (ob_get_level()) ob_end_clean();
        jsonResponse(['message' => 'Profile updated', 'user' => $updatedUser]);
        exit;
        
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        if (ob_get_level()) ob_end_clean();
        jsonResponse(['error' => 'Database error', 'debug' => $e->getMessage()], 500);
        exit;
    }
}

if ($method === 'POST' && $path === '/auth/register') {
    $email = trim($body['email'] ?? '');
    $password = (string)($body['password'] ?? '');
    $role = $body['role'] ?? 'user';

    if (!$email || !$password) {
        jsonResponse(['error' => ' email, password required'], 422);
    }
    if (!in_array($role, ['user', 'teacher'], true)) {
        $role = 'user';
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    try {
        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)');
        $stmt->execute([
            'email' => $email,
            'password_hash' => $hashed,
            'role' => $role,
        ]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Почта уже занята'], 409);
    }

    jsonResponse(['message' => 'Registered']);
}

// ============================================================================
// === МАРШРУТЫ ДЛЯ МОДУЛЕЙ И УРОКОВ ===
// ============================================================================

// === СОЗДАНИЕ МОДУЛЯ: POST /courses/{courseId}/modules ===
if ($method === 'POST' && preg_match('#^/courses/(\d+)/modules$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $courseId = (int)$matches[1];
    
    // Проверка: курс принадлежит пользователю
    $checkStmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :teacher_id');
    $checkStmt->execute(['id' => $courseId, 'teacher_id' => $user['id']]);
    if (!$checkStmt->fetch()) {
        jsonResponse(['error' => 'Course not found or access denied'], 403);
    }
    
    $title = trim($body['title'] ?? '');
    $sortOrder = (int)($body['sort_order'] ?? 0);
    
    if (!$title) {
        jsonResponse(['error' => 'Module title is required'], 422);
    }
    
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO course_modules (course_id, title, sort_order) 
             VALUES (:course_id, :title, :sort_order)'
        );
        $stmt->execute([
            'course_id' => $courseId,
            'title' => $title,
            'sort_order' => $sortOrder,
        ]);
        
        jsonResponse(['id' => $pdo->lastInsertId(), 'message' => 'Module created']);
    } catch (PDOException $e) {
        error_log("Create module error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to create module'], 500);
    }
}

// === ОБНОВЛЕНИЕ МОДУЛЯ: PUT /modules/{moduleId} ===
if ($method === 'PUT' && preg_match('#^/modules/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $moduleId = (int)$matches[1];
    
    $title = trim($body['title'] ?? '');
    $sortOrder = (int)($body['sort_order'] ?? 0);
    
    try {
        // Проверка прав: модуль принадлежит курсу пользователя
        $checkStmt = $pdo->prepare(
            'SELECT cm.id FROM course_modules cm
             JOIN courses c ON c.id = cm.course_id
             WHERE cm.id = :module_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['module_id' => $moduleId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Module not found or access denied'], 403);
        }
        
        $stmt = $pdo->prepare(
            'UPDATE course_modules SET title = :title, sort_order = :sort_order 
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $title,
            'sort_order' => $sortOrder,
            'id' => $moduleId,
        ]);
        
        jsonResponse(['message' => 'Module updated']);
    } catch (PDOException $e) {
        error_log("Update module error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update module'], 500);
    }
}

// === УДАЛЕНИЕ МОДУЛЯ: DELETE /modules/{moduleId} ===
if ($method === 'DELETE' && preg_match('#^/modules/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $moduleId = (int)$matches[1];
    
    try {
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT cm.id FROM course_modules cm
             JOIN courses c ON c.id = cm.course_id
             WHERE cm.id = :module_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['module_id' => $moduleId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Module not found or access denied'], 403);
        }
        
        // Удаляем уроки модуля (CASCADE если настроен, или вручную)
        $pdo->prepare('DELETE FROM lessons WHERE module_id = :module_id')
            ->execute(['module_id' => $moduleId]);
        
        // Удаляем модуль
        $pdo->prepare('DELETE FROM course_modules WHERE id = :id')
            ->execute(['id' => $moduleId]);
        
        jsonResponse(['message' => 'Module deleted']);
    } catch (PDOException $e) {
        error_log("Delete module error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete module'], 500);
    }
}

// === СОЗДАНИЕ УРОКА: POST /modules/{moduleId}/lessons ===
if ($method === 'POST' && preg_match('#^/modules/(\d+)/lessons$#', $path, $matches)) {
    try {
        $user = requireRole($pdo, ['teacher', 'admin']);
        $moduleId = (int)$matches[1];

        // Проверка прав доступа
        $checkStmt = $pdo->prepare(
            'SELECT cm.id FROM course_modules cm
             JOIN courses c ON c.id = cm.course_id
             WHERE cm.id = :module_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['module_id' => $moduleId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Module not found or access denied'], 403);
        }

        // Парсинг тела запроса
        $title = trim($body['title'] ?? '');
        $contentType = $body['content_type'] ?? 'text';
        $lessonType = $body['lesson_type'] ?? 'lesson';
        $content = $body['content'] ?? '';
        $videoUrl = $body['video_url'] ?? '';
        $sortOrder = (int)($body['sort_order'] ?? 0);

        if ($title === '') {
            jsonResponse(['error' => 'Lesson title is required'], 422);
        }

        // Вставка в БД
        $stmt = $pdo->prepare(
            'INSERT INTO lessons (module_id, title, content_type, lesson_type, content, video_url, sort_order) 
             VALUES (:module_id, :title, :content_type, :lesson_type, :content, :video_url, :sort_order)'
        );
        $stmt->execute([
            'module_id' => $moduleId,
            'title' => $title,
            'content_type' => $contentType,
            'lesson_type' => $lessonType,
            'content' => $content,
            'video_url' => $videoUrl,
            'sort_order' => $sortOrder,
        ]);

        // Безопасное получение ID
        $newId = $pdo->lastInsertId() ?: 0;
        
        jsonResponse(['id' => $newId, 'message' => 'Lesson created'], 201);
        exit; // 🔹 Важно: прерываем выполнение после успешного ответа

    } catch (\Throwable $e) {
        // 🔹 Ловим ЛЮБУЮ ошибку (включая PDOException, TypeError, и т.д.)
        error_log("CREATE LESSON ERROR: " . $e->getMessage() . " | File: " . $e->getFile() . ":" . $e->getLine());
        jsonResponse(['error' => 'Failed to create lesson', 'debug' => $e->getMessage()], 500);
        exit;
    }
}

// === ОБНОВЛЕНИЕ УРОКА: PUT /lessons/{lessonId} ===
if ($method === 'PUT' && preg_match('#^/lessons/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $lessonId = (int)$matches[1];
    
    $title = trim($body['title'] ?? '');
    $contentType = $body['content_type'] ?? 'text';
    $lessonType = $body['lesson_type'] ?? 'lesson';
    $content = $body['content'] ?? '';
    $videoUrl = $body['video_url'] ?? '';
    $sortOrder = (int)($body['sort_order'] ?? 0);
    
    try {
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT l.id FROM lessons l
             JOIN course_modules cm ON cm.id = l.module_id
             JOIN courses c ON c.id = cm.course_id
             WHERE l.id = :lesson_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['lesson_id' => $lessonId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Lesson not found or access denied'], 403);
        }
        
        $stmt = $pdo->prepare(
            'UPDATE lessons SET 
                title = :title,
                content_type = :content_type,
                lesson_type = :lesson_type,
                content = :content,
                video_url = :video_url,
                sort_order = :sort_order
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $title,
            'content_type' => $contentType,
            'lesson_type' => $lessonType,
            'content' => $content,
            'video_url' => $videoUrl,
            'sort_order' => $sortOrder,
            'id' => $lessonId,
        ]);
        
        jsonResponse(['message' => 'Lesson updated']);
    } catch (PDOException $e) {
        error_log("Update lesson error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update lesson'], 500);
    }
}

// === УДАЛЕНИЕ УРОКА: DELETE /lessons/{lessonId} ===
if ($method === 'DELETE' && preg_match('#^/lessons/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $lessonId = (int)$matches[1];
    
    try {
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT l.id FROM lessons l
             JOIN course_modules cm ON cm.id = l.module_id
             JOIN courses c ON c.id = cm.course_id
             WHERE l.id = :lesson_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['lesson_id' => $lessonId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Lesson not found or access denied'], 403);
        }
        
        $pdo->prepare('DELETE FROM lessons WHERE id = :id')
            ->execute(['id' => $lessonId]);
        
        jsonResponse(['message' => 'Lesson deleted']);
    } catch (PDOException $e) {
        error_log("Delete lesson error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete lesson'], 500);
    }
}

if ($method === 'GET' && preg_match('#^/courses/(\d+)$#', $path, $matches)) {
    $courseId = (int)$matches[1];
    
    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.title, c.description, c.status, c.price, c.level,
                    c.duration_hours, c.lessons_count, c.video_duration, c.tests_count,
                    c.rating, c.students_count, c.certificate, c.what_you_learn,
                    c.about_course, c.for_whom,
                    CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
             FROM courses c
             JOIN users u ON u.id = c.teacher_id
             WHERE c.id = :id"
        );
        $stmt->execute(['id' => $courseId]);
        $course = $stmt->fetch();
        
        if (!$course) {
            jsonResponse(['error' => 'Not found'], 404);
        }

        // 2. Получаем модули с уроками — 🔹 $pdo!
        $modulesStmt = $pdo->prepare(
            'SELECT m.id as module_id, m.title as module_title, m.sort_order as module_order,
                    l.id as lesson_id, l.title as lesson_title, l.content_type, l.lesson_type, 
                    l.content, l.video_url, l.sort_order as lesson_order
             FROM course_modules m
             LEFT JOIN lessons l ON l.module_id = m.id
             WHERE m.course_id = :course_id
             ORDER BY m.sort_order, l.sort_order'
        );
        $modulesStmt->execute(['course_id' => $courseId]);
        $items = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Группируем уроки по модулям
        $modules = [];
        foreach ($items as $item) {
            $moduleId = $item['module_id'];
            if (!isset($modules[$moduleId])) {
                $modules[$moduleId] = [
                    'id' => $moduleId,
                    'title' => $item['module_title'],
                    'sort_order' => $item['module_order'],
                    'lessons' => []
                ];
            }
            if ($item['lesson_id']) {
                $modules[$moduleId]['lessons'][] = [
                    'id' => $item['lesson_id'],
                    'title' => $item['lesson_title'],
                    'content_type' => $item['content_type'] ?? 'text',
                    'lesson_type' => $item['lesson_type'] ?? 'lesson',
                    'content' => $item['content'],
                    'video_url' => $item['video_url'],
                    'sort_order' => $item['lesson_order'],
                ];
            }
        }

        jsonResponse([
            'course' => $course,
            'modules' => array_values($modules),
        ]);
        
    } catch (PDOException $e) {
        error_log("Course load error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load course'], 500);
    }
}

// ============================================================================
// === QUIZ ANSWERS (вопросы теста) — используем существующую таблицу ===
// ============================================================================

// === ПОЛУЧЕНИЕ вопросов урока: GET /lessons/{lessonId}/quiz ===
if ($method === 'GET' && preg_match('#^/lessons/(\d+)/quiz$#', $path, $matches)) {
    $lessonId = (int)$matches[1];
    
    try {
        $stmt = $pdo->prepare(
            'SELECT id, lesson_id, question, options, correct_answer 
             FROM quiz_answers 
             WHERE lesson_id = :lesson_id 
             ORDER BY id'
        );
        $stmt->execute(['lesson_id' => $lessonId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Декодируем JSON options
        foreach ($questions as &$q) {
            $q['options'] = json_decode($q['options'], true);
    
            // 🔹 Декодируем correct_answer
            $raw = $q['correct_answer'] ?? '0';
            $decoded = @json_decode($raw, true);
            $q['correct_answer'] = is_array($decoded) ? $decoded : [(int)$decoded];
        }
        
        jsonResponse(['questions' => $questions]);
    } catch (PDOException $e) {
        error_log("Get quiz error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load quiz'], 500);
    }
}

// === СОЗДАНИЕ вопроса: ПОЛНАЯ ОТЛАДКА ===
if ($method === 'POST' && preg_match('#^/lessons/(\d+)/quiz$#', $path, $matches)) {
    error_log("=== QUIZ CREATE DEBUG START ===");
    
    try {
        $user = requireRole($pdo, ['teacher', 'admin']);
        $lessonId = (int)$matches[1];
        
        error_log("User: " . $user['id'] . ", Lesson: " . $lessonId);
        
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT l.id FROM lessons l
             JOIN course_modules cm ON cm.id = l.module_id
             JOIN courses c ON c.id = cm.course_id
             WHERE l.id = ? AND c.teacher_id = ?'
        );
        $checkStmt->execute([$lessonId, $user['id']]);
        if (!$checkStmt->fetch()) {
            error_log("Access denied for user {$user['id']} to lesson $lessonId");
            ob_end_clean();
            jsonResponse(['error' => 'Access denied'], 403);
            exit;
        }
        
        $body = getJsonBody();
        error_log("Raw body: " . json_encode($body));
        
        $question = trim($body['question'] ?? '');
        $options = $body['options'] ?? [];
        $correctIndices = $body['correct_answer'] ?? [];
        
        error_log("Parsed: question='$question', options=" . json_encode($options) . ", correct=" . json_encode($correctIndices));
        
        // Валидация
        if (!$question || !is_array($options) || count(array_filter($options, fn($o) => trim($o))) < 2) {
            error_log("Validation failed");
            ob_end_clean();
            jsonResponse(['error' => 'Question and at least 2 non-empty options required'], 422);
            exit;
        }
        
        // Логика для множественного выбора
        if (count($options) <= 2 && is_array($correctIndices) && count($correctIndices) > 1) {
            $correctIndices = [reset($correctIndices)];
        }
        
        $correctAnswerSerialized = json_encode(is_array($correctIndices) ? $correctIndices : [$correctIndices]);
        $optionsSerialized = json_encode(array_map('trim', array_filter($options)));
        
        error_log("Serialized: correct='$correctAnswerSerialized', options='$optionsSerialized'");
        
        // 🔹 Проверка структуры таблицы
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'quiz_answers'")->fetch();
        if (!$tableCheck) {
            error_log("FATAL: Table 'quiz_answers' does not exist!");
            ob_end_clean();
            jsonResponse(['error' => 'Database table missing', 'debug' => 'Table quiz_answers not found'], 500);
            exit;
        }
        
        $cols = $pdo->query("DESCRIBE quiz_answers")->fetchAll(PDO::FETCH_COLUMN);
        error_log("Table columns: " . implode(', ', $cols));
        
        // 🔹 Попытка INSERT
        error_log("Attempting INSERT...");
        $sql = "INSERT INTO quiz_answers (lesson_id, question, options, correct_answer) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE question=?, options=?, correct_answer=?";
        
        error_log("SQL: $sql");
        
        $stmt = $pdo->prepare($sql);
        $params = [$lessonId, $question, $optionsSerialized, $correctAnswerSerialized, $question, $optionsSerialized, $correctAnswerSerialized];
        error_log("Params: " . json_encode($params));
        
        try {
            $stmt->execute($params);
            error_log("✅ INSERT successful. Last ID: " . $pdo->lastInsertId());
        } catch (PDOException $insertErr) {
            error_log("❌ INSERT FAILED: " . $insertErr->getMessage());
            error_log("SQL State: " . json_encode($insertErr->errorInfo));
            throw $insertErr;
        }
        
        // Успех
        $lastId = $pdo->lastInsertId();
        ob_end_clean();
        jsonResponse(['id' => $lastId, 'message' => 'Question created']);
        error_log("=== QUIZ CREATE DEBUG END (SUCCESS) ===");
        exit;
        
    } catch (PDOException $e) {
        error_log("=== FATAL PDO ERROR ===");
        error_log("Message: " . $e->getMessage());
        error_log("Code: " . $e->getCode());
        error_log("File: " . $e->getFile() . ":" . $e->getLine());
        error_log("Trace: " . $e->getTraceAsString());
        error_log("=== END DEBUG ===");
        
        ob_end_clean();
        jsonResponse(['error' => 'Failed to create question', 'debug' => $e->getMessage(), 'sql_state' => $e->errorInfo ?? null], 500);
        exit;
        
    } catch (Exception $e) {
        error_log("=== GENERAL ERROR ===");
        error_log("Message: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        error_log("=== END DEBUG ===");
        
        ob_end_clean();
        jsonResponse(['error' => 'Server error', 'debug' => $e->getMessage()], 500);
        exit;
    }
}

// === ОБНОВЛЕНИЕ вопроса: PUT /quiz/{questionId} ===
if ($method === 'PUT' && preg_match('#^/quiz/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $questionId = (int)$matches[1];
    
    $body = getJsonBody(); // 🔹 Получаем тело запроса
    
    $question = trim($body['question'] ?? '');
    $options = $body['options'] ?? [];
    
    // 🔹 Обработка correct_answer: принимаем массив или строку
    $correctIndices = $body['correct_answer'] ?? [];
    if (!is_array($correctIndices)) {
        // Если пришла строка "[0,2]" — парсим
        $decoded = @json_decode($correctIndices, true);
        $correctIndices = is_array($decoded) ? $decoded : [$correctIndices];
    }
    
    // Для ≤2 вариантов — только один правильный
    if (count($options) <= 2 && count($correctIndices) > 1) {
        $correctIndices = [reset($correctIndices)];
    }
    $correctAnswerSerialized = json_encode($correctIndices);
    
    try {
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT qa.id FROM quiz_answers qa
             JOIN lessons l ON l.id = qa.lesson_id
             JOIN course_modules cm ON cm.id = l.module_id
             JOIN courses c ON c.id = cm.course_id
             WHERE qa.id = :question_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['question_id' => $questionId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Access denied'], 403);
        }
        
        $stmt = $pdo->prepare(
            'UPDATE quiz_answers SET 
                question = :question,
                options = :options,
                correct_answer = :correct_answer
             WHERE id = :id'
        );
        $stmt->execute([
            'question' => $question,
            'options' => json_encode(array_map('trim', array_filter($options))),
            'correct_answer' => $correctAnswerSerialized, // 🔹 Сохраняем как JSON-строку
            'id' => $questionId,
        ]);
        
        jsonResponse(['message' => 'Question updated']);
    } catch (PDOException $e) {
        error_log("Update quiz question error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update question'], 500);
    }
}

// === УДАЛЕНИЕ вопроса: DELETE /quiz/{questionId} ===
if ($method === 'DELETE' && preg_match('#^/quiz/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $questionId = (int)$matches[1];
    
    try {
        // Проверка прав
        $checkStmt = $pdo->prepare(
            'SELECT qa.id FROM quiz_answers qa
             JOIN lessons l ON l.id = qa.lesson_id
             JOIN course_modules cm ON cm.id = l.module_id
             JOIN courses c ON c.id = cm.course_id
             WHERE qa.id = :question_id AND c.teacher_id = :teacher_id'
        );
        $checkStmt->execute(['question_id' => $questionId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Access denied'], 403);
        }
        
        $pdo->prepare('DELETE FROM quiz_answers WHERE id = :id')
            ->execute(['id' => $questionId]);
        
        jsonResponse(['message' => 'Question deleted']);
    } catch (PDOException $e) {
        error_log("Delete quiz question error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete question'], 500);
    }
}

// === ПОЛУЧЕНИЕ вишлиста: GET /user/wishlist ===
if ($method === 'GET' && $path === '/user/wishlist') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    try {
        $sql = "SELECT c.id, c.title, c.description, c.price, c.level, c.status,
                       c.rating, c.students_count, c.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                FROM wishlists w
                JOIN courses c ON c.id = w.course_id
                JOIN users u ON u.id = c.teacher_id
                WHERE w.user_id = :user_id
                ORDER BY w.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        jsonResponse(['courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        error_log("Wishlist fetch error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load wishlist'], 500);
    }
    exit;
}

// === ТОГГЛ вишлиста: POST /courses/{id}/wishlist ===
if ($method === 'POST' && preg_match('#^/courses/(\d+)/wishlist$#', $path, $matches)) {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $courseId = (int)$matches[1];

    try {
        $checkStmt = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = :user_id AND course_id = :course_id');
        $checkStmt->execute(['user_id' => $user['id'], 'course_id' => $courseId]);
        
        if ($checkStmt->fetch()) {
            $pdo->prepare('DELETE FROM wishlists WHERE user_id = :user_id AND course_id = :course_id')
                ->execute(['user_id' => $user['id'], 'course_id' => $courseId]);
            jsonResponse(['in_wishlist' => false, 'message' => 'Удалено']);
        } else {
            $pdo->prepare('INSERT INTO wishlists (user_id, course_id) VALUES (:user_id, :course_id)')
                ->execute(['user_id' => $user['id'], 'course_id' => $courseId]);
            jsonResponse(['in_wishlist' => true, 'message' => 'Добавлено']);
        }
    } catch (PDOException $e) {
        error_log("Wishlist toggle error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update wishlist'], 500);
    }
    exit;
}

// === ПРОВЕРКА статуса вишлиста: GET /courses/{id}/wishlist ===
if ($method === 'GET' && preg_match('#^/courses/(\d+)/wishlist$#', $path, $matches)) {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $courseId = (int)$matches[1];
    
    try {
        $stmt = $pdo->prepare('SELECT id FROM wishlists WHERE user_id = :user_id AND course_id = :course_id');
        $stmt->execute(['user_id' => $user['id'], 'course_id' => $courseId]);
        $inWishlist = $stmt->fetch() !== false;
        
        jsonResponse(['in_wishlist' => $inWishlist]);
    } catch (PDOException $e) {
        error_log("Wishlist check error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to check wishlist'], 500);
    }
    exit;
}

if ($method === 'POST' && $path === '/auth/login') {
    $email = trim($body['email'] ?? '');
    $password = (string)($body['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, role, password_hash, is_teacher, bio, about FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Неверная почта или пароль.'], 401);
    }

    $token = bin2hex(random_bytes(24));
    $insert = $pdo->prepare('INSERT INTO sessions (user_id, token, expires_at) VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 7 DAY))');
    $insert->execute(['user_id' => $user['id'], 'token' => $token]);

    // 🔹 Возвращаем first_name + last_name вместо name
    jsonResponse([
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'email' => $user['email'],
            'role' => $user['role'],
            'bio' => $user['bio'] ?? '',
            'about' => $user['about'] ?? '',
            'is_private' => (bool)($user['is_private'] ?? false),
            'is_teacher' => (bool)($user['is_teacher'] ?? false),
        ],
    ]);
}

if ($method === 'GET' && $path === '/auth/me') {
    $user = currentUser($pdo);
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    jsonResponse([
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'email' => $user['email'],
            'role' => $user['role'],
            'bio' => $user['bio'] ?? '',
            'about' => $user['about'] ?? '',
            'is_private' => (bool)($user['is_private'] ?? false),
            'is_teacher' => (bool)($user['is_teacher'] ?? false),
        ]
    ]);
}

// === Получение списка курсов (МИНИМАЛЬНЫЙ ТЕСТ) ===
if ($method === 'GET' && $path === '/courses') {
    error_log("✅ Matched GET /courses");
    
    try {
        $sql = "SELECT c.id, c.title, c.description, c.status, c.price, c.created_at, 
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                FROM courses c
                INNER JOIN users u ON u.id = c.teacher_id
                WHERE c.status = 'published'
                ORDER BY c.created_at DESC";
        
        $stmt = $pdo->query($sql);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['courses' => $courses]);
        
    } catch (PDOException $e) {
        error_log("❌ Courses query error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load courses'], 500);
    }
}

if ($method === 'POST' && $path === '/courses') {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $title = trim($body['title'] ?? '');
    $description = trim($body['description'] ?? '');
    if (!$title) {
        jsonResponse(['error' => 'title required'], 422);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO courses (title, description, teacher_id, status)
         VALUES (:title, :description, :teacher_id, "draft")'
    );
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'teacher_id' => $user['id'],
    ]);
    jsonResponse(['message' => 'Course created']);
}

// === ПОЛУЧЕНИЕ записей пользователя: GET /user/enrollments ===
if ($method === 'GET' && $path === '/user/enrollments') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    
    try {
        // 1. Получаем базовый список курсов без сложных расчётов
        $stmt = $pdo->prepare(
            "SELECT c.id, c.title, c.description, c.price, c.level, c.status, 
                    c.rating, c.students_count,
                    CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             JOIN users u ON u.id = c.teacher_id
             WHERE e.user_id = :user_id
             ORDER BY c.id DESC"
        );
        $stmt->execute(['user_id' => $user['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Для каждого курса отдельно считаем уроки и прогресс
        // (это надёжнее, чем GROUP BY или подзапросы в SELECT)
        foreach ($courses as &$course) {
            // Общее количество уроков
            $totalStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM course_modules cm 
                 JOIN lessons l ON l.module_id = cm.id 
                 WHERE cm.course_id = :cid"
            );
            $totalStmt->execute(['cid' => $course['id']]);
            $course['total_lessons'] = (int)$totalStmt->fetchColumn();

            // Количество пройденных уроков
            $progressStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM course_modules cm 
                 JOIN lessons l ON l.module_id = cm.id 
                 JOIN lesson_progress lp ON lp.lesson_id = l.id 
                 WHERE cm.course_id = :cid AND lp.user_id = :uid"
            );
            $progressStmt->execute(['cid' => $course['id'], 'uid' => $user['id']]);
            $course['progress'] = (int)$progressStmt->fetchColumn();
        }

        jsonResponse(['courses' => $courses]);
        
    } catch (PDOException $e) {
        error_log("ENROLLMENTS ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        // 🔹 Временно выводим точную ошибку в браузер для отладки
        jsonResponse([
            'error' => 'Failed to load enrollments', 
            'debug' => $e->getMessage()
        ], 500);
    }
    exit;
}

// ============================================================================
// === NOTIFICATIONS ===
// ============================================================================

// === ПОЛУЧЕНИЕ уведомлений: GET /notifications ===
if ($method === 'GET' && $path === '/notifications') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, type, title, message, course_id, course_name, is_read, created_at 
             FROM notifications 
             WHERE user_id = :uid 
             ORDER BY created_at DESC 
             LIMIT 50"
        );
        $stmt->execute(['uid' => $user['id']]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonResponse(['notifications' => $notifications]);
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to load notifications'], 500);
    }
    exit;
}

// === ОТМЕТКА КАК ПРОЧИТАННОЕ: POST /notifications/{id}/read ===
if ($method === 'POST' && preg_match('#^/notifications/(\d+)/read$#', $path, $matches)) {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $notificationId = (int)$matches[1];
    
    try {
        // Проверка: уведомление принадлежит пользователю
        $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE id = :id AND user_id = :uid");
        $checkStmt->execute(['id' => $notificationId, 'uid' => $user['id']]);
        
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Notification not found'], 404);
        }
        
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
        $stmt->execute(['id' => $notificationId, 'uid' => $user['id']]);
        
        jsonResponse(['message' => 'Notification marked as read']);
    } catch (PDOException $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update notification'], 500);
    }
    exit;
}

// === ОТМЕТКА ВСЕХ КАК ПРОЧИТАННЫЕ: POST /notifications/read-all ===
if ($method === 'POST' && $path === '/notifications/read-all') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
        $stmt->execute(['uid' => $user['id']]);
        
        jsonResponse(['message' => 'All notifications marked as read']);
    } catch (PDOException $e) {
        error_log("Mark all notifications read error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update notifications'], 500);
    }
    exit;
}

// === ПОЛУЧЕНИЕ СЕРТИФИКАТОВ ПОЛЬЗОВАТЕЛЯ: GET /user/certificates ===
if ($method === 'GET' && $path === '/user/certificates') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    
    try {
        // Простой и безопасный запрос без сложных JOIN
        $sql = "SELECT cert.id, cert.certificate_code, cert.issued_at, cert.course_id,
                       c.title AS course_title
                FROM certificates cert
                INNER JOIN courses c ON c.id = cert.course_id
                WHERE cert.user_id = :user_id
                ORDER BY cert.issued_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user['id']]);
        
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Форматируем дату для фронтенда
        foreach ($certificates as &$cert) {
            $cert['issued_date'] = date('d.m.Y', strtotime($cert['issued_at']));
        }
        
        jsonResponse(['certificates' => $certificates]);
        
    } catch (PDOException $e) {
        // 🔹 Логируем ошибку для отладки
        error_log("CERTIFICATES ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        
        // Возвращаем детальную ошибку (удалите 'debug' в продакшене)
        jsonResponse([
            'error' => 'Failed to load certificates', 
            'debug' => $e->getMessage()
        ], 500);
    }
    exit;
}

// === ВЫДАЧА СЕРТИФИКАТА: ИСПРАВЛЕННАЯ ВЕРСИЯ ===
if ($method === 'POST' && $path === '/certificates/issue') {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $body = getJsonBody();
    
    $courseId = (int)($body['course_id'] ?? 0);
    $courseTitle = trim($body['course_title'] ?? 'Курс');
    
    if (!$courseId) {
        jsonResponse(['error' => 'Course ID required'], 422);
    }
    
    try {
        // 1. Проверка дубликата
        $checkStmt = $pdo->prepare("SELECT id, certificate_code FROM certificates WHERE user_id = :uid AND course_id = :cid");
        $checkStmt->execute(['uid' => $user['id'], 'cid' => $courseId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            jsonResponse([
                'issued' => false,
                'already_exists' => true,
                'certificate' => $existing
            ]);
            exit;
        }
        
        // 2. Генерация кода
        $certCode = 'CERT-' . strtoupper(substr(md5($user['id'] . $courseId . time()), 0, 8));
        
        // 3. 🔹 INSERT с ПРАВИЛЬНЫМ именем колонки: certificate_code
        $stmt = $pdo->prepare(
            "INSERT INTO certificates (user_id, course_id, certificate_code, issued_at) 
             VALUES (:uid, :cid, :ccode, NOW())"
        );
        $stmt->execute([
            'uid' => $user['id'],
            'cid' => $courseId,
            'ccode' => $certCode  // 🔹 Параметр :ccode
        ]);
        
        $certId = $pdo->lastInsertId();
        
        // 4. Уведомление (не критично)
        try {
            $notifStmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, title, message, course_id, course_name) 
                 VALUES (:uid, 'certificate', :title, :msg, :cid, :cname)"
            );
            $notifStmt->execute([
                'uid' => $user['id'],
                'title' => '🏆 Сертификат получен',
                'msg' => "Поздравляем! Вы завершили курс «{$courseTitle}»",
                'cid' => $courseId,
                'cname' => $courseTitle
            ]);
        } catch (Exception $e) {
            error_log("Notification failed (non-fatal): " . $e->getMessage());
        }
        
        // 5. Успешный ответ
        ob_end_clean();
        jsonResponse([
            'issued' => true,
            'certificate' => [
                'id' => $certId,
                'code' => $certCode,  // 🔹 В ответе фронтенду можно оставить ключ 'code'
                'course_title' => $courseTitle,
                'issued_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
        
    } catch (PDOException $e) {
        error_log("Certificate issue DB error: " . $e->getMessage());
        ob_end_clean();
        jsonResponse(['error' => 'Database error', 'debug' => $e->getMessage()], 500);
        exit;
    }
}

// ============================================================================
// === ADMIN PANEL ROUTES ===
// ============================================================================

// --- USERS ---
if ($method === 'GET' && $path === '/admin/users') {
    requireRole($pdo, ['admin']);
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, role, is_teacher, is_private, avatar_url, created_at FROM users ORDER BY created_at DESC");
    jsonResponse(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST' && $path === '/admin/users') {
    requireRole($pdo, ['admin']);
    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $pass = $body['password'] ?? '';
    if (!$email || !$pass) jsonResponse(['error' => 'Email и пароль обязательны'], 422);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, is_teacher, is_private) VALUES (:fn, :ln, :em, :ph, :ro, :it, :ip)");
        $stmt->execute([
            'fn' => trim($body['first_name'] ?? ''),
            'ln' => trim($body['last_name'] ?? ''),
            'em' => $email,
            'ph' => password_hash($pass, PASSWORD_BCRYPT),
            'ro' => $body['role'] ?? 'user',
            'it' => (int)($body['is_teacher'] ?? 0),
            'ip' => (int)($body['is_private'] ?? 0)
        ]);
        jsonResponse(['message' => 'User created']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Email уже существует или ошибка БД'], 500);
    }
    exit;
}

// === ADMIN: Обновление пользователя ===
if ($method === 'PUT' && preg_match('#^/admin/users/(\d+)$#', $path, $matches)) {
    $admin = requireRole($pdo, ['admin']);
    $userId = (int)$matches[1];
    $body = getJsonBody();

    // 🔹 ЗАЩИТА: нельзя изменить роль текущего пользователя
    if ($userId === $admin['id'] && isset($body['role']) && $body['role'] !== $admin['role']) {
        jsonResponse(['error' => 'Нельзя изменить роль текущего пользователя. Выйдите и войдите под другим аккаунтом.'], 403);
    }

    $email = trim($body['email'] ?? '');
    if (!$email) jsonResponse(['error' => 'Email обязателен'], 422);

    try {
        $fields = "first_name = :fn, last_name = :ln, email = :em, is_teacher = :it";
        $params = [
            'fn' => trim($body['first_name'] ?? ''),
            'ln' => trim($body['last_name'] ?? ''),
            'em' => $email,
            'it' => (int)($body['is_teacher'] ?? 0),
            'id' => $userId
        ];

        // 🔹 Обновляем роль только если она передана и пользователь не редактирует себя
        if (isset($body['role']) && $userId !== $admin['id']) {
            $newRole = $body['role'];
            if (in_array($newRole, ['user', 'teacher', 'admin'], true)) {
                $fields .= ", role = :ro";
                $params['ro'] = $newRole;
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET $fields WHERE id = :id");
        $stmt->execute($params);

        // Возвращаем обновлённые данные
        $updatedStmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, is_teacher, is_private, avatar_url FROM users WHERE id = :id");
        $updatedStmt->execute(['id' => $userId]);
        jsonResponse(['message' => 'User updated', 'user' => $updatedStmt->fetch(PDO::FETCH_ASSOC)]);

    } catch (PDOException $e) {
        error_log("Admin user update error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update user'], 500);
    }
    exit;
}

if ($method === 'DELETE' && preg_match('#^/admin/users/(\d+)$#', $path, $matches)) {
    requireRole($pdo, ['admin']);
    $userId = (int)$matches[1];
    $me = currentUser($pdo);
    if ($userId === $me['id']) jsonResponse(['error' => 'Нельзя удалить себя'], 403);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    jsonResponse(['message' => 'User deleted']);
    exit;
}

// --- COURSES ---
if ($method === 'GET' && $path === '/admin/courses') {
    requireRole($pdo, ['admin']);
    $stmt = $pdo->query("SELECT c.id, c.title, c.description, c.status, c.price, c.level, c.created_at, CONCAT(u.first_name, ' ', u.last_name) AS teacher_name FROM courses c JOIN users u ON u.id = c.teacher_id ORDER BY c.created_at DESC");
    jsonResponse(['courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST' && $path === '/admin/courses') {
    requireRole($pdo, ['admin']);
    $body = getJsonBody();
    if (!trim($body['title'] ?? '')) jsonResponse(['error' => 'Название обязательно'], 422);
    try {
        $stmt = $pdo->prepare("INSERT INTO courses (title, description, teacher_id, status, price, level) VALUES (:t, :d, :tid, :s, :p, :l)");
        $stmt->execute([
            't' => trim($body['title']), 'd' => trim($body['description'] ?? ''),
            'tid' => (int)($body['teacher_id'] ?? 1), 's' => $body['status'] ?? 'draft',
            'p' => floatval($body['price'] ?? 0), 'l' => $body['level'] ?? 'beginner'
        ]);
        jsonResponse(['message' => 'Course created']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'DB Error'], 500);
    }
    exit;
}

if ($method === 'PUT' && preg_match('#^/admin/courses/(\d+)$#', $path, $matches)) {
    requireRole($pdo, ['admin']);
    $courseId = (int)$matches[1];
    $body = getJsonBody();
    try {
        $stmt = $pdo->prepare("UPDATE courses SET title=:t, description=:d, teacher_id=:tid, status=:s, price=:p, level=:l WHERE id=:id");
        $stmt->execute([
            't' => trim($body['title'] ?? ''), 'd' => trim($body['description'] ?? ''),
            'tid' => (int)($body['teacher_id'] ?? 1), 's' => $body['status'] ?? 'draft',
            'p' => floatval($body['price'] ?? 0), 'l' => $body['level'] ?? 'beginner', 'id' => $courseId
        ]);
        jsonResponse(['message' => 'Course updated']);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Failed to update'], 500);
    }
    exit;
}

if ($method === 'DELETE' && preg_match('#^/admin/courses/(\d+)$#', $path, $matches)) {
    requireRole($pdo, ['admin']);
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([(int)$matches[1]]);
    jsonResponse(['message' => 'Course deleted']);
    exit;
}

// === ADMIN: Курсы на модерации ===
if ($method === 'GET' && $path === '/admin/courses/pending') {
    requireRole($pdo, ['admin']);
    $stmt = $pdo->query("SELECT c.id, c.title, c.description, c.price, c.level, c.created_at, 
                                CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                         FROM courses c 
                         JOIN users u ON u.id = c.teacher_id 
                         WHERE c.status = 'pending' 
                         ORDER BY c.created_at DESC");
    jsonResponse(['courses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
} 


// === ADMIN: Смена статуса курса (POST вместо PATCH) ===
if ($method === 'PUT' && preg_match('#^/admin/courses/(\d+)/status$#', $path, $matches)) {
    $admin = requireRole($pdo, ['admin']);
    $courseId = (int)$matches[1];
    $body = getJsonBody();
    
    $newStatus = $body['status'] ?? '';
    if (!in_array($newStatus, ['published', 'draft', 'pending'], true)) {
        jsonResponse(['error' => 'Invalid status'], 422);
    }
    
    try {
        // Получаем данные курса для уведомления
        $courseStmt = $pdo->prepare(
            "SELECT c.id, c.title, c.teacher_id, u.first_name, u.last_name, u.email 
             FROM courses c 
             JOIN users u ON u.id = c.teacher_id 
             WHERE c.id = :id"
        );
        $courseStmt->execute(['id' => $courseId]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            jsonResponse(['error' => 'Course not found'], 404);
        }
        
        // Обновляем статус
        $stmt = $pdo->prepare("UPDATE courses SET status = :status, created_at = NOW() WHERE id = :id");
        $stmt->execute(['status' => $newStatus, 'id' => $courseId]);
        
        // 🔹 === СОЗДАНИЕ УВЕДОМЛЕНИЯ ДЛЯ АВТОРА ===
        $sendNotification = $body['send_notification'] ?? true;
        if ($sendNotification && $course['teacher_id']) {
            $type = $newStatus === 'published' ? 'course_approved' : 'course_rejected';
            $title = $newStatus === 'published' ? '✅ Курс опубликован' : '❌ Курс отправлен на доработку';
            $message = $newStatus === 'published' 
                ? "Ваш курс «{$course['title']}» прошел модерацию и теперь доступен всем учащимся!"
                : "Пожалуйста, внесите правки в курс «{$course['title']}» и отправьте его на повторную проверку.";
            
            try {
                $notifStmt = $pdo->prepare(
                    "INSERT INTO notifications (user_id, type, title, message, course_id, course_name) 
                     VALUES (:uid, :type, :title, :msg, :cid, :cname)"
                );
                $notifStmt->execute([
                    'uid' => $course['teacher_id'],
                    'type' => $type,
                    'title' => $title,
                    'msg' => $message,
                    'cid' => $courseId,
                    'cname' => $course['title']
                ]);
            } catch (Exception $notifErr) {
                error_log("Notification create failed (non-fatal): " . $notifErr->getMessage());
                // Не прерываем выполнение, статус курса уже обновлён
            }
        }
        // 🔹 === КОНЕЦ СОЗДАНИЯ УВЕДОМЛЕНИЯ ===
        
        jsonResponse([
            'message' => 'Course status updated',
            'course' => [
                'id' => $courseId,
                'title' => $course['title'],
                'status' => $newStatus
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Update course status error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update course status'], 500);
    }
    exit;
}

// === ОБНОВЛЕНИЕ КУРСА: PUT /courses/{id} ===
if ($method === 'PUT' && preg_match('#^/courses/(\d+)$#', $path, $matches)) {
    $user = requireRole($pdo, ['teacher', 'admin']);
    $courseId = (int)$matches[1];

    try {
        // 1. Проверка прав доступа
        $checkStmt = $pdo->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :teacher_id');
        $checkStmt->execute(['id' => $courseId, 'teacher_id' => $user['id']]);
        if (!$checkStmt->fetch()) {
            jsonResponse(['error' => 'Course not found or access denied'], 403);
        }

        // 2. Подготовка данных (защита от null и приведение типов)
        $title = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $whatYouLearn = $body['what_you_learn'] ?? ''; // 🔹 TEXT поля: пустая строка вместо null
        $aboutCourse = $body['about_course'] ?? '';
        $forWhom = $body['for_whom'] ?? '';
        $price = floatval($body['price'] ?? 0);
        $level = in_array($body['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $body['level'] : 'beginner';
        $status = in_array($body['status'] ?? '', ['draft', 'pending', 'published'], true) ? $body['status'] : 'draft';
        $certificate = trim($body['certificate'] ?? '');

        // 3. Валидация
        if ($title === '') {
            jsonResponse(['error' => 'Title is required'], 422);
        }

        // 4. Обновление (без updated_at, чтобы не зависеть от структуры БД)
        $stmt = $pdo->prepare(
            'UPDATE courses SET 
                title = :title,
                description = :description,
                what_you_learn = :what_you_learn,
                about_course = :about_course,
                for_whom = :for_whom,
                price = :price,
                level = :level,
                status = :status,
                certificate = :certificate
             WHERE id = :id'
        );
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'what_you_learn' => $whatYouLearn,
            'about_course' => $aboutCourse,
            'for_whom' => $forWhom,
            'price' => $price,
            'level' => $level,
            'status' => $status,
            'certificate' => $certificate,
            'id' => $courseId,
        ]);

        // 5. Возвращаем обновлённые данные
        $updatedStmt = $pdo->prepare(
            'SELECT c.*, CONCAT(u.first_name, " ", u.last_name) AS teacher_name 
             FROM courses c 
             JOIN users u ON u.id = c.teacher_id 
             WHERE c.id = :id'
        );
        $updatedStmt->execute(['id' => $courseId]);
        $updatedCourse = $updatedStmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse(['message' => 'Course updated', 'course' => $updatedCourse]);

    } catch (PDOException $e) {
        // 🔹 Логируем реальную ошибку в файл сервера
        error_log("UPDATE COURSE ERROR [{$courseId}]: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        
        // Возвращаем безопасную ошибку клиенту
        jsonResponse([
            'error' => 'Failed to update course', 
            'debug' => $e->getMessage() // 🔹 Удалите 'debug' в продакшене
        ], 500);
    }
    exit;
}
if ($method === 'GET' && preg_match('#^/courses/(\d+)$#', $path, $matches)) {
    $courseId = (int)$matches[1];
    $stmt = $pdo->prepare(
        "SELECT c.id, c.title, c.description, c.status, c.price, c.level,
               c.duration_hours, c.lessons_count, c.video_duration, c.tests_count,
               c.rating, c.students_count, c.certificate, c.what_you_learn,
               c.about_course, c.for_whom,
               CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        WHERE c.id = :id"
    );
    $stmt->execute(['id' => $courseId]);
    $course = $stmt->fetch();
    
    if (!$course) {
        jsonResponse(['error' => 'Not found'], 404);
    }

    // Получаем уроки
    $lessonsStmt = $pdo->prepare(
        'SELECT l.id, l.title, l.content, l.content_type, l.sort_order, m.title as module_title
         FROM lessons l
         JOIN course_modules m ON m.id = l.module_id
         WHERE m.course_id = :course_id
         ORDER BY m.sort_order, l.sort_order'
    );
    $lessonsStmt->execute(['course_id' => $courseId]);
    $lessons = $lessonsStmt->fetchAll();

    // Группируем уроки по модулям
    $modules = [];
    foreach ($lessons as $lesson) {
        $moduleName = $lesson['module_title'];
        if (!isset($modules[$moduleName])) {
            $modules[$moduleName] = [];
        }
        $modules[$moduleName][] = $lesson;
    }

    jsonResponse([
        'course' => $course,
        'lessons' => $lessons,
        'modules' => $modules,
    ]);
}

if ($method === 'POST' && preg_match('#^/courses/(\d+)/enroll$#', $path, $matches)) {
    $user = requireRole($pdo, ['user', 'teacher', 'admin']);
    $courseId = (int)$matches[1];
    $stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (:user_id, :course_id)');
    $stmt->execute(['user_id' => $user['id'], 'course_id' => $courseId]);
    jsonResponse(['message' => 'Enrolled']);
}

// === Курсы преподавателя ===
if ($method === 'GET' && $path === '/teacher/courses') {
    
    $user = requireRole($pdo, ['teacher', 'admin']);
    
    try {
        $sql = "SELECT c.id, c.title, c.description, c.status, c.price, c.created_at,
                       CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                FROM courses c
                JOIN users u ON u.id = c.teacher_id
                WHERE c.teacher_id = :teacher_id
                ORDER BY c.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['teacher_id' => $user['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("✅ Found " . count($courses) . " courses for teacher " . $user['id']);
        
        jsonResponse(['courses' => $courses]);
        
    } catch (PDOException $e) {
        error_log("❌ Teacher courses error: " . $e->getMessage());
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

if ($method === 'GET' && $path === '/admin/users') {
    requireRole($pdo, ['admin']);
    $stmt = $pdo->query('SELECT id, email, role, created_at FROM users ORDER BY created_at DESC');
    jsonResponse(['users' => $stmt->fetchAll()]);
}

jsonResponse(['error' => 'Route not found'], 404);
