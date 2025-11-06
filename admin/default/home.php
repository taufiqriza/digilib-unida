<?php
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    include_once '../../sysconfig.inc.php';
}
?>

<head>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body, html {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        .content {
            padding: 0;
        }
        .menuBox.adminHome {
            display: none;
        }
    </style>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Inter', 'sans-serif'],
            },
            colors: {
              primary: {
                50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a', 950: '#172554',
              }
            }
          }
        }
      }
    </script>
</head>

<div class="w-full min-h-screen bg-slate-50">
    
    <div class_alias="contentDesc" class="w-full p-6 md:px-8 mx-auto">
        
        <div class="bg-gradient-to-r from-blue-700 to-blue-500 text-white shadow-xl mb-6 rounded-xl">
            <div class="p-6 md:p-8 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-home text-3xl opacity-75"></i>
                    <div>
                        <h1 class_alias="per_title" class="text-3xl font-extrabold">
                            <?php echo __('Library Administration Dashboard'); ?>
                        </h1>
                        <p class="text-base text-blue-100 opacity-90 mt-1">Monitor, manage, and analyze library operations efficiently and effectively.</p>
                    </div>
                </div>
                <div class="text-right text-blue-100 text-sm opacity-90">
                    <div class="flex items-center justify-end mb-1">
                        <span class="inline-block w-3 h-3 bg-green-400 rounded-full mr-2"></span> Operational
                    </div>
                    <div>Last Updated: <?php echo date('d M, Y H:i'); ?></div>
                </div>
            </div>
        </div>
        
        <?php
        $warnings = array();
        
        if (!extension_loaded('gd')) {
            $warnings[] = __('<strong>PHP GD</strong> extension is not installed. Please install it or application won\'t be able to create image thumbnail and barcode.');
        } else {
            if (!function_exists('imagettftext')) {
                $warnings[] = __('<strong>Freetype</strong> support is not enabled in PHP GD extension. Rebuild PHP GD extension with Freetype support or application won\'t be able to create barcode.');
            }
        }
        
        $overdue_q = $dbs->query('SELECT COUNT(loan_id) FROM loan AS l WHERE (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\'' . date('Y-m-d') . '\')) GROUP BY member_id');
        $num_overdue = $overdue_q->num_rows;
        if ($num_overdue > 0) {
            $warnings[] = str_replace('{num_overdue}', $num_overdue, __('There are currently <strong>{num_overdue}</strong> library members having overdue. Please check at <b>Circulation</b> module at <b>Overdues</b> section for more detail'));
            $overdue_q->free_result();
        }
        
        if (!is_writable(IMGBS) OR !is_writable(IMGBS . 'barcodes') OR !is_writable(IMGBS . 'persons') OR !is_writable(IMGBS . 'docs')) {
            $warnings[] = __('<strong>Images</strong> directory and directories under it is not writable. Make sure it is writable by changing its permission or you won\'t be able to upload any images and create barcodes');
        }
        
        if (!is_writable(REPOBS)) {
            $warnings[] = __('<strong>Repository</strong> directory is not writable. Make sure it is writable (and all directories under it) by changing its permission or you won\'t be able to upload any bibliographic attachments.');
        }
        
        if (!is_writable(UPLOAD)) {
            $warnings[] = __('<strong>File upload</strong> directory is not writable. Make sure it is writable (and all directories under it) by changing its permission or you won\'t be able to upload any file, create report files and create database backups.');
        }
        
        if (is_dir('../install/')) {
            $warnings[] = __('Installer folder is still exist inside your server. Please remove it or rename to another name for security reason.');
        }

        if ($_SESSION['uid'] === '1') {
            $warnings[] = __('<strong><i>You are logged in as Super User. With great power comes great responsibility.</i></strong>');
        }


        if ($warnings) {
            echo '<div class="relative bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 rounded-xl shadow-sm mb-6" role="alert">';
            echo '<button type="button" onclick="this.parentElement.style.display=\'none\'" class="absolute top-3 right-3 p-1 rounded-md hover:bg-yellow-100 transition-colors" aria-label="Close">';
            echo '<i class="fa fa-times text-yellow-700"></i>';
            echo '</button>';
            echo '<h4 class="font-semibold mb-1">' . __('System Warnings') . '</h4>';
            echo '<ul class="list-disc list-inside space-y-1 text-sm">';
            foreach ($warnings as $warning_msg) {
                echo '<li>' . $warning_msg . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        if ($_SESSION['uid'] === '1') {
            $query_of_tables = $dbs->query('SHOW TABLES');
            $num_of_tables = $query_of_tables->num_rows;
            $prevtable = '';
            $repair = '';
            $is_repaired = false;

            if (isset ($_POST['do_repair'])) {
                if ($_POST['do_repair'] == 1) {
                    while ($row = $query_of_tables->fetch_row()) {
                        $sql_of_repair = 'REPAIR TABLE ' . $row[0];
                        $query_of_repair = $dbs->query($sql_of_repair);
                    }
                    echo '<div class="relative bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-xl shadow-sm mb-6" role="alert">';
                    echo '<button type="button" onclick="this.parentElement.style.display=\'none\'" class="absolute top-3 right-3 p-1 rounded-md hover:bg-green-100 transition-colors" aria-label="Close">';
                    echo '<i class="fa fa-times text-green-700"></i>';
                    echo '</button>';
                    echo '<p class="font-semibold">' . __('Database repair process completed successfully.') . '</p>';
                    echo '</div>';
                }
            }

            @mysqli_data_seek($query_of_tables, 0);
            while ($row = $query_of_tables->fetch_row()) {
                $query_of_check = $dbs->query('CHECK TABLE `' . $row[0] . '`');
                if ($query_of_check) {
                    while ($rowcheck = $query_of_check->fetch_assoc()) {
                        if (!(($rowcheck['Msg_type'] == "status") && ($rowcheck['Msg_text'] == "OK"))) {
                            if ($row[0] != $prevtable) {
                                $repair .= '<li>' . __('Table') . ' <strong>' . $row[0] . '</strong> ' . __('might need to be repaired.') . '</li>';
                            }
                            $prevtable = $row[0];
                            $is_repaired = true;
                        }
                    }
                }
            }
            
            if (($is_repaired) && !isset($_POST['do_repair'])) {
                echo '<div class="relative bg-red-50 border border-red-200 text-red-800 p-5 rounded-xl shadow-sm mb-6">';
                echo '<button type="button" onclick="this.parentElement.style.display=\'none\'" class="absolute top-3 right-3 p-1 rounded-md hover:bg-red-100 transition-colors" aria-label="Close">';
                echo '<i class="fa fa-times text-red-700"></i>';
                echo '</button>';
                echo '<h4 class="font-bold text-lg mb-2">' . __('Database Corruption Detected') . '</h4>';
                echo '<p class="mb-4 text-sm">' . __('Some of your database tables seem to be corrupted. Please repair them to prevent data loss.') . '</p>';
                echo '<ul class="list-disc list-inside space-y-1 mb-4 text-sm">';
                echo $repair;
                echo '</ul>';
                echo '<form method="POST" class="m-0">
                      <input type="hidden" name="do_repair" value="1">
                      <input type="submit" value="' . __('Click Here To Repair The Tables') . '" class="cursor-pointer bg-red-600 text-white font-bold py-2 px-5 rounded-lg hover:bg-red-700 transition-colors shadow">
                      </form>';
                echo '</div>';
            }
        }
        
        
        if ($sysconf['admin_home']['mode'] == 'default') {
            require LIB . 'content.inc.php';
            $content = new content();
            $content_data = $content->get($dbs, 'adminhome');
            if ($content_data) {
                echo '<div class="bg-white p-6 rounded-xl shadow-sm">';
                echo '<article class="prose max-w-none">' . $content_data['Content'] . '</article>';
                echo '</div>';
                unset($content_data);
            }
        } else {
            $start_date = date('Y-m-d');
            ?>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                
                <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-lg bg-blue-100 text-primary-500">
                            <i class="fa fa-bookmark fa-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-slate-500"><?php echo __('Total of Collections') ?></h4>
                            <div class="text-3xl font-extrabold text-slate-900 biblio_total_all">0</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-lg bg-green-100 text-green-500">
                            <i class="fa fa-barcode fa-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-slate-500"><?php echo __('Total of Items') ?></h4>
                            <div class="text-3xl font-extrabold text-slate-900 item_total_all">0</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-lg bg-yellow-100 text-yellow-500">
                            <i class="fa fa-archive fa-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-slate-500"><?php echo __('Lent') ?></h4>
                            <div class="text-3xl font-extrabold text-slate-900 item_total_lent">0</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-lg bg-sky-100 text-sky-500">
                            <i class="fa fa-check fa-lg"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-slate-500"><?php echo __('Available') ?></h4>
                            <div class="text-3xl font-extrabold text-slate-900 item_total_available">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <div class="lg:col-span-2 bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <h5 class="text-xl font-bold text-slate-800 mb-3"><?php echo __('Latest Transactions') ?></h5>
                    <div class="relative h-[320px]"> 
                        <canvas id="line-chartjs"></canvas>
                    </div>
                    <div class="s-dashboard-legend flex justify-center items-center gap-6 mt-4 text-sm text-slate-600">
                        <span class="flex items-center gap-2"><i class="fa fa-square" style="color:#F4CC17;"></i> <?php echo __('Loan') ?></span>
                        <span class="flex items-center gap-2"><i class="fa fa-square" style="color:#459CBD;"></i> <?php echo __('Return') ?></span>
                        <span class="flex items-center gap-2"><i class="fa fa-square" style="color:#5D45BD;"></i> <?php echo __('Extend') ?></span>
                    </div>
                </div>

                <div class="lg:col-span-1 bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-shadow">
                    <h5 class="text-xl font-bold text-slate-800 mb-3"><?php echo __('Summary') ?></h5>
                    <div class="s-chart relative h-[200px] w-[200px] mx-auto mb-4">
                        <canvas id="radar-chartjs"></canvas>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="flex items-center gap-3">
                                <i class="fa fa-square fa-lg" style="color:#f2f2f2; border: 1px solid #ddd; border-radius: 4px;"></i>
                                <span class="text-slate-700"><?php echo __('Total') ?></span>
                            </span>
                            <span class="font-bold text-slate-800 loan_total">0</span>
                        </div>
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="flex items-center gap-3">
                                <i class="fa fa-square fa-lg" style="color:#337AB7; border-radius: 4px;"></i>
                                <span class="text-slate-700"><?php echo __('New') ?></span>
                            </span>
                            <span class="font-bold text-slate-800 loan_new">0</span>
                        </div>
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="flex items-center gap-3">
                                <i class="fa fa-square fa-lg" style="color:#06B1CD; border-radius: 4px;"></i>
                                <span class="text-slate-700"><?php echo __('Return') ?></span>
                            </span>
                            <span class="font-bold text-slate-800 loan_return">0</span>
                        </div>
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="flex items-center gap-3">
                                <i class="fa fa-square fa-lg" style="color:#4AC49B; border-radius: 4px;"></i>
                                <span class="text-slate-700"><?php echo __('Extends') ?></span>
                            </span>
                            <span class="font-bold text-slate-800 loan_extend">0</span>
                        </div>
                        <div class="flex justify-between items-center text-sm font-medium">
                            <span class="flex items-center gap-3">
                                <i class="fa fa-square fa-lg" style="color:#F4CC17; border-radius: 4px;"></i>
                                <span class="text-slate-700"><?php echo __('Overdue') ?></span>
                            </span>
                            <span class="font-bold text-slate-800 loan_overdue">0</span>
                        </div>
                    </div>
                </div>

            </div><?php
        }
        ?>

    </div></div><script src="<?php echo JWB ?>chartjs/Chart.min.js"></script>
<script>
    $(function () {

        async function getTotal(url, selector = null) {
            if(selector !== null) $(selector).text('...');
            let res = await (await fetch(url)).json();
            if(selector !== null) $(selector).text(new Intl.NumberFormat('id-ID').format(res.data));
            return res.data;
        }

        getTotal('<?= SWB ?>index.php?p=api/biblio/total/all', '.biblio_total_all');
        getTotal('<?= SWB ?>index.php?p=api/item/total/all', '.item_total_all');
        getTotal('<?= SWB ?>index.php?p=api/item/total/lent', '.item_total_lent');
        getTotal('<?= SWB ?>index.php?p=api/item/total/available', '.item_total_available');

        fetch('<?= SWB ?>index.php?p=api/loan/summary')
            .then(res => res.json())
            .then(res => {

                $('.loan_total').text(new Intl.NumberFormat('id-ID').format(res.data.total));
                $('.loan_new').text(new Intl.NumberFormat('id-ID').format(res.data.new));
                $('.loan_return').text(new Intl.NumberFormat('id-ID').format(res.data.return));
                $('.loan_extend').text(new Intl.NumberFormat('id-ID').format(res.data.extend));
                $('.loan_overdue').text(new Intl.NumberFormat('id-ID').format(res.data.overdue));

                let data = [
                    { value: parseInt(res.data.total), color: "#f2f2f2", label: "<?php echo __('Total'); ?>" },
                    { value: parseInt(res.data.new), color: "#337AB7", label: "<?php echo __('Loan'); ?>" },
                    { value: parseInt(res.data.return), color: "#06B1CD", label: "<?php echo __('Return'); ?>" },
                    { value: parseInt(res.data.extend), color: "#4AC49B", label: "<?php echo __('Extend'); ?>" },
                    { value: parseInt(res.data.overdue), color: "#F4CC17", label: "<?php echo __('Overdue'); ?>" }
                ];

                let r = $('#radar-chartjs');
                if (r.length === 0) return;
                let container = $(r).parent();
                let rt = r.get(0).getContext("2d");
                
                let myChart = null;

                $(window).resize(respondCanvas);

                function respondCanvas() {
                    r.attr('width', $(container).width());
                    r.attr('height', $(container).height());
                    
                    if(myChart) myChart.destroy();

                    myChart = new Chart(rt).Doughnut(data, {
                        animation: true,
                        segmentStrokeWidth: 1,
                        responsive: true,
                        maintainAspectRatio: false
                    });
                }
                respondCanvas();
            });

        fetch('<?= SWB ?>index.php?p=api/loan/getdate/<?= $start_date ?>')
        .then(res => res.json())
        .then(res => {
            let a = getTotal('<?= SWB ?>index.php?p=api/loan/summary/<?= $start_date ?>');
            a.then(res_total => {
                let lineChartData = {
                    labels: res.raw,
                    datasets: [
                        { fillColor: '#F4CC17', highlightFill: '#F4CC17', data: res_total.loan },
                        { fillColor: '#459CBD', highlightFill: '#459CBD', data: res_total.return },
                        { fillColor: '#5D45BD', highlightFill: '#5D45BD', data: res_total.extend },
                    ]
                }

                let c = $('#line-chartjs');
                if (c.length === 0) return;
                let container = $(c).parent();
                let ct = c.get(0).getContext("2d");
                
                let myBarChart = null;
                
                $(window).resize(respondCanvasBar);

                function respondCanvasBar() {
                    c.attr('width', $(container).width()); 
                    c.attr('height', $(container).height());
                    
                    if(myBarChart) myBarChart.destroy();

                    myBarChart = new Chart(ct).Bar(lineChartData, {
                        barShowStroke: false,
                        barDatasetSpacing: 4,
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            onProgress: function(animation) {
                            }
                        }
                    });
                }
                respondCanvasBar();
            })
        })
    });

</script>

