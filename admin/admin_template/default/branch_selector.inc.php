<?php
/**
 * Branch Selector for Super Admin
 * Allows switching between branches in multi-branch setup
 */

if (function_exists('isSuperAdmin') && isSuperAdmin()) {
    $branches = getAllBranches();
    if (count($branches) > 1):
        $currentBranchId = getCurrentBranchId();
?>
<div class="branch-selector">
    <form method="get" id="branchSwitchForm">
        <select name="branch" onchange="switchBranch(this.value)" class="branch-select">
            <?php foreach ($branches as $branch): ?>
            <option value="<?php echo $branch['branch_id']; ?>" <?php echo $branch['branch_id'] == $currentBranchId ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($branch['branch_name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<script>
function switchBranch(branchId) {
    const url = new URL(window.location.href);
    url.searchParams.set('switch_branch', branchId);
    window.location.href = url.toString();
}
</script>
<?php 
    endif;
}
?>
