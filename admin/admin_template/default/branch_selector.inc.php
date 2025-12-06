<?php
/**
 * Branch Selector for Super Admin
 */
if (function_exists('isSuperAdmin') && isSuperAdmin() && defined('CURRENT_BRANCH_ID')): ?>
<style>
#branchSelector { position: fixed; right: 20px; top: 8px; z-index: 9999; }
#branchSelector select { width: 180px; font-size: 12px; padding: 4px 8px; border-radius: 4px; border: 1px solid #ccc; background: #fff; }
</style>
<div id="branchSelector">
    <select id="switchBranch" onchange="switchBranch(this.value)">
        <?php foreach (getAllBranches() as $b): ?>
        <option value="<?php echo $b['branch_id']; ?>" <?php echo $b['branch_id'] == CURRENT_BRANCH_ID ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($b['branch_name']); ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>
<script>function switchBranch(id){var u=new URL(location.href);u.searchParams.set('branch',id);location.href=u;}</script>
<?php endif; ?>
