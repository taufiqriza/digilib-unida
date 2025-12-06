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

/* LOAN HISTORY LIST IFRAME CONTENT */

// key to authenticate
define('INDEX_AUTH', '1');
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

require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';

// page title
$page_title = 'Member Loan List';

// Include modern CSS and Font Awesome
echo '<link rel="stylesheet" href="'.MWB.'circulation/circulation-modern.css">';
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';

// start the output buffering
ob_start();
// check if there is member ID
if (isset($_SESSION['memberID']) AND !empty($_SESSION['memberID'])) {
    /* LOAN HISTORY LIST */
    $memberID = trim($_SESSION['memberID']);

    // Get member stats
    $total_loans_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE member_id='".$dbs->escape_string($memberID)."'");
    $total_loans = $total_loans_q->fetch_row()[0];

    $active_loans_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE member_id='".$dbs->escape_string($memberID)."' AND is_return=0 AND is_lent=1");
    $active_loans = $active_loans_q->fetch_row()[0];
    ?>

    <div class="circulation-workspace">
        <!-- Workspace Header Hero -->
        <div class="workspace-hero">
            <div class="workspace-hero__icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="workspace-hero__text">
                <h2><?php echo __('Loan History'); ?></h2>
                <p><?php echo __('Complete Member Lending History'); ?></p>
            </div>
            <div class="workspace-hero__stats">
                <div class="workspace-stat">
                    <div class="workspace-stat__value"><?php echo $total_loans; ?></div>
                    <div class="workspace-stat__label"><?php echo __('Total'); ?></div>
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
                <!-- Collapsible Search Filter -->
                <div class="biblio-search-card" style="background: #fff; border-radius: 12px; padding: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                    <div class="biblio-search-toggle collapsed" id="loanHistSearchToggle" style="cursor: pointer; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-radius: 12px; transition: background 0.2s;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-search" style="color: #3b82f6;"></i>
                            <span style="font-weight: 500; color: #333;"><?php echo __('Search History'); ?></span>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon" style="color: #666; transition: transform 0.3s;"></i>
                    </div>

                    <div class="biblio-search-content collapse" id="loanHistSearchContent" style="padding: 0 18px 18px 18px;">
                        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: grid; gap: 12px;">
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px;">
                                    <i class="fas fa-search"></i> <?php echo __('Search'); ?>
                                </label>
                                <input type="text" name="keywords" class="form-control" placeholder="<?php echo __('Search by item code or title...'); ?>">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-search"></i> <?php echo __('Search'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php
                // table spec
                $table_spec = 'loan AS l
                    LEFT JOIN item AS i ON l.item_code=i.item_code
                    LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

                // create datagrid
                $datagrid = new simbio_datagrid();
                $datagrid->setSQLColumn(
                    'l.item_code AS \''.__('Item Code').'\'',
                    'b.title AS \''.__('Title').'\'',
                    'l.loan_date AS \''.__('Loan Date').'\'',
                    'IF(is_return = 0, \'<span class="badge badge-warning"><i class="fas fa-clock"></i> '.__('Not Returned Yet').'</span>\', return_date) AS \''.__('Returned Date').'\'');
                $datagrid->setSQLorder("l.loan_date DESC");

                $criteria = 'l.member_id=\''.$dbs->escape_string($memberID).'\' ';
                // is there any search
                if (isset($_GET['keywords']) AND $_GET['keywords']) {
                    $keyword = $dbs->escape_string($_GET['keywords']);
                    $criteria .= " AND (l.item_code LIKE '%$keyword%' OR b.title LIKE '%$keyword%')";
                }
                $datagrid->setSQLCriteria($criteria);

                // set table and table header attributes
                $datagrid->table_attr = 'id="dataList" class="s-table table" style="width: 100%;"';
                $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
                $datagrid->icon_edit = SWB.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
                // special properties
                $datagrid->using_AJAX = false;
                $datagrid->column_width = array(1 => '70%');
                $datagrid->disableSort('Returned Date');

                // put the result into variables
                $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, false);
                if (isset($_GET['keywords']) AND $_GET['keywords']) {
                    $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
                    echo '<div class="infoBox">'.$msg.' : "'.htmlentities($_GET['keywords']).'"</div>';
                }

                echo $datagrid_result;
                ?>
            </div>
        </div>
    </div>

    <style>
        .biblio-search-toggle:hover {
            background: #f8f9fa !important;
        }
    </style>

    <script>
        $(document).ready(function() {
            $('#loanHistSearchToggle').click(function(e) {
                e.preventDefault();
                $(this).toggleClass('collapsed');
                $('#loanHistSearchContent').collapse('toggle');

                if ($(this).hasClass('collapsed')) {
                    $(this).find('.toggle-icon').css('transform', 'rotate(0deg)');
                } else {
                    $(this).find('.toggle-icon').css('transform', 'rotate(180deg)');
                }
            });
        });
    </script>

    <?php
} else {
    echo '<div class="circulation-workspace"><div class="workspace-surface"><div class="errorBox">No member session found.</div></div></div>';
}

// get the buffered content
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
