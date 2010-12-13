<?php
/*****
* 
* Copyright 2010 Keith Dsouza (dsouza.keith@gmail.com / http://keithdsouza.com)
* 
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
*  You may obtain a copy of the License at
*
*      http://www.apache.org/licenses/LICENSE-2.0
*
*  Unless required by applicable law or agreed to in writing, software
*  distributed under the License is distributed on an "AS IS" BASIS,
*  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
*  See the License for the specific language governing permissions and
*  limitations under the License.
* 
*****/

/*
Plugin Name: Wordpress Portability Kit
Plugin URI: http://techie-buzz.com/wordpress-portability-kit
Description: Wordpress Portability Kit allows users to easily move their WordPress Plugins and themes from one place to another, this could be useful when you are moving hosts or setting up a new blog and want to use plugins and themes from you existing blog on your new blog
Version: 0.1
Author: Keith Dsouza
Author URI: http://techie-buzz.com/

WordPress Portability Kit helps you port from one blog to another easily carrying your plugins and themes

*. Creates an archive of active plugins on current blog
*. Creates an archive of active theme on current blog
*. Uploads and activates all your old  plugins to your new blog
*. Uploads and activates all your old themes to your new blog


Usage Instructions
-------------------------

Go to Manage -> Portability Kit and follow the instructions to create your portability kit.


Copyright 2007  Keith Dsouza  (email : dsouza.keith at gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

@ define('WPK_PAGE', 'wordpress-portability-kit/wordpress-portability-kit.php');
@ define('WPK_LOG_FILE', 'wpk-log-data.txt');
$wpContentDir = trailingslashit(trailingslashit(ABSPATH) . 'wp-content');
@ define('WPK_CONTENT_DIR', $wpContentDir);
$pluginDir = trailingslashit(trailingslashit($wpContentDir) . 'plugins');
@ define('WPK_PLUGIN_DIR', $pluginDir);
$themeDir = trailingslashit(trailingslashit($wpContentDir) . 'themes');
@ define('WPK_THEMES_DIR', $themeDir);
@ define('WPK_KIT_NAME', 'wpk-portability-kit');
@ define('WPK_EXTENSION', '.zip');
//the logger class
require_once ('wpk_helper.class.php');

$wpIncludeDirs = array (
	'wp-admin',
	'wp-includes'
);



if (isset ($_REQUEST['task'])) {
	$task = $_REQUEST['task'];
}

function wpk_manage_page() {
	add_submenu_page('tools.php', 'Portability Kit', 'Portability Kit', 2, 'wordpress-portability-kit/wordpress-portability-kit.php', 'wp_portability_kit');
}

function wp_portability_kit() {
	if (!user_can_access_admin_page()) {
		return false;
	}

	if (isset ($_REQUEST['_wpnonce'])) {
		if (function_exists('check_admin_referer')) {
			check_admin_referer('wordpress-portability-kit');
		}
	}

	global $task;
	switch ($task) {
		case 'upkit' :
			wpk_create_kit();
			break;
		case 'downkit' :
			wpk_unpack_kit();
			break;
		case 'restore' :
			wpk_restore_kit();
			break;
		default :
			wpk_show_kit_start();
			break;
	}
}

function wpk_show_kit_start() {
	echo "<pre>";
  print_r($_REQUEST);
  echo "</pre>";
  wpk_start_html();
?>
		<form action="tools.php?page=<?php echo WPK_PAGE; ?>" method="post" name="frmWPK1" enctype="multipart/form-data" onsubmit="return verifySelected(this);">
<?php

	if (function_exists('wp_nonce_field')) {
		wp_nonce_field('wordpress-portability-kit');
	}
?>
		<p><strong>Choose From the Options Below, what you would like to do.</strong></p>
		<p>
		<input type="Radio" name="task" value="upkit" onclick="showOptions('up');" /> Create A Portability Kit<br />
		<input type="Radio" name="task" value="downkit" onclick="showOptions('down')"  /> Restore A Portability Kit<br />
		</p>
		<div style="visibility:hidden;display:none" id="wpkoptionsdiv">
		<p><strong  id="wpkoptions"></strong></p>
		<p>
		<input type="Checkbox" name="plugins" value="true" /> Plugins<br />
		<input type="Checkbox" name="themes" value="true" /> Themes<br />
		<!--input type="Checkbox" name="settings" value="yes" /> Settings<br /-->
		</p>
		</div>
		<div style="visibility:hidden;display:none" id="wpkfilediv">
		<p>
		<input type="File" name="thefile" accept="application/x-zip-compressed" /> The portability Zip File Created Earlier
		</p>
		</div>
		<div style="visibility:hidden;display:none" id="wpkbuttondiv">
		<p>
			<input type="Submit" name="wpkstartkit" value="Lets GO" />
		</p>
		</div>
		
		
		</form>
<?php

	wpk_end_html();
}

function wpk_admin_head_js() {
?>
			<script type="text/javascript" language="JavaScript">
			function showOptions(what) {
				document.getElementById('wpkoptionsdiv').style.visibility = 'visible';
				document.getElementById('wpkoptionsdiv').style.display = 'inline';
				document.getElementById('wpkbuttondiv').style.visibility = 'visible';
				document.getElementById('wpkbuttondiv').style.display = 'inline';
				if(what.toLowerCase()  == 'up') {
					document.getElementById('wpkoptions').innerHTML = "Choose the options you want to Create the Portability Kit from";
					document.getElementById('wpkfilediv').style.visibility = 'hidden';
					document.getElementById('wpkfilediv').style.display = 'none';
				}
				
				if(what.toLowerCase()  == 'down') {
					document.getElementById('wpkoptions').innerHTML = "Choose the options you want to Restore from the portability Kit";
					document.getElementById('wpkfilediv').style.visibility = 'visible';
					document.getElementById('wpkfilediv').style.display = 'inline';
				}
				
				
			}
			
			function verifySelected(frmObj) {
				if(! isChecked(frmObj.task)) {
					alert("Please select atleast one task to be performed");
					return false;
				}
				//if(! isChecked(frmObj.plugins) && !isChecked(frmObj.themes) && !isChecked(frmObj.settings)) {
					
				if(! isChecked(frmObj.plugins) && !isChecked(frmObj.themes)) {
					alert("Please select atleast one option to be restored");
					return false;
				}
				return true;
			}
			
			//Function to check whether a radio button is checked or not
			function isChecked (frmField)
			{ 
				var i, flg;
				flg = false;
				if(frmField != null){
					if(frmField.length > 1){
						for(i=0; i < frmField.length; i++){
							if (frmField[i].checked == true){
								flg=true;
								break;
							}
						}
					}
					else{
						if(frmField.checked == true){
							flg=true;
						}
					}
				}
				return flg;
			}
			
		</script>
<?

}

/**
* Function to back up the existing wordpress installation files
**/
function wpk_create_kit() {
	if (!current_user_can('edit_files')) {
		echo 'Oops sorry you are not authorized to do this';
		return false;
	}

  set_time_limit ( 0 ) ;

	require_once ('wpk_create_kit.class.php');
	$plugins = false;
	$themes = false;
	if(isset($_REQUEST['plugins']) && $_REQUEST['plugins'] == 'true') {
		$plugins = true;
	}
	if(isset($_REQUEST['themes']) && $_REQUEST['themes'] == 'true') {
		$themes = true;
	}
	/*if(isset($_REQUEST['settings'])) {
		$settings = true;
	}*/
	$create_kit = new wpkCreateKit($plugins, $themes, false);
	if($create_kit->create_portability_kit()) {
		//need to show message that creation is complete and file is available for download
		$message = 'Congrats your Portability Kit has been created.' .
				'Please <a href="'.get_bloginfo('siteurl').'/wp-content/'.$_SESSION['wpkkitname'].'">DOWNLOAD IT</a> so that you can restore the same plugins and theme on another ' .
				'WordPress site.<br /><br /><h2><a href="'.get_bloginfo('siteurl').'/wp-content/'.$_SESSION['wpkkitname'].'">Download Portability Kit</a></h2>';
		wpk_show_logs($message);
	}
  
}

function wpk_unpack_kit() {
	if (!current_user_can('edit_files')) {
		echo 'Oops sorry you are not authorized to do this';
		return false;
	}
	require_once ('wpk_restore_kit.class.php');
	
	$plugins = false;
	$themes = false;
	if(isset($_REQUEST['plugins']) && $_REQUEST['plugins']) {
		$plugins = true;
	}
	if(isset($_REQUEST['themes']) && $_REQUEST['themes']) {
		$themes = true;
	}
	
	if(isset($_FILES)) {
		$restore_kit = new wpkRestoreKit(false, false, false);
		if($restore_kit->unpack_portability_kit($_FILES)) {
			unset($restore_kit);
			$redirect = '?page='.WPK_PAGE .'&task=restore&plugins='.$plugins.'&themes='.$themes;
			wpk_redirect($redirect);
		}
		else {
			wpk_message ('OOPS we could not restore the portability kit. There were some errors please check the logs below to see the errors.');
			wpk_show_logs($restore_kit->get_logs());
		}
		unset($restore_kit);
	}
	else {
		wpk_message ('OOPS no files were uploaded to restore from. Please select a file to restore the backup from.');
		wpk_show_kit_start();
	}
}
function wpk_restore_kit() {
	
	$plugins = false;
	$themes = false;
	if(isset($_REQUEST['plugins']) && $_REQUEST['plugins']) {
		$plugins = true;
	}
	if(isset($_REQUEST['themes']) && $_REQUEST['themes']) {
		$themes = true;
	}
	/*if(isset($_REQUEST['settings'])) {
		$settings = true;
	}*/
	require_once ('wpk_restore_kit.class.php');
	$restore_kit = new wpkRestoreKit($plugins, $themes, false);
	
	//plugin reactivation takes subtask since
	//some plugins may not be compatible with current version
	if(isset($_REQUEST['subtask']) && trim($_REQUEST['subtask']) == 'restoreplug') {
		$pluginid = intval($_REQUEST['pluginid']);
		$restore_kit->restore_plugins($pluginid);
	}
	else {
		if($restore_kit->restore_portability_kit()) {
			wpk_message ('Congratulations all the plugins and themes were activated succesffully');
			wpk_show_log($restore_kit->get_logs());
		}
		else {
			wpk_message ('The plugins and themes could not be activated.');
			wpk_show_log($restore_kit->get_logs());
		}
	}
	if(isset($_SESSION['wpkkitname'])) {
		$file_name = $_SESSION['wpkkitname'];
		@unlink($file_name);
	}
}

/** start html **/
function wpk_start_html() {
?>
		<div align="left" style="margin-left:30px;"><br /><br />
			<div>
				<h2>Wordpress Portability Kit: Take Your WordPress Installation where you go</h2>
<?php

}


/** end html **/
function wpk_end_html() {
?>
			</div>
		</div>
		<div style="clear:both"></div>
<?php

}

function wpk_message($message) {
	echo '<div id="message"  class="updated fade">';
	echo '<b><span style="color:red">'.$message.'</span><br /><br /></b>';
	echo '</div>';
}

function wpk_show_logs($logs) {
	wpk_start_html();
	echo '<div><b>Below are the logs for the task</b><br /><br />'.$logs.'</div>';
	wpk_end_html();
}

function wpk_redirect($goto = '') {
	$goto = (!$goto ? "/" : $goto);

	if(strpos(strtolower('-'.$_SERVER['SERVER_SOFTWARE']), strtolower('Microsoft-IIS')) ) {
		$header='Refresh: 0; URL='.$goto;
	} else {
		$header='Location: '.$goto;
	}

	// if header() redirect fails, use a javascript redirection
	if(!@header($header)) {
		echo '
			<html><head><title>Redirecting..</title></head><body>
			<script type="text/javascript">
			<!--
				document.location = "'.$goto.'";
			//-->
			</script>
			</body></html>
			';
	}
	exit;
}

add_action('admin_menu', 'wpk_manage_page');
add_action('admin_head', 'wpk_admin_head_js');
?>