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
require(AT_INCLUDE_PATH.'lib/test_result_functions.inc.php');

$_section[0][0] = _AT('tools');
$_section[0][1] = 'tools/index.php';
$_section[1][0] = _AT('my_tests');
$_section[1][1] = 'tools/my_tests.php';
$_section[2][0] = _AT('test_results');

require(AT_INCLUDE_PATH.'header.inc.php');
echo '<h2>';
if ($_SESSION['prefs'][PREF_CONTENT_ICONS] != 2) {
	echo '<a href="tools/index.php?g=11"><img src="images/icons/default/square-large-tools.gif"  class="menuimageh2" width="42" border="0" vspace="2" height="40" alt="" /></a>';
}
if ($_SESSION['prefs'][PREF_CONTENT_ICONS] != 1) {
	echo ' <a href="tools/index.php?g=11">'._AT('tools').'</a>';
}
echo '</h2>';

echo '<h3>';
if ($_SESSION['prefs'][PREF_CONTENT_ICONS] != 2) {
	echo '&nbsp;<img src="images/icons/default/my-tests-large.gif" vspace="2"  class="menuimageh3" width="42" height="38" alt="" /> ';
}
if ($_SESSION['prefs'][PREF_CONTENT_ICONS] != 1) {
		echo ' <a href="tools/my_tests.php?g=11">'._AT('my_tests').'</a>';
}
echo '</h3>';
$tid = intval($_GET['tid']);
$rid = intval($_GET['rid']);

$sql	= "SELECT title FROM ".TABLE_PREFIX."tests WHERE test_id=$tid AND course_id=$_SESSION[course_id]";
$result	= mysql_query($sql, $db);
$row	= mysql_fetch_array($result);

echo '<h4>'._AT('submissions_for', AT_print($row['title'], 'tests.title')).'</h4>';

$mark_right = '<img src="images/checkmark.gif" alt="'._AT('correct_answer').'" />';
$mark_wrong = '<img src="images/x.gif" alt="'._AT('wrong_answer').'" />';

$sql	= "SELECT * FROM ".TABLE_PREFIX."tests_results WHERE result_id=$rid AND member_id=$_SESSION[member_id]";
$result	= mysql_query($sql, $db); 
if (!$row = mysql_fetch_assoc($result)){
	$msg->printErrors('RESULT_NOT_FOUND');
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}
$final_score = $row['final_score'];

//make sure they're allowed to see results now
$sql	= "SELECT result_release, out_of FROM ".TABLE_PREFIX."tests WHERE test_id=$tid AND course_id=$_SESSION[course_id]";
$result	= mysql_query($sql, $db); 
$row = mysql_fetch_assoc($result);

if ( ($row['result_release']==AT_RELEASE_NEVER) || ($row['result_release']==AT_RELEASE_MARKED && $final_score=='') ) {
	$msg->printErrors('RESULTS_NOT_RELEASED');
	require(AT_INCLUDE_PATH.'footer.inc.php');
	exit;
}

$out_of = $row['out_of'];

// $sql	= "SELECT * FROM ".TABLE_PREFIX."tests_questions WHERE course_id=$_SESSION[course_id] AND test_id=$tid ORDER BY ordering, question_id";

/* Retrieve randomly choosed questions */
$sql	= "SELECT question_id FROM ".TABLE_PREFIX."tests_answers WHERE result_id=$rid";
$result	= mysql_query($sql, $db); 
$row = mysql_fetch_array($result);
$random_id_string = $row[question_id];
$row = mysql_fetch_array($result);	
while ($row['question_id'] != '') {
	$random_id_string = $random_id_string.','.$row['question_id'];
	$row = mysql_fetch_array($result);
}

$sql	= "SELECT TQ.*, TQA.* FROM ".TABLE_PREFIX."tests_questions TQ INNER JOIN ".TABLE_PREFIX."tests_questions_assoc TQA USING (question_id) WHERE TQA.test_id=$tid AND TQ.question_id IN ($random_id_string) ORDER BY TQA.ordering, TQ.question_id";	
$result	= mysql_query($sql, $db); 
		
$count = 1;
echo '<form>';

if ($row = mysql_fetch_assoc($result)){
	echo '<table border="0" cellspacing="3" cellpadding="3" class="bodyline" width="90%" align="center">';

	do {
		/* get the results for this question */
		$sql		= "SELECT * FROM ".TABLE_PREFIX."tests_answers WHERE result_id=$rid AND question_id=$row[question_id] AND member_id=$_SESSION[member_id]";
		$result_a	= mysql_query($sql, $db); 
		$answer_row = mysql_fetch_assoc($result_a);

		echo '<tr>';
		echo '<td valign="top">';
		echo '<b>'.$count.'</b><br />';
		
		$count++;

		switch ($row['type']) {
			case AT_TESTS_MC:
				/* multiple choice question */

				if ($row['weight']) {
					print_score($row['answer_'.$answer_row['answer']], $row['weight'], $row['question_id'], $answer_row['score'], false, true);
				}

				echo '</td>';
				echo '<td>';

				echo AT_print($row['question'], 'tests_questions.question').'<br /><p>';

				/* for each non-empty choice: */
				for ($i=0; ($i < 10) && ($row['choice_'.$i] != ''); $i++) {
					if ($i > 0) {
						echo '<br />';
					}
					print_result($row['choice_'.$i], $row['answer_'.$i], $i, AT_print($answer_row['answer'], 'tests_answers.answer'), $row['answer_'.$answer_row['answer']], $row['weight']);

					if (($row['answer_'.$i] == 1)  && (!$row['answer_'.$answer_row['answer']])) {
						echo ' ('.$mark_right.')';
					}
				}
				echo '<br />';

				print_result('<em>'._AT('left_blank').'</em>', -1, -1, AT_print($answer_row['answer'], 'tests_answers.answer'), false, $row['weight']);
				echo '</p>';
				$my_score=($my_score+$answer_row['score']);
				$this_total += $row['weight'];
				break;

			case AT_TESTS_TF:
				/* true or false question */
				if ($row['weight']) {
					print_score($row['answer_'.$answer_row['answer']], $row['weight'], $row['question_id'], $answer_row['score'], false, true, $row['weight']);
				}
				echo '</td>';
				echo '<td>';
				echo AT_print($row['question'], 'tests_questions.question').'<br /><p>';

				/* avman */
				if($answer_row['answer']== $row['answer_0']){
					$correct=1;
				} else {
					$correct='';
				}
				print_result(_AT('true'), $row['answer_0'], 1, AT_print($answer_row['answer'], 'tests_answers.answer'), $correct, $row['weight']);

				print_result(_AT('false'), $row['answer_1'], 2, AT_print($answer_row['answer'], 'tests_answers.answer'), $correct, $row['weight']);

				echo '<br />';
				print_result('<em>'._AT('left_blank').'</em>', -1, -1, AT_print($answer_row['answer'], 'tests_answers.answer'), false, $row['weight']);
				$my_score=($my_score+$answer_row['score']);
				$this_total += $row['weight'];
				echo '</p>';
				break;

			case AT_TESTS_LONG:
				/* long answer question */

				if ($row['weight']) {
					print_score($row['answer_'.$answer_row['answer']], $row['weight'], $row['question_id'], $answer_row['score'], false, true);
				}

				echo '</td>';
				echo '<td>';

				echo AT_print($row['question'], 'tests_questions.question').'<br /><p><br />';
				echo AT_print($answer_row['answer'], 'tests_answers.answer');	
				echo '</p><br />';
				$my_score=($my_score+$answer_row['score']);
				$this_total += $row['weight'];
				echo '</p><br />';
				break;

			case AT_TESTS_LIKERT:
				/* Likert question */
				echo '</td>';
				echo '<td>';

				echo AT_print($row['question'], 'tests_questions.question').'<br /><p>';

				/* for each non-empty choice: */
				for ($i=0; ($i < 10) && ($row['choice_'.$i] != ''); $i++) {
					if ($i > 0) {
						echo '<br />';
					}
					print_result($row['choice_'.$i], '' , $i, AT_print($answer_row['answer'], 'tests_answers.answer'), 'none', $row['weight']);
				}

				echo '<br />';

				print_result('<em>'._AT('left_blank').'</em>', -1, -1, AT_print($answer_row['answer'], 'tests_answers.answer'), 'none', $row['weight']);
				echo '</p>';
				$my_score=($my_score+$answer_row['score']);
				$this_total += $row['weight'];
				break;
		}


		if ($row['feedback'] == '') {
			//echo '<em>'._AT('none').'</em>.';
		} else {
			echo '<p><strong>'._AT('feedback').':</strong> ';
			echo nl2br($row['feedback']).'</p>';
		}

		//echo '</p>';
		echo '</td></tr>';
		echo '<tr><td colspan="2"><hr /></td></tr>';
	} while ($row = mysql_fetch_array($result));

	if ($this_total) {
		echo '<tr><td colspan="2"><strong>'.$my_score.'/'.$this_total.'</strong></td></tr>';
	}
	echo '</table>';
} else {
	echo '<p>'._AT('no_questions').'</p>';
}
echo '</form>';

require(AT_INCLUDE_PATH.'footer.inc.php');
?>