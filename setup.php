<?php
// setup.php - JEDNORAZOWY plik do tworzenia tabel w bazie danych
// ⚠️ USUŃ TEN PLIK PO UŻYCIU! ⚠️

require_once 'config.php';

echo "<h1>🚀 Setup bazy danych - Chat App</h1>";
echo "<p>Tworzenie tabel...</p>";

// Pełny kod SQL do tworzenia wszystkich tabel
$sql = "
-- Tabela użytkowników
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela znajomych
CREATE TABLE IF NOT EXISTS friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user_id, friend_id)
);

-- Tabela zaproszeń email
CREATE TABLE IF NOT EXISTS email_invitations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inviter_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
    FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela wiadomości
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabela sesji (opcjonalna)
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
";

// Tablica z indeksami do dodania
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_friends_user_id ON friends(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_friends_friend_id ON friends(friend_id)", 
    "CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id)",
    "CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id)",
    "CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at)",
    "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
    "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)"
];

try {
    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    
    // Pobierz połączenie z bazą danych
    $db = Database::getInstance()->getConnection();
    
    echo "✅ <strong>Połączenie z bazą danych:</strong> OK<br>";
    
    // Wykonaj główny SQL (tworzenie tabel)
    $db->exec($sql);
    echo "✅ <strong>Tworzenie tabel:</strong> OK<br>";
    
    // Dodaj indeksy jeden po drugim
    foreach ($indexes as $index) {
        try {
            $db->exec($index);
            echo "✅ <strong>Indeks:</strong> " . substr($index, 0, 50) . "...<br>";
        } catch (Exception $e) {
            echo "⚠️ <strong>Indeks (pominięty):</strong> " . $e->getMessage() . "<br>";
        }
    }
    
    echo "</div>";
    
    // Sprawdź czy tabele zostały utworzone
    echo "<h2>📋 Sprawdzenie utworzonych tabel:</h2>";
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px;'>";
    
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = ['users', 'friends', 'email_invitations', 'messages', 'user_sessions'];
    
    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ <strong>Tabela '{$table}':</strong> ISTNIEJE<br>";
        } else {
            echo "❌ <strong>Tabela '{$table}':</strong> BRAK<br>";
        }
    }
    
    echo "</div>";
    
    // Sprawdź liczbę rekordów w każdej tabeli
    echo "<h2>📊 Status tabel:</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM `{$table}`");
            $count = $stmt->fetchColumn();
            echo "📄 <strong>Tabela '{$table}':</strong> {$count} rekordów<br>";
        } catch (Exception $e) {
            echo "⚠️ <strong>Tabela '{$table}':</strong> błąd odczytu<br>";
        }
    }
    
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;'>";
    echo "<h2>🎉 SUKCES!</h2>";
    echo "<p><strong>Baza danych została pomyślnie skonfigurowana!</strong></p>";
    echo "<p>Możesz teraz:</p>";
    echo "<ul style='text-align: left; display: inline-block;'>";
    echo "<li>✅ Testować rejestrację użytkowników</li>";
    echo "<li>✅ Testować logowanie</li>";
    echo "<li>✅ Testować wysyłanie wiadomości</li>";
    echo "<li>⚠️ <strong>USUŃ ten plik setup.php ze względów bezpieczeństwa!</strong></li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h2>❌ BŁĄD!</h2>";
    echo "<p><strong>Nie udało się utworzyć tabel:</strong></p>";
    echo "<p style='color: red; font-family: monospace;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>🔧 Możliwe przyczyny:</h3>";
    echo "<ul>";
    echo "<li>❌ Zmienna DATABASE_URL nie jest ustawiona w Railway</li>";
    echo "<li>❌ MySQL nie działa lub nie jest dostępny</li>";
    echo "<li>❌ Błąd w config.php</li>";
    echo "<li>❌ Problem z uprawnieniami bazy danych</li>";
    echo "</ul>";
    echo "<p><strong>Sprawdź:</strong></p>";
    echo "<ol>";
    echo "<li>Czy MySQL działa w Railway (zielony status)</li>";
    echo "<li>Czy zmienna DATABASE_URL jest ustawiona w PHP aplikacji</li>";
    echo "<li>Czy config.php jest prawidłowo skonfigurowany</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #666; font-size: 12px;'>";
echo "Chat App Setup Script | Wygenerowano: " . date('Y-m-d H:i:s');
echo "</p>";
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
}

h1 {
    color: #2c3e50;
    text-align: center;
}

h2 {
    color: #34495e;
    border-bottom: 2px solid #3498db;
    padding-bottom: 5px;
}

ul, ol {
    line-height: 1.6;
}

code {
    background: #e9ecef;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
}
</style>