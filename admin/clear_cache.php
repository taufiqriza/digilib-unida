<?php
// key to authenticate
define('INDEX_AUTH', '1');

// required file
require '../sysconfig.inc.php';

// Clear the cache
try {
    \SLiMS\Cache::purge();
    echo 'Cache has been successfully cleared. Please try accessing your module now. You can also delete this file.';
} catch (Exception $e) {
    echo 'An error occurred while clearing the cache: ' . $e->getMessage();
}
