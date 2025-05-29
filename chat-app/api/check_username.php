<?php
// api/check-username.php

require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
    exit;
}

$username = sanitizeInput($_POST['username'] ?? '');

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Nazwa użytkownika jest wymagana']);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 20) {
    echo json_encode(['success' => false, 'message' => 'Nazwa użytkownika musi mieć od 3 do 20 znaków']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => 'Nazwa użytkownika może zawierać tylko litery, cyfry i podkreślniki']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    $available = $stmt->rowCount() === 0;
    
    echo json_encode([
        'success' => true,
        'available' => $available,
        'message' => $available ? 'Nazwa użytkownika jest dostępna' : 'Nazwa użytkownika jest już zajęta'
    ]);
    
} catch (Exception $e) {
    error_log("Check username error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas sprawdzania nazwy użytkownika']);
}
?>