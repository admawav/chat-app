<?php
// classes/Message.php

require_once '../config.php';

class Message {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Wysyłanie wiadomości
    public function sendMessage($senderId, $receiverId, $message) {
        try {
            // Sprawdź czy użytkownicy są znajomymi
            $stmt = $this->db->prepare("
                SELECT id FROM friends 
                WHERE user_id = ? AND friend_id = ? AND status = 'accepted'
            ");
            $stmt->execute([$senderId, $receiverId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Możesz wysyłać wiadomości tylko do znajomych'];
            }
            
            // Dodaj wiadomość do bazy danych
            $stmt = $this->db->prepare("
                INSERT INTO messages (sender_id, receiver_id, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$senderId, $receiverId, $message]);
            
            $messageId = $this->db->lastInsertId();
            
            // Pobierz szczegóły wiadomości
            $stmt = $this->db->prepare("
                SELECT m.*, u.username as sender_username, u.full_name as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([$messageId]);
            $messageData = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => 'Wiadomość została wysłana',
                'data' => $messageData
            ];
            
        } catch (Exception $e) {
            error_log("Send message error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas wysyłania wiadomości'];
        }
    }
    
    // Pobieranie konwersacji między dwoma użytkownikami
    public function getConversation($userId1, $userId2, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       s.username as sender_username, 
                       s.full_name as sender_name,
                       r.username as receiver_username, 
                       r.full_name as receiver_name
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId1, $userId2, $userId2, $userId1, $limit, $offset]);
            
            $messages = $stmt->fetchAll();
            
            // Odwróć kolejność, żeby najnowsze były na końcu
            return array_reverse($messages);
            
        } catch (Exception $e) {
            error_log("Get conversation error: " . $e->getMessage());
            return [];
        }
    }
    
    // Oznaczanie wiadomości jako przeczytane
    public function markAsRead($userId, $senderId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE
            ");
            $stmt->execute([$userId, $senderId]);
            
            return ['success' => true, 'affected_rows' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas oznaczania wiadomości'];
        }
    }
    
    // Pobieranie nieprzeczytanych wiadomości
    public function getUnreadMessages($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, 
                       u.username as sender_username, 
                       u.full_name as sender_name,
                       COUNT(*) as unread_count
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.receiver_id = ? AND m.is_read = FALSE
                GROUP BY m.sender_id
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get unread messages error: " . $e->getMessage());
            return [];
        }
    }
    
    // Pobieranie ostatnich konwersacji użytkownika
    public function getRecentConversations($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN m.sender_id = ? THEN m.receiver_id 
                        ELSE m.sender_id 
                    END as contact_id,
                    CASE 
                        WHEN m.sender_id = ? THEN r.username 
                        ELSE s.username 
                    END as contact_username,
                    CASE 
                        WHEN m.sender_id = ? THEN r.full_name 
                        ELSE s.full_name 
                    END as contact_name,
                    CASE 
                        WHEN m.sender_id = ? THEN r.is_online 
                        ELSE s.is_online 
                    END as contact_online,
                    m.message as last_message,
                    m.created_at as last_message_time,
                    m.sender_id = ? as is_sent_by_me,
                    SUM(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 ELSE 0 END) as unread_count
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.sender_id = ? OR m.receiver_id = ?
                GROUP BY contact_id
                ORDER BY m.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([
                $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $limit
            ]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get recent conversations error: " . $e->getMessage());
            return [];
        }
    }
    
    // Usuwanie wiadomości
    public function deleteMessage($messageId, $userId) {
        try {
            // Sprawdź czy użytkownik jest właścicielem wiadomości
            $stmt = $this->db->prepare("
                SELECT id FROM messages 
                WHERE id = ? AND sender_id = ?
            ");
            $stmt->execute([$messageId, $userId]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Nie możesz usunąć tej wiadomości'];
            }
            
            // Usuń wiadomość
            $stmt = $this->db->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            
            return ['success' => true, 'message' => 'Wiadomość została usunięta'];
            
        } catch (Exception $e) {
            error_log("Delete message error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas usuwania wiadomości'];
        }
    }
    
    // Wyszukiwanie wiadomości
    public function searchMessages($userId, $query, $contactId = null) {
        try {
            $sql = "
                SELECT m.*, 
                       s.username as sender_username, 
                       s.full_name as sender_name,
                       r.username as receiver_username, 
                       r.full_name as receiver_name
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE (m.sender_id = ? OR m.receiver_id = ?) 
                  AND m.message LIKE ?
            ";
            
            $params = [$userId, $userId, "%{$query}%"];
            
            if ($contactId) {
                $sql .= " AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))";
                $params = array_merge($params, [$userId, $contactId, $contactId, $userId]);
            }
            
            $sql .= " ORDER BY m.created_at DESC LIMIT 50";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Search messages error: " . $e->getMessage());
            return [];
        }
    }
}
?>