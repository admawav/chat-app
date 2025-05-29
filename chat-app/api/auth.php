<?php
// api/auth.php

require_once '../config.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$user = new User();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Wszystkie pola są wymagane']);
                break;
            }
            
            $result = $user->login($email, $password);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $fullName = sanitizeInput($_POST['fullName'] ?? '');
            $invitationToken = sanitizeInput($_GET['token'] ?? '');
            
            if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
                echo json_encode(['success' => false, 'message' => 'Wszystkie pola są wymagane']);
                break;
            }
            
            $result = $user->register($username, $email, $password, $fullName, $invitationToken);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $result = $user->logout($userId);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'status':
        if ($user->isLoggedIn()) {
            $currentUser = $user->getCurrentUser();
            echo json_encode(['success' => true, 'user' => $currentUser]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Nieznana akcja']);
        break;
}
?>