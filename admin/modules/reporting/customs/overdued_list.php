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

/* Overdues Report */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// start the session
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';
require SIMBIO . 'simbio_UTILS/simbio_date.inc.php';
require MDLBS . 'membership/member_base_lib.inc.php';
require MDLBS . 'circulation/circulation_base_lib.inc.php';

// privileges checking
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . __('You don\'t have enough privileges to access this area!') . '</div>');
}

require SIMBIO . 'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO . 'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO . 'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO . 'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MDLBS . 'reporting/report_dbgrid.inc.php';

$page_title = 'Overdued List Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
    // Get today's overdues count and stats
    $overdue_count_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE is_lent=1 AND is_return=0 AND due_date < CURDATE()");
    $overdue_count = $overdue_count_q->fetch_row()[0];

    // Get unique members with overdues
    $overdue_members_q = $dbs->query("SELECT COUNT(DISTINCT member_id) FROM loan WHERE is_lent=1 AND is_return=0 AND due_date < CURDATE()");
    $overdue_members = $overdue_members_q->fetch_row()[0];
    ?>
    <!-- Include modern CSS and Font Awesome -->
    <link rel="stylesheet" href="<?php echo MWB; ?>circulation/circulation-modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- filter -->
    <div class="circulation-workspace">
      <!-- Workspace Header Hero -->
      <div class="workspace-hero">
        <div class="workspace-hero__icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="workspace-hero__text">
          <h2><?php echo __('Overdue List'); ?></h2>
          <p><?php echo __('Library Overdued Items Report'); ?></p>
        </div>
        <div class="workspace-hero__stats" style="display: flex; gap: 20px;">
          <div class="workspace-stat" style="text-align: center;">
            <div class="workspace-stat__value" style="font-size: 32px; font-weight: 700; color: #dc3545;"><?php echo $overdue_count; ?></div>
            <div class="workspace-stat__label" style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo __('Items'); ?></div>
          </div>
          <div class="workspace-stat" style="text-align: center;">
            <div class="workspace-stat__value" style="font-size: 32px; font-weight: 700; color: #ff6b6b;"><?php echo $overdue_members; ?></div>
            <div class="workspace-stat__label" style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo __('Members'); ?></div>
          </div>
        </div>
      </div>

      <!-- Workspace Surface -->
      <div class="workspace-surface">
        <div class="workspace-section">
          <!-- Compact Search/Filter like Bibliography -->
          <div class="biblio-search-card" style="background: #fff; border-radius: 12px; padding: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
              <div class="biblio-search-toggle collapsed" id="overdueFilterToggle" style="cursor: pointer; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-radius: 12px; transition: background 0.2s;">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <i class="fas fa-search" style="color: #1f3bb3;"></i>
                  <span style="font-weight: 500; color: #333;"><?php echo __('Search & Filter'); ?></span>
                </div>
                <i class="fas fa-chevron-down toggle-icon" style="color: #666; transition: transform 0.3s;"></i>
              </div>

              <div class="biblio-search-content collapse" id="overdueFilterContent" style="padding: 0 18px 18px 18px;">
                <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView" style="display: grid; gap: 14px;">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                      <label style="display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px;">
                        <?php echo __('Member ID') . '/' . __('Member Name'); ?>
                      </label>
                      <?php echo simbio_form_element::textField('text', 'id_name', '', 'class="form-control" placeholder="'.__('Search member...').'"'); ?>
                    </div>
                    <div>
                      <label style="display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px;">
                        <?php echo __('Records per page'); ?>
                      </label>
                      <input type="text" name="recsEachPage" class="form-control" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" style="width: 100px;"/>
                    </div>
                  </div>

                  <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 6px;">
                      <?php echo __('Loan Date Range'); ?>
                    </label>
                    <div id="range" style="display: flex; gap: 8px; align-items: center;">
                      <input type="text" name="startDate" value="2000-01-01" class="form-control" style="flex: 1;">
                      <span style="color: #999;"><?= __('to') ?></span>
                      <input type="text" name="untilDate" value="<?= date('Y-m-d') ?>" class="form-control" style="flex: 1;">
                    </div>
                  </div>

                  <div style="display: flex; gap: 8px; padding-top: 6px;">
                    <button type="submit" name="applyFilter" class="btn btn-primary" style="flex: 1;">
                      <i class="fas fa-search"></i> <?php echo __('Apply Filter'); ?>
                    </button>
                    <input type="hidden" name="reportView" value="true"/>
                  </div>
                </form>
              </div>
            </div>
        </div>
      </div>
    </div>
    <!-- filter end -->
    <style>
        /* Hover effect for toggle */
        .biblio-search-toggle:hover {
            background: #f8f9fa !important;
        }

        /* Table row hover effect - enhanced */
        #reportView table tbody tr:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            border-left-width: 4px !important;
        }

        /* Smooth transitions for table elements */
        #reportView table tbody tr {
            transition: all 0.2s ease;
        }

        #reportView table tbody tr td {
            transition: padding 0.2s ease;
        }

        /* Button hover effects */
        #reportView table tbody tr td a.btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(37,211,102,0.5) !important;
        }

        /* Badge pulse effect on hover */
        #reportView table tbody tr:hover span[style*="background: linear-gradient(135deg, #ffeaa7"] {
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.85; }
        }

        /* Scrollbar styling for table container */
        #reportView {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f7fafc;
        }

        #reportView::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        #reportView::-webkit-scrollbar-track {
            background: #f7fafc;
            border-radius: 4px;
        }

        #reportView::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        #reportView::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            #reportView table {
                font-size: 11px !important;
            }
            #reportView table th,
            #reportView table td {
                padding: 8px 10px !important;
            }
        }

        @media (max-width: 768px) {
            .biblio-search-content form > div:first-child {
                grid-template-columns: 1fr !important;
            }
            .workspace-hero__stats {
                flex-direction: column;
                gap: 10px !important;
            }
            #reportView table {
                font-size: 10px !important;
            }
            #reportView table th,
            #reportView table td {
                padding: 6px 8px !important;
            }
            /* Stack member info on mobile */
            #reportView > div > div[style*="background: linear-gradient(135deg, #fff5f5"] > div:first-child {
                flex-direction: column !important;
                align-items: flex-start !important;
            }
            #reportView > div > div[style*="background: linear-gradient(135deg, #fff5f5"] > div:first-child > div:last-child {
                width: 100%;
                justify-content: flex-start !important;
            }
        }

        /* Hide record count info, print button, and table header - comprehensive */
        #reportView .info,
        #reportView .infoBox,
        #reportView .printPageInfo,
        #reportView .s-print__page-info,
        #reportView div[style*="background-color: #d5e5f7"],
        #reportView div[style*="background: #d5e5f7"],
        #reportView div[style*="background-color:#d5e5f7"],
        #reportView > div > div:first-child,
        #reportView table.dataListPrinted > thead > tr:first-child,
        iframe[name="reportView"] + div > div:first-child {
            display: none !important;
        }

        /* Remove padding from iframe content */
        #reportView > div,
        #reportView body > div:first-child {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        /* Hide any blue info boxes and print buttons */
        #reportView div[class*="info"],
        #reportView div[class*="Info"],
        #reportView a[class*="printReport"],
        #reportView .printReport {
            display: none !important;
        }

        /* Force hide the first div that contains record info */
        #reportView > div > div:first-of-type {
            display: none !important;
        }

        /* Print styles */
        @media print {
            #reportView table {
                font-size: 9px !important;
            }
            #reportView table tbody tr {
                page-break-inside: avoid;
            }
            .workspace-hero,
            .biblio-search-card,
            .paging-area {
                display: none !important;
            }
        }

        /* Loading animation */
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        .loading-shimmer {
            animation: shimmer 2s infinite;
            background: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
            background-size: 1000px 100%;
        }
    </style>
    <script>
        $(document).ready(function(){
            const elem = document.getElementById('range');
            const dateRangePicker = new DateRangePicker(elem, {
                language: '<?= substr($sysconf['default_lang'], 0,2) ?>',
                format: 'yyyy-mm-dd',
            });

            // Handle filter toggle functionality
            $('#overdueFilterToggle').click(function(e) {
                e.preventDefault();
                $(this).toggleClass('collapsed');
                $('#overdueFilterContent').collapse('toggle');

                // Toggle chevron icon
                if ($(this).hasClass('collapsed')) {
                    $(this).find('.toggle-icon').css('transform', 'rotate(0deg)');
                } else {
                    $(this).find('.toggle-icon').css('transform', 'rotate(180deg)');
                }
            });

            // Add loading indicator for iframe
            const iframe = document.getElementById('reportView');
            const loadingIndicator = $('<div class="text-center" style="padding: 40px; color: #666;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #1f3bb3; margin-bottom: 10px;"></i><div style="font-size: 14px; font-weight: 500;">Loading overdue data...</div></div>');

            iframe.addEventListener('load', function() {
                loadingIndicator.fadeOut();
            });

            // Add smooth scroll to results
            $('button[name="applyFilter"]').click(function() {
                setTimeout(function() {
                    $('html, body').animate({
                        scrollTop: $('#reportView').offset().top - 20
                    }, 500);
                }, 100);
            });
        })
    </script>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'] . '?reportView=true'; ?>"
            frameborder="0" style="width: 100%; min-height: 600px; border: none; background: #fff; border-radius: 8px; margin-bottom: 20px;"></iframe>
    <div class="paging-area" style="padding: 0 20px;"><div class="pb-3 pr-3" id="pagingBox"></div></div>
  <?php
} else {
    ob_start();

    // Initialize date criteria globally for callback function
    global $date_criteria;
    $date_criteria = '';

    // table spec - start from loan table since we need overdue items
    $table_spec = 'loan AS l
      LEFT JOIN member AS m ON l.member_id=m.member_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('l.member_id AS \'' . __('Member ID') . '\'');
    $reportgrid->setSQLorder('MIN(l.due_date) ASC');  // Use MIN() for GROUP BY compatibility
    $reportgrid->sql_group_by = 'l.member_id';

    // Build overdue criteria - items that are currently on loan and overdue
    $overdue_criteria = ' l.is_lent=1 AND l.is_return=0 AND l.due_date < \'' . date('Y-m-d') . '\' ';

    // is there any search
    if (isset($_GET['id_name']) and $_GET['id_name']) {
        $keyword = $dbs->escape_string(trim($_GET['id_name']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' (';
            foreach ($words as $word) {
                $concat_sql .= " (m.member_id LIKE '%$word%' OR m.member_name LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $overdue_criteria .= ' AND ' . $concat_sql;
        } else {
            $overdue_criteria .= " AND (m.member_id LIKE '%$keyword%' OR m.member_name LIKE '%$keyword%')";
        }
    }

    // loan date filter
    if (isset($_GET['startDate']) and isset($_GET['untilDate'])) {
        $date_criteria = ' AND l.loan_date BETWEEN \'' . $dbs->escape_string($_GET['startDate']) . '\' AND \'' . $dbs->escape_string($_GET['untilDate']) . '\'';
        $overdue_criteria .= $date_criteria;
    }

    // records per page
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer) $_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200) ? $recsEachPage : $num_recs_show;
    }

    $reportgrid->setSQLCriteria($overdue_criteria);

    // set table and table header attributes
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
    $reportgrid->column_width = array('1' => '80%');

    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria, $sysconf;

        // Initialize circulation object with required properties
        $circulation = new circulation($obj_db, $array_data[0]);

        // Set properties if they exist in sysconf/session
        if (property_exists($circulation, 'ignore_holidays_fine_calc')) {
            $circulation->ignore_holidays_fine_calc = isset($sysconf['ignore_holidays_fine_calc']) ? $sysconf['ignore_holidays_fine_calc'] : false;
        }
        if (property_exists($circulation, 'holiday_dayname')) {
            $circulation->holiday_dayname = isset($_SESSION['holiday_dayname']) ? $_SESSION['holiday_dayname'] : array();
        }
        if (property_exists($circulation, 'holiday_date')) {
            $circulation->holiday_date = isset($_SESSION['holiday_date']) ? $_SESSION['holiday_date'] : array();
        }

        // member name
        $member_q = $obj_db->query('SELECT m.member_name, m.member_email, m.member_phone, m.member_mail_address,
                                           IFNULL(CAST(mmt.fine_each_day AS DECIMAL(10,2)), 0) as fine_each_day
                                           FROM member m
                                           LEFT JOIN mst_member_type mmt on m.member_type_id = mmt.member_type_id
                                           WHERE m.member_id=\'' . $obj_db->escape_string($array_data[0]) . '\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        $member_email = $member_d[1];
        $member_phone = $member_d[2];
        $member_mail_address = $member_d[3];

        // Safely convert fine to float - handle string values like '-'
        $member_fine_raw = $member_d[4];
        if (is_numeric($member_fine_raw)) {
            $member_fine_per_day = floatval($member_fine_raw);
        } else {
            $member_fine_per_day = 0; // Default to 0 if not numeric
        }

        unset($member_q);

        // Get overdue items for this member
        $today = date('Y-m-d');
        $ovd_sql = 'SELECT l.loan_id, l.item_code,
          IFNULL(CAST(i.price AS DECIMAL(10,2)), 0) as price,
          i.price_currency,
          b.title, l.loan_date, l.due_date,
          DATEDIFF(CURDATE(), l.due_date) AS overdue_days,
          IFNULL(CAST(mlr.fine_each_day AS DECIMAL(10,2)), 0) as fine_each_day
          FROM loan AS l
              LEFT JOIN item AS i ON l.item_code=i.item_code
              LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
              LEFT JOIN mst_loan_rules mlr on l.loan_rules_id = mlr.loan_rules_id
          WHERE l.is_lent=1 AND l.is_return=0 AND l.due_date < \'' . $today . '\'
            AND l.member_id=\'' . $obj_db->escape_string($array_data[0]) . '\'';

        if (!empty($date_criteria)) {
            $ovd_sql .= $date_criteria;
        }

        $ovd_title_q = $obj_db->query($ovd_sql);

        // Calculate totals for this member
        $total_items = $ovd_title_q->num_rows;
        $total_fines = 0;
        $max_overdue_days = 0;

        // Store results for display
        $overdue_items = [];
        while ($row = $ovd_title_q->fetch_assoc()) {
            $overdue_items[] = $row;

            // Calculate overdue days
            $overdue_days = intval($row['overdue_days']);
            if ($overdue_days > $max_overdue_days) {
                $max_overdue_days = $overdue_days;
            }

            // Calculate fine
            $fine_from_rule = $row['fine_each_day'];
            if (is_numeric($fine_from_rule) && floatval($fine_from_rule) > 0) {
                $fine_per_day = floatval($fine_from_rule);
            } else {
                $fine_per_day = floatval($member_fine_per_day);
            }
            $total_fines += floatval($overdue_days) * floatval($fine_per_day);
        }

        // Modern compact horizontal card design for member info
        $_buffer = '<div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%); border-left: 4px solid #dc3545; border-radius: 8px; padding: 14px 16px; margin: 10px 0 14px 0; box-shadow: 0 2px 4px rgba(220,53,69,0.1);">';

        // Header row with avatar, name, summary stats, and email button - all in one line
        $_buffer .= '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 12px;">';

        // Left side: Avatar and name with contact info
        $_buffer .= '<div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">';
        $_buffer .= '<div style="background: #dc3545; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; flex-shrink: 0;">' . substr($member_name, 0, 1) . '</div>';
        $_buffer .= '<div style="min-width: 0; flex: 1;">';
        $_buffer .= '<div style="font-weight: 600; color: #333; font-size: 14px; margin-bottom: 4px;">' . $member_name . '</div>';

        // ID, Phone, Email in one line - compact horizontal
        $_buffer .= '<div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 10px; color: #666; align-items: center;">';
        $_buffer .= '<div style="display: flex; align-items: center; gap: 4px;"><span style="font-weight: 500;">ID:</span> <span style="font-family: monospace; background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px;">' . $array_data[0] . '</span></div>';

        if (!empty($member_phone)) {
            $_buffer .= '<div style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-phone" style="color: #dc3545; font-size: 9px;"></i><span>' . htmlspecialchars($member_phone) . '</span></div>';
        }
        if (!empty($member_email)) {
            $_buffer .= '<div style="display: flex; align-items: center; gap: 4px;"><i class="fas fa-envelope" style="color: #dc3545; font-size: 9px;"></i><span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;" title="' . htmlspecialchars($member_email) . '">' . htmlspecialchars($member_email) . '</span></div>';
        }

        $_buffer .= '</div>';
        $_buffer .= '</div>';
        $_buffer .= '</div>';

        // Right side: Summary stats badges + email button
        $_buffer .= '<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; flex-shrink: 0;">';
        $_buffer .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 5px 10px; border-radius: 6px; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(102,126,234,0.3); white-space: nowrap;"><i class="fas fa-book"></i> <span>' . $total_items . ' ' . ($total_items > 1 ? __('Items') : __('Item')) . '</span></div>';
        $_buffer .= '<div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; padding: 5px 10px; border-radius: 6px; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(245,87,108,0.3); white-space: nowrap;"><i class="fas fa-money-bill-wave"></i> <span>' . currency($total_fines) . '</span></div>';
        $_buffer .= '<div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #fff; padding: 5px 10px; border-radius: 6px; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(250,112,154,0.3); white-space: nowrap;"><i class="fas fa-clock"></i> <span>' . $max_overdue_days . ' ' . __('days') . '</span></div>';

        // Email button
        if (!empty($member_email)) {
            $_buffer .= '<a class="usingAJAX btn btn-sm" href="' . MWB . 'membership/overdue_mail.php' . '" postdata="memberID=' . $array_data[0] . '" loadcontainer="' . $array_data[0] . 'emailStatus" style="text-decoration: none; background: #dc3545; color: #fff; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(220,53,69,0.3); white-space: nowrap;"><i class="fa fa-paper-plane"></i> ' . __('Email') . '</a>';
        }
        $_buffer .= '</div>';

        // Address in second row if exists (since it can be long)
        if (!empty($member_mail_address)) {
            $_buffer .= '<div style="display: flex; align-items: center; gap: 6px; font-size: 10px; color: #666; margin-top: 6px; padding-left: 50px;"><i class="fas fa-map-marker-alt" style="color: #dc3545; font-size: 9px; flex-shrink: 0;"></i><span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . htmlspecialchars($member_mail_address) . '">' . htmlspecialchars($member_mail_address) . '</span></div>';
        }

        // Email status container
        if (!empty($member_email)) {
            $_buffer .= '<div id="' . $array_data[0] . 'emailStatus" style="margin-top: 8px;"></div>';
        }

        $_buffer .= '</div>';

        // Modern compact table design with horizontal layout
        $_buffer .= '<table style="width: 100%; border-collapse: separate; border-spacing: 0 6px; font-size: 13px;">';
        $_buffer .= '<thead><tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">';
        $_buffer .= '<th style="padding: 10px 12px; text-align: left; border-radius: 6px 0 0 6px; width: 10%;">' . __('Item Code') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: left; width: 30%;">' . __('Title') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: left; width: 12%;">' . __('Dates') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: center; width: 10%;">' . __('Overdue') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: center; width: 10%;">' . __('Fine') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: center; width: 10%;">' . __('Price') . '</th>';
        $_buffer .= '<th style="padding: 10px 12px; text-align: center; border-radius: 0 6px 6px 0; width: 10%;">' . __('Action') . '</th>';
        $_buffer .= '</tr></thead><tbody>';

        foreach ($overdue_items as $ovd_title_d) {
            //calculate Fines - use overdue_days from query
            $overdue_days = intval($ovd_title_d['overdue_days']);

            // Calculate fine - use grace period if available
            $overdue_result = $circulation->countOverdueValue($ovd_title_d['loan_id'], date('Y-m-d'));
            if (is_array($overdue_result) && isset($overdue_result['days'])) {
                $overdue_days = intval($overdue_result['days']);
            }

            // Calculate fines amount - ensure all values are numeric and valid
            // Get fine per day - ensure it's numeric
            $fine_from_rule = $ovd_title_d['fine_each_day'];
            if (is_numeric($fine_from_rule) && floatval($fine_from_rule) > 0) {
                $fine_per_day = floatval($fine_from_rule);
            } else {
                $fine_per_day = floatval($member_fine_per_day);
            }

            // Calculate total fines
            $fines_amount = floatval($overdue_days) * floatval($fine_per_day);

            // Ensure fines amount is valid before passing to currency
            if (!is_numeric($fines_amount) || $fines_amount < 0) {
                $fines_amount = 0;
            }
            $fines = currency($fines_amount);

            // Format overdue days
            $overdue_days_formatted = number_format($overdue_days, 0, ',', '.');

            // Safely get price - ensure numeric
            $item_price = is_numeric($ovd_title_d['price']) ? floatval($ovd_title_d['price']) : 0;
            $item_price_formatted = currency($item_price);

            $_buffer .= '<tr style="background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: all 0.2s ease; border-left: 3px solid #dc3545;">';

            // Item Code - more compact
            $_buffer .= '<td style="padding: 10px 12px; border-radius: 6px 0 0 6px; vertical-align: middle;"><code style="background: #e8eaf6; color: #3f51b5; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">' . htmlspecialchars($ovd_title_d['item_code']) . '</code></td>';

            // Title - compact single line with truncation
            $_buffer .= '<td style="padding: 10px 12px; vertical-align: middle;"><div style="font-weight: 500; color: #333; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . htmlspecialchars($ovd_title_d['title']) . '">' . htmlspecialchars($ovd_title_d['title']) . '</div></td>';

            // Dates - horizontal compact format
            $_buffer .= '<td style="padding: 10px 12px; vertical-align: middle; font-size: 11px; line-height: 1.6;">';
            $_buffer .= '<div style="display: flex; flex-direction: column; gap: 2px;">';
            $_buffer .= '<div><span style="color: #666; font-weight: 500;">Loan:</span> <span style="color: #333;">' . date('d/m/y', strtotime($ovd_title_d['loan_date'])) . '</span></div>';
            $_buffer .= '<div><span style="color: #666; font-weight: 500;">Due:</span> <span style="color: #dc3545; font-weight: 600;">' . date('d/m/y', strtotime($ovd_title_d['due_date'])) . '</span></div>';
            $_buffer .= '</div></td>';

            // Overdue Days - compact badge
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; vertical-align: middle;"><span style="background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); color: #d63031; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; display: inline-block; min-width: 50px; box-shadow: 0 2px 4px rgba(253,203,110,0.3);">' . $overdue_days_formatted . '</span><div style="font-size: 10px; color: #666; margin-top: 4px;">' . __('days') . '</div></td>';

            // Fine - compact badge
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; vertical-align: middle;"><span style="background: linear-gradient(135deg, #ff7675 0%, #d63031 100%); color: #fff; padding: 6px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block; box-shadow: 0 2px 4px rgba(214,48,49,0.3);">' . $fines . '</span></td>';

            // Price - compact
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; vertical-align: middle;"><span style="background: #f0f0f0; color: #555; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block;">' . $item_price_formatted . '</span></td>';

            // Action - compact button
            $wa_text = urlencode('Assalamualaikum ' . $member_name . ', kami ingin menyampaikan bahwa ada pinjaman buku dengan keterlambatan *' . $overdue_days_formatted . ' hari* di Perpustakaan. Kode Barcode: *' . $ovd_title_d['item_code'] . '*, Judul: *' . $ovd_title_d['title'] . '*. Tanggal harus kembali: ' . $ovd_title_d['due_date'] . '. Denda: ' . $fines . '. Terima Kasih. ' . $sysconf['library_name']);
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; border-radius: 0 6px 6px 0; vertical-align: middle;"><a class="btn btn-sm" href="https://wa.me/62' . preg_replace('/[^0-9]/', '', $member_phone) . '?text=' . $wa_text . '" target="_blank" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: #fff; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; box-shadow: 0 2px 4px rgba(37,211,102,0.3);"><i class="fab fa-whatsapp"></i> WA</a></td>';
            $_buffer .= '</tr>';
        }

        // Add summary footer row if there are items
        if (count($overdue_items) > 0) {
            $_buffer .= '<tr style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-weight: 700; border-top: 2px solid #dee2e6;">';
            $_buffer .= '<td colspan="4" style="padding: 10px 12px; text-align: right; border-radius: 6px 0 0 6px; font-size: 12px; color: #495057;">' . __('Total for this member') . ':</td>';
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; font-size: 12px; color: #495057;"><span style="background: #ffc107; color: #000; padding: 6px 12px; border-radius: 20px; font-weight: 700; display: inline-block;">' . number_format($max_overdue_days, 0, ',', '.') . '</span></td>';
            $_buffer .= '<td style="padding: 10px 12px; text-align: center; font-size: 12px; color: #495057;"><span style="background: #dc3545; color: #fff; padding: 6px 10px; border-radius: 6px; font-weight: 700; display: inline-block;">' . currency($total_fines) . '</span></td>';
            $_buffer .= '<td colspan="2" style="padding: 10px 12px; border-radius: 0 6px 6px 0; text-align: center; font-size: 11px; color: #6c757d;"><i class="fas fa-book"></i> ' . $total_items . ' ' . ($total_items > 1 ? __('items') : __('item')) . '</td>';
            $_buffer .= '</tr>';
        }

        $_buffer .= '</tbody></table>';
        return $_buffer;
    }

    // modify column value
    $reportgrid->modifyColumnContent(0, 'callback{showOverduedList}');

    // put the result into variables
    echo '<div style="padding: 20px; background: #fff; min-height: 300px;">';

    try {
        $datagrid_result = $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

        // Check if there's any data
        if ($reportgrid->num_rows < 1) {
            echo '<div style="text-align: center; padding: 60px 20px; color: #666;">';
            echo '<i class="fas fa-check-circle" style="font-size: 64px; color: #28a745; margin-bottom: 20px;"></i>';
            echo '<h3 style="color: #333; margin-bottom: 10px;">'.__('No Overdue Items').'</h3>';
            echo '<p style="font-size: 14px;">'.__('Great! There are currently no overdue items in the system.').'</p>';
            echo '</div>';
        } else {
            echo $datagrid_result;
        }
    } catch (Exception $e) {
        echo '<div style="text-align: center; padding: 40px 20px; color: #dc3545;">';
        echo '<i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px;"></i>';
        echo '<h3 style="margin-bottom: 10px;">Error Loading Data</h3>';
        echo '<p style="font-size: 13px; background: #f8f9fa; padding: 15px; border-radius: 6px; max-width: 600px; margin: 0 auto;">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }

    echo '</div>';

    ?>
    <script type="text/javascript" src="<?php echo JWB . 'jquery.js'; ?>"></script>
    <script type="text/javascript" src="<?php echo JWB . 'updater.js'; ?>"></script>
    <script type="text/javascript">
        // registering event for send email button
        $(document).ready(function () {
            parent.$('#pagingBox').html('<?php echo str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set) ?>');
            $('a.usingAJAX').click(function (evt) {
                evt.preventDefault();
                var anchor = $(this);
                // get anchor href
                var url = anchor.attr('href');
                var postData = anchor.attr('postdata');
                var loadContainer = anchor.attr('loadcontainer');
                if (loadContainer) {
                    container = jQuery('#' + loadContainer);
                    container.html('<div class="alert alert-info">Please wait....</div>');
                }
                // set ajax
                if (postData) {
                    container.simbioAJAX(url, {method: 'post', addData: postData});
                } else {
                    container.simbioAJAX(url, {addData: {ajaxload: 1}});
                }
            });
        });
    </script>
  <?php

    $content = ob_get_clean();
    // include the page template
    require SB . '/admin/' . $sysconf['admin_template']['dir'] . '/printed_page_tpl.php';
}
