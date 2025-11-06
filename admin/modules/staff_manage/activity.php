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
$page_title = 'Staff Activity Log';

// query to get activity logs
$activity_q = $dbs->query("SELECT * FROM staff_activity_log ORDER BY timestamp DESC");

?>

<style>
    .activity-log-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    .activity-log-table th {
        background-color: #f9fafb;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #4b5563;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
    .activity-log-table td {
        background-color: #ffffff;
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
    }
    .activity-log-table tbody tr {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .activity-log-table tbody tr:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    .badge {
        display: inline-block;
        padding: .25em .4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem;
    }
    .badge-info {
        color: #fff;
        background-color: #3B82F6;
    }
    .badge-secondary {
        color: #fff;
        background-color: #6B7280;
    }
</style>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2><?php echo __('Staff Activity Log'); ?></h2>
        </div>
        <div class="sub_section">
            <table class="activity-log-table">
                <thead>
                    <tr>
                        <th><?php echo __('Timestamp'); ?></th>
                        <th><?php echo __('Staff Name'); ?></th>
                        <th><?php echo __('Action'); ?></th>
                        <th><?php echo __('Location'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($activity_q->num_rows > 0) : ?>
                        <?php while ($log = $activity_q->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo date('d M Y, H:i:s', strtotime($log['timestamp'])); ?></td>
                                <td><?php echo htmlspecialchars($log['staff_name']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($log['location']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4" style="text-align: center;"><?php echo __('No activity logs found. Run the setup script to insert dummy data.'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>