<?php
/**
 * Bibliography Class - Modal Data Provider (HTML)
 *
 * Generates the modal content as HTML so the front-end can inject it
 * directly without performing JSON parsing.
 */

// Ensure JSON headers are not sent; we output HTML
header('Content-Type: text/html; charset=utf-8');

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

// Helper to output consistent HTML response and stop execution
function output_message($message, $icon = 'fas fa-exclamation-triangle')
{
    echo '<div class="empty-state">';
    echo '<i class="' . $icon . '"></i>';
    echo '<h3>' . __('Information') . '</h3>';
    echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</div>';
    exit;
}

if (!isset($_SESSION['uid'])) {
    output_message(__('Session expired. Please login again.'), 'fas fa-user-lock');
}

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
    output_message(__('You don\'t have enough privileges to view this section'), 'fas fa-ban');
}

// Get parameters
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$valueRaw = isset($_GET['value']) ? trim($_GET['value']) : '';
$valueEscaped = $dbs->escape_string($valueRaw);
$valueInt = ctype_digit($valueRaw) ? (int)$valueRaw : 0;
$classCode = isset($_GET['class']) ? $dbs->escape_string(trim($_GET['class'])) : '';
$search = isset($_GET['search']) ? $dbs->escape_string(trim($_GET['search'])) : '';

// Build base query
$sql = "SELECT
    b.biblio_id,
    b.title,
    IFNULL(GROUP_CONCAT(DISTINCT ma.author_name ORDER BY ma.author_name SEPARATOR '; '), '') AS authors,
    b.classification,
    b.publish_year,
    b.isbn_issn,
    COUNT(DISTINCT i.item_id) AS copies
FROM biblio AS b
LEFT JOIN item AS i ON b.biblio_id = i.biblio_id
LEFT JOIN biblio_author AS ba ON b.biblio_id = ba.biblio_id
LEFT JOIN mst_author AS ma ON ba.author_id = ma.author_id";

// Build WHERE criteria
$criteria = array();
$criteria[] = "b.opac_hide=0";

// Add filter criteria based on type
if ($type === 'gmd' && $valueInt > 0) {
    $criteria[] = "b.gmd_id=" . $valueInt;
} elseif ($type === 'collection' && $valueInt > 0) {
    $criteria[] = "i.coll_type_id=" . $valueInt;
} elseif ($type === 'classification' && $classCode !== '') {
    $criteria[] = "b.classification LIKE '%" . $classCode . "%'";
} elseif ($type === 'language' && $valueEscaped !== '') {
    $criteria[] = "b.language_id='" . $valueEscaped . "'";
}

// Add search criteria
if ($search !== '') {
    $criteria[] = "(b.title LIKE '%" . $search . "%' OR ma.author_name LIKE '%" . $search . "%' OR b.isbn_issn LIKE '%" . $search . "%')";
}

// Combine criteria
$sql .= " WHERE " . implode(' AND ', $criteria);
$sql .= " GROUP BY b.biblio_id, b.title, b.classification, b.publish_year, b.isbn_issn";
$sql .= " ORDER BY b.last_update DESC";
$sql .= " LIMIT 100";

// Execute query
$result = $dbs->query($sql);

if (!$result) {
    output_message(__('Failed to retrieve data.') . ' ' . $dbs->error, 'fas fa-bug');
}

if ($result->num_rows < 1) {
    echo '<div class="empty-state">';
    echo '<i class="fas fa-search"></i>';
    echo '<h3>' . __('No Results Found') . '</h3>';
    echo '<p>' . __('No bibliographies match the selected filter.') . '</p>';
    echo '</div>';
    exit;
}

echo '<div class="biblio-card-list">';

while ($row = $result->fetch_assoc()) {
    $title = $row['title'] ?: '-';
    $author = $row['authors'] ?: '-';
    $class = $row['classification'] ?: '-';
    $year = $row['publish_year'] ?: '-';
    $isbn = $row['isbn_issn'] ?: '-';
    $copies = (int)$row['copies'];
    $biblioId = (int)$row['biblio_id'];

    $metaBlocks = array();
    if ($class !== '-') {
        $metaBlocks[] = '<span><i class="fas fa-tags"></i>' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    if ($isbn !== '-') {
        $metaBlocks[] = '<span><i class="fas fa-barcode"></i>' . htmlspecialchars($isbn, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    if ($year !== '-') {
        $metaBlocks[] = '<span><i class="fas fa-calendar-alt"></i>' . htmlspecialchars($year, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $metaBlocks[] = '<span><i class="fas fa-layer-group"></i>' . sprintf(__('Copies: %d'), $copies) . '</span>';

    echo '<article class="biblio-card">';

    echo '<div>';
    echo '<h4 class="biblio-card__title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
    if (!empty($metaBlocks)) {
        echo '<div class="biblio-card__meta">' . implode('', $metaBlocks) . '</div>';
    }
    echo '</div>';

    echo '<div class="biblio-card__info">';
    echo '<div><strong>' . __('Author') . ':</strong> ' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</div>';
    if ($year !== '-') {
        echo '<div><strong>' . __('Published') . ':</strong> ' . htmlspecialchars($year, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '</div>';

    echo '<div class="biblio-card__actions">';
    echo '<button type="button" class="modal-action-btn modal-action-btn--view" onclick="showBiblioDetail(' . $biblioId . ')"><i class="fas fa-eye"></i> ' . __('View') . '</button>';
    echo '</div>';

    echo '</article>';
}

echo '</div>';

exit;
