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


if (isset($_POST['submit_no'])) {
	$msg->addFeedback('CANCELLED');
	header('Location: '.$_base_href.'admin/modules/add_new.php');
	exit;
} else if (isset($_POST['mod']) && isset($_POST['submit_yes'])) {
	$module =& $moduleFactory->getModule($_POST['mod']);
	$module->install();

	if ($msg->containsErrors()) {
		header('Location: '.$_base_href.'admin/modules/details.php?mod='.$_POST['mod'].SEP.'new=1');
	} else {
		$msg->addFeedback('MOD_INSTALLED');
		header('Location: '.$_base_href.'admin/modules/index.php');
	}
	exit;
} else if (isset($_GET['submit'])) {
	header('Location: index.php');
	exit;
}

require(AT_INCLUDE_PATH.'header.inc.php'); 

$moduleParser =& new ModuleParser();

$_REQUEST['mod'] = str_replace(array('.','..'), '', $_REQUEST['mod']);

if (!file_exists('../../mods/'.$_GET['mod'].'/module.xml')) {
?>
<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<input type="hidden" name="mod" value="<?php echo $_GET['mod']; ?>" />
<input type="hidden" name="new" value="<?php echo $_GET['new']; ?>" />
<div class="input-form">
	<div class="row">
		<h3><?php echo $_GET['mod']; ?></h3>
	</div>

	<div class="row">
		<?php echo _AT('missing_info'); ?>
	</div>

	<div class="row buttons">
		<input type="submit" name="submit" value="<?php echo _AT('back'); ?>" />
		<?php if (isset($_GET['new']) && $_GET['new']): ?>
			<input type="submit" name="install" value="<?php echo _AT('install'); ?>" />
		<?php endif; ?>
	</div>

</div>
</form>
<?php
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}

$moduleParser->parse(file_get_contents('../../mods/'.$_GET['mod'].'/module.xml'));

$module =& $moduleFactory->getModule($_GET['mod']);

$properties = $module->getProperties(array('maintainers', 'url', 'date', 'license', 'state', 'notes', 'version'));
?>
<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<input type="hidden" name="mod" value="<?php echo $_GET['mod']; ?>" />
<input type="hidden" name="new" value="<?php echo $_GET['new']; ?>" />
<div class="input-form">
	<div class="row">
		<h3><?php echo $module->getName(); ?></h3>
	</div>

	<div class="row">
		<?php echo _AT('description'); ?><br />
		<?php echo nl2br($module->getDescription($_SESSION['lang'])); ?>
	</div>

	<div class="row">
		<?php echo _AT('maintainers'); ?><br />
			<ul class="horizontal">
				<?php foreach ($properties['maintainers'] as $maintainer): ?>
					<li><?php echo $maintainer['name'] .' &lt;'.$maintainer['email'].'&gt;'; ?></li>
				<?php endforeach; ?>
			</ul>
	</div>

	<div class="row">
		<?php echo _AT('url'); ?><br />
		<?php echo $properties['url']; ?>
	</div>

	<div class="row">
		<?php echo _AT('version'); ?><br />
		<?php echo $properties['version']; ?>
	</div>

	<div class="row">
		<?php echo _AT('date'); ?><br />
		<?php echo $properties['date']; ?>
	</div>

	<div class="row">
		<?php echo _AT('license'); ?><br />
		<?php echo $properties['license']; ?>
	</div>

	<div class="row">
		<?php echo _AT('state'); ?><br />
		<?php echo $properties['state']; ?>
	</div>

	<div class="row">
		<?php echo _AT('notes'); ?><br />
		<?php echo $properties['notes']; ?>
	</div>
<?php if (!isset($_REQUEST['new'])): ?>
	<div class="row buttons">
		<input type="submit" name="submit" value="<?php echo _AT('back'); ?>" />
	</div>
<?php endif; ?>
</div>
</form>
<?php if (isset($_REQUEST['new'])): ?>
	<?php
		$hidden_vars['mod'] = $_REQUEST['mod'];
		$hidden_vars['new'] = '1';
		$msg->addConfirm(array('ADD_MODULE', $_REQUEST['mod']), $hidden_vars);
		$msg->printConfirm();
	?>
<?php endif; ?>

<?php require(AT_INCLUDE_PATH.'footer.inc.php'); ?>