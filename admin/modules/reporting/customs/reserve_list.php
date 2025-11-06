<?php
/**
 *
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

/* Reserve List */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r');
$can_write = utility::havePrivilege('circulation', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS.'reporting/report_dbgrid.inc.php';

$page_title = 'Reservation List Report';
$reportView = false;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

// Get reservation count
$reserve_total_q = $dbs->query("SELECT COUNT(*) FROM reserve");
$reserve_total = $reserve_total_q->fetch_row()[0];

if (!$reportView) {
?>
    <!-- Include modern CSS and Font Awesome -->
    <link rel="stylesheet" href="<?php echo MWB; ?>circulation/circulation-modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- filter -->
    <div class="circulation-workspace">
      <div class="workspace-hero">
        <div class="workspace-hero__icon">
          <i class="fas fa-bookmark"></i>
        </div>
        <div class="workspace-hero__text">
          <h2><?php echo __('Reservation List'); ?></h2>
          <p><?php echo __('Comprehensive Reservation Report'); ?></p>
        </div>
        <div class="workspace-hero__stats">
          <div class="workspace-stat">
            <div class="workspace-stat__value"><?php echo $reserve_total; ?></div>
            <div class="workspace-stat__label"><?php echo __('Total'); ?></div>
          </div>
        </div>
      </div>

      <div class="workspace-surface">
        <div class="workspace-section">
          <div class="workspace-section-header">
            <h3><i class="fas fa-filter"></i> <?php echo __('Report Filter'); ?></h3>
            <p><?php echo __('Configure filters to generate reservation report'); ?></p>
          </div>

          <div class="workspace-form-card">
            <!-- Collapsible Filter Toggle -->
            <div class="workspace-search-toggle collapsed" id="reserveFilterToggle" style="cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sliders-h"></i>
                <span style="font-weight: 500;"><?php echo __('Filter Options'); ?></span>
              </div>
              <i class="fas fa-chevron-down toggle-icon" style="transition: transform 0.3s;"></i>
            </div>

            <div class="workspace-search-content collapse" id="reserveFilterContent">
              <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
                <div id="filterForm">
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Member ID').'/'.__('Member Name'); ?></label>
                    <div class="workspace-form-control">
                      <?php echo simbio_form_element::textField('text', 'member', '', 'class="form-control" style="width: 100%;"'); ?>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Title/ISBN'); ?></label>
                    <div class="workspace-form-control">
                      <?php echo simbio_form_element::textField('text', 'title', '', 'class="form-control" style="width: 100%;"'); ?>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Item Code'); ?></label>
                    <div class="workspace-form-control">
                      <?php echo simbio_form_element::textField('text', 'itemCode', '', 'class="form-control" style="width: 100%;"'); ?>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Reserve Date Range'); ?></label>
                    <div class="workspace-form-control">
                      <div style="display: flex; gap: 12px; align-items: center;">
                        <?php echo simbio_form_element::dateField('startDate', '2000-01-01','class="form-control" style="flex: 1;"'); ?>
                        <span><?php echo __('to'); ?></span>
                        <?php echo simbio_form_element::dateField('untilDate', date('Y-m-d'),'class="form-control" style="flex: 1."'); ?>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="workspace-form-actions">
                  <button type="submit" name="applyFilter" class="workspace-btn workspace-btn-primary">
                    <i class="fas fa-check"></i> <?php echo __('Apply Filter'); ?>
                  </button>
                  <input type="hidden" name="reportView" value="true" />
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- filter end -->
    <script>
    $(document).ready(function() {
        // Handle filter toggle functionality
        $('#reserveFilterToggle').click(function(e) {
            e.preventDefault();
            $(this).toggleClass('collapsed');
            $('#reserveFilterContent').collapse('toggle');

            // Toggle chevron icon
            if ($(this).hasClass('collapsed')) {
                $(this).find('.toggle-icon').css('transform', 'rotate(0deg)');
            } else {
                $(this).find('.toggle-icon').css('transform', 'rotate(180deg)');
            }
        });
    });
    </script>
    <div class="paging-area"><div class="pb-3 pr-3" id="pagingBox"></div></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
    $table_spec = 'reserve AS r
        LEFT JOIN biblio AS b ON r.biblio_id=b.biblio_id
        LEFT JOIN member AS m ON r.member_id=m.member_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->table_attr = 'class="s-table table table-sm table-bordered"';
    $reportgrid->setSQLColumn('r.item_code AS \''.__('Item Code').'\'',
        'b.title AS \''.__('Title').'\'',
        'm.member_name AS \''.__('Member Name').'\'',
        'm.member_id AS \''.__('Member ID').'\'',
        'r.reserve_date AS \''.__('Reserve Date').'\'');
    $reportgrid->setSQLorder('r.reserve_date DESC');

    // is there any search
    $criteria = 'r.reserve_id IS NOT NULL ';
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (b.title LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } else {
            $criteria .= ' AND (b.title LIKE \'%'.$keyword.'%\')';
        }
    }
    if (isset($_GET['itemCode']) AND !empty($_GET['itemCode'])) {
        $item_code = $dbs->escape_string(trim($_GET['itemCode']));
        $criteria .= ' AND i.item_code LIKE \'%'.$item_code.'%\'';
    }
    if (isset($_GET['member']) AND !empty($_GET['member'])) {
        $member = $dbs->escape_string($_GET['member']);
        $criteria .= ' AND (m.member_name LIKE \'%'.$member.'%\' OR m.member_id LIKE \'%'.$member.'%\')';
    }
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $criteria .= ' AND (TO_DAYS(r.reserve_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
            TO_DAYS(\''.$_GET['untilDate'].'\'))';
    }

    $reportgrid->setSQLCriteria($criteria);

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, 20);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'#pagingBox\').html(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SB.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
