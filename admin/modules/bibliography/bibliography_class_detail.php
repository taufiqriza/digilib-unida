<?php
/**
 * Bibliography Class - Detail Provider (JSON)
 */

header('Content-Type: application/json; charset=utf-8');

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

// Helper to respond and exit
function detail_response($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!isset($_SESSION['uid'])) {
    detail_response([
        'success' => false,
        'error' => __('Session expired. Please login again.')
    ]);
}

// privileges checking
if (!utility::havePrivilege('bibliography', 'r')) {
    detail_response([
        'success' => false,
        'error' => __('You don\'t have enough privileges to view this section')
    ]);
}

$biblioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($biblioId < 1) {
    detail_response([
        'success' => false,
        'error' => __('Invalid bibliography identifier.')
    ]);
}

require_once __DIR__ . '/biblio.inc.php';

$biblio = new Biblio($dbs, $biblioId);
$record = $biblio->detail('full');

if (!$record) {
    detail_response([
        'success' => false,
        'error' => __('Bibliography data not found.')
    ]);
}

$title = $record['title'] ?? __('Untitled');
$classification = trim($record['classification'] ?? '');
$gmd = trim($record['gmd_name'] ?? $record['gmd'] ?? '');
$language = trim($record['language_name'] ?? $record['language'] ?? '');
$publish_year = trim($record['publish_year'] ?? '');
$publisher = trim($record['publisher_name'] ?? $record['publisher'] ?? '');
$publish_place = trim($record['publish_place'] ?? '');
$isbn = trim($record['isbn_issn'] ?? '');
$call_number = trim($record['call_number'] ?? '');
$collation = trim($record['collation'] ?? '');
$series = trim($record['series_title'] ?? '');
$notes = trim($record['notes'] ?? '');
$specific_info = trim($record['specific_detail_info'] ?? '');

$authors = array();
if (!empty($record['authors'])) {
    foreach ($record['authors'] as $author) {
        $name = trim($author['author_name'] ?? '');
        if ($name !== '') {
            $role = trim($author['authority_type'] ?? '');
            if ($role !== '') {
                $authors[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' <span class="detail-role">(' . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . ')</span>';
            } else {
                $authors[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            }
        }
    }
}

$subjects = array();
if (!empty($record['subjects'])) {
    foreach ($record['subjects'] as $subject) {
        $topic = trim($subject['topic'] ?? '');
        if ($topic !== '') {
            $subjects[] = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');
        }
    }
}

$copies = array();
if (!empty($record['copies'])) {
    foreach ($record['copies'] as $copy) {
        $itemCode = htmlspecialchars($copy['item_code'] ?? '-', ENT_QUOTES, 'UTF-8');
        $location = htmlspecialchars($copy['location_name'] ?? __('Unknown Location'), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($copy['item_status_name'] ?? $copy['avail_statement'] ?? __('Available'), ENT_QUOTES, 'UTF-8');
        $site = htmlspecialchars($copy['site'] ?? '', ENT_QUOTES, 'UTF-8');
        $copies[] = sprintf(
            '<li><span class="copy-code">%s</span><span class="copy-location"><i class="fas fa-location-dot"></i> %s</span><span class="copy-status">%s%s</span></li>',
            $itemCode,
            $location,
            $status,
            $site ? ' · ' . $site : ''
        );
    }
}

$copyCount = count($record['copies'] ?? array());
$badgeHtml = array();
if ($classification !== '') {
    $badgeHtml[] = '<span class="detail-badge"><i class="fas fa-tags"></i>' . htmlspecialchars($classification, ENT_QUOTES, 'UTF-8') . '</span>';
}
if ($gmd !== '') {
    $badgeHtml[] = '<span class="detail-badge"><i class="fas fa-shapes"></i>' . htmlspecialchars($gmd, ENT_QUOTES, 'UTF-8') . '</span>';
}
if ($language !== '') {
    $badgeHtml[] = '<span class="detail-badge"><i class="fas fa-language"></i>' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '</span>';
}

$html = '';

if (!empty($badgeHtml)) {
    $html .= '<div class="detail-section">' . implode('', $badgeHtml) . '</div>';
}

if (!empty($authors)) {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Contributors') . '</div>';
    $html .= '<div class="detail-section__content detail-list">' . implode('<br>', $authors) . '</div>';
    $html .= '</div>';
}

$publicationInfo = array();
if ($publisher !== '') {
    $publicationInfo[] = '<strong>' . __('Publisher') . ':</strong> ' . htmlspecialchars($publisher, ENT_QUOTES, 'UTF-8');
}
if ($publish_place !== '') {
    $publicationInfo[] = '<strong>' . __('Publish Place') . ':</strong> ' . htmlspecialchars($publish_place, ENT_QUOTES, 'UTF-8');
}
if ($publish_year !== '') {
    $publicationInfo[] = '<strong>' . __('Publish Year') . ':</strong> ' . htmlspecialchars($publish_year, ENT_QUOTES, 'UTF-8');
}
if ($collation !== '') {
    $publicationInfo[] = '<strong>' . __('Collation') . ':</strong> ' . htmlspecialchars($collation, ENT_QUOTES, 'UTF-8');
}
if ($series !== '') {
    $publicationInfo[] = '<strong>' . __('Series') . ':</strong> ' . htmlspecialchars($series, ENT_QUOTES, 'UTF-8');
}
if ($specific_info !== '') {
    $publicationInfo[] = '<strong>' . __('Specific Detail') . ':</strong> ' . htmlspecialchars($specific_info, ENT_QUOTES, 'UTF-8');
}

if (!empty($publicationInfo)) {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Publication') . '</div>';
    $html .= '<div class="detail-section__content detail-list">' . implode('<br>', $publicationInfo) . '</div>';
    $html .= '</div>';
}

$identifierInfo = array();
if ($isbn !== '') {
    $identifierInfo[] = '<strong>' . __('ISBN/ISSN') . ':</strong> ' . htmlspecialchars($isbn, ENT_QUOTES, 'UTF-8');
}
if ($call_number !== '') {
    $identifierInfo[] = '<strong>' . __('Call Number') . ':</strong> ' . htmlspecialchars($call_number, ENT_QUOTES, 'UTF-8');
}

if (!empty($identifierInfo)) {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Identifiers') . '</div>';
    $html .= '<div class="detail-section__content detail-list">' . implode('<br>', $identifierInfo) . '</div>';
    $html .= '</div>';
}

if (!empty($subjects)) {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Subjects') . '</div>';
    $html .= '<div class="detail-section__content detail-chips">';
    foreach ($subjects as $subject) {
        $html .= '<span class="detail-chip"><i class="fas fa-bookmark"></i> ' . $subject . '</span>';
    }
    $html .= '</div>';
    $html .= '</div>';
}

if (!empty($copies)) {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Copies & Availability') . '</div>';
    $html .= '<div class="detail-section__content"><ul class="detail-copy-list">' . implode('', $copies) . '</ul></div>';
    $html .= '</div>';
}

if ($notes !== '') {
    $html .= '<div class="detail-section">';
    $html .= '<div class="detail-section__label">' . __('Notes') . '</div>';
    $html .= '<div class="detail-section__content">' . nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')) . '</div>';
    $html .= '</div>';
}

$metaParts = array();
$metaParts[] = sprintf(__('Copies: %d'), $copyCount);
if ($isbn !== '') {
    $metaParts[] = sprintf(__('ISBN: %s'), htmlspecialchars($isbn, ENT_QUOTES, 'UTF-8'));
}
if ($publish_year !== '') {
    $metaParts[] = sprintf(__('Year: %s'), htmlspecialchars($publish_year, ENT_QUOTES, 'UTF-8'));
}

detail_response([
    'success' => true,
    'id' => $biblioId,
    'title' => $title,
    'html' => $html,
    'meta' => implode(' • ', $metaParts),
    'editUrl' => MWB . 'bibliography/index.php?itemID=' . $biblioId . '&detail=true',
    'adminEditUrl' => SWB . 'admin/index.php?mod=bibliography&action=detail&itemID=' . $biblioId . '&detail=true',
    'moduleEditUrl' => MWB . 'bibliography/index.php',
]);
