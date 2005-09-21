<?php
if (!defined('AT_INCLUDE_PATH')) { exit; }

define('AT_PRIV_ADMIN', $this->getPrivilege());

//admin pages
$_module_pages['admin/edit_course.php']['title_var'] = 'course_properties';
$_module_pages['admin/edit_course.php']['parent']    = 'admin/courses.php';

$_module_pages['admin/delete_course.php']['title_var'] = 'delete_course';
$_module_pages['admin/delete_course.php']['parent']    = 'admin/courses.php';


//instructor pages
$_module_pages['tools/course_properties.php']['title_var'] = 'properties';
$_module_pages['tools/course_properties.php']['parent']    = 'tools/index.php';
$_module_pages['tools/course_properties.php']['children']  = array('tools/delete_course.php');
$_module_pages['tools/course_properties.php']['guide']     = 'instructor/?p=12.0.properties.php';

	$_module_pages['tools/delete_course.php']['title_var'] = 'delete_course';
	$_module_pages['tools/delete_course.php']['parent']    = 'tools/course_properties.php';

?>