<?php
/**
 * Branch Management - Statistics
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

if (!isSuperAdmin()) {
    die('<div class="alert alert-danger">Access denied. Super Admin only.</div>');
}

// Get all branches stats
$sql = "SELECT b.*, 
    (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_biblio,
    (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_item,
    (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_member,
    (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND is_return = 0) as active_loans,
    (SELECT COUNT(*) FROM loan WHERE branch_id = b.branch_id AND DATE(loan_date) = CURDATE()) as loans_today
    FROM branches b WHERE b.is_active = 1 ORDER BY b.branch_name";
$stmt = $dbs->query($sql);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = ['biblio' => 0, 'item' => 0, 'member' => 0, 'loans' => 0, 'today' => 0];
foreach ($branches as $b) {
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
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h3><?php echo count($branches); ?></h3>
                <p class="mb-0"><?php echo __('Branches'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($totals['biblio']); ?></h3>
                <p class="mb-0"><?php echo __('Total Titles'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($totals['item']); ?></h3>
                <p class="mb-0"><?php echo __('Total Items'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($totals['member']); ?></h3>
                <p class="mb-0"><?php echo __('Total Members'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($totals['loans']); ?></h3>
                <p class="mb-0"><?php echo __('Active Loans'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h3><?php echo number_format($totals['today']); ?></h3>
                <p class="mb-0"><?php echo __('Loans Today'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Branch Details Table -->
<div class="card">
    <div class="card-header">
        <h5><?php echo __('Statistics per Branch'); ?></h5>
    </div>
    <div class="card-body">
        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th><?php echo __('Branch'); ?></th>
                    <th class="text-center"><?php echo __('Titles'); ?></th>
                    <th class="text-center"><?php echo __('Items'); ?></th>
                    <th class="text-center"><?php echo __('Members'); ?></th>
                    <th class="text-center"><?php echo __('Active Loans'); ?></th>
                    <th class="text-center"><?php echo __('Today'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($b['branch_name']); ?></strong>
                        <?php if ($b['is_main_branch']): ?>
                            <span class="badge badge-primary">Main</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo number_format($b['total_biblio']); ?></td>
                    <td class="text-center"><?php echo number_format($b['total_item']); ?></td>
                    <td class="text-center"><?php echo number_format($b['total_member']); ?></td>
                    <td class="text-center"><?php echo number_format($b['active_loans']); ?></td>
                    <td class="text-center"><?php echo number_format($b['loans_today']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="thead-light">
                <tr>
                    <th><?php echo __('TOTAL'); ?></th>
                    <th class="text-center"><?php echo number_format($totals['biblio']); ?></th>
                    <th class="text-center"><?php echo number_format($totals['item']); ?></th>
                    <th class="text-center"><?php echo number_format($totals['member']); ?></th>
                    <th class="text-center"><?php echo number_format($totals['loans']); ?></th>
                    <th class="text-center"><?php echo number_format($totals['today']); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
