<?php
// api/messages.php

require_once '../config.php';
require_once '../classes/User.php';
require_once '../classes/Message.php';

header('Content-Type: application/json');

$user = new User();
$message = new Message();

// Sprawdź czy użytkownik jest zalogowany
if (!$user->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
    exit;
}

$currentUser = $user->getCurrentUser();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $receiverId = intval($_POST['receiver_id'] ?? 0);
            $messageText = sanitizeInput($_POST['message'] ?? '');
            
            if (empty($messageText) || $receiverId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowe dane wiadomości']);
                break;
            }
            
            $result = $message->sendMessage($currentUser['id'], $receiverId, $messageText);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'conversation':
        $userId = intval($_GET['user_id'] ?? 0);
        $limit = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID użytkownika']);
            break;
        }
        
        $messages = $message->getConversation($currentUser['id'], $userId, $limit, $offset);
        echo json_encode(['success' => true, 'messages' => $messages]);
        break;
        
    case 'recent':
        $limit = intval($_GET['limit'] ?? 10);
        $conversations = $message->getRecentConversations($currentUser['id'], $limit);
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        break;
        
    case 'unread':
        $unreadMessages = $message->getUnreadMessages($currentUser['id']);
        echo json_encode(['success' => true, 'messages' => $unreadMessages]);
        break;
        
    case 'mark_read':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $senderId = intval($_POST['sender_id'] ?? 0);
            
            if ($senderId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID nadawcy']);
                break;
            }
            
            $result = $message->markAsRead($currentUser['id'], $senderId);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $messageId = intval($_POST['message_id'] ?? 0);
            
            if ($messageId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID wiadomości']);
                break;
            }
            
            $result = $message->deleteMessage($messageId, $currentUser['id']);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'search':
        $query = sanitizeInput($_GET['query'] ?? '');
        $contactId = intval($_GET['contact_id'] ?? 0);
        
        if (empty($query)) {
            echo json_encode(['success' => false, 'message' => 'Puste zapytanie wyszukiwania']);
            break;
        }
        
        $results = $message->searchMessages($currentUser['id'], $query, $contactId > 0 ? $contactId : null);
        echo json_encode(['success' => true, 'messages' => $results]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Nieznana akcja']);
        break;
}
?>