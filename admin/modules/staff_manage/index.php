<?php
// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SB . 'admin/default/session.inc.php';
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-system');

require SB . 'admin/default/session_check.inc.php';

// privileges checking
$can_read = utility::havePrivilege('staff_manage', 'r');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// page title
$page_title = 'Staff Management';

?>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Staff Management'); ?></h2>
        </div>
        <div class="sub_section">
            <p>This is the placeholder for the Staff Management module. You can start building your module here.</p>
        </div>
    </div>
</div>