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

/* Biblio file Adding Pop Windows */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

// main system configuration
require '../../../sysconfig.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';

do_checkIP('smc');
do_checkIP('smc-bibliography');
// start the session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO.'simbio_FILE/simbio_file_upload.inc.php';
require SIMBIO.'simbio_FILE/simbio_directory.inc.php';

// privileges checking
$can_write = utility::havePrivilege('bibliography', 'w');
if (!$can_write) {
  die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

// page title
$page_title = 'File Attachment Upload';

// check for biblio ID in url
$biblioID = 0;
if (isset($_GET['biblioID']) AND $_GET['biblioID']) {
  $biblioID = (integer)$_GET['biblioID'];
}
// check for file ID in url
$fileID = 0;
if (isset($_GET['fileID']) AND $_GET['fileID']) {
  $fileID = (integer)$_GET['fileID'];
}

// start the output buffer
ob_start();
/* main content */
// biblio topic save proccess
if (isset($_POST['upload']) AND trim(strip_tags($_POST['fileTitle'])) != '') {
  $uploaded_file_id = 0;
  $title = trim(strip_tags($_POST['fileTitle']));
  $url = trim(strip_tags($_POST['fileURL']));
  // create new sql op object
  $sql_op = new simbio_dbop($dbs);
  // FILE UPLOADING
  if (isset($_FILES['file2attach']) AND $_FILES['file2attach']['size']) {
    // create upload object
    $file_dir = trim($_POST['fileDir']);
    $file_upload = new simbio_file_upload();
    $file_upload->setAllowableFormat($sysconf['allowed_file_att']);
    $file_upload->setMaxSize($sysconf['max_upload']*1024);
    $file_upload->setUploadDir(REPOBS.DS.str_replace('/', DS, $file_dir));
    $file_upload_status = $file_upload->doUpload('file2attach',md5(date('Y-m-d H:i:s')));
    if ($file_upload_status === UPLOAD_SUCCESS) {
        $file_ext = substr($file_upload->new_filename, strrpos($file_upload->new_filename, '.')+1);
        $fdata['uploader_id'] = $_SESSION['uid'];
        $fdata['file_title'] = $dbs->escape_string($title);
        $fdata['file_name'] = $dbs->escape_string($file_upload->new_filename);
        $fdata['file_url'] = $dbs->escape_string($url);
        $fdata['file_dir'] = $dbs->escape_string($file_dir);
        $fdata['file_desc'] = $dbs->escape_string(trim(strip_tags($_POST['fileDesc'])));
        if(isset($_POST['fileKey']) && trim($_POST['fileKey']) !== '')
          $fdata['file_key'] = $dbs->escape_string(trim(strip_tags($_POST['fileKey'])));
        $fdata['mime_type'] = $sysconf['mimetype'][$file_ext];
        $fdata['input_date'] = date('Y-m-d H:i:s');
        $fdata['last_update'] = $fdata['input_date'];
        // insert file data to database
        @$sql_op->insert('files', $fdata);
        $uploaded_file_id = $sql_op->insert_id;
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' upload file ('.$file_upload->new_filename.')', 'Attachment', 'Add');
    } else {
      utility::jsToastr('File Attachment', __('Upload FAILED! Forbidden file type or file size too big!'), 'error');
      echo '<script type="text/javascript">';
      echo 'self.location.href = "'.$_SERVER['PHP_SELF'].'";';
      echo '</script>';
      die();
    }
  } else {
    if ($url && preg_match('@^(http|https|ftp|gopher):\/\/@i', $url)) {
      $fdata['uploader_id'] = $_SESSION['uid'];
      $fdata['file_title'] = $dbs->escape_string($title);
      $fdata['file_name'] = $dbs->escape_string($url);
      $fdata['file_url'] = $dbs->escape_string($fdata['file_name']);
      $fdata['file_dir'] = 'literal{NULL}';
      $fdata['file_desc'] = $dbs->escape_string(trim(strip_tags($_POST['fileDesc'])));
      if(isset($_POST['fileKey']) && trim($_POST['fileKey']) !== '')
          $fdata['file_key'] = $dbs->escape_string(trim(strip_tags($_POST['fileKey'])));
      $fdata['mime_type'] = 'text/uri-list';
      $fdata['input_date'] = date('Y-m-d H:i:s');
      $fdata['last_update'] = $fdata['input_date'];
      // insert file data to database
      @$sql_op->insert('files', $fdata);
      $uploaded_file_id = $sql_op->insert_id;
    }
  }

  // BIBLIO FILE RELATION DATA UPDATE
  // check if biblio_id POST var exists
  if (isset($_POST['updateBiblioID']) AND !empty($_POST['updateBiblioID'])) {
    $updateBiblioID = (integer)$_POST['updateBiblioID'];
    $data['biblio_id'] = $updateBiblioID;
    $data['file_id'] = $uploaded_file_id;
    $data['placement'] = utility::filterData('placement', 'post', true, true, true);
    $data['access_type'] = trim($_POST['accessType']);
    $data['access_limit'] = 'literal{NULL}';
    // parsing member type data
    if ($data['access_type'] == 'public') {
      $groups = '';
      if (isset($_POST['accLimit']) AND count($_POST['accLimit']) > 0) {
        $groups = serialize($_POST['accLimit']);
      } else {
        $groups = 'literal{NULL}';
      }
      $data['access_limit'] = trim($groups);
    }

    if (isset($_POST['updateFileID'])) {
      $fileID = (integer)$_POST['updateFileID'];
      // file biblio access update
      $update1 = $sql_op->update('biblio_attachment', array('access_type' => $data['access_type'], 'access_limit' => $data['access_limit'], 'placement' => $data['placement']), 'biblio_id='.$updateBiblioID.' AND file_id='.$fileID);
      // file description update
      $file_desc_update = array('file_title' => $title, 'file_url' => $url, 'file_desc' => $dbs->escape_string(trim($_POST['fileDesc'])));
      if(isset($_POST['fileKey']))
          $file_desc_update['file_key'] = $dbs->escape_string(trim(strip_tags($_POST['fileKey'])));
      $update2 = $sql_op->update('files', $file_desc_update, 'file_id='.$fileID);
      if ($update1) {
        utility::jsToastr('File Attachment', __('File Attachment data updated!'), 'success');
        echo '<script type="text/javascript">';
        echo 'parent.setIframeContent(\'attachIframe\', \''.MWB.'bibliography/iframe_attach.php?biblioID='.$updateBiblioID.'\');';
        echo '</script>';
      } else {
          utility::jsToastr('File Attachment', ''.__('File Attachment data FAILED to update!').''."\n".$sql_op->error, 'error');
      }
    } else {
      if ($sql_op->insert('biblio_attachment', $data)) {
        utility::jsToastr('File Attachment', __('File Attachment uploaded succesfully!'), 'success');
        echo '<script type="text/javascript">';
        echo 'parent.setIframeContent(\'attachIframe\', \''.MWB.'bibliography/iframe_attach.php?biblioID='.$data['biblio_id'].'\');';
        echo '</script>';
      } else {
        utility::jsToastr('File Attachment',''.__('File Attachment data FAILED to save!').''."\n".$sql_op->error, 'error');
      }
    }
    utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' updating file attachment data', 'Attachment', 'Update');
  } else {
    if ($uploaded_file_id) {
      // add to session array
      $fdata['file_id'] = $uploaded_file_id;
      $fdata['access_type'] = trim($_POST['accessType']);
      $_SESSION['biblioAttach'][$uploaded_file_id] = $fdata;
      utility::jsToastr('File Attachment', __('File Attachment uploaded succesfully!'), 'success');
      echo '<script type="text/javascript">';
      echo 'parent.setIframeContent(\'attachIframe\', \''.MWB.'bibliography/iframe_attach.php\');';
      echo '</script>';
    }
  }
}

// create new instance
$form = new simbio_form_table('attachUploadForm', $_SERVER['PHP_SELF'].'?biblioID='.$biblioID, 'post');
$form->submit_button_attr = 'name="upload" value="'.__('Upload Now').'" class="btn btn-primary"';
// form table attributes
$form->table_attr = 'id="dataList" class="s-table table"';
$form->table_header_attr = 'class="alterCell font-weight-bold"';
$form->table_content_attr = 'class="alterCell2"';

// query
$file_attach_q = $dbs->query("SELECT fl.*, batt.* FROM files AS fl
  LEFT JOIN biblio_attachment AS batt ON fl.file_id=batt.file_id
  WHERE batt.biblio_id=$biblioID AND batt.file_id=$fileID");
$file_attach_d = $file_attach_q->fetch_assoc();

// edit mode
if (isset($file_attach_d['biblio_id']) AND isset($file_attach_d['file_id'])) {
  $form->addHidden('updateBiblioID', $file_attach_d['biblio_id']);
  $form->addHidden('updateFileID', $file_attach_d['file_id']);
} else if ($biblioID) {
  $form->addHidden('updateBiblioID', $biblioID);
}

// file title
$form->addTextField('text', 'fileTitle', __('Title').'*', $file_attach_d['file_title']??'', 'class="form-control" placeholder="'.__('Enter file title').'"');
// file attachment
if (isset($file_attach_d['file_name'])) {
  $form->addAnything('Attachment', '<div class="attached-file-display"><i class="fa fa-paperclip"></i> '.$file_attach_d['file_dir'].'/'.$file_attach_d['file_name'].'</div>');
} else {
  // file upload dir
  // create simbio directory object
  $repo = new simbio_directory(REPOBS);
  $repo_dir_tree = $repo->getDirectoryTree(5);
  $repodir_options[] = array('', __('Repository ROOT'));
  if (is_array($repo_dir_tree)) {
    // sort array by index
    ksort($repo_dir_tree);
    // loop array
    foreach ($repo_dir_tree as $dir) {
      $repodir_options[] = array($dir, $dir);
    }
  }
  // add repo directory options to select list
  $form->addSelectList('fileDir', __('Repo. Directory'), $repodir_options,'','class="form-control select-modern"');
  // file upload
  $str_input  = '<div class="upload-area-wrapper">';
  $str_input .= '<div class="custom-file-modern">';
  $str_input .= simbio_form_element::textField('file', 'file2attach','','class="custom-file-input" id="fileInput"');
  $str_input .= '<label class="custom-file-label" for="fileInput">';
  $str_input .= '<div class="upload-icon-wrapper">';
  $str_input .= '<i class="fa fa-cloud-upload"></i>';
  $str_input .= '</div>';
  $str_input .= '<div class="upload-content">';
  $str_input .= '<div class="upload-title">Choose file or drag here</div>';
  $str_input .= '<div class="upload-info">PDF, DOC, XLS, PPT, ZIP • Max '.$sysconf['max_upload'].' KB</div>';
  $str_input .= '</div>';
  $str_input .= '</label>';
  $str_input .= '</div>';
  $str_input .= '</div>';
  $form->addAnything(__('File To Attach'), $str_input);
}
// file url
$form->addTextField('textarea', 'fileURL', __('URL'), $file_attach_d['file_url']??'', 'rows="1" class="form-control" placeholder="'.__('https://example.com/file.pdf').'"');

// placement
$str_input = '<div class="placement-wrapper">';
$str_input .= '<div class="placement-info"><i class="fa fa-info-circle"></i> '.__('Work for embedded link or video attachment').'</div>';
$str_input .= '<div class="radio-group-modern">';
$placement_options = [['link', __('Link')], ['popup', __('Popup')], ['embed', __('Embed')]];
foreach ($placement_options as $opt) {
  $checked = (($file_attach_d['placement']??'link') == $opt[0]) ? ' checked' : '';
  $str_input .= '<label class="radio-label-modern">';
  $str_input .= '<input type="radio" name="placement" value="'.$opt[0].'"'.$checked.'>';
  $str_input .= '<span class="radio-text">'.$opt[1].'</span>';
  $str_input .= '</label>';
}
$str_input .= '</div>';
$str_input .= '</div>';
$form->addAnything(__('Placement'), $str_input);

// file description
$form->addTextField('textarea', 'fileDesc', __('Description'), $file_attach_d['file_desc']??'', 'rows="3" class="form-control" placeholder="'.__('Enter file description (optional)').'"');

// Advanced options (collapsed by default)
$advanced_options = '<div class="card" style="border: 1px solid #e5e7eb; border-radius: 8px; margin-top: 15px;">';
$advanced_options .= '<div class="card-header" style="background: #f9fafb; padding: 10px 15px; cursor: pointer;" onclick="$(\'#advancedOptions\').slideToggle();">';
$advanced_options .= '<i class="fa fa-cog"></i> <strong>' . __('Advanced Options') . '</strong> <small class="text-muted">(click to expand)</small>';
$advanced_options .= '</div>';
$advanced_options .= '<div id="advancedOptions" class="card-body" style="display: none; padding: 15px;">';

// file key (inside advanced)
$advanced_options .= '<div class="form-group" style="margin-bottom: 15px;">';
$advanced_options .= '<label style="font-weight: 600; color: #374151;">' . __('File Password') . '</label>';
$advanced_options .= '<textarea name="fileKey" rows="1" class="form-control" style="border-radius: 6px;">' . ($file_attach_d['file_key']??'') . '</textarea>';
$advanced_options .= '<small class="text-muted">' . __('Optional: Set password to protect this file') . '</small>';
$advanced_options .= '</div>';

// file access (inside advanced)
$advanced_options .= '<div class="form-group" style="margin-bottom: 15px;">';
$advanced_options .= '<label style="font-weight: 600; color: #374151;">' . __('Access') . '</label>';
$advanced_options .= '<select name="accessType" class="form-control" style="border-radius: 6px; max-width: 200px;">';
$advanced_options .= '<option value="public"' . (($file_attach_d['access_type']??'public') == 'public' ? ' selected' : '') . '>' . __('Public') . '</option>';
$advanced_options .= '<option value="private"' . (($file_attach_d['access_type']??'') == 'private' ? ' selected' : '') . '>' . __('Private') . '</option>';
$advanced_options .= '</select>';
$advanced_options .= '</div>';

// file access limit if set to public
$group_query = $dbs->query('SELECT member_type_id, member_type_name FROM mst_member_type');
$group_options_html = '<div class="form-group">';
$group_options_html .= '<label style="font-weight: 600; color: #374151;">' . __('Access Limit by Member Type') . '</label><br>';
while ($group_data = $group_query->fetch_row()) {
  $checked = '';
  if (!empty($file_attach_d['access_limit'])) {
    $limits = unserialize($file_attach_d['access_limit']);
    if (is_array($limits) && in_array($group_data[0], $limits)) {
      $checked = ' checked';
    }
  }
  $group_options_html .= '<label style="display: inline-block; margin-right: 15px; font-weight: normal;">';
  $group_options_html .= '<input type="checkbox" name="accLimit[]" value="' . $group_data[0] . '"' . $checked . '> ' . $group_data[1];
  $group_options_html .= '</label>';
}
$group_options_html .= '</div>';
$advanced_options .= $group_options_html;

$advanced_options .= '</div></div>';
$form->addAnything('', $advanced_options);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* Modern Elegant Popup Styling */
* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html, body {
  height: 100%;
  overflow: hidden;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.popup-container {
  height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 40px 30px;
  -webkit-overflow-scrolling: touch;
}

.popup-content {
  max-width: 1200px;
  margin: 0 auto;
  background: white;
  border-radius: 20px;
  box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3), 0 10px 30px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  border: none;
  animation: slideUp 0.4s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.popup-header {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  padding: 40px 50px;
  text-align: left;
  position: relative;
  overflow: hidden;
}

.popup-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.popup-header::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
}

.popup-header h3 {
  margin: 0 0 10px 0;
  font-size: 28px;
  font-weight: 700;
  color: white;
  position: relative;
  z-index: 1;
  letter-spacing: -0.5px;
}

.popup-header p {
  margin: 0;
  color: rgba(255, 255, 255, 0.95);
  font-size: 15px;
  position: relative;
  z-index: 1;
  font-weight: 400;
  line-height: 1.6;
}

.popup-header .icon {
  display: inline-block;
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  line-height: 56px;
  text-align: center;
  margin-right: 18px;
  position: relative;
  z-index: 1;
  float: left;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.popup-header .icon i {
  font-size: 26px;
  color: white;
}

.popup-body {
  padding: 40px 50px 50px;
  background: linear-gradient(to bottom, #ffffff 0%, #f9fafb 100%);
}

.s-table.table {
  background: transparent;
  border: none;
  box-shadow: none;
  margin-bottom: 0;
}

.s-table.table td {
  padding: 8px 0 !important;
  border-bottom: none;
  vertical-align: middle;
}

/* Hide the colon column */
.s-table.table td:nth-child(2) {
  display: none;
}

.s-table.table tr:last-child td {
  padding-bottom: 0 !important;
}

.s-table.table tr:first-child td {
  padding-top: 0 !important;
}

.alterCell {
  background: transparent !important;
  color: #1f2937;
  font-weight: 600 !important;
  font-size: 15px;
  width: auto !important;
  padding-right: 12px !important;
  line-height: 1.3;
  vertical-align: middle !important;
  white-space: nowrap;
}

.alterCell2 {
  background: transparent !important;
  width: auto;
  vertical-align: middle !important;
}

.form-control {
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 18px;
  font-size: 15px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: #ffffff;
  width: 100%;
  color: #1f2937;
  line-height: 1.5;
}

.form-control:hover {
  border-color: #cbd5e1;
}

.form-control:focus {
  border-color: #3b82f6;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.08);
  outline: none;
  transform: translateY(-1px);
}

.form-control::placeholder {
  color: #9ca3af;
}

.btn-primary {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  border: none;
  padding: 16px 40px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 16px;
  color: white;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
  display: inline-block;
  margin-top: 20px;
  letter-spacing: 0.3px;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #1d4ed8 0%, #1e3a8a 100%);
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(37, 99, 235, 0.45);
}

.btn-primary:active {
  transform: translateY(0);
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Modern Upload Area */
.upload-area-wrapper {
  margin-top: 0;
}

.custom-file-modern {
  position: relative;
}

.custom-file-input {
  opacity: 0;
  position: absolute;
  z-index: 10;
  width: 100%;
  height: 100%;
  cursor: pointer;
  top: 0;
  left: 0;
}

/* Hide the default "Browse" button completely */
.custom-file-input::-webkit-file-upload-button {
  display: none;
  visibility: hidden;
  width: 0;
  height: 0;
  opacity: 0;
}

.custom-file-input::file-selector-button {
  display: none;
  visibility: hidden;
  width: 0;
  height: 0;
  opacity: 0;
}

.custom-file-label {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-start;
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  padding: 16px 20px;
  background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  text-align: left;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  gap: 16px;
}

.custom-file-label::before {
  content: '';
  position: absolute;
  top: 50%;
  left: -100px;
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
  transform: translateY(-50%);
  opacity: 0;
  transition: all 0.5s;
}

.custom-file-label:hover::before {
  opacity: 1;
  left: 0;
}

.upload-icon-wrapper {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.12);
  position: relative;
  z-index: 1;
}

.upload-icon-wrapper i {
  font-size: 20px;
  color: #2563eb;
  transition: all 0.35s;
}

.upload-content {
  position: relative;
  z-index: 1;
  flex: 1;
}

.upload-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 4px;
  letter-spacing: -0.2px;
  line-height: 1.3;
}

.upload-info {
  font-size: 12px;
  color: #6b7280;
  font-weight: 400;
  line-height: 1.4;
  margin: 0;
}

.custom-file-label:hover {
  border-color: #3b82f6;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
}

.custom-file-label:hover .upload-icon-wrapper {
  transform: scale(1.05);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.2);
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.custom-file-label:hover .upload-icon-wrapper i {
  color: white;
}

.custom-file-label:hover .upload-title {
  color: #1e40af;
}

/* Attached file display */
.attached-file-display {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border: 2px solid #86efac;
  border-radius: 12px;
  padding: 16px 20px;
  color: #166534;
  font-weight: 500;
  font-size: 15px;
}

.attached-file-display i {
  margin-right: 10px;
  font-size: 18px;
}

.card {
  border: 2px solid #e5e7eb !important;
  border-radius: 14px !important;
  margin-top: 28px;
  overflow: hidden;
  transition: all 0.3s;
  background: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.card:hover {
  border-color: #cbd5e1 !important;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  transform: translateY(-1px);
}

.card-header {
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%) !important;
  padding: 18px 24px !important;
  cursor: pointer;
  transition: all 0.3s;
  border-bottom: 2px solid #e5e7eb !important;
}

.card-header:hover {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%) !important;
}

.card-header strong {
  color: #1f2937;
  font-size: 16px;
  font-weight: 600;
}

.card-header i {
  color: #3b82f6;
  margin-right: 12px;
  font-size: 16px;
}

.card-body {
  padding: 28px !important;
  background: #fafbfc;
}

.form-group {
  margin-bottom: 22px;
}

.form-group label {
  display: block;
  margin-bottom: 10px;
  font-weight: 600;
  color: #1f2937;
  font-size: 15px;
}

.form-group .text-muted {
  display: block;
  margin-top: 8px;
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
}

/* Modern Select Dropdown */
select.form-control {
  cursor: pointer;
  appearance: none;
  background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="%233b82f6" d="M8 11L3 6h10z"/></svg>');
  background-repeat: no-repeat;
  background-position: right 16px center;
  padding-right: 48px;
  font-weight: 500;
}

select.form-control:hover {
  border-color: #3b82f6;
}

/* Modern Radio Buttons */
.radio-group-modern {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.radio-label-modern {
  display: inline-flex;
  align-items: center;
  padding: 12px 20px;
  background: #f8fafc;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s;
  font-weight: 500;
  color: #475569;
}

.radio-label-modern:hover {
  background: #eff6ff;
  border-color: #3b82f6;
  transform: translateY(-1px);
}

.radio-label-modern input[type="radio"] {
  margin-right: 10px;
  margin-left: 0;
  width: 18px;
  height: 18px;
  accent-color: #3b82f6;
  cursor: pointer;
}

.radio-label-modern input[type="radio"]:checked + .radio-text {
  color: #2563eb;
  font-weight: 600;
}

.radio-label-modern:has(input:checked) {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.radio-text {
  transition: all 0.3s;
}

/* Placement wrapper */
.placement-wrapper {
  margin-top: 8px;
}

.placement-info {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 14px;
  padding: 12px 16px;
  background: #f0f9ff;
  border-left: 3px solid #3b82f6;
  border-radius: 8px;
}

.placement-info i {
  color: #3b82f6;
  margin-right: 8px;
}

input[type="checkbox"] {
  margin-right: 10px;
  width: 18px;
  height: 18px;
  accent-color: #3b82f6;
  cursor: pointer;
}

/* Scrollbar styling */
.popup-container::-webkit-scrollbar {
  width: 14px;
}

.popup-container::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

.popup-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 10px;
  border: 3px solid transparent;
  background-clip: padding-box;
  transition: all 0.3s;
}

.popup-container::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
  background-clip: padding-box;
}

/* Improve label cursor */
label {
  cursor: pointer;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .popup-container {
    padding: 20px 15px;
  }

  .popup-content {
    border-radius: 16px;
  }

  .popup-header {
    padding: 30px 25px;
  }

  .popup-body {
    padding: 35px 30px 40px;
  }

  .alterCell {
    width: 160px;
    font-size: 14px;
  }

  .radio-group-modern {
    flex-direction: column;
    gap: 12px;
  }

  .radio-label-modern {
    width: 100%;
  }
}
</style>
</head>
<body>
<div class="popup-container">
  <div class="popup-content">
    <div class="popup-header">
      <div style="overflow: hidden;">
        <div class="icon">
          <i class="fa fa-cloud-upload"></i>
        </div>
        <div style="margin-left: 65px;">
          <h3><?php echo __('File Attachment'); ?></h3>
          <p><?php echo __('Upload PDF, DOC, or other digital files to this bibliography record'); ?></p>
        </div>
      </div>
    </div>
    <div class="popup-body">
      <?php
      // print out the object
      echo $form->printOut();
      ?>
    </div>
  </div>
</div>
<script type="text/javascript">
  $(document).on('change', '.custom-file-input', function () {
      let fileName = $(this).val().replace(/\\/g, '/').replace(/.*\//, '');
      if (fileName) {
        // Get file extension
        let fileExt = fileName.split('.').pop().toLowerCase();
        let iconClass = 'fa-file-o';
        let fileColor = '#10b981';

        // Choose icon and color based on file type
        if (['pdf'].includes(fileExt)) {
          iconClass = 'fa-file-pdf-o';
          fileColor = '#ef4444';
        } else if (['doc', 'docx'].includes(fileExt)) {
          iconClass = 'fa-file-word-o';
          fileColor = '#3b82f6';
        } else if (['xls', 'xlsx'].includes(fileExt)) {
          iconClass = 'fa-file-excel-o';
          fileColor = '#10b981';
        } else if (['ppt', 'pptx'].includes(fileExt)) {
          iconClass = 'fa-file-powerpoint-o';
          fileColor = '#f59e0b';
        } else if (['jpg', 'jpeg', 'png', 'gif', 'svg'].includes(fileExt)) {
          iconClass = 'fa-file-image-o';
          fileColor = '#8b5cf6';
        } else if (['zip', 'rar', '7z'].includes(fileExt)) {
          iconClass = 'fa-file-archive-o';
          fileColor = '#6366f1';
        } else if (['mp4', 'avi', 'mov', 'mkv'].includes(fileExt)) {
          iconClass = 'fa-file-video-o';
          fileColor = '#ec4899';
        } else if (['mp3', 'wav', 'ogg'].includes(fileExt)) {
          iconClass = 'fa-file-audio-o';
          fileColor = '#14b8a6';
        }

        let html = '<div class="upload-icon-wrapper" style="background: linear-gradient(135deg, ' + fileColor + '20 0%, ' + fileColor + '35 100%); box-shadow: 0 2px 10px ' + fileColor + '25;">' +
                   '<i class="fa ' + iconClass + '" style="color: ' + fileColor + ';"></i>' +
                   '</div>' +
                   '<div class="upload-content">' +
                   '<div class="upload-title" style="color: ' + fileColor + ';">' + fileName + '</div>' +
                   '<div class="upload-info" style="color: #059669; font-weight: 500;"><i class="fa fa-check-circle"></i> Ready to upload</div>' +
                   '</div>';

        $(this).siblings('.custom-file-label')
          .html(html)
          .css({
            'border-color': fileColor,
            'border-style': 'solid',
            'background': 'linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%)',
            'box-shadow': '0 4px 16px rgba(16, 185, 129, 0.15)'
          });
      }
  });

  // Prevent parent window scroll when popup scrolls to bottom
  $(document).ready(function() {
    $('.popup-container').on('scroll touchmove mousewheel', function(e) {
      e.stopPropagation();
    });

    // Prevent body scroll
    $('body').css('overflow', 'hidden');

    // Add smooth scroll behavior
    $('.popup-container').css('scroll-behavior', 'smooth');
  });
</script>
</body>
</html>
<?php
/* main content end */
$content = ob_get_clean();
// include the page template
require SB.'/admin/'.$sysconf['admin_template']['dir'].'/notemplate_page_tpl.php';
