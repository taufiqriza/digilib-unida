<?php
/**
 * Branch Management - Statistics
 */

if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}

require SB . 'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('branch_management', 'r');
if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// Get all branches stats
$sql = "SELECT b.*, 
    (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_biblio,
    (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_item,
    (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_member,
    (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND is_return = 0) as active_loans,
    (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND DATE(loan_date) = CURDATE()) as loans_today
    FROM branches b WHERE b.is_active = 1 ORDER BY b.branch_name";
$result = $dbs->query($sql);

$branches = [];
$totals = ['biblio' => 0, 'item' => 0, 'member' => 0, 'loans' => 0, 'today' => 0];
while ($b = $result->fetch_assoc()) {
    $branches[] = $b;
    $totals['biblio'] += $b['total_biblio'];
    $totals['item'] += $b['total_item'];
    $totals['member'] += $b['total_member'];
    $totals['loans'] += $b['active_loans'];
    $totals['today'] += $b['loans_today'];
}
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo __('Branch Statistics'); ?></h2>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row" style="margin: 0 0 20px 0;">
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo count($branches); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-building"></i> <?php echo __('Branches'); ?></div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($totals['biblio']); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-book"></i> <?php echo __('Titles'); ?></div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($totals['item']); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-copy"></i> <?php echo __('Items'); ?></div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($totals['member']); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-users"></i> <?php echo __('Members'); ?></div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($totals['loans']); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-exchange-alt"></i> <?php echo __('Active Loans'); ?></div>
        </div>
    </div>
    <div class="col-md-2 col-sm-4 col-6" style="padding: 5px;">
        <div class="stat-card" style="background: linear-gradient(135deg, #5ee7df 0%, #b490ca 100%); color: #fff; padding: 20px; border-radius: 10px; text-align: center;">
            <div style="font-size: 28px; font-weight: bold;"><?php echo number_format($totals['today']); ?></div>
            <div style="font-size: 12px; opacity: 0.9;"><i class="fa fa-calendar-day"></i> <?php echo __('Today'); ?></div>
        </div>
    </div>
</div>

<!-- Branch Details Table -->
<div class="s-table-container">
    <table class="s-table table table-hover" id="dataList">
        <thead>
            <tr>
                <th><?php echo __('Branch'); ?></th>
                <th class="text-center" width="12%"><?php echo __('Titles'); ?></th>
                <th class="text-center" width="12%"><?php echo __('Items'); ?></th>
                <th class="text-center" width="12%"><?php echo __('Members'); ?></th>
                <th class="text-center" width="12%"><?php echo __('Active Loans'); ?></th>
                <th class="text-center" width="10%"><?php echo __('Today'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($branches as $b): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($b['branch_name']); ?></strong>
                    <?php if (!empty($b['is_main_branch'])): ?>
                        <span class="badge badge-primary ml-1">Main</span>
                    <?php endif; ?>
                    <?php if ($b['branch_city']): ?>
                        <br><small class="text-muted"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($b['branch_city']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge badge-info"><?php echo number_format($b['total_biblio']); ?></span></td>
                <td class="text-center"><span class="badge badge-warning"><?php echo number_format($b['total_item']); ?></span></td>
                <td class="text-center"><span class="badge badge-success"><?php echo number_format($b['total_member']); ?></span></td>
                <td class="text-center"><span class="badge badge-danger"><?php echo number_format($b['active_loans']); ?></span></td>
                <td class="text-center"><span class="badge badge-secondary"><?php echo number_format($b['loans_today']); ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot style="background: #f8f9fa; font-weight: bold;">
            <tr>
                <td><strong><?php echo __('TOTAL'); ?></strong></td>
                <td class="text-center"><?php echo number_format($totals['biblio']); ?></td>
                <td class="text-center"><?php echo number_format($totals['item']); ?></td>
                <td class="text-center"><?php echo number_format($totals['member']); ?></td>
                <td class="text-center"><?php echo number_format($totals['loans']); ?></td>
                <td class="text-center"><?php echo number_format($totals['today']); ?></td>
            </tr>
        </tfoot>
    </table>
</div>
