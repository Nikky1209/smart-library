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

$overdue_query = "SELECT 
                    b.*, 
                    br.id as record_id, 
                    br.borrow_date, 
                    br.due_date, 
                    br.status,
                    DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                    f.amount as fine_amount,
                    f.paid as fine_paid
                  FROM borrowing_records br 
                  JOIN books b ON br.book_id = b.id 
                  LEFT JOIN fines f ON br.id = f.borrowing_id
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
                    SUM(CASE WHEN f.paid = TRUE THEN f.amount ELSE 0 END) as total_paid,
                    COUNT(CASE WHEN f.paid = FALSE THEN 1 END) as unpaid_count
                 FROM fines f
                 JOIN borrowing_records br ON f.borrowing_id = br.id
                 WHERE br.user_id = ?";
$stmt3 = $conn->prepare($fines_query);
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$fines_result = $stmt3->get_result();
$fines_data = $fines_result->fetch_assoc();

$available_books_query = "SELECT * FROM books WHERE copies_available >= 0 ORDER BY title LIMIT 10";
$available_books = $conn->query($available_books_query);

$history_query = "SELECT b.title, b.author, br.borrow_date, br.return_date 
                  FROM borrowing_records br 
                  JOIN books b ON br.book_id = b.id 
                  WHERE br.user_id = ? AND br.status = 'returned' 
                  ORDER BY br.borrow_date DESC LIMIT 5";
$stmt4 = $conn->prepare($history_query);
$stmt4->bind_param("i", $user_id);
$stmt4->execute();
$borrowing_history = $stmt4->get_result();

$reservations_query = "SELECT r.*, b.title, b.author, b.isbn, 
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

$conn->close();

function calculateFine($days_overdue) {
    $grace_period = 2;
    $daily_rate = 0.50;
    $max_fine_per_book = 25.00;
    
    if ($days_overdue <= $grace_period) {
        return 0;
    }
    
    $fine_days = $days_overdue - $grace_period;
    $fine = $fine_days * $daily_rate;
    
    return min($fine, $max_fine_per_book);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SmartLibrary Academic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@300;400;700&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Academic Student Dashboard Styles - Matches Landing Page */
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
        
        .student-info {
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
        
        /* Student Stats Grid */
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
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #FF9800, #EF6C00); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #9C27B0, #6A1B9A); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #2196F3, #0D47A1); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #607D8B, #37474F); }
        
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
        
        /* Section Container */
        .section-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .section-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-main), var(--primary-light));
        }
        
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-family: 'Merriweather', serif;
            color: var(--primary-dark);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Messages */
        .academic-message {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            border-left: 4px solid transparent;
        }
        
        .warning-message {
            background-color: #fff3cd;
            border-left-color: var(--warning);
            color: #856404;
        }
        
        .warning-message i {
            color: var(--warning);
            font-size: 1.5rem;
        }
        
        .success-message {
            background-color: #d4edda;
            border-left-color: var(--success);
            color: #155724;
        }
        
        .success-message i {
            color: var(--success);
            font-size: 1.5rem;
        }
        
        .info-message {
            background-color: #d1ecf1;
            border-left-color: var(--info);
            color: #0c5460;
        }
        
        .info-message i {
            color: var(--info);
            font-size: 1.5rem;
        }
        
        /* Academic Tables */
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
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .status-borrowed {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        /* Time Badges */
        .time-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* Action Buttons */
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            white-space: nowrap;
        }
        
        .btn-view {
            background-color: var(--info);
            color: white;
        }
        
        .btn-view:hover {
            background-color: #0277bd;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn-approve {
            background-color: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background-color: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn-reserve {
            background-color: #FF9800;
            color: white;
        }
        
        .btn-reserve:hover {
            background-color: #e68900;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--error);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c62828;
            transform: translateY(-2px);
        }
        
        /* Search Box */
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
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
        
        .btn-clear {
            padding: 12px 20px;
            background: var(--slate);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-clear:hover {
            background: var(--charcoal);
            transform: translateY(-2px);
        }
        
        /* Payment Options */
        .payment-options {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .payment-options h3 {
            color: var(--primary-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .option-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .option-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .option-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-main), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .option-icon i {
            font-size: 1.5rem;
            color: white;
        }
        
        .option-content h4 {
            color: var(--primary-dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .option-content p {
            color: var(--slate);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        /* Fines Summary */
        .fines-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .fine-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .fine-label {
            color: var(--slate);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .fine-amount {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .text-danger {
            color: var(--error);
        }
        
        .text-success {
            color: var(--success);
        }
        
        .fine-count {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
        }
        
        .fines-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
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
            background: var(--primary-main);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn-info:hover {
            background: #0277bd;
            transform: translateY(-2px);
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
        
        /* Text Utilities */
        .text-danger {
            color: var(--error);
            font-weight: 600;
        }
        
        .text-warning {
            color: var(--warning);
        }
        
        .text-muted {
            color: var(--slate);
            opacity: 0.7;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .options-grid {
                grid-template-columns: 1fr;
            }
            
            .fines-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .student-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .user-greeting {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-container {
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
            
            .section-title {
                font-size: 1.3rem;
            }
            
            .academic-message {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Academic Header -->
    <header class="academic-header">
        <div class="container">
            <div class="header-content">
                <a href="../../landing.php" class="dashboard-brand">
                    <div class="dashboard-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="brand-text">
                        <h1>Smart<span style="color: var(--accent-gold);">Library</span></h1>
                        <p>Student Academic Portal</p>
                    </div>
                </a>
                
                <div class="student-info">
                    <div class="user-greeting">
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                        <span class="role-badge">Student Member</span>
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
                <h1>Student Academic Dashboard</h1>
                <p>Manage your library account, explore resources, and track your academic journey</p>
            </div>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Current Borrowings</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $borrowed_books->num_rows; ?></div>
                        <div class="stat-label">Books in Your Possession</div>
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
                        <h3>Overdue Items</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $overdue_books->num_rows; ?></div>
                        <div class="stat-label">Require Immediate Attention</div>
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
                        <h3>Outstanding Fines</h3>
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
                        <h3>Active Reservations</h3>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $reservations->num_rows; ?></div>
                        <div class="stat-label">Books Reserved</div>
                    </div>
                    <div class="card-actions">
                        <a href="#reservations" class="action-btn">View All</a>
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
                    <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Overdue Books & Fines</h2>
                </div>
                
                <?php if ($overdue_books->num_rows > 0): ?>
                <div class="academic-message warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Attention Required: <?php echo $overdue_books->num_rows; ?> Overdue Item(s)</strong>
                        <p>Return overdue books immediately to avoid additional penalties. Current unpaid balance: <strong>₱<?php echo number_format($fines_data['total_unpaid'] ?? 0, 2); ?></strong></p>
                    </div>
                </div>
                
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Due Date</th>
                            <th>Overdue By</th>
                            <th>Fine Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_fine_due = 0;
                        $overdue_books->data_seek(0);
                        while($book = $overdue_books->fetch_assoc()): 
                            $due_date = new DateTime($book['due_date']);
                            $today = new DateTime();
                            $days_overdue = $today->diff($due_date)->days;
                            if ($today > $due_date) {
                                $days_overdue = $days_overdue * 1;
                            }
                            
                            if ($book['fine_amount'] === null) {
                                $fine_amount = calculateFine($days_overdue);
                            } else {
                                $fine_amount = $book['fine_amount'];
                            }
                            
                            $fine_status = $book['fine_paid'] ? 'Paid' : 'Unpaid';
                            $total_fine_due += ($book['fine_paid'] ? 0 : $fine_amount);
                        ?>
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
                                    <?php echo $days_overdue; ?> days
                                </span>
                            </td>
                            <td>
                                <strong class="text-danger">
                                    ₱<?php echo number_format($fine_amount, 2); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $book['fine_paid'] ? 'status-approved' : 'status-overdue'; ?>">
                                    <?php echo $fine_status; ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="returnBook(<?php echo $book['record_id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                        <i class="fas fa-undo"></i> Return
                                    </button>
                                    <?php if (!$book['fine_paid'] && $fine_amount > 0): ?>
                                    <button class="btn-action btn-success" onclick="payFine(<?php echo $book['record_id']; ?>, <?php echo $fine_amount; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                        <i class="fas fa-credit-card"></i> Pay
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; padding: 15px;">
                                <strong>Total Amount Due:</strong>
                            </td>
                            <td colspan="2">
                                <strong class="text-danger" style="font-size: 1.2rem;">
                                    ₱<?php echo number_format($total_fine_due, 2); ?>
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Payment Options -->
                <div class="payment-options">
                    <h3><i class="fas fa-credit-card"></i> Payment Resolution Options</h3>
                    <div class="options-grid">
                        <div class="option-card">
                            <div class="option-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="option-content">
                                <h4>Pay at Library Desk</h4>
                                <p>Visit the library circulation desk during operational hours to settle your fines in person.</p>
                                <button class="btn-action btn-view" onclick="generateReceipt()">
                                    <i class="fas fa-print"></i> Print Receipt
                                </button>
                            </div>
                        </div>
                        
                        <div class="option-card">
                            <div class="option-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="option-content">
                                <h4>Online Payment Portal</h4>
                                <p>Pay securely online using institutional payment gateway (Available Soon).</p>
                                <button class="btn-action btn-success" disabled>
                                    <i class="fas fa-lock"></i> Coming Soon
                                </button>
                            </div>
                        </div>
                        
                        <div class="option-card">
                            <div class="option-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div class="option-content">
                                <h4>Fine Forgiveness Program</h4>
                                <p>Return overdue materials within 7 days to qualify for 50% fine reduction.</p>
                                <button class="btn-action btn-info" onclick="applyForgiveness()">
                                    <i class="fas fa-gift"></i> Apply Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="academic-message success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Excellent! No Overdue Books</strong>
                        <p>You're managing your borrowings well. Keep returning books on time to maintain your good standing.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Currently Borrowed Books -->
            <div class="section-container" id="borrowed-books">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-bookmark"></i> Currently Borrowed Books</h2>
                </div>
                
                <?php if ($borrowed_books->num_rows > 0): ?>
                <table class="academic-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Time Remaining</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $borrowed_books->data_seek(0);
                        while($book = $borrowed_books->fetch_assoc()): 
                            $due_date = new DateTime($book['due_date']);
                            $today = new DateTime();
                            $is_overdue = $today > $due_date;
                            $interval = $today->diff($due_date);
                            $days_left = $is_overdue ? 0 : $interval->days;
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
                                <?php if ($is_overdue): ?>
                                    <span class="time-badge badge-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="time-badge <?php echo $days_left <= 3 ? 'badge-danger' : ($days_left <= 7 ? 'badge-warning' : 'badge-success'); ?>">
                                        <?php echo $days_left; ?> days
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $is_overdue ? 'status-overdue' : 'status-borrowed'; ?>">
                                    <?php echo $is_overdue ? 'Overdue' : 'Borrowed'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-view" onclick="returnBook(<?php echo $book['record_id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')">
                                        <i class="fas fa-undo"></i> Return
                                    </button>
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
                        <p>Browse our collection below to discover books for your academic needs.</p>
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
                            <th>Availability</th>
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
                                    <?php echo $book['copies_available']; ?> copies
                                </span>
                            </td>
                            <td>14 days</td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-action btn-approve" onclick="borrowBook(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-book"></i> Borrow
                                    </button>
                                    <button class="btn-action btn-reserve" onclick="reserveBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['title']); ?>')">
                                        <i class="fas fa-bookmark"></i> Reserve
                                    </button>
                                    <button class="btn-action btn-view" onclick="viewBookDetails(<?php echo $book['id']; ?>)">
                                        <i class="fas fa-info-circle"></i> Details
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
                        <p>Our collection is currently being updated. Please check back soon or inquire at the library desk.</p>
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
                                    <span class="time-badge badge-info">#<?php echo $reservation['queue_position'] + 1; ?></span>
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
                                    <button class="btn-action btn-success" onclick="checkAvailability(<?php echo $reservation['book_id']; ?>, <?php echo $reservation['id']; ?>)">
                                        <i class="fas fa-bell"></i> Notify
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
                        <p>Reserve currently unavailable books to secure your place in the queue.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Borrowing History -->
            <div class="section-container" id="history">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-history"></i> Recent Borrowing History</h2>
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
                            <th>Status</th>
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
                                    <span class="text-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($duration !== 'N/A'): ?>
                                    <span class="time-badge badge-info"><?php echo $duration; ?> days</span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-approved">
                                    Completed
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="academic-message info-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Begin Your Academic Journey</strong>
                        <p>Start exploring our collection to build your reading history.</p>
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
                        <div class="fine-label">Unpaid Fines</div>
                        <div class="fine-amount text-danger">
                            ₱<?php echo number_format($fines_data['total_unpaid'] ?? 0, 2); ?>
                        </div>
                    </div>
                    <div class="fine-item">
                        <div class="fine-label">Paid Fines</div>
                        <div class="fine-amount text-success">
                            ₱<?php echo number_format($fines_data['total_paid'] ?? 0, 2); ?>
                        </div>
                    </div>
                    <div class="fine-item">
                        <div class="fine-label">Outstanding Items</div>
                        <div class="fine-count">
                            <?php echo $fines_data['unpaid_count'] ?? 0; ?> items
                        </div>
                    </div>
                </div>
                
                <div class="fines-actions">
                    <button class="btn btn-primary" onclick="viewAllFines()">
                        <i class="fas fa-list"></i> View Detailed History
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
                <p>&copy; <?php echo date('Y'); ?> SmartLibrary Academic System. All rights reserved.</p>
                <p>Student Portal • Borrowing Limit: 5 books • Period: 14 days</p>
                <small><i class="fas fa-shield-alt"></i> Secure academic environment. Your privacy is protected.</small>
            </div>
        </div>
    </footer>
    
    <script src="../../assets/js/scripts.js"></script>
    <script>
        function borrowBook(bookId) {
        if (!confirm('Borrow this book for 14 days?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('book_id', bookId);
        
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
    
    
    const returnBtn = event.target;
    const originalText = returnBtn.innerHTML;
    returnBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Returning...';
    returnBtn.disabled = true;
    
    fetch('../../includes/return_book.php', {
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
            if (data.fine_applied && data.fine_amount > 0) {
                alert('✅ Book returned successfully!\n\n📚 Book: ' + bookTitle + 
                      '\n💰 Fine applied: ₱' + data.fine_amount.toFixed(2) + 
                      '\n📝 Reason: ' + data.reason);
            } else {
                alert('✅ Book returned successfully!\n\n📚 Book: ' + bookTitle);
            }
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            alert('❌ Error: ' + data.message);
            returnBtn.innerHTML = originalText;
            returnBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred. Please try again.');
        returnBtn.innerHTML = originalText;
        returnBtn.disabled = false;
    });
}

function reserveBook(bookId, bookTitle) {
    if (!confirm('Reserve "' + bookTitle + '"?\n\nYou will be notified when it becomes available.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('book_id', bookId);
    
   
    const reserveBtn = event.target;
    const originalText = reserveBtn.innerHTML;
    reserveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reserving...';
    reserveBtn.disabled = true;
    
    fetch('../../includes/reserve_book.php', {
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
            alert('✅ Book reserved successfully!\n\n📚 Book: ' + bookTitle + 
                  '\n📅 Reservation expires: ' + data.expiry_date + 
                  '\n📊 Your position in queue: ' + data.queue_position);
           
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            if (data.available) {
                
                if (confirm('This book is currently available!\n\nWould you like to borrow it instead?')) {
                    borrowBook(bookId);
                }
            } else {
                alert('❌ Error: ' + data.message);
            }
            reserveBtn.innerHTML = originalText;
            reserveBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred. Please try again.');
        reserveBtn.innerHTML = originalText;
        reserveBtn.disabled = false;
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
            alert('✅ Reservation cancelled successfully!');
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred. Please try again.');
    });
}


function checkAvailability(bookId, reservationId) {
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
                borrowBook(bookId);
            }
        } else {
            alert('Book is still unavailable. We will notify you when it becomes available.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ An error occurred. Please try again.');
    });
}


function payFine(recordId, amount, bookTitle, renewAfterPayment = false) {
    const paymentMethod = prompt('Enter payment method (Cash/Card/Online):', 'Cash');
    if (!paymentMethod) {
        return;
    }
    
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
            alert('✅ Fine paid successfully!\n\n📚 Book: ' + bookTitle + 
                  '\n💰 Amount: ₱' + amount.toFixed(2) + 
                  '\n💳 Payment method: ' + paymentMethod);
            
            if (renewAfterPayment) {
               
                setTimeout(() => {
                    renewBook(recordId, bookTitle);
                }, 500);
            } else {
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Payment failed. Please try again.');
    });
}
    
    
    function payAllFines() {
        const totalAmount = <?php echo $fines_data['total_unpaid'] ?? 0; ?>;
        if (totalAmount <= 0) {
            alert('You have no unpaid fines.');
            return;
        }
        
        if (!confirm(`Pay all fines totaling $${totalAmount.toFixed(2)}?`)) {
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
                alert('All fines paid successfully!\nPayment method: ' + paymentMethod + '\nTransaction ID: ' + data.transaction_id);
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
    
    
function viewBookDetails(bookId) {
    console.log('Loading details for book ID:', bookId);
    
   
    showLoading();
    
    
    fetch(`../../includes/get_book_details.php?id=${bookId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            if (data.success) {
                displayBookDetails(data);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error fetching book details:', error);
            alert('Failed to load book details. Please try again.');
        });
}


function displayBookDetails(data) {
    const book = data.book;
    
    
    function getValue(value, fallback = 'N/A') {
        return value && value !== 'N/A' && value !== 'undefined' ? value : fallback;
    }
    
    
    function formatNumber(num) {
        return num && !isNaN(num) ? num : 0;
    }
    
    
    let similarBooksHTML = '';
    if (data.similar_books && data.similar_books.length > 0) {
        similarBooksHTML = `
            <div class="similar-books">
                <h3>📚 Similar Books</h3>
                <div class="similar-books-grid">
                    ${data.similar_books.map(similar => `
                        <div class="similar-book-card" onclick="viewBookDetails(${similar.id})">
                            <img src="/smart-library/assets/images/books/${getValue(similar.cover_image, 'default-book.jpg')}" 
                                 alt="${getValue(similar.title, 'Book')}"
                                 onerror="this.src='/smart-library/assets/images/books/default-book.jpg'">
                            <div class="similar-book-info">
                                <h4>${getValue(similar.title, 'Untitled Book')}</h4>
                                <p>${getValue(similar.author, 'Unknown Author')}</p>
                                <span class="badge ${(similar.copies_available || 0) > 0 ? 'success' : 'warning'}">
                                    ${getValue(similar.copies_available, 0)} available
                                </span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    
   
    let reviewsHTML = '';
    if (data.reviews && data.reviews.length > 0) {
        reviewsHTML = `
            <div class="reviews-list">
                ${data.reviews.map(review => `
                    <div class="review-card">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <img src="/smart-library/assets/images/profiles/${getValue(review.profile_picture, 'default-avatar.jpg')}" 
                                     alt="${getValue(review.username, 'Reader')}" 
                                     class="reviewer-avatar"
                                     onerror="this.src='/smart-library/assets/images/profiles/default-avatar.jpg'">
                                <div>
                                    <strong>${getValue(review.username, 'Reader')}</strong>
                                    <div class="review-rating">
                                        ${'⭐'.repeat(review.rating || 0)}${'☆'.repeat(5 - (review.rating || 0))}
                                        <span class="rating-value">${getValue(review.rating, 0)}.0</span>
                                    </div>
                                </div>
                            </div>
                            <span class="review-date">
                                ${review.created_at ? new Date(review.created_at).toLocaleDateString() : 'Recently'}
                            </span>
                        </div>
                        <div class="review-content">
                            <p>${getValue(review.comment, 'No comment provided.')}</p>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    } else {
        reviewsHTML = `
            <div class="no-reviews">
                <i class="fas fa-comment-slash"></i>
                <p>No reviews yet. Be the first to review this book!</p>
                ${data.user_info.has_borrowed ? 
                    `<button class="btn btn-info" onclick="showReviewForm(${book.id})">
                        <i class="fas fa-star"></i> Write First Review
                    </button>` : 
                    `<p class="text-muted">You need to borrow this book first to leave a review.</p>`
                }
            </div>
        `;
    }
    
   
    const modalHTML = `
        <div class="book-details-modal" id="bookDetailsModal">
            <div class="modal-overlay" onclick="closeBookDetails()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h2>📚 ${getValue(book.title, 'Untitled Book')}</h2>
                    <button class="close-btn" onclick="closeBookDetails()">&times;</button>
                </div>
                
                <div class="modal-body">
                    <!-- Info Banner for Generated Data -->
                    ${data.metadata && data.metadata.generated_fields && data.metadata.generated_fields.length > 0 ? `
                    <div class="info-banner">
                        <i class="fas fa-info-circle"></i>
                        <span>Some information was automatically generated to enhance your viewing experience.</span>
                    </div>
                    ` : ''}
                    
                    <div class="book-main-info">
                        <div class="book-cover">
                            <img src="/smart-library/assets/images/books/${getValue(book.cover_image, 'default-book.jpg')}" 
                                 alt="${getValue(book.title, 'Book')}" 
                                 onerror="this.src='/smart-library/assets/images/books/default-book.jpg'">
                            <div class="book-status">
                                <span class="status-badge ${(book.copies_available || 0) > 0 ? 'status-available' : 'status-unavailable'}">
                                    ${(book.copies_available || 0) > 0 ? 'Available' : 'Unavailable'}
                                </span>
                                <span class="copies-count">
                                    ${getValue(book.copies_available, 0)} of ${getValue(book.total_copies, 1)} copies available
                                </span>
                            </div>
                        </div>
                        
                        <div class="book-info">
                            <div class="info-grid">
                                <div class="info-item">
                                    <strong>👤 Author:</strong>
                                    <span>${getValue(book.author, 'Unknown Author')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>🏷️ Category:</strong>
                                    <span>${getValue(book.category, 'General')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>📖 ISBN:</strong>
                                    <span>${getValue(book.isbn, 'Not Available')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>🏢 Publisher:</strong>
                                    <span>${getValue(book.publisher, 'Unknown Publisher')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>📅 Published Year:</strong>
                                    <span>${getValue(book.published_year, 'Unknown')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>📚 Edition:</strong>
                                    <span>${getValue(book.edition, 'Unknown Edition')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>🗺️ Language:</strong>
                                    <span>${getValue(book.language, 'English')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>📄 Pages:</strong>
                                    <span>${getValue(book.total_pages, 'Unknown')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>📍 Location:</strong>
                                    <span>${getValue(book.location, 'Library Collection')}</span>
                                </div>
                                <div class="info-item">
                                    <strong>🔢 Call Number:</strong>
                                    <span>${getValue(book.call_number, 'Not Assigned')}</span>
                                </div>
                            </div>
                            
                            <div class="book-stats">
                                <div class="stat-item">
                                    <div class="stat-value">${formatNumber(book.total_borrows)}</div>
                                    <div class="stat-label">Total Borrows</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${formatNumber(book.active_reservations)}</div>
                                    <div class="stat-label">Active Reservations</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">⭐ ${getValue(book.average_rating, 0)}/5</div>
                                    <div class="stat-label">Rating (${formatNumber(book.review_count)} reviews)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Book Description -->
                    <div class="book-description">
                        <h3>📖 Description</h3>
                        <p>${getValue(book.description, 'No description available for this book.')}</p>
                    </div>
                    
                    <!-- Actions -->
                    <div class="book-actions">
                        ${(book.copies_available || 0) > 0 ? 
                            `<button class="btn btn-primary" onclick="borrowBook(${book.id}, '${(book.title || '').replace(/'/g, "\\'")}')">
                                <i class="fas fa-book"></i> Borrow Now
                            </button>` : 
                            `<button class="btn btn-reserve" onclick="reserveBook(${book.id}, '${(book.title || '').replace(/'/g, "\\'")}')">
                                <i class="fas fa-bookmark"></i> Reserve
                            </button>`
                        }
                        ${data.user_info && data.user_info.has_borrowed && !data.user_info.has_reviewed ? 
                            `<button class="btn btn-info" onclick="showReviewForm(${book.id})">
                                <i class="fas fa-star"></i> Write Review
                            </button>` : ''
                        }
                        <button class="btn btn-view" onclick="addToReadingList(${book.id})">
                            <i class="fas fa-bookmark"></i> Add to Reading List
                        </button>
                        <button class="btn btn-secondary" onclick="suggestBookDetails(${book.id})">
                            <i class="fas fa-edit"></i> Suggest Details
                        </button>
                    </div>
                    
                    <!-- Similar Books -->
                    ${similarBooksHTML}
                    
                    <!-- Reviews -->
                    <div class="book-reviews">
                        <div class="reviews-header">
                            <h3>⭐ Reviews (${formatNumber(book.review_count)})</h3>
                            ${data.user_info && data.user_info.has_borrowed && !data.user_info.has_reviewed ? 
                                `<button class="btn-small" onclick="showReviewForm(${book.id})">
                                    <i class="fas fa-plus"></i> Add Review
                                </button>` : ''
                            }
                        </div>
                        ${reviewsHTML}
                    </div>
                </div>
            </div>
        </div>
    `;
    
  
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
  
    document.body.style.overflow = 'hidden';
    
   
    document.addEventListener('keydown', handleModalKeyboard);
}


function suggestBookDetails(bookId) {
    const formHTML = `
        <div class="suggestion-modal" id="suggestionModal">
            <div class="modal-overlay" onclick="closeSuggestionForm()"></div>
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3>✏️ Suggest Book Details</h3>
                    <button class="close-btn" onclick="closeSuggestionForm()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="suggestionForm" onsubmit="submitSuggestion(event, ${bookId})">
                        <div class="form-group">
                            <label for="suggestDescription">Description:</label>
                            <textarea id="suggestDescription" name="description" 
                                      rows="4" 
                                      placeholder="Provide a better description for this book..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="suggestPublisher">Publisher:</label>
                            <input type="text" id="suggestPublisher" name="publisher" 
                                   placeholder="Enter publisher name">
                        </div>
                        
                        <div class="form-group">
                            <label for="suggestYear">Published Year:</label>
                            <input type="number" id="suggestYear" name="year" 
                                   min="1800" max="2024" 
                                   placeholder="YYYY">
                        </div>
                        
                        <div class="form-group">
                            <label for="suggestPages">Number of Pages:</label>
                            <input type="number" id="suggestPages" name="pages" 
                                   min="1" max="5000" 
                                   placeholder="e.g., 250">
                        </div>
                        
                        <div class="form-group">
                            <label for="suggestLanguage">Language:</label>
                            <input type="text" id="suggestLanguage" name="language" 
                                   placeholder="e.g., English, Spanish">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeSuggestionForm()">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Suggestion
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', formHTML);
}


function closeSuggestionForm() {
    const modal = document.getElementById('suggestionModal');
    if (modal) {
        modal.remove();
    }
}


function submitSuggestion(event, bookId) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    formData.append('book_id', bookId);
    
    fetch('../../includes/suggest_book_details.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Thank you for your suggestion! Library staff will review it.');
            closeSuggestionForm();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit suggestion. Please try again.');
    });
}


function addToReadingList(bookId) {
    const formData = new FormData();
    formData.append('book_id', bookId);
    
    fetch('../../includes/add_to_reading_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Added to your reading list!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add to reading list.');
    });
}


function closeBookDetails() {
    const modal = document.getElementById('bookDetailsModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleModalKeyboard);
    }
}


function handleModalKeyboard(event) {
    if (event.key === 'Escape') {
        closeBookDetails();
    }
}


function showLoading() {
    const loadingHTML = `
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-spinner">
                <i class="fas fa-book-open fa-spin"></i>
                <p>Loading book details...</p>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', loadingHTML);
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}


function showReviewForm(bookId) {
    const reviewHTML = `
        <div class="review-modal" id="reviewModal">
            <div class="modal-overlay" onclick="closeReviewForm()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>✍️ Write a Review</h3>
                    <button class="close-btn" onclick="closeReviewForm()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="reviewForm" onsubmit="submitReview(event, ${bookId})">
                        <div class="rating-section">
                            <label>Rating:</label>
                            <div class="star-rating">
                                ${[1,2,3,4,5].map(i => `
                                    <i class="far fa-star" data-rating="${i}" 
                                       onclick="setRating(${i})"></i>
                                `).join('')}
                            </div>
                            <input type="hidden" id="ratingValue" name="rating" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="reviewComment">Your Review:</label>
                            <textarea id="reviewComment" name="comment" 
                                      rows="5" 
                                      placeholder="Share your thoughts about this book..."
                                      required></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeReviewForm()">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', reviewHTML);
    
    
    document.querySelectorAll('.star-rating .fa-star').forEach(star => {
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            highlightStars(rating);
        });
        
        star.addEventListener('mouseleave', function() {
            const currentRating = parseInt(document.getElementById('ratingValue').value);
            highlightStars(currentRating);
        });
    });
}


function setRating(rating) {
    document.getElementById('ratingValue').value = rating;
    highlightStars(rating);
}

function highlightStars(rating) {
    document.querySelectorAll('.star-rating .fa-star').forEach((star, index) => {
        const starRating = index + 1;
        if (starRating <= rating) {
            star.classList.remove('far');
            star.classList.add('fas');
            star.style.color = '#FFD700';
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
            star.style.color = '#ccc';
        }
    });
}


function closeReviewForm() {
    const modal = document.getElementById('reviewModal');
    if (modal) {
        modal.remove();
    }
}


function submitReview(event, bookId) {
    event.preventDefault();
    
    const form = event.target;
    const rating = document.getElementById('ratingValue').value;
    const comment = document.getElementById('reviewComment').value;
    
    if (rating == 0) {
        alert('Please select a rating.');
        return;
    }
    
    const formData = new FormData();
    formData.append('book_id', bookId);
    formData.append('rating', rating);
    formData.append('comment', comment);
    
    fetch('../../includes/submit_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Review submitted successfully!');
            closeReviewForm();
            closeBookDetails();
            
            viewBookDetails(bookId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit review. Please try again.');
    });
}


function viewAllReviews(bookId) {
    window.open(`book_reviews.php?id=${bookId}`, 'Book Reviews', 'width=800,height=600');
}


function addToReadingList(bookId) {
    const formData = new FormData();
    formData.append('book_id', bookId);
    
    fetch('../../includes/add_to_reading_list.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Added to your reading list!');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add to reading list.');
    });
}
    
    
    function viewAllFines() {
        window.open('fines_history.php', 'Fines History', 'width=800,height=600');
    }
    
   
    function requestFineWaiver() {
        const reason = prompt('Please explain why you are requesting a fine waiver:', 'Financial hardship / First-time offense');
        if (reason) {
            alert('Fine waiver request submitted. Library staff will review your request within 3 business days.');
           
        }
    }
    
   
    function applyForgiveness() {
        if (confirm('Apply for 50% fine forgiveness? You must return all overdue books within 7 days.')) {
            alert('Forgiveness program applied! Return your books within 7 days to qualify.');
        }
    }
    
   
    function generateReceipt() {
        const receiptWindow = window.open('', 'Receipt', 'width=600,height=800');
        receiptWindow.document.write(`
            <html>
                <head>
                    <title>Library Fine Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .receipt-header { text-align: center; margin-bottom: 30px; }
                        .receipt-details { margin-bottom: 20px; }
                        .receipt-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .receipt-table th, .receipt-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .receipt-table th { background-color: #f5f5f5; }
                        .total { font-weight: bold; font-size: 1.2em; }
                        .footer { text-align: center; margin-top: 30px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="receipt-header">
                        <h2>Library Management System</h2>
                        <h3>Fine Payment Receipt</h3>
                        <p>Date: ${new Date().toLocaleDateString()}</p>
                        <p>Student: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                    </div>
                    
                    <div class="receipt-details">
                        <p><strong>Unpaid Balance:</strong> $<?php echo number_format($fines_data['total_unpaid'] ?? 0, 2); ?></p>
                    </div>
                    
                    <table class="receipt-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Days Overdue</th>
                                <th>Fine Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $overdue_books->data_seek(0);
                            while($book = $overdue_books->fetch_assoc()):
                                $due_date = new DateTime($book['due_date']);
                                $today = new DateTime();
                                $days_overdue = $today->diff($due_date)->days;
                                if ($today > $due_date) $days_overdue = $days_overdue * 1;
                                
                                if ($book['fine_amount'] === null) {
                                    $fine_amount = calculateFine($days_overdue);
                                } else {
                                    $fine_amount = $book['fine_amount'];
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo $days_overdue; ?> days</td>
                                <td>$<?php echo number_format($fine_amount, 2); ?></td>
                                <td><?php echo $book['fine_paid'] ? 'Paid' : 'Unpaid'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p>Thank you for paying your fines!</p>
                        <p>Bring this receipt to the library circulation desk.</p>
                        <p>Receipt ID: REC-<?php echo date('Ymd') . '-' . $_SESSION['user_id']; ?></p>
                    </div>
                </body>
            </html>
        `);
        receiptWindow.document.close();
        receiptWindow.focus();
        receiptWindow.print();
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
    
    
    function updateDashboardStats() {
        const overdueCount = <?php echo $overdue_books->num_rows; ?>;
        const overdueElement = document.querySelector('[data-stat="overdue-books"]');
        if (overdueElement) {
            overdueElement.textContent = overdueCount;
        }
    }
    
    
    document.addEventListener('DOMContentLoaded', function() {
        updateDashboardStats();
        
        
        setInterval(() => {
            const hasOverdue = <?php echo $overdue_books->num_rows > 0 ? 'true' : 'false'; ?>;
            if (hasOverdue) {
                
                fetch('../../includes/get_overdue_count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.count > 0) {
                           
                            const countElements = document.querySelectorAll('.stat-number');
                            if (countElements[1]) {
                                countElements[1].textContent = data.count;
                            }
                        }
                    });
            }
        }, 300000); 
    });
    </script>
</body>
</html>