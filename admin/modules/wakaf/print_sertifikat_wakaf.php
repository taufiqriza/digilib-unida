<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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

/* Cetak Sertifikat Wakaf Buku , Oleh Muhammad Ibrahim */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-wakaf');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('wakaf', 'r');

if (!$can_read) {
    die('<div class="errorBox">You dont have enough privileges to view this section</div>');
}

// local settings
$max_print = 1;

// clean print queue
if (isset($_GET['action']) AND $_GET['action'] == 'clear') {
    // update print queue count object
    echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
    utility::jsAlert(__('Print queue cleared!'));
    unset($_SESSION['card']);
    exit();
}

if (isset($_POST['wakifID']) AND !empty($_POST['wakifID']) AND isset($_POST['itemAction'])) {
    if (!$can_read) {
        die();
    }
    if (!is_array($_POST['wakifID'])) {
        // make an array
        $_POST['wakifID'] = array($_POST['wakifID']);
    }
    // loop array
    if (isset($_SESSION['card'])) {
        $print_count = count($_SESSION['card']);
    } else {
        $print_count = 0;
    }
    // card size
    $size = 2;
    // create AJAX request
    echo '<script type="text/javascript" src="'.JWB.'jquery.js"></script>';
    echo '<script type="text/javascript">';
    // loop array
    foreach ($_POST['wakifID'] as $wakifID) {
        if ($print_count == $max_print) {
            $limit_reach = true;
            break;
        }
        if (isset($_SESSION['card'][$wakifID])) {
            continue;
        }
        if (!empty($wakifID)) {
            $card_text = trim($wakifID);
            echo '$.ajax({url: \''.SWB.'lib/phpbarcode/barcode.php?code='.$card_text.'&encoding='.$sysconf['barcode_encoding'].'&scale='.$size.'&mode=png\', type: \'GET\', error: function() { alert(\'Error creating sertifikat buku !\'); } });'."\n";
            // add to sessions
            $_SESSION['card'][$wakifID] = $wakifID;
            $print_count++;
        }
    }
    echo '</script>';
    if (isset($limit_reach)) {
        $msg = str_replace('{max_print}', $max_print, __('Selected wakif NOT ADDED to print queue. Only {max_print} can be printed at once')); //mfc
        utility::jsAlert($msg);
    } else {
        // update print queue count object
        echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\''.$print_count.'\');</script>';
        utility::jsAlert(__('Selected wakif added to print queue'));
    }
    exit();
}

// card pdf download
if (isset($_GET['action']) AND $_GET['action'] == 'print') {
    // check if label session array is available
    if (!isset($_SESSION['card'])) {
        utility::jsAlert(__('There is no data to print!'));
        die();
    }
    if (count($_SESSION['card']) < 1) {
        utility::jsAlert(__('There is no data to print!'));
        die();
    }
    // concat all ID together
    $wakif_ids = '';
    foreach ($_SESSION['card'] as $id) {
        $wakif_ids .= '\''.$id.'\',';
    }

     // include printed settings configuration file
    include SB.'admin'.DS.'admin_template'.DS.'printed_settings.inc.php';
    // check for custom template settings
    $custom_settings = SB.'admin'.DS.$sysconf['admin_template']['dir'].DS.$sysconf['template']['theme'].DS.'printed_settings.inc.php';
    if (file_exists($custom_settings)) {
        include $custom_settings;
    }

    // fungsi tgl format indo
    function tgl_indo($tanggal){
        $bulan = array (
            1 =>   "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "November",
            "Desember"
        );
        $pecahkan = explode('-', $tanggal);
        
        // variabel pecahkan 0 = tanggal
        // variabel pecahkan 1 = bulan
        // variabel pecahkan 2 = tahun
     
        return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
    }

    // load print settings from database to override value from printed_settings file
    loadPrintSettings($dbs, 'wakaf');

    // strip the last comma
    $wakif_ids = substr_replace($wakif_ids, '', -1);
    
    $wakif_q = $dbs->query('SELECT * FROM wakif WHERE wakif_id IN('.$wakif_ids.')');
    $wakif_datas = array();
    while ($wakif_d = $wakif_q->fetch_assoc()) {

        $date=date_create("$wakif_d[input_date]");
        // create html ouput
        $html_str .= '<!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="utf-8">
                        <title></title>
                    </head>

                    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Sofia">
                    <style type="text/css">
                        @page {
                            size:A4 landscape;
                            margin-left: 0px;
                            margin-right: 0px;
                            margin-top: 0px;
                            margin-bottom: 0px;
                            margin: 0;
                        }
                        .bungkus {
                            position: relative;
                        }
                        .sertifikat{
                            position: absolute;
                            width: 100%;
                            z-index: 1;
                        }
                        .nama {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            margin: 350px auto 0;
                            z-index: 1;
                            font-size: 34px;
                            font-family: "Montserrat";
                            font-weight: 600;
                            color: #042a45;
                        }
                        .isi {
                            position: absolute;
                            z-index: 1;
                            top: 50%;
                            left: 20%;
                            transform: translate(-12%, -12%);
                            margin: 420px auto 0;
                            line-height:28px;
                            font-size: 18px;
                            font-family: "Montserrat";
                            font-weight: 400;
                            color: #042a45;
                            text-align: center;
                        }
                    </style>'."\n";
        $html_str .= '<body>'."\n";
        
        $html_str .= '<div class="bungkus">
                        <img src="'.$sertifikat_template.'" class="sertifikat">
                        <div class="nama">
                            '.$wakif_d['wakif_name'].'
                        </div>      
                        <div class="isi">
                            Sebagai wakif yang mewakafkan bukunya sejumlah <b>'.$wakif_d['total_books'].' buku</b> kepada Perpustakaan UNIDA Gontor.<br>
                            Semoga menjadi amal jariyah saudara dan bermanfaat bagi semua pihak.<br><br>
                            Ponorogo, '.tgl_indo(date_format($date, 'Y-m-d')).'
                    </div>';

        $html_str .= '<script type="text/javascript">self.print();</script>'."\n";
        $html_str .= '</body></html>'."\n";
    }

    // unset the session
    unset($_SESSION['card']);
    // write to file
    $print_file_name = 'member_card_gen_print_result_'.strtolower(str_replace(' ', '_', $_SESSION['uname'])).'.html';
    $file_write = @file_put_contents(UPLOAD.$print_file_name, $html_str);
    if ($file_write) {
        // update print queue count object
        echo '<script type="text/javascript">parent.$(\'#queueCount\').html(\'0\');</script>';
        // open result in window
        echo '<script type="text/javascript">top.jQuery.colorbox({href: "'.SWB.FLS.'/'.$print_file_name.'", iframe: true, width: 800, height: 595, title: "'.__('Sertifikat Wakaf Buku : Muhammad Ibrahim').'"})</script>';
    } else { utility::jsAlert('ERROR! Cards failed to generate, possibly because '.SB.FLS.' directory is not writable'); }
    exit();
}

?>
<fieldset class="menuBox">
<div class="menuBoxInner printIcon">
    <div class="per_title">
        <h2><?php echo __('Sertifikat Wakaf Buku'); ?></h2>
    </div>
    <div class="sub_section">
        <div class="btn-group">
        <a target="blindSubmit" href="<?php echo MWB; ?>wakaf/print_sertifikat_wakaf.php?action=clear" class="notAJAX btn btn-default" style="color: #f00;"><i class="glyphicon glyphicon-trash"></i>&nbsp;<?php echo __('Clear Print Queue'); ?></a>
        <a target="blindSubmit" href="<?php echo MWB; ?>wakaf/print_sertifikat_wakaf.php?action=print" class="notAJAX btn btn-default"><i class="glyphicon glyphicon-print"></i>&nbsp;<?php echo __('Cetak Sertifikat Wakaf'); ?></a>
    </div>
        <form name="search" action="<?php echo MWB; ?>wakaf/print_sertifikat_wakaf.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?>:
        <input type="text" name="keywords" size="30" />
        <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
        </form>
    </div>
    <div class="infoBox">
    <?php
    echo __('Maximum').' <font style="color: #f00">'.$max_print.'</font> '.__('records can be printed at once. Currently there is').' '; //mfc
    if (isset($_SESSION['card'])) {
        echo '<font id="queueCount" style="color: #f00">'.count($_SESSION['card']).'</font>';
    } else { echo '<font id="queueCount" style="color: #f00">0</font>'; }
    echo ' '.__('in queue waiting to be printed.'); //mfc
    ?>
    </div>
</div>
</fieldset>
<?php
/* search form end */
/* ITEM LIST */
// table spec
$table_spec = 'wakif AS w';
// create datagrid
$datagrid = new simbio_datagrid();
$datagrid->setSQLColumn('w.wakif_id',
    // 'w.wakif_id AS \''.__('Wakif ID').'\'',
    'w.wakif_name AS \''.__('Wakif Name').'\'',
    'w.total_books AS \''.__('Total Books').'\'',
    'w.input_date AS \''.__('Input Date').'\'');
$datagrid->setSQLorder('w.last_update ASC');
// is there any search
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    $keyword = $dbs->escape_string(trim($_GET['keywords']));
    $words = explode(' ', $keyword);
    if (count($words) > 1) {
        $concat_sql = ' (';
        foreach ($words as $word) {
            $concat_sql .= " (w.wakif_name LIKE '%$word%'";
        }
        // remove the last AND
        $concat_sql = substr_replace($concat_sql, '', -3);
        $concat_sql .= ') ';
        $datagrid->setSQLCriteria($concat_sql);
    } else {
        $datagrid->setSQLCriteria("w.wakif_name LIKE '%$keyword%'");
    }
}
// set table and table header attributes
$datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
$datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
// edit and checkbox property
$datagrid->edit_property = false;
$datagrid->chbox_property = array('wakifID', __('Add'));
$datagrid->chbox_action_button = __('Add To Print Queue');
$datagrid->chbox_confirm_msg = __('Add to print queue?');
$datagrid->column_width = array('50%', '30%', '15%');
// set checkbox action URL
$datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];
// put the result into variables
$datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, $can_read);
if (isset($_GET['keywords']) AND $_GET['keywords']) {
    echo '<div class="infoBox">'.__('Found').' '.$datagrid->num_rows.' '.__('from your search with keyword').': "'.$_GET['keywords'].'"</div>'; //mfc
}
echo $datagrid_result;
/* main content end */
