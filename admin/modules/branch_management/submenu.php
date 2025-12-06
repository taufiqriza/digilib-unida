<?php
/**
 * Branch Management Module - Submenu
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

$menu[] = array('Header', __('Branch Management'));
$menu[] = array(__('Branch List'), MWB . 'branch_management/index.php', __('Manage library branches'));
$menu[] = array(__('Add Branch'), MWB . 'branch_management/branch_form.php', __('Add new branch'));
$menu[] = array(__('Statistics'), MWB . 'branch_management/statistics.php', __('View branch statistics'));
$menu[] = array(__('Import Data'), MWB . 'branch_management/import.php', __('Import data from other SLiMS'));
