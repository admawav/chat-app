<?php
// classes/User.php

require_once '../config.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Rejestracja użytkownika
    public function register($username, $email, $password, $fullName, $invitationToken = null) {
        try {
            // Sprawdź czy użytkownik już istnieje
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Użytkownik o tym adresie email lub nazwie użytkownika już istnieje'];
            }
            
            // Walidacja danych
            if (strlen($password) < 6) {
                return ['success' => false, 'message' => 'Hasło musi mieć co najmniej 6 znaków'];
            }
            
            if (!isValidEmail($email)) {
                return ['success' => false, 'message' => 'Nieprawidłowy adres email'];
            }
            
            $hashedPassword = hashPassword($password);
            
            $this->db->beginTransaction();
            
            // Dodaj użytkownika
            $stmt = $this->db->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword, $fullName]);
            
            $userId = $this->db->lastInsertId();
            
            // Jeśli rejestracja przez zaproszenie
            if ($invitationToken) {
                $stmt = $this->db->prepare("SELECT inviter_id FROM email_invitations WHERE token = ? AND is_used = FALSE AND expires_at > NOW()");
                $stmt->execute([$invitationToken]);
                $invitation = $stmt->fetch();
                
                if ($invitation) {
                    // Dodaj zapraszającego do znajomych
                    $this->addFriend($userId, $invitation['inviter_id'], true);
                    
                    // Oznacz zaproszenie jako użyte
                    $stmt = $this->db->prepare("UPDATE email_invitations SET is_used = TRUE WHERE token = ?");
                    $stmt->execute([$invitationToken]);
                }
            }
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Rejestracja zakończona pomyślnie', 'user_id' => $userId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas rejestracji'];
        }
    }
    
    // Logowanie użytkownika
    public function login($email, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, email, password, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !verifyPassword($password, $user['password'])) {
                return ['success' => false, 'message' => 'Nieprawidłowy email lub hasło'];
            }
            
            // Aktualizuj status online
            $this->updateOnlineStatus($user['id'], true);
            
            // Utwórz sesję
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            
            return [
                'success' => true,
                'message' => 'Logowanie pomyślne',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas logowania'];
        }
    }
    
    // Wylogowanie użytkownika
    public function logout($userId) {
        $this->updateOnlineStatus($userId, false);
        session_destroy();
        return ['success' => true, 'message' => 'Wylogowano pomyślnie'];
    }
    
    // Aktualizacja statusu online
    public function updateOnlineStatus($userId, $isOnline) {
        $stmt = $this->db->prepare("UPDATE users SET is_online = ?, last_seen = NOW() WHERE id = ?");
        $stmt->execute([$isOnline, $userId]);
    }
    
    // Zaproszenie znajomego
    public function inviteFriend($userId, $email) {
        try {
            // Sprawdź czy użytkownik o tym emailu już istnieje
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // Jeśli użytkownik istnieje, dodaj go bezpośrednio do znajomych
                return $this->addFriend($userId, $existingUser['id']);
            } else {
                // Jeśli nie istnieje, wyślij zaproszenie email
                $token = generateToken();
                
                $stmt = $this->db->prepare("INSERT INTO email_invitations (inviter_id, email, token) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $email, $token]);
                
                // Pobierz dane zapraszającego
                $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $inviter = $stmt->fetch();
                
                // Wyślij email z zaproszeniem
                $subject = "Zaproszenie do Chat App";
                $body = "
                    <h2>Zaproszenie do Chat App</h2>
                    <p>{$inviter['full_name']} zaprasza Cię do dołączenia do Chat App!</p>
                    <p>Kliknij poniższy link, aby się zarejestrować:</p>
                    <a href='" . SITE_URL . "/register.php?token={$token}'>Dołącz do Chat App</a>
                    <p>Link jest ważny przez 7 dni.</p>
                ";
                
                if (sendEmail($email, $subject, $body)) {
                    return ['success' => true, 'message' => 'Zaproszenie zostało wysłane'];
                } else {
                    return ['success' => false, 'message' => 'Błąd podczas wysyłania zaproszenia'];
                }
            }
            
        } catch (Exception $e) {
            error_log("Invite friend error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas zapraszania znajomego'];
        }
    }
    
    // Dodawanie znajomego
    public function addFriend($userId, $friendId, $autoAccept = false) {
        try {
            // Sprawdź czy już nie są znajomymi
            $stmt = $this->db->prepare("SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->execute([$userId, $friendId, $friendId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Już jesteście znajomymi lub zaproszenie jest w toku'];
            }
            
            $status = $autoAccept ? 'accepted' : 'pending';
            
            // Dodaj zaproszenie
            $stmt = $this->db->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $friendId, $status]);
            
            if ($autoAccept) {
                // Dodaj również odwrotną relację
                $stmt = $this->db->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
                $stmt->execute([$friendId, $userId]);
            }
            
            return ['success' => true, 'message' => $autoAccept ? 'Znajomy został dodany' : 'Zaproszenie zostało wysłane'];
            
        } catch (Exception $e) {
            error_log("Add friend error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas dodawania znajomego'];
        }
    }
    
    // Akceptowanie zaproszenia do znajomych
    public function acceptFriendRequest($userId, $friendId) {
        try {
            $this->db->beginTransaction();
            
            // Aktualizuj status zaproszenia
            $stmt = $this->db->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
            $stmt->execute([$friendId, $userId]);
            
            // Dodaj odwrotną relację
            $stmt = $this->db->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
            $stmt->execute([$userId, $friendId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Zaproszenie zostało zaakceptowane'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Accept friend error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Wystąpił błąd podczas akceptowania zaproszenia'];
        }
    }
    
    // Pobieranie listy znajomych
    public function getFriends($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.full_name, u.is_online, u.last_seen, f.status
            FROM friends f
            JOIN users u ON f.friend_id = u.id
            WHERE f.user_id = ?
            ORDER BY u.is_online DESC, u.last_seen DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    // Pobieranie zaproszeń do znajomych
    public function getFriendRequests($userId) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.full_name, f.created_at
            FROM friends f
            JOIN users u ON f.user_id = u.id
            WHERE f.friend_id = ? AND f.status = 'pending'
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    // Sprawdzanie czy użytkownik jest zalogowany
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Pobieranie danych zalogowanego użytkownika
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name']
        ];
    }
}
?>