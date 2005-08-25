<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2005 by Greg Gay, Joel Kronenberg & Heidi Hazelton*/
/* Adaptive Technology Resource Centre / University of Toronto			*/
/* http://atutor.ca														*/
/*																		*/
/* This program is free software. You can redistribute it and/or		*/
/* modify it under the terms of the GNU General Public License			*/
/* as published by the Free Software Foundation.						*/
/************************************************************************/
// $Id$

define('AT_INCLUDE_PATH', '../../include/');
require(AT_INCLUDE_PATH.'vitals.inc.php');
admin_authenticate(AT_ADMIN_PRIV_ADMIN);
require(AT_INCLUDE_PATH.'classes/Module/ModuleParser.class.php');

require(AT_INCLUDE_PATH.'lib/modules.inc.php');

if (isset($_GET['mod'])) {
	$dir_name = str_replace(array('.','..','/'), '', $_GET['mod']);

	if (isset($_GET['install'])) {
		header('Location: '.$_base_href.'admin/modules/confirm.php?mod='.$dir_name);
		exit;
	} elseif ($_GET['details']) {
		header('Location: '.$_base_href.'admin/modules/details.php?mod='.$dir_name.';new=1');
		exit;
	}

} else if (isset($_GET['details']) || isset($_GET['install'])) {
	$msg->addError('NO_ITEM_SELECTED');
}

$module_list = $moduleFactory->getUnInstalledModules();
$keys = array_keys($module_list);

require(AT_INCLUDE_PATH.'header.inc.php'); 
?>

<div class="input-form">
	<div class="row">
		<?php echo _AT('install_module_text', realpath('./../../mods/')); ?>		
	</div>
</div>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get" name="installform">
<table class="data" summary="" rules="cols" style="width:40%;">
<thead>
<tr>
	<th scope="col">&nbsp;</th>
	<th scope="col"><?php echo _AT('module_name'); ?></th>
	<th scope="col"><?php echo _AT('directory_name'); ?></th>
</tr>
</thead>
<tfoot>
<tr>
	<td colspan="3">
		<input type="submit" name="details"  value="<?php echo _AT('details'); ?>" />
		<input type="submit" name="install"  value="<?php echo _AT('install'); ?>" />
	</td>
</tr>
</tfoot>
<tbody>
<?php if (!empty($keys)): ?>
	<?php foreach($keys as $dir_name) : $module =& $module_list[$dir_name]; ?>
		<tr onmousedown="document.installform['m_<?php echo $dir_name; ?>'].checked = true;">
			<td valign="top"><input type="radio" id="m_<?php echo $dir_name; ?>" name="mod" value="<?php echo $dir_name; ?>" /></td>
			<td valign="top"><label for="m_<?php echo $row['dir_name']; ?>"><?php echo $module->getName($_SESSION['lang']); ?></label></td>
			<td valign="top"><label for="m_<?php echo $row['dir_name']; ?>"><code><?php echo $dir_name; ?>/</code></label></td>
		</tr>
	<?php endforeach; ?>
<?php else: ?>
	<tr>
		<td colspan="3"><?php echo _AT('none_found'); ?></td>
	</tr>
<?php endif; ?>
</tbody>
</table>
</form>

<?php require(AT_INCLUDE_PATH.'footer.inc.php'); ?>