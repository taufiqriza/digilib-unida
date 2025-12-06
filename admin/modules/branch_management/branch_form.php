<?php
/**
 * Branch Management - Add/Edit Branch Form
 */

if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}

require SB . 'admin/default/session_check.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';

$can_write = utility::havePrivilege('branch_management', 'w');
if (!$can_write) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

$branch = null;
$isEdit = false;
$pageTitle = __('Add New Branch');

// Load existing branch for edit
if (isset($_GET['id'])) {
    $branchId = (int)$_GET['id'];
    $result = $dbs->query("SELECT * FROM branches WHERE branch_id = $branchId");
    $branch = $result->fetch_assoc();
    $isEdit = (bool)$branch;
    $pageTitle = __('Edit Branch');
}

// Handle form submission
if (isset($_POST['saveData'])) {
    $data = [
        'branch_code' => $dbs->real_escape_string(trim($_POST['branch_code'])),
        'branch_name' => $dbs->real_escape_string(trim($_POST['branch_name'])),
        'branch_address' => $dbs->real_escape_string(trim($_POST['branch_address'])),
        'branch_city' => $dbs->real_escape_string(trim($_POST['branch_city'])),
        'branch_phone' => $dbs->real_escape_string(trim($_POST['branch_phone'])),
        'branch_email' => $dbs->real_escape_string(trim($_POST['branch_email'])),
        'branch_subdomain' => $dbs->real_escape_string(trim($_POST['branch_subdomain'])),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if ($isEdit) {
        $sql = "UPDATE branches SET 
                branch_code='{$data['branch_code']}', branch_name='{$data['branch_name']}', 
                branch_address='{$data['branch_address']}', branch_city='{$data['branch_city']}', 
                branch_phone='{$data['branch_phone']}', branch_email='{$data['branch_email']}', 
                branch_subdomain='{$data['branch_subdomain']}', is_active={$data['is_active']} 
                WHERE branch_id={$branch['branch_id']}";
        $dbs->query($sql);
        utility::jsToastr('Branch', __('Branch updated successfully'), 'success');
    } else {
        $sql = "INSERT INTO branches (branch_code, branch_name, branch_address, branch_city, 
                branch_phone, branch_email, branch_subdomain, is_active) 
                VALUES ('{$data['branch_code']}','{$data['branch_name']}','{$data['branch_address']}',
                '{$data['branch_city']}','{$data['branch_phone']}','{$data['branch_email']}',
                '{$data['branch_subdomain']}',{$data['is_active']})";
        $dbs->query($sql);
        utility::jsToastr('Branch', __('Branch added successfully'), 'success');
    }
    
    echo '<script>parent.$(\'#mainContent\').simbioAJAX(\'' . MWB . 'branch_management/index.php\');</script>';
    exit;
}
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo $pageTitle; ?></h2>
        </div>
        <div class="sub_section">
            <div class="btn-group">
                <a href="<?php echo MWB; ?>branch_management/index.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo __('Back'); ?></a>
            </div>
        </div>
    </div>
</div>

<form name="mainForm" id="mainForm" method="post" class="form-horizontal">
    <div class="s-form-wrapper">
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Branch Code'); ?> <span class="text-danger">*</span></label>
            <div class="col-sm-3">
                <input type="text" name="branch_code" class="form-control" required maxlength="20"
                       value="<?php echo htmlspecialchars($branch['branch_code'] ?? ''); ?>" 
                       placeholder="e.g. BR-001">
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Branch Name'); ?> <span class="text-danger">*</span></label>
            <div class="col-sm-9">
                <input type="text" name="branch_name" class="form-control" required
                       value="<?php echo htmlspecialchars($branch['branch_name'] ?? ''); ?>"
                       placeholder="<?php echo __('Enter branch name'); ?>">
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Address'); ?></label>
            <div class="col-sm-9">
                <textarea name="branch_address" class="form-control" rows="2" 
                          placeholder="<?php echo __('Enter address'); ?>"><?php echo htmlspecialchars($branch['branch_address'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('City'); ?></label>
            <div class="col-sm-4">
                <input type="text" name="branch_city" class="form-control"
                       value="<?php echo htmlspecialchars($branch['branch_city'] ?? ''); ?>"
                       placeholder="<?php echo __('Enter city'); ?>">
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Phone'); ?></label>
            <div class="col-sm-4">
                <input type="text" name="branch_phone" class="form-control"
                       value="<?php echo htmlspecialchars($branch['branch_phone'] ?? ''); ?>"
                       placeholder="e.g. +62-21-1234567">
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Email'); ?></label>
            <div class="col-sm-4">
                <input type="email" name="branch_email" class="form-control"
                       value="<?php echo htmlspecialchars($branch['branch_email'] ?? ''); ?>"
                       placeholder="e.g. branch@library.com">
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Subdomain'); ?></label>
            <div class="col-sm-4">
                <input type="text" name="branch_subdomain" class="form-control"
                       value="<?php echo htmlspecialchars($branch['branch_subdomain'] ?? ''); ?>"
                       placeholder="e.g. cabang-a">
                <small class="form-text text-muted"><?php echo __('For access via subdomain (cabang-a.yourdomain.com)'); ?></small>
            </div>
        </div>
        
        <div class="form-group row">
            <label class="col-sm-3 col-form-label"><?php echo __('Status'); ?></label>
            <div class="col-sm-9">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" name="is_active" class="custom-control-input" id="is_active"
                           <?php echo ($branch['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="custom-control-label" for="is_active"><?php echo __('Active'); ?></label>
                </div>
            </div>
        </div>
        
        <div class="form-group row">
            <div class="col-sm-9 offset-sm-3">
                <button type="submit" name="saveData" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo __('Save'); ?></button>
                <button type="button" class="btn btn-default" onclick="parent.$('#mainContent').simbioAJAX('<?php echo MWB; ?>branch_management/index.php')"><?php echo __('Cancel'); ?></button>
            </div>
        </div>
    </div>
</form>
