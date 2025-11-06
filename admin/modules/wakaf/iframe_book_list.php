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


/* Biblio Item List */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-wakaf');

// privileges checking
$can_write = utility::havePrivilege('wakaf', 'w');
if (!$can_write) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

// page title
$page_title = 'Buku Wakaf List';
// get id from url
$wakifID = 0;
if (isset($_GET['wakifID']) AND !empty($_GET['wakifID'])) {
  $wakifID = (integer)$_GET['wakifID'];
}

// start the output buffer
ob_start();

// if biblio ID is set
if ($wakifID) {
  $table = new simbio_table();
  $table->table_attr = 'align="center" class="detailTable" style="width: 100%;" cellpadding="2" cellspacing="0"';
  //$table->appendTableRow(array('Title', 'Location', 'Call Number', 'Status', 'Copies'));
  $table->appendTableRow(array('Title', 'Call Number', 'Copies',));
  // database list
  $item_q = $dbs->query('SELECT b.biblio_id, b.title, b.call_number, w.wakif_id, COUNT(i.item_id) AS copies FROM biblio AS b
    LEFT JOIN item AS i ON i.biblio_id=b.biblio_id
    LEFT JOIN wakif AS w ON w.wakif_id=i.wakif_id
    WHERE w.wakif_id='.$wakifID.' GROUP BY b.biblio_id');

  $row = 1;
  while ($item_d = $item_q->fetch_assoc()) {
    // alternate the row color
    $row_class = ($row%2 == 0)?'alterCell':'alterCell2';

    $title       = $item_d['title'];
    //$item_code   = $item_d['item_code'];
    //$location    = $item_d['location_name'];
    $callnumber  = $item_d['call_number'];
    //$item_status = $item_d['item_status_name'];

    if ($item_d['copies']>0){
      $copies = $item_d['copies'];
    } 
    else {
      $copies = '<strong style="color: #f00;">None</strong';
    }

    // links
    // $edit_link = '<a class="notAJAX btn btn-default button openPopUp" href="'.MWB.'bibliography/pop_item.php?action=detail&biblioID='.$item_d['biblio_id'].'" width="650" height="400" title="'.__('Detail Item').'" style="text-decoration: underline;">' . __('View') . '</a>';

    $table->appendTableRow(array($title, $callnumber, $copies));
    //$table->appendTableRow(array($title, $location, $callnumber, $item_status, $copies));
    $table->setCellAttr($row, 0, 'class="'.$row_class.'" style="font-weight: bold; width: 30%;"');
    $table->setCellAttr($row, 1, 'class="'.$row_class.'" style="font-weight: bold; width: 10%;"');
    $table->setCellAttr($row, 2, 'class="'.$row_class.'" style="font-weight: bold; width: 30%;"');
    //$table->setCellAttr($row, 3, 'class="'.$row_class.'" style="font-weight: bold; width: 15%;"');
    //$table->setCellAttr($row, 4, 'class="'.$row_class.'" style="font-weight: bold; width: 10%;"');

    $row++;
  }
  echo $table->printTable();
  // hidden form
  echo '<form name="hiddenActionForm" method="post" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="bid" value="0" /><input type="hidden" name="remove" value="0" /></form>';
}
/* main content end */
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
