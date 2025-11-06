<?php
// key to authenticate
define('INDEX_AUTH', '1');

// required file
require '../sysconfig.inc.php';

// Check if the module already exists
$check_q = $dbs->query("SELECT * FROM mst_module WHERE module_path = 'staff_manage'");
if ($check_q->num_rows > 0) {
    echo 'The "Staff Management" module already exists in the database. Please delete this file.';
} else {
    // Insert the new module
    $dbs->query("INSERT INTO mst_module (module_name, module_path, module_desc) VALUES ('Staff Management', 'staff_manage', 'Module for managing staff members')");
    if ($dbs->error) {
        echo 'Failed to insert module: ' . $dbs->error;
    } else {
        echo 'Module "Staff Management" has been successfully added to the database. You can now set the privileges for this module in the user group settings. Please delete this file.';
    }
}
