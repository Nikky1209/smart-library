<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

if ($_SESSION['role'] !== 'librarian') {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $book_id = intval($_GET['delete']);
    $delete_query = "UPDATE books SET status = 'deleted', deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $success_message = "Book archived successfully!";
    }
}

if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $book_id = intval($_GET['restore']);
    $restore_query = "UPDATE books SET status = 'active', deleted_at = NULL WHERE id = ?";
    $stmt = $conn->prepare($restore_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $success_message = "Book restored successfully!";
    }
}

if (isset($_GET['permanent_delete']) && is_numeric($_GET['permanent_delete'])) {
    $book_id = intval($_GET['permanent_delete']);
    
    $check_query = "SELECT COUNT(*) as active_count FROM borrowing_records WHERE book_id = ? AND status = 'borrowed'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data['active_count'] > 0) {
        $error_message = "Cannot delete book with active borrowings!";
    } else {
        $delete_records = "DELETE FROM borrowing_records WHERE book_id = ?";
        $stmt2 = $conn->prepare($delete_records);
        $stmt2->bind_param("i", $book_id);
        $stmt2->execute();
        
        $delete_query = "DELETE FROM books WHERE id = ?";
        $stmt3 = $conn->prepare($delete_query);
        $stmt3->bind_param("i", $book_id);
        $stmt3->execute();
        
        if ($stmt3->affected_rows > 0) {
            $success_message = "Book permanently deleted!";
        }
    }
}

$filter = $_GET['filter'] ?? 'active';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];
$types = "";

if ($filter === 'active') {
    $where_conditions[] = "b.status = 'active'";
} elseif ($filter === 'archived') {
    $where_conditions[] = "b.status = 'deleted'";
}

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

$books_query = "SELECT b.*, 
                (SELECT COUNT(*) FROM borrowing_records br WHERE br.book_id = b.id AND br.status = 'borrowed') as currently_borrowed,
                (SELECT COUNT(*) FROM borrowing_records br WHERE br.book_id = b.id) as total_borrowed
                FROM books b 
                $where_clause 
                ORDER BY b.title";
                
$stmt = $conn->prepare($books_query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

$borrowings_query = "SELECT br.*, u.username, u.full_name, u.email, b.title 
                     FROM borrowing_records br 
                     JOIN users u ON br.user_id = u.id 
                     JOIN books b ON br.book_id = b.id 
                     WHERE br.status = 'borrowed' 
                     ORDER BY br.due_date ASC";
$borrowings = $conn->query($borrowings_query);

$overdue_query = "SELECT br.*, u.username, u.full_name, u.email, b.title, 
                  DATEDIFF(CURDATE(), br.due_date) as days_overdue
                  FROM borrowing_records br 
                  JOIN users u ON br.user_id = u.id 
                  JOIN books b ON br.book_id = b.id 
                  WHERE br.status = 'borrowed' AND br.due_date < CURDATE() 
                  ORDER BY br.due_date ASC";
$overdue_books = $conn->query($overdue_query);

$stats_query = "SELECT 
    (SELECT COUNT(*) FROM books WHERE status = 'active') as total_books,
    (SELECT SUM(copies_available) FROM books WHERE status = 'active') as available_books,
    (SELECT COUNT(*) FROM borrowing_records WHERE status = 'borrowed') as borrowed_books,
    (SELECT COUNT(*) FROM borrowing_records WHERE due_date < CURDATE() AND status = 'borrowed') as overdue_books,
    (SELECT COUNT(*) FROM books WHERE status = 'deleted') as archived_books,
    (SELECT COUNT(DISTINCT user_id) FROM borrowing_records WHERE status = 'borrowed') as active_borrowers";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent activities
$activities_query = "SELECT 
    'borrow' as type, u.username, b.title, br.borrow_date as date
    FROM borrowing_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    WHERE br.status = 'borrowed'
    UNION ALL
    SELECT 
    'return' as type, u.username, b.title, br.return_date as date
    FROM borrowing_records br
    JOIN users u ON br.user_id = u.id
    JOIN books b ON br.book_id = b.id
    WHERE br.status = 'returned'
    ORDER BY date DESC
    LIMIT 10";
$activities = $conn->query($activities_query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard - SmartLibrary Academic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Academic Librarian Dashboard Styles */
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
            --librarian-accent: #800020;
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
        
        /* Academic Header - Librarian Variant */
        .academic-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-main));
            color: white;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-bottom: 3px solid var(--librarian-accent);
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
            flex-shrink: 0;
        }
        
        .dashboard-seal {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--librarian-accent);
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
        
        .librarian-info {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .librarian-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn-librarian {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .action-btn-librarian:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            border-color: var(--librarian-accent);
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
            background: linear-gradient(135deg, var(--librarian-accent), #600016);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(128, 0, 32, 0.9);
            color: white;
            border: 2px solid rgba(128, 0, 32, 0.6);
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: var(--librarian-accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 30px 0;
            background: linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="%232d4a8a" opacity="0.03"/></svg>');
        }
        
        .dashboard-header {
            margin-bottom: 40px;
            text-align: center;
            position: relative;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-main), transparent);
            transform: translateY(-50%);
        }
        
        .dashboard-header h1 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 2.8rem;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
            padding: 0 30px;
            background: white;
        }
        
        .dashboard-header p {
            color: var(--slate);
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 0 20px;
            position: relative;
        }
        
        /* Librarian Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid rgba(45, 74, 138, 0.1);
            transition: all 0.4s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            border-color: var(--primary-light);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--librarian-accent), var(--accent-gold));
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-icon::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
            animation: shine 3s infinite linear;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #1a365d, #2d4a8a); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #FF9800, #EF6C00); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #800020, #600016); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #9C27B0, #6A1B9A); }
        .stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #d4af37, #b8941e); }
        
        .stat-icon i {
            font-size: 2rem;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .stat-header h3 {
            font-size: 1.3rem;
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .stat-content {
            margin-bottom: 25px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 5px;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stat-label {
            color: var(--slate);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .card-actions {
            text-align: center;
        }
        
        .action-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s;
            border: 2px solid var(--primary-main);
            box-shadow: 0 4px 15px rgba(45, 74, 138, 0.2);
        }
        
        .action-btn:hover {
            background: transparent;
            color: var(--primary-main);
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(45, 74, 138, 0.3);
        }
        
        /* Quick Actions */
        .quick-actions {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 40px;
            border: 2px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .quick-actions h2 {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.8rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .quick-btn {
            width: 100%;
            justify-content: center;
            padding: 20px;
            background: white;
            border: 2px solid rgba(45, 74, 138, 0.1);
            color: var(--primary-dark);
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .quick-btn:hover {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
            border-color: var(--primary-main);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(45, 74, 138, 0.2);
        }
        
        .quick-btn i {
            font-size: 1.3rem;
        }
        
        /* Section Container */
        .section-container {
            background: white;
            border-radius: 15px;
            padding: 35px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 2px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .section-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--librarian-accent), var(--accent-gold));
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .section-title {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Table Controls */
        .table-controls {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .search-form {
            margin: 0;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            position: relative;
        }
        
        .search-input {
            padding: 12px 15px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            width: 300px;
            font-size: 1rem;
            background: var(--parchment);
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--librarian-accent);
            box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
            background: white;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate);
        }
        
        .search-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .search-btn:hover {
            background: transparent;
            color: var(--primary-main);
            border: 2px solid var(--primary-main);
            transform: translateY(-2px);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            text-decoration: none;
            color: var(--charcoal);
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
            border-color: var(--primary-main);
        }
        
        .filter-tab:hover:not(.active) {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        /* Academic Tables */
        .academic-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .academic-table thead {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
        }
        
        .academic-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 1rem;
            border-bottom: 3px solid var(--primary-dark);
        }
        
        .academic-table td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        }
        
        .academic-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .academic-table tbody tr:hover {
            background-color: rgba(45, 74, 138, 0.04);
        }
        
        .academic-table tbody tr:nth-child(even) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .academic-table tbody tr:nth-child(even):hover {
            background-color: rgba(45, 74, 138, 0.06);
        }
        
        .academic-table tbody tr.archived {
            background: #fff5f5;
            opacity: 0.9;
        }
        
        .academic-table tbody tr.archived:hover {
            background: #ffeaea;
        }
        
        .academic-table tbody tr.overdue-row {
            background: #fff3cd;
        }
        
        .academic-table tbody tr.overdue-row:hover {
            background: #ffeaa7;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid rgba(0, 0, 0, 0.08);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid transparent;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #155724;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-color: #856404;
        }
        
        .status-overdue {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #721c24;
        }
        
        .status-borrowed {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-color: #0c5460;
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #721c24;
        }
        
        /* Time Badges */
        .time-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            border: 2px solid transparent;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-color: #721c24;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-color: #856404;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-color: #155724;
        }
        
        .badge-info {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            color: #0c5460;
            border-color: #0c5460;
        }
        
        /* Category Tags */
        .category-tag {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--accent-gold), #b8941e);
            color: white;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-view {
            background: linear-gradient(135deg, var(--info), #0277bd);
            color: white;
        }
        
        .btn-view:hover {
            background: transparent;
            color: var(--info);
            border: 2px solid var(--info);
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
        
        .btn-warning {
            background: linear-gradient(135deg, #FF9800, #e68900);
            color: white;
        }
        
        .btn-warning:hover {
            background: transparent;
            color: #FF9800;
            border: 2px solid #FF9800;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--error), #c62828);
            color: white;
        }
        
        .btn-danger:hover {
            background: transparent;
            color: var(--error);
            border: 2px solid var(--error);
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #800020, #600016);
            color: white;
        }
        
        .btn-info:hover {
            background: transparent;
            color: var(--librarian-accent);
            border: 2px solid var(--librarian-accent);
            transform: translateY(-2px);
        }
        
        /* Messages */
        .academic-message {
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            border-left: 5px solid transparent;
        }
        
        .warning-message {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border-left-color: var(--warning);
            color: #856404;
        }
        
        .warning-message i {
            color: var(--warning);
            font-size: 1.8rem;
        }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border-left-color: var(--success);
            color: #155724;
        }
        
        .success-message i {
            color: var(--success);
            font-size: 1.8rem;
        }
        
        .error-message {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border-left-color: var(--error);
            color: #721c24;
        }
        
        .error-message i {
            color: var(--error);
            font-size: 1.8rem;
        }
        
        .close-alert {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: inherit;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 15px;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .close-alert:hover {
            opacity: 1;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--primary-light), var(--accent-gold));
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-icon {
            position: absolute;
            left: -40px;
            top: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .timeline-icon.borrow-icon {
            background: linear-gradient(135deg, var(--success), #1e7e34);
        }
        
        .timeline-icon.return-icon {
            background: linear-gradient(135deg, var(--info), #0277bd);
        }
        
        .timeline-content {
            background: white;
            border: 2px solid rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .timeline-content:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .timeline-time {
            color: var(--slate);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--slate);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--charcoal);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            max-width: 400px;
            margin: 0 auto 25px;
            font-size: 1.1rem;
        }
        
        /* Buttons */
        .btn {
            padding: 15px 35px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
        }
        
        .btn-primary:hover {
            background: transparent;
            color: var(--primary-main);
            border: 3px solid var(--primary-main);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--slate), var(--charcoal));
            color: white;
        }
        
        .btn-secondary:hover {
            background: transparent;
            color: var(--charcoal);
            border: 3px solid var(--charcoal);
            transform: translateY(-3px);
        }
        
        /* Text Utilities */
        .text-danger {
            color: var(--error);
            font-weight: 700;
        }
        
        .text-warning {
            color: var(--warning);
        }
        
        .text-success {
            color: var(--success);
        }
        
        .text-muted {
            color: var(--slate);
            opacity: 0.8;
        }
        
        /* Academic Footer */
        .academic-footer {
            background: linear-gradient(135deg, var(--charcoal), #2c3e50);
            color: white;
            padding: 35px 0 25px;
            margin-top: auto;
            border-top: 3px solid var(--librarian-accent);
        }
        
        .footer-content {
            text-align: center;
        }
        
        .footer-content p {
            margin-bottom: 15px;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .footer-content small {
            opacity: 0.7;
            font-size: 0.9rem;
            display: block;
            margin-top: 10px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid var(--librarian-accent);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .modal-header h2 {
            font-family: 'Merriweather', serif;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            background: var(--parchment);
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--librarian-accent);
            box-shadow: 0 0 0 4px rgba(128, 0, 32, 0.1);
            background: white;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid rgba(0, 0, 0, 0.08);
        }
        
        /* Upload Instructions */
        .upload-instructions {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 5px solid var(--accent-gold);
        }
        
        .upload-instructions h3 {
            color: var(--primary-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-instructions ul {
            margin: 10px 0 15px 20px;
        }
        
        .upload-instructions li {
            margin-bottom: 8px;
            color: var(--slate);
        }
        
        /* Upload Progress */
        .upload-progress {
            margin: 25px 0;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #eaeaea;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            width: 0%;
            transition: width 0.3s;
        }
        
        .progress-text {
            text-align: center;
            margin-top: 10px;
            font-size: 1rem;
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 1600px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .librarian-info {
                flex-direction: column;
                width: 100%;
            }
            
            .librarian-actions {
                justify-content: center;
                width: 100%;
            }
            
            .user-greeting {
                text-align: center;
                width: 100%;
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            
            .section-container {
                padding: 25px;
            }
            
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .table-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
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
            .dashboard-header h1 {
                font-size: 1.8rem;
                padding: 0 15px;
            }
            
            .dashboard-header p {
                font-size: 1rem;
                padding: 0 10px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .stat-icon {
                width: 60px;
                height: 60px;
            }
            
            .stat-icon i {
                font-size: 1.5rem;
            }
            
            .section-title {
                font-size: 1.5rem;
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .academic-message {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Academic Header - Librarian Variant -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <a href="../../landing.php" class="dashboard-brand">
                    <div class="dashboard-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span style="color: var(--accent-gold);">Library</span></h1>
                        <p>Librarian Administration Portal</p>
                    </div>
                </a>
                
                <div class="librarian-info">
                    <div class="librarian-actions">
                        <a href="book_archive.php" class="action-btn-librarian">
                            <i class="fas fa-archive"></i> Archive
                        </a>
                        <a href="reports.php" class="action-btn-librarian">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <a href="users.php" class="action-btn-librarian">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </div>
                    
                    <div class="user-greeting">
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="role-badge">Head Librarian</span>
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
            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
            <div class="academic-message success-message" id="successMessage">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Success!</strong>
                    <p><?php echo $success_message; ?></p>
                </div>
                <button class="close-alert" onclick="document.getElementById('successMessage').remove()">&times;</button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="academic-message error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Error!</strong>
                    <p><?php echo $error_message; ?></p>
                </div>
                <button class="close-alert" onclick="document.getElementById('errorMessage').remove()">&times;</button>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-header">
                <h1>Librarian Administration Dashboard</h1>
                <p>Manage library collection, track circulation, and oversee academic resources</p>
            </div>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3>Total Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_books']; ?></div>
                        <div class="stat-label">Active in Collection</div>
                    </div>
                    <div class="card-actions">
                        <a href="#books" class="action-btn">View All</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Borrowed Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['borrowed_books']; ?></div>
                        <div class="stat-label">Currently Loaned</div>
                    </div>
                    <div class="card-actions">
                        <a href="#borrowings" class="action-btn">Manage</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3>Overdue Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['overdue_books']; ?></div>
                        <div class="stat-label">Need Attention</div>
                    </div>
                    <div class="card-actions">
                        <a href="#overdue" class="action-btn">View All</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Active Borrowers</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['active_borrowers']; ?></div>
                        <div class="stat-label">Currently Active</div>
                    </div>
                    <div class="card-actions">
                        <a href="users.php" class="action-btn">View Users</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-archive"></i>
                        </div>
                        <h3>Archived Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['archived_books']; ?></div>
                        <div class="stat-label">In Archive</div>
                    </div>
                    <div class="card-actions">
                        <a href="?filter=archived" class="action-btn">View Archive</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <h3>Add New Book</h3>
                    </div>
                    <div class="stat-content">
                        <p>Add new books to the academic collection</p>
                    </div>
                    <div class="card-actions">
                        <button onclick="showAddBookModal()" class="action-btn">Add Book</button>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2><i class="fas fa-bolt"></i> Quick Administration Actions</h2>
                <div class="actions-grid">
                    <button class="quick-btn" onclick="showAddBookModal()">
                        <i class="fas fa-plus"></i> Add Book
                    </button>
                    <button class="quick-btn" onclick="showBulkUploadModal()">
                        <i class="fas fa-upload"></i> Bulk Upload
                    </button>
                    <button class="quick-btn" onclick="showQRGenerator()">
                        <i class="fas fa-qrcode"></i> Generate QR Codes
                    </button>
                    <button class="quick-btn" onclick="exportBooks()">
                        <i class="fas fa-file-export"></i> Export Books
                    </button>
                    <button class="quick-btn" onclick="printInventory()">
                        <i class="fas fa-print"></i> Print Inventory
                    </button>
                    <button class="quick-btn" onclick="showReports()">
                        <i class="fas fa-chart-line"></i> View Reports
                    </button>
                </div>
            </div>
            
            <!-- Book Management -->
            <div class="section-container" id="books">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-book"></i> Book Management</h2>
                    <div class="table-controls">
                        <form method="GET" class="search-form">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="search-input" placeholder="Search books by title, author, or ISBN..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="search-btn">
                                    Search
                                </button>
                            </div>
                        </form>
                        <div class="filter-tabs">
                            <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                                Active Books (<?php echo $stats['total_books']; ?>)
                            </a>
                            <a href="?filter=archived" class="filter-tab <?php echo $filter === 'archived' ? 'active' : ''; ?>">
                                Archived (<?php echo $stats['archived_books']; ?>)
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if ($books->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="academic-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Category</th>
                                <th>Available</th>
                                <th>Total</th>
                                <th>Borrowed</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($book = $books->fetch_assoc()): 
                                $is_archived = $book['status'] === 'deleted';
                            ?>
                            <tr class="<?php echo $is_archived ? 'archived' : ''; ?>">
                                <td><?php echo $book['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                    <?php if ($is_archived): ?>
                                    <span class="time-badge badge-danger">Archived</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><code><?php echo htmlspecialchars($book['isbn']); ?></code></td>
                                <td>
                                    <span class="category-tag"><?php echo htmlspecialchars($book['category']); ?></span>
                                </td>
                                <td>
                                    <span class="time-badge <?php echo $book['copies_available'] > 0 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $book['copies_available']; ?>
                                    </span>
                                </td>
                                <td><?php echo $book['total_copies']; ?></td>
                                <td><?php echo $book['currently_borrowed']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $book['status'] === 'active' ? 'status-approved' : 'status-rejected'; ?>">
                                        <?php echo ucfirst($book['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-view" onclick="editBook(<?php echo $book['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-info" onclick="viewBookDetails(<?php echo $book['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if (!$is_archived): ?>
                                            <?php if ($book['currently_borrowed'] == 0): ?>
                                            <button class="btn-action btn-danger" onclick="archiveBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                                <i class="fas fa-archive"></i> Archive
                                            </button>
                                            <?php else: ?>
                                            <button class="btn-action btn-warning" onclick="showCannotArchive('<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                                <i class="fas fa-ban"></i> In Use
                                            </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn-action btn-success" onclick="restoreBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                            <button class="btn-action btn-danger" onclick="permanentDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="table-info">
                        <strong><?php echo $books->num_rows; ?></strong> books found
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-secondary" onclick="exportTable()">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button class="btn btn-primary" onclick="showAddBookModal()">
                            <i class="fas fa-plus"></i> Add New Book
                        </button>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No books found</h3>
                    <p><?php echo $filter === 'archived' ? 'No archived books found.' : 'No active books found.'; ?></p>
                    <button class="btn btn-primary" onclick="showAddBookModal()">
                        <i class="fas fa-plus"></i> Add Your First Book
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Current Borrowings -->
            <div class="section-container" id="borrowings">
                <h2 class="section-title"><i class="fas fa-exchange-alt"></i> Current Borrowings</h2>
                <?php if ($borrowings->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book Title</th>
                            <th>Borrower</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Days Left</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($record = $borrowings->fetch_assoc()): 
                            $due_date = new DateTime($record['due_date']);
                            $today = new DateTime();
                            $is_overdue = $today > $due_date;
                            $interval = $today->diff($due_date);
                            $days_left = $is_overdue ? 0 : $interval->days;
                        ?>
                        <tr class="<?php echo $is_overdue ? 'overdue-row' : ''; ?>">
                            <td>BR-<?php echo $record['id']; ?></td>
                            <td><?php echo htmlspecialchars($record['title']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['full_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($record['username']); ?></small>
                                <br><small><?php echo htmlspecialchars($record['email']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($record['borrow_date'])); ?></td>
                            <td>
                                <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($record['due_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="time-badge <?php echo $is_overdue ? 'badge-danger' : ($days_left <= 3 ? 'badge-warning' : 'badge-success'); ?>">
                                    <?php echo $is_overdue ? 'Overdue' : $days_left . ' days'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $is_overdue ? 'status-overdue' : 'status-borrowed'; ?>">
                                    <?php echo $is_overdue ? 'Overdue' : 'Borrowed'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-success" onclick="markReturned(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars(addslashes($record['title'])); ?>')">
                                        <i class="fas fa-check"></i> Return
                                    </button>
                                    <button class="btn-action btn-info" onclick="extendDueDate(<?php echo $record['id']; ?>)">
                                        <i class="fas fa-calendar-plus"></i> Extend
                                    </button>
                                    <button class="btn-action btn-view" onclick="viewBorrowerDetails(<?php echo $record['user_id']; ?>)">
                                        <i class="fas fa-user"></i> Profile
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-reader"></i>
                    <h3>No current borrowings</h3>
                    <p>All books are currently available in the library.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Overdue Books -->
            <div class="section-container" id="overdue">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Overdue Books</h2>
                <?php if ($overdue_books->num_rows > 0): ?>
                <div class="academic-message warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Action Required!</strong>
                        <p>You have <?php echo $overdue_books->num_rows; ?> overdue book(s) that need attention.</p>
                    </div>
                </div>
                
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Borrower</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Fine</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($record = $overdue_books->fetch_assoc()): 
                            $due_date = new DateTime($record['due_date']);
                            $today = new DateTime();
                            $days_overdue = $record['days_overdue'];
                            $fine_amount = calculateFine($days_overdue);
                        ?>
                        <tr class="overdue-row">
                            <td><strong><?php echo htmlspecialchars($record['title']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['full_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($record['username']); ?></small>
                                <br><small><?php echo htmlspecialchars($record['email']); ?></small>
                            </td>
                            <td>
                                <span class="text-danger">
                                    <?php echo date('M d, Y', strtotime($record['due_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="time-badge badge-danger">
                                    <?php echo $days_overdue; ?> days
                                </span>
                            </td>
                            <td>
                                <strong class="text-danger">
                                    ₱<?php echo number_format($fine_amount, 2); ?>
                                </strong>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="contactBorrower('<?php echo htmlspecialchars($record['email']); ?>', '<?php echo htmlspecialchars($record['full_name']); ?>')">
                                        <i class="fas fa-envelope"></i> Email
                                    </button>
                                    <button class="btn-action btn-info" onclick="callBorrower('<?php echo htmlspecialchars($record['full_name']); ?>')">
                                        <i class="fas fa-phone"></i> Call
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-success" onclick="markReturned(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars(addslashes($record['title'])); ?>', true)">
                                        <i class="fas fa-check"></i> Return
                                    </button>
                                    <button class="btn-action btn-warning" onclick="sendReminder(<?php echo $record['user_id']; ?>, <?php echo $record['id']; ?>)">
                                        <i class="fas fa-bell"></i> Remind
                                    </button>
                                    <button class="btn-action btn-danger" onclick="applyFine(<?php echo $record['id']; ?>, <?php echo $fine_amount; ?>)">
                                        <i class="fas fa-money-bill"></i> Fine
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Excellent!</strong>
                        <p>No overdue books at the moment.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activities -->
            <div class="section-container" id="activities">
                <h2 class="section-title"><i class="fas fa-history"></i> Recent Library Activities</h2>
                <?php if ($activities->num_rows > 0): ?>
                <div class="timeline">
                    <?php while($activity = $activities->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?php echo $activity['type'] === 'borrow' ? 'borrow-icon' : 'return-icon'; ?>">
                            <i class="fas fa-<?php echo $activity['type'] === 'borrow' ? 'arrow-right' : 'arrow-left'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                <span class="timeline-time"><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></span>
                            </div>
                            <p>
                                <?php echo $activity['type'] === 'borrow' ? 'borrowed' : 'returned'; ?> 
                                <strong>"<?php echo htmlspecialchars($activity['title']); ?>"</strong>
                            </p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No recent activities</h3>
                    <p>Library activities will appear here.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Academic Footer -->
    <footer class="academic-footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. Librarian Administration Portal.</p>
                <p>Total Books: <?php echo $stats['total_books']; ?> | Active Borrowers: <?php echo $stats['active_borrowers']; ?></p>
                <small><i class="fas fa-shield-alt"></i> Administration access only. Unauthorized access prohibited.</small>
            </div>
        </div>
    </footer>
    
    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Book</h2>
                <button class="close-btn" onclick="closeModal('addBookModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addBookForm" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title"><i class="fas fa-heading"></i> Book Title *</label>
                            <input type="text" id="title" name="title" required placeholder="Enter book title">
                        </div>
                        <div class="form-group">
                            <label for="author"><i class="fas fa-user-pen"></i> Author *</label>
                            <input type="text" id="author" name="author" required placeholder="Enter author name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="isbn"><i class="fas fa-barcode"></i> ISBN *</label>
                            <input type="text" id="isbn" name="isbn" required placeholder="Enter ISBN">
                        </div>
                        <div class="form-group">
                            <label for="category"><i class="fas fa-tags"></i> Category *</label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Fiction">Fiction</option>
                                <option value="Non-Fiction">Non-Fiction</option>
                                <option value="Science">Science</option>
                                <option value="Technology">Technology</option>
                                <option value="History">History</option>
                                <option value="Biography">Biography</option>
                                <option value="Literature">Literature</option>
                                <option value="Academic">Academic</option>
                                <option value="Children">Children</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="publisher"><i class="fas fa-building"></i> Publisher</label>
                            <input type="text" id="publisher" name="publisher" placeholder="Enter publisher">
                        </div>
                        <div class="form-group">
                            <label for="published_year"><i class="fas fa-calendar"></i> Publication Year</label>
                            <input type="number" id="published_year" name="published_year" 
                                   min="1000" max="<?php echo date('Y'); ?>" 
                                   placeholder="YYYY" value="<?php echo date('Y'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="copies"><i class="fas fa-copy"></i> Total Copies *</label>
                            <input type="number" id="copies" name="copies" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="location"><i class="fas fa-map-marker-alt"></i> Shelf Location</label>
                            <input type="text" id="location" name="location" placeholder="e.g., A-12">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Enter book description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="cover_image"><i class="fas fa-image"></i> Cover Image</label>
                        <input type="file" id="cover_image" name="cover_image" accept="image/*">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addBookModal')">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Book Modal -->
    <div id="editBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Book</h2>
                <button class="close-btn" onclick="closeModal('editBookModal')">&times;</button>
            </div>
            <div class="modal-body" id="editBookFormContainer">
                <!-- Form will be loaded here via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- Bulk Upload Modal -->
    <div id="bulkUploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-upload"></i> Bulk Book Upload</h2>
                <button class="close-btn" onclick="closeModal('bulkUploadModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="upload-instructions">
                    <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                    <p>Upload a CSV file with the following columns:</p>
                    <ul>
                        <li>title (required)</li>
                        <li>author (required)</li>
                        <li>isbn (required)</li>
                        <li>category (required)</li>
                        <li>publisher (optional)</li>
                        <li>published_year (optional)</li>
                        <li>total_copies (required, default: 1)</li>
                        <li>description (optional)</li>
                    </ul>
                    <p><a href="/smart-library/assets/templates/books_template.csv" class="btn btn-secondary" style="display: inline-flex;">
                        <i class="fas fa-download"></i> Download Template
                    </a></p>
                </div>
                
                <form id="bulkUploadForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="csv_file"><i class="fas fa-file-csv"></i> CSV File *</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="skip_duplicates" checked>
                            Skip duplicate ISBNs
                        </label>
                    </div>
                    
                    <div class="upload-progress" id="uploadProgress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                        <div class="progress-text" id="progressText">0%</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkUploadModal')">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
     <script src="/smart-library/assets/js/scripts.js"></script>
    <script src="/smart-library/assets/js/librarian.js"></script>
</body>
</html>