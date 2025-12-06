<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Stock Take */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-stocktake');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('stock_take', 'r');
$can_write = utility::havePrivilege('stock_take', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}
// check if there is any active stock take proccess
$stk_query = $dbs->query("SELECT * FROM stock_take WHERE is_active=1");
if (!$stk_query->num_rows) {
    echo '<div class="errorBox">'.__('NO stock taking proccess running!').'</div>';
    die();
}
$activeData = $stk_query->fetch_assoc();
mysqli_data_seek($stk_query, 0);

if (isset($_POST['confirmFinish'])) {
  set_time_limit(0);
  // get currently active stock take name
  $stk_take_q = $dbs->query('SELECT stock_take_name, stock_take_id FROM stock_take WHERE is_active=1');
  $stk_take_d = $stk_take_q->fetch_row();
  // update stock take finish time
  $finish_time_q = $dbs->query('UPDATE stock_take SET end_date=NOW() WHERE is_active=1');
  $stk_take_report_filename = strtolower(str_replace(' ', '_', trim($stk_take_d[0]))).'_report.html';
  if ($dbs->affected_rows) {
    // purge item data
    if (isset($_POST['purge']) AND !empty($_POST['purge'])) {
      // purge data in item table
      $purge_item_q = $dbs->query('DELETE FROM item WHERE item_id IN (SELECT item_id FROM stock_take_item WHERE status=\'m\')');
      // purge data in loan table
      $purge_loan_q = $dbs->query('DELETE FROM loan WHERE item_code IN (SELECT item_code FROM stock_take_item WHERE status=\'m\')');
    }
    $update_item_status_to_missing = $dbs->query('UPDATE item SET item_status_id=\'MIS\' WHERE item_code IN (SELECT item_code FROM stock_take_item WHERE status=\'m\')');
    // start output buffering content for report generation
    ob_start();
    echo '<html><head><title>'.$stk_take_d[0].' ' . __('Stock Take Report') . '</title>';
    echo '<meta http-equiv="Pragma" content="No-Cache">'."\n";
    echo '<meta http-equiv="Cache-Control" content="No-Cache">'."\n";
    echo '<style type="text/css">'."\n";
    echo 'body {padding: 0.2cm}'."\n";
    echo 'body * {color: black; font-size: 11pt;}'."\n";
    echo 'table {border: 1px solid #000000;}'."\n";
    echo '.dataListHeader {background-color: #000000; color: white; font-weight: bold;}'."\n";
    echo '.alterCell {border-bottom: 1px solid #666; background-color: #CCCCCC;}'."\n";
    echo '.alterCell2 {border-bottom: 1px solid #666; background-color: #FFFFFF;}'."\n";
    echo '</style>'."\n";
    echo '</head>';
    echo '<body>'."\n";
    define('REPECT_INCLUDE', true);
    // stock take general report
    echo '<h3>'.$stk_take_d[0].' - ' . __('Stock Take Report') . '</h3><hr />';
    include MDLBS.'stock_take/st_report.php';

    // cell row class
    $cellClass = 'alterCell';
    // stock take lost item list
    $lost_item_q = $dbs->query('SELECT item_code, title, classification, coll_type_name, call_number FROM stock_take_item WHERE status=\'m\'');
    if ($lost_item_q->num_rows > 0) {
        echo '<br />';
        echo '<h3>' . __('LOST Item list') . '</h3><hr size="1" />';
        echo '<table style="width: 100%; border: 1px solid #666;" cellspacing="0">';
        echo '<tr>';
        echo '<th class="dataListHeader">' . __('Item Code') . '</th>
            <th class="dataListHeader">' . __('Title') . '</th>
            <th class="dataListHeader">' . __('Classification') . '</th>';
        echo '</tr>'."\n";
        while ($lost_item_d = $lost_item_q->fetch_row()) {
            $cellClass = ($cellClass == 'alterCell')?'alterCell2':'alterCell';
            echo '<tr><td class="'.$cellClass.'">'.$lost_item_d[0].'</td>
                <td class="'.$cellClass.'">'.$lost_item_d[1].'</td>
                <td class="'.$cellClass.'">'.$lost_item_d[2].'</td>';
            echo '</tr>'."\n";
        }
        echo '</table>'."\n";
        unset($lost_item_q);
    }

    // stock take error logs
    $error_log_q = $dbs->query('SELECT log_date, log_msg FROM system_log WHERE log_location=\'stock_take\' AND log_msg LIKE \'Stock Take ERROR%\'');
    if ($error_log_q->num_rows > 0) {
        echo '<br />';
        echo '<h3>' . __('Stock Take Error Logs') . '</h3><hr size="1" />';
        echo '<table style="width: 100%; border: 1px solid #666;" cellspacing="0">';
        echo '<tr>';
        echo '<th class="dataListHeader">' . __('Time') . '</th>
            <th class="dataListHeader">' . __('Message') . '</th>';
        echo '</tr>';
        while ($error_log_d = $error_log_q->fetch_row()) {
            $cellClass = ($cellClass == 'alterCell')?'alterCell2':'alterCell';
            echo '<tr>';
            echo '<td class="'.$cellClass.'">'.$error_log_d[0].'</td><td class="'.$cellClass.'">'.$error_log_d[1].'</td>';
            echo '</tr>';
        }
        echo '</table>';
        unset($error_log_q);
    }
    echo '</html>';
    $html_str = ob_get_clean();
    // put html to file
    $file_write = @file_put_contents(REPBS.$stk_take_report_filename, $html_str);
    if ($file_write) {
        // open result in new window
        echo '<script type="text/javascript">top.$.colorbox({href: "'.SWB.'/'.FLS.'/'.REP.'/'.$stk_take_report_filename.'", width: 800, height: 500, title: "Stock Take Report"})</script>';
    } else { utility::jsAlert(str_replace('{directory}', REPBS, __('ERROR! Stock take report failed to generate, possibly because {directory} directory is not writable'))); }
    // update
    $update_st_q = $dbs->query("UPDATE stock_take SET report_file='$stk_take_report_filename' WHERE is_active=1");
    // set currently active stock take process to unactive
    $inactive_q = $dbs->query('UPDATE stock_take SET is_active=0');
    // clean all current stock take error log
    $error_log_q = $dbs->query('DELETE FROM system_log WHERE log_location=\'stock_take\' AND log_msg LIKE \'Stock Take ERROR%\'');
    // write log
    utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'stock_take', $_SESSION['realname'].' finish stock take ('.$stk_take_d[0].') from address '.$_SERVER['REMOTE_ADDR'], 'Finished', 'OK');
    // send an alert
    echo '<script type="text/javascript">';
    echo 'alert(\''.__('Stock Take Proccess Finished!').'\');';
    echo 'parent.location.href = \''.SWB.'admin/index.php?mod=stock_take\';';
    echo '</script>';
  }
  exit();
} else {
    $count_q = $dbs->query("SELECT 
        SUM(CASE WHEN status='e' THEN 1 ELSE 0 END) AS scanned,
        SUM(CASE WHEN status='m' THEN 1 ELSE 0 END) AS missing,
        SUM(CASE WHEN status='l' THEN 1 ELSE 0 END) AS loan
        FROM stock_take_item");
    $counts = $count_q ? $count_q->fetch_assoc() : array('scanned'=>0,'missing'=>0,'loan'=>0);
    $total = (int)($activeData['total_item_stock_taked'] ?? 0);
    $progress = $total > 0 ? round(($counts['scanned'] / $total) * 100, 1) : 0;
?>
<style>
    .stock-finish {
        font-family: 'Inter','Segoe UI',sans-serif;
        background: #f8fafc;
        padding: 32px;
    }
    .stock-finish__card {
        background: #fff;
        border-radius: 24px;
        padding: 28px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 45px -25px rgba(15,23,42,0.35);
        max-width: 840px;
        margin: 0 auto;
    }
    .stock-finish__header h1 {
        margin: 0;
        font-size: 26px;
    }
    .stock-finish__header p {
        margin: 8px 0 0;
        color: #475569;
    }
    .stock-finish__stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px,1fr));
        gap: 14px;
        margin: 24px 0;
    }
    .stock-finish__stats div {
        background: #f8fafc;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        padding: 14px;
    }
    .stock-finish__stats span {
        display: block;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.2em;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .stock-finish__stats strong {
        font-size: 22px;
        color: #0f172a;
    }
    .finish-warning {
        background: #fef3c7;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid #fcd34d;
        font-size: 14px;
        margin-bottom: 20px;
        color: #92400e;
    }
    .finish-form label {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 12px;
        color: #475569;
    }
    .finish-form button {
        border: none;
        border-radius: 14px;
        padding: 12px 20px;
        font-weight: 700;
        background: linear-gradient(135deg,#ef4444,#dc2626);
        color: #fff;
        cursor: pointer;
        width: 100%;
        font-size: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
</style>
<div class="stock-finish">
    <div class="stock-finish__card">
        <div class="stock-finish__header">
            <h1><?php echo __('Finish Stock Take'); ?></h1>
            <p><?php echo __('You are about to end the current session'); ?> · <strong><?php echo htmlspecialchars($activeData['stock_take_name']); ?></strong></p>
        </div>
        <div class="stock-finish__stats">
            <div><span><?php echo __('Scanned'); ?></span><strong><?php echo number_format($counts['scanned']); ?></strong></div>
            <div><span><?php echo __('Missing'); ?></span><strong style="color:#dc2626;"><?php echo number_format($counts['missing']); ?></strong></div>
            <div><span><?php echo __('On Loan'); ?></span><strong><?php echo number_format($counts['loan']); ?></strong></div>
            <div><span><?php echo __('Progress'); ?></span><strong><?php echo $progress; ?>%</strong></div>
        </div>
        <div class="finish-warning">
            <strong><?php echo __('Irreversible action'); ?>:</strong> <?php echo __('Finishing will lock this session and generate the reconciliation report. You will not be able to rescan under the same session.'); ?>
        </div>
        <form method="post" class="finish-form">
            <label>
                <input type="checkbox" name="purge[]" value="1">
                <?php echo __('Delete items flagged as missing from the catalog'); ?>
            </label>
            <input type="hidden" name="confirmFinish" value="true">
            <button type="submit" onclick="return confirm('<?php echo __('Finish and generate report now?'); ?>')">
                <i class="fas fa-flag-checkered"></i> <?php echo __('Finish Stock Take'); ?>
            </button>
        </form>
    </div>
</div>
<?php
}
