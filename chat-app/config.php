
<?php
// config.php - Kompletna konfiguracja dla Render.com + PostgreSQL

// Konfiguracja bazy danych PostgreSQL
$database_url = parse_url(getenv('DATABASE_URL'));

define('DB_HOST', $database_url['host'] ?? 'localhost');
define('DB_USER', $database_url['user'] ?? 'postgres');
define('DB_PASS', $database_url['pass'] ?? '');
define('DB_NAME', ltrim($database_url['path'] ?? '/chat_app', '/'));
define('DB_PORT', $database_url['port'] ?? 5432);

// Konfiguracja aplikacji
define('SITE_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'render-secret-key-2024');

// Konfiguracja email (opcjonalne)
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('FROM_NAME', getenv('FROM_NAME') ?: 'Chat App');

// Konfiguracja Node.js Socket.IO
define('SOCKET_SERVER_URL', getenv('SOCKET_SERVER_URL') ?: 'http://localhost:3000');

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // PostgreSQL connection string
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
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
    // Sprawdź czy SMTP jest skonfigurowany
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        error_log("SMTP not configured, email not sent to: " . $to);
        return false;
    }

    // Prosta implementacja wysyłania emaili
    $headers = array();
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    if ($isHTML) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// Funkcja do walidacji email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Funkcja do sanityzacji danych
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Funkcja do logowania błędów
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' Context: ' . json_encode($context) : '';
    error_log("[{$timestamp}] {$message}{$contextStr}");
}

// Funkcja do sprawdzania uprawnień
function checkPermission($requiredRole = 'user') {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    $roles = ['user' => 1, 'admin' => 2];
    $userRole = $_SESSION['role'] ?? 'user';
    
    return $roles[$userRole] >= $roles[$requiredRole];
}

// Funkcja do generowania CSRF tokenu
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Funkcja do weryfikacji CSRF tokenu
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Funkcja do rate limiting
function checkRateLimit($identifier, $limit = 10, $window = 60) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    $data = $_SESSION[$key];
    
    if (time() > $data['reset_time']) {
        $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + $window];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// Rozpoczęcie sesji z bezpiecznymi ustawieniami
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);

session_start();

// Regeneruj ID sesji co jakiś czas
if (!isset($_SESSION['session_started'])) {
    session_regenerate_id(true);
    $_SESSION['session_started'] = time();
} elseif (time() - $_SESSION['session_started'] > 1800) { // 30 minut
    session_regenerate_id(true);
    $_SESSION['session_started'] = time();
}

// Ustawienie nagłówków bezpieczeństwa
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Ustawienie nagłówków CORS
$allowed_origins = [
    'http://localhost:3000',
    'http://localhost:8000',
    'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Obsługa preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Ustawienie domyślnej strefy czasowej
date_default_timezone_set('Europe/Warsaw');

// Konfiguracja obsługi błędów
if (getenv('APP_ENV') === 'production') {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Funkcja do sprawdzania środowiska
function isProduction() {
    return getenv('APP_ENV') === 'production';
}

// Funkcja do zwracania JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Funkcja do zwracania błędu JSON
function jsonError($message, $status = 400, $details = null) {
    $response = ['success' => false, 'message' => $message];
    if ($details && !isProduction()) {
        $response['details'] = $details;
    }
    jsonResponse($response, $status);
}

// Funkcja do zwracania sukcesu JSON
function jsonSuccess($message, $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

// Sprawdzenie połączenia z bazą danych przy pierwszym ładowaniu
try {
    Database::getInstance();
} catch (Exception $e) {
    if (!isProduction()) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        logError("Database connection failed: " . $e->getMessage());
        die("Service temporarily unavailable");
    }
}
?>
