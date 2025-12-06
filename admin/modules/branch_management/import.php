<?php
/**
 * Branch Management - Import Data from Other SLiMS
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

if (!isSuperAdmin()) {
    die('<div class="alert alert-danger">Access denied. Super Admin only.</div>');
}

// Get branches for dropdown
$branches = getAllBranches();

// Handle import
$message = '';
$error = '';

if (isset($_POST['test_connection'])) {
    $host = trim($_POST['source_host']);
    $dbname = trim($_POST['source_db']);
    $user = trim($_POST['source_user']);
    $pass = $_POST['source_pass'];
    $port = (int)($_POST['source_port'] ?: 3306);
    
    try {
        $testDb = new PDO("mysql:host={$host};port={$port};dbname={$dbname}", $user, $pass);
        $testDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get counts
        $counts = [];
        $tables = ['biblio', 'item', 'member', 'loan', 'loan_history'];
        foreach ($tables as $t) {
            $stmt = $testDb->query("SELECT COUNT(*) FROM {$t}");
            $counts[$t] = $stmt->fetchColumn();
        }
        
        $message = "Connection successful! Found: " . 
            number_format($counts['biblio']) . " biblio, " .
            number_format($counts['item']) . " items, " .
            number_format($counts['member']) . " members, " .
            number_format($counts['loan']) . " loans";
            
        $_SESSION['import_source'] = compact('host', 'dbname', 'user', 'pass', 'port', 'counts');
    } catch (Exception $e) {
        $error = "Connection failed: " . $e->getMessage();
    }
}

if (isset($_POST['start_import']) && isset($_SESSION['import_source'])) {
    $src = $_SESSION['import_source'];
    $targetBranch = (int)$_POST['target_branch'];
    
    try {
        $sourceDb = new PDO("mysql:host={$src['host']};port={$src['port']};dbname={$src['dbname']}", $src['user'], $src['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create import log
        $stmt = $dbs->prepare("INSERT INTO import_logs (branch_id, source_db, source_type, import_type, status, started_at) VALUES (?, ?, 'database', 'full', 'running', NOW())");
        $stmt->execute([$targetBranch, $src['dbname']]);
        $logId = $dbs->lastInsertId();
        
        $imported = ['member' => 0, 'biblio' => 0, 'item' => 0];
        
        // Import Members
        $stmt = $sourceDb->query("SELECT * FROM member");
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($members as $m) {
            $oldId = $m['member_id'];
            unset($m['member_id']);
            $m['branch_id'] = $targetBranch;
            
            $cols = implode(',', array_keys($m));
            $placeholders = implode(',', array_fill(0, count($m), '?'));
            $ins = $dbs->prepare("INSERT INTO member ({$cols}) VALUES ({$placeholders})");
            $ins->execute(array_values($m));
            $newId = $dbs->lastInsertId();
            
            // Save mapping
            $dbs->prepare("INSERT INTO id_mapping (import_log_id, branch_id, table_name, old_id, new_id) VALUES (?,?,?,?,?)")
                ->execute([$logId, $targetBranch, 'member', $oldId, $newId]);
            $imported['member']++;
        }
        
        // Import Biblio
        $stmt = $sourceDb->query("SELECT * FROM biblio");
        $biblios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($biblios as $b) {
            $oldId = $b['biblio_id'];
            unset($b['biblio_id']);
            $b['branch_id'] = $targetBranch;
            
            $cols = implode(',', array_keys($b));
            $placeholders = implode(',', array_fill(0, count($b), '?'));
            $ins = $dbs->prepare("INSERT INTO biblio ({$cols}) VALUES ({$placeholders})");
            $ins->execute(array_values($b));
            $newId = $dbs->lastInsertId();
            
            $dbs->prepare("INSERT INTO id_mapping (import_log_id, branch_id, table_name, old_id, new_id) VALUES (?,?,?,?,?)")
                ->execute([$logId, $targetBranch, 'biblio', $oldId, $newId]);
            $imported['biblio']++;
        }
        
        // Import Items with biblio mapping
        $stmt = $sourceDb->query("SELECT * FROM item");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $i) {
            $oldBiblioId = $i['biblio_id'];
            unset($i['item_id']);
            $i['branch_id'] = $targetBranch;
            
            // Get new biblio_id
            $mapStmt = $dbs->prepare("SELECT new_id FROM id_mapping WHERE branch_id = ? AND table_name = 'biblio' AND old_id = ?");
            $mapStmt->execute([$targetBranch, $oldBiblioId]);
            $newBiblioId = $mapStmt->fetchColumn();
            
            if ($newBiblioId) {
                $i['biblio_id'] = $newBiblioId;
                $cols = implode(',', array_keys($i));
                $placeholders = implode(',', array_fill(0, count($i), '?'));
                $ins = $dbs->prepare("INSERT INTO item ({$cols}) VALUES ({$placeholders})");
                $ins->execute(array_values($i));
                $imported['item']++;
            }
        }
        
        // Update log
        $total = array_sum($imported);
        $dbs->prepare("UPDATE import_logs SET status = 'completed', records_imported = ?, completed_at = NOW() WHERE log_id = ?")
            ->execute([$total, $logId]);
        
        $message = "Import completed! Imported: {$imported['member']} members, {$imported['biblio']} biblio, {$imported['item']} items";
        unset($_SESSION['import_source']);
        
    } catch (Exception $e) {
        $error = "Import failed: " . $e->getMessage();
        if (isset($logId)) {
            $dbs->prepare("UPDATE import_logs SET status = 'failed', error_message = ? WHERE log_id = ?")
                ->execute([$e->getMessage(), $logId]);
        }
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
<div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h5><?php echo __('Step 1: Connect to Source Database'); ?></h5>
    </div>
    <div class="card-body">
        <form method="post">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label><?php echo __('Host'); ?></label>
                        <input type="text" name="source_host" class="form-control" value="localhost" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label><?php echo __('Port'); ?></label>
                        <input type="number" name="source_port" class="form-control" value="3306">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo __('Database Name'); ?></label>
                        <input type="text" name="source_db" class="form-control" required placeholder="slims_cabang_a">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label><?php echo __('Username'); ?></label>
                        <input type="text" name="source_user" class="form-control" value="root" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label><?php echo __('Password'); ?></label>
                        <input type="password" name="source_pass" class="form-control">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="test_connection" class="btn btn-info btn-block">
                            <i class="fa fa-plug"></i> <?php echo __('Test Connection'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_SESSION['import_source'])): ?>
<div class="card mt-3">
    <div class="card-header bg-success text-white">
        <h5><?php echo __('Step 2: Start Import'); ?></h5>
    </div>
    <div class="card-body">
        <form method="post" onsubmit="return confirm('Start import? This may take a while.')">
            <div class="form-group">
                <label><?php echo __('Target Branch'); ?></label>
                <select name="target_branch" class="form-control" required>
                    <option value="">-- Select Branch --</option>
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
<div class="card mt-3">
    <div class="card-header">
        <h5><?php echo __('Import History'); ?></h5>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Branch</th>
                    <th>Source</th>
                    <th>Records</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = $dbs->query("SELECT l.*, b.branch_name FROM import_logs l LEFT JOIN branches b ON b.branch_id = l.branch_id ORDER BY l.log_id DESC LIMIT 10");
                while ($log = $logs->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr>
                    <td><?php echo $log['log_id']; ?></td>
                    <td><?php echo htmlspecialchars($log['branch_name']); ?></td>
                    <td><?php echo htmlspecialchars($log['source_db']); ?></td>
                    <td><?php echo number_format($log['records_imported']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $log['status'] == 'completed' ? 'success' : ($log['status'] == 'failed' ? 'danger' : 'warning'); ?>">
                            <?php echo $log['status']; ?>
                        </span>
                    </td>
                    <td><?php echo $log['started_at']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
