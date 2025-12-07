<?php
/**
 * Branch Management - Submenu
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');

$menu[] = array('Header', __('Manajemen Cabang'));
$menu[] = array(__('Dashboard Cabang'), MWB . 'branch_management/index.php', __('Kelola cabang perpustakaan'));
$menu[] = array(__('Tambah Cabang'), MWB . 'branch_management/branch_form.php', __('Tambah cabang baru'));
