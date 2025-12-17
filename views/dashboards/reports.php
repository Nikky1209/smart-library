<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if ($_SESSION['role'] !== 'staff') {
    header("Location: staff.php");
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

$conn = getDBConnection();

function getReportData($conn, $report_type, $start_date, $end_date) {
    $data = [];
    
    switch($report_type) {
        case 'overview':
            $data = getOverviewReport($conn, $start_date, $end_date);
            break;
        case 'users':
            $data = getUserReport($conn, $start_date, $end_date);
            break;
        case 'books':
            $data = getBookReport($conn, $start_date, $end_date);
            break;
        case 'borrowing':
            $data = getBorrowingReport($conn, $start_date, $end_date);
            break;
        case 'fines':
            $data = getFinesReport($conn, $start_date, $end_date);
            break;
        default:
            $data = getOverviewReport($conn, $start_date, $end_date);
    }
    
    return $data;
}

function getOverviewReport($conn, $start_date, $end_date) {
    $data = [];
    
    $stats_query = "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE approved = FALSE) as pending_users,
        (SELECT COUNT(*) FROM books) as total_books,
        (SELECT SUM(copies_available) FROM books) as available_books,
        (SELECT COUNT(*) FROM borrowing_records WHERE status = 'borrowed') as borrowed_books,
        (SELECT COUNT(*) FROM borrowing_records WHERE status = 'overdue') as overdue_books,
        (SELECT COUNT(*) FROM borrowing_records WHERE DATE(borrow_date) BETWEEN '$start_date' AND '$end_date') as recent_borrowings,
        (SELECT COUNT(*) FROM users WHERE DATE(registration_date) BETWEEN '$start_date' AND '$end_date') as new_users,
        (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid = FALSE) as pending_fines";
    
    $result = $conn->query($stats_query);
    $data['overview'] = $result->fetch_assoc();
    
    $activity_query = "SELECT 
        DATE(borrow_date) as date,
        COUNT(*) as borrow_count
    FROM borrowing_records 
    WHERE borrow_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(borrow_date)
    ORDER BY date";
    
    $result = $conn->query($activity_query);
    $data['daily_activity'] = [];
    while($row = $result->fetch_assoc()) {
        $data['daily_activity'][] = $row;
    }
    
    $top_books_query = "SELECT 
        b.title,
        b.author,
        b.category,
        COUNT(br.id) as borrow_count
    FROM books b
    LEFT JOIN borrowing_records br ON b.id = br.book_id
    WHERE br.borrow_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY b.id
    ORDER BY borrow_count DESC
    LIMIT 10";
    
    $result = $conn->query($top_books_query);
    $data['top_books'] = [];
    while($row = $result->fetch_assoc()) {
        $data['top_books'][] = $row;
    }
    
    return $data;
}

function getUserReport($conn, $start_date, $end_date) {
    $data = [];
    
    $role_stats_query = "SELECT 
        role,
        COUNT(*) as count,
        SUM(CASE WHEN approved = TRUE THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN approved = FALSE THEN 1 ELSE 0 END) as pending_count
    FROM users
    GROUP BY role";
    
    $result = $conn->query($role_stats_query);
    $data['role_stats'] = [];
    while($row = $result->fetch_assoc()) {
        $data['role_stats'][] = $row;
    }
    
    $new_users_query = "SELECT 
        DATE(registration_date) as date,
        COUNT(*) as new_users,
        GROUP_CONCAT(username SEPARATOR ', ') as usernames
    FROM users
    WHERE registration_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(registration_date)
    ORDER BY date";
    
    $result = $conn->query($new_users_query);
    $data['new_users'] = [];
    while($row = $result->fetch_assoc()) {
        $data['new_users'][] = $row;
    }
    
    $active_users_query = "SELECT 
        u.username,
        u.full_name,
        u.role,
        COUNT(br.id) as borrow_count,
        COUNT(DISTINCT br.book_id) as unique_books
    FROM users u
    LEFT JOIN borrowing_records br ON u.id = br.user_id
    WHERE br.borrow_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY u.id
    ORDER BY borrow_count DESC
    LIMIT 10";
    
    $result = $conn->query($active_users_query);
    $data['active_users'] = [];
    while($row = $result->fetch_assoc()) {
        $data['active_users'][] = $row;
    }
    
    return $data;
}

function getBookReport($conn, $start_date, $end_date) {
    $data = [];
    
    $category_stats_query = "SELECT 
        category,
        COUNT(*) as total_books,
        SUM(copies_available) as available_copies,
        SUM(total_copies) as total_copies,
        ROUND(SUM(copies_available) * 100.0 / SUM(total_copies), 2) as availability_rate
    FROM books
    GROUP BY category
    ORDER BY total_books DESC";
    
    $result = $conn->query($category_stats_query);
    $data['category_stats'] = [];
    while($row = $result->fetch_assoc()) {
        $data['category_stats'][] = $row;
    }
    
    $acquisition_query = "SELECT 
        YEAR(added_date) as year,
        MONTH(added_date) as month,
        COUNT(*) as books_added,
        GROUP_CONCAT(title SEPARATOR ', ') as titles
    FROM books
    WHERE added_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY YEAR(added_date), MONTH(added_date)
    ORDER BY year, month";
    
    $result = $conn->query($acquisition_query);
    $data['acquisition'] = [];
    while($row = $result->fetch_assoc()) {
        $data['acquisition'][] = $row;
    }
    
    $low_availability_query = "SELECT 
        title,
        author,
        category,
        copies_available,
        total_copies,
        ROUND((copies_available * 100.0 / total_copies), 2) as availability_percentage
    FROM books
    WHERE copies_available <= 2 AND total_copies > 0
    ORDER BY availability_percentage ASC
    LIMIT 10";
    
    $result = $conn->query($low_availability_query);
    $data['low_availability'] = [];
    while($row = $result->fetch_assoc()) {
        $data['low_availability'][] = $row;
    }
    
    return $data;
}

function getBorrowingReport($conn, $start_date, $end_date) {
    $data = [];
    
    $trends_query = "SELECT 
        DATE(borrow_date) as date,
        COUNT(*) as borrow_count,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT book_id) as unique_books
    FROM borrowing_records
    WHERE borrow_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(borrow_date)
    ORDER BY date";
    
    $result = $conn->query($trends_query);
    $data['borrowing_trends'] = [];
    while($row = $result->fetch_assoc()) {
        $data['borrowing_trends'][] = $row;
    }
    
    $return_stats_query = "SELECT 
        status,
        COUNT(*) as count,
        AVG(DATEDIFF(COALESCE(return_date, NOW()), borrow_date)) as avg_days_held
    FROM borrowing_records
    WHERE borrow_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY status";
    
    $result = $conn->query($return_stats_query);
    $data['return_stats'] = [];
    while($row = $result->fetch_assoc()) {
        $data['return_stats'][] = $row;
    }
    
    $overdue_query = "SELECT 
        u.username,
        u.full_name,
        b.title,
        DATEDIFF(NOW(), br.due_date) as days_overdue,
        br.due_date,
        br.borrow_date
    FROM borrowing_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    WHERE br.status = 'overdue'
    ORDER BY days_overdue DESC
    LIMIT 20";
    
    $result = $conn->query($overdue_query);
    $data['overdue_details'] = [];
    while($row = $result->fetch_assoc()) {
        $data['overdue_details'][] = $row;
    }
    
    return $data;
}

function getFinesReport($conn, $start_date, $end_date) {
    $data = [];
    
    $fines_summary_query = "SELECT 
        SUM(CASE WHEN paid = TRUE THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN paid = FALSE THEN amount ELSE 0 END) as total_unpaid,
        COUNT(CASE WHEN paid = TRUE THEN 1 END) as paid_count,
        COUNT(CASE WHEN paid = FALSE THEN 1 END) as unpaid_count,
        AVG(amount) as avg_fine_amount
    FROM fines";
    
    $result = $conn->query($fines_summary_query);
    $data['fines_summary'] = $result->fetch_assoc();
    
    $fines_trend_query = "SELECT 
        DATE(br.borrow_date) as date,
        SUM(f.amount) as daily_fines,
        SUM(CASE WHEN f.paid = TRUE THEN f.amount ELSE 0 END) as daily_paid,
        SUM(CASE WHEN f.paid = FALSE THEN f.amount ELSE 0 END) as daily_unpaid,
        COUNT(f.id) as fine_count
    FROM fines f
    LEFT JOIN borrowing_records br ON f.borrowing_id = br.id
    WHERE br.borrow_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(br.borrow_date)
    ORDER BY date";
    
    $result = $conn->query($fines_trend_query);
    $data['fines_trend'] = [];
    while($row = $result->fetch_assoc()) {
        $data['fines_trend'][] = $row;
    }
    
    $top_fines_query = "SELECT 
        u.id,
        u.username,
        u.full_name,
        COUNT(f.id) as fine_count,
        SUM(f.amount) as total_fines,
        SUM(CASE WHEN f.paid = TRUE THEN f.amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN f.paid = FALSE THEN f.amount ELSE 0 END) as unpaid_amount
    FROM users u
    JOIN fines f ON u.id = f.user_id
    GROUP BY u.id
    ORDER BY total_fines DESC
    LIMIT 10";
    
    $result = $conn->query($top_fines_query);
    $data['top_fines'] = [];
    while($row = $result->fetch_assoc()) {
        $data['top_fines'][] = $row;
    }
    
    return $data;
}

$report_data = getReportData($conn, $report_type, $start_date, $end_date);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - SmartLibrary Academic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Academic Reports Styles */
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
            --staff-accent: #800020;
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
            max-width: 1800px;
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
            gap: 20px;
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
            gap: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
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
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: linear-gradient(135deg, var(--staff-accent), #600016);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            border-color: var(--accent-gold);
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 30px 0;
            background: linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97));
        }
        
        .report-header {
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }
        
        .report-header::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-main), transparent);
            transform: translateY(-50%);
        }
        
        .report-header h1 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
            padding: 0 30px;
            background: white;
        }
        
        .report-header p {
            color: var(--slate);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 0 20px;
            position: relative;
        }
        
        /* Report Filters */
        .report-filters {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid rgba(0, 0, 0, 0.05);
            border-left: 5px solid var(--accent-gold);
        }
        
        .report-filters h3 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.5rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .filter-form {
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--charcoal);
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            background: var(--parchment);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
            background: white;
        }
        
        .filter-group .btn {
            width: 100%;
            margin-top: 8px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
        }
        
        .btn-primary:hover {
            background: transparent;
            color: var(--primary-main);
            border: 2px solid var(--primary-main);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--slate), var(--charcoal));
            color: white;
        }
        
        .btn-secondary:hover {
            background: transparent;
            color: var(--charcoal);
            border: 2px solid var(--charcoal);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #1e7e34);
            color: white;
        }
        
        .btn-success:hover {
            background: transparent;
            color: var(--success);
            border: 2px solid var(--success);
            transform: translateY(-2px);
        }
        
        .date-range-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border: 2px solid transparent;
        }
        
        .badge.info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-color: #0c5460;
        }
        
        .badge.success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #155724;
        }
        
        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 2px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, var(--success), #1e7e34);
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, var(--warning), #e65100);
        }
        
        .stat-icon.danger {
            background: linear-gradient(135deg, var(--error), #c62828);
        }
        
        .stat-icon.info {
            background: linear-gradient(135deg, var(--info), #0277bd);
        }
        
        .stat-info h3 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--slate);
            font-size: 0.95rem;
        }
        
        /* Report Content */
        .report-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        /* Academic Tables */
        .academic-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }
        
        .academic-table thead {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
        }
        
        .academic-table th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 3px solid var(--primary-dark);
        }
        
        .academic-table td {
            padding: 16px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .academic-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .academic-table tbody tr:hover {
            background-color: rgba(45, 74, 138, 0.04);
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        /* Chart Containers */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-small:hover {
            transform: translateY(-2px);
        }
        
        /* Metric Cards */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 25px;
            border: 2px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1;
            margin-bottom: 10px;
        }
        
        .metric-label {
            color: var(--slate);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .metric-change {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .metric-change.positive {
            color: var(--success);
        }
        
        .metric-change.negative {
            color: var(--error);
        }
        
        /* Academic Footer */
        .academic-footer {
            background: linear-gradient(135deg, var(--charcoal), #2c3e50);
            color: white;
            padding: 30px 0 20px;
            margin-top: auto;
            border-top: 3px solid var(--accent-gold);
        }
        
        .footer-content {
            text-align: center;
        }
        
        .footer-content p {
            margin-bottom: 10px;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .footer-content small {
            opacity: 0.7;
            font-size: 0.9rem;
            display: block;
            margin-top: 10px;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .staff-info {
                flex-direction: column;
                width: 100%;
            }
            
            .user-greeting {
                text-align: center;
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .report-header h1 {
                font-size: 2rem;
                padding: 0 15px;
            }
            
            .report-header p {
                font-size: 1rem;
                padding: 0 10px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-grid {
                gap: 20px;
            }
            
            .chart-container {
                padding: 20px;
            }
            
            .academic-table {
                display: block;
                overflow-x: auto;
            }
            
            .academic-table th,
            .academic-table td {
                padding: 12px 10px;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .date-range-info {
                flex-direction: column;
            }
            
            .badge {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Academic Header -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <a href="staff.php" class="dashboard-brand">
                    <div class="dashboard-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span style="color: var(--accent-gold);">Library</span></h1>
                        <p>Academic Analytics Portal</p>
                    </div>
                </a>
                
                <div class="staff-info">
                    <div class="user-greeting">
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="role-badge">Administrative Staff</span>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <a href="staff.php" class="nav-btn">
                            <i class="fas fa-arrow-left"></i> Staff Dashboard
                        </a>
                        <a href="../auth/logout.php" class="nav-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Main Content -->
    <main>
        <div class="container">
            <!-- Report Header -->
            <div class="report-header">
                <h1><i class="fas fa-chart-pie"></i> Academic Analytics & Reports</h1>
                <p>Comprehensive insights into library operations, usage patterns, and academic performance metrics</p>
            </div>
            
            <!-- Report Filters -->
            <div class="report-filters">
                <h3><i class="fas fa-filter"></i> Report Filters</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="report_type"><i class="fas fa-file-alt"></i> Report Type</label>
                            <select id="report_type" name="report_type" onchange="this.form.submit()">
                                <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>Overview Dashboard</option>
                                <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Analytics</option>
                                <option value="books" <?php echo $report_type == 'books' ? 'selected' : ''; ?>>Book Inventory</option>
                                <option value="borrowing" <?php echo $report_type == 'borrowing' ? 'selected' : ''; ?>>Borrowing Patterns</option>
                                <option value="fines" <?php echo $report_type == 'fines' ? 'selected' : ''; ?>>Fines & Revenue</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="start_date"><i class="fas fa-calendar-alt"></i> Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end_date"><i class="fas fa-calendar-alt"></i> End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                        </div>
                        
                        <div class="filter-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Apply Filters
                            </button>
                            <button type="button" onclick="resetFilters()" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="button" onclick="exportReport()" class="btn btn-success">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                        </div>
                                                </div>
                    </div>
                </form>
                
                <div class="date-range-info">
                    <span class="badge info">
                        <i class="fas fa-calendar-check"></i> Report Period: 
                        <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?>
                    </span>
                    <span class="badge success">
                        <i class="fas fa-chart-line"></i> Report Type: 
                        <?php echo ucfirst($report_type); ?> Analytics
                    </span>
                </div>
            </div>
            
            <?php if ($report_type == 'overview'): ?>
            <!-- Overview Report -->
            <div class="quick-stats">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['overview']['total_users'] ?? 0; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['overview']['total_books'] ?? 0; ?></h3>
                        <p>Total Books</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['overview']['borrowed_books'] ?? 0; ?></h3>
                        <p>Books Borrowed</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['overview']['overdue_books'] ?? 0; ?></h3>
                        <p>Overdue Books</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['overview']['pending_users'] ?? 0; ?></h3>
                        <p>Pending Approvals</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_data['overview']['pending_fines'] ?? 0, 2); ?></h3>
                        <p>Pending Fines</p>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="chart-grid">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Borrowing Activity (Last 30 Days)</h3>
                        <button class="btn-small btn-primary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Top Borrowed Books</h3>
                        <button class="btn-small btn-primary">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="topBooksChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Table -->
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-list-alt"></i> Top 10 Most Borrowed Books</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Borrow Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['top_books'])): ?>
                                <?php $rank = 1; ?>
                                <?php foreach ($report_data['top_books'] as $book): ?>
                                <tr>
                                    <td><span style="display: inline-block; width: 25px; height: 25px; background: var(--primary-main); color: white; border-radius: 50%; text-align: center; line-height: 25px;"><?php echo $rank++; ?></span></td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><span style="background: #e9ecef; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem;"><?php echo htmlspecialchars($book['category']); ?></span></td>
                                    <td><strong><?php echo $book['borrow_count']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-book-open" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No borrowing activity in this period</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'users'): ?>
            <!-- Users Report -->
            <div class="quick-stats">
                <?php if (!empty($report_data['role_stats'])): ?>
                    <?php foreach ($report_data['role_stats'] as $role): ?>
                    <div class="stat-card">
                        <div class="stat-icon <?php echo $role['role'] == 'admin' ? 'danger' : ($role['role'] == 'staff' ? 'warning' : 'primary'); ?>">
                            <i class="fas fa-<?php echo $role['role'] == 'admin' ? 'crown' : ($role['role'] == 'staff' ? 'user-tie' : 'user-graduate'); ?>"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $role['count']; ?></h3>
                            <p><?php echo ucfirst($role['role']); ?> Users</p>
                            <div style="font-size: 0.8rem; color: var(--slate);">
                                <span><?php echo $role['approved_count']; ?> approved</span> • 
                                <span><?php echo $role['pending_count']; ?> pending</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-user-plus"></i> New User Registrations</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>New Users</th>
                                <th>Usernames</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['new_users'])): ?>
                                <?php foreach ($report_data['new_users'] as $registration): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($registration['date'])); ?></td>
                                    <td><strong><?php echo $registration['new_users']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($registration['usernames']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-user-plus" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No new user registrations in this period</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-fire"></i> Most Active Users (Top 10)</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Borrow Count</th>
                                <th>Unique Books</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['active_users'])): ?>
                                <?php foreach ($report_data['active_users'] as $user): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><span style="background: #e9ecef; padding: 5px 10px; border-radius: 15px; font-size: 0.85rem;"><?php echo htmlspecialchars($user['role']); ?></span></td>
                                    <td><span class="metric-change positive"><?php echo $user['borrow_count']; ?></span></td>
                                    <td><?php echo $user['unique_books']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-users" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No user activity in this period</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'books'): ?>
            <!-- Books Report -->
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-tags"></i> Book Categories Analysis</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Books</th>
                                <th>Total Copies</th>
                                <th>Available Copies</th>
                                <th>Availability Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['category_stats'])): ?>
                                <?php foreach ($report_data['category_stats'] as $category): ?>
                                <tr>
                                    <td><span style="background: #e9ecef; padding: 5px 15px; border-radius: 15px; font-weight: 600;"><?php echo htmlspecialchars($category['category']); ?></span></td>
                                    <td><?php echo $category['total_books']; ?></td>
                                    <td><?php echo $category['total_copies']; ?></td>
                                    <td><?php echo $category['available_copies']; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="flex: 1; height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                                                <div style="height: 100%; width: <?php echo $category['availability_rate']; ?>%; background: var(--success);"></div>
                                            </div>
                                            <span><?php echo $category['availability_rate']; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-books" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No category data available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-exclamation-triangle"></i> Books with Low Availability</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Available</th>
                                <th>Total</th>
                                <th>Availability</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['low_availability'])): ?>
                                <?php foreach ($report_data['low_availability'] as $book): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['category']); ?></td>
                                    <td><span style="color: var(--error); font-weight: bold;"><?php echo $book['copies_available']; ?></span></td>
                                    <td><?php echo $book['total_copies']; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="flex: 1; height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                                                <div style="height: 100%; width: <?php echo $book['availability_percentage']; ?>%; background: <?php echo $book['availability_percentage'] < 20 ? 'var(--error)' : 'var(--warning)'; ?>;"></div>
                                            </div>
                                            <span><?php echo $book['availability_percentage']; ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 15px; display: block;"></i>
                                        <p>All books have good availability!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'borrowing'): ?>
            <!-- Borrowing Report -->
            <div class="metric-grid">
                <?php if (!empty($report_data['return_stats'])): ?>
                    <?php foreach ($report_data['return_stats'] as $stat): ?>
                    <div class="metric-card">
                        <div class="metric-value"><?php echo $stat['count']; ?></div>
                        <div class="metric-label">Books <?php echo ucfirst($stat['status']); ?></div>
                        <?php if ($stat['avg_days_held']): ?>
                        <div class="metric-change">
                            <i class="fas fa-clock"></i>
                            Avg. <?php echo round($stat['avg_days_held']); ?> days held
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-calendar-alt"></i> Daily Borrowing Trends</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Borrowings</th>
                                <th>Unique Users</th>
                                <th>Unique Books</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['borrowing_trends'])): ?>
                                <?php foreach ($report_data['borrowing_trends'] as $trend): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($trend['date'])); ?></td>
                                    <td><strong><?php echo $trend['borrow_count']; ?></strong></td>
                                    <td><?php echo $trend['unique_users']; ?></td>
                                    <td><?php echo $trend['unique_books']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-exchange-alt" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No borrowing activity in this period</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-exclamation-circle"></i> Overdue Books Details</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Full Name</th>
                                <th>Book Title</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['overdue_details'])): ?>
                                <?php foreach ($report_data['overdue_details'] as $overdue): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($overdue['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($overdue['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($overdue['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($overdue['borrow_date'])); ?></td>
                                    <td><span style="color: var(--error);"><?php echo date('M d, Y', strtotime($overdue['due_date'])); ?></span></td>
                                    <td>
                                        <span style="background: var(--error); color: white; padding: 5px 10px; border-radius: 15px; font-weight: bold;">
                                            <?php echo $overdue['days_overdue']; ?> days
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 15px; display: block;"></i>
                                        <p>No overdue books! Excellent management.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php elseif ($report_type == 'fines'): ?>
            <!-- Fines Report -->
            <div class="quick-stats">
                <?php if (!empty($report_data['fines_summary'])): ?>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-money-check-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_data['fines_summary']['total_paid'] ?? 0, 2); ?></h3>
                        <p>Total Fines Paid</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-money-bill-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_data['fines_summary']['total_unpaid'] ?? 0, 2); ?></h3>
                        <p>Total Unpaid Fines</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($report_data['fines_summary']['avg_fine_amount'] ?? 0, 2); ?></h3>
                        <p>Average Fine Amount</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['fines_summary']['paid_count'] ?? 0; ?></h3>
                        <p>Paid Fines</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $report_data['fines_summary']['unpaid_count'] ?? 0; ?></h3>
                        <p>Unpaid Fines</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="report-content">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-chart-bar"></i> Top 10 Users with Highest Fines</h3>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Total Fines</th>
                                <th>Paid Amount</th>
                                <th>Unpaid Amount</th>
                                <th>Fine Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($report_data['top_fines'])): ?>
                                <?php foreach ($report_data['top_fines'] as $fine): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($fine['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($fine['full_name']); ?></td>
                                    <td>
                                        <span style="color: var(--error); font-weight: bold;">
                                            $<?php echo number_format($fine['total_fines'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--success);">
                                            $<?php echo number_format($fine['paid_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--warning);">
                                            $<?php echo number_format($fine['unpaid_amount'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $fine['fine_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <i class="fas fa-money-bill-wave" style="font-size: 3rem; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                        <p>No fines data available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php endif; ?>
            
            <!-- Print & Export Section -->
            <div style="text-align: center; margin: 40px 0;">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button onclick="exportReport('pdf')" class="btn btn-success">
                    <i class="fas fa-file-pdf"></i> Export as PDF
                </button>
                <button onclick="exportReport('csv')" class="btn btn-secondary">
                    <i class="fas fa-file-csv"></i> Export as CSV
                </button>
                <button onclick="exportReport('excel')" class="btn btn-primary">
                    <i class="fas fa-file-excel"></i> Export as Excel
                </button>
            </div>
        </div>
    </main>
    
    <!-- Academic Footer -->
    <footer class="academic-footer">
        <div class="container">
            <div class="footer-content">
                <p><i class="fas fa-university"></i> SmartLibrary Academic System - Analytics & Reporting Module</p>
                <p>Comprehensive insights for data-driven academic library management</p>
                <small>Report generated on <?php echo date('F d, Y \a\t h:i A'); ?></small>
                <small>&copy; <?php echo date('Y'); ?> SmartLibrary. All rights reserved.</small>
            </div>
        </div>
    </footer>
    
    <script>
        // Initialize charts for overview report
        <?php if ($report_type == 'overview'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Daily Activity Chart
            <?php if (!empty($report_data['daily_activity'])): ?>
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityDates = <?php echo json_encode(array_column($report_data['daily_activity'], 'date')); ?>;
            const activityData = <?php echo json_encode(array_column($report_data['daily_activity'], 'borrow_count')); ?>;
            
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: activityDates,
                    datasets: [{
                        label: 'Daily Borrowings',
                        data: activityData,
                        borderColor: '#2d4a8a',
                        backgroundColor: 'rgba(45, 74, 138, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Borrowings'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Top Books Chart
            <?php if (!empty($report_data['top_books'])): ?>
            const booksCtx = document.getElementById('topBooksChart').getContext('2d');
            const bookTitles = <?php echo json_encode(array_column($report_data['top_books'], 'title')); ?>;
            const bookBorrows = <?php echo json_encode(array_column($report_data['top_books'], 'borrow_count')); ?>;
            
            new Chart(booksCtx, {
                type: 'bar',
                data: {
                    labels: bookTitles,
                    datasets: [{
                        label: 'Borrow Count',
                        data: bookBorrows,
                        backgroundColor: [
                            '#2d4a8a', '#4a6fac', '#6d8fc7', '#91aedc',
                            '#1a365d', '#800020', '#d4af37', '#2e7d32',
                            '#ed6c02', '#d32f2f'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Borrowings'
                            }
                        },
                        x: {
                            ticks: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            <?php endif; ?>
        });
        <?php endif; ?>
        
        // Utility functions
        function resetFilters() {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            const startDate = thirtyDaysAgo.toISOString().split('T')[0];
            
            window.location.href = window.location.pathname + '?start_date=' + startDate + '&end_date=' + today + '&report_type=overview';
        }
        
        function exportReport(format = 'pdf') {
            const params = new URLSearchParams(window.location.search);
            params.append('export', format);
            
            // You would typically make an AJAX call to generate the export file
            alert('Export functionality would generate a ' + format.toUpperCase() + ' file for the current report.\n\nParameters:\n' + params.toString());
            
            // For actual implementation, you would:
            // 1. Create a separate export.php file
            // 2. Use libraries like TCPDF for PDF, or PhpSpreadsheet for Excel
            // 3. Redirect to the export URL
            // window.location.href = 'export_report.php?' + params.toString();
        }
        
        // Auto-refresh report every 5 minutes (optional)
        // setTimeout(() => { location.reload(); }, 300000);
        
        // Print optimization
        window.onbeforeprint = function() {
            // Hide filters and buttons when printing
            document.querySelector('.report-filters').style.display = 'none';
            document.querySelector('.academic-header').style.borderBottom = 'none';
        };
        
        window.onafterprint = function() {
            // Restore display after printing
            document.querySelector('.report-filters').style.display = 'block';
            document.querySelector('.academic-header').style.borderBottom = '3px solid var(--accent-gold)';
        };
    </script>
</body>
</html>
