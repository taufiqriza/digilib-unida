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

/* Circulation section */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

if (!defined('SB')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SB.'admin/default/session.inc.php';
}
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');

require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_element.inc.php';

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

// Include modern CSS and Font Awesome for icons
echo '<link rel="stylesheet" href="'.MWB.'circulation/circulation-modern.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// check if there is transaction running
if (isset($_SESSION['memberID']) AND !empty($_SESSION['memberID'])) {
    define('DIRECT_INCLUDE', true);
    include MDLBS.'circulation/circulation_action.php';
} else {
?>
<div class="menuBox circulation-workspace">
  <div class="menuBoxInner circulationIcon">
    <!-- Workspace Header Hero -->
    <div class="workspace-hero">
      <div class="workspace-hero__icon">
        <i class="fas fa-exchange-alt"></i>
      </div>
      <div class="workspace-hero__text">
        <h2><?php echo __('Circulation Workspace'); ?></h2>
        <p><?php echo __('Library Lending Management System'); ?></p>
      </div>
      <div class="workspace-hero__stats">
        <?php
        // Get today's transactions count
        $today = date('Y-m-d');
        $today_loans_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE DATE(loan_date) = '$today'");
        $today_loans = $today_loans_q->fetch_row()[0];

        // Get active loans count
        $active_loans_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE is_lent=1 AND is_return=0");
        $active_loans = $active_loans_q->fetch_row()[0];
        ?>
        <div class="workspace-stat">
          <div class="workspace-stat__value"><?php echo $today_loans; ?></div>
          <div class="workspace-stat__label"><?php echo __('Today'); ?></div>
        </div>
        <div class="workspace-stat">
          <div class="workspace-stat__value"><?php echo $active_loans; ?></div>
          <div class="workspace-stat__label"><?php echo __('Active'); ?></div>
        </div>
      </div>
    </div>

    <!-- Workspace Surface -->
    <div class="workspace-surface">
      <div class="workspace-section">
        <div class="workspace-section__header">
          <h3><i class="fas fa-user-check"></i> <?php echo __('Start Transaction'); ?></h3>
          <p><?php echo __('Enter member ID or scan barcode to begin circulation process'); ?></p>
        </div>

        <div class="workspace-form-card">
          <form id="startCirc" action="<?php echo MWB; ?>circulation/circulation_action.php" method="post" class="workspace-form">
            <div class="workspace-form-group">
              <label class="workspace-label">
                <i class="fas fa-id-card"></i> <?php echo __('Member ID'); ?>
              </label>
              <?php
              // create AJAX drop down
              $ajaxDD = new simbio_fe_AJAX_select();
              $ajaxDD->element_name = 'memberID';
              $ajaxDD->element_css_class = 'form-control workspace-input';
              $ajaxDD->handler_URL = MWB.'membership/member_AJAX_response.php';
              echo $ajaxDD->out();
              ?>
            </div>

            <div class="workspace-actions">
              <button type="submit" name="start" id="start" class="workspace-btn workspace-btn--primary">
                <i class="fas fa-play-circle"></i>
                <span><?php echo __('Start Transaction'); ?></span>
              </button>
              <?php if($sysconf['barcode_reader']) : ?>
              <a class="workspace-btn workspace-btn--secondary notAJAX" id="barcodeReader" href="<?php echo MWB.'circulation/barcode_reader.php?mode=membership' ?>">
                <i class="fas fa-barcode"></i>
                <span><?php echo __('Barcode Reader (F8)'); ?></span>
              </a>
              <?php endif ?>
            </div>
          </form>
        </div>

        <!-- Quick Actions -->
        <div class="workspace-quick-actions">
          <h4><?php echo __('Quick Actions'); ?></h4>
          <div class="quick-actions-grid">
            <a href="<?php echo MWB; ?>circulation/quick_return.php" class="quick-action-card">
              <div class="quick-action-icon" style="background: linear-gradient(135deg, #10b981, #3ccf91);">
                <i class="fas fa-undo-alt"></i>
              </div>
              <div class="quick-action-text">
                <h5><?php echo __('Quick Return'); ?></h5>
                <p><?php echo __('Fast item return'); ?></p>
              </div>
            </a>

            <a href="<?php echo MWB; ?>circulation/loan_rules.php" class="quick-action-card">
              <div class="quick-action-icon" style="background: linear-gradient(135deg, #0891b2, #06b6d4);">
                <i class="fas fa-book"></i>
              </div>
              <div class="quick-action-text">
                <h5><?php echo __('Loan Rules'); ?></h5>
                <p><?php echo __('Manage policies'); ?></p>
              </div>
            </a>

            <a href="<?php echo MWB; ?>reporting/customs/overdued_list.php" class="quick-action-card">
              <div class="quick-action-icon" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                <i class="fas fa-exclamation-triangle"></i>
              </div>
              <div class="quick-action-text">
                <h5><?php echo __('Overdue List'); ?></h5>
                <p><?php echo __('View overdues'); ?></p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php 
  if($sysconf['barcode_reader']) {
    ob_start();
    require SB.'admin/'.$sysconf['admin_template']['dir'].'/barcodescannermodal.tpl.php';
    $barcode = ob_get_clean();
    echo $barcode;
  ?>
  <script type="text/javascript">
    $('#barcodeReader').click(function(e){
      e.preventDefault();
      var url = $(this).attr('href');
      $('#iframeBarcodeReader').attr('src', url);
      $('#barcodeModal').modal('show');
    });

    $(document.body).bind('keyup', this, function(e){
      // F8
      if(e.keyCode == 119) {
        $('#barcodeReader').click();
      }
    });
    parent.$(".modal-backdrop").remove();
  </script>
  <?php }
    if (isset($_POST['finishID'])) {
      $msg = str_ireplace('{member_id}', $_POST['finishID'], __('Transaction with member {member_id} is completed'));
      echo '<div class="infoBox">'.$msg.'</div>';
    }
}
