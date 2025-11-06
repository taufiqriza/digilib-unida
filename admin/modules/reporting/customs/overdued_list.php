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
    // Get today's overdues count
    $overdue_count_q = $dbs->query("SELECT COUNT(*) FROM loan WHERE is_lent=1 AND is_return=0 AND due_date < CURDATE()");
    $overdue_count = $overdue_count_q->fetch_row()[0];
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
        <div class="workspace-hero__stats">
          <div class="workspace-stat">
            <div class="workspace-stat__value"><?php echo $overdue_count; ?></div>
            <div class="workspace-stat__label"><?php echo __('Overdue'); ?></div>
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

        /* Table row hover effect */
        .dataListPrinted tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12) !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .biblio-search-content form > div:first-child {
                grid-template-columns: 1fr !important;
            }
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
        })
    </script>
    <div class="paging-area" style="padding: 0 20px;"><div class="pb-3 pr-3" id="pagingBox"></div></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'] . '?reportView=true'; ?>"
            frameborder="0" style="width: 100%; min-height: 600px; border: none; background: #fff; border-radius: 8px;"></iframe>
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

        // Modern card design for member info
        $_buffer = '<div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%); border-left: 4px solid #dc3545; border-radius: 8px; padding: 16px; margin: 10px 0 16px 0; box-shadow: 0 2px 4px rgba(220,53,69,0.1);">';
        $_buffer .= '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">';
        $_buffer .= '<div style="background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: bold;">' . substr($member_name, 0, 1) . '</div>';
        $_buffer .= '<div style="flex: 1;">';
        $_buffer .= '<div style="font-weight: 600; color: #333; font-size: 15px;">' . $member_name . '</div>';
        $_buffer .= '<div style="color: #666; font-size: 13px;">ID: ' . $array_data[0] . '</div>';
        $_buffer .= '</div>';
        $_buffer .= '</div>';

        if (!empty($member_mail_address)) $_buffer .= '<div style="font-size: 13px; color: #555; margin-bottom: 6px;"><i class="fas fa-map-marker-alt" style="color: #dc3545; width: 16px;"></i> ' . htmlspecialchars($member_mail_address) . '</div>';
        if (!empty($member_email)) {
            $_buffer .= '<div style="font-size: 13px; color: #555; margin-bottom: 6px;"><i class="fas fa-envelope" style="color: #dc3545; width: 16px;"></i> ' . htmlspecialchars($member_email) . '</div>';
            $_buffer .= '<div id="' . $array_data[0] . 'emailStatus" style="margin-bottom: 8px;"></div>';
        }
        if (!empty($member_phone)) $_buffer .= '<div style="font-size: 13px; color: #555; margin-bottom: 10px;"><i class="fas fa-phone" style="color: #dc3545; width: 16px;"></i> ' . htmlspecialchars($member_phone) . '</div>';

        if (!empty($member_email)) {
            $_buffer .= '<a class="usingAJAX btn btn-sm btn-danger" href="' . MWB . 'membership/overdue_mail.php' . '" postdata="memberID=' . $array_data[0] . '" loadcontainer="' . $array_data[0] . 'emailStatus" style="text-decoration: none;"><i class="fa fa-paper-plane"></i> ' . __('Send Email Notification') . '</a>';
        }
        $_buffer .= '</div>';

        // Modern table design
        $_buffer .= '<table style="width: 100%; border-collapse: separate; border-spacing: 0 8px;">';
        $_buffer .= '<thead><tr style="background: #f8f9fa; color: #333; font-weight: 600; font-size: 13px;">';
        $_buffer .= '<th style="padding: 10px; text-align: left; border-radius: 6px 0 0 6px;">' . __('Item Code') . '</th>';
        $_buffer .= '<th style="padding: 10px; text-align: left;">' . __('Title') . '</th>';
        $_buffer .= '<th style="padding: 10px; text-align: center;">' . __('Overdue') . '</th>';
        $_buffer .= '<th style="padding: 10px; text-align: center;">' . __('Dates') . '</th>';
        $_buffer .= '<th style="padding: 10px; text-align: center; border-radius: 0 6px 6px 0;">' . __('Action') . '</th>';
        $_buffer .= '</tr></thead><tbody>';

        while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {
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

            $_buffer .= '<tr style="background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: transform 0.2s;">';
            $_buffer .= '<td style="padding: 12px; border-radius: 6px 0 0 6px; vertical-align: top;"><code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($ovd_title_d['item_code']) . '</code></td>';
            $_buffer .= '<td style="padding: 12px; vertical-align: top;"><div style="font-weight: 500; color: #333; margin-bottom: 4px;">' . htmlspecialchars($ovd_title_d['title']) . '</div><div style="font-size: 12px; color: #666;"><i class="fas fa-tag" style="width: 14px;"></i> ' . __('Price') . ': ' . $item_price_formatted . '</div></td>';
            $_buffer .= '<td style="padding: 12px; text-align: center; vertical-align: top;"><div style="background: #fff3cd; color: #856404; padding: 6px 10px; border-radius: 6px; font-weight: 600; margin-bottom: 6px; font-size: 13px;"><i class="fas fa-exclamation-triangle"></i> ' . $overdue_days_formatted . ' ' . __('days') . '</div><div style="background: #f8d7da; color: #721c24; padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 13px;">' . __('Fine') . ': ' . $fines . '</div></td>';
            $_buffer .= '<td style="padding: 12px; text-align: center; vertical-align: top; font-size: 12px;"><div style="margin-bottom: 4px;"><strong>' . __('Loan') . ':</strong> ' . $ovd_title_d['loan_date'] . '</div><div><strong>' . __('Due') . ':</strong> <span style="color: #dc3545;">' . $ovd_title_d['due_date'] . '</span></div></td>';

            $wa_text = urlencode('Assalamualaikum ' . $member_name . ', kami ingin menyampaikan bahwa ada pinjaman buku dengan keterlambatan *' . $overdue_days_formatted . ' hari* di Perpustakaan. Kode Barcode: *' . $ovd_title_d['item_code'] . '*, Judul: *' . $ovd_title_d['title'] . '*. Tanggal harus kembali: ' . $ovd_title_d['due_date'] . '. Denda: ' . $fines . '. Terima Kasih. ' . $sysconf['library_name']);
            $_buffer .= '<td style="padding: 12px; text-align: center; border-radius: 0 6px 6px 0; vertical-align: top;"><a class="btn btn-sm btn-success" href="https://wa.me/62' . preg_replace('/[^0-9]/', '', $member_phone) . '?text=' . $wa_text . '" target="_blank" style="white-space: nowrap; text-decoration: none;"><i class="fab fa-whatsapp"></i> WhatsApp</a></td>';
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
