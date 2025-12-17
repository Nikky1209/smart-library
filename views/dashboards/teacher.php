<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$borrowed_query = "SELECT b.*, br.id as record_id, br.borrow_date, br.due_date, br.status 
                   FROM borrowing_records br 
                   JOIN books b ON br.book_id = b.id 
                   WHERE br.user_id = ? AND br.status = 'borrowed' 
                   ORDER BY br.due_date ASC";
$stmt = $conn->prepare($borrowed_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrowed_books = $stmt->get_result();

$overdue_query = "SELECT b.*, br.id as record_id, br.borrow_date, br.due_date, 
                         DATEDIFF(CURDATE(), br.due_date) as days_overdue
                  FROM borrowing_records br 
                  JOIN books b ON br.book_id = b.id 
                  WHERE br.user_id = ? 
                    AND br.status = 'borrowed' 
                    AND br.due_date < CURDATE()
                  ORDER BY br.due_date ASC";
$stmt2 = $conn->prepare($overdue_query);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$overdue_books = $stmt2->get_result();

$fines_query = "SELECT 
                    SUM(CASE WHEN f.paid = FALSE THEN f.amount ELSE 0 END) as total_unpaid,
                    SUM(CASE WHEN f.paid = TRUE THEN f.amount ELSE 0 END) as total_paid
                 FROM fines f
                 WHERE f.user_id = ?";
$stmt3 = $conn->prepare($fines_query);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$fines_result = $stmt3->get_result();
$fines_data = $fines_result->fetch_assoc();

$available_books_query = "SELECT * FROM books WHERE copies_available >= 0 ORDER BY title LIMIT 15";
$available_books = $conn->query($available_books_query);

$reservations_query = "SELECT r.*, b.title, b.author, 
                      (SELECT COUNT(*) FROM reservations r2 
                       WHERE r2.book_id = r.book_id 
                       AND r2.status = 'active' 
                       AND r2.reservation_date < r.reservation_date) as queue_position
                      FROM reservations r 
                      JOIN books b ON r.book_id = b.id 
                      WHERE r.user_id = ? AND r.status = 'active'
                      ORDER BY r.reservation_date ASC";
$reservations_stmt = $conn->prepare($reservations_query);
$reservations_stmt->bind_param("i", $user_id);
$reservations_stmt->execute();
$reservations = $reservations_stmt->get_result();

$history_query = "SELECT b.title, b.author, br.borrow_date, br.return_date 
                  FROM borrowing_records br 
                  JOIN books b ON br.book_id = b.id 
                  WHERE br.user_id = ? AND br.status = 'returned' 
                  ORDER BY br.borrow_date DESC LIMIT 10";
$stmt4 = $conn->prepare($history_query);
$stmt4->bind_param("i", $user_id);
$stmt4->execute();
$borrowing_history = $stmt4->get_result();

$course_materials_query = "SELECT cm.*, b.title, b.author 
                           FROM course_materials cm 
                           JOIN books b ON cm.book_id = b.id 
                           WHERE cm.teacher_id = ? 
                           ORDER BY cm.request_date DESC";
$course_stmt = $conn->prepare($course_materials_query);
$course_stmt->bind_param("i", $user_id);
$course_stmt->execute();
$course_materials = $course_stmt->get_result();

$conn->close();

$borrowing_limit = 30; 
$max_books = 999; 
$max_reservations = 999; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - SmartLibrary Academic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Academic Teacher Dashboard Styles */
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
            --teacher-accent: #d4af37;
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Academic Header - Teacher Variant */
        .academic-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-main));
            color: white;
            padding: 15px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-bottom: 3px solid var(--teacher-accent);
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
            border: 2px solid var(--teacher-accent);
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
        
        .teacher-info {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .teacher-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn-teacher {
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
        
        .action-btn-teacher:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            border-color: var(--teacher-accent);
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
            background: linear-gradient(135deg, var(--teacher-accent), #b8941e);
            color: var(--primary-dark);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(212, 175, 55, 0.9);
            color: var(--primary-dark);
            border: 2px solid rgba(212, 175, 55, 0.6);
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: var(--teacher-accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        /* Main Content */
        main {
            flex: 1;
            padding: 30px 0;
            background: linear-gradient(rgba(255, 255, 255, 0.97), rgba(255, 255, 255, 0.97)),
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><pattern id="pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M0,0 L20,20 M-5,5 L5,15 M15,5 L25,15" stroke="%232d4a8a" stroke-width="0.5" opacity="0.05"/></pattern><rect x="0" y="0" width="100" height="100" fill="url(%23pattern)"/></svg>');
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
        
        /* Teacher Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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
            background: linear-gradient(90deg, var(--teacher-accent), var(--accent-burgundy));
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
        
        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #FF9800, #EF6C00); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #d4af37, #b8941e); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #9C27B0, #6A1B9A); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #800020, #600016); }
        .stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #2196F3, #0D47A1); }
        
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
            background: linear-gradient(90deg, var(--teacher-accent), var(--accent-burgundy));
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
        }
        
        .section-title {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .info-message {
            background: linear-gradient(135deg, #d1ecf1, #bee5eb);
            border-left-color: var(--info);
            color: #0c5460;
        }
        
        .info-message i {
            color: var(--info);
            font-size: 1.8rem;
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
        
        .btn-approve {
            background: linear-gradient(135deg, var(--success), #1e7e34);
            color: white;
        }
        
        .btn-approve:hover {
            background: transparent;
            color: var(--success);
            border: 2px solid var(--success);
            transform: translateY(-2px);
        }
        
        .btn-reserve {
            background: linear-gradient(135deg, #FF9800, #e68900);
            color: white;
        }
        
        .btn-reserve:hover {
            background: transparent;
            color: #FF9800;
            border: 2px solid #FF9800;
            transform: translateY(-2px);
        }
        
        .btn-course {
            background: linear-gradient(135deg, var(--accent-burgundy), #600016);
            color: white;
        }
        
        .btn-course:hover {
            background: transparent;
            color: var(--accent-burgundy);
            border: 2px solid var(--accent-burgundy);
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
        
        /* Search Box */
        .search-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            background: var(--parchment);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--teacher-accent);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
            background: white;
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate);
        }
        
        .btn-clear {
            padding: 15px 25px;
            background: linear-gradient(135deg, var(--slate), var(--charcoal));
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-clear:hover {
            background: linear-gradient(135deg, var(--charcoal), #2c3e50);
            transform: translateY(-2px);
        }
        
        /* Fines Summary */
        .fines-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin: 30px 0;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            border: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .fine-item {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .fine-item:hover {
            border-color: var(--teacher-accent);
            transform: translateY(-5px);
        }
        
        .fine-label {
            color: var(--slate);
            font-size: 1rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .fine-amount {
            font-size: 2.2rem;
            font-weight: 800;
        }
        
        .text-danger {
            color: var(--error);
        }
        
        .text-success {
            color: var(--success);
        }
        
        .fines-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
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
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #1e7e34);
            color: white;
        }
        
        .btn-success:hover {
            background: transparent;
            color: var(--success);
            border: 3px solid var(--success);
            transform: translateY(-3px);
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info), #0277bd);
            color: white;
        }
        
        .btn-info:hover {
            background: transparent;
            color: var(--info);
            border: 3px solid var(--info);
            transform: translateY(-3px);
        }
        
        /* Course Materials Section */
        .course-actions {
            margin: 25px 0 35px;
        }
        
        .course-info {
            background: linear-gradient(135deg, var(--parchment), #f0f0e0);
            border-left: 5px solid var(--teacher-accent);
            padding: 20px 25px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        /* Academic Footer */
        .academic-footer {
            background: linear-gradient(135deg, var(--charcoal), #2c3e50);
            color: white;
            padding: 35px 0 25px;
            margin-top: auto;
            border-top: 3px solid var(--teacher-accent);
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
        
        /* Text Utilities */
        .text-danger {
            color: var(--error);
            font-weight: 700;
        }
        
        .text-warning {
            color: var(--warning);
        }
        
        .text-muted {
            color: var(--slate);
            opacity: 0.8;
        }
        
        /* Responsive Design */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1100px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .teacher-info {
                flex-direction: column;
                width: 100%;
            }
            
            .teacher-actions {
                justify-content: center;
                width: 100%;
            }
            
            .user-greeting {
                text-align: center;
                width: 100%;
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
            
            .academic-table {
                display: block;
                overflow-x: auto;
            }
            
            .academic-table th,
            .academic-table td {
                padding: 12px 10px;
                font-size: 0.9rem;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .fines-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .fines-summary {
                grid-template-columns: 1fr;
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
            
            .fine-item {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Academic Header - Teacher Variant -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <a href="../../landing.php" class="dashboard-brand">
                    <div class="dashboard-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span style="color: var(--teacher-accent);">Library</span></h1>
                        <p>Faculty Academic Portal</p>
                    </div>
                </a>
                
                <div class="teacher-info">
                    <div class="teacher-actions">
                        <a href="fines_history.php" class="action-btn-teacher">
                            <i class="fas fa-money-bill-wave"></i> Fines History
                        </a>
                        <a href="course_requests.php" class="action-btn-teacher">
                            <i class="fas fa-chalkboard-teacher"></i> Course Requests
                        </a>
                    </div>
                    
                    <div class="user-greeting">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="role-badge">Faculty Member</span>
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
                <h1>Faculty Academic Dashboard</h1>
                <p>Manage library resources, course materials, and teaching resources with extended privileges</p>
            </div>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Borrowed Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $borrowed_books->num_rows; ?></div>
                        <div class="stat-label">Currently Borrowed</div>
                    </div>
                    <div class="card-actions">
                        <a href="#borrowed-books" class="action-btn">View All</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Overdue Books</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $overdue_books->num_rows; ?></div>
                        <div class="stat-label">Require Attention</div>
                    </div>
                    <div class="card-actions">
                        <a href="#overdue-books" class="action-btn">Check Now</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3>Total Fines</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">₱<?php echo number_format($fines_data['total_unpaid'] ?? 0, 2); ?></div>
                        <div class="stat-label">Unpaid Balance</div>
                    </div>
                    <div class="card-actions">
                        <a href="#fines-section" class="action-btn">Pay Now</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-bookmark"></i>
                        </div>
                        <h3>Reservations</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $reservations->num_rows; ?></div>
                        <div class="stat-label">Active Reservations</div>
                    </div>
                    <div class="card-actions">
                        <a href="#reservations" class="action-btn">View All</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard"></i>
                        </div>
                        <h3>Course Materials</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $course_materials->num_rows; ?></div>
                        <div class="stat-label">Requested Items</div>
                    </div>
                    <div class="card-actions">
                        <a href="#course-materials" class="action-btn">Manage</a>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Reading History</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $borrowing_history->num_rows; ?>+</div>
                        <div class="stat-label">Books Read</div>
                    </div>
                    <div class="card-actions">
                        <a href="#history" class="action-btn">View History</a>
                    </div>
                </div>
            </div>
            
            <!-- Overdue Books Section -->
            <div class="section-container" id="overdue-books">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Overdue Books</h2>
                </div>
                
                <?php if ($overdue_books->num_rows > 0): ?>
                <div class="academic-message warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Attention: <?php echo $overdue_books->num_rows; ?> Overdue Book(s)</strong>
                        <p>Return overdue books immediately to avoid additional fines. As faculty, you're expected to maintain good standing.</p>
                    </div>
                </div>
                
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($book = $overdue_books->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td>
                                <span class="text-danger">
                                    <?php echo date('M d, Y', strtotime($book['due_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="time-badge badge-danger">
                                    <?php echo $book['days_overdue']; ?> days
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="returnBook('<?php echo $book['record_id']; ?>', '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-undo"></i> Return
                                    </button>
                                    <button class="btn-action btn-success" onclick="payFine('<?php echo $book['record_id']; ?>', '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-credit-card"></i> Pay Fine
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
                        <strong>Excellent! No Overdue Books</strong>
                        <p>You're maintaining excellent library standing. Keep up the good work.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Currently Borrowed Books -->
            <div class="section-container" id="borrowed-books">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-bookmark"></i> Your Borrowed Books</h2>
                </div>
                
                <?php if ($borrowed_books->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Days Remaining</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($book = $borrowed_books->fetch_assoc()): 
                            $due_date = new DateTime($book['due_date']);
                            $today = new DateTime();
                            $is_overdue = $today > $due_date;
                            $interval = $today->diff($due_date);
                            $days_remaining = $is_overdue ? 0 : $interval->days;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($book['borrow_date'])); ?></td>
                            <td>
                                <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($book['due_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="time-badge <?php echo $is_overdue ? 'badge-danger' : ($days_remaining <= 7 ? 'badge-warning' : 'badge-success'); ?>">
                                    <?php echo $is_overdue ? 'Overdue' : $days_remaining . ' days'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="returnBook('<?php echo $book['record_id']; ?>', '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-undo"></i> Return
                                    </button>
                                    <?php if (!$is_overdue): ?>
                                    <button class="btn-action btn-approve" onclick="renewBook('<?php echo $book['record_id']; ?>', '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-redo"></i> Renew
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message info-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>No Books Currently Borrowed</strong>
                        <p>Browse our academic collection to find resources for your teaching and research.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Available Books -->
            <div class="section-container" id="available-books">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-book"></i> Available Books</h2>
                </div>
                
                <?php if ($available_books->num_rows > 0): ?>
                <div class="search-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" placeholder="Search books by title, author, or category..." onkeyup="searchBooks(this.value)">
                    </div>
                    <button class="btn-clear" onclick="clearSearch()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
                
                <table class="academic-table" id="booksTable">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Available Copies</th>
                            <th>Borrow Period</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($book = $available_books->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td>
                                <span class="time-badge <?php echo $book['copies_available'] <= 2 ? 'badge-warning' : 'badge-success'; ?>">
                                    <?php echo $book['copies_available']; ?> available
                                </span>
                            </td>
                            <td><?php echo $borrowing_limit; ?> days</td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-approve" onclick="borrowBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-book"></i> Borrow
                                    </button>
                                    <button class="btn-action btn-reserve" onclick="reserveBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-bookmark"></i> Reserve
                                    </button>
                                    <button class="btn-action btn-course" onclick="requestForCourse(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-chalkboard"></i> Course Use
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Limited Availability</strong>
                        <p>Our academic collection is currently being updated. Please check back soon or inquire at the library desk.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- My Reservations -->
            <div class="section-container" id="reservations">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-bookmark"></i> My Reservations</h2>
                </div>
                
                <?php if ($reservations->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Reservation Date</th>
                            <th>Expiry Date</th>
                            <th>Queue Position</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($reservation = $reservations->fetch_assoc()): 
                            $expiry_date = new DateTime($reservation['expiry_date']);
                            $today = new DateTime();
                            $days_left = $today->diff($expiry_date)->days;
                            $is_expiring_soon = $days_left <= 2;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reservation['title']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                            <td>
                                <span class="<?php echo $is_expiring_soon ? 'text-danger' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($reservation['expiry_date'])); ?>
                                    <?php if ($is_expiring_soon): ?>
                                    <br><small class="text-danger">(<?php echo $days_left; ?> days left)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reservation['queue_position'] == 0): ?>
                                    <span class="time-badge badge-success">Next in line</span>
                                <?php else: ?>
                                    <span class="time-badge badge-info">#<?php echo $reservation['queue_position'] + 1; ?> in queue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-pending">
                                    Active
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-danger" onclick="cancelReservation(<?php echo $reservation['id']; ?>, '<?php echo addslashes($reservation['title']); ?>')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <?php if ($reservation['queue_position'] == 0): ?>
                                    <button class="btn-action btn-success" onclick="checkAvailability(<?php echo $reservation['book_id']; ?>)">
                                        <i class="fas fa-bell"></i> Check Availability
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message info-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>No Active Reservations</strong>
                        <p>Reserve currently unavailable books to secure your place in the queue for academic resources.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Course Materials -->
            <div class="section-container" id="course-materials">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Course Materials</h2>
                </div>
                
                <div class="course-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Request Books for Your Courses</strong>
                        <p>Faculty members can request books to be reserved for specific courses. These books get priority in reservations and extended loan periods.</p>
                    </div>
                </div>
                
                <div class="course-actions">
                    <button class="btn btn-primary" onclick="showCourseRequestForm()">
                        <i class="fas fa-plus-circle"></i> Request New Course Material
                    </button>
                </div>
                
                <?php if ($course_materials->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Course</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($material = $course_materials->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($material['title']); ?></td>
                            <td><?php echo htmlspecialchars($material['author']); ?></td>
                            <td><?php echo htmlspecialchars($material['course_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($material['request_date'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $material['status'] == 'approved' ? 'status-approved' : 'status-pending'; ?>">
                                    <?php echo ucfirst($material['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="viewCourseMaterial(<?php echo $material['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <?php if ($material['status'] == 'pending'): ?>
                                    <button class="btn-action btn-danger" onclick="cancelCourseRequest(<?php echo $material['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message info-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>No Course Material Requests</strong>
                        <p>Start by requesting books for your courses to provide essential resources for your students.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Borrowing History -->
            <div class="section-container" id="history">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-history"></i> Borrowing History</h2>
                </div>
                
                <?php if ($borrowing_history->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($record = $borrowing_history->fetch_assoc()): 
                            $borrow_date = new DateTime($record['borrow_date']);
                            $return_date = $record['return_date'] ? new DateTime($record['return_date']) : null;
                            $duration = $return_date ? $borrow_date->diff($return_date)->days : 'N/A';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['title']); ?></td>
                            <td><?php echo htmlspecialchars($record['author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($record['borrow_date'])); ?></td>
                            <td>
                                <?php if ($record['return_date']): ?>
                                    <?php echo date('M d, Y', strtotime($record['return_date'])); ?>
                                <?php else: ?>
                                    <span class="text-warning">Not returned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($duration !== 'N/A'): ?>
                                    <span class="time-badge badge-info"><?php echo $duration; ?> days</span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message info-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Begin Your Research Journey</strong>
                        <p>Start exploring our academic collection to build your research history and teaching resources.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Fines Summary -->
            <div class="section-container" id="fines-section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> Fines Summary</h2>
                </div>
                
                <div class="fines-summary">
                    <div class="fine-item">
                        <div class="fine-label">Total Unpaid Fines:</div>
                        <div class="fine-amount text-danger">
                            ₱<?php echo number_format($fines_data['total_unpaid'] ?? 0, 2); ?>
                        </div>
                    </div>
                    <div class="fine-item">
                        <div class="fine-label">Total Paid Fines:</div>
                        <div class="fine-amount text-success">
                            ₱<?php echo number_format($fines_data['total_paid'] ?? 0, 2); ?>
                        </div>
                    </div>
                </div>
                
                <div class="fines-actions">
                    <button class="btn btn-primary" onclick="viewAllFines()">
                        <i class="fas fa-list"></i> View All Fines
                    </button>
                    <button class="btn btn-success" onclick="payAllFines()" <?php echo ($fines_data['total_unpaid'] ?? 0) <= 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-credit-card"></i> Pay All Fines
                    </button>
                    <button class="btn btn-info" onclick="requestFineWaiver()">
                        <i class="fas fa-handshake"></i> Request Waiver
                    </button>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Academic Footer -->
    <footer class="academic-footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. Faculty Portal.</p>
                <p>Borrowing Limit: <?php echo $max_books; ?> books | Borrowing Period: <?php echo $borrowing_limit; ?> days | Max Reservations: <?php echo $max_reservations; ?></p>
                <small><i class="fas fa-user-graduate"></i> Faculty privileges applied. Your academic status is verified.</small>
            </div>
        </div>
    </footer>
    
    <script src="../../assets/js/scripts.js"></script>
    <script>
    /* ========== Backend Functions (Preserved) ========== */
    
    function borrowBook(bookId, bookTitle) {
        if (!confirm('Borrow "' + bookTitle + '" for <?php echo $borrowing_limit; ?> days?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('book_id', bookId);
        formData.append('user_role', 'teacher');
        
        fetch('../../includes/borrow_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Book borrowed successfully! Due date: ' + data.due_date);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function returnBook(recordId, bookTitle) {
        if (!confirm('Return "' + bookTitle + '"?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('record_id', recordId);
        
        fetch('../../includes/return_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.fine_applied && data.fine_amount > 0) {
                    alert('Book returned successfully!\n\nFine applied: ₱' + data.fine_amount.toFixed(2) + '\nReason: ' + data.reason);
                } else {
                    alert('Book returned successfully!');
                }
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function renewBook(recordId, bookTitle) {
        if (!confirm('Renew "' + bookTitle + '" for another <?php echo $borrowing_limit; ?> days?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('record_id', recordId);
        formData.append('user_role', 'teacher');
        
        fetch('../../includes/renew_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Book renewed successfully! New due date: ' + data.new_due_date);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function reserveBook(bookId, bookTitle) {
        if (!confirm('Reserve "' + bookTitle + '"?\n\nYou will be notified when it becomes available.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('book_id', bookId);
        formData.append('user_role', 'teacher');
        
        fetch('../../includes/reserve_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Book reserved successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function cancelReservation(reservationId, bookTitle) {
        if (!confirm('Cancel reservation for "' + bookTitle + '"?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('reservation_id', reservationId);
        
        fetch('../../includes/cancel_reservation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reservation cancelled successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function checkAvailability(bookId) {
        const formData = new FormData();
        formData.append('book_id', bookId);
        
        fetch('../../includes/check_availability.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                if (confirm('Book is now available!\n\nWould you like to borrow it?')) {
                    borrowBook(bookId, 'Available Book');
                }
            } else {
                alert('Book is still unavailable. We will notify you when it becomes available.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function payFine(recordId, bookTitle) {
        const amount = prompt('Enter fine amount for "' + bookTitle + '":', '50.00');
        if (!amount || isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount.');
            return;
        }
        
        const paymentMethod = prompt('Enter payment method (Cash/Card/Online):', 'Cash');
        if (!paymentMethod) return;
        
        const formData = new FormData();
        formData.append('record_id', recordId);
        formData.append('amount', amount);
        formData.append('method', paymentMethod);
        
        fetch('../../includes/pay_fine.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Fine paid successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function payAllFines() {
        const totalAmount = <?php echo $fines_data['total_unpaid'] ?? 0; ?>;
        if (totalAmount <= 0) {
            alert('You have no unpaid fines.');
            return;
        }
        
        if (!confirm('Pay all fines totaling ₱' + totalAmount.toFixed(2) + '?')) {
            return;
        }
        
        const paymentMethod = prompt('Enter payment method (Cash/Card/Online):', 'Cash');
        if (!paymentMethod) return;
        
        const formData = new FormData();
        formData.append('amount', totalAmount);
        formData.append('method', paymentMethod);
        
        fetch('../../includes/pay_all_fines.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('All fines paid successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function viewAllFines() {
        window.open('fines_history.php', 'Fines History', 'width=1200,height=800');
    }
    
    function requestFineWaiver() {
        const reason = prompt('Please explain why you are requesting a fine waiver:', 'Course material / Research purposes');
        if (reason) {
            alert('Fine waiver request submitted. Library staff will review your request.');
        }
    }
    
    function requestForCourse(bookId, bookTitle) {
        console.log('DEBUG - Function called with:', {bookId, bookTitle});
        
        const courseName = prompt('Enter course name for "' + bookTitle + '":');
        console.log('DEBUG - Course name entered:', courseName);
        
        if (!courseName) {
            console.log('DEBUG - User cancelled at course name');
            return;
        }
        
        const semester = prompt('Enter semester:');
        console.log('DEBUG - Semester entered:', semester);
        
        if (!semester) {
            console.log('DEBUG - User cancelled at semester');
            return;
        }
        
        console.log('DEBUG - Preparing to send request...');
        
        const formData = new FormData();
        formData.append('book_id', bookId);
        formData.append('course_name', courseName);
        formData.append('semester', semester);
        
        console.log('DEBUG - FormData prepared:', {
            book_id: bookId,
            course_name: courseName,
            semester: semester
        });
        
        const url = '../../includes/request_course_material.php';
        console.log('DEBUG - Fetch URL:', url);
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('DEBUG - Response status:', response.status);
            console.log('DEBUG - Response headers:', response.headers);
            return response.text();
        })
        .then(text => {
            console.log('DEBUG - Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('DEBUG - JSON parse error:', e);
                return {success: false, message: 'Invalid JSON response: ' + text};
            }
        })
        .then(data => {
            console.log('DEBUG - Parsed data:', data);
            if (data.success) {
                alert('Course material request submitted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('DEBUG - Fetch error:', error);
            alert('An error occurred. Please check console for details.');
        });
    }
    
    function showCourseRequestForm() {
        const courseName = prompt('Enter course name:');
        if (!courseName) return;
        
        const bookTitle = prompt('Enter book title (or ISBN):');
        if (!bookTitle) return;
        
        const reason = prompt('Why do you need this book for your course?');
        if (!reason) return;
        
        const formData = new FormData();
        formData.append('course_name', courseName);
        formData.append('book_info', bookTitle);
        formData.append('reason', reason);
        
        fetch('../../includes/request_course_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Course book request submitted successfully! Library staff will review your request.');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function viewCourseMaterial(materialId) {
        window.open('course_material_details.php?id=' + materialId, 'Course Material Details', 'width=600,height=400');
    }
    
    function cancelCourseRequest(requestId) {
        if (!confirm('Cancel this course material request?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('request_id', requestId);
        
        fetch('../../includes/cancel_course_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Course request cancelled successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
    
    function searchBooks(query) {
        const table = document.getElementById('booksTable');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const lowerQuery = query.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(lowerQuery) ? '' : 'none';
        });
    }
    
    function clearSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.value = '';
            searchBooks('');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId !== '#') {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });
        
        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function() {
                // You can add custom tooltip logic here if needed
            });
        });
    });
    </script>
</body>
</html>