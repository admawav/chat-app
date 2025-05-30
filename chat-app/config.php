<?php
// config.php - Konfiguracja systemu

// Konfiguracja bazy danych
define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PASS', 'XIYoTjZqJnpifinpLfnqzsMcgywZIVuU');
define('DB_NAME', 'railway');

// Konfiguracja aplikacji
define('SITE_URL', 'http://localhost');
define('JWT_SECRET', 'your_secret_key_here_change_in_production');

// Konfiguracja email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_password');
define('FROM_EMAIL', 'your_email@gmail.com');
define('FROM_NAME', 'Chat App');

// Konfiguracja Node.js Socket.IO
define('SOCKET_SERVER_URL', 'http://localhost:3000');

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Błąd połączenia z bazą danych: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Funkcja do hashowania haseł
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Funkcja do weryfikacji hasła
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Funkcja do generowania tokenu
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Funkcja do wysyłania emaili
function sendEmail($to, $subject, $body, $isHTML = true) {
    require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

// Funkcja do walidacji email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Funkcja do sanityzacji danych
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Rozpoczęcie sesji
session_start();

// Ustawienie nagłówków CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>
