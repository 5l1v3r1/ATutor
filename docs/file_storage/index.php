<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2006 by Greg Gay & Joel Kronenberg        */
/* Adaptive Technology Resource Centre / University of Toronto  */
/* http://atutor.ca												*/
/*                                                              */
/* This program is free software. You can redistribute it and/or*/
/* modify it under the terms of the GNU General Public License  */
/* as published by the Free Software Foundation.				*/
/****************************************************************/
// $Id$

define('AT_INCLUDE_PATH', '../include/');
require(AT_INCLUDE_PATH.'vitals.inc.php');
require(AT_INCLUDE_PATH.'lib/filemanager.inc.php'); // for get_human_size()
require(AT_INCLUDE_PATH.'lib/file_storage.inc.php');

if (isset($_GET['submit_workspace'])) {
	unset($_GET['folder']);

	$owner_type = abs($_GET['ot']);

	if ($owner_type == WORKSPACE_GROUP) {

		$parts = explode('_', $_GET['ot'], 2);
		if (isset($parts[1]) && $parts[1] && isset($_SESSION['groups'][$parts[1]])) {
			$owner_id = $parts[1];
		} else {
			$owner_type = WORKSPACE_COURSE;
			unset($owner_id);
		}
	} else if ($owner_type == WORKSPACE_ASSIGNMENT) {
		$parts = explode('_', $_GET['ot'], 2);
		if (isset($parts[1]) && $parts[1]) {
			$owner_id = $parts[1];
		} else {
			$owner_type = WORKSPACE_ASSIGNMENT;
			unset($owner_id);
		}
	} else {
		unset($owner_id);
	}
	$_REQUEST['folder'] = 0;
} else if (isset($_REQUEST['ot'], $_REQUEST['oid'])) {
	$owner_type = abs($_REQUEST['ot']);
	$owner_id   = abs($_REQUEST['oid']);
} else if (isset($_SESSION['fs_owner_type'], $_SESSION['fs_owner_id'], $_SESSION['fs_folder_id'])) {
	$owner_type = abs($_SESSION['fs_owner_type']);
	$owner_id   = abs($_SESSION['fs_owner_id']);
} else {
	$owner_type = WORKSPACE_COURSE;
}

if (isset($_REQUEST['folder'])) {
	$folder_id = abs($_REQUEST['folder']);
} else if (isset($_SESSION['fs_folder_id'])) {
	$folder_id = abs($_SESSION['fs_folder_id']);
} else {
	$folder_id = 0;
}

// init the owner_id if not currently set
if (!isset($owner_id)) {
	if ($owner_type == WORKSPACE_COURSE) {
		$owner_id = $_SESSION['course_id'];
	} else if ($owner_type == WORKSPACE_PERSONAL) {
		$owner_id = $_SESSION['member_id'];
	} else if ($owner_type == WORKSPACE_GROUP) {
		$owner_id = $group_id;
	}
}

$owner_arg_prefix = '?ot='.$owner_type.SEP.'oid='.$owner_id. SEP;
if (!($owner_status = fs_authenticate($owner_type, $owner_id))) {
	$msg->addError('ACCESS_DENIED');
	header('Location: index.php');
	exit;
}
$_SESSION['fs_owner_type'] = $owner_type;
$_SESSION['fs_owner_id']   = $owner_id;
$_SESSION['fs_folder_id']  = $folder_id;

if (isset($_GET['submit_workspace'])) {
	header('Location: index.php'.$owner_arg_prefix);
	exit;
}

// action - Submit Assignment
if (isset($_GET['assignment']) && (isset($_GET['files']) || isset($_GET['folders']))) {
	if (isset($_GET['folders'])) {
		$msg->addError('HAND_IN_FOLDER');
	} else if (!isset($_GET['files'])) {
		$msg->addError('NO_ITEM_SELECTED');
	} else {
		header('Location: assignment.php?'.$_SERVER['QUERY_STRING']);
		exit;
	}
}
// action - View Revisions
else if (isset($_GET['revisions'], $_GET['files'])) {
	if (is_array($_GET['files']) && (count($_GET['files']) == 1) && empty($_GET['folders'])) {
		$file_id = intval(current($_GET['files']));
		header('Location: revisions.php'.$owner_arg_prefix.'id='.$file_id);
		exit;
	}
}
// action - View Comments
else if (isset($_GET['comments'], $_GET['files'])) {
	if (is_array($_GET['files']) && (count($_GET['files']) == 1) && empty($_GET['folders'])) {
		$file_id = intval(current($_GET['files']));
		header('Location: comments.php'.$owner_arg_prefix.'id='.$file_id);
		exit;
	}
}
// action - Edit File/Folder
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_GET['edit']) && (isset($_GET['folders']) || isset($_GET['files']))) {
	if (is_array($_GET['files']) && (count($_GET['files']) == 1) && empty($_GET['folders'])) {
		$file_id = abs(current($_GET['files']));
		header('Location: edit.php'.$owner_arg_prefix.'id='.$file_id);
		exit;
	} else if (is_array($_GET['folders']) && (count($_GET['folders']) == 1) && empty($_GET['files'])) {
		$folder_id = abs(current($_GET['folders']));
		header('Location: edit_folder.php'.$owner_arg_prefix.'id='.$folder_id);
		exit;
	}
}
// action - Move Files/Folders
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_GET['move']) && (isset($_GET['folders']) || isset($_GET['files']))) {
	header('Location: move.php'.$owner_arg_prefix.$_SERVER['QUERY_STRING']);
	exit;
}
// action - Download Files/Folders
else if (isset($_GET['download']) && (isset($_GET['folders']) || isset($_GET['files']))) {
	if (is_array($_GET['files']) && (count($_GET['files']) == 1) && empty($_GET['folders'])) {
		$file_id = abs(current($_GET['files']));
		$sql = "SELECT file_name, file_size FROM ".TABLE_PREFIX."files WHERE file_id=$file_id AND owner_type=$owner_type AND owner_id=$owner_id";
		$result = mysql_query($sql, $db);
		if ($row = mysql_fetch_assoc($result)) {
			$ext = fs_get_file_extension($row['file_name']);

			if (isset($mime[$ext]) && $mime[$ext][0]) {
				$file_mime = $mime[$ext][0];
			} else {
				$file_mime = 'application/octet-stream';
			}
			
			header('Content-Type: ' . $file_mime);
			header('Content-transfer-encoding: binary'); 
			header('Content-Disposition: attachment; filename="'.htmlspecialchars($row['file_name']).'"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.$row['file_size']);
			@readfile(fs_get_file_path($file_id) . $file_id);
			exit;
		}
	} else {
		// zip multiple files and folders
		require(AT_INCLUDE_PATH . 'classes/zipfile.class.php');
		$zipfile =& new zipfile();

		$zip_file_name = fs_get_workspace($owner_type, $owner_id); // want the name of the workspace

		if (is_array($_GET['files'])) {
			foreach ($_GET['files'] as $file_id) {
				$file_path = fs_get_file_path($file_id) . $file_id;

				$sql = "SELECT file_name, UNIX_TIMESTAMP(date) AS date FROM ".TABLE_PREFIX."files WHERE file_id=$file_id AND owner_type=$owner_type AND owner_id=$owner_id";
				$result = mysql_query($sql, $db);
				if (($row = mysql_fetch_assoc($result)) && file_exists($file_path)) {
					$zipfile->add_file(file_get_contents($file_path), $row['file_name'], $row['date']);
				}
			}
		}
		if (is_array($_GET['folders'])) {
			foreach($_GET['folders'] as $folder_id) {
				fs_download_folder($folder_id, $zipfile, $owner_type, $owner_id);
				$zipfile->create_dir($row['title']);
			}

			if (count($_GET['folders']) == 1) {
				// zip just one folder, use that folder's title as the zip file name
				$row = fs_get_folder_by_id($_GET['folders'][0], $owner_type, $owner_id);
				if ($row) {
					$zip_file_name = $row['title'];
				}
			}
		}
		$zipfile->close();
		$zipfile->send_file($zip_file_name);
	}
	exit;

}
// action - Delete Files/Folders (pre-confirmation)
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_GET['delete']) && (isset($_GET['folders']) || isset($_GET['files']))) {
	$hidden_vars = array();
	$hidden_vars['folder'] = $folder_id;
	$hidden_vars['ot']     = $owner_type;
	$hidden_vars['oid']     = $owner_id;
	if (isset($_GET['files'])) {
		$file_list_to_print = '';
		$files = implode(',', $_GET['files']);
		$hidden_vars['files'] = $files;
		$sql = "SELECT file_name FROM ".TABLE_PREFIX."files WHERE file_id IN ($files) AND owner_type=$owner_type AND owner_id=$owner_id ORDER BY file_name";
		$result = mysql_query($sql, $db);
		while ($row = mysql_fetch_assoc($result)) {
			$file_list_to_print .= '<li style="list-style: none; margin: 0px; padding: 0px 10px;"><img src="images/file_types/'.fs_get_file_type_icon($row['file_name']).'.gif" height="16" width="16" alt="" title="" /> '.$row['file_name'].'</li>';
		}
		$msg->addConfirm(array('FILE_DELETE', $file_list_to_print), $hidden_vars);
	}
		
	if (isset($_GET['folders'])) {
		$dir_list_to_print = '';
		$folders = implode(',', $_GET['folders']);
		$hidden_vars['folders'] = $folders;
		$rows = fs_get_folder_by_id($_GET['folders'], $owner_type, $owner_id);
		foreach ($rows as $row) {
			$dir_list_to_print .= '<li style="list-style: none; margin: 0px; padding: 0px 10px;"><img src="images/folder.gif" height="18" width="20" alt="" title="" /> '.$row['title'].'</li>';
		}
		$msg->addConfirm(array('DIR_DELETE', $dir_list_to_print), $hidden_vars);
	}

	require(AT_INCLUDE_PATH.'header.inc.php');
	$msg->printConfirm();
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;

}
// action - Confirm Delete Files/Folders
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_POST['submit_yes'])) {

	// handle the delete
	if (isset($_POST['files'])) {
		$files = explode(',', $_POST['files']);
	}
	if (isset($_POST['folders'])) {
		$folders = explode(',', $_POST['folders']);
	}
	if (isset($files)) {
		foreach ($files as $file) {
			fs_delete_file($file, $owner_type, $owner_id);
		}
		$msg->addFeedback('FILE_DELETED');
	}

	if (isset($folders)) {
		foreach ($folders as $folder) {
			fs_delete_folder($folder, $owner_type, $owner_id);
		}
		$msg->addFeedback('DIR_DELETED');
	}

	header('Location: index.php'.$owner_arg_prefix.'folder='.abs($_POST['folder']));
	exit;
}
// action - Cancel Delete
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_POST['submit_no'])) {
	$msg->addFeedback('CANCELLED');
	header('Location: index.php'.$owner_arg_prefix.'folder='.abs($_POST['folder']));
	exit;

// action - Create Folder
} else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_POST['create_folder'])) {
	// create a new folder
	$_POST['new_folder_name'] = trim($_POST['new_folder_name']);

	if (!$_POST['new_folder_name']) {
		$msg->addError('MISSING_FOLDER_NAME');
	}

	if (!$msg->containsErrors()) {
		$_POST['new_folder_name'] = $addslashes($_POST['new_folder_name']);

		$parent_folder_id = abs($_POST['folder']);

		$sql = "INSERT INTO ".TABLE_PREFIX."folders VALUES (0, $parent_folder_id, $owner_type, $owner_id, '$_POST[new_folder_name]')";
		$result = mysql_query($sql, $db);
		$msg->addFeedback('FOLDER_CREATED');
		header('Location: index.php'.$owner_arg_prefix.'folder='.$parent_folder_id);
		exit;
	}
}
// action - Upload
else if (query_bit($owner_status, WORKSPACE_AUTH_WRITE) && isset($_POST['upload'])) {
	// handle the file upload
	$_POST['comments'] = trim($_POST['comments']);

	$parent_folder_id = abs($_POST['folder']);

	if ($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE) {
		$msg->addError(array('FILE_TOO_BIG', get_human_size(megabytes_to_bytes(substr(ini_get('upload_max_filesize'), 0, -1)))));

	} else if (!isset($_FILES['file']['name']) || ($_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) || ($_FILES['file']['size'] == 0)) {
		$msg->addError('FILE_NOT_SELECTED');

	} else if ($_FILES['file']['error'] || !is_uploaded_file($_FILES['file']['tmp_name'])) {
		$msg->addError('FILE_NOT_SAVED');
	}

	// check that we own this folder
	if ($parent_folder_id) {
		$sql = "SELECT folder_id FROM ".TABLE_PREFIX."folders WHERE folder_id=$parent_folder_id AND owner_type=$owner_type AND owner_id=$owner_id";
		$result = mysql_query($sql, $db);
		if (!$row = mysql_fetch_assoc($result)) {
			$msg->addError('ACCESS_DENIED');
			header('Location: index.php');
			exit;
		}
	}

	if (!$msg->containsErrors()) {
		$_POST['comments'] = $addslashes($_POST['comments']);
		$_FILES['file']['name'] = addslashes($_FILES['file']['name']);

		if ($_POST['comments']) {
			$num_comments = 1;
		} else {
			$num_comments = 0;
		}

		$sql = "INSERT INTO ".TABLE_PREFIX."files VALUES (0, $owner_type, $owner_id, $_SESSION[member_id], $parent_folder_id, 0, NOW(), $num_comments, 0, '{$_FILES['file']['name']}', {$_FILES['file']['size']})";
		$result = mysql_query($sql, $db);

		if ($result && ($file_id = mysql_insert_id($db))) {
			$path = fs_get_file_path($file_id);
			move_uploaded_file($_FILES['file']['tmp_name'], $path . $file_id);

			// check if this file name already exists
			$sql = "SELECT file_id, num_revisions FROM ".TABLE_PREFIX."files WHERE owner_type=$owner_type AND owner_id=$owner_id AND folder_id=$parent_folder_id AND file_id<>$file_id AND file_name='{$_FILES['file']['name']}' AND parent_file_id=0 ORDER BY file_id DESC LIMIT 1";
			$result = mysql_query($sql, $db);
			if ($row = mysql_fetch_assoc($result)) {
				if ($_config['fs_versioning']) {
					$sql = "UPDATE ".TABLE_PREFIX."files SET parent_file_id=$file_id WHERE file_id=$row[file_id]";
					$result = mysql_query($sql, $db);

					$sql = "UPDATE ".TABLE_PREFIX."files SET num_revisions=$row[num_revisions]+1 WHERE file_id=$file_id";
					$result = mysql_query($sql, $db);
				} else {
					fs_delete_file($row['file_id'], $owner_type, $owner_id);
				}
			}

			if ($_POST['comments']){
				$sql = "INSERT INTO ".TABLE_PREFIX."files_comments VALUES (0, $file_id, $_SESSION[member_id], NOW(), '{$_POST['comments']}')";
				mysql_query($sql, $db);
			}

			$msg->addFeedback('FILE_UPLOADED');
		} else {
			$msg->addError('FILE_NOT_SAVED');
		}
	}
	header('Location: index.php'.$owner_arg_prefix.'folder='.$parent_folder_id);
	exit;
} else if ((isset($_GET['delete']) || isset($_GET['download']) || isset($_GET['move']) || isset($_GET['edit']) || isset($_GET['assignment'])) && !isset($_GET['files']) && !isset($_GET['folders'])) {
	$msg->addError('NO_ITEM_SELECTED');
}

if (query_bit($owner_status, WORKSPACE_AUTH_WRITE)) {
	$onload = 'hideform(\'upload\'); hideform(\'folder\');';
}

require(AT_INCLUDE_PATH.'header.inc.php');

$orders = array('asc' => 'desc', 'desc' => 'asc');

if (isset($_GET['asc'])) {
	$order = 'asc';
	$col   = $addslashes($_GET['asc']);
} else if (isset($_GET['desc'])) {
	$order = 'desc';
	$col   = $addslashes($_GET['desc']);
} else {
	// no order set
	$order = 'asc';
	$col   = 'file_name';
}

$folder_path = fs_get_folder_path($folder_id, $owner_type, $owner_id);

$folders = fs_get_folder_by_pid($folder_id, $owner_type, $owner_id);

$files = array();
$sql = "SELECT * FROM ".TABLE_PREFIX."files WHERE folder_id=$folder_id AND owner_type=$owner_type AND owner_id=$owner_id AND parent_file_id=0 ORDER BY $col $order";
$result = mysql_query($sql, $db);

while ($row = mysql_fetch_assoc($result)) {
	$files[] = $row;
}

?>

<?php if (query_bit($owner_status, WORKSPACE_AUTH_WRITE)): ?>
	<form method="post" action="<?php echo $_SERVER['PHP_SELF'].$owner_arg_prefix; ?>" enctype="multipart/form-data" name="form0">
	<input type="hidden" name="folder" value="<?php echo $folder_id; ?>" />
	<div style="margin: 0px auto; width: 70%">
		<div class="input-form" style="width: 48%; float: right">
			<div class="row">
				<h3><a onclick="javascript:document.getElementById('folder').style.display='';document.form0.new_folder_name.focus();" style="font-family: Helevetica, Arial, sans-serif;" onmouseover="this.style.cursor='pointer'"><?php echo _AT('create_folder'); ?></a></h3>
			</div>
			<div name="folder" id="folder">
				<div class="row">
					<div class="required" title="<?php echo _AT('required_field'); ?>">*</div><label for="fname"><?php echo _AT('name'); ?></label><br />
					<input type="text" id="fname" name="new_folder_name" size="20" />
				</div>
				<div class="row buttons">
					<input type="submit" name="create_folder" value="<?php echo _AT('create'); ?>" />
				</div>
			</div>
		</div>
		<div class="input-form" style="float: left; width: 48%">
			<div class="row">
				<h3><a onclick="javascript:document.getElementById('upload').style.display='';document.form0.file.focus();" style="font-family: Helevetica, Arial, sans-serif;" onmouseover="this.style.cursor='pointer'"><?php echo _AT('new_file'); ?></a></h3>
			</div>
			<div name="upload" id="upload">
				<div class="row">
					<div class="required" title="<?php echo _AT('required_field'); ?>">*</div><label for="file"><?php echo _AT('upload_file'); ?></label><br />
					<input type="file" name="file" id="file" />
					<br /><?php echo _AT('or'); ?> <a href="file_storage/new.php<?php echo $owner_arg_prefix; ?>folder=<?php echo $folder_id; ?>"><?php echo _AT('file_manager_new'); ?></a>
				</div>
				<div class="row">
					<label for="comments"><?php echo _AT('revision_comment'); ?></label><br />
					<textarea name="comments" id="comments" rows="1" cols="20"></textarea>
				</div>
				<div class="row buttons">
					<input type="submit" name="upload" value="<?php echo _AT('upload'); ?>" />
				</div>
			</div>
		</div>
	</div>
	</form>

	<div style="clear: both;"></div>
<?php endif; ?>

<?php
if ($_SESSION['groups']) {
	$file_storage_groups = array();
	$groups_list = implode(',',$_SESSION['groups']);
	$sql = "SELECT G.type_id, G.title, G.group_id FROM ".TABLE_PREFIX."file_storage_groups FS INNER JOIN ".TABLE_PREFIX."groups G USING (group_id) WHERE FS.group_id IN ($groups_list) ORDER BY G.type_id, G.title";
	$result = mysql_query($sql, $db);
	while ($row = mysql_fetch_assoc($result)) {
		$file_storage_groups[] = $row;
	}
}

if (authenticate(AT_PRIV_ASSIGNMENTS, AT_PRIV_RETURN)) {
	$file_storage_assignments = array();
	$sql = "SELECT * FROM ".TABLE_PREFIX."assignments WHERE course_id=$_SESSION[course_id] ORDER BY title";
	$result = mysql_query($sql, $db);
	while ($row = mysql_fetch_assoc($result)) {
		$file_storage_assignments[] = $row;
	}
}
?>

<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" name="form">
<input type="hidden" name="folder" value="<?php echo $folder_id; ?>" />
<input type="hidden" name="oid" value="<?php echo $owner_id; ?>" />
<table class="data">
<thead>
<tr>
	<td colspan="7">
		<input type="submit" name="submit_workspace" value="<?php echo _AT('workspace'); ?>" class="button" />
		<select name="ot" id="ot">
			<option value="1" <?php if ($owner_type == WORKSPACE_COURSE) { echo 'selected="selected"'; } ?>><?php echo _AT('course_files'); ?></option>
			<?php if ($_SESSION['member_id']): ?>
				<option value="2" <?php if ($owner_type == WORKSPACE_PERSONAL) { echo 'selected="selected"'; } ?>><?php echo _AT('my_files'); ?></option>
			<?php endif; ?>
			<?php if ($file_storage_groups): ?>
				<optgroup label="<?php echo _AT('groups'); ?>">
					<?php foreach ($file_storage_groups as $group): ?>
						<option value="<?php echo WORKSPACE_GROUP; ?>_<?php echo $group['group_id']; ?>" <?php if ($owner_type == WORKSPACE_GROUP && $owner_id == $group['group_id']) { echo 'selected="selected"'; } ?>><?php echo $group['title']; ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
			<?php if (authenticate(AT_PRIV_ASSIGNMENTS, AT_PRIV_RETURN)): ?>
				<optgroup label="<?php echo _AT('assignments'); ?>">
					<?php foreach ($file_storage_assignments as $assignment): ?>
						<option value="<?php echo WORKSPACE_ASSIGNMENT; ?>_<?php echo $assignment['assignment_id']; ?>" <?php if ($owner_type == WORKSPACE_ASSIGNMENT && $owner_id == $assignment['assignment_id']) { echo 'selected="selected"'; } ?>><?php echo $assignment['title']; ?></option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
		</select>

		<?php foreach ($folder_path as $folder_info): ?>
			<?php if ($folder_info['folder_id'] == $folder_id): ?>
				� <?php echo $folder_info['title']; ?>
				<?php $parent_folder_id = $folder_info['parent_folder_id']; ?>
			<?php else: ?>
				� <a href="<?php echo $_SERVER['PHP_SELF'].$owner_arg_prefix; ?>folder=<?php echo $folder_info['folder_id']; ?>"><?php echo $folder_info['title']; ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</td>
</tr>
<tr>
	<th align="left" width="10"><input type="checkbox" value="<?php echo _AT('select_all'); ?>" id="all" title="<?php echo _AT('select_all'); ?>" name="selectall" onclick="CheckAll();" /></th>
	<th scope="col"><a href="<?php echo $_SERVER['PHP_SELF'] . $owner_arg_prefix . 'folder='.$folder_id.SEP.$orders[$order]; ?>=file_name"><?php echo _AT('file');      ?></a></th>
	<th scope="col"><?php echo _AT('author');    ?></th>
	<th scope="col"><?php if ($_config['fs_versioning']): ?><?php echo _AT('revisions'); ?><?php endif; ?></th>
	<th scope="col"><?php echo _AT('comments');  ?></th>
	<th scope="col"><a href="<?php echo $_SERVER['PHP_SELF'] . $owner_arg_prefix . 'folder='.$folder_id.SEP.$orders[$order]; ?>=file_size"><?php echo _AT('size'); ?></a></th>
	<th scope="col"><a href="<?php echo $_SERVER['PHP_SELF'] . $owner_arg_prefix . 'folder='.$folder_id.SEP.$orders[$order]; ?>=date"><?php echo _AT('date'); ?></a></th>
</tr>
<colgroup>
	<?php if ($col == 'file_name'): ?>
		<col />
		<col class="sort" />
		<col span="5" />
	<?php elseif($col == 'file_size'): ?>
		<col span="5" />
		<col class="sort" />
		<col />
	<?php elseif($col == 'date'): ?>
		<col span="6" />
		<col class="sort" />
	<?php endif; ?>
</colgroup>
</thead>
<tfoot>
<tr>
	<td colspan="7">
		<input type="submit" name="download" value="<?php echo _AT('download'); ?>" />
		<?php if (query_bit($owner_status, WORKSPACE_AUTH_WRITE)): ?>
			<?php if ($owner_type != WORKSPACE_COURSE): ?>
				<input type="submit" name="assignment" value="<?php echo _AT('hand_in'); ?>" />
			<?php endif; ?>
			<input type="submit" name="edit" value="<?php echo _AT('edit'); ?>" />
			<input type="submit" name="move" value="<?php echo _AT('move'); ?>" />
			<input type="submit" name="delete" value="<?php echo _AT('delete'); ?>" />
		<?php endif; ?>
	</td>
</tr>
</tfoot>
<tbody>
<?php if ($folders || $files): ?>
	<?php foreach ($folders as $folder_info): ?>
		<tr onmousedown="document.form['f<?php echo $folder_info['folder_id']; ?>'].checked = !document.form['f<?php echo $folder_info['folder_id']; ?>'].checked; rowselectbox(this, document.form['f<?php echo $folder_info['folder_id']; ?>'].checked, 'checkbuttons(false)');" id="r_<?php echo $folder_info['folder_id']; ?>_1">
			<td width="10"><input type="checkbox" name="folders[]" value="<?php echo $folder_info['folder_id']; ?>" id="f<?php echo $folder_info['folder_id']; ?>" onmouseup="this.checked=!this.checked" /></td>
			<td><img src="images/folder.gif" height="18" width="20" alt="" /> <a href="<?php echo $_SERVER['PHP_SELF'].$owner_arg_prefix; ?>folder=<?php echo $folder_info['folder_id']; ?>"><?php echo $folder_info['title']; ?></a></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
	<?php endforeach; ?>
	<?php foreach ($files as $file_info): ?>
		<tr onmousedown="document.form['r<?php echo $file_info['file_id']; ?>'].checked = !document.form['r<?php echo $file_info['file_id']; ?>'].checked; rowselectbox(this, document.form['r<?php echo $file_info['file_id']; ?>'].checked, 'checkbuttons(false)');" id="r_<?php echo $file_info['file_id']; ?>_0">
			<td valign="top" width="10"><input type="checkbox" name="files[]" value="<?php echo $file_info['file_id']; ?>" id="r<?php echo $file_info['file_id']; ?>" onmouseup="this.checked=!this.checked" /></td>
			<td valign="top"><img src="images/file_types/<?php echo fs_get_file_type_icon($file_info['file_name']); ?>.gif" height="16" width="16" alt="" title="" /> <?php echo $file_info['file_name']; ?></td>
			<td align="center" valign="top"><?php echo get_login($file_info['member_id']); ?></td>
			<td align="right" valign="top">
				<?php if ($_config['fs_versioning']): ?>
					<?php if ($file_info['num_revisions']): ?>
						<a href="<?php echo 'file_storage/revisions.php'.$owner_arg_prefix.'id='.$file_info['file_id']; ?>"><?php echo $file_info['num_revisions']; ?></a>
					<?php else: ?>
						-
					<?php endif; ?>
				<?php endif; ?>
			</td>
			<td align="right" valign="top"><a href="<?php echo 'file_storage/comments.php'.$owner_arg_prefix.'id='.$file_info['file_id']; ?>"><?php echo $file_info['num_comments']; ?></a></td>
			<td align="right" valign="top"><?php echo get_human_size($file_info['file_size']); ?></td>
			<td align="right" valign="top"><?php echo AT_date(_AT('filemanager_date_format'), $file_info['date'], AT_DATE_MYSQL_DATETIME); ?></td>
		</tr>
	<?php endforeach; ?>
<?php else: ?>
	<tr>
		<td colspan="7"><?php echo _AT('none_found'); ?></td>
	</tr>
<?php endif; ?>
</tbody>
</table>
</form>

<script type="text/javascript">
function checkbuttons(state) {
	document.form.selectall.checked = state;

	var num_files_checked = 0;
	var num_folders_checked = 0;
	for (var i=0;i<document.form.elements.length;i++) {
		var e = document.form.elements[i];
		if ((e.name == 'folders[]') && (e.type=='checkbox') && e.checked) {
			num_folders_checked++;
		} else if ((e.name == 'files[]') && (e.type=='checkbox') && e.checked) {
			num_files_checked++;
		}
	}
	if (num_files_checked + num_folders_checked > 1) {
		if (document.form.edit)
			document.form.edit.disabled = true;
	} else {
		if (document.form.edit)
			document.form.edit.disabled = false;
	}
}
function CheckAll() {
	var state = document.form.selectall.checked;
	for (var i=0;i<document.form.elements.length;i++)	{
		var e = document.form.elements[i];
		if ((e.name == 'folders[]') && (e.type=='checkbox')) {
			e.checked = state;
			rowselectbox(document.getElementById('r_' + e.value + '_1'), state, 'checkbuttons(' + state + ')');
		} else if ((e.name == 'files[]') && (e.type=='checkbox')) {
			e.checked = state;
			rowselectbox(document.getElementById('r_' + e.value + '_0'), state, 'checkbuttons(' + state + ')');
		}
	}
}

function hideform(id) {
	document.getElementById(id).style.display='none';
}
</script>

<?php require(AT_INCLUDE_PATH.'footer.inc.php'); ?>