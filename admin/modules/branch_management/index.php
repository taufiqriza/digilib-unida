<?php
/**
 * Branch Management - Branch List
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

// Only super admin can access
if (!isSuperAdmin()) {
    die('<div class="alert alert-danger">Access denied. Super Admin only.</div>');
}

// Delete action
if (isset($_POST['delete']) && isset($_POST['branch_id'])) {
    $branchId = (int)$_POST['branch_id'];
    if ($branchId > 1) { // Cannot delete main branch
        $stmt = $dbs->prepare("UPDATE branches SET is_active = 0 WHERE branch_id = ?");
        $stmt->execute([$branchId]);
        echo '<script>alert("Branch deactivated successfully"); location.href = "'.MWB.'branch_management/index.php";</script>';
    }
}

// Get all branches
$stmt = $dbs->query("SELECT b.*, 
    (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_biblio,
    (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_member,
    (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_item
    FROM branches b ORDER BY b.is_main_branch DESC, b.branch_name");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo __('Branch Management'); ?></h2>
        </div>
        <div class="sub_section">
            <div class="btn-group">
                <a href="<?php echo MWB; ?>branch_management/branch_form.php" class="btn btn-primary">
                    <i class="fa fa-plus"></i> <?php echo __('Add New Branch'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%"><?php echo __('Code'); ?></th>
                <th><?php echo __('Branch Name'); ?></th>
                <th width="10%"><?php echo __('Biblio'); ?></th>
                <th width="10%"><?php echo __('Items'); ?></th>
                <th width="10%"><?php echo __('Members'); ?></th>
                <th width="8%"><?php echo __('Status'); ?></th>
                <th width="15%"><?php echo __('Action'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($branches as $branch): ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><code><?php echo htmlspecialchars($branch['branch_code']); ?></code></td>
                <td>
                    <strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong>
                    <?php if ($branch['is_main_branch']): ?>
                        <span class="badge badge-primary">Main</span>
                    <?php endif; ?>
                    <?php if ($branch['branch_city']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($branch['branch_city']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo number_format($branch['total_biblio']); ?></td>
                <td class="text-center"><?php echo number_format($branch['total_item']); ?></td>
                <td class="text-center"><?php echo number_format($branch['total_member']); ?></td>
                <td class="text-center">
                    <?php if ($branch['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo MWB; ?>branch_management/branch_form.php?id=<?php echo $branch['branch_id']; ?>" 
                       class="btn btn-sm btn-warning" title="Edit">
                        <i class="fa fa-edit"></i>
                    </a>
                    <a href="<?php echo MWB; ?>branch_management/statistics.php?id=<?php echo $branch['branch_id']; ?>" 
                       class="btn btn-sm btn-info" title="Statistics">
                        <i class="fa fa-chart-bar"></i>
                    </a>
                    <?php if (!$branch['is_main_branch']): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Deactivate this branch?')">
                        <input type="hidden" name="branch_id" value="<?php echo $branch['branch_id']; ?>">
                        <button type="submit" name="delete" class="btn btn-sm btn-danger" title="Deactivate">
                            <i class="fa fa-times"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
