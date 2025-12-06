<?php
/**
 * Bibliography Class - AJAX Handler
 * Separate file to avoid HTML output issues
 */

// Set JSON header FIRST before any output
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

// Manual session check without redirect
if (!isset($_SESSION['uid'])) {
    echo json_encode(array(
        'success' => false,
        'error' => 'Session expired. Please login again.',
        'data' => array()
    ));
    exit;
}

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');

if (!$can_read) {
    echo json_encode(array(
        'success' => false,
        'error' => 'You don\'t have enough privileges to view this section',
        'data' => array()
    ));
    exit;
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
    COUNT(DISTINCT i.item_id) as copies
    FROM biblio AS b
    LEFT JOIN item AS i ON b.biblio_id = i.biblio_id
    LEFT JOIN biblio_author AS ba ON b.biblio_id = ba.biblio_id
    LEFT JOIN mst_author AS ma ON ba.author_id = ma.author_id";

// Build WHERE criteria
$criteria = array();
$criteria[] = "b.opac_hide=0";

// Add filter criteria based on type
if ($type == 'gmd' && $valueInt > 0) {
    $criteria[] = "b.gmd_id=" . $valueInt;
} elseif ($type == 'collection' && $valueInt > 0) {
    $criteria[] = "i.coll_type_id=" . $valueInt;
} elseif ($type == 'classification' && !empty($classCode)) {
    $criteria[] = "b.classification LIKE '%" . $classCode . "%'";
} elseif ($type == 'language' && $valueEscaped !== '') {
    $criteria[] = "b.language_id='" . $valueEscaped . "'";
} elseif ($type == 'publisher' && $valueInt > 0) {
    $criteria[] = "b.publisher_id=" . $valueInt;
} elseif ($type == 'year' && $valueEscaped !== '') {
    $criteria[] = "TRIM(b.publish_year) REGEXP '(^|[^0-9])" . $valueEscaped . "([^0-9]|$)'";
}

// Add search criteria
if (!empty($search)) {
    $criteria[] = "(b.title LIKE '%" . $search . "%' OR ma.author_name LIKE '%" . $search . "%' OR b.isbn_issn LIKE '%" . $search . "%')";
}

// Combine criteria
$sql .= " WHERE " . implode(' AND ', $criteria);
$sql .= " GROUP BY b.biblio_id, b.title, b.classification, b.publish_year, b.isbn_issn";
$sql .= " ORDER BY b.last_update DESC";
$sql .= " LIMIT 100";

// Execute query
$result = $dbs->query($sql);
$data = array();
$error = null;

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                'biblio_id' => $row['biblio_id'],
                'title' => $row['title'] ?: '-',
                    'author' => $row['authors'] ?: '-',
                'classification' => $row['classification'] ?: '-',
                'publish_year' => $row['publish_year'] ?: '-',
                'isbn_issn' => $row['isbn_issn'] ?: '-',
                'copies' => intval($row['copies'])
            );
        }
    }
} else {
    $error = $dbs->error;
}

// Normalize string values to valid UTF-8 to avoid JSON encoding issues
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
    'success' => $result !== false,
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
