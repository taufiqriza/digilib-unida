# Multi-Branch Import Tool Documentation

## 1. Overview

Tool untuk import data dari database SLiMS terpisah ke sistem multi-branch central.

## 2. Fitur Import Tool

- **Database-to-Database**: Import langsung dari MySQL
- **SQL File Import**: Import dari file .sql dump
- **Wizard UI**: Interface step-by-step
- **ID Mapping**: Track old ID vs new ID
- **Conflict Resolution**: Handle duplicate
- **Rollback**: Batalkan jika error
- **Progress Tracking**: Monitor real-time
- **Validation**: Cek integritas data

## 3. Lokasi File

```
admin/modules/branch_management/
├── index.php                 # Branch list
├── import/
│   ├── index.php            # Import wizard
│   ├── step1_source.php     # Pilih source
│   ├── step2_mapping.php    # Field mapping
│   ├── step3_preview.php    # Preview data
│   ├── step4_import.php     # Execute import
│   └── step5_result.php     # Result & validation
├── classes/
│   ├── BranchImporter.php   # Main importer class
│   ├── TableMigrator.php    # Per-table migrator
│   └── IdMapper.php         # ID mapping handler
└── templates/
    └── import_wizard.tpl.php
```

## 4. Konfigurasi Source Database

```php
// File: config/import_sources.php

return [
    'sources' => [
        'cabang_a' => [
            'name' => 'Perpustakaan Cabang A',
            'host' => 'localhost',
            'database' => 'slims_cabang_a',
            'username' => 'root',
            'password' => 'password',
            'port' => 3306,
            'target_branch_id' => 2
        ],
        'cabang_b' => [
            'name' => 'Perpustakaan Cabang B',
            'host' => '192.168.1.100',
            'database' => 'slims_cabang_b',
            'username' => 'slims_user',
            'password' => 'password',
            'port' => 3306,
            'target_branch_id' => 3
        ],
        // ... dst
    ]
];
```

## 5. Class BranchImporter

```php
<?php
// File: admin/modules/branch_management/classes/BranchImporter.php

namespace SLiMS\Migration;

class BranchImporter {
    
    private $sourceDb;
    private $targetDb;
    private $branchId;
    private $logId;
    private $idMapper;
    private $errors = [];
    
    public function __construct($sourceConfig, $targetDb, $branchId) {
        $this->sourceDb = new \PDO(
            "mysql:host={$sourceConfig['host']};dbname={$sourceConfig['database']};port={$sourceConfig['port']}",
            $sourceConfig['username'],
            $sourceConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $this->targetDb = $targetDb;
        $this->branchId = $branchId;
        $this->idMapper = new IdMapper($targetDb, $branchId);
    }
    
    /**
     * Start full import process
     */
    public function importAll($options = []) {
        $this->logId = $this->createImportLog('full');
        
        try {
            $this->targetDb->beginTransaction();
            
            // Import sequence
            $this->importLocations();
            $this->importMembers();
            $this->importBiblio();
            $this->importBiblioRelations();
            $this->importItems();
            $this->importLoans();
            $this->importLoanHistory();
            $this->importReserves();
            $this->importFines();
            $this->importVisitorCount();
            
            $this->targetDb->commit();
            $this->updateLogStatus('completed');
            
            return ['success' => true, 'log_id' => $this->logId];
            
        } catch (\Exception $e) {
            $this->targetDb->rollBack();
            $this->updateLogStatus('failed', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Import specific table only
     */
    public function importTable($tableName) {
        $method = 'import' . ucfirst($tableName);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        throw new \Exception("Unknown table: $tableName");
    }
    
    /**
     * Import members
     */
    protected function importMembers() {
        $sql = "SELECT * FROM member";
        $stmt = $this->sourceDb->query($sql);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $imported = 0;
        foreach ($members as $member) {
            $oldId = $member['member_id'];
            unset($member['member_id']);
            $member['branch_id'] = $this->branchId;
            
            $newId = $this->insertRecord('member', $member);
            $this->idMapper->map('member', $oldId, $newId);
            $imported++;
        }
        
        $this->logProgress('member', count($members), $imported);
        return $imported;
    }
    
    /**
     * Import biblio with author/topic relations
     */
    protected function importBiblio() {
        $sql = "SELECT * FROM biblio";
        $stmt = $this->sourceDb->query($sql);
        $biblios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $imported = 0;
        foreach ($biblios as $biblio) {
            $oldId = $biblio['biblio_id'];
            unset($biblio['biblio_id']);
            $biblio['branch_id'] = $this->branchId;
            
            // Map foreign keys
            $biblio['publisher_id'] = $this->mapPublisher($biblio['publisher_id']);
            $biblio['gmd_id'] = $this->mapGmd($biblio['gmd_id']);
            $biblio['language_id'] = $this->mapLanguage($biblio['language_id']);
            
            $newId = $this->insertRecord('biblio', $biblio);
            $this->idMapper->map('biblio', $oldId, $newId);
            $imported++;
        }
        
        $this->logProgress('biblio', count($biblios), $imported);
        return $imported;
    }
    
    /**
     * Import items with biblio mapping
     */
    protected function importItems() {
        $sql = "SELECT * FROM item";
        $stmt = $this->sourceDb->query($sql);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $imported = 0;
        foreach ($items as $item) {
            $oldId = $item['item_id'];
            unset($item['item_id']);
            $item['branch_id'] = $this->branchId;
            
            // Map biblio_id
            $item['biblio_id'] = $this->idMapper->getNewId('biblio', $item['biblio_id']);
            
            if ($item['biblio_id']) {
                $newId = $this->insertRecord('item', $item);
                $this->idMapper->map('item', $oldId, $newId);
                $imported++;
            }
        }
        
        $this->logProgress('item', count($items), $imported);
        return $imported;
    }
    
    /**
     * Import loans with member mapping
     */
    protected function importLoans() {
        $sql = "SELECT * FROM loan";
        $stmt = $this->sourceDb->query($sql);
        $loans = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $imported = 0;
        foreach ($loans as $loan) {
            unset($loan['loan_id']);
            $loan['branch_id'] = $this->branchId;
            $loan['member_id'] = $this->idMapper->getNewId('member', $loan['member_id']);
            
            if ($loan['member_id']) {
                $this->insertRecord('loan', $loan);
                $imported++;
            }
        }
        
        $this->logProgress('loan', count($loans), $imported);
        return $imported;
    }
    
    /**
     * Helper: Insert record and return new ID
     */
    protected function insertRecord($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->targetDb->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->targetDb->lastInsertId();
    }
    
    /**
     * Map publisher to central (create if not exists)
     */
    protected function mapPublisher($oldId) {
        if (!$oldId) return null;
        
        // Get publisher name from source
        $stmt = $this->sourceDb->prepare("SELECT publisher_name FROM mst_publisher WHERE publisher_id = ?");
        $stmt->execute([$oldId]);
        $name = $stmt->fetchColumn();
        
        if (!$name) return null;
        
        // Find or create in target
        $stmt = $this->targetDb->prepare("SELECT publisher_id FROM mst_publisher WHERE publisher_name = ?");
        $stmt->execute([$name]);
        $newId = $stmt->fetchColumn();
        
        if (!$newId) {
            $stmt = $this->targetDb->prepare("INSERT INTO mst_publisher (publisher_name, branch_id) VALUES (?, NULL)");
            $stmt->execute([$name]);
            $newId = $this->targetDb->lastInsertId();
        }
        
        return $newId;
    }
    
    // Similar methods for mapGmd(), mapLanguage(), mapAuthor(), mapTopic()...
    
    protected function createImportLog($type) {
        $sql = "INSERT INTO import_logs (branch_id, source_type, import_type, status, started_at) 
                VALUES (?, 'database', ?, 'running', NOW())";
        $stmt = $this->targetDb->prepare($sql);
        $stmt->execute([$this->branchId, $type]);
        return $this->targetDb->lastInsertId();
    }
    
    protected function updateLogStatus($status, $error = null) {
        $sql = "UPDATE import_logs SET status = ?, error_message = ?, completed_at = NOW() WHERE log_id = ?";
        $stmt = $this->targetDb->prepare($sql);
        $stmt->execute([$status, $error, $this->logId]);
    }
    
    protected function logProgress($table, $total, $imported) {
        $sql = "UPDATE import_logs SET 
                records_total = records_total + ?,
                records_imported = records_imported + ?
                WHERE log_id = ?";
        $stmt = $this->targetDb->prepare($sql);
        $stmt->execute([$total, $imported, $this->logId]);
    }
}
```

## 6. Class IdMapper

```php
<?php
// File: admin/modules/branch_management/classes/IdMapper.php

namespace SLiMS\Migration;

class IdMapper {
    
    private $db;
    private $branchId;
    private $cache = [];
    
    public function __construct($db, $branchId) {
        $this->db = $db;
        $this->branchId = $branchId;
    }
    
    public function map($table, $oldId, $newId) {
        $sql = "INSERT INTO id_mapping (branch_id, table_name, old_id, new_id) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE new_id = VALUES(new_id)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->branchId, $table, $oldId, $newId]);
        
        $this->cache["{$table}_{$oldId}"] = $newId;
    }
    
    public function getNewId($table, $oldId) {
        $key = "{$table}_{$oldId}";
        
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $sql = "SELECT new_id FROM id_mapping WHERE branch_id = ? AND table_name = ? AND old_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->branchId, $table, $oldId]);
        $newId = $stmt->fetchColumn();
        
        if ($newId) {
            $this->cache[$key] = $newId;
        }
        
        return $newId ?: null;
    }
    
    public function getAllMappings($table) {
        $sql = "SELECT old_id, new_id FROM id_mapping WHERE branch_id = ? AND table_name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->branchId, $table]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
```

## 7. Import Wizard UI

### Step 1: Select Source

```php
<?php
// File: admin/modules/branch_management/import/step1_source.php
?>
<div class="import-wizard">
    <h3>Step 1: Pilih Sumber Data</h3>
    
    <form method="post" action="?mod=branch_management&action=import&step=2">
        <div class="form-group">
            <label>Target Branch</label>
            <select name="branch_id" class="form-control" required>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['branch_id'] ?>"><?= $b['branch_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Tipe Import</label>
            <select name="source_type" class="form-control" id="sourceType">
                <option value="database">Database Connection</option>
                <option value="sql_file">SQL File Upload</option>
                <option value="preset">Preset Configuration</option>
            </select>
        </div>
        
        <!-- Database Connection -->
        <div id="dbConfig" class="source-config">
            <div class="row">
                <div class="col-md-6">
                    <label>Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost">
                </div>
                <div class="col-md-6">
                    <label>Port</label>
                    <input type="text" name="db_port" class="form-control" value="3306">
                </div>
            </div>
            <div class="form-group">
                <label>Database Name</label>
                <input type="text" name="db_name" class="form-control">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label>Username</label>
                    <input type="text" name="db_user" class="form-control">
                </div>
                <div class="col-md-6">
                    <label>Password</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
            </div>
            <button type="button" class="btn btn-info" onclick="testConnection()">Test Connection</button>
        </div>
        
        <!-- SQL File Upload -->
        <div id="sqlConfig" class="source-config" style="display:none;">
            <div class="form-group">
                <label>Upload SQL File</label>
                <input type="file" name="sql_file" class="form-control" accept=".sql">
            </div>
        </div>
        
        <hr>
        <button type="submit" class="btn btn-primary">Next: Field Mapping →</button>
    </form>
</div>
```

### Step 2: Preview & Mapping

```php
<?php
// File: admin/modules/branch_management/import/step2_mapping.php
?>
<div class="import-wizard">
    <h3>Step 2: Preview Data & Mapping</h3>
    
    <div class="source-stats">
        <h4>Statistik Database Source</h4>
        <table class="table">
            <tr><th>Tabel</th><th>Jumlah Record</th><th>Import?</th></tr>
            <?php foreach ($sourceStats as $table => $count): ?>
            <tr>
                <td><?= $table ?></td>
                <td><?= number_format($count) ?></td>
                <td><input type="checkbox" name="tables[]" value="<?= $table ?>" checked></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="conflict-options">
        <h4>Penanganan Konflik</h4>
        <div class="form-group">
            <label>Jika data sudah ada:</label>
            <select name="conflict_mode" class="form-control">
                <option value="skip">Skip (Lewati)</option>
                <option value="update">Update (Timpa)</option>
                <option value="duplicate">Duplicate (Buat baru)</option>
            </select>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Next: Execute Import →</button>
</div>
```

### Step 3: Execute & Progress

```php
<?php
// File: admin/modules/branch_management/import/step3_import.php
?>
<div class="import-wizard">
    <h3>Step 3: Import Progress</h3>
    
    <div id="importProgress">
        <div class="progress-item" data-table="member">
            <span class="table-name">Members</span>
            <div class="progress">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <span class="progress-text">0 / 0</span>
        </div>
        <div class="progress-item" data-table="biblio">
            <span class="table-name">Bibliografi</span>
            <div class="progress">
                <div class="progress-bar" style="width: 0%"></div>
            </div>
            <span class="progress-text">0 / 0</span>
        </div>
        <!-- ... more tables -->
    </div>
    
    <div id="importLog" class="import-log"></div>
</div>

<script>
// AJAX polling for progress
function checkProgress() {
    fetch('?mod=branch_management&action=import_status&log_id=<?= $logId ?>')
        .then(r => r.json())
        .then(data => {
            updateProgressUI(data);
            if (data.status === 'running') {
                setTimeout(checkProgress, 1000);
            }
        });
}
checkProgress();
</script>
```
