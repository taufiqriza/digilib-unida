<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Modification by Muhammad Ibrahim (C) 2021 (islahboim@gmail.com)
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

/* Circulation module submenu items */
// IP based access limitation
do_checkIP('smc');
do_checkIP('smc-wakaf');

$menu[] = array('Header', __('Wakaf'));
//$menu[] = array(__('Report'), MWB.'wakaf/index.php', __('Show Reporting Wakaf Data'));
$menu[] = array(__('Wakaf List'), MWB.'wakaf/index.php', __('Show Existing Wakaf Data'));
$menu[] = array(__('Add New Wakaf'), MWB.'wakaf/index.php?action=detail', __('Add New Wakaf Data'));
$menu[] = array('Header', __('Tools'));
$menu[] = array(__('Akta Wakaf '), MWB.'wakaf/print_akta_wakaf.php', __('Print Akta Wakaf'));
$menu[] = array(__('Sertifikat Wakaf '), MWB.'wakaf/print_sertifikat_wakaf.php', __('Print Sertifikat Wakaf'));
$menu[] = array(__('Data Export'), MWB.'wakaf/export.php', __('Export Wakaf Data To CSV format'));