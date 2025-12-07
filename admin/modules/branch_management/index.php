<?php
/**
 * Branch Management - Dashboard
 */
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}
if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}
require SB . 'admin/default/session_check.inc.php';

// Actions
if (isset($_POST['action']) && $_POST['action'] === 'toggle' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($id > 1) {
        $dbs->query("UPDATE branches SET is_active = NOT is_active WHERE branch_id = $id AND is_main_branch = 0");
        utility::jsToastr(__('Branch'), __('Status updated'), 'success');
        echo '<script>parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'branch_management/index.php\');</script>';
        exit;
    }
}

// Get branches with stats in single optimized query
$sql = "SELECT b.branch_id, b.branch_code, b.branch_name, b.branch_city, b.is_active, b.is_main_branch,
        COALESCE(s.total_biblio, 0) as total_biblio,
        COALESCE(s.total_item, 0) as total_item,
        COALESCE(s.total_member, 0) as total_member,
        COALESCE(s.active_loans, 0) as active_loans
        FROM branches b
        LEFT JOIN (
            SELECT branch_id,
                   SUM(CASE WHEN type='biblio' THEN cnt ELSE 0 END) as total_biblio,
                   SUM(CASE WHEN type='item' THEN cnt ELSE 0 END) as total_item,
                   SUM(CASE WHEN type='member' THEN cnt ELSE 0 END) as total_member,
                   SUM(CASE WHEN type='loan' THEN cnt ELSE 0 END) as active_loans
            FROM (
                SELECT branch_id, 'biblio' as type, COUNT(*) as cnt FROM biblio GROUP BY branch_id
                UNION ALL SELECT branch_id, 'item', COUNT(*) FROM item GROUP BY branch_id
                UNION ALL SELECT branch_id, 'member', COUNT(*) FROM member GROUP BY branch_id
                UNION ALL SELECT branch_id, 'loan', COUNT(*) FROM loan WHERE is_return=0 GROUP BY branch_id
            ) stats GROUP BY branch_id
        ) s ON s.branch_id = b.branch_id
        ORDER BY b.is_main_branch DESC, b.branch_name";
$branches = $dbs->query($sql);

// Calculate totals
$totals = ['branches' => 0, 'biblio' => 0, 'item' => 0, 'member' => 0, 'loans' => 0];
$branchList = [];
while ($row = $branches->fetch_assoc()) {
    $branchList[] = $row;
    if ($row['is_active']) {
        $totals['branches']++;
        $totals['biblio'] += $row['total_biblio'];
        $totals['item'] += $row['total_item'];
        $totals['member'] += $row['total_member'];
        $totals['loans'] += $row['active_loans'];
    }
}
?>
<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title"><h2><?php echo __('Manajemen Cabang'); ?></h2></div>
        <div class="sub_section">
            <a href="<?php echo MWB; ?>branch_management/branch_form.php" class="btn btn-primary notAJAX openPopUp" title="<?php echo __('Tambah Cabang'); ?>">
                <i class="fa fa-plus"></i> <?php echo __('Tambah Cabang'); ?>
            </a>
        </div>
    </div>
</div>

<div style="padding:10px">
<!-- Summary Cards -->
<div class="row mb-3">
    <div class="col-6 col-md-3 mb-2">
        <div style="background:#4f46e5;color:#fff;padding:15px;border-radius:8px;text-align:center">
            <div style="font-size:24px;font-weight:700"><?php echo $totals['branches']; ?></div>
            <div style="font-size:12px;opacity:.8"><i class="fa fa-building"></i> Cabang Aktif</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div style="background:#059669;color:#fff;padding:15px;border-radius:8px;text-align:center">
            <div style="font-size:24px;font-weight:700"><?php echo number_format($totals['biblio']); ?></div>
            <div style="font-size:12px;opacity:.8"><i class="fa fa-book"></i> Total Judul</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div style="background:#0891b2;color:#fff;padding:15px;border-radius:8px;text-align:center">
            <div style="font-size:24px;font-weight:700"><?php echo number_format($totals['item']); ?></div>
            <div style="font-size:12px;opacity:.8"><i class="fa fa-copy"></i> Total Eksemplar</div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-2">
        <div style="background:#dc2626;color:#fff;padding:15px;border-radius:8px;text-align:center">
            <div style="font-size:24px;font-weight:700"><?php echo number_format($totals['loans']); ?></div>
            <div style="font-size:12px;opacity:.8"><i class="fa fa-exchange"></i> Peminjaman Aktif</div>
        </div>
    </div>
</div>

<!-- Branch List -->
<div class="s-table-container">
<table class="s-table table table-hover table-sm">
    <thead>
        <tr>
            <th width="8%">Kode</th>
            <th>Nama Cabang</th>
            <th class="text-center" width="10%">Judul</th>
            <th class="text-center" width="10%">Eksemplar</th>
            <th class="text-center" width="10%">Anggota</th>
            <th class="text-center" width="10%">Pinjaman</th>
            <th class="text-center" width="8%">Status</th>
            <th class="text-center" width="10%">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($branchList as $b): ?>
        <tr<?php echo !$b['is_active'] ? ' class="text-muted"' : ''; ?>>
            <td><code><?php echo htmlspecialchars($b['branch_code']); ?></code></td>
            <td>
                <strong><?php echo htmlspecialchars($b['branch_name']); ?></strong>
                <?php if ($b['is_main_branch']): ?><span class="badge badge-primary">Pusat</span><?php endif; ?>
                <?php if ($b['branch_city']): ?><br><small class="text-muted"><?php echo htmlspecialchars($b['branch_city']); ?></small><?php endif; ?>
            </td>
            <td class="text-center"><?php echo number_format($b['total_biblio']); ?></td>
            <td class="text-center"><?php echo number_format($b['total_item']); ?></td>
            <td class="text-center"><?php echo number_format($b['total_member']); ?></td>
            <td class="text-center"><?php echo $b['active_loans'] ? '<span class="badge badge-warning">'.$b['active_loans'].'</span>' : '-'; ?></td>
            <td class="text-center">
                <?php if ($b['is_active']): ?>
                    <span class="badge badge-success">Aktif</span>
                <?php else: ?>
                    <span class="badge badge-secondary">Nonaktif</span>
                <?php endif; ?>
            </td>
            <td class="text-center">
                <a href="<?php echo MWB; ?>branch_management/branch_form.php?id=<?php echo $b['branch_id']; ?>" class="btn btn-xs btn-warning notAJAX openPopUp" title="Edit"><i class="fa fa-edit"></i></a>
                <?php if (!$b['is_main_branch']): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('<?php echo $b['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?> cabang ini?')">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo $b['branch_id']; ?>">
                    <button type="submit" class="btn btn-xs btn-<?php echo $b['is_active'] ? 'danger' : 'success'; ?>" title="<?php echo $b['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                        <i class="fa fa-power-off"></i>
                    </button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>
