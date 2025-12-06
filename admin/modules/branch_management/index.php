<?php
/**
 * Branch Management - Branch List
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
$can_write = utility::havePrivilege('branch_management', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

// Delete action
if (isset($_POST['delete']) && isset($_POST['branch_id']) && $can_write) {
    $branchId = (int)$_POST['branch_id'];
    if ($branchId > 1) {
        $dbs->query("UPDATE branches SET is_active = 0 WHERE branch_id = $branchId");
        utility::jsToastr('Branch', __('Branch deactivated successfully'), 'success');
        echo '<script>parent.$(\'#mainContent\').simbioAJAX(\'' . $_SERVER['PHP_SELF'] . '\');</script>';
        exit;
    }
}

// Get all branches
$result = $dbs->query("SELECT b.*, 
    (SELECT COUNT(*) FROM biblio WHERE branch_id = b.branch_id) as total_biblio,
    (SELECT COUNT(*) FROM member WHERE branch_id = b.branch_id) as total_member,
    (SELECT COUNT(*) FROM item WHERE branch_id = b.branch_id) as total_item
    FROM branches b ORDER BY b.is_main_branch DESC, b.branch_name");
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo __('Branch Management'); ?></h2>
        </div>
        <div class="sub_section">
            <div class="btn-group">
                <a href="<?php echo MWB; ?>branch_management/branch_form.php" class="btn btn-primary openPopUp" title="<?php echo __('Add New Branch'); ?>">
                    <i class="fa fa-plus"></i> <?php echo __('Add New Branch'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="s-table-container">
    <table class="s-table table table-hover" id="dataList">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="10%"><?php echo __('Code'); ?></th>
                <th><?php echo __('Branch Name'); ?></th>
                <th width="10%" class="text-center"><?php echo __('Biblio'); ?></th>
                <th width="10%" class="text-center"><?php echo __('Items'); ?></th>
                <th width="10%" class="text-center"><?php echo __('Members'); ?></th>
                <th width="8%" class="text-center"><?php echo __('Status'); ?></th>
                <th width="12%" class="text-center"><?php echo __('Action'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1; 
            while ($branch = $result->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($branch['branch_code']); ?></span></td>
                <td>
                    <strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong>
                    <?php if (!empty($branch['is_main_branch'])): ?>
                        <span class="badge badge-primary ml-1">Main</span>
                    <?php endif; ?>
                    <?php if ($branch['branch_city']): ?>
                        <br><small class="text-muted"><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($branch['branch_city']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge badge-info"><?php echo number_format($branch['total_biblio']); ?></span></td>
                <td class="text-center"><span class="badge badge-warning"><?php echo number_format($branch['total_item']); ?></span></td>
                <td class="text-center"><span class="badge badge-success"><?php echo number_format($branch['total_member']); ?></span></td>
                <td class="text-center">
                    <?php if ($branch['is_active']): ?>
                        <span class="badge badge-success"><i class="fa fa-check"></i> Active</span>
                    <?php else: ?>
                        <span class="badge badge-secondary"><i class="fa fa-times"></i> Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <a href="<?php echo MWB; ?>branch_management/branch_form.php?id=<?php echo $branch['branch_id']; ?>" 
                       class="btn btn-sm btn-warning openPopUp" title="<?php echo __('Edit'); ?>">
                        <i class="fa fa-edit"></i>
                    </a>
                    <?php if (empty($branch['is_main_branch']) && $can_write): ?>
                    <button class="btn btn-sm btn-danger" 
                            onclick="confirmDelete(<?php echo $branch['branch_id']; ?>, '<?php echo addslashes($branch['branch_name']); ?>')" 
                            title="<?php echo __('Deactivate'); ?>">
                        <i class="fa fa-power-off"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<form id="deleteForm" method="post" style="display:none">
    <input type="hidden" name="branch_id" id="deleteBranchId">
    <input type="hidden" name="delete" value="1">
</form>

<script>
function confirmDelete(id, name) {
    if (confirm('<?php echo __('Deactivate branch'); ?> "' + name + '"?')) {
        document.getElementById('deleteBranchId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
