<?php
/**
 * Circulation Analytics Dashboard
 * Comprehensive circulation statistics and analysis tool
 *
 * Provides insights on:
 * - Most active members (frequent borrowers)
 * - Most borrowed collections
 * - Collection usage statistics
 * - Comprehensive loan statistics
 */

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    define('INDEX_AUTH', '1');
    define('CIRCULATION_AUTH', 1);

    require '../../../sysconfig.inc.php';

    require LIB . 'ip_based_access.inc.php';
    do_checkIP('smc');
    do_checkIP('smc-circulation');

    require SB . 'admin/default/session.inc.php';

    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['uid'])) {
        echo json_encode(array(
            'success' => false,
            'error' => 'Session expired. Please login again.',
            'data' => array()
        ));
        exit;
    }
    $can_read = utility::havePrivilege('circulation', 'r');

    if (!$can_read) {
        echo json_encode(array(
            'success' => false,
            'error' => 'You don\'t have enough privileges to view this section',
            'data' => array()
        ));
        exit;
    }
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $search = isset($_GET['search']) ? $dbs->escape_string(trim($_GET['search'])) : '';
    $dateFrom = isset($_GET['dateFrom']) ? $dbs->escape_string(trim($_GET['dateFrom'])) : '';
    $dateTo = isset($_GET['dateTo']) ? $dbs->escape_string(trim($_GET['dateTo'])) : '';

    $data = array();
    $error = null;
    $sql = '';

    try {
        switch ($type) {
            case 'active_members':
                $sql = "SELECT
                    m.member_id,
                    m.member_name,
                    m.member_email,
                    mt.member_type_name,
                    COUNT(l.loan_id) as total_loans,
                    COUNT(DISTINCT DATE(l.loan_date)) as visit_count,
                    MAX(l.loan_date) as last_loan_date,
                    SUM(CASE WHEN l.is_return=0 AND l.is_lent=1 THEN 1 ELSE 0 END) as current_loans,
                    SUM(CASE WHEN l.return_date > l.due_date THEN 1 ELSE 0 END) as overdue_count
                FROM loan l
                LEFT JOIN member m ON l.member_id=m.member_id
                LEFT JOIN mst_member_type mt ON m.member_type_id=mt.member_type_id
                WHERE l.is_lent=1";

                if (!empty($dateFrom)) {
                    $sql .= " AND l.loan_date >= '$dateFrom'";
                }
                if (!empty($dateTo)) {
                    $sql .= " AND l.loan_date <= '$dateTo'";
                }
                if (!empty($search)) {
                    $sql .= " AND (m.member_name LIKE '%$search%' OR m.member_id LIKE '%$search%')";
                }

                $sql .= " GROUP BY m.member_id, m.member_name, m.member_email, mt.member_type_name
                ORDER BY total_loans DESC
                LIMIT $limit";
                break;

            case 'borrowed_collections':
                $sql = "SELECT
                    b.biblio_id,
                    b.title,
                    b.classification,
                    gmd.gmd_name,
                    pub.publisher_name,
                    COUNT(l.loan_id) as total_borrows,
                    COUNT(DISTINCT l.member_id) as unique_borrowers,
                    COUNT(DISTINCT i.item_code) as total_copies,
                    MAX(l.loan_date) as last_borrowed,
                    ROUND(COUNT(l.loan_id) / COUNT(DISTINCT i.item_code), 2) as circulation_ratio
                FROM loan l
                LEFT JOIN item i ON l.item_code=i.item_code
                LEFT JOIN biblio b ON i.biblio_id=b.biblio_id
                LEFT JOIN mst_gmd gmd ON b.gmd_id=gmd.gmd_id
                LEFT JOIN mst_publisher pub ON b.publisher_id=pub.publisher_id
                WHERE l.is_lent=1";

                if (!empty($dateFrom)) {
                    $sql .= " AND l.loan_date >= '$dateFrom'";
                }
                if (!empty($dateTo)) {
                    $sql .= " AND l.loan_date <= '$dateTo'";
                }
                if (!empty($search)) {
                    $sql .= " AND (b.title LIKE '%$search%' OR b.classification LIKE '%$search%')";
                }

                $sql .= " GROUP BY b.biblio_id, b.title, b.classification, gmd.gmd_name, pub.publisher_name
                ORDER BY total_borrows DESC
                LIMIT $limit";
                break;

            case 'collection_usage':
                $sql = "SELECT
                    gmd.gmd_name as category,
                    COUNT(DISTINCT b.biblio_id) as title_count,
                    COUNT(DISTINCT i.item_id) as item_count,
                    COUNT(l.loan_id) as total_circulation,
                    COUNT(DISTINCT l.member_id) as unique_borrowers,
                    ROUND(COUNT(l.loan_id) / NULLIF(COUNT(DISTINCT i.item_id), 0), 2) as circulation_ratio,
                    ROUND((COUNT(l.loan_id) / NULLIF((SELECT COUNT(*) FROM loan WHERE is_lent=1), 0)) * 100, 2) as percentage
                FROM mst_gmd gmd
                LEFT JOIN biblio b ON gmd.gmd_id=b.gmd_id
                LEFT JOIN item i ON b.biblio_id=i.biblio_id
                LEFT JOIN loan l ON i.item_code=l.item_code AND l.is_lent=1";

                if (!empty($dateFrom) || !empty($dateTo)) {
                    if (!empty($dateFrom)) {
                        $sql .= " AND l.loan_date >= '$dateFrom'";
                    }
                    if (!empty($dateTo)) {
                        $sql .= " AND l.loan_date <= '$dateTo'";
                    }
                }

                $sql .= " GROUP BY gmd.gmd_id, gmd.gmd_name
                ORDER BY total_circulation DESC";
                break;

            case 'collection_by_type':
                $sql = "SELECT
                    ct.coll_type_name as category,
                    COUNT(DISTINCT i.item_id) as item_count,
                    COUNT(l.loan_id) as total_circulation,
                    COUNT(DISTINCT l.member_id) as unique_borrowers,
                    ROUND(COUNT(l.loan_id) / NULLIF(COUNT(DISTINCT i.item_id), 0), 2) as circulation_ratio
                FROM mst_coll_type ct
                LEFT JOIN item i ON ct.coll_type_id=i.coll_type_id
                LEFT JOIN loan l ON i.item_code=l.item_code AND l.is_lent=1";

                if (!empty($dateFrom) || !empty($dateTo)) {
                    if (!empty($dateFrom)) {
                        $sql .= " AND l.loan_date >= '$dateFrom'";
                    }
                    if (!empty($dateTo)) {
                        $sql .= " AND l.loan_date <= '$dateTo'";
                    }
                }

                $sql .= " GROUP BY ct.coll_type_id, ct.coll_type_name
                ORDER BY total_circulation DESC";
                break;

            case 'loan_statistics':
                $sql = "SELECT
                    DATE_FORMAT(l.loan_date, '%Y-%m') as period,
                    COUNT(l.loan_id) as total_loans,
                    COUNT(DISTINCT l.member_id) as active_members,
                    COUNT(DISTINCT i.biblio_id) as unique_titles,
                    SUM(CASE WHEN l.is_return=1 THEN 1 ELSE 0 END) as returned,
                    SUM(CASE WHEN l.is_return=0 THEN 1 ELSE 0 END) as on_loan,
                    SUM(CASE WHEN l.return_date > l.due_date THEN 1 ELSE 0 END) as overdue,
                    ROUND(AVG(DATEDIFF(IFNULL(l.return_date, CURDATE()), l.loan_date)), 1) as avg_loan_days
                FROM loan l
                LEFT JOIN item i ON l.item_code=i.item_code
                WHERE l.is_lent=1";

                if (!empty($dateFrom)) {
                    $sql .= " AND l.loan_date >= '$dateFrom'";
                }
                if (!empty($dateTo)) {
                    $sql .= " AND l.loan_date <= '$dateTo'";
                }

                $sql .= " GROUP BY DATE_FORMAT(l.loan_date, '%Y-%m')
                ORDER BY period DESC
                LIMIT 12";
                break;

            case 'overdue_analysis':
                $sql = "SELECT
                    m.member_id,
                    m.member_name,
                    b.title,
                    i.item_code,
                    l.loan_date,
                    l.due_date,
                    DATEDIFF(CURDATE(), l.due_date) as days_overdue,
                    IFNULL(SUM(f.debet - f.credit), 0) as total_fines
                FROM loan l
                LEFT JOIN member m ON l.member_id=m.member_id
                LEFT JOIN item i ON l.item_code=i.item_code
                LEFT JOIN biblio b ON i.biblio_id=b.biblio_id
                LEFT JOIN fines f ON m.member_id=f.member_id
                WHERE l.is_lent=1
                AND l.is_return=0
                AND l.due_date < CURDATE()";

                if (!empty($search)) {
                    $sql .= " AND (m.member_name LIKE '%$search%' OR b.title LIKE '%$search%' OR i.item_code LIKE '%$search%')";
                }

                $sql .= " GROUP BY l.loan_id, m.member_id, m.member_name, b.title, i.item_code, l.loan_date, l.due_date
                ORDER BY days_overdue DESC
                LIMIT $limit";
                break;

            default:
                throw new Exception('Invalid request type');
        }

        $result = $dbs->query($sql);

        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
            }
        } else {
            $error = $dbs->error;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!empty($data)) {
        $utf8Normalizer = function (&$value) {
            if (!is_string($value)) {
                return;
            }
            if (function_exists('mb_convert_encoding')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            } else {
                $value = utf8_encode(utf8_decode($value));
            }
        };
        array_walk_recursive($data, $utf8Normalizer);
    }

    $response = array(
        'success' => $result !== false && $error === null,
        'data' => $data,
        'count' => count($data),
        'error' => $error,
        'sql' => $sql // For debugging - remove in production
    );

    $jsonOptions = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonOptions |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $encoded = json_encode($response, $jsonOptions);

    if ($encoded === false) {
        $fallback = array(
            'success' => false,
            'error' => 'JSON encode failed: ' . json_last_error_msg(),
            'data' => array(),
            'count' => 0,
            'sql' => $sql
        );
        $encoded = json_encode($fallback, $jsonOptions);
    }

    echo $encoded;
    exit;
}

define('INDEX_AUTH', '1');
define('CIRCULATION_AUTH', 1);

require '../../../sysconfig.inc.php';

require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');

require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('circulation', 'r');

if (!$can_read) {
    die('<div class="errorBox">' . __('You don\'t have enough privileges to view this section') . '</div>');
}

require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';

$page_title = 'Circulation Analytics';

function getSummaryStats($dbs)
{
    $stats = array();

    $q = $dbs->query("SELECT COUNT(*) as total FROM loan WHERE is_lent=1");
    $d = $q->fetch_assoc();
    $stats['total_loans'] = $d['total'];

    $q = $dbs->query("SELECT COUNT(*) as total FROM loan WHERE is_lent=1 AND is_return=0");
    $d = $q->fetch_assoc();
    $stats['active_loans'] = $d['total'];

    $q = $dbs->query("SELECT COUNT(*) as total FROM member WHERE is_pending=0");
    $d = $q->fetch_assoc();
    $stats['total_members'] = $d['total'];

    $q = $dbs->query("SELECT COUNT(DISTINCT member_id) as total FROM loan WHERE is_lent=1 AND loan_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $d = $q->fetch_assoc();
    $stats['active_members'] = $d['total'];

    $q = $dbs->query("SELECT COUNT(*) as total FROM loan WHERE is_lent=1 AND is_return=0 AND due_date < CURDATE()");
    $d = $q->fetch_assoc();
    $stats['overdue_items'] = $d['total'];

    $q = $dbs->query("SELECT COUNT(*) as total FROM loan WHERE is_lent=1 AND MONTH(loan_date)=MONTH(CURDATE()) AND YEAR(loan_date)=YEAR(CURDATE())");
    $d = $q->fetch_assoc();
    $stats['monthly_loans'] = $d['total'];

    $q1 = $dbs->query("SELECT COUNT(DISTINCT i.item_code) as borrowed FROM loan l LEFT JOIN item i ON l.item_code=i.item_code WHERE l.is_lent=1");
    $d1 = $q1->fetch_assoc();
    $q2 = $dbs->query("SELECT COUNT(*) as total FROM item");
    $d2 = $q2->fetch_assoc();
    $stats['collection_usage'] = $d2['total'] > 0 ? round(($d1['borrowed'] / $d2['total']) * 100, 1) : 0;

    return $stats;
}

$summary = getSummaryStats($dbs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #3b82f6;
            --primary-blue-dark: #2563eb;
            --bg-light: #f8f9fc;
            --bg-white: #ffffff;
            --text-dark: #27345d;
            --text-medium: #414d7a;
            --text-light: #6b7280;
            --border-light: #e3e7f3;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --danger-red: #ef4444;
            --purple: #8b5cf6;
            --teal: #14b8a6;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            margin: 0;
            padding: 0;
        }

        .analytics-hero {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 80%);
            border-radius: 20px;
            padding: 22px 28px;
            margin: 16px 20px 0 20px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
        }

        .analytics-hero__content {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }

        .analytics-hero__icon {
            width: 54px;
            height: 54px;
            background: rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .analytics-hero__text h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .analytics-hero__text p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.4;
        }

        .analytics-container {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .stats-dashboard {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .stat-card__content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-card__icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-card__icon--primary {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-card__icon--success {
            background: #d1fae5;
            color: #10b981;
        }

        .stat-card__icon--warning {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-card__icon--danger {
            background: #fee2e2;
            color: #ef4444;
        }

        .stat-card__icon--purple {
            background: #e0e7ff;
            color: #8b5cf6;
        }

        .stat-card__icon--teal {
            background: #ccfbf1;
            color: #14b8a6;
        }

        .stat-card__text {
            flex: 1;
        }

        .stat-card__label {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
        }

        .stat-card__value {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .analytics-sections-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        @media (max-width: 1200px) {
            .analytics-sections-grid {
                grid-template-columns: 1fr;
            }
        }

        .analytics-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 0;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .analytics-section:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .analytics-section__header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .analytics-section__title-wrap {
            flex: 1;
        }

        .analytics-section__title {
            margin: 0;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .analytics-section__title i {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .analytics-section__subtitle {
            font-size: 13px;
            color: var(--text-light);
            margin: 6px 0 0 48px;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.active {
            display: block;
        }

        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        .modal.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -45%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 18px;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 24px;
            max-height: calc(90vh - 180px);
            overflow-y: auto;
        }

        .modal-content {
            background-color: white;
        }
        .modal-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        .modal-filters input,
        .modal-filters select {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        .modal-filters button {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .modal-filters button:hover {
            background: #5568d3;
        }
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }
        .data-table thead th {
            background: #f9fafb;
            color: #374151;
            font-weight: 700;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid #e5e7eb;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .data-table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table tbody tr:hover {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .loading {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        .loading i {
            font-size: 3rem;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="analytics-hero">
    <div class="analytics-hero__content">
        <div class="analytics-hero__icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="analytics-hero__text">
            <h1>Analisis Sirkulasi</h1>
            <p>Statistik dan analisis sirkulasi perpustakaan yang komprehensif untuk manajemen yang lebih baik</p>
        </div>
    </div>
</div>

<div class="analytics-container">
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Total Peminjaman</div>
                    <div class="stat-card__value"><?php echo number_format($summary['total_loans']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Peminjaman Aktif</div>
                    <div class="stat-card__value"><?php echo number_format($summary['active_loans']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--teal">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Anggota Aktif</div>
                    <div class="stat-card__value"><?php echo number_format($summary['active_members']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Keterlambatan</div>
                    <div class="stat-card__value"><?php echo number_format($summary['overdue_items']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--purple">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Bulan Ini</div>
                    <div class="stat-card__value"><?php echo number_format($summary['monthly_loans']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--warning">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Tingkat Pemakaian</div>
                    <div class="stat-card__value"><?php echo $summary['collection_usage']; ?>%</div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--success">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Total Anggota</div>
                    <div class="stat-card__value"><?php echo number_format($summary['total_members']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--primary">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Peminjaman Bulanan</div>
                    <div class="stat-card__value"><?php echo number_format($summary['monthly_loans']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="analytics-sections-grid">
        <div class="analytics-section">
            <div class="analytics-section__header">
                <div class="analytics-section__title-wrap">
                    <h3 class="analytics-section__title">
                        <i class="fas fa-crown"></i>
                        Anggota Paling Aktif
                    </h3>
                    <p class="analytics-section__subtitle">Anggota dengan frekuensi peminjaman tertinggi</p>
                </div>
                <button class="btn-view" onclick="openModal('active_members')">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </div>
        </div>

        <div class="analytics-section">
            <div class="analytics-section__header">
                <div class="analytics-section__title-wrap">
                    <h3 class="analytics-section__title">
                        <i class="fas fa-fire"></i>
                        Koleksi Terpopuler
                    </h3>
                    <p class="analytics-section__subtitle">Koleksi dengan sirkulasi tertinggi</p>
                </div>
                <button class="btn-view" onclick="openModal('borrowed_collections')">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </div>
        </div>

        <div class="analytics-section">
            <div class="analytics-section__header">
                <div class="analytics-section__title-wrap">
                    <h3 class="analytics-section__title">
                        <i class="fas fa-chart-pie"></i>
                        Statistik Keterpakaian Koleksi
                    </h3>
                    <p class="analytics-section__subtitle">Analisis keterpakaian berdasarkan format dan jenis</p>
                </div>
                <button class="btn-view" onclick="openModal('collection_usage')">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </div>
        </div>

        <div class="analytics-section">
            <div class="analytics-section__header">
                <div class="analytics-section__title-wrap">
                    <h3 class="analytics-section__title">
                        <i class="fas fa-chart-bar"></i>
                        Statistik Peminjaman
                    </h3>
                    <p class="analytics-section__subtitle">Tren dan pola peminjaman berdasarkan waktu</p>
                </div>
                <button class="btn-view" onclick="openModal('loan_statistics')">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </div>
        </div>

        <div class="analytics-section">
            <div class="analytics-section__header">
                <div class="analytics-section__title-wrap">
                    <h3 class="analytics-section__title">
                        <i class="fas fa-exclamation-circle"></i>
                        Analisis Keterlambatan
                    </h3>
                    <p class="analytics-section__subtitle">Peminjaman terlambat dan manajemen denda</p>
                </div>
                <button class="btn-view" onclick="openModal('overdue_analysis')">
                    <i class="fas fa-eye"></i> Lihat
                </button>
            </div>
        </div>

    </div>

</div>
<div id="modalOverlay" class="modal-overlay" onclick="closeModal()"></div>
<div id="dataModal" class="modal">
    <div class="modal-header">
        <h2 id="modalTitle"><i class="fas fa-spinner fa-spin"></i> Loading...</h2>
        <button class="modal-close" onclick="closeModal()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="modal-body" id="modalBody">
        <div class="loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading data...</p>
        </div>
    </div>
</div>

<script>
let currentModalType = '';

function openModal(type) {
    currentModalType = type;
    const modal = document.getElementById('dataModal');
    const modalOverlay = document.getElementById('modalOverlay');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    const titles = {
        'active_members': '<i class="fas fa-crown"></i> Anggota Paling Aktif - Detail',
        'borrowed_collections': '<i class="fas fa-fire"></i> Koleksi Terpopuler - Detail',
        'collection_usage': '<i class="fas fa-chart-pie"></i> Statistik Keterpakaian Koleksi - Per Format',
        'loan_statistics': '<i class="fas fa-chart-bar"></i> Statistik Peminjaman - Tren Bulanan',
        'overdue_analysis': '<i class="fas fa-exclamation-circle"></i> Analisis Keterlambatan - Status Saat Ini'
    };

    modalTitle.innerHTML = titles[type] || '<i class="fas fa-spinner fa-spin"></i> Loading...';

    modal.classList.add('active');
    modalOverlay.classList.add('active');

    loadModalData(type);
}

function closeModal() {
    const modal = document.getElementById('dataModal');
    const modalOverlay = document.getElementById('modalOverlay');

    modal.classList.remove('active');
    modalOverlay.classList.remove('active');
    currentModalType = '';
}

function loadModalData(type, filters = {}) {
    const modalBody = document.getElementById('modalBody');

    modalBody.innerHTML = `
        <div class="loading">
            <i class="fas fa-spinner"></i>
            <p>Loading data...</p>
        </div>
    `;

    const params = new URLSearchParams({
        ajax: '1',
        type: type,
        limit: filters.limit || 50,
        search: filters.search || '',
        dateFrom: filters.dateFrom || '',
        dateTo: filters.dateTo || ''
    });

    fetch('<?php echo MWB; ?>circulation/circulation_analytics.php?' + params.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(text => {
            try {
                const result = JSON.parse(text);
                if (result.success) {
                    renderModalData(type, result.data, filters);
                } else {
                    modalBody.innerHTML = `
                        <div class="error-message">
                            <strong>Error:</strong> ${result.error || 'Failed to load data'}
                        </div>
                    `;
                }
            } catch (e) {
                modalBody.innerHTML = `
                    <div class="error-message">
                        <strong>JSON Parse Error:</strong><br>
                        ${e.message}<br><br>
                        <strong>Response:</strong><br>
                        <pre style="max-height: 300px; overflow: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;">${text.substring(0, 1000)}</pre>
                    </div>
                `;
            }
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="error-message">
                    <strong>Fetch Error:</strong> ${error.message}
                </div>
            `;
        });
}

function renderModalData(type, data, filters) {
    const modalBody = document.getElementById('modalBody');

    if (!data || data.length === 0) {
        modalBody.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No data found</p>
            </div>
        `;
        return;
    }

    let html = '';

    html += renderFilters(type, filters);

    switch (type) {
        case 'active_members':
            html += renderActiveMembersTable(data);
            break;
        case 'borrowed_collections':
            html += renderBorrowedCollectionsTable(data);
            break;
        case 'collection_usage':
        case 'collection_by_type':
            html += renderCollectionUsageTable(data);
            break;
        case 'loan_statistics':
            html += renderLoanStatisticsTable(data);
            break;
        case 'overdue_analysis':
            html += renderOverdueAnalysisTable(data);
            break;
    }

    modalBody.innerHTML = html;
}

function renderFilters(type, currentFilters) {
    let html = '<div class="modal-filters">';

    if (['active_members', 'borrowed_collections', 'overdue_analysis'].includes(type)) {
        html += `
            <input type="text"
                   id="filterSearch"
                   placeholder="Search..."
                   value="${currentFilters.search || ''}"
                   style="flex: 1; min-width: 200px;">
        `;
    }

    if (['active_members', 'borrowed_collections', 'collection_usage', 'loan_statistics'].includes(type)) {
        html += `
            <input type="date"
                   id="filterDateFrom"
                   value="${currentFilters.dateFrom || ''}"
                   placeholder="From Date">
            <input type="date"
                   id="filterDateTo"
                   value="${currentFilters.dateTo || ''}"
                   placeholder="To Date">
        `;
    }

    html += `
        <select id="filterLimit">
            <option value="20" ${currentFilters.limit == 20 ? 'selected' : ''}>Top 20</option>
            <option value="50" ${currentFilters.limit == 50 ? 'selected' : ''}>Top 50</option>
            <option value="100" ${currentFilters.limit == 100 ? 'selected' : ''}>Top 100</option>
        </select>
    `;

    html += `
        <button onclick="applyFilters()">Apply Filters</button>
        <button onclick="resetFilters()" style="background: #6b7280;">Reset</button>
    `;

    html += '</div>';
    return html;
}

function applyFilters() {
    const filters = {
        search: document.getElementById('filterSearch')?.value || '',
        dateFrom: document.getElementById('filterDateFrom')?.value || '',
        dateTo: document.getElementById('filterDateTo')?.value || '',
        limit: document.getElementById('filterLimit')?.value || 50
    };

    loadModalData(currentModalType, filters);
}

function resetFilters() {
    loadModalData(currentModalType, {});
}

function renderActiveMembersTable(data) {
    let html = '<table class="data-table"><thead><tr>';
    html += '<th>Rank</th>';
    html += '<th>Member ID</th>';
    html += '<th>Member Name</th>';
    html += '<th>Type</th>';
    html += '<th>Total Loans</th>';
    html += '<th>Visits</th>';
    html += '<th>Current Loans</th>';
    html += '<th>Overdue</th>';
    html += '<th>Last Activity</th>';
    html += '</tr></thead><tbody>';

    data.forEach((row, index) => {
        html += '<tr>';
        html += `<td><strong>#${index + 1}</strong></td>`;
        html += `<td>${row.member_id || '-'}</td>`;
        html += `<td>${row.member_name || '-'}</td>`;
        html += `<td>${row.member_type_name || '-'}</td>`;
        html += `<td><span class="badge badge-info">${row.total_loans}</span></td>`;
        html += `<td>${row.visit_count}</td>`;
        html += `<td>${row.current_loans}</td>`;
        html += `<td>${row.overdue_count > 0 ? '<span class="badge badge-danger">' + row.overdue_count + '</span>' : '-'}</td>`;
        html += `<td>${row.last_loan_date || '-'}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

function renderBorrowedCollectionsTable(data) {
    let html = '<table class="data-table"><thead><tr>';
    html += '<th>Rank</th>';
    html += '<th>Title</th>';
    html += '<th>Classification</th>';
    html += '<th>Format</th>';
    html += '<th>Total Borrows</th>';
    html += '<th>Unique Borrowers</th>';
    html += '<th>Copies</th>';
    html += '<th>Circulation Ratio</th>';
    html += '<th>Last Borrowed</th>';
    html += '</tr></thead><tbody>';

    data.forEach((row, index) => {
        html += '<tr>';
        html += `<td><strong>#${index + 1}</strong></td>`;
        html += `<td>${row.title || '-'}</td>`;
        html += `<td>${row.classification || '-'}</td>`;
        html += `<td>${row.gmd_name || '-'}</td>`;
        html += `<td><span class="badge badge-success">${row.total_borrows}</span></td>`;
        html += `<td>${row.unique_borrowers}</td>`;
        html += `<td>${row.total_copies}</td>`;
        html += `<td>${row.circulation_ratio || '0'}</td>`;
        html += `<td>${row.last_borrowed || '-'}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

function renderCollectionUsageTable(data) {
    let html = '<table class="data-table"><thead><tr>';
    html += '<th>Category</th>';
    html += '<th>Titles</th>';
    html += '<th>Items</th>';
    html += '<th>Total Circulation</th>';
    html += '<th>Unique Borrowers</th>';
    html += '<th>Circulation Ratio</th>';
    html += '<th>Percentage</th>';
    html += '</tr></thead><tbody>';

    data.forEach((row) => {
        html += '<tr>';
        html += `<td><strong>${row.category || '-'}</strong></td>`;
        html += `<td>${row.title_count || 0}</td>`;
        html += `<td>${row.item_count || 0}</td>`;
        html += `<td><span class="badge badge-info">${row.total_circulation || 0}</span></td>`;
        html += `<td>${row.unique_borrowers || 0}</td>`;
        html += `<td>${row.circulation_ratio || '0.00'}</td>`;
        html += `<td>${row.percentage || '0.00'}%</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

function renderLoanStatisticsTable(data) {
    let html = '<table class="data-table"><thead><tr>';
    html += '<th>Period</th>';
    html += '<th>Total Loans</th>';
    html += '<th>Active Members</th>';
    html += '<th>Unique Titles</th>';
    html += '<th>Returned</th>';
    html += '<th>On Loan</th>';
    html += '<th>Overdue</th>';
    html += '<th>Avg Loan Days</th>';
    html += '</tr></thead><tbody>';

    data.forEach((row) => {
        html += '<tr>';
        html += `<td><strong>${row.period || '-'}</strong></td>`;
        html += `<td><span class="badge badge-info">${row.total_loans || 0}</span></td>`;
        html += `<td>${row.active_members || 0}</td>`;
        html += `<td>${row.unique_titles || 0}</td>`;
        html += `<td><span class="badge badge-success">${row.returned || 0}</span></td>`;
        html += `<td><span class="badge badge-warning">${row.on_loan || 0}</span></td>`;
        html += `<td>${row.overdue > 0 ? '<span class="badge badge-danger">' + row.overdue + '</span>' : '0'}</td>`;
        html += `<td>${row.avg_loan_days || '0.0'} days</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

function renderOverdueAnalysisTable(data) {
    let html = '<table class="data-table"><thead><tr>';
    html += '<th>Member ID</th>';
    html += '<th>Member Name</th>';
    html += '<th>Title</th>';
    html += '<th>Item Code</th>';
    html += '<th>Loan Date</th>';
    html += '<th>Due Date</th>';
    html += '<th>Days Overdue</th>';
    html += '<th>Fines</th>';
    html += '</tr></thead><tbody>';

    data.forEach((row) => {
        html += '<tr>';
        html += `<td>${row.member_id || '-'}</td>`;
        html += `<td>${row.member_name || '-'}</td>`;
        html += `<td>${row.title || '-'}</td>`;
        html += `<td>${row.item_code || '-'}</td>`;
        html += `<td>${row.loan_date || '-'}</td>`;
        html += `<td>${row.due_date || '-'}</td>`;
        html += `<td><span class="badge badge-danger">${row.days_overdue || 0} days</span></td>`;
        html += `<td>${parseFloat(row.total_fines || 0).toLocaleString()}</td>`;
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>

</body>
</html>
