<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? 'student';
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        $result = registerUser($username, $email, $password, $full_name, $role);
        
        if ($result === true) {
            $success = 'Registration successful! Your account is pending approval by staff.';
            $_POST = array();
        } else {
            $error = 'Registration failed: ' . $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartLibrary | Academic Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/register.css">
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <a href="../../landing.php" class="register-brand">
                <div class="register-seal">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="register-brand-text">
                    <h1>Smart<span style="color: var(--accent-burgundy);">Library</span></h1>
                    <p>Academic Library Management System</p>
                </div>
            </a>
        </div>
        
        <div class="register-wrapper">
            <!-- Info Panel -->
            <div class="register-info">
                <div class="info-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2 class="info-title">Join Our Academic Community</h2>
                <p class="info-text">
                    Register for access to institutional resources, borrowing privileges, 
                    and digital library services designed for educational excellence.
                </p>
                
                <div class="role-display">
                    <h3><i class="fas fa-users"></i> Available Roles</h3>
                    <div class="role-cards">
                        <div class="role-card">
                            <i class="fas fa-user-graduate"></i>
                            <h4>Student</h4>
                            <p>Access to general collection</p>
                        </div>
                        <div class="role-card">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h4>Teacher</h4>
                            <p>Extended borrowing period</p>
                        </div>
                        <div class="role-card">
                            <i class="fas fa-book-reader"></i>
                            <h4>Librarian</h4>
                            <p>Manage Book Archives</p>
                        </div>
                    </div>
                </div>
                
                <div class="registration-note">
                    <p><i class="fas fa-exclamation-triangle"></i> All registrations require verification by library staff. You'll receive an email once approved.</p>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="register-form-section">
                <h2 class="form-title"><i class="fas fa-user-plus"></i> Create Academic Account</h2>
                
                <?php if ($error): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <strong>Registration Error</strong>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>Registration Successful</strong>
                            <p><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registrationForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name" class="form-label">
                                <i class="fas fa-id-card"></i> Full Name
                            </label>
                            <div class="input-with-icon">
                                <input type="text" 
                                       id="full_name" 
                                       name="full_name" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                                       placeholder="Enter your full name"
                                       required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Username
                            </label>
                            <div class="input-with-icon">
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       placeholder="Choose a username"
                                       required>
                                <i class="fas fa-at input-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <div class="input-with-icon">
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="Enter institutional email"
                                   required>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                        <small class="input-help">Use your institutional email address</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <div class="input-with-icon">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-input"
                                       placeholder="Create password (min. 6 chars)"
                                       required>
                                <i class="fas fa-lock input-icon"></i>
                            </div>
                            <div id="passwordStrength" class="password-strength" style="display: none;">
                                <div class="strength-bar">
                                    <div class="strength-fill"></div>
                                </div>
                                <div class="strength-text">Password strength: <span id="strengthText">None</span></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i> Confirm Password
                            </label>
                            <div class="input-with-icon">
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-input"
                                       placeholder="Confirm password"
                                       required>
                                <i class="fas fa-lock input-icon"></i>
                            </div>
                            <div id="passwordMatch" style="display: none; font-size: 0.85rem; margin-top: 6px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="role" class="form-label">
                            <i class="fas fa-user-tag"></i> Institutional Role
                        </label>
                        <div class="role-select">
                            <select id="role" name="role" class="form-select">
                                <option value="student" <?php echo ($_POST['role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>>Teacher / Faculty</option>
                                <option value="librarian" <?php echo ($_POST['role'] ?? '') === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                            </select>
                            <i class="fas fa-chevron-down select-icon"></i>
                        </div>
                        <small class="input-help">Select your primary role within the institution</small>
                    </div>
                    
                    <div class="form-agreement">
                        <label class="terms-agreement">
                            <input type="checkbox" name="terms" required>
                            <span>I agree to the <a href="#" class="terms-link">Library Terms of Service</a> and confirm that I am affiliated with this institution.</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="register-btn">
                        <i class="fas fa-user-plus"></i> Complete Registration
                    </button>
                </form>
                
                <div class="login-link">
                    <p>Already have an account?</p>
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login to Existing Account
                    </a>
                </div>
                
                <div class="back-home">
                    <a href="../../landing.php">
                        <i class="fas fa-arrow-left"></i> Back to Homepage
                    </a>
                </div>
            </div>
        </div>
        
        <div class="register-footer">
            <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. All rights reserved.</p>
            <p style="margin-top: 5px; font-size: 0.8rem; opacity: 0.7;">
                <i class="fas fa-shield-alt"></i> Your information is secured and used only for institutional purposes.
            </p>
        </div>
    </div>
    
    <script src="../../assets/js/auth.js"></script>
</body>
</html>