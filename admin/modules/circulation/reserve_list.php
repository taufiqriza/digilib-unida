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

/* RESERVATION LIST IFRAME CONTENT */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

if (!isset($_SESSION['memberID'])) { die(); }

require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';

// page title
$page_title = 'Member Reserve List';

// Include modern CSS and Font Awesome
echo '<link rel="stylesheet" href="'.MWB.'circulation/circulation-modern.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// start the output buffering
ob_start();
?>
<!--reserve specific javascript functions-->
<script type="text/javascript">
function confirmProcess(intReserveID, strTitle)
{
    var confirmBox = confirm('<?php echo __('Are you sure to remove reservation for'); ?>' + "\n" + strTitle);
    if (confirmBox) {
        document.reserveHiddenForm.reserveID.value = intReserveID;
        document.reserveHiddenForm.submit();
    }
}
</script>
<!--reserve specific javascript functions end-->

<div style="padding: 20px;">
  <!--item loan form-->
  <div class="s-circulation__reserve" style="background: linear-gradient(135deg, #e6f2ff 0%, #cce5ff 100%); padding: 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(31, 59, 179, 0.1); border: 2px solid rgba(31, 59, 179, 0.1);">
    <form name="reserveForm" id="search" action="circulation_action.php" method="post" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <label style="font-weight: 600; color: #1f3bb3; min-width: 140px;"><i class="fas fa-search"></i> <?php echo __('Search Collection'); ?></label>
      <?php
      // AJAX expression
      $ajax_exp = "ajaxFillSelect('item_AJAX_lookup_handler.php', 'item', 'i.item_code:title', 'reserveItemID', $('#bib_search_str').val())";
      $biblio_options[] = array('0', 'Title');
      echo simbio_form_element::textField('text', 'bib_search_str', '', 'class="form-control" style="flex: 1; min-width: 200px;" oninput="'.$ajax_exp.'" placeholder="Search by title..."');
      echo simbio_form_element::selectList('reserveItemID', $biblio_options, '', 'class="form-control" style="flex: 1; min-width: 200px;"');
      ?>
      <button type="submit" name="addReserve" class="s-btn btn btn-primary" style="white-space: nowrap;">
        <i class="fas fa-bookmark"></i> <?php echo __('Add Reserve'); ?>
      </button>
    </form>
  </div>
  <!--item loan form end-->

<?php
// check if there is member ID
if (isset($_SESSION['memberID'])) {
    $memberID = trim($_SESSION['memberID']);
    $reserve_list_q = $dbs->query("SELECT r.*, b.title FROM reserve AS r
        LEFT JOIN biblio AS b ON r.biblio_id=b.biblio_id
        WHERE r.member_id='$memberID'");

    // create table object
    $reserve_list = new simbio_table();
    $reserve_list->table_attr = 'class="s-table table" style="width: 100%;"';
    $reserve_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    $reserve_list->highlight_row = true;
    // table header
    $headers = array(__('Remove'), __('Title'), __('Item Code'), __('Reserve Date'), __('Status'));
    $reserve_list->setHeader($headers);
    // row number init
    $row = 1;
    while ($reserve_list_d = $reserve_list_q->fetch_assoc()) {
        // alternate the row color
        $row_class = ($row%2 == 0)?'alterCell':'alterCell2';

        // remove reserve link
        $remove_link = '<a href="#" onclick="confirmProcess('.$reserve_list_d['reserve_id'].', \''.$reserve_list_d['title'].'\')" title="'.__('Remove Reservation').'" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> '.__('Remove').'</a>';

        // check if item/collection is available
        $status_badge = '';
        $avail_q = $dbs->query("SELECT COUNT(loan_id) FROM loan WHERE item_code='".$reserve_list_d['item_code']."' AND is_lent=1 AND is_return=0");
        $avail_d = $avail_q->fetch_row();
        if ($avail_d[0] < 1) {
            $status_badge = '<span class="badge badge-success"><i class="fas fa-check-circle"></i> '.strtoupper(__('Available')).'</span>';
        } else {
            $status_badge = '<span class="badge badge-warning"><i class="fas fa-clock"></i> '.__('On Loan').'</span>';
        }

        // check if reservation are already expired
        if ( (strtotime(date('Y-m-d'))-strtotime($reserve_list_d['reserve_date']))/(3600*24) > $sysconf['reserve_expire_periode'] ) {
            $status_badge = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> '.__('EXPIRED').'</span>';
        }

        // row colums array
        $fields = array(
            $remove_link,
            $reserve_list_d['title'],
            '<code>'.$reserve_list_d['item_code'].'</code>',
            $reserve_list_d['reserve_date'],
            $status_badge
            );

        // append data to table row
        $reserve_list->appendTableRow($fields);
        // set the HTML attributes
        $reserve_list->setCellAttr($row, null, "valign='top' class='$row_class'");
        $reserve_list->setCellAttr($row, 0, "valign='top' align='center' class='$row_class' style='width: 100px;'");
        $reserve_list->setCellAttr($row, 1, "valign='top' class='$row_class' style='font-weight: 500;'");
        $reserve_list->setCellAttr($row, 3, "valign='top' class='$row_class' style='width: 120px;'");
        $reserve_list->setCellAttr($row, 4, "valign='top' class='$row_class' style='width: 120px;'");

        $row++;
    }

    echo $reserve_list->printTable();
    // hidden form for return and extend process
    echo '<form name="reserveHiddenForm" method="post" action="circulation_action.php"><input type="hidden" name="process" value="delete" /><input type="hidden" name="reserveID" value="" /></form>';
}
?>
</div>

<?php
// get the buffered content
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
