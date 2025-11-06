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

/* Quick Return page */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}
// key to get full database access
define('DB_ACCESS', 'fa');

require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');

// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// load settings from database
utility::loadSettings($dbs);

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!($can_read AND $can_write)) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
}

// check if quick return is enabled
if (!$sysconf['quick_return']) {
    die('<div class="errorBox">'.__('Quick Return is disabled').'</div');
}

// Include modern CSS and Font Awesome
echo '<link rel="stylesheet" href="'.MWB.'circulation/circulation-modern.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

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
  <?php } ?>

<div class="menuBox circulation-workspace">
  <div class="menuBoxInner quickReturnIcon">
    <!-- Workspace Header Hero -->
    <div class="workspace-hero">
      <div class="workspace-hero__icon">
        <i class="fas fa-undo-alt"></i>
      </div>
      <div class="workspace-hero__text">
        <h2><?php echo __('Quick Return'); ?></h2>
        <p><?php echo __('Fast Item Return Processing'); ?></p>
      </div>
      <div class="workspace-hero__stats">
        <?php
        // Get today's returns count
        $today = date('Y-m-d');
        $today_returns_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE DATE(return_date) = '$today' AND is_return=1");
        $today_returns = $today_returns_q->fetch_row()[0];
        ?>
        <div class="workspace-stat">
          <div class="workspace-stat__value"><?php echo $today_returns; ?></div>
          <div class="workspace-stat__label"><?php echo __('Returns'); ?></div>
        </div>
      </div>
    </div>

    <!-- Workspace Surface -->
    <div class="workspace-surface">
      <div class="workspace-section">
        <div class="workspace-section__header">
          <h3><i class="fas fa-qrcode"></i> <?php echo __('Scan Item Barcode'); ?></h3>
          <p><?php echo __('Scan or enter item barcode to quickly process returns'); ?></p>
        </div>

        <div class="workspace-form-card">
          <form action="<?php echo MWB; ?>circulation/ajax_action.php" target="circAction" method="post" class="workspace-form notAJAX">
            <div class="workspace-form-group">
              <label class="workspace-label">
                <i class="fas fa-box"></i> <?php echo __('Item ID / Barcode'); ?>
              </label>
              <input type="text" name="quickReturnID" id="quickReturnID" class="form-control workspace-input" placeholder="Scan or type item barcode..." autofocus />
            </div>

            <div class="workspace-actions">
              <button type="submit" id="quickReturnProcess" class="workspace-btn workspace-btn--primary">
                <i class="fas fa-check-circle"></i>
                <span><?php echo __('Process Return'); ?></span>
              </button>
              <?php if($sysconf['barcode_reader']) : ?>
              <a class="workspace-btn workspace-btn--secondary notAJAX" id="barcodeReader" href="<?php echo MWB.'circulation/barcode_reader.php?mode=quickreturn' ?>">
                <i class="fas fa-barcode"></i>
                <span><?php echo __('Open Barcode Reader (F8)'); ?></span>
              </a>
              <?php endif ?>
            </div>
          </form>
          <iframe name="circAction" id="circAction" style="display: inline; width: 5px; height: 5px; visibility: hidden;"></iframe>
        </div>

        <!-- Return Result Area -->
        <div id="circulationLayer" style="margin-top: 20px;"></div>
      </div>
    </div>
  </div>
</div>
