<?php
// register.php - Strona rejestracji z obsługą zaproszeń

require_once 'config.php';

$invitationToken = $_GET['token'] ?? '';
$invitationData = null;

// Sprawdź token zaproszenia jeśli istnieje
if (!empty($invitationToken)) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ei.*, u.full_name as inviter_name 
            FROM email_invitations ei
            JOIN users u ON ei.inviter_id = u.id
            WHERE ei.token = ? AND ei.is_used = FALSE AND ei.expires_at > NOW()
        ");
        $stmt->execute([$invitationToken]);
        $invitationData = $stmt->fetch();
        
        if (!$invitationData) {
            $error = "Zaproszenie jest nieprawidłowe lub wygasło.";
        }
    } catch (Exception $e) {
        error_log("Invitation check error: " . $e->getMessage());
        $error = "Wystąpił błąd podczas sprawdzania zaproszenia.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - Chat App</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .register-header .subtitle {
            color: #7f8c8d;
            font-size: 14px;
        }

        .invitation-info {
            background: #e8f5e8;
            border: 1px solid #27ae60;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .invitation-info i {
            color: #27ae60;
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }

        .invitation-info h3 {
            color: #27ae60;
            margin-bottom: 5px;
        }

        .invitation-info p {
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #e74c3c; width: 25%; }
        .strength-fair { background: #f39c12; width: 50%; }
        .strength-good { background: #f1c40f; width: 75%; }
        .strength-strong { background: #27ae60; width: 100%; }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid #e9ecef;
        }

        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .terms {
            font-size: 12px;
            color: #7f8c8d;
            text-align: center;
            margin-top: 15px;
            line-height: 1.4;
        }

        .terms a {
            color: #3498db;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> Dołącz do Chat App</h1>
            <p class="subtitle">Stwórz konto i zacznij rozmawiać z znajomymi</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($invitationData): ?>
            <div class="invitation-info">
                <i class="fas fa-envelope-open"></i>
                <h3>Zaproszenie od <?= htmlspecialchars($invitationData['inviter_name']) ?></h3>
                <p>Zostałeś zaproszony do dołączenia do Chat App!</p>
            </div>
        <?php endif; ?>

        <div id="alertContainer"></div>

        <form id="registerForm">
            <?php if ($invitationToken): ?>
                <input type="hidden" name="invitation_token" value="<?= htmlspecialchars($invitationToken) ?>">
                <div class="form-group">
                    <label for="email">Adres email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($invitationData['email'] ?? '') ?>" required readonly>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="email">Adres email</label>
                    <input type="email" id="email" name="email" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="username">Nazwa użytkownika</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="20" pattern="[a-zA-Z0-9_]+" title="Tylko litery, cyfry i podkreślniki">
            </div>

            <div class="form-group">
                <label for="fullName">Imię i nazwisko</label>
                <input type="text" id="fullName" name="fullName" required minlength="2" maxlength="50">
            </div>

            <div class="form-group">
                <label for="password">Hasło</label>
                <input type="password" id="password" name="password" required minlength="6">
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span id="strengthText">Wprowadź hasło</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword">Potwierdź hasło</label>
                <input type="password" id="confirmPassword" name="confirmPassword" required>
                <div id="passwordMatch" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-user-plus"></i> Utwórz konto
            </button>
        </form>

        <div class="terms">
            Tworząc konto, akceptujesz nasze <a href="#" onclick="showTerms()">Warunki korzystania</a> 
            i <a href="#" onclick="showPrivacy()">Politykę prywatności</a>.
        </div>

        <div class="login-link">
            Masz już konto? <a href="index.html">Zaloguj się</a>
        </div>
    </div>

    <script>
        // Sprawdzanie siły hasła
        function checkPasswordStrength(password) {
            let score = 0;
            let feedback = "";

            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            if (password.length === 0) {
                strengthFill.className = 'strength-fill';
                strengthText.textContent = 'Wprowadź hasło';
                return;
            }

            if (score < 3) {
                strengthFill.className = 'strength-fill strength-weak';
                feedback = 'Słabe hasło';
            } else if (score < 4) {
                strengthFill.className = 'strength-fill strength-fair';
                feedback = 'Przeciętne hasło';
            } else if (score < 5) {
                strengthFill.className = 'strength-fill strength-good';
                feedback = 'Dobre hasło';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                feedback = 'Silne hasło';
            }

            strengthText.textContent = feedback;
        }

        // Sprawdzanie zgodności haseł
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
                return true;
            }

            if (password === confirmPassword) {
                matchDiv.innerHTML = '<span style="color: #27ae60;"><i class="fas fa-check"></i> Hasła są zgodne</span>';
                return true;
            } else {
                matchDiv.innerHTML = '<span style="color: #e74c3c;"><i class="fas fa-times"></i> Hasła nie są zgodne</span>';
                return false;
            }
        }

        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });

        document.getElementById('confirmPassword').addEventListener('input', checkPasswordMatch);

        // Sprawdzanie dostępności nazwy użytkownika
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            
            clearTimeout(usernameTimeout);
            
            if (username.length >= 3) {
                usernameTimeout = setTimeout(() => {
                    fetch('api/check-username.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'username=' + encodeURIComponent(username)
                    })
                    .then(response => response.json())
                    .then(data => {
                        const usernameInput = document.getElementById('username');
                        if (data.available) {
                            usernameInput.style.borderColor = '#27ae60';
                        } else {
                            usernameInput.style.borderColor = '#e74c3c';
                        }
                    })
                    .catch(error => console.error('Username check error:', error));
                }, 500);
            }
        });

        // Obsługa formularza rejestracji
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            // Sprawdź zgodność haseł
            if (password !== confirmPassword) {
                showAlert('Hasła nie są zgodne', 'error');
                return;
            }

            // Sprawdź siłę hasła
            if (password.length < 6) {
                showAlert('Hasło musi mieć co najmniej 6 znaków', 'error');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Tworzenie konta...';

            const formData = new FormData(this);

            fetch('api/auth.php?action=register', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Konto zostało utworzone pomyślnie! Możesz się teraz zalogować.', 'success');
                    setTimeout(() => {
                        window.location.href = 'index.html';
                    }, 2000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Register error:', error);
                showAlert('Wystąpił błąd podczas rejestracji. Spróbuj ponownie.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `<div class="alert alert-${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}
            </div>`;
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }

        function showTerms() {
            alert('Warunki korzystania - ta funkcja zostanie wkrótce dodana.');
        }

        function showPrivacy() {
            alert('Polityka prywatności - ta funkcja zostanie wkrótce dodana.');
        }

        // Auto-focus na pierwszym polu
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input:not([readonly])');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>