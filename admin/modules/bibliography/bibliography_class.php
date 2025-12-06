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

define('INDEX_AUTH', '1');
define('BIBLIOGRAPHY_AUTH', 1);

require '../../../sysconfig.inc.php';

require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');

require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';
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

function normalize_biblio_year($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/(17|18|19|20|21)\d{2}/', $raw, $match)) {
        $year = (int)$match[0];
        $current = (int)date('Y') + 1;
        if ($year >= 1700 && $year <= $current) {
            return (string)$year;
        }
    }
    return null;
}

function getStatistics($dbs)
{
    $stats = array();
    $total_q = $dbs->query("SELECT COUNT(DISTINCT biblio_id) as total FROM biblio WHERE opac_hide=0");
    $total_d = $total_q->fetch_assoc();
    $stats['total_biblio'] = $total_d['total'];
    $items_q = $dbs->query("SELECT COUNT(item_id) as total FROM item");
    $items_d = $items_q->fetch_assoc();
    $stats['total_items'] = $items_d['total'];
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
    $publisher_q = $dbs->query("SELECT
        p.publisher_id,
        p.publisher_name,
        COUNT(DISTINCT b.biblio_id) as count
        FROM mst_publisher p
        LEFT JOIN biblio b ON p.publisher_id = b.publisher_id AND b.opac_hide=0
        GROUP BY p.publisher_id, p.publisher_name
        HAVING count > 0
        ORDER BY count DESC
        LIMIT 12");
    $stats['by_publisher'] = array();
    while ($publisher_d = $publisher_q->fetch_assoc()) {
        $stats['by_publisher'][] = $publisher_d;
    }
    $year_counts = array();
    $year_q = $dbs->query("SELECT publish_year FROM biblio WHERE publish_year IS NOT NULL AND publish_year <> '' AND opac_hide=0");
    while ($year_d = $year_q->fetch_assoc()) {
        $normalized = normalize_biblio_year($year_d['publish_year']);
        if ($normalized) {
            if (!isset($year_counts[$normalized])) {
                $year_counts[$normalized] = 0;
            }
            $year_counts[$normalized]++;
        }
    }
    krsort($year_counts, SORT_NUMERIC);
    $stats['by_year'] = array();
    foreach ($year_counts as $year => $count) {
        $stats['by_year'][] = array('publish_year' => $year, 'count' => $count);
        if (count($stats['by_year']) >= 30) {
            break;
        }
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

        .biblio-class-hero {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 80%);
            border-radius: 20px;
            padding: 22px 28px;
            margin: 16px 20px 0 20px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .biblio-class-hero__content {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }

        .biblio-class-hero__icon {
            width: 54px;
            height: 54px;
            background: rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .biblio-class-hero__text h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
        }

        .biblio-class-hero__text p {
            margin: 4px 0 0 0;
            font-size: 13px;
            opacity: 0.9;
            line-height: 1.4;
        }

        .biblio-class-container {
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

        .menu-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 6px;
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.35);
        }

        .menu-badge--green {
            background: #059669;
        }

        .category-section-header {
            margin: 36px 0 18px;
            padding: 0 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .category-section__eyebrow {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-light);
            margin: 0 0 6px;
        }

        .category-section__title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .category-section__hint {
            font-size: 13px;
            color: var(--text-light);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        @media (max-width: 1200px) {
            .category-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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
            padding: 16px 20px;
            border-bottom: 2px solid var(--border-light);
        }

        .category-card__title {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .category-card__title i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-blue-dark));
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .category-card__body {
            padding: 16px 24px 24px;
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
        }

        .category-card__body:hover {
            scrollbar-width: thin;
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

        .category-item--stacked {
            align-items: flex-start;
        }

        .category-item__metrics {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            min-width: 0;
            margin-left: auto;
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
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-blue);
            background: rgba(59, 130, 246, 0.15);
            padding: 3px 12px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            width: auto;
            min-width: 0;
            margin-left: auto;
        }

        .category-item__badge {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            font-size: 10px;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 700;
            margin-left: auto;
            background: rgba(5, 150, 105, 0.15);
            color: var(--success-green);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            white-space: nowrap;
            flex-shrink: 0;
            width: auto;
            min-width: 0;
        }

        .category-item:not(.category-item--stacked) .category-item__count::before {
            content: "\f5fd";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            display: inline-flex;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.4);
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: var(--primary-blue);
        }

        .category-item--stacked .category-item__count::before {
            content: none;
        }

        .category-item__metrics .category-item__count {
            width: auto;
            justify-content: flex-end;
        }

        .category-item__metrics .category-item__badge {
            width: auto;
            margin-left: 0;
            justify-content: flex-end;
        }

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


        .modal-data-grid {
            display: block;
        }

        .biblio-card-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .biblio-card {
            background: white;
            border-radius: 18px;
            padding: 20px 22px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.9fr) auto;
            gap: 20px;
            align-items: flex-start;
        }

        .biblio-card__title {
            font-weight: 700;
            font-size: 16px;
            color: var(--text-dark);
            margin: 0 0 10px 0;
        }

        .biblio-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .biblio-card__meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary-blue);
            font-size: 12px;
            font-weight: 600;
        }

        .biblio-card__meta i {
            font-size: 12px;
        }

        .biblio-card__info {
            font-size: 13px;
            color: var(--text-medium);
            line-height: 1.6;
            display: grid;
            gap: 8px;
        }

        .biblio-card__info strong {
            color: var(--text-dark);
        }

        .biblio-card__actions {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
        }

        .modal-action-btn {
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .modal-action-btn i {
            font-size: 14px;
        }

        .modal-action-btn--view {
            background: rgba(59, 130, 246, 0.15);
            color: var(--primary-blue);
        }

        .modal-action-btn--view:hover {
            background: rgba(37, 99, 235, 0.2);
            color: var(--primary-blue-dark);
        }

        @media (max-width: 992px) {
            .biblio-card {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .biblio-card__actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 640px) {
            .biblio-card {
                padding: 18px 18px;
            }

            .modal-action-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .detail-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 10000;
            animation: fadeIn 0.2s ease;
        }

        .detail-modal-overlay.active {
            display: block;
        }

        .detail-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: min(720px, 94vw);
            max-height: 90vh;
            background: white;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 30px 60px -20px rgba(15, 23, 42, 0.35);
            z-index: 10001;
            animation: slideUp 0.25s ease;
        }

        .detail-modal.active {
            display: block;
        }

        .detail-modal__header {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .detail-modal__title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            max-width: 85%;
        }

        .detail-modal__close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .detail-modal__close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .detail-modal__body {
            padding: 26px 28px;
            overflow-y: auto;
            max-height: calc(90vh - 200px);
        }

        .detail-section {
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
        }

        .detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-section__label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-light);
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .detail-section__content {
            font-size: 14px;
            color: var(--text-medium);
            line-height: 1.7;
        }

        .detail-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.12);
            color: var(--primary-blue);
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            margin-right: 10px;
            margin-bottom: 8px;
        }

        .detail-modal__footer {
            padding: 22px 28px;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .detail-footer__meta {
            font-size: 12px;
            color: var(--text-light);
        }

        .detail-footer__actions {
            display: flex;
            gap: 10px;
        }

        .detail-footer__actions a,
        .detail-footer__actions button {
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .detail-footer__actions .edit-link {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .detail-footer__actions .edit-link:hover {
            filter: brightness(1.05);
        }

        .detail-footer__actions .close-link {
            background: rgba(15, 23, 42, 0.05);
            color: var(--text-medium);
        }

        .detail-footer__actions .close-link:hover {
            background: rgba(15, 23, 42, 0.08);
            color: var(--text-dark);
        }

        .detail-section__content.detail-list strong {
            color: var(--text-dark);
        }

        .detail-section__content.detail-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .detail-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.12);
            color: var(--primary-blue);
            font-size: 12px;
            font-weight: 600;
        }

        .detail-chip i {
            font-size: 12px;
        }

        .detail-role {
            color: var(--text-light);
            font-style: italic;
            font-weight: 500;
        }

        .detail-copy-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }

        .detail-copy-list li {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            background: rgba(148, 163, 184, 0.12);
            border-radius: 12px;
            font-size: 13px;
            color: var(--text-medium);
        }

        .copy-code {
            font-weight: 700;
            color: var(--primary-blue);
        }

        .copy-location {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-light);
            font-size: 12px;
        }

        .copy-location i {
            color: var(--primary-blue);
        }

        .copy-status {
            margin-left: auto;
            font-size: 12px;
            font-weight: 600;
            color: var(--success-green);
        }

        .detail-loading {
            padding: 40px 0;
            text-align: center;
            color: var(--text-light);
        }

        .detail-loading i {
            font-size: 40px;
            color: var(--primary-blue);
            animation: spin 1s linear infinite;
            margin-bottom: 14px;
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

        .category-card__body::-webkit-scrollbar {
            width: 0;
        }

        .category-card__body:hover::-webkit-scrollbar {
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

<div class="biblio-class-hero">
    <div class="biblio-class-hero__content">
        <div class="biblio-class-hero__icon">
            <i class="fas fa-layer-group"></i>
        </div>
        <div class="biblio-class-hero__text">
            <h1>Klasifikasi & Kategori Bibliografi</h1>
            <p>Analisis koleksi komprehensif berdasarkan klasifikasi, kategori, dan jenis bahan</p>
        </div>
    </div>
</div>

<div class="biblio-class-container">
    <div class="stats-dashboard">
        <div class="stat-card">
            <div class="stat-card__content">
                <div class="stat-card__icon stat-card__icon--primary">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-card__text">
                    <div class="stat-card__label">Total Koleksi</div>
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
                    <div class="stat-card__label">Total Eksemplar</div>
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
                    <div class="stat-card__label">Jenis Koleksi</div>
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
                    <div class="stat-card__label">Jenis Bahan</div>
                    <div class="stat-card__value"><?php echo count($statistics['by_gmd']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="category-grid">
        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-cube"></i>
                    Berdasarkan Jenis Bahan (GMD)
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
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-folder-open"></i>
                    Berdasarkan Jenis Koleksi
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_collection']) > 0): ?>
                    <?php foreach ($statistics['by_collection'] as $coll): ?>
                        <?php if ($coll['count'] > 0): ?>
                        <div class="category-item category-item--stacked" style="cursor: pointer;" onclick="openModal('collection', <?php echo $coll['coll_type_id']; ?>, '<?php echo addslashes($coll['coll_type_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($coll['coll_type_name']); ?>
                            </span>
                            <span class="category-item__metrics">
                                <span class="category-item__count">
                                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                                    <?php echo number_format($coll['count']); ?>
                                </span>
                                <span class="category-item__badge">
                                    <i class="fas fa-barcode" aria-hidden="true"></i>
                                    <?php echo number_format($coll['item_count']); ?>
                                </span>
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-sitemap"></i>
                    Berdasarkan Klasifikasi
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
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-language"></i>
                    Berdasarkan Bahasa
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_language']) > 0): ?>
                    <?php foreach ($statistics['by_language'] as $lang): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('language', '<?php echo htmlspecialchars($lang['language_id'], ENT_QUOTES); ?>', '<?php echo addslashes($lang['language_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($lang['language_name']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($lang['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-building"></i>
                    Berdasarkan Penerbit
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_publisher']) > 0): ?>
                    <?php foreach ($statistics['by_publisher'] as $publisher): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('publisher', <?php echo (int)$publisher['publisher_id']; ?>, '<?php echo addslashes($publisher['publisher_name']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($publisher['publisher_name']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($publisher['count']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="category-card">
            <div class="category-card__header">
                <h2 class="category-card__title">
                    <i class="fas fa-calendar-days"></i>
                    Berdasarkan Tahun Terbit
                </h2>
            </div>
            <div class="category-card__body">
                <?php if (count($statistics['by_year']) > 0): ?>
                    <?php foreach ($statistics['by_year'] as $year): ?>
                        <?php if (trim($year['publish_year']) !== ''): ?>
                        <div class="category-item" style="cursor: pointer;" onclick="openModal('year', '<?php echo addslashes($year['publish_year']); ?>', 'Tahun <?php echo addslashes($year['publish_year']); ?>')">
                            <span class="category-item__name">
                                <?php echo htmlspecialchars($year['publish_year']); ?>
                            </span>
                            <span class="category-item__count"><?php echo number_format($year['count']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
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
            <input type="text" id="modalSearch" placeholder="Cari judul, pengarang, atau ISBN...">
            <button onclick="performSearch()">
                <i class="fas fa-search"></i>
                Cari
            </button>
        </div>
        <div class="modal-data-grid" id="modalDataGrid">
            <div class="modal-loading">
                <i class="fas fa-spinner"></i>
                <p>Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<div class="detail-modal-overlay" id="detailModalOverlay" onclick="closeDetailModal(event)"></div>
<div class="detail-modal" id="detailModal">
    <div class="detail-modal__header">
        <h3 class="detail-modal__title" id="detailModalTitle">Detail Bibliografi</h3>
        <button class="detail-modal__close" type="button" onclick="closeDetailModal(event)">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="detail-modal__body" id="detailModalBody">
        <div class="detail-loading">
            <i class="fas fa-spinner"></i>
            <p>Memuat detail...</p>
        </div>
    </div>
    <div class="detail-modal__footer">
        <div class="detail-footer__meta" id="detailModalMeta"></div>
        <div class="detail-footer__actions">
            <button type="button" class="edit-link notAJAX" id="detailModalEdit" onclick="openBibliographyEditor();">
                <i class="fas fa-pen-to-square"></i> Edit Bibliografi
            </button>
            <a href="javascript:void(0)" class="close-link" onclick="closeDetailModal(event)">
                <i class="fas fa-times-circle"></i> Tutup
            </a>
        </div>
    </div>
</div>

<script>
const modalDataEndpoint = <?php echo json_encode(MWB . 'bibliography/bibliography_class_data.php'); ?>;
const modalDetailEndpoint = <?php echo json_encode(MWB . 'bibliography/bibliography_class_detail.php'); ?>;
const adminIndexBase = <?php echo json_encode(SWB . 'admin/index.php'); ?>;
const moduleEditEndpoint = <?php echo json_encode(MWB . 'bibliography/index.php'); ?>;
const detailLoadingTemplate = '<div class="detail-loading"><i class="fas fa-spinner"></i><p>Memuat detail...</p></div>';

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
            titleText = 'Jenis Bahan: ' + label;
            break;
        case 'collection':
            titleText = 'Jenis Koleksi: ' + label;
            break;
        case 'classification':
            titleText = 'Klasifikasi: ' + label;
            break;
        case 'language':
            titleText = 'Bahasa: ' + label;
            break;
        case 'publisher':
            titleText = 'Penerbit: ' + label;
            break;
        case 'year':
            titleText = 'Tahun Terbit: ' + label;
            break;
        default:
            titleText = 'Data Terfilter';
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
    dataGrid.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner"></i><p>Memuat data...</p></div>';

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

    // Fetch data as HTML from dedicated endpoint
    fetch(`${modalDataEndpoint}?${params.toString()}`, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            if (!html.trim()) {
                dataGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>Tidak ada konten dari server.</p></div>';
                return;
            }
            dataGrid.innerHTML = html;
        })
        .catch(error => {
            console.error('Fetch error:', error);
            dataGrid.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Error</h3><p>' + escapeHtml(error.message) + '</p></div>';
        });
}

function showBiblioDetail(biblioId) {
    if (!biblioId) {
        return;
    }
    const overlay = document.getElementById('detailModalOverlay');
    const modal = document.getElementById('detailModal');
    const title = document.getElementById('detailModalTitle');
    const meta = document.getElementById('detailModalMeta');
    const editLink = document.getElementById('detailModalEdit');

    title.textContent = 'Detail Bibliografi';
    const body = document.getElementById('detailModalBody');
    body.innerHTML = '<div class="detail-loading"><i class="fas fa-spinner"></i><p>Memuat detail...</p></div>';
    meta.textContent = '';
    editLink.dataset.biblioId = '';
    editLink.dataset.editUrl = '';
    editLink.dataset.moduleUrl = '';

    overlay.classList.add('active');
    modal.classList.add('active');

    const params = new URLSearchParams();
    params.append('id', biblioId);

    fetch(`${modalDetailEndpoint}?${params.toString()}`, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(payload => {
            if (!payload.success) {
                throw new Error(payload.error || 'Unable to load detail');
            }
            title.textContent = payload.title || 'Detail Bibliografi';
            body.innerHTML = payload.html || '';
            meta.textContent = payload.meta || '';
            const biblioId = payload.id || '';
            const defaultEditUrl = `${adminIndexBase}?mod=bibliography&action=detail&detail=true&itemID=${encodeURIComponent(biblioId)}`;
            editLink.dataset.biblioId = biblioId;
            editLink.dataset.editUrl = payload.adminEditUrl || defaultEditUrl;
            editLink.dataset.moduleUrl = payload.moduleEditUrl || moduleEditEndpoint;
        })
        .catch(error => {
            console.error('Detail fetch error:', error);
            if (body) {
                body.innerHTML = '<div class="detail-loading"><i class="fas fa-circle-exclamation"></i><p>' + escapeHtml(error.message) + '</p></div>';
            }
        });
}

function closeDetailModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const overlay = document.getElementById('detailModalOverlay');
    const modal = document.getElementById('detailModal');
    if (overlay) overlay.classList.remove('active');
    if (modal) modal.classList.remove('active');

    const body = document.getElementById('detailModalBody');
    if (body) {
        body.innerHTML = detailLoadingTemplate;
        body.scrollTop = 0;
    }
    const meta = document.getElementById('detailModalMeta');
    if (meta) {
        meta.textContent = '';
    }
    const editLink = document.getElementById('detailModalEdit');
    if (editLink) {
        editLink.dataset.biblioId = '';
        editLink.dataset.editUrl = '';
        editLink.dataset.moduleUrl = '';
    }
}

function openBibliographyEditor() {
    const editLink = document.getElementById('detailModalEdit');
    if (!editLink) {
        return;
    }
    const biblioId = editLink.dataset.biblioId || '';
    if (!biblioId) {
        return;
    }
    const moduleUrl = editLink.dataset.moduleUrl || moduleEditEndpoint;
    const fallbackUrl = editLink.dataset.editUrl || `${moduleUrl}?itemID=${encodeURIComponent(biblioId)}&detail=true`;

    try {
        closeDetailModal();
        closeModal();
    } catch (err) {
        console.warn('Unable to close modal before navigating', err);
    }

    try {
        const topWindow = window.top || window;
        if (topWindow && topWindow.$ && typeof topWindow.$('#mainContent').simbioAJAX === 'function') {
            topWindow.$('#mainContent').simbioAJAX(moduleUrl, {
                method: 'post',
                addData: `itemID=${encodeURIComponent(biblioId)}&detail=true`
            });
            return;
        }
    } catch (err) {
        console.error('Failed to use AJAX editor path', err);
    }

    if (window.top) {
        window.top.location.href = fallbackUrl;
    } else {
        window.location.href = fallbackUrl;
    }
}

function performSearch() {
    const searchQuery = document.getElementById('modalSearch').value;
    loadModalData(searchQuery);
}

// Allow Enter key to search
document.addEventListener('DOMContentLoaded', function() {
    const modalSearch = document.getElementById('modalSearch');
    if (modalSearch) {
        modalSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
    const editLink = document.getElementById('detailModalEdit');
    if (editLink) {
        editLink.addEventListener('click', function(ev) {
            ev.preventDefault();
            openBibliographyEditor();
        });
    }
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
