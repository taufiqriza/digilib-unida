<?php
/**
 * Bibliography Class - AJAX Handler
 * Separate file to avoid HTML output issues
 */

// Set JSON header FIRST before any output
header('Content-Type: application/json');

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
$value = isset($_GET['value']) ? intval($_GET['value']) : 0;
$classCode = isset($_GET['class']) ? $dbs->escape_string(trim($_GET['class'])) : '';
$search = isset($_GET['search']) ? $dbs->escape_string(trim($_GET['search'])) : '';

// Build base query
$sql = "SELECT
    b.biblio_id,
    b.title,
    b.author,
    b.classification,
    b.publish_year,
    b.isbn_issn,
    COUNT(DISTINCT i.item_id) as copies
    FROM biblio AS b
    LEFT JOIN item AS i ON b.biblio_id = i.biblio_id";

// Build WHERE criteria
$criteria = array();
$criteria[] = "b.opac_hide=0";

// Add filter criteria based on type
if ($type == 'gmd' && $value > 0) {
    $criteria[] = "b.gmd_id=" . $value;
} elseif ($type == 'collection' && $value > 0) {
    $criteria[] = "i.coll_type_id=" . $value;
} elseif ($type == 'classification' && !empty($classCode)) {
    $criteria[] = "b.classification LIKE '%" . $classCode . "%'";
} elseif ($type == 'language' && $value > 0) {
    $criteria[] = "b.language_id='" . $value . "'";
}

// Add search criteria
if (!empty($search)) {
    $criteria[] = "(b.title LIKE '%" . $search . "%' OR b.author LIKE '%" . $search . "%' OR b.isbn_issn LIKE '%" . $search . "%')";
}

// Combine criteria
$sql .= " WHERE " . implode(' AND ', $criteria);
$sql .= " GROUP BY b.biblio_id, b.title, b.author, b.classification, b.publish_year, b.isbn_issn";
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
                'author' => $row['author'] ?: '-',
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

echo json_encode(array(
    'success' => $result !== false,
    'data' => $data,
    'count' => count($data),
    'error' => $error,
    'sql' => $sql // For debugging - remove in production
));
exit;
