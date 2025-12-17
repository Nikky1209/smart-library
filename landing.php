<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartLibrary | Academic Library Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>
    <!-- Academic Header -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <!-- Logo with academic seal -->
                <a href="/" class="library-brand">
                    <div class="seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span class="highlight">Library</span></h1>
                        <p class="tagline">Academic Library Management System</p>
                    </div>
                </a>

                <!-- Main Navigation -->
                <nav class="academic-nav">
                    <ul class="nav-menu">
                        <li><a href="#features"><i class="fas fa-star"></i> Features</a></li>
                        <li><a href="#access"><i class="fas fa-users"></i> Access Levels</a></li>
                        <li><a href="#process"><i class="fas fa-list-ol"></i> Process</a></li>
                        <li class="nav-divider"></li>
                        <li><a href="views/auth/login.php" class="nav-login">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a></li>
                        <li><a href="views/auth/register.php" class="nav-register">
                            <i class="fas fa-user-plus"></i> Register
                        </a></li>
                    </ul>
                </nav>

                <button class="mobile-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h2 class="hero-title">Academic Excellence Through <span class="highlight">Organized Knowledge</span></h2>
                    <p class="hero-subtitle">A comprehensive library management system designed specifically for educational institutions to streamline resource management and enhance learning.</p>
                    
                    <div class="hero-actions">
                        <a href="views/auth/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Access Portal
                        </a>
                        <a href="#features" class="btn btn-outline">
                            <i class="fas fa-book-open"></i> Explore Features
                        </a>
                    </div>

                    <div class="academic-stats">
                        <div class="stat">
                            <i class="fas fa-university"></i>
                            <div>
                                <h3>250+</h3>
                                <p>Institutions</p>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-book"></i>
                            <div>
                                <h3>500K+</h3>
                                <p>Resources</p>
                            </div>
                        </div>
                        <div class="stat">
                            <i class="fas fa-user-graduate"></i>
                            <div>
                                <h3>50K+</h3>
                                <p>Active Users</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="hero-image">
                    <div class="library-scene">
                        <div class="book-stack">
                            <div class="book"></div>
                            <div class="book"></div>
                            <div class="book"></div>
                        </div>
                        <div class="reading-area">
                            <i class="fas fa-user-graduate"></i>
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features -->
    <section id="features" class="core-features">
        <div class="container">
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Core <span class="highlight">Features</span></h2>
                <p>Essential tools for modern academic library management</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Digital Catalog</h3>
                    <p>Comprehensive database of all library resources with advanced search capabilities and filtering options.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3>Circulation Management</h3>
                    <p>Streamlined borrowing, returning, and renewal processes with automated notifications and due date tracking.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Advanced Search</h3>
                    <p>Powerful search engine with filters for author, title, ISBN, subject, and publication year.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Usage Analytics</h3>
                    <p>Detailed reports on resource utilization, popular subjects, and user engagement metrics.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Automated Alerts</h3>
                    <p>Email and system notifications for due dates, reservation availability, and new arrivals.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon academic">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Access Control</h3>
                    <p>Role-based permissions ensuring appropriate access levels for students, faculty, and staff.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Access Levels -->
    <section id="access" class="access-levels">
        <div class="container">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Access <span class="highlight">Levels</span></h2>
                <p>Tailored interfaces for different user roles within the academic community</p>
            </div>

            <div class="access-grid">
                <div class="access-card student">
                    <div class="access-header">
                        <i class="fas fa-user-graduate"></i>
                        <h3>Students</h3>
                    </div>
                    <ul>
                        <li><i class="fas fa-check"></i> Borrow up to 5 items for 14 days</li>
                        <li><i class="fas fa-check"></i> Access digital resources</li>
                        <li><i class="fas fa-check"></i> View borrowing history</li>
                        <li><i class="fas fa-check"></i> Reserve unavailable items</li>
                        <li><i class="fas fa-check"></i> Renew items online</li>
                    </ul>
                    <div class="access-footer">
                        <a href="views/auth/register.php?role=student" class="btn-access">Register as Student</a>
                    </div>
                </div>

                <div class="access-card faculty">
                    <div class="access-header">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <h3>Faculty</h3>
                    </div>
                    <ul>
                        <li><i class="fas fa-check"></i> Borrow up to 10 items for 30 days</li>
                        <li><i class="fas fa-check"></i> Course reserve management</li>
                        <li><i class="fas fa-check"></i> Request new acquisitions</li>
                        <li><i class="fas fa-check"></i> Priority access to resources</li>
                        <li><i class="fas fa-check"></i> Extended renewal periods</li>
                    </ul>
                    <div class="access-footer">
                        <a href="views/auth/register.php?role=faculty" class="btn-access">Register as Faculty</a>
                    </div>
                </div>

                <div class="access-card librarian">
                    <div class="access-header">
                        <i class="fas fa-book-reader"></i>
                        <h3>Librarians</h3>
                    </div>
                    <ul>
                        <li><i class="fas fa-check"></i> Full catalog management</li>
                        <li><i class="fas fa-check"></i> Circulation oversight</li>
                        <li><i class="fas fa-check"></i> User account management</li>
                        <li><i class="fas fa-check"></i> System configuration</li>
                        <li><i class="fas fa-check"></i> Report generation</li>
                    </ul>
                    <div class="access-footer">
                        <span class="note">Librarian access requires administrative approval</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Getting Started -->
    <section id="process" class="getting-started">
        <div class="container">
            <div class="section-header">
                <h2><i class="fas fa-list-ol"></i> Getting <span class="highlight">Started</span></h2>
                <p>Simple steps to begin using the library system</p>
            </div>

            <div class="process-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Account Registration</h3>
                        <p>Complete the registration form with your institutional email and select your role (Student, Faculty, or Librarian).</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Verification</h3>
                        <p>Your registration will be verified by library staff to confirm your institutional affiliation.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Access Approval</h3>
                        <p>Once verified, you'll receive login credentials and can access the system immediately.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Start Exploring</h3>
                        <p>Begin searching resources, borrowing items, and utilizing all available features.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Access -->
    <section class="quick-access">
        <div class="container">
            <div class="access-cta">
                <h2>Ready to Access Library Resources?</h2>
                <p>Join your academic community in managing and discovering knowledge resources efficiently.</p>
                
                <div class="cta-buttons">
                    <a href="views/auth/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login to Existing Account
                    </a>
                    <a href="views/auth/register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>

                <div class="institutional-info">
                    <p><i class="fas fa-info-circle"></i> For institutional inquiries or technical support, contact your campus library administration.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Academic Footer -->
    <footer class="academic-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-section">
                    <div class="footer-brand">
                        <i class="fas fa-graduation-cap"></i>
                        <div>
                            <h3>SmartLibrary</h3>
                            <p>Academic Library Management System</p>
                        </div>
                    </div>
                    <p class="mission">Supporting educational excellence through efficient resource management and accessibility.</p>
                </div>

                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="views/auth/login.php"><i class="fas fa-arrow-right"></i> Login Portal</a></li>
                        <li><a href="views/auth/register.php"><i class="fas fa-arrow-right"></i> Registration</a></li>
                        <li><a href="#features"><i class="fas fa-arrow-right"></i> System Features</a></li>
                        <li><a href="#access"><i class="fas fa-arrow-right"></i> User Roles</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#"><i class="fas fa-question-circle"></i> Help Desk</a></li>
                        <li><a href="#"><i class="fas fa-file-alt"></i> Documentation</a></li>
                        <li><a href="#"><i class="fas fa-book"></i> User Guides</a></li>
                        <li><a href="#"><i class="fas fa-envelope"></i> Contact Support</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Institution</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-map-marker-alt"></i> Library Administration Building</p>
                        <p><i class="fas fa-clock"></i> Mon-Fri: 8:00 AM - 6:00 PM</p>
                        <p><i class="fas fa-envelope"></i> library@institution.edu</p>
                        <p><i class="fas fa-phone"></i> (123) 456-7890</p>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. All rights reserved.</p>
                <p class="disclaimer">This system is for educational institution use only. Unauthorized access is prohibited.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript remains exactly the same -->
    <script src="http://localhost/smart-library/assets/js/landing.js"></script>
</body>
</html>