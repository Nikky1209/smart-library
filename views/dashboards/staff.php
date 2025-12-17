<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if ($_SESSION['role'] !== 'staff') {
    header("Location: dashboard.php");
    exit();
}

$pending_users = getPendingUsers();

$conn = getDBConnection();
$all_users_query = "SELECT 
    u.id, 
    u.username, 
    u.email, 
    u.full_name, 
    u.role, 
    u.approved, 
    u.registration_date,
    u.approval_date,
    s.username as approved_by_username,
    s.full_name as approved_by_name
FROM users u
LEFT JOIN users s ON u.approved_by = s.id
ORDER BY u.registration_date DESC 
LIMIT 20";
$all_users = $conn->query($all_users_query);

$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE approved = FALSE) as pending_users,
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM books) as total_books,
    (SELECT COUNT(*) FROM borrowing_records WHERE status = 'borrowed') as borrowed_books";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - SmartLibrary Academic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Academic Dashboard Styles - Matches Landing Page */
        :root {
            --primary-dark: #1a365d;
            --primary-main: #2d4a8a;
            --primary-light: #4a6fac;
            --accent-gold: #d4af37;
            --accent-burgundy: #800020;
            --ivory: #fffff0;
            --parchment: #f5f5dc;
            --slate: #708090;
            --charcoal: #36454f;
            --success: #2e7d32;
            --warning: #ed6c02;
            --error: #d32f2f;
            --info: #0288d1;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: var(--ivory);
            color: var(--charcoal);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Academic Header */
        .academic-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-main));
            color: white;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-bottom: 3px solid var(--accent-gold);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-brand {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: white;
        }
        
        .dashboard-seal {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--accent-gold);
        }
        
        .dashboard-seal i {
            font-size: 1.5rem;
        }
        
        .brand-text h1 {
            font-family: 'Merriweather', serif;
            font-size: 1.8rem;
            margin-bottom: 3px;
        }
        
        .brand-text p {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .staff-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-greeting {
            text-align: right;
        }
        
        .user-greeting span {
            display: block;
            font-weight: 600;
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--accent-gold);
            color: var(--primary-dark);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 30px 0;
            background: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.95)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="%232d4a8a" opacity="0.03"/></svg>');
        }
        
        .dashboard-header {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .dashboard-header h1 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .dashboard-header p {
            color: var(--slate);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-main), var(--primary-light));
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon i {
            font-size: 1.8rem;
            color: white;
        }
        
        .stat-header h3 {
            font-size: 1.2rem;
            color: var(--charcoal);
        }
        
        .stat-content {
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--slate);
            font-size: 0.95rem;
        }
        
        .card-actions {
            text-align: center;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 25px;
            background: var(--primary-main);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            border: 2px solid var(--primary-main);
        }
        
        .action-btn:hover {
            background: transparent;
            color: var(--primary-main);
            transform: translateY(-2px);
        }
        
        /* Table Container */
        .academic-table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .table-header h2 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px 10px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-main);
            box-shadow: 0 0 0 3px rgba(45, 74, 138, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate);
        }
        
        /* Tables */
        .academic-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .academic-table thead {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
        }
        
        .academic-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .academic-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .academic-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .academic-table tbody tr:hover {
            background-color: rgba(45, 74, 138, 0.03);
        }
        
        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .role-badge-small {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .student-badge {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .teacher-badge {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .librarian-badge {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        
        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-action {
            padding: 6px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-approve {
            background-color: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn-reject {
            background-color: var(--error);
            color: white;
        }
        
        .btn-reject:hover {
            background-color: #c62828;
            transform: translateY(-2px);
        }
        
        /* System Management */
        .system-management {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .system-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            text-align: center;
        }
        
        .system-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        
        .system-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .system-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .system-card h3 {
            color: var(--primary-dark);
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .system-card p {
            color: var(--slate);
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        /* Academic Footer */
        .academic-footer {
            background: var(--charcoal);
            color: white;
            padding: 30px 0 20px;
            margin-top: auto;
        }
        
        .footer-content {
            text-align: center;
        }
        
        .footer-content p {
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .footer-content small {
            opacity: 0.7;
            font-size: 0.85rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--slate);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--charcoal);
            margin-bottom: 10px;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .system-management {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .staff-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .user-greeting {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .academic-table-container {
                padding: 20px;
            }
            
            .academic-table {
                display: block;
                overflow-x: auto;
            }
            
            .academic-table th,
            .academic-table td {
                padding: 10px;
                font-size: 0.9rem;
            }
            
            .table-actions {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-header h1 {
                font-size: 2rem;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .system-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Academic Header -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <a href="#" class="dashboard-brand">
                    <div class="dashboard-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span style="color: var(--accent-gold);">Library</span></h1>
                        <p>Academic Staff Portal</p>
                    </div>
                </a>
                
                <div class="staff-info">
                    <div class="user-greeting">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                        <span class="role-badge">Staff Administrator</span>
                    </div>
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="dashboard-header">
                <h1>Staff Administration Dashboard</h1>
                <p>Manage user accounts, monitor system activity, and oversee library operations</p>
            </div>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h3>Pending Approvals</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['pending_users']; ?></div>
                        <div class="stat-label">Users Awaiting Review</div>
                    </div>
                    <div class="card-actions">
                        <a href="#pending-approvals" class="action-btn">Review Now</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Total Users</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Registered in System</div>
                    </div>
                    <div class="card-actions">
                        <a href="#all-users" class="action-btn">View All</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>Library Collection</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_books']; ?></div>
                        <div class="stat-label">Books in Catalog</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <h3>Active Borrowings</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['borrowed_books']; ?></div>
                        <div class="stat-label">Currently Loaned Out</div>
                    </div>
                </div>
            </div>
            
            <!-- Pending User Approvals -->
            <div class="academic-table-container" id="pending-approvals">
                <div class="table-header">
                    <h2><i class="fas fa-user-check"></i> Pending User Approvals</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search pending users...">
                    </div>
                </div>
                
                <?php if (count($pending_users) > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pending_users as $user): ?>
                        <tr id="user-row-<?php echo $user['id']; ?>">
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge-small <?php echo $user['role']; ?>-badge">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                            <td class="table-actions">
                                <button class="btn-action btn-approve" onclick="handleUserAction(<?php echo $user['id']; ?>, 'approve')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-action btn-reject" onclick="handleUserAction(<?php echo $user['id']; ?>, 'reject')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-check"></i>
                    <h3>No Pending Approvals</h3>
                    <p>All user registrations have been processed. Check back later for new requests.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- All Users -->
            <div class="academic-table-container" id="all-users">
                <div class="table-header">
                    <h2><i class="fas fa-users"></i> All System Users</h2>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search users...">
                    </div>
                </div>
                
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registration Date</th>
                            <th>Approval Date</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $all_users->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge-small <?php echo $user['role']; ?>-badge">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['approved'] ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo $user['approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></td>
                            <td>
                                <?php if ($user['approval_date']): ?>
                                    <?php echo date('M d, Y', strtotime($user['approval_date'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--slate); opacity: 0.6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['approved_by_name']): ?>
                                    <div>
                                        <div><?php echo htmlspecialchars($user['approved_by_name']); ?></div>
                                        <small style="color: var(--slate); font-size: 0.8rem;">
                                            @<?php echo htmlspecialchars($user['approved_by_username']); ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--slate); opacity: 0.6;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- System Management -->
            <div class="system-management">
                <div class="system-card">
                    <div class="system-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>System Configuration</h3>
                    <p>Manage library settings, borrowing rules, and system parameters</p>
                    <div class="card-actions">
                        <a href="settings.php" class="action-btn">Configure System</a>
                    </div>
                </div>
                
                <div class="system-card">
                    <div class="system-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Analytics & Reports</h3>
                    <p>Generate detailed reports and analyze library usage statistics</p>
                    <div class="card-actions">
                        <a href="reports.php" class="action-btn">View Analytics</a>
                    </div>
                </div>
                
                <div class="system-card">
                    <div class="system-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Security & Access</h3>
                    <p>Manage user permissions, security settings, and access controls</p>
                    <div class="card-actions">
                        <a href="security.php" class="action-btn">Security Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Academic Footer -->
    <footer class="academic-footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. All rights reserved.</p>
                <p>Staff Administration Portal - Version 2.1</p>
                <small><i class="fas fa-shield-alt"></i> Secure institutional system. Unauthorized access prohibited.</small>
            </div>
        </div>
    </footer>
    
    <script>
        // User approval/rejection functionality
        function handleUserAction(userId, action) {
            if (!confirm(`Are you sure you want to ${action} this user?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('action', action);
            
            fetch('../../includes/approve_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    showNotification(`User ${action}ed successfully!`, 'success');
                    
                    // Remove row from table
                    const row = document.getElementById(`user-row-${userId}`);
                    if (row) {
                        row.remove();
                        
                        // Update pending count
                        updatePendingCount();
                    }
                    
                    // If no more pending users, show empty state
                    const pendingTable = document.querySelector('#pending-approvals table tbody');
                    if (pendingTable && pendingTable.children.length === 0) {
                        showEmptyState();
                    }
                } else {
                    showNotification(`Error: ${data.message}`, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
        
        // Update pending count
        function updatePendingCount() {
            const pendingCountElement = document.querySelector('.stat-card:nth-child(1) .stat-number');
            if (pendingCountElement) {
                let currentCount = parseInt(pendingCountElement.textContent) || 0;
                if (currentCount > 0) {
                    pendingCountElement.textContent = currentCount - 1;
                }
            }
        }
        
        // Show empty state for pending approvals
        function showEmptyState() {
            const tableContainer = document.getElementById('pending-approvals');
            const table = tableContainer.querySelector('table');
            if (table) {
                table.style.display = 'none';
                
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <i class="fas fa-user-check"></i>
                    <h3>No Pending Approvals</h3>
                    <p>All user registrations have been processed. Check back later for new requests.</p>
                `;
                
                tableContainer.appendChild(emptyState);
            }
        }
        
        // Show notification
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border-radius: 6px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                z-index: 1000;
                border-left: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'};
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 5000);
        }
        
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInputs = document.querySelectorAll('.search-input');
            
            searchInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const table = this.closest('.academic-table-container').querySelector('table');
                    
                    if (!table) return;
                    
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            });
        });
    </script>
</body>
</html>