<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modified for Excel output (C) 2010 by Wardiyono (wynerst@gmail.com)
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
/* Loan History By Members */

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
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS.'reporting/report_dbgrid.inc.php';

$membershipTypes = membershipApi::getMembershipType($dbs);
$page_title = 'Loan History Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

// Get total loans count
$total_loans_q = $dbs->query("SELECT COUNT(*) FROM loan");
$total_loans = $total_loans_q->fetch_row()[0];

if (!$reportView) {
?>
    <!-- Include modern CSS and Font Awesome -->
    <link rel="stylesheet" href="<?php echo MWB; ?>circulation/circulation-modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- filter -->
    <div class="circulation-workspace">
      <div class="workspace-hero">
        <div class="workspace-hero__icon">
          <i class="fas fa-history"></i>
        </div>
        <div class="workspace-hero__text">
          <h2><?php echo __('Loan History'); ?></h2>
          <p><?php echo __('Comprehensive Loan History Report'); ?></p>
        </div>
        <div class="workspace-hero__stats">
          <div class="workspace-stat">
            <div class="workspace-stat__value"><?php echo $total_loans; ?></div>
            <div class="workspace-stat__label"><?php echo __('Total'); ?></div>
          </div>
        </div>
      </div>

      <div class="workspace-surface">
        <div class="workspace-section">
          <div class="workspace-section-header">
            <h3><i class="fas fa-filter"></i> <?php echo __('Report Filter'); ?></h3>
            <p><?php echo __('Configure filters to generate loan history report'); ?></p>
          </div>

          <div class="workspace-form-card">
            <!-- Collapsible Filter Toggle -->
            <div class="workspace-search-toggle collapsed" id="loanHistoryFilterToggle" style="cursor: pointer; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-sliders-h"></i>
                <span style="font-weight: 500;"><?php echo __('Filter Options'); ?></span>
              </div>
              <i class="fas fa-chevron-down toggle-icon" style="transition: transform 0.3s;"></i>
            </div>

            <div class="workspace-search-content collapse" id="loanHistoryFilterContent">
              <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
                <div id="filterForm">
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Member ID').'/'.__('Member Name'); ?></label>
                    <div class="workspace-form-control">
                      <?php echo simbio_form_element::textField('text', 'id_name', '', 'class="form-control" style="width: 100%;"'); ?>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Membership Type'); ?></label>
                    <div class="workspace-form-control">
                      <select name="membershipType" class="form-control" style="width: 100%;">
                      <?php
                      foreach ($membershipTypes as $key => $membershipType) {
                      echo '<option value="'.$membershipType['member_type_name'].'">'.$membershipType['member_type_name'].'</option>';
                      }
                      ?>
                      </select>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Title'); ?></label>
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
                    <label class="workspace-form-label"><?php echo __('Loan Date Range'); ?></label>
                    <div class="workspace-form-control">
                      <div id="range">
                        <input type="text" name="startDate" value="2000-01-01" class="form-control">
                        <span style="margin: 0 8px;"><?= __('to') ?></span>
                        <input type="text" name="untilDate" value="<?= date('Y-m-d') ?>" class="form-control">
                      </div>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Loan Status'); ?></label>
                    <div class="workspace-form-control">
                      <select name="loanStatus" class="form-control" style="width: 100%;">
                        <option value="ALL"><?php echo __('ALL'); ?></option>
                        <option value="0"><?php echo __('On Loan'); ?></option>
                        <option value="1"><?php echo __('Returned'); ?></option>
                      </select>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Location'); ?></label>
                    <div class="workspace-form-control">
                      <?php
                      $loc_q = $dbs->query('SELECT location_id, location_name FROM mst_location');
                      $loc_options = array();
                      $loc_options[] = array('', __('ALL'));
                      while ($loc_d = $loc_q->fetch_row()) {
                          $loc_options[] = array($loc_d[1], $loc_d[1]);
                      }
                      echo simbio_form_element::selectList('location', $loc_options,'','class="form-control" style="width: 100%;"');
                      ?>
                    </div>
                  </div>
                  <div class="workspace-form-group">
                    <label class="workspace-form-label"><?php echo __('Record each page'); ?></label>
                    <div class="workspace-form-control">
                      <input type="text" name="recsEachPage" size="3" maxlength="3" class="form-control" value="<?php echo $num_recs_show; ?>" style="width: 120px; display: inline-block;" />
                      <span class="form-text"><?php echo __('Set between 20 and 200'); ?></span>
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
    <script>
        $(document).ready(function(){
            const elem = document.getElementById('range');
            const dateRangePicker = new DateRangePicker(elem, {
                language: '<?= substr($sysconf['default_lang'], 0,2) ?>',
                format: 'yyyy-mm-dd',
            });

            // Handle filter toggle functionality
            $('#loanHistoryFilterToggle').click(function(e) {
                e.preventDefault();
                $(this).toggleClass('collapsed');
                $('#loanHistoryFilterContent').collapse('toggle');

                // Toggle chevron icon
                if ($(this).hasClass('collapsed')) {
                    $(this).find('.toggle-icon').css('transform', 'rotate(0deg)');
                } else {
                    $(this).find('.toggle-icon').css('transform', 'rotate(180deg)');
                }
            });
        })
    </script>
    <!-- filter end -->
    <div class="paging-area"><div class="pt-3 pr-3" id="pagingBox"></div></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
    $table_spec = 'loan_history';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->table_attr = 'class="s-table table table-sm table-bordered"';

    $reportgrid->setSQLColumn('member_id AS \''.__('Member ID').'\'',
        'member_name AS \''.__('Member Name').'\'',
        'member_type_name AS \''.__('Membership Type').'\'',
        'item_code AS \''.__('Item Code').'\'',
        'title AS \''.__('Title').'\'',
        'loan_date AS \''.__('Loan Date').'\'',
        'due_date AS \''.__('Due Date').'\'', 'is_return AS \''.__('Loan Status').'\'');
    $reportgrid->setSQLorder('loan_date DESC');

    $criteria = 'member_id IS NOT NULL ';
    if (isset($_GET['id_name']) AND !empty($_GET['id_name'])) {
        $id_name = utility::filterData('id_name', 'get', true, true, true);
        $criteria .= ' AND (member_id LIKE \'%'.$id_name.'%\' OR member_name LIKE \'%'.$id_name.'%\')';
    }
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
        $keyword = utility::filterData('title', 'get', true, true, true);
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (title LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } else {
            $criteria .= ' AND title LIKE \'%'.$keyword.'%\'';
        }
    }
    if (isset($_GET['itemCode']) AND !empty($_GET['itemCode'])) {
        $item_code = utility::filterData('itemCode', 'get', true, true, true);
        $criteria .= ' AND item_code=\''.$item_code.'\'';
    }
    // loan date
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $criteria .= ' AND (TO_DAYS(loan_date) BETWEEN TO_DAYS(\''.utility::filterData('startDate', 'get', true, true, true).'\') AND
           TO_DAYS(\''.utility::filterData('untilDate', 'get', true, true, true).'\'))';
    }
    // loan status
    if (isset($_GET['loanStatus']) AND $_GET['loanStatus'] != 'ALL') {
        $loanStatus = (integer)utility::filterData('loanStatus', 'get', true, true, true);
        $criteria .= ' AND is_return='.$loanStatus;
    }

    if ((isset($_GET['membershipType'])) AND ($_GET['membershipType'] != 'All')) {
        $membershipType = utility::filterData('membershipType', 'get', true, true, true);
        $criteria .= ' AND member_type_name LIKE \''.$membershipType.'\'';
    }else{
        $criteria .= ' AND member_type_name LIKE \'%%\'';
    }
	
    // item location	
    if (isset($_GET['location']) AND !empty($_GET['location'])) {
        $location = utility::filterData('location', 'get', true, true, true);
        $criteria .= ' AND location_name LIKE \''.$location.'\'';
    }
	
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)utility::filterData('recsEachPage', 'get', true, true, true);
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    $reportgrid->setSQLCriteria($criteria);

   // callback function to show loan status
    function loanStatus($obj_db, $array_data)
    {
        if ($array_data[7] == 0) {
            return '<strong>'.__('On Loan').'</strong>';
        } else {
            return __('Returned');
        }
    }

    // modify column value
    $reportgrid->modifyColumnContent(7, 'callback{loanStatus}');

    // show spreadsheet export button
    $reportgrid->show_spreadsheet_export = true;

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'#pagingBox\').html(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';
	$xlsquery = 'SELECT member_id AS \''.__('Member ID').'\''.
        ', member_name AS \''.__('Member Name').'\''.
        ', item_code AS \''.__('Item Code').'\''.
        ', title AS \''.__('Title').'\''.
        ', loan_date AS \''.__('Loan Date').'\''.
        ', due_date AS \''.__('Due Date').'\', is_return AS \''.__('Loan Status').'\''.
		' FROM '.$table_spec.' WHERE '.$criteria;

		unset($_SESSION['xlsdata']);
		$_SESSION['xlsquery'] = $xlsquery;
		$_SESSION['tblout'] = "loan_history";

	//echo '<div class="s-export"><a href="../xlsoutput.php" class="s-btn btn btn-default">'.__('Export to spreadsheet format').'</a></div>';

    $content = ob_get_clean();
    // include the page template
    require SB.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
?>
