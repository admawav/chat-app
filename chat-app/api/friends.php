<?php
// api/friends.php

require_once '../config.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

$user = new User();

// Sprawdź czy użytkownik jest zalogowany
if (!$user->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Użytkownik nie jest zalogowany']);
    exit;
}

$currentUser = $user->getCurrentUser();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'invite':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($email) || !isValidEmail($email)) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy adres email']);
                break;
            }
            
            if ($email === $currentUser['email']) {
                echo json_encode(['success' => false, 'message' => 'Nie możesz zaprosić samego siebie']);
                break;
            }
            
            $result = $user->inviteFriend($currentUser['id'], $email);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            if ($friendId === $currentUser['id']) {
                echo json_encode(['success' => false, 'message' => 'Nie możesz dodać samego siebie']);
                break;
            }
            
            $result = $user->addFriend($currentUser['id'], $friendId);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'accept':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            $result = $user->acceptFriendRequest($currentUser['id'], $friendId);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'reject':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
                $stmt->execute([$friendId, $currentUser['id']]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Zaproszenie zostało odrzucone']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Nie znaleziono zaproszenia do odrzucenia']);
                }
            } catch (Exception $e) {
                error_log("Reject friend error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas odrzucania zaproszenia']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'remove':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            try {
                $db = Database::getInstance()->getConnection();
                $db->beginTransaction();
                
                // Usuń obie relacje znajomości
                $stmt = $db->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
                $stmt->execute([$currentUser['id'], $friendId, $friendId, $currentUser['id']]);
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Znajomy został usunięty']);
                
            } catch (Exception $e) {
                $db->rollback();
                error_log("Remove friend error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas usuwania znajomego']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'list':
        try {
            $friends = $user->getFriends($currentUser['id']);
            $requests = $user->getFriendRequests($currentUser['id']);
            
            echo json_encode([
                'success' => true, 
                'friends' => $friends,
                'requests' => $requests
            ]);
        } catch (Exception $e) {
            error_log("Get friends list error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas pobierania listy znajomych']);
        }
        break;
        
    case 'search':
        $query = sanitizeInput($_GET['query'] ?? '');
        
        if (empty($query)) {
            echo json_encode(['success' => false, 'message' => 'Puste zapytanie wyszukiwania']);
            break;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT id, username, full_name, email, is_online
                FROM users 
                WHERE (username LIKE ? OR full_name LIKE ? OR email LIKE ?) 
                  AND id != ?
                ORDER BY is_online DESC, full_name ASC
                LIMIT 20
            ");
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $currentUser['id']]);
            
            $results = $stmt->fetchAll();
            echo json_encode(['success' => true, 'users' => $results]);
            
        } catch (Exception $e) {
            error_log("Search users error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas wyszukiwania']);
        }
        break;
        
    case 'status':
        $friendId = intval($_GET['friend_id'] ?? 0);
        
        if ($friendId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
            break;
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Sprawdź status znajomości
            $stmt = $db->prepare("
                SELECT status FROM friends 
                WHERE user_id = ? AND friend_id = ?
            ");
            $stmt->execute([$currentUser['id'], $friendId]);
            $friendship = $stmt->fetch();
            
            // Pobierz informacje o użytkowniku
            $stmt = $db->prepare("
                SELECT id, username, full_name, is_online, last_seen
                FROM users WHERE id = ?
            ");
            $stmt->execute([$friendId]);
            $friendInfo = $stmt->fetch();
            
            if (!$friendInfo) {
                echo json_encode(['success' => false, 'message' => 'Użytkownik nie został znaleziony']);
                break;
            }
            
            $friendshipStatus = $friendship ? $friendship['status'] : 'none';
            
            echo json_encode([
                'success' => true,
                'friend' => $friendInfo,
                'friendship_status' => $friendshipStatus
            ]);
            
        } catch (Exception $e) {
            error_log("Get friend status error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas pobierania statusu znajomego']);
        }
        break;
        
    case 'block':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            try {
                $db = Database::getInstance()->getConnection();
                
                // Sprawdź czy znajomość istnieje
                $stmt = $db->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ?");
                $stmt->execute([$currentUser['id'], $friendId]);
                
                if ($stmt->rowCount() > 0) {
                    // Aktualizuj status na 'blocked'
                    $stmt = $db->prepare("UPDATE friends SET status = 'blocked' WHERE user_id = ? AND friend_id = ?");
                    $stmt->execute([$currentUser['id'], $friendId]);
                } else {
                    // Dodaj nowy wpis z statusem 'blocked'
                    $stmt = $db->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'blocked')");
                    $stmt->execute([$currentUser['id'], $friendId]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Użytkownik został zablokowany']);
                
            } catch (Exception $e) {
                error_log("Block user error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas blokowania użytkownika']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    case 'unblock':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $friendId = intval($_POST['friend_id'] ?? 0);
            
            if ($friendId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Nieprawidłowy ID znajomego']);
                break;
            }
            
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'blocked'");
                $stmt->execute([$currentUser['id'], $friendId]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Użytkownik został odblokowany']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Użytkownik nie był zablokowany']);
                }
                
            } catch (Exception $e) {
                error_log("Unblock user error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Wystąpił błąd podczas odblokowywania użytkownika']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nieprawidłowa metoda żądania']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Nieznana akcja']);
        break;
}
?>