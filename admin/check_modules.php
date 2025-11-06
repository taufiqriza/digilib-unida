<?php
// key to authenticate
define('INDEX_AUTH', '1');

// required file
require '../sysconfig.inc.php';

echo '<h2>Modules in mst_module table:</h2>';
echo '<pre>';

$modules_q = $dbs->query("SELECT * FROM mst_module");
if ($modules_q) {
    while ($module = $modules_q->fetch_assoc()) {
        print_r($module);
    }
} else {
    echo 'Error querying the database: ' . $dbs->error;
}

echo '</pre>';
