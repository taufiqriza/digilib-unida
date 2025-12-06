<?php
/**
 * Stock Take Dashboard
 */

define('INDEX_AUTH', '1');

if (!defined('SB')) {
    require '../../../sysconfig.inc.php';
    require SB . 'admin/default/session.inc.php';
}

require SB . 'admin/default/session_check.inc.php';
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-stocktake');
require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_DB/simbio_dbop.inc.php';

$can_read = utility::havePrivilege('stock_take', 'r');
$can_write = utility::havePrivilege('stock_take', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You don\'t have enough privileges to access this area!') . '</div>');
}

function stock_format_number($value)
{
    return number_format((int)$value);
}

function stock_format_date($date)
{
    if (!$date) {
        return '-';
    }
    return utility::formatDate($date);
}

$detailId = isset($_GET['itemID']) ? (int)$_GET['itemID'] : 0;
$historyKeyword = trim($_GET['keywords'] ?? '');

$activeSession = null;
$activeStats = [
    'total' => 0,
    'scanned' => 0,
    'missing' => 0,
    'loan' => 0,
    'progress' => 0,
    'lastScan' => null,
    'participants' => []
];

if ($active_q = $dbs->query("SELECT * FROM stock_take WHERE is_active=1 ORDER BY start_date DESC LIMIT 1")) {
    if ($active_q->num_rows) {
        $activeSession = $active_q->fetch_assoc();
        $count_q = $dbs->query("SELECT 
                SUM(CASE WHEN status='e' THEN 1 ELSE 0 END) AS scanned,
                SUM(CASE WHEN status='m' THEN 1 ELSE 0 END) AS missing,
                SUM(CASE WHEN status='l' THEN 1 ELSE 0 END) AS loan
            FROM stock_take_item");
        if ($count_q && $count_q->num_rows) {
            $counts = $count_q->fetch_assoc();
            $activeStats['scanned'] = (int)($counts['scanned'] ?? 0);
            $activeStats['missing'] = (int)($counts['missing'] ?? 0);
            $activeStats['loan'] = (int)($counts['loan'] ?? 0);
        }
        $activeStats['total'] = (int)($activeSession['total_item_stock_taked'] ?? ($activeStats['scanned'] + $activeStats['missing']));
        if ($activeStats['total'] > 0) {
            $activeStats['progress'] = round(($activeStats['scanned'] / $activeStats['total']) * 100, 1);
        }
        $last_scan_q = $dbs->query("SELECT MAX(last_update) AS last_scan FROM stock_take_item WHERE last_update IS NOT NULL");
        if ($last_scan_q && $last_scan_q->num_rows) {
            $activeStats['lastScan'] = $last_scan_q->fetch_assoc()['last_scan'];
        }
        $participants = [];
        if (!empty($activeSession['stock_take_users'])) {
            $serialized = @unserialize($activeSession['stock_take_users']);
            if (is_array($serialized)) {
                $participants = array_values(array_filter($serialized));
            }
        }
        $activeStats['participants'] = $participants;
    }
}

$historyRows = [];
$historySql = "SELECT stock_take_id, stock_take_name, start_date, end_date, total_item_stock_taked, total_item_lost, total_item_exists, report_file FROM stock_take";
if ($historyKeyword !== '') {
    $safeKeyword = $dbs->escape_string($historyKeyword);
    $historySql .= " WHERE stock_take_name LIKE '%$safeKeyword%' OR init_user LIKE '%$safeKeyword%'";
}
$historySql .= ' ORDER BY start_date DESC';
if ($historyKeyword === '') {
    $historySql .= ' LIMIT 12';
}
if ($history_q = $dbs->query($historySql)) {
    while ($row = $history_q->fetch_assoc()) {
        $historyRows[] = $row;
    }
}

$detailData = null;
if ($detailId > 0) {
    $detail_q = $dbs->query('SELECT * FROM stock_take WHERE stock_take_id=' . $detailId);
    if ($detail_q && $detail_q->num_rows) {
        $detailData = $detail_q->fetch_assoc();
    }
}
?>
<style>
    .stock-dashboard {
        font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
        background: #f8fafc;
        padding: 24px 24px 40px;
        color: #0f172a;
    }
    .stock-hero {
        background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
        color: #fff;
        border-radius: 24px;
        padding: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        box-shadow: 0 20px 45px -25px rgba(15, 23, 42, 0.6);
        margin-bottom: 24px;
    }
    .stock-hero__eyebrow {
        font-size: 12px;
        letter-spacing: 0.3em;
        text-transform: uppercase;
        opacity: 0.8;
        margin-bottom: 6px;
    }
    .stock-hero h1 {
        font-size: 28px;
        margin: 0;
    }
    .stock-hero p {
        margin: 6px 0 0;
        opacity: 0.85;
    }
    .stock-hero__actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .stock-btn {
        border: none;
        border-radius: 999px;
        padding: 10px 18px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .stock-btn--white {
        background: rgba(255,255,255,0.15);
        color: #fff;
    }
    .stock-btn--dark {
        background: #0f172a;
        color: #fff;
    }
    .stock-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -10px rgba(15,23,42,0.4);
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: #fff;
        border-radius: 18px;
        padding: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 8px 20px -12px rgba(15, 23, 42, 0.15);
    }
    .stat-card__label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.3em;
        color: #64748b;
    }
    .stat-card__value {
        font-size: 28px;
        margin: 12px 0 4px;
        font-weight: 700;
    }
    .stat-card__hint {
        color: #94a3b8;
        font-size: 12px;
    }
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }
    .action-card {
        background: #fff;
        border-radius: 20px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .action-card h3 {
        margin: 0;
        font-size: 16px;
    }
    .action-card p {
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }
    .action-card .action-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
    }
    .action-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #2563eb;
        text-decoration: none;
    }
    .action-pill:hover {
        background: #eff6ff;
    }
    .finish-form label {
        display: flex;
        gap: 8px;
        align-items: center;
        font-size: 13px;
        color: #475569;
        margin-bottom: 12px;
    }
    .finish-form button {
        border: none;
        border-radius: 12px;
        padding: 12px 18px;
        font-weight: 700;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: #fff;
        cursor: pointer;
    }
    .history-card {
        background: #fff;
        border-radius: 22px;
        border: 1px solid #e2e8f0;
        padding: 24px;
        box-shadow: 0 12px 30px -20px rgba(15, 23, 42, 0.4);
    }
    .history-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }
    .history-header form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .history-header input[type="text"] {
        padding: 8px 14px;
        border-radius: 10px;
        border: 1px solid #cbd5f5;
    }
    .history-header button {
        border: none;
        background: #2563eb;
        color: #fff;
        border-radius: 10px;
        padding: 8px 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .history-table th {
        text-align: left;
        padding: 12px;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        font-size: 11px;
        color: #94a3b8;
    }
    .history-table td {
        padding: 14px 12px;
        border-top: 1px solid #e2e8f0;
    }
    .history-table a {
        color: #2563eb;
        font-weight: 600;
        text-decoration: none;
    }
    .detail-card {
        background: #fff;
        border-radius: 22px;
        padding: 26px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 18px 30px -25px rgba(15,23,42,0.45);
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }
    .detail-grid div {
        background: #f8fafc;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    .detail-grid span {
        display: block;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #94a3b8;
        margin-bottom: 6px;
    }
    .detail-actions {
        margin-top: 20px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .empty-state {
        background: #fff;
        border-radius: 18px;
        padding: 32px;
        border: 1px dashed #cbd5f5;
        text-align: center;
    }
</style>
<div class="stock-dashboard">
<?php if ($detailData): ?>
    <div class="stock-hero" style="margin-bottom:30px;">
        <div>
            <p class="stock-hero__eyebrow"><?php echo __('Stock Take Detail'); ?></p>
            <h1><?php echo htmlspecialchars($detailData['stock_take_name']); ?></h1>
            <p><?php echo __('Initialized by'); ?> <?php echo htmlspecialchars($detailData['init_user']); ?> · <?php echo stock_format_date($detailData['start_date']); ?></p>
        </div>
        <div class="stock-hero__actions">
            <a href="<?php echo MWB; ?>stock_take/index.php" class="stock-btn stock-btn--white">&larr; <?php echo __('Back to dashboard'); ?></a>
            <?php if (!empty($detailData['report_file'])): ?>
                <a class="stock-btn stock-btn--dark" target="_blank" href="<?php echo SWB . FLS . '/' . REP . '/' . $detailData['report_file']; ?>">
                    <i class="fas fa-file"></i> <?php echo __('Open Report'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="detail-card">
        <div class="detail-grid">
            <div>
                <span><?php echo __('Start Date'); ?></span>
                <strong><?php echo stock_format_date($detailData['start_date']); ?></strong>
            </div>
            <div>
                <span><?php echo __('End Date'); ?></span>
                <strong><?php echo stock_format_date($detailData['end_date']); ?></strong>
            </div>
            <div>
                <span><?php echo __('Total Items'); ?></span>
                <strong><?php echo stock_format_number($detailData['total_item_stock_taked']); ?></strong>
            </div>
            <div>
                <span><?php echo __('Missing'); ?></span>
                <strong><?php echo stock_format_number($detailData['total_item_lost']); ?></strong>
            </div>
        </div>
        <div class="detail-actions">
            <a class="action-pill" href="<?php echo MWB; ?>stock_take/lost_item_list.php?detail=<?php echo $detailData['stock_take_id']; ?>" target="_blank">
                <i class="fas fa-triangle-exclamation"></i> <?php echo __('Lost item list'); ?>
            </a>
            <a class="action-pill" href="<?php echo MWB; ?>stock_take/st_report.php?detail=<?php echo $detailData['stock_take_id']; ?>" target="_blank">
                <i class="fas fa-chart-pie"></i> <?php echo __('Report summary'); ?>
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="stock-hero">
        <div>
            <p class="stock-hero__eyebrow"><?php echo __('Stock Take'); ?></p>
            <h1><?php echo __('Cacah Jiwo Control Center'); ?></h1>
            <?php if ($activeSession): ?>
                <p><?php echo __('Monitoring active session'); ?> · <?php echo stock_format_date($activeSession['start_date']); ?></p>
            <?php else: ?>
                <p><?php echo __('No active stock take. Initialize a new session to begin.'); ?></p>
            <?php endif; ?>
        </div>
        <div class="stock-hero__actions">
            <?php if ($can_write): ?>
                <a href="<?php echo MWB; ?>stock_take/init.php" class="stock-btn stock-btn--white">
                    <i class="fas fa-play"></i> <?php echo __('Initialize'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo MWB; ?>stock_take/current.php" class="stock-btn stock-btn--dark">
                <i class="fas fa-barcode"></i> <?php echo __('Open Scanner'); ?>
            </a>
        </div>
    </div>
    <?php if ($activeSession): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('Total Items'); ?></div>
            <div class="stat-card__value"><?php echo stock_format_number($activeStats['total']); ?></div>
            <div class="stat-card__hint"><?php echo __('Scope of this session'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('Scanned'); ?></div>
            <div class="stat-card__value"><?php echo stock_format_number($activeStats['scanned']); ?></div>
            <div class="stat-card__hint"><?php echo $activeStats['progress']; ?>% <?php echo __('completed'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('Missing'); ?></div>
            <div class="stat-card__value" style="color:#ef4444"><?php echo stock_format_number($activeStats['missing']); ?></div>
            <div class="stat-card__hint"><?php echo __('Detected discrepancies'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('On Loan'); ?></div>
            <div class="stat-card__value" style="color:#059669"><?php echo stock_format_number($activeStats['loan']); ?></div>
            <div class="stat-card__hint"><?php echo __('Excluded during scan'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('Participants'); ?></div>
            <div class="stat-card__value"><?php echo count($activeStats['participants']); ?></div>
            <div class="stat-card__hint"><?php echo implode(', ', array_slice($activeStats['participants'], 0, 3)) ?: __('No participant yet'); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label"><?php echo __('Last scan'); ?></div>
            <div class="stat-card__value" style="font-size:20px;">
                <?php echo $activeStats['lastScan'] ? stock_format_date($activeStats['lastScan']) : __('No scan recorded'); ?>
            </div>
            <div class="stat-card__hint"><?php echo __('Keep the scanner active to update this timestamp.'); ?></div>
        </div>
    </div>
    <div class="action-grid">
        <div class="action-card">
            <h3><?php echo __('Session Actions'); ?></h3>
            <p><?php echo __('Everything you need to continue the ongoing stock take.'); ?></p>
            <div class="action-list">
                <a class="action-pill" href="<?php echo MWB; ?>stock_take/current.php"><i class="fas fa-barcode"></i> <?php echo __('Continue Scanning'); ?></a>
                <a class="action-pill" href="<?php echo MWB; ?>stock_take/lost_item_list.php"><i class="fas fa-triangle-exclamation"></i> <?php echo __('View Missing Items'); ?></a>
                <a class="action-pill" href="<?php echo MWB; ?>stock_take/st_upload.php"><i class="fas fa-upload"></i> <?php echo __('Upload Offline Scan'); ?></a>
                <a class="action-pill" href="<?php echo MWB; ?>stock_take/resync.php"><i class="fas fa-rotate"></i> <?php echo __('Resync Metadata'); ?></a>
            </div>
        </div>
        <?php if ($can_write): ?>
        <div class="action-card">
            <h3><?php echo __('Finish Current Session'); ?></h3>
            <p><?php echo __('Generate reconciliation report and mark the session as completed.'); ?></p>
            <form action="<?php echo MWB; ?>stock_take/finish.php" method="post" class="finish-form">
                <label>
                    <input type="checkbox" name="purge[]" value="1">
                    <?php echo __('Remove missing items from catalog automatically'); ?>
                </label>
                <input type="hidden" name="confirmFinish" value="true">
                <button type="submit" onclick="return confirm('<?php echo __('Finishing will lock this session. Continue?'); ?>')">
                    <i class="fas fa-flag-checkered"></i> <?php echo __('Finish & Generate Report'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
        <div class="action-card">
            <h3><?php echo __('Need a new session?'); ?></h3>
            <p><?php echo __('Archive the current results and prepare a fresh scope.'); ?></p>
            <div class="action-list">
                <a class="action-pill" href="<?php echo MWB; ?>stock_take/init.php">
                    <i class="fas fa-plus"></i> <?php echo __('Initialize Stock Take'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <h3><?php echo __('No active stock take'); ?></h3>
            <p><?php echo __('Launch a new stock take session to begin scanning your collection.'); ?></p>
            <?php if ($can_write): ?>
                <a class="stock-btn stock-btn--dark" href="<?php echo MWB; ?>stock_take/init.php">
                    <i class="fas fa-plus-circle"></i> <?php echo __('Start New Session'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="history-card" style="margin-top:30px;">
        <div class="history-header">
            <div>
                <h3 style="margin:0; font-size:18px;"><?php echo __('Stock Take History'); ?></h3>
                <p style="margin:4px 0 0; color:#64748b; font-size:13px;">
                    <?php echo __('Latest reconciliation cycles and their outcomes.'); ?>
                </p>
            </div>
            <form method="get">
                <input type="text" name="keywords" value="<?php echo htmlspecialchars($historyKeyword); ?>" placeholder="<?php echo __('Search history...'); ?>">
                <button type="submit"><?php echo __('Filter'); ?></button>
            </form>
        </div>
        <?php if (empty($historyRows)): ?>
            <p style="color:#94a3b8;"><?php echo __('No stock take history recorded yet.'); ?></p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="history-table">
                <thead>
                    <tr>
                        <th><?php echo __('Name'); ?></th>
                        <th><?php echo __('Duration'); ?></th>
                        <th><?php echo __('Missing'); ?></th>
                        <th><?php echo __('Report'); ?></th>
                        <th><?php echo __('Detail'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyRows as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['stock_take_name']); ?></strong></td>
                            <td><?php echo stock_format_date($row['start_date']); ?> &ndash; <?php echo stock_format_date($row['end_date']); ?></td>
                            <td><?php echo stock_format_number($row['total_item_lost']); ?></td>
                            <td>
                                <?php if (!empty($row['report_file'])): ?>
                                    <a target="_blank" href="<?php echo SWB . FLS . '/' . REP . '/' . $row['report_file']; ?>"><?php echo __('Open'); ?></a>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo MWB; ?>stock_take/index.php?itemID=<?php echo (int)$row['stock_take_id']; ?>" class="action-pill" style="padding:6px 12px;">
                                    <i class="fas fa-eye"></i> <?php echo __('Detail'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
