<?php
/**
 * Branch Management - Add/Edit Form
 */
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}
if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}
require SB . 'admin/default/session_check.inc.php';

$branch = null;
$isEdit = false;

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $q = $dbs->query("SELECT * FROM branches WHERE branch_id = $id");
    $branch = $q->fetch_assoc();
    $isEdit = (bool)$branch;
}

// Save
if (isset($_POST['save'])) {
    $code = $dbs->real_escape_string(trim($_POST['code']));
    $name = $dbs->real_escape_string(trim($_POST['name']));
    $address = $dbs->real_escape_string(trim($_POST['address']));
    $city = $dbs->real_escape_string(trim($_POST['city']));
    $phone = $dbs->real_escape_string(trim($_POST['phone']));
    $email = $dbs->real_escape_string(trim($_POST['email']));
    $active = isset($_POST['active']) ? 1 : 0;
    
    if (empty($code) || empty($name)) {
        utility::jsToastr(__('Error'), __('Kode dan Nama wajib diisi'), 'error');
    } else {
        if ($isEdit) {
            $dbs->query("UPDATE branches SET branch_code='$code', branch_name='$name', branch_address='$address', 
                         branch_city='$city', branch_phone='$phone', branch_email='$email', is_active=$active 
                         WHERE branch_id={$branch['branch_id']}");
            utility::jsToastr(__('Cabang'), __('Data berhasil diperbarui'), 'success');
        } else {
            $dbs->query("INSERT INTO branches (branch_code, branch_name, branch_address, branch_city, branch_phone, branch_email, is_active) 
                         VALUES ('$code', '$name', '$address', '$city', '$phone', '$email', $active)");
            utility::jsToastr(__('Cabang'), __('Cabang baru berhasil ditambahkan'), 'success');
        }
        echo '<script>parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'branch_management/index.php\');parent.$.colorbox.close();</script>';
        exit;
    }
}
?>
<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title"><h2><?php echo $isEdit ? __('Edit Cabang') : __('Tambah Cabang Baru'); ?></h2></div>
    </div>
</div>

<form method="post" style="padding:15px">
    <div class="form-group row">
        <label class="col-3"><?php echo __('Kode Cabang'); ?> *</label>
        <div class="col-4">
            <input type="text" name="code" class="form-control" required maxlength="20" 
                   value="<?php echo htmlspecialchars($branch['branch_code'] ?? ''); ?>" placeholder="CBG-001">
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Nama Cabang'); ?> *</label>
        <div class="col-9">
            <input type="text" name="name" class="form-control" required 
                   value="<?php echo htmlspecialchars($branch['branch_name'] ?? ''); ?>" placeholder="Perpustakaan Cabang A">
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Alamat'); ?></label>
        <div class="col-9">
            <textarea name="address" class="form-control" rows="2" placeholder="Alamat lengkap"><?php echo htmlspecialchars($branch['branch_address'] ?? ''); ?></textarea>
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Kota'); ?></label>
        <div class="col-5">
            <input type="text" name="city" class="form-control" 
                   value="<?php echo htmlspecialchars($branch['branch_city'] ?? ''); ?>" placeholder="Jakarta">
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Telepon'); ?></label>
        <div class="col-5">
            <input type="text" name="phone" class="form-control" 
                   value="<?php echo htmlspecialchars($branch['branch_phone'] ?? ''); ?>" placeholder="021-1234567">
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Email'); ?></label>
        <div class="col-5">
            <input type="email" name="email" class="form-control" 
                   value="<?php echo htmlspecialchars($branch['branch_email'] ?? ''); ?>" placeholder="cabang@perpustakaan.id">
        </div>
    </div>
    
    <div class="form-group row">
        <label class="col-3"><?php echo __('Status'); ?></label>
        <div class="col-9">
            <label class="form-check-label">
                <input type="checkbox" name="active" value="1" <?php echo ($branch['is_active'] ?? 1) ? 'checked' : ''; ?>>
                Aktif
            </label>
        </div>
    </div>
    
    <hr>
    <div class="form-group row">
        <div class="col-9 offset-3">
            <button type="submit" name="save" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo __('Simpan'); ?></button>
            <button type="button" class="btn btn-default" onclick="parent.$.colorbox.close()"><?php echo __('Batal'); ?></button>
        </div>
    </div>
</form>
