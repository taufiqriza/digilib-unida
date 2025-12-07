<?php
/**
 * Home Submenu
 */

$menu[] = array('Header', __('SHORTCUT'));
$menu['user-profile'] = array(__('Change User Profiles'), MWB.'system/app_user.php?changecurrent=true&action=detail', __('Change Current User Profiles and Password'));
$menu[] = array(__('Shortcut Setting'), MWB.'system/shortcut.php', __('Shortcut Setting'));
$menu[] = array(__('Theme'), MWB.'system/theme.php', __('Configure theme Preferences'));

// Cabang Management
$menu[] = array('Header', __('CABANG'));
$menu[] = array(__('Dashboard Cabang'), MWB.'branch_management/index.php', __('Kelola cabang perpustakaan'));
$menu[] = array(__('Tambah Cabang'), MWB.'branch_management/branch_form.php', __('Tambah cabang baru'));
