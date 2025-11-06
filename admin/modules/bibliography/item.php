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


/* Item Management section */

// key to authenticate
if (!defined('INDEX_AUTH')) {
  define('INDEX_AUTH', '1');
}
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
if (!defined('SB')) {
  require '../../../sysconfig.inc.php';
}

// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
  $in_pop_up = true;
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    $itemCode = trim(strip_tags($_POST['itemCode']));
    if (empty($itemCode)) {
        utility::jsToastr('Item', __('Item Code can\'t be empty!'), 'error');
        exit();
    } else {
        // biblio title
        $title = trim($_POST['biblioTitle']);
        $data['biblio_id'] = $_POST['biblioID'];
        $data['item_code'] = $dbs->escape_string($itemCode);
        $data['call_number'] = trim($dbs->escape_string($_POST['callNumber']));
        // check inventory code
        $inventoryCode = trim($_POST['inventoryCode']);
        if ($inventoryCode) {
            $data['inventory_code'] = $inventoryCode;
        } else {
            $data['inventory_code'] = 'literal{NULL}';
        }

        $data['location_id'] = $_POST['locationID'];
        $data['site'] = trim($dbs->escape_string(strip_tags($_POST['itemSite'])));
        $data['coll_type_id'] = intval($_POST['collTypeID']);
        $data['item_status_id'] = $dbs->escape_string($_POST['itemStatusID']);
        $data['source'] = $_POST['source'];
        $data['order_no'] = trim($dbs->escape_string(strip_tags($_POST['orderNo'])));
        $data['order_date'] = $_POST['ordDate'];
        $data['received_date'] = $_POST['recvDate'];
        $data['supplier_id'] = $_POST['supplierID'];
        $data['invoice'] = $_POST['invoice'];
        $data['invoice_date'] = $_POST['invcDate'];
        $data['price_currency'] = trim($dbs->escape_string(strip_tags($_POST['priceCurrency'])));
        if (!$data['price_currency']) { $data['price_currency'] = 'literal{NULL}'; }
        $data['price'] = preg_replace('@[.,\-a-z ]@i', '', strip_tags($_POST['price']));
        $data['input_date'] = date('Y-m-d H:i:s');
        $data['last_update'] = date('Y-m-d H:i:s');
        $data['uid'] = $_SESSION['uid'];

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            unset($data['uid']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
            // update the data
            $update = $sql_op->update('item', $data, "item_id=".$updateRecordID);
            if ($update) {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' update item data ('.$data['item_code'].') with title ('.$title.')', 'Item', 'Update');
                
                // Update item information in search_biblio after updating item
                // This ensures that the updated item appears correctly in bibliography search results
                require MDLBS . 'system/biblio_indexer.inc.php';
                $indexer = new biblio_indexer($dbs);
                $indexer->updateItems($data['biblio_id']);
                
                if ($sysconf['bibliography_item_update_notification']) {
                    utility::jsToastr('Item', __('Item Data Successfully Updated'), 'success');
			    }
                if ($in_pop_up) {
                    echo '<script type="text/javascript">top.setIframeContent(\'itemIframe\', \''.MWB.'bibliography/iframe_item_list.php?biblioID='.$data['biblio_id'].'\');</script>';
                    echo '<script type="text/javascript">top.jQuery.colorbox.close();</script>';
                } else {
                    echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[0].url);</script>';
                }
            } else { utility::jsToastr('Item', __('Item Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error, 'error'); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('item', $data);
            if ($insert) {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' insert item data ('.$data['item_code'].') with title ('.$title.')', 'Item', 'Add');
                
                // Update item information in search_biblio after inserting new item
                // This ensures that the new item appears in bibliography search results
                require MDLBS . 'system/biblio_indexer.inc.php';
                $indexer = new biblio_indexer($dbs);
                $indexer->updateItems($data['biblio_id']);
                
                utility::jsToastr('Item', __('New Item Data Successfully Saved'), 'success');
                if ($in_pop_up) {
                    echo '<script type="text/javascript">top.setIframeContent(\'itemIframe\', \''.MWB.'bibliography/iframe_item_list.php?biblioID='.$data['biblio_id'].'\');</script>';
                    echo '<script type="text/javascript">top.jQuery.colorbox.close();</script>';
                } else {
                    echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
                }
            } else { utility::jsToastr('Item', __('Item Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error, 'error'); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    // create sql op object
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_on_loan = array();
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
        // check if the item still on loan
        $loan_q = $dbs->query('SELECT i.item_code, b.title, COUNT(l.loan_id) FROM item AS i
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            LEFT JOIN loan AS l ON (i.item_code=l.item_code AND l.is_lent=1 AND l.is_return=0)
            WHERE i.item_id='.$itemID.' GROUP BY i.item_code');
        $loan_d = $loan_q->fetch_row();
        // if there is no loan
        if ($loan_d[2] < 1) {
            if (!$sql_op->delete('item', 'item_id='.$itemID)) {
                $error_num++;
            } else {
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' DELETE item data ('.$loan_d[0].') with title ('.$loan_d[1].')', 'Item', 'Delete');
            }
        } else {
            $still_on_loan[] = $loan_d[0].' - '.$loan_d[1];
            $error_num++;
        }
    }

    if ($still_on_loan) {
        $items = '';
        foreach ($still_on_loan as $item) {
            $items .= $item."\n";
        }
        utility::jsToastr('Item on Hold', __('Item data can not be deleted because still on hold by members')." : \n".$items, 'error');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
        exit();
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsToastr('Item', __('Item succesfully removed!'), 'success');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    } else {
        utility::jsToastr('Item', __('Item FAILED to removed!'), 'error');
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

if (!$in_pop_up) {
/* search form */
?>
    <div class="menuBox bibliography-hub">
        <div class="menuBoxInner itemIcon">
            <style>
                .bibliography-hub {
                    background: #eef1fb;
                    padding: 1.1rem;
                    border-radius: 22px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4);
                }
                .bibliography-hub .biblio-hero {
                    display: flex;
                    align-items: center;
                    justify-content: flex-start; /* Changed from space-between to flex-start to keep icon and text together */
                    gap: 8px; /* Gap between icon and text */
                    background: linear-gradient(118deg, #1f3bb3, #5563de 58%, #7b9dff);
                    border-radius: 18px;
                    padding: 16px 20px;
                    color: #fff;
                    box-shadow: 0 12px 28px rgba(31, 59, 179, 0.28);
                }
                .bibliography-hub .biblio-hero__icon {
                    flex: 0 0 54px;
                    height: 54px;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.18);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.24);
                }
                .bibliography-hub .biblio-hero__actions {
                    margin-left: auto; /* Push actions to the right */
                }
                .bibliography-hub .biblio-hero__actions {
                    display: flex;
                    flex-direction: column; /* Stack buttons vertically */
                    align-items: flex-end; /* Align to right */
                    gap: 8px; /* Space between buttons */
                }
                .bibliography-hub .biblio-hero__icon {
                    flex: 0 0 54px;
                    height: 54px;
                    border-radius: 16px;
                    background: rgba(255, 255, 255, 0.18);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 24px;
                    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.24);
                }
                .bibliography-hub .biblio-hero__text {
                    display: flex;
                    flex-direction: column;
                    gap: 2px; /* Reduced gap between title and description */
                    min-width: 0;
                }
                .bibliography-hub .biblio-hero__text h2 {
                    margin: 0;
                    font-size: 1.15rem;
                    font-weight: 700;
                    letter-spacing: 0.25px;
                }
                .bibliography-hub .biblio-hero__text p {
                    margin: 0;
                    color: rgba(255, 255, 255, 0.82);
                    font-size: 0.92rem;
                    letter-spacing: 0.12px;
                    padding-right: 8px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .bibliography-hub .biblio-surface {
                    margin-top: 18px;
                    background: #fff;
                    border-radius: 18px;
                    padding: 18px 20px 20px;
                    box-shadow: 0 16px 34px rgba(24, 46, 116, 0.14);
                    position: relative;
                }
                .bibliography-hub .biblio-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 14px;
                    margin-bottom: 16px;
                }
                .bibliography-hub .biblio-toolbar__actions {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                .bibliography-hub .biblio-toolbar__actions .btn {
                    border: none;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    padding: 9px 18px;
                    font-weight: 600;
                    box-shadow: 0 12px 24px rgba(31, 59, 179, 0.28);
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-toolbar__actions .btn:hover,
                .bibliography-hub .biblio-toolbar__actions .btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    transform: translateY(-1px);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.32);
                }
                .bibliography-hub .eddc-button-wrapper {
                    position: relative;
                    display: inline-block;
                }
                .bibliography-hub .eddc-button-wrapper .eddc-badge {
                    position: absolute;
                    top: -8px;
                    right: -8px;
                    background: #3ccf91;
                    color: #0a1d33;
                    font-size: 0.6rem;
                    font-weight: 700;
                    padding: 2px 6px;
                    border-radius: 9px;
                    letter-spacing: 0.5px;
                    text-transform: uppercase;
                    box-shadow: 0 2px 6px rgba(48, 170, 120, 0.4);
                    pointer-events: none;
                    z-index: 2;
                }
                .bibliography-hub .eddc-button-wrapper a {
                    position: relative;
                    z-index: 1;
                    min-width: 200px; /* Equal width for both buttons */
                    text-align: center;
                }
                .bibliography-hub .biblio-toolbar__hint {
                    font-size: 0.82rem;
                    color: #66739c;
                    max-width: 320px;
                    line-height: 1.35;
                    flex: 1 1 auto;
                    text-align: right;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .biblio-inputby {
                    margin-top: 4px;
                    font-size: 0.72rem;
                    letter-spacing: 0.05em;
                    color: #54607b;
                    text-transform: uppercase;
                }
                .bibliography-hub .biblio-search-grid {
                    display: grid;
                    grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 1fr) max-content;
                    align-items: stretch;
                    gap: 12px;
                    background: #f7f9ff;
                    border: 1px solid #e3e7f5;
                    border-radius: 14px;
                    padding: 12px 16px;
                }
                .bibliography-hub .biblio-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    justify-content: center;
                }
                .bibliography-hub .biblio-field label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                .bibliography-hub .biblio-field input,
                .bibliography-hub .biblio-field select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    background: #fff;
                    padding: 10px 12px;
                    color: #1f294a;
                    font-size: 0.94rem;
                    height: 42px;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                    align-self: stretch;
                }
                .bibliography-hub .biblio-field input:focus,
                .bibliography-hub .biblio-field select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                .bibliography-hub .biblio-actions {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 6px;
                    align-items: flex-end;
                }
                .bibliography-hub .biblio-actions-label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                .bibliography-hub .biblio-actions-buttons {
                    display: flex;
                    gap: 10px;
                    align-items: stretch;
                }
                .bibliography-hub .biblio-search-btn,
                .bibliography-hub .biblio-advanced-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    border-radius: 12px;
                    padding: 0 18px;
                    height: 42px;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-search-btn {
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    box-shadow: 0 12px 26px rgba(31, 59, 179, 0.35);
                }
                .bibliography-hub .biblio-search-btn:hover,
                .bibliography-hub .biblio-search-btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.38);
                }
                .bibliography-hub .biblio-advanced-toggle {
                    background: #fff;
                    color: #28376a;
                    border: 1px solid rgba(40, 55, 106, 0.16);
                    box-shadow: 0 6px 14px rgba(40, 55, 106, 0.1);
                }
                .bibliography-hub .biblio-advanced-toggle:hover,
                .bibliography-hub .biblio-advanced-toggle:focus {
                    color: #fff;
                    background: #28376a;
                    border-color: transparent;
                }
                .bibliography-hub .biblio-advanced-panel {
                    margin-top: 18px;
                    background: #f9faff;
                    border-radius: 14px;
                    border: 1px dashed #d0d8f2;
                    padding: 16px;
                }
                .bibliography-hub .biblio-filter-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 14px;
                }
                .bibliography-hub .biblio-filter-grid label {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    font-size: 0.85rem;
                    font-weight: 600;
                    color: #4a5685;
                    letter-spacing: 0.02em;
                }
                .bibliography-hub .biblio-filter-grid select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    padding: 9px 12px;
                    background: #fff;
                    color: #1f294a;
                    height: 40px;
                    transition: border 0.2s ease, box-shadow 0.2s ease;
                }
                .bibliography-hub .biblio-filter-grid select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                .bibliography-hub .biblio-ucs-link {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    margin-top: 14px;
                    border-radius: 11px;
                    padding: 9px 16px;
                    background: #f0f4ff;
                    color: #1f2d5c !important;
                    border: 1px solid rgba(31, 45, 92, 0.1);
                    box-shadow: 0 8px 16px rgba(31, 45, 92, 0.12);
                    font-weight: 600;
                    transition: all 0.2s ease;
                }
                .bibliography-hub .biblio-ucs-link:hover {
                    text-decoration: none;
                    color: #fff !important;
                    background: #1f3bb3;
                    border-color: #1f3bb3;
                    box-shadow: 0 14px 26px rgba(31, 59, 179, 0.32);
                }
                
                .biblio-search-card {
                    margin-top: 18px;
                    background: #fff;
                    border-radius: 18px;
                    padding: 0;
                    box-shadow: 0 16px 34px rgba(24, 46, 116, 0.14);
                    position: relative;
                }
                
                .biblio-search-toggle {
                    padding: 16px 20px;
                    background: linear-gradient(118deg, #1f3bb3, #5563de 58%, #7b9dff);
                    color: #fff;
                    border-radius: 18px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .biblio-search-toggle:hover {
                    background: linear-gradient(118deg, #182f8b, #4451c4 58%, #5a7de0);
                }
                
                .biblio-search-toggle .toggle-icon {
                    transition: transform 0.3s ease;
                }
                
                .biblio-search-toggle.collapsed .toggle-icon {
                    transform: rotate(-90deg);
                }
                
                .biblio-search-content {
                    padding: 18px 20px 20px;
                }
                
                .biblio-search-grid {
                    display: grid;
                    grid-template-columns: minmax(220px, 1.4fr) minmax(170px, 1fr) max-content;
                    align-items: stretch;
                    gap: 12px;
                    background: #f7f9ff;
                    border: 1px solid #e3e7f5;
                    border-radius: 14px;
                    padding: 12px 16px;
                }
                
                .biblio-field {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    justify-content: center;
                }
                
                .biblio-field label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                
                .biblio-field input,
                .biblio-field select {
                    border-radius: 10px;
                    border: 1px solid #d2d9ef;
                    background: #fff;
                    padding: 10px 12px;
                    color: #1f294a;
                    font-size: 0.94rem;
                    height: 42px;
                    transition: border-color 0.2s ease, box-shadow 0.2s ease;
                    align-self: stretch;
                }
                
                .biblio-field input:focus,
                .biblio-field select:focus {
                    border-color: #4c60df;
                    box-shadow: 0 0 0 3px rgba(76, 96, 223, 0.18);
                    outline: none;
                }
                
                .biblio-actions {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    gap: 6px;
                    align-items: flex-end;
                }
                
                .biblio-actions-label {
                    font-size: 0.78rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #6a7299;
                    margin: 0;
                }
                
                .biblio-actions-buttons {
                    display: flex;
                    gap: 10px;
                    align-items: stretch;
                }
                
                .biblio-search-btn,
                .biblio-advanced-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    border-radius: 12px;
                    padding: 0 18px;
                    height: 42px;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .biblio-search-btn {
                    background: linear-gradient(135deg, #1f3bb3, #5563de);
                    color: #fff;
                    box-shadow: 0 12px 26px rgba(31, 59, 179, 0.35);
                }
                
                .biblio-search-btn:hover,
                .biblio-search-btn:focus {
                    background: linear-gradient(135deg, #182f8b, #4451c4);
                    box-shadow: 0 16px 28px rgba(31, 59, 179, 0.38);
                }
                
                .biblio-advanced-toggle {
                    background: #fff;
                    color: #28376a;
                    border: 1px solid rgba(40, 55, 106, 0.16);
                    box-shadow: 0 6px 14px rgba(40, 55, 106, 0.1);
                }
                
                .biblio-advanced-toggle:hover,
                .biblio-advanced-toggle:focus {
                    color: #fff;
                    background: #28376a;
                    border-color: transparent;
                }
            </style>
            <div class="biblio-hero">
                <div class="biblio-hero__icon">
                    <span class="fa fa-tags" aria-hidden="true"></span>
                </div>
                <div class="biblio-hero__text">
                    <h2><?php echo __('Manajemen Item'); ?></h2>
                    <p><?php echo __('Kelola item koleksi, jumlah eksemplar, dan status ketersediaan secara efisien.'); ?></p>
                </div>
            </div>
            
            <div class="biblio-search-card">
                    <div class="biblio-search-toggle collapsed" id="searchToggle">
                        <span class="fa fa-search" aria-hidden="true"></span>
                        <span><?php echo __('Pencarian Item'); ?></span>
                        <span class="fa fa-chevron-down toggle-icon" aria-hidden="true"></span>
                    </div>
                    <div class="biblio-search-content" id="searchContent" style="display: none;">
                        <form name="search" action="<?php echo MWB; ?>bibliography/item.php" id="search" method="get"
                              class="biblio-search-grid">
                            <div class="biblio-field biblio-field--keywords">
                                <label for="keywords"><?php echo __('Kata Kunci'); ?></label>
                                <input type="text" name="keywords" id="keywords" class="form-control" placeholder="<?php echo __('Cari kode item, judul, atau lokasi'); ?>"/>
                            </div>
                            <div class="biblio-field">
                                <label for="searchby"><?php echo __('Mode Pencarian'); ?></label>
                                <select name="searchby" id="searchby" class="form-control">
                                    <option value="item"><?php echo __('Pencarian Item'); ?></option>
                                    <option value="others"><?php echo __('Lainnya'); ?> </option>
                                </select>
                            </div>
                            <div class="biblio-actions">
                                <span class="biblio-actions-label"><?php echo __('Tindakan'); ?></span>
                                <div class="biblio-actions-buttons">
                                    <button type="submit" id="doSearch" class="biblio-search-btn">
                                        <span class="fa fa-search" aria-hidden="true"></span>
                                        <span><?php echo __('Cari'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            // Toggle search section - same approach as index.php
            $('#searchToggle').click(function(e) {
                e.preventDefault();
                $(this).toggleClass('collapsed');
                $('#searchContent').slideToggle('fast');
                $(this).find('.toggle-icon').toggleClass('fa-chevron-down fa-chevron-up');
            });
            
            // Initialize toggle state - search is collapsed by default
            if ($('#searchToggle').hasClass('collapsed')) {
                $('#searchContent').hide();
                $('#searchToggle').find('.toggle-icon').removeClass('fa-chevron-down').addClass('fa-chevron-up');
            } else {
                $('#searchContent').show();
            }
        });
    </script>
<?php
/* search form end */
}
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
      die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
    }
    /* RECORD FORM */
    // try query
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT item.*, b.biblio_id, b.title, s.supplier_name
        FROM item
        LEFT JOIN biblio AS b ON item.biblio_id=b.biblio_id
        LEFT JOIN mst_supplier AS s ON item.supplier_id=s.supplier_id
        WHERE item_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('itemForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="s-btn btn btn-default"';
    // form table attributes
    $form->table_attr = 'id="dataList" class="s-table table"';
    $form->table_header_attr = 'class="alterCell font-weight-bold"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) {
            $form->record_id = $itemID;
        } else {
            $form->addHidden('updateRecordID', $itemID);
            $form->back_button = false;
        }
        // form record title
        $form->record_title = $rec_d['title'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="btn btn-default"';
        // default biblio title and biblio ID
        $b_title = $rec_d['title'];
        $b_id = $rec_d['biblio_id'];
        if (trim($rec_d['call_number']??'') == '') {
            $biblio_q = $dbs->query('SELECT call_number FROM biblio WHERE biblio_id='.$rec_d['biblio_id']);
            $biblio_d = $biblio_q->fetch_assoc();
            $rec_d['call_number'] = $biblio_d['call_number'];
        }
    } else {
        // get biblio title and biblio ID from database if we are not on edit mode
        $biblioID = 0;
        if (isset($_GET['biblioID'])) {
            $biblioID = (integer)$_GET['biblioID'];
        }
        $biblio_q = $dbs->query('SELECT biblio_id, title, call_number FROM biblio WHERE biblio_id='.$biblioID);
        $biblio_d = $biblio_q->fetch_assoc();
        $b_title = $biblio_d['title'];
        $b_id = $biblio_d['biblio_id'];
        $def_call_number = $biblio_d['call_number'];
    }

    /* Form Element(s) */
    // title
    if (!$in_pop_up) {
      $str_input = $b_title;
      $str_input .= '<div class="makeHidden"><a class="s-btn btn btn-default notAJAX openPopUp" href="'.MWB.'bibliography/pop_biblio.php?inPopUp=true&action=detail&itemID='.($rec_d['biblio_id']??'').'&itemCollID='.($rec_d['item_id']??'').'" width="750" height="500" title="'.__('Edit Biblographic data').'">'.__('Edit Biblographic data').'</a></div>';
    } else { $str_input = $b_title; }
    $form->addAnything(__('Title'), $str_input);
    $form->addHidden('biblioTitle', $b_title);
    $form->addHidden('biblioID', $b_id);
    // item code
    $str_input  = '<div class="container-fluid">';
    $str_input .= '<div class="row">';
    $str_input .= simbio_form_element::textField('text', 'itemCode', $rec_d['item_code']??'', 'onblur="ajaxCheckID(\''.SWB.'admin/AJAX_check_id.php\', \'item\', \'item_code\', \'msgBox\', \'itemCode\')" style="width: 50%;" class="form-control col-5"');
    $str_input .= '<span id="msgBox" class="col p-2"></span>';
    $str_input .= '</div>';
    $str_input .= '</div>';
    $form->addAnything(__('Item Code'), $str_input);
    // call number
    $form->addTextField('text', 'callNumber', __('Call Number'), $rec_d['call_number']??$def_call_number, 'style="width: 50%;" class="form-control"');
    // inventory code
    $form->addTextField('text', 'inventoryCode', __('Inventory Code'), $rec_d['inventory_code']??'', 'style="width: 50%;" class="form-control"');
    // item location
        // get location data related to this record from database
        $location_q = $dbs->query("SELECT location_id, location_name FROM mst_location");
        $location_options = array();
        while ($location_d = $location_q->fetch_row()) {
            $location_options[] = array($location_d[0], $location_d[1]);
        }
    $form->addSelectList('locationID', __('Location'), $location_options, $rec_d['location_id']??'','style="width: 50%" class="form-control"');
    // item site
    $form->addTextField('text', 'itemSite', __('Shelf Location'), $rec_d['site']??'', 'style="width: 50%;" class="form-control"');
    // collection type
        // get collection type data related to this record from database
        $coll_type_q = $dbs->query("SELECT coll_type_id, coll_type_name FROM mst_coll_type");
        $coll_type_options = array();
        while ($coll_type_d = $coll_type_q->fetch_row()) {
            $coll_type_options[] = array($coll_type_d[0], $coll_type_d[1]);
        }
    $form->addSelectList('collTypeID', __('Collection Type'), $coll_type_options, $rec_d['coll_type_id']??'','style="width: 40%" class="form-control"');
    // item status
        // get item status data from database
        $item_status_q = $dbs->query("SELECT item_status_id, item_status_name FROM mst_item_status");
        $item_status_options[] = array('0', __('Available'));
        while ($item_status_d = $item_status_q->fetch_row()) {
            $item_status_options[] = array($item_status_d[0], $item_status_d[1]);
        }
    $form->addSelectList('itemStatusID', __('Item Status'), $item_status_options, $rec_d['item_status_id']??'','style="width:40%" class="form-control"');
    // order number
    $form->addTextField('text', 'orderNo', __('Order Number'), $rec_d['order_no']??'', 'style="width: 40%;" class="form-control"');
    // order date
    $form->addDateField('ordDate', __('Order Date'), $rec_d['order_date']??date('Y-m-d'), 'class="form-control"');
    // received date
    $form->addDateField('recvDate', __('Receiving Date'), $rec_d['received_date']??date('Y-m-d'),'class="form-control"');
    // item supplier
        // get item status data from database
        $supplier_q = $dbs->query("SELECT supplier_id, supplier_name FROM mst_supplier");
        $supplier_options[] = array('0', __('Not Applicable'));
        while ($supplier_d = $supplier_q->fetch_row()) {
            $supplier_options[] = array($supplier_d[0], $supplier_d[1]);
        }
    $form->addSelectList('supplierID', __('Supplier'), $supplier_options, $rec_d['supplier_id']??'','class="form-control"');
    // item source
        $source_options[] = array('1', __('Buy'));
        $source_options[] = array('2', __('Prize/Grant'));
    $form->addRadio('source', __('Source'), $source_options, !empty($rec_d['source'])?$rec_d['source']:'1');
    // item invoice
    $form->addTextField('text', 'invoice', __('Invoice'), $rec_d['invoice']??'', 'style="width: 100%;" class="form-control"');
    // invoice date
    $form->addDateField('invcDate', __('Invoice Date'), $rec_d['invoice_date']??date('Y-m-d'),'class="form-control"');
    // price
    $str_input  = '<div class="container-fluid">';
    $str_input .= '<div class="row">';
    $str_input .= simbio_form_element::textField('text', 'price', !empty($rec_d['price'])?$rec_d['price']:'0', 'style="width: 40%;" class="form-control col-4"');
    $str_input .= simbio_form_element::selectList('priceCurrency', $sysconf['currencies'], $rec_d['price_currency']??'','style="width: 10%;" class="form-control col-2"');
    $str_input .= '</div>';
    $str_input .= '</div>';
    $form->addAnything(__('Price'), $str_input);

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="s-alert infoBox">'.__('You are going to edit Item data').': <b>'.$rec_d['title'].'</b> ' //mfc
            .'<br />'.__('Last Updated').'&nbsp;'.date('d F Y h:i:s',strtotime($rec_d['last_update']));
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} else {
    require SIMBIO.'simbio_UTILS/simbio_tokenizecql.inc.php';
    require LIB.'biblio_list_model.inc.php';

    if ($sysconf['index']['type'] == 'default' || (isset($_GET['searchby']) && $_GET['searchby'] == 'item')) {
        require LIB.'biblio_list.inc.php';
        $title_field_idx = 1;
        // callback function to show title and authors in datagrid
        function showTitleAuthors($obj_db, $array_data)
        {
            global $title_field_idx;
            // biblio author detail
            $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
                LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
                LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
                WHERE b.biblio_id='.$array_data[$title_field_idx]);
            echo $obj_db->error;
            $_authors = '';
            while ($_biblio_d = $_biblio_q->fetch_row()) {
                $_title = $_biblio_d[0];
                $_authors .= $_biblio_d[1].' - ';
            }
            $_authors = substr_replace($_authors, '', -3);
            $_output = '<div style="float: left;"><span class="title">'.$_title.'</span><div class="authors">'.$_authors.'</div></div>';
            return $_output;
        }

        /* ITEM LIST */
        // table spec
        $table_spec = 'item
            LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id
            LEFT JOIN mst_location AS loc ON item.location_id=loc.location_id
            LEFT JOIN mst_coll_type AS ct ON item.coll_type_id=ct.coll_type_id';

        // create datagrid
        $datagrid = new simbio_datagrid();
        if ($can_write) {
            $datagrid->setSQLColumn('item.item_id',
                'item.item_code AS \''.__('Item Code').'\'',
                'item.biblio_id AS \''.__('Title').'\'',
                'ct.coll_type_name AS \''.__('Collection Type').'\'',
                'loc.location_name AS \''.__('Location').'\'',
                'biblio.classification AS \''.__('Classification').'\'',
                'item.last_update AS \''.__('Last Updated').'\'');
            $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
            $title_field_idx = 2;
        } else {
            $datagrid->setSQLColumn('item.item_code AS \''.__('Item Code').'\'',
                'item.biblio_id AS \''.__('Title').'\'',
                'ct.coll_type_name AS \''.__('Collection Type').'\'',
                'loc.location_name AS \''.__('Location').'\'',
                'biblio.classification AS \''.__('Classification').'\'',
                'item.last_update AS \''.__('Last Updated').'\'');
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
        }
        $datagrid->setSQLorder('item.last_update DESC');
    } else {
        require LIB.'biblio_list_index.inc.php';

        // callback function to show title and authors in datagrid
        function showTitleAuthors($obj_db, $array_data)
        {
            global $title_field_idx;
            $_output = '<div style="float: left;"><span class="title">'.$array_data[$title_field_idx].'</span><div class="authors">'.$array_data[$title_field_idx+1].'</div></div>';
            return $_output;
        }

        /* ITEM LIST */
        // table spec
        $table_spec = '(item
            LEFT JOIN mst_location AS loc ON item.location_id=loc.location_id
            LEFT JOIN mst_coll_type AS ct ON item.coll_type_id=ct.coll_type_id)
            LEFT JOIN search_biblio AS `index` ON item.biblio_id=index.biblio_id';

        // create datagrid
        $datagrid = new simbio_datagrid();
        if ($can_write) {
            $datagrid->setSQLColumn('item.item_id',
                'item.item_code AS \''.__('Item Code').'\'',
                'index.title AS \''.__('Title').'\'',
                'index.author AS \''.__('Author').'\'',
                'ct.coll_type_name AS \''.__('Collection Type').'\'',
                'loc.location_name AS \''.__('Location').'\'',
                #'index.classification AS \''.__('Classification').'\'',
                'item.call_number AS \''.__('Call Number').'\'',
                'item.last_update AS \''.__('Last Updated').'\'');
            $datagrid->invisible_fields = array(2);
            $title_field_idx = 2;
            $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
        } else {
            $datagrid->setSQLColumn('item.item_code AS \''.__('Item Code').'\'',
                'index.title AS \''.__('Title').'\'',
                'index.author AS \''.__('Author').'\'',
                'ct.coll_type_name AS \''.__('Collection Type').'\'',
                'loc.location_name AS \''.__('Location').'\'',
                #'index.classification AS \''.__('Classification').'\'',
                'item.call_number AS \''.__('Call Number').'\'',
                'item.last_update AS \''.__('Last Updated').'\'');
            $datagrid->invisible_fields = array(2);
            $title_field_idx = 1;
            $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
        }
        $datagrid->setSQLorder('item.last_update DESC');
    }


    // is there any search
    if (isset($_GET['keywords']) && $_GET['keywords']) {
        $keywords = utility::filterData('keywords', 'get', true, true, true);
        $searchable_fields = array('title', 'author', 'subject', 'itemcode');
        $search_str = '';
        // if no qualifier in fields
        if (!preg_match('@[a-z]+\s*=\s*@i', $keywords)) {
            foreach ($searchable_fields as $search_field) {
                $search_str .= $search_field.'='.$keywords.' OR ';
            }
        } else {
            $search_str = $keywords;
        }
        $biblio_list = new biblio_list($dbs, 20);
        $criteria = $biblio_list->setSQLcriteria($search_str);
    }
    if (isset($criteria)) {
        $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
    }

    // set table and table header attributes
    $datagrid->table_attr = 'id="dataList" class="s-table table"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : '.htmlspecialchars($_GET['keywords']).'<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo '<div class="biblio-table-wrapper"><div class="biblio-table-card">', $datagrid_result, '</div></div>';
    
    // Add JavaScript for handling action button transformation and modern styling
    ?>
    <style>
        .biblio-table-wrapper {
            margin: 20px 0;
            background: #f8f9fc;
            border-radius: 20px;
            padding: 20px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
        }
        .biblio-table-card {
            margin-top: 0;
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 4px 20px rgba(21, 39, 102, 0.08);
            overflow-x: auto;
        }
        .biblio-table-card #dataList.s-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
            margin: 0;
        }
        .biblio-table-card #dataList.s-table thead tr th {
            background: transparent;
            border: none;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.1em;
            color: #7984ac;
            padding: 0 16px 6px;
        }
        .biblio-table-card #dataList.s-table tbody tr {
            position: relative;
            transform: translateY(0);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .biblio-table-card #dataList.s-table tbody tr::before {
            content: '';
            position: absolute;
            inset: 0;
            background: #f8f9ff;
            border-radius: 20px;
            box-shadow: 0 18px 36px rgba(29, 52, 123, 0.12);
            z-index: 0;
        }
        .biblio-table-card #dataList.s-table tbody tr:hover {
            transform: translateY(-4px);
        }
        .biblio-table-card #dataList.s-table tbody tr:hover::before {
            box-shadow: 0 24px 42px rgba(29, 52, 123, 0.18);
        }
        .biblio-table-card #dataList.s-table tbody td {
            border: none;
            padding: 14px 18px;
            color: #27345d;
            font-size: 0.94rem;
            line-height: 1.4;
            background: transparent;
            position: relative;
            z-index: 1;
        }
        .biblio-table-card #dataList.s-table tbody tr td:first-child {
            border-top-left-radius: 18px;
            border-bottom-left-radius: 18px;
        }
        .biblio-table-card #dataList.s-table tbody tr td:last-child {
            border-top-right-radius: 18px;
            border-bottom-right-radius: 18px;
        }
        .biblio-table-card #totalData {
            margin-top: 18px;
            font-weight: 600;
            color: #414d7a;
        }

        .biblio-cover-wrapper {
            position: relative;
            margin-right: 1rem;
            min-width: 50px;
            flex-shrink: 0;
        }

        .biblio-badge--overlay {
            position: absolute;
            top: -5px;
            right: -5px;
            z-index: 2;
            background: #10b981;
            color: white;
            font-weight: 700;
            font-size: 11px;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.5);
        }


        .biblio-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
        }
        .biblio-badge__date {
            letter-spacing: 0.02em;
        }
        .biblio-badge__time {
            font-size: 0.72rem;
            letter-spacing: 0.02em;
        }
        .biblio-badge--last-update {
            background: rgba(31, 59, 179, 0.1);
            color: #203268;
        }
        .biblio-badge--last-update .biblio-badge__time {
            color: #1f3bb3;
        }
        .biblio-badge--class {
            background: rgba(59, 130, 246, 0.18);
            color: #1e40af;
        }
        .biblio-badge--year {
            background: rgba(46, 176, 152, 0.18);
            color: #17695f;
        }
        .biblio-badge--isbn {
            background: rgba(123, 111, 255, 0.18);
            color: #4136a4;
        }
        .biblio-badge--copies {
            background: #10b981;
            color: white;
        }
        .biblio-badge--copies.biblio-badge--empty,
        .biblio-badge--isbn.biblio-badge--empty,
        .biblio-badge--year.biblio-badge--empty {
            background: rgba(128, 138, 168, 0.18);
            color: #6f7a9b;
        }
        .biblio-badge--empty {
            background: rgba(128, 138, 168, 0.18);
            color: #6f7a9b;
        }
        
        /* Last column - merged checkbox + edit button */
        .biblio-actions-cell {
            width: 120px !important;
            text-align: right !important;
            padding: 8px 12px 8px 8px !important;
            vertical-align: middle !important;
        }
        
        /* Container for checkbox + edit button */
        .biblio-actions-merged {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        
        /* Compact checkbox styling */
        .biblio-actions-merged input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0;
            flex-shrink: 0;
        }
        
        #dataList thead input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0 auto;
            display: block;
        }
        
        /* Edit button styling - using original style but modern container */
        .biblio-edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 16px;
            line-height: 1;
            text-decoration: none;
            color: white;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
            flex-shrink: 0;
        }
        
        .biblio-edit-btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.4);
        }
    </style>
    <script type="text/javascript">
    $(document).ready(function() {
        // Based on the approach in index.php, merge checkbox and edit button in the last column
        // Process header - remove checkbox header and update action header
        var $headerRow = $('#dataList thead tr');
        var $checkboxHeader = $headerRow.find('th:first'); // Checkbox header is usually first
        var $editHeader = $headerRow.find('th:eq(1)'); // Edit header might be second

        // Remove checkbox header if it exists
        if ($checkboxHeader.length > 0) {
            $checkboxHeader.remove();
        }
        
        // Process body rows - merge checkbox and edit button into last cell
        $('#dataList tbody tr').each(function() {
            var $row = $(this);
            var $checkboxCell = $row.find('td:first'); // Checkbox cell is usually first
            var $editCell = $row.find('td:eq(1)'); // Edit cell might be second

            // Get checkbox and edit link
            var $checkbox = $checkboxCell.find('input[type="checkbox"]');
            var $editLink = $editCell.find('a[href*="itemID"]');

            // Create merged cell content
            var $mergedContent = $('<div class="biblio-actions-merged"></div>');

            // Add edit button
            if ($editLink.length > 0) {
                var editHref = $editLink.attr('href');
                var editTitle = $editLink.attr('title') || $editLink.text();
                var $editButton = $('<a class="biblio-edit-btn" href="' + editHref + '" title="' + editTitle + '"><i class="fa fa-edit"></i></a>');
                $mergedContent.append($editButton);
            }

            // Add checkbox
            if ($checkbox.length > 0) {
                $mergedContent.append($checkbox.clone());
            }

            // Remove original cells
            $editCell.remove();
            $checkboxCell.remove();

            // Add merged action cell at the end
            var $newActionCell = $('<td class="biblio-actions-cell"></td>');
            $newActionCell.append($mergedContent);
            $row.append($newActionCell);
        });

        // Update header to show "Actions" in the last column
        var $lastHeader = $('#dataList thead tr th:last');
        if ($lastHeader.length > 0) {
            $lastHeader.html('<?php echo __("Actions"); ?>');
            $lastHeader.css({'text-align': 'center', 'font-weight': 'bold'});
        }
    });
    </script>
    <?php
}
/* main content end */
