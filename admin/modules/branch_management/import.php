<?php
/**
 * Branch Management - Import Data from Other SLiMS
 */

if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}

require SB . 'admin/default/session_check.inc.php';

$can_write = utility::havePrivilege('branch_management', 'w');
if (!$can_write) {
    die('<div class="errorBox">' . __('You are not authorized to view this section') . '</div>');
}

$branches = getAllBranches();
$message = '';
$error = '';

if (isset($_POST['test_connection'])) {
    $host = trim($_POST['source_host']);
    $dbname = trim($_POST['source_db']);
    $user = trim($_POST['source_user']);
    $pass = $_POST['source_pass'];
    $port = (int)($_POST['source_port'] ?: 3306);
    
    $testDb = @new mysqli($host, $user, $pass, $dbname, $port);
    if ($testDb->connect_error) {
        $error = "Connection failed: " . $testDb->connect_error;
    } else {
        $counts = [];
        foreach (['biblio', 'item', 'member', 'loan'] as $t) {
            $r = $testDb->query("SELECT COUNT(*) as c FROM {$t}");
            $counts[$t] = $r ? $r->fetch_assoc()['c'] : 0;
        }
        $message = "Connection successful! Found: " . number_format($counts['biblio']) . " biblio, " .
            number_format($counts['item']) . " items, " . number_format($counts['member']) . " members";
        $_SESSION['import_source'] = compact('host', 'dbname', 'user', 'pass', 'port', 'counts');
        $testDb->close();
    }
}

if (isset($_POST['start_import']) && isset($_SESSION['import_source'])) {
    $src = $_SESSION['import_source'];
    $targetBranch = (int)$_POST['target_branch'];
    
    $sourceDb = new mysqli($src['host'], $src['user'], $src['pass'], $src['dbname'], $src['port']);
    if ($sourceDb->connect_error) {
        $error = "Connection failed: " . $sourceDb->connect_error;
    } else {
        $dbs->query("INSERT INTO import_logs (branch_id, source_db, source_type, import_type, status, started_at) 
                     VALUES ($targetBranch, '{$src['dbname']}', 'database', 'full', 'running', NOW())");
        $logId = $dbs->insert_id;
        
        $imported = ['member' => 0, 'biblio' => 0, 'item' => 0];
        
        // Import Members
        $result = $sourceDb->query("SELECT * FROM member");
        while ($m = $result->fetch_assoc()) {
            $oldId = $m['member_id'];
            unset($m['member_id']);
            $m['branch_id'] = $targetBranch;
            
            $cols = implode(',', array_map(function($c) { return "`$c`"; }, array_keys($m)));
            $vals = implode(',', array_map(function($v) use ($dbs) { 
                return $v === null ? 'NULL' : "'" . $dbs->real_escape_string($v) . "'"; 
            }, array_values($m)));
            
            if ($dbs->query("INSERT INTO member ($cols) VALUES ($vals)")) {
                $newId = $dbs->insert_id;
                $dbs->query("INSERT INTO id_mapping (import_log_id, branch_id, table_name, old_id, new_id) 
                             VALUES ($logId, $targetBranch, 'member', $oldId, $newId)");
                $imported['member']++;
            }
        }
        
        // Import Biblio
        $result = $sourceDb->query("SELECT * FROM biblio");
        while ($b = $result->fetch_assoc()) {
            $oldId = $b['biblio_id'];
            unset($b['biblio_id']);
            $b['branch_id'] = $targetBranch;
            
            $cols = implode(',', array_map(function($c) { return "`$c`"; }, array_keys($b)));
            $vals = implode(',', array_map(function($v) use ($dbs) { 
                return $v === null ? 'NULL' : "'" . $dbs->real_escape_string($v) . "'"; 
            }, array_values($b)));
            
            if ($dbs->query("INSERT INTO biblio ($cols) VALUES ($vals)")) {
                $newId = $dbs->insert_id;
                $dbs->query("INSERT INTO id_mapping (import_log_id, branch_id, table_name, old_id, new_id) 
                             VALUES ($logId, $targetBranch, 'biblio', $oldId, $newId)");
                $imported['biblio']++;
            }
        }
        
        // Import Items
        $result = $sourceDb->query("SELECT * FROM item");
        while ($i = $result->fetch_assoc()) {
            $oldBiblioId = $i['biblio_id'];
            unset($i['item_id']);
            $i['branch_id'] = $targetBranch;
            
            $mapResult = $dbs->query("SELECT new_id FROM id_mapping WHERE branch_id = $targetBranch AND table_name = 'biblio' AND old_id = $oldBiblioId");
            if ($map = $mapResult->fetch_assoc()) {
                $i['biblio_id'] = $map['new_id'];
                
                $cols = implode(',', array_map(function($c) { return "`$c`"; }, array_keys($i)));
                $vals = implode(',', array_map(function($v) use ($dbs) { 
                    return $v === null ? 'NULL' : "'" . $dbs->real_escape_string($v) . "'"; 
                }, array_values($i)));
                
                if ($dbs->query("INSERT INTO item ($cols) VALUES ($vals)")) {
                    $imported['item']++;
                }
            }
        }
        
        $total = array_sum($imported);
        $dbs->query("UPDATE import_logs SET status = 'completed', records_imported = $total, completed_at = NOW() WHERE log_id = $logId");
        $message = "Import completed! {$imported['member']} members, {$imported['biblio']} biblio, {$imported['item']} items";
        unset($_SESSION['import_source']);
        $sourceDb->close();
    }
}
?>

<div class="menuBox">
    <div class="menuBoxInner masterFileIcon">
        <div class="per_title">
            <h2><?php echo __('Import Data from Other SLiMS'); ?></h2>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa fa-check-circle"></i> <?php echo $message; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fa fa-exclamation-circle"></i> <?php echo $error; ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<!-- Step 1: Connection -->
<div class="card mb-3" style="border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 10px 10px 0 0;">
        <h5 class="mb-0"><i class="fa fa-plug"></i> <?php echo __('Step 1: Connect to Source Database'); ?></h5>
    </div>
    <div class="card-body">
        <form method="post" id="connectionForm">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fa fa-server"></i> <?php echo __('Host'); ?></label>
                        <input type="text" name="source_host" class="form-control" value="localhost" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label><i class="fa fa-hashtag"></i> <?php echo __('Port'); ?></label>
                        <input type="number" name="source_port" class="form-control" value="3306">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fa fa-database"></i> <?php echo __('Database'); ?></label>
                        <input type="text" name="source_db" class="form-control" required placeholder="slims_cabang_a">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label><i class="fa fa-user"></i> <?php echo __('Username'); ?></label>
                        <input type="text" name="source_user" class="form-control" value="root" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label><i class="fa fa-key"></i> <?php echo __('Password'); ?></label>
                        <input type="password" name="source_pass" class="form-control">
                    </div>
                </div>
            </div>
            <button type="submit" name="test_connection" class="btn btn-info">
                <i class="fa fa-plug"></i> <?php echo __('Test Connection'); ?>
            </button>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['import_source'])): ?>
<!-- Step 2: Import -->
<div class="card mb-3" style="border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; border-radius: 10px 10px 0 0;">
        <h5 class="mb-0"><i class="fa fa-download"></i> <?php echo __('Step 2: Start Import'); ?></h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> 
            <strong>Source:</strong> <?php echo $_SESSION['import_source']['dbname']; ?> - 
            <?php echo number_format($_SESSION['import_source']['counts']['biblio']); ?> biblio, 
            <?php echo number_format($_SESSION['import_source']['counts']['item']); ?> items, 
            <?php echo number_format($_SESSION['import_source']['counts']['member']); ?> members
        </div>
        <form method="post" onsubmit="return confirm('<?php echo __('Start import? This may take a while.'); ?>')">
            <div class="form-group">
                <label><i class="fa fa-building"></i> <?php echo __('Target Branch'); ?></label>
                <select name="target_branch" class="form-control" style="max-width: 400px;" required>
                    <option value="">-- <?php echo __('Select Branch'); ?> --</option>
                    <?php foreach ($branches as $b): ?>
                    <option value="<?php echo $b['branch_id']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="start_import" class="btn btn-success btn-lg">
                <i class="fa fa-download"></i> <?php echo __('Start Import'); ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Import History -->
<div class="card" style="border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
    <div class="card-header" style="background: #f8f9fa; border-radius: 10px 10px 0 0;">
        <h5 class="mb-0"><i class="fa fa-history"></i> <?php echo __('Import History'); ?></h5>
    </div>
    <div class="card-body p-0">
        <table class="s-table table table-hover mb-0">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th><?php echo __('Branch'); ?></th>
                    <th><?php echo __('Source'); ?></th>
                    <th class="text-center"><?php echo __('Records'); ?></th>
                    <th class="text-center"><?php echo __('Status'); ?></th>
                    <th><?php echo __('Date'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $logs = $dbs->query("SELECT l.*, b.branch_name FROM import_logs l LEFT JOIN branches b ON b.branch_id = l.branch_id ORDER BY l.log_id DESC LIMIT 10");
            if ($logs && $logs->num_rows > 0):
                while ($log = $logs->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo $log['log_id']; ?></td>
                <td><?php echo htmlspecialchars($log['branch_name'] ?? '-'); ?></td>
                <td><code><?php echo htmlspecialchars($log['source_db']); ?></code></td>
                <td class="text-center"><span class="badge badge-info"><?php echo number_format($log['records_imported']); ?></span></td>
                <td class="text-center">
                    <?php 
                    $statusClass = $log['status'] == 'completed' ? 'success' : ($log['status'] == 'failed' ? 'danger' : 'warning');
                    $statusIcon = $log['status'] == 'completed' ? 'check' : ($log['status'] == 'failed' ? 'times' : 'spinner fa-spin');
                    ?>
                    <span class="badge badge-<?php echo $statusClass; ?>">
                        <i class="fa fa-<?php echo $statusIcon; ?>"></i> <?php echo ucfirst($log['status']); ?>
                    </span>
                </td>
                <td><small><?php echo $log['started_at']; ?></small></td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
            <tr><td colspan="6" class="text-center text-muted"><?php echo __('No import history'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
