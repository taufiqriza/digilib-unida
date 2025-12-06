<?php
/**
 * Branch Management - Add/Edit Branch Form
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

if (!isSuperAdmin()) {
    die('<div class="alert alert-danger">Access denied. Super Admin only.</div>');
}

$branch = null;
$isEdit = false;

// Load existing branch for edit
if (isset($_GET['id'])) {
    $stmt = $dbs->prepare("SELECT * FROM branches WHERE branch_id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    $isEdit = (bool)$branch;
}

// Handle form submission
if (isset($_POST['save'])) {
    $data = [
        'branch_code' => trim($_POST['branch_code']),
        'branch_name' => trim($_POST['branch_name']),
        'branch_address' => trim($_POST['branch_address']),
        'branch_city' => trim($_POST['branch_city']),
        'branch_phone' => trim($_POST['branch_phone']),
        'branch_email' => trim($_POST['branch_email']),
        'branch_subdomain' => trim($_POST['branch_subdomain']) ?: null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if ($isEdit) {
        $sql = "UPDATE branches SET branch_code=?, branch_name=?, branch_address=?, branch_city=?, 
                branch_phone=?, branch_email=?, branch_subdomain=?, is_active=? WHERE branch_id=?";
        $stmt = $dbs->prepare($sql);
        $stmt->execute([...array_values($data), $branch['branch_id']]);
        $msg = 'Branch updated successfully';
    } else {
        $sql = "INSERT INTO branches (branch_code, branch_name, branch_address, branch_city, 
                branch_phone, branch_email, branch_subdomain, is_active) VALUES (?,?,?,?,?,?,?,?)";
        $stmt = $dbs->prepare($sql);
        $stmt->execute(array_values($data));
        $msg = 'Branch added successfully';
    }
    
    echo '<script>alert("'.$msg.'"); location.href = "'.MWB.'branch_management/index.php";</script>';
    exit;
}
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo $isEdit ? __('Edit Branch') : __('Add New Branch'); ?></h2>
        </div>
        <div class="sub_section">
            <a href="<?php echo MWB; ?>branch_management/index.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> <?php echo __('Back to List'); ?>
            </a>
        </div>
    </div>
</div>

<form method="post" class="form-horizontal">
    <div class="card">
        <div class="card-body">
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Branch Code'); ?> *</label>
                <div class="col-sm-4">
                    <input type="text" name="branch_code" class="form-control" required
                           value="<?php echo htmlspecialchars($branch['branch_code'] ?? ''); ?>"
                           placeholder="e.g. CAB-A" maxlength="20">
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Branch Name'); ?> *</label>
                <div class="col-sm-9">
                    <input type="text" name="branch_name" class="form-control" required
                           value="<?php echo htmlspecialchars($branch['branch_name'] ?? ''); ?>"
                           placeholder="e.g. Perpustakaan Cabang A">
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Address'); ?></label>
                <div class="col-sm-9">
                    <textarea name="branch_address" class="form-control" rows="2"><?php echo htmlspecialchars($branch['branch_address'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('City'); ?></label>
                <div class="col-sm-4">
                    <input type="text" name="branch_city" class="form-control"
                           value="<?php echo htmlspecialchars($branch['branch_city'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Phone'); ?></label>
                <div class="col-sm-4">
                    <input type="text" name="branch_phone" class="form-control"
                           value="<?php echo htmlspecialchars($branch['branch_phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Email'); ?></label>
                <div class="col-sm-4">
                    <input type="email" name="branch_email" class="form-control"
                           value="<?php echo htmlspecialchars($branch['branch_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Subdomain'); ?></label>
                <div class="col-sm-4">
                    <input type="text" name="branch_subdomain" class="form-control"
                           value="<?php echo htmlspecialchars($branch['branch_subdomain'] ?? ''); ?>"
                           placeholder="e.g. cabang-a">
                    <small class="text-muted">For access via cabang-a.yourdomain.com</small>
                </div>
            </div>
            
            <div class="form-group row">
                <label class="col-sm-3 col-form-label"><?php echo __('Status'); ?></label>
                <div class="col-sm-9">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                               <?php echo ($branch['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active"><?php echo __('Active'); ?></label>
                    </div>
                </div>
            </div>
            
            <div class="form-group row">
                <div class="col-sm-9 offset-sm-3">
                    <button type="submit" name="save" class="btn btn-primary">
                        <i class="fa fa-save"></i> <?php echo __('Save'); ?>
                    </button>
                    <a href="<?php echo MWB; ?>branch_management/index.php" class="btn btn-secondary">
                        <?php echo __('Cancel'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>
