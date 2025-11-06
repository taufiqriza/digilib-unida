<?php
/**
 * Copyright (C) 2010 Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB . 'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// start the session
require SB . 'admin/default/session.inc.php';
require SB . 'admin/default/session_check.inc.php';
require SB . 'admin/admin_template/printed_settings.inc.php';

// page title
$page_title = 'Loan Receipt';

// start the output buffer
ob_start();
/* main content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Modern Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Libre+Barcode+39&family=Source+Code+Pro:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style type="text/css">
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
                padding: 0; margin: 0;
            }
            .receipt-container {
                box-shadow: none; border-radius: 0; border: none;
            }
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            padding: 20px;
            color: #212529;
            font-size: 10px;
        }
        .receipt-container {
            width: 78mm;
            max-width: 100%;
            background-color: #fff;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        .receipt-header {
            text-align: center;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .receipt-header img {
            max-width: 100px;
            margin-bottom: 8px;
        }
        .receipt-header h1 {
            font-size: 12px;
            font-weight: 700;
            margin: 2px 0 0 0;
            text-transform: uppercase;
            color: #000;
        }
        .receipt-header p {
            font-size: 9px;
            margin: 2px 0 0 0;
            color: #495057;
        }
        .receipt-details {
            margin-bottom: 10px;
            border-top: 1px dashed #adb5bd;
            border-bottom: 1px dashed #adb5bd;
            padding: 10px 0;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            margin-bottom: 3px;
            line-height: 1.5;
        }
        .detail-item span {
            color: #495057;
            white-space: nowrap;
            padding-right: 10px;
        }
        .detail-item strong {
            font-weight: 600;
            text-align: right;
            font-family: 'Source Code Pro', monospace;
        }
        .transaction-section h2 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding-bottom: 5px;
            margin: 15px 0 10px 0;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #eee;
        }
        .transaction-section h2 i {
            margin-right: 8px;
            color: #343a40;
        }
        .item {
            font-size: 10px;
            margin-bottom: 10px;
            padding-left: 8px;
            border-left: 2px solid #e9ecef;
        }
        .item .title {
            font-weight: 600;
            display: block;
            line-height: 1.4;
        }
        .item .details {
            display: block;
            font-size: 9px;
            color: #495057;
            margin-top: 2px;
            font-family: 'Source Code Pro', monospace;
        }
        .item .dates {
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            font-size: 9px;
        }
        .receipt-footer {
            padding-top: 10px;
            margin-top: 10px;
            text-align: center;
            border-top: 1px dashed #adb5bd;
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .footer-left {
            text-align: left;
            font-size: 9px;
        }
        .staff-info span {
            color: #495057;
        }
        .staff-info strong {
            font-weight: 600;
            margin-left: 5px;
        }
        .thank-you p {
            font-size: 8px;
            font-style: italic;
            color: #6c757d;
            margin: 5px 0 0 0;
            line-height: 1.3;
        }
        .footer-right {
            text-align: center;
        }
        .qr-code {
            width: 50px;
            height: 50px;
            background-color: #e9ecef;
            margin: 0 auto 5px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-code i {
            font-size: 24px;
            color: #adb5bd;
        }
        .barcode {
            font-family: 'Libre Barcode 39', cursive;
            font-size: 32px;
            line-height: 1;
            color: #000;
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <header class="receipt-header">
        <img src="receipt.png" alt="Logo Perpustakaan" onerror="this.style.display='none'">
        <h1>Perpustakaan UNIDA Gontor</h1>
        <p>Resit Transaksi Peminjaman</p>
    </header>

    <section class="receipt-details">
        <div class="detail-item">
            <span>Nama Anggota:</span>
            <strong><?php echo htmlspecialchars($_SESSION['receipt_record']['memberName']); ?></strong>
        </div>
        <div class="detail-item">
            <span>ID Anggota:</span>
            <strong><?php echo htmlspecialchars($_SESSION['receipt_record']['memberID']); ?></strong>
        </div>
        <div class="detail-item">
            <span>Tanggal:</span>
            <strong><?php echo htmlspecialchars($_SESSION['receipt_record']['date']); ?></strong>
        </div>
    </section>

    <!-- LOAN & EXTEND SECTION -->
    <?php if (isset($_SESSION['receipt_record']['loan']) || isset($_SESSION['receipt_record']['extend'])): ?>
    <section class="transaction-section">
        <h2><i class="fas fa-arrow-circle-down"></i> Peminjaman / Perpanjangan</h2>
        <?php
        // Loans
        if (isset($_SESSION['receipt_record']['loan'])) {
            foreach ($_SESSION['receipt_record']['loan'] as $loan) {
                echo '<div class="item">';
                echo '<span class="title">' . htmlspecialchars(substr($loan['title'], 0, $sysconf['print']['receipt']['receipt_titleLength'])) . '</span>';
                echo '<span class="details">ITEM: ' . htmlspecialchars($loan['itemCode']) . '</span>';
                echo '<div class="dates">';
                echo '<span><strong>Pinjam:</strong> ' . htmlspecialchars($loan['loanDate']) . '</span>';
                echo '<span><strong>Kembali:</strong> ' . htmlspecialchars($loan['dueDate']) . '</span>';
                echo '</div></div>';
            }
        }

        // Extends
        if (isset($_SESSION['receipt_record']['extend'])) {
            foreach ($_SESSION['receipt_record']['extend'] as $ext) {
                echo '<div class="item">';
                echo '<span class="title">' . htmlspecialchars(substr($ext['title'], 0, $sysconf['print']['receipt']['receipt_titleLength'])) . ' <strong>(Diperpanjang)</strong></span>';
                echo '<span class="details">ITEM: ' . htmlspecialchars($ext['itemCode']) . '</span>';
                echo '<div class="dates">';
                echo '<span><strong>Pinjam:</strong> ' . htmlspecialchars($ext['loanDate']) . '</span>';
                echo '<span><strong>Kembali:</strong> ' . htmlspecialchars($ext['dueDate']) . '</span>';
                echo '</div></div>';
            }
        }
        ?>
    </section>
    <?php endif; ?>

    <?php
    
    if (isset($_SESSION['receipt_record']['return']) && isset($_SESSION['receipt_record']['extend'])) {
        foreach ($_SESSION['receipt_record']['extend'] as $key => $value) {
            if (isset($_SESSION['receipt_record']['return'][$key]) && $_SESSION['receipt_record']['extend'][$key]['itemCode'] == $_SESSION['receipt_record']['return'][$key]['itemCode']) {
                unset($_SESSION['receipt_record']['return'][$key]);
            }
        }
    }
    ?>

    <!-- RETURN SECTION -->
    <?php if (isset($_SESSION['receipt_record']['return']) && (count($_SESSION['receipt_record']['return']) != 0)): ?>
    <section class="transaction-section">
        <h2><i class="fas fa-arrow-circle-up"></i> Pengembalian</h2>
        <?php
        foreach ($_SESSION['receipt_record']['return'] as $ret) {
            echo '<div class="item">';
            echo '<span class="title">' . htmlspecialchars(substr($ret['title'], 0, $sysconf['print']['receipt']['receipt_titleLength'])) . '</span>';
            echo '<span class="details">ITEM: ' . htmlspecialchars($ret['itemCode']) . '</span>';
            echo '<div class="dates">';
            echo '<span><strong>Tgl Kembali:</strong> ' . htmlspecialchars($ret['returnDate']) . '</span>';
            if ($ret['overdues']) {
                echo '<span><strong>Terlambat:</strong> ' . htmlspecialchars($ret['overdues']['days']) . ' hari</span>';
            }
            echo '</div></div>';
        }
        ?>
    </section>
    <?php endif; ?>

    <section class="receipt-footer">
        <div class="footer-content">
            <div class="footer-left">
                <div class="staff-info">
                    <span>Petugas:</span>
                    <strong><?php echo htmlspecialchars($_SESSION['realname']); ?></strong>
                </div>
                <div class="thank-you">
                    <p>Terima kasih. Mohon kembalikan buku tepat waktu.</p>
                </div>
            </div>
            <div class="footer-right">
                <div class="qr-code"><i class="fas fa-qrcode"></i></div>
            </div>
        </div>
        <div class="barcode">*<?php echo 'TRX' . mt_rand(100000, 999999); ?>*</div>
    </section>
</div>

<script type="text/javascript">
    
    window.onload = function() {
        window.print();
    };
</script>

</body>
</html>
<?php

$content = ob_get_clean();
// include the page template
require SB . '/admin/' . $sysconf['admin_template']['dir'] . '/notemplate_page_tpl.php';
?>
