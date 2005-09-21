<?php
/****************************************************************/
/* ATutor														*/
/****************************************************************/
/* Copyright (c) 2002-2005 by Greg Gay & Joel Kronenberg        */
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

require(AT_INCLUDE_PATH.'header.inc.php');

$module_list = $moduleFactory->getModules(AT_MODULE_STATUS_ENABLED);
$keys = array_keys($module_list);
natsort($keys);

echo '<ol>';
foreach ($keys as $module_name) {
	$module =& $module_list[$module_name];
	if ($module->getPrivilege() && authenticate($module->getPrivilege(), AT_PRIV_RETURN)) {
		$parent = $module->getChildPage('tools/index.php');
		echo '<li><a href="' . $parent . '">' . _AT($_pages[$parent]['title_var']) .'</a></li>';
	}
}
echo '</ol>';

require(AT_INCLUDE_PATH.'footer.inc.php');
?>