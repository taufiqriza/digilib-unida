<?php
/**
 * Bibliography Class - Classification & Category Management
 *
 * A comprehensive tool for library staff to analyze and manage
 * bibliographic collections by classification, category, and type
 *
 * @package   SLiMS
 * @author    Claude AI Assistant
 * @version   1.0
 */

// key to authentication
define('INDEX_AUTH', '1');
define('BIBLIOGRAPHY_AUTH', 1);

// main system configuration
require '../../../sysconfig.inc.php';

// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');

// start the session
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
    die('<div class="errorBox">' . __('You don\'t have enough privileges to view this section') . '</div>');
}

require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO . 'simbio_DB/simbio_dbop.inc.php';

$page_title = 'Bibliography Classification & Category';

// Get statistics
function getStatistics($dbs)
{
    $stats = array();

    // Total bibliographies
    $total_q = $dbs->query("SELECT COUNT(DISTINCT biblio_id) as total FROM biblio WHERE opac_hide=0");
    $total_d = $total_q->fetch_assoc();
    $stats['total_biblio'] = $total_d['total'];

    // Total items
    $items_q = $dbs->query("SELECT COUNT(item_id) as total FROM item");
    $items_d = $items_q->fetch_assoc();
    $stats['total_items'] = $items_d['total'];

    // By GMD (General Material Designation)
    $gmd_q = $dbs->query("SELECT
        g.gmd_id,
        g.gmd_name,
        g.gmd_code,
        COUNT(DISTINCT b.biblio_id) as count
        FROM mst_gmd g
        LEFT JOIN biblio b ON g.gmd_id = b.gmd_id AND b.opac_hide=0
        GROUP BY g.gmd_id, g.gmd_name, g.gmd_code
        ORDER BY count DESC");
    $stats['by_gmd'] = array();
    while ($gmd_d = $gmd_q->fetch_assoc()) {
        $stats['by_gmd'][] = $gmd_d;
    }

    // By Collection Type
    $coll_q = $dbs->query("SELECT
        ct.coll_type_id,
        ct.coll_type_name,
        COUNT(DISTINCT i.biblio_id) as count,
        COUNT(i.item_id) as item_count
        FROM mst_coll_type ct
        LEFT JOIN item i ON ct.coll_type_id = i.coll_type_id
        LEFT JOIN biblio b ON i.biblio_id = b.biblio_id AND b.opac_hide=0
        GROUP BY ct.coll_type_id, ct.coll_type_name
        ORDER BY count DESC");
    $stats['by_collection'] = array();
    while ($coll_d = $coll_q->fetch_assoc()) {
        $stats['by_collection'][] = $coll_d;
    }

    // By Classification (Top 10)
    $class_q = $dbs->query("SELECT
        classification,
        COUNT(DISTINCT biblio_id) as count
        FROM biblio
        WHERE classification IS NOT NULL AND classification != '' AND opac_hide=0
        GROUP BY classification
        ORDER BY count DESC
        LIMIT 20");
    $stats['by_classification'] = array();
    while ($class_d = $class_q->fetch_assoc()) {
        $stats['by_classification'][] = $class_d;
    }

    // By Language (Top 10)
    $lang_q = $dbs->query("SELECT
        l.language_id,
        l.language_name,
        COUNT(DISTINCT b.biblio_id) as count
        FROM mst_language l
        LEFT JOIN biblio b ON l.language_id = b.language_id AND b.opac_hide=0
        GROUP BY l.language_id, l.language_name
        HAVING count > 0
        ORDER BY count DESC
        LIMIT 10");
    $stats['by_language'] = array();
    while ($lang_d = $lang_q->fetch_assoc()) {
        $stats['by_language'][] = $lang_d;
    }

    return $stats;
}

$statistics = getStatistics($dbs);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Color System */
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

        /* Hero Section */
        .biblio-class-hero {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
            border-radius: var(--radius-xl);
            padding: 32px 40px;
            margin: 20px 20px 0 20px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .biblio-class-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .biblio-class-hero__content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .biblio-class-hero__icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .biblio-class-hero__text h1 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .biblio-class-hero__text p {
            margin: 0;
            font-size: 15px;
            opacity: 0.95;
            line-height: 1.5;
        }

        /* Container */
        .biblio-class-container {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Statistics Dashboard - Home.php style */
        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
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

        .stat-card__icon--purple {
            background: #e0e7ff;
            color: #8b5cf6;
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

        /* Category Grid */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .category-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 0;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .category-card__header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px 24px;
            border-bottom: 2px solid var(--border-light);
        }

        .category-card__title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .category-card__title i {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .category-card__body {
            padding: 16px 24px 24px;
            max-height: 400px;
            overflow-y: auto;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            margin-bottom: 8px;
            background: var(--bg-light);
            border-radius: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .category-item:hover {
            background: white;
            border-color: var(--primary-blue);
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .category-item__name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
            flex: 1;
        }

        .category-item__count {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-blue);
            background: rgba(59, 130, 246, 0.1);
            padding: 6px 14px;
            border-radius: 20px;
            min-width: 45px;
            text-align: center;
        }

        .category-item__badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            margin-left: 8px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-green);
        }

        /* Data Grid Section */
        .data-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            margin-top: 24px;
        }

        .data-section__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-light);
        }

        .data-section__title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .data-section__title i {
            color: var(--primary-blue);
        }

        .back-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Table Styling */
        .biblio-class-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .biblio-class-table thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 14px 18px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            text-align: left;
            border: none;
        }

        .biblio-class-table thead th:first-child {
            border-radius: 10px 0 0 10px;
        }

        .biblio-class-table thead th:last-child {
            border-radius: 0 10px 10px 0;
        }

        .biblio-class-table tbody tr {
            background: white;
            transition: all 0.2s ease;
        }

        .biblio-class-table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: var(--shadow-md);
        }

        .biblio-class-table tbody td {
            padding: 16px 18px;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            color: var(--text-medium);
        }

        .biblio-class-table tbody td:first-child {
            border-left: 1px solid var(--border-light);
            border-radius: 10px 0 0 10px;
        }

        .biblio-class-table tbody td:last-child {
            border-right: 1px solid var(--border-light);
            border-radius: 0 10px 10px 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 64px;
            color: rgba(59, 130, 246, 0.3);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: var(--text-dark);
            margin: 0 0 12px 0;
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
        }

        /* Modal Styles */
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

        .modal-container {
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

        .modal-container.active {
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

        .modal-header h3 {
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
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 24px;
            max-height: calc(90vh - 180px);
            overflow-y: auto;
        }

        .modal-search {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }

        .modal-search input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .modal-search input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .modal-search button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-search button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }

        .modal-data-grid {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .modal-data-grid table {
            width: 100%;
            border-collapse: collapse;
        }

        .modal-data-grid th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-data-grid td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .modal-data-grid tr:hover {
            background: #f8fafc;
        }

        .modal-loading {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .modal-loading i {
            font-size: 48px;
            color: #3b82f6;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-dashboard {
                grid-template-columns: repeat(2, 1fr);
            }

            .biblio-class-hero {
                padding: 24px;
                flex-direction: column;
                text-align: center;
            }

            .biblio-class-hero__content {
                flex-direction: column;
            }

            .stats-dashboard {
                grid-template-columns: 1fr;
            }

            .category-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Scrollbar Styling */
        .category-card__body::-webkit-scrollbar {
            width: 6px;
        }

        .category-card__body::-webkit-scrollbar-track {
            background: var(--bg-light);
            border-radius: 3px;
        }

        .category-card__body::-webkit-scrollbar-thumb {
            background: var(--primary-blue);
            border-radius: 3px;
        }

        .category-card__body::-webkit-scrollbar-thumb:hover {
            background: var(--primary-blue-dark);
        }
    </style>
</head>
<body>

<!-- Hero Section -->
<div class="biblio-class-hero">
    <div class="biblio-class-hero__content">
        <div class="biblio-class-hero__icon">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="biblio-class-hero__text">
            <h1><?php echo __('Bibliography Classification & Category'); ?></h1>
            <p><?php echo __('Comprehensive collection analysis by classification, category, and material type'); ?></p>
        </div>
    </div>
</div>

<div class="biblio-class-container">
    <!-- Statistics Dashboard -->
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label"><?php echo __('Total of Collections'); ?></div>
                    <div class="stat-card__value"><?php echo number_format($statistics['total_biblio']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--success">
                    <i class="fas fa-barcode"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label"><?php echo __('Total of Items'); ?></div>
                    <div class="stat-card__value"><?php echo number_format($statistics['total_items']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--warning">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label"><?php echo __('Collection Types'); ?></div>
                    <div class="stat-card__value"><?php echo count($statistics['by_collection']); ?></div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--purple">
                    <i class="fas fa-shapes"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label"><?php echo __('Material Types'); ?></div>
                    <div class="stat-card__value"><?php echo count($statistics['by_gmd']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Grid -->
    <div class="category-grid">
        <!-- General Material Designation -->
        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-cube"></i>
                    <?php echo __('By Material Type (GMD)'); ?>
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_gmd']) > 0): ?>
                    <?php foreach ($statistics['by_gmd'] as $gmd): ?>
                        <?php if ($gmd['count'] > 0): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('gmd', <?php echo $gmd['gmd_id']; ?>, '<?php echo addslashes($gmd['gmd_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($gmd['gmd_name']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($gmd['count']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p><?php echo __('No data available'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Collection Type -->
        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-folder-open"></i>
                    <?php echo __('By Collection Type'); ?>
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_collection']) > 0): ?>
                    <?php foreach ($statistics['by_collection'] as $coll): ?>
                        <?php if ($coll['count'] > 0): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('collection', <?php echo $coll['coll_type_id']; ?>, '<?php echo addslashes($coll['coll_type_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($coll['coll_type_name']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($coll['count']); ?></span>
                            <span class="category-item__badge"><?php echo number_format($coll['item_count']); ?> items</span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p><?php echo __('No data available'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Classification -->
        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-sitemap"></i>
                    <?php echo __('By Classification'); ?>
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_classification']) > 0): ?>
                    <?php foreach ($statistics['by_classification'] as $class): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('classification', 0, '<?php echo addslashes($class['classification']); ?>', '<?php echo addslashes($class['classification']); ?>')">
                            <span class="category-item__name">
                                <i class="fas fa-hashtag" style="font-size: 11px; opacity: 0.5; margin-right: 6px;"></i>
                                <?php echo htmlspecialchars($class['classification']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($class['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p><?php echo __('No data available'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Language -->
        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-language"></i>
                    <?php echo __('By Language'); ?>
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_language']) > 0): ?>
                    <?php foreach ($statistics['by_language'] as $lang): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('language', <?php echo $lang['language_id']; ?>, '<?php echo addslashes($lang['language_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($lang['language_name']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($lang['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p><?php echo __('No data available'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Overlay -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>

<!-- Modal Container -->
<div class="modal-container" id="modalContainer">
    <div class="modal-header">
        <h3 id="modalTitle">
            <i class="fas fa-filter"></i>
            <span id="modalTitleText">Filtered Data</span>
        </h3>
        <button class="modal-close" onclick="closeModal()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="modal-body">
        <div class="modal-search">
            <input type="text" id="modalSearch" placeholder="<?php echo __('Search title, author, or ISBN...'); ?>">
            <button onclick="performSearch()">
                <i class="fas fa-search"></i>
                <?php echo __('Search'); ?>
            </button>
        </div>
        <div class="modal-data-grid" id="modalDataGrid">
            <div class="modal-loading">
                <i class="fas fa-spinner"></i>
                <p><?php echo __('Loading data...'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
let currentFilter = {
    type: '',
    value: 0,
    label: '',
    classCode: ''
};

function openModal(type, value, label, classCode = '') {
    currentFilter = { type, value, label, classCode };

    // Update modal title
    let titleText = '';
    switch(type) {
        case 'gmd':
            titleText = '<?php echo __('Material Type'); ?>: ' + label;
            break;
        case 'collection':
            titleText = '<?php echo __('Collection Type'); ?>: ' + label;
            break;
        case 'classification':
            titleText = '<?php echo __('Classification'); ?>: ' + label;
            break;
        case 'language':
            titleText = '<?php echo __('Language'); ?>: ' + label;
            break;
    }
    document.getElementById('modalTitleText').textContent = titleText;

    // Show modal
    document.getElementById('modalOverlay').classList.add('active');
    document.getElementById('modalContainer').classList.add('active');

    // Load data
    loadModalData();
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
    document.getElementById('modalContainer').classList.remove('active');
    document.getElementById('modalSearch').value = '';
}

function loadModalData(searchQuery = '') {
    const dataGrid = document.getElementById('modalDataGrid');
    dataGrid.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner"></i><p><?php echo __('Loading data...'); ?></p></div>';

    // Build query parameters
    let params = new URLSearchParams();
    params.append('ajax', '1');
    params.append('type', currentFilter.type);
    params.append('value', currentFilter.value);
    if (currentFilter.classCode) {
        params.append('class', currentFilter.classCode);
    }
    if (searchQuery) {
        params.append('search', searchQuery);
    }

    console.log('Fetching with params:', params.toString());

    // Fetch data from separate AJAX handler file
    fetch('bibliography_class_ajax.php?' + params.toString())
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);

                if (!data.success) {
                    throw new Error(data.error || 'Unknown error');
                }

                if (data.data && data.data.length > 0) {
                    let html = '<table><thead><tr>';
                    html += '<th><?php echo __('Title'); ?></th>';
                    html += '<th><?php echo __('Author'); ?></th>';
                    html += '<th><?php echo __('Classification'); ?></th>';
                    html += '<th><?php echo __('Year'); ?></th>';
                    html += '<th><?php echo __('ISBN/ISSN'); ?></th>';
                    html += '<th><?php echo __('Copies'); ?></th>';
                    html += '<th><?php echo __('Action'); ?></th>';
                    html += '</tr></thead><tbody>';

                    data.data.forEach(row => {
                        html += '<tr>';
                        html += '<td><strong>' + escapeHtml(row.title) + '</strong></td>';
                        html += '<td>' + escapeHtml(row.author) + '</td>';
                        html += '<td>' + escapeHtml(row.classification) + '</td>';
                        html += '<td>' + escapeHtml(row.publish_year) + '</td>';
                        html += '<td>' + escapeHtml(row.isbn_issn) + '</td>';
                        html += '<td><span style="background:#dbeafe;color:#3b82f6;padding:4px 8px;border-radius:6px;font-weight:600;font-size:12px;">' + row.copies + '</span></td>';
                        html += '<td><a href="index.php?action=detail&id=' + row.biblio_id + '" target="_blank" style="color:#3b82f6;text-decoration:none;"><i class="fas fa-eye"></i> <?php echo __('View'); ?></a></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';

                    // Add debug info
                    if (data.sql) {
                        html += '<div style="margin-top:20px;padding:10px;background:#f8fafc;border-radius:8px;font-size:11px;color:#64748b;"><strong>Debug SQL:</strong><br><code>' + escapeHtml(data.sql) + '</code></div>';
                    }

                    dataGrid.innerHTML = html;
                } else {
                    let debugInfo = '';
                    if (data.sql) {
                        debugInfo = '<br><small style="color:#64748b;">SQL: ' + escapeHtml(data.sql) + '</small>';
                    }
                    dataGrid.innerHTML = '<div class="empty-state"><i class="fas fa-search"></i><h3><?php echo __('No Results Found'); ?></h3><p><?php echo __('No bibliographies match the selected filter.'); ?></p>' + debugInfo + '</div>';
                }
            } catch (parseError) {
                console.error('Parse error:', parseError);
                dataGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3><?php echo __('Error'); ?></h3><p>Parse error: ' + escapeHtml(parseError.message) + '</p><pre style="text-align:left;font-size:11px;overflow:auto;max-height:200px;">' + escapeHtml(text.substring(0, 1000)) + '</pre></div>';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            dataGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3><?php echo __('Error'); ?></h3><p>' + escapeHtml(error.message) + '</p></div>';
        });
}

function performSearch() {
    const searchQuery = document.getElementById('modalSearch').value;
    loadModalData(searchQuery);
}

// Allow Enter key to search
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
});

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
</script>

</body>
</html>
