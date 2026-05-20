<?php

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $body = json_decode($raw, true);
    return is_array($body) ? $body : [];
}

function getBearerToken(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function createNotification($pdo, $userId, $type, $title, $message, $courseId = null, $courseName = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, course_id, course_name)
            VALUES (:uid, :type, :title, :msg, :cid, :cname)
        ");
        $stmt->execute([
            'uid' => $userId,
            'type' => $type,
            'title' => $title,
            'msg' => $message,
            'cid' => $courseId,
            'cname' => $courseName
        ]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}
