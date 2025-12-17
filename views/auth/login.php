<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = loginUser($username, $password);
        
        if ($result === true) {
            redirectByRole();
        } elseif ($result === "account_pending") {
            $message = "Your account is pending approval. Please wait for staff approval.";
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartLibrary | Academic Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <a href="../../landing.php" class="login-brand">
                <div class="login-seal">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="login-brand-text">
                    <h1>Smart<span style="color: var(--accent-burgundy);">Library</span></h1>
                    <p>Academic Library Management System</p>
                </div>
            </a>
        </div>
        
        <div class="login-wrapper">
            <!-- Welcome Panel -->
            <div class="login-welcome">
                <div class="welcome-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h2 class="welcome-title">Welcome Back to SmartLibrary</h2>
                <p class="welcome-text">
                    Access your academic resources, manage your borrowing history, 
                    and explore our digital collection with ease.
                </p>
                
                <div class="welcome-features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Access digital resources and e-books</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Manage your borrowing history</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Receive automated due date alerts</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Reserve unavailable items</span>
                    </div>
                </div>
                
                <div class="login-info">
                    <p><i class="fas fa-info-circle"></i> For institutional support, contact your campus library administration.</p>
                </div>
            </div>
            
            <!-- Login Form -->
            <div class="login-form-section">
                <h2 class="form-title"><i class="fas fa-sign-in-alt"></i> Library System Login</h2>
                
                <?php if ($error): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Authentication Error</strong>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="message info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Account Status</strong>
                            <p><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i> Username
                        </label>
                        <div class="input-with-icon">
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-input"
                                   placeholder="Enter your username"
                                   required>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-with-icon">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="form-input"
                                   placeholder="Enter your password"
                                   required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-link">
                            Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login to Account
                    </button>
                </form>
                
                <div class="register-link">
                    <p>Don't have an account?</p>
                    <a href="register.php" class="register-btn">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
                
                <div class="back-home">
                    <a href="../../landing.php">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </a>
                </div>
            </div>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. All rights reserved.</p>
            <p style="margin-top: 5px; font-size: 0.8rem; opacity: 0.7;">
                <i class="fas fa-shield-alt"></i> Secure academic system. Unauthorized access is prohibited.
            </p>
        </div>
    </div>
    
    <script>
            document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const lockIcon = passwordInput.parentElement.querySelector('.fa-lock');
            
            if (passwordInput && lockIcon) {
                const toggle = document.createElement('i');
                toggle.className = 'fas fa-eye password-toggle';
                toggle.style.cssText = 'cursor: pointer; margin-left: 10px; color: var(--slate); position: absolute; right: 16px; top: 50%; transform: translateY(-50%);';
                
                toggle.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    toggle.className = type === 'password' ? 'fas fa-eye password-toggle' : 'fas fa-eye-slash password-toggle';
                });
                
                passwordInput.parentElement.appendChild(toggle);
            }
        });
    </script>
</body>
</html>