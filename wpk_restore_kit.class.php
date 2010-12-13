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

class wpkRestoreKit extends wpkHelper {
	var $archiver;
	var $port_plugins;
	var $port_themes;
	var $port_settings;
	var $file_name;

	function wpkRestoreKit($port_plugins = true, $port_themes = true, $port_settings = false) {
		$this->port_plugins = $port_plugins;
		$this->port_themes = $port_themes;
		$this->port_settings = $port_settings;
		//$this->log_message('LOG -> <br /><br /><br /><strong>Creating</strong> files backup archive at ' . $this->file_name . '<br /><br /><br />');
	}

	function unpack_portability_kit($file_data) {
		if (isset ($file_data)) {
			$this->file_name = WPK_KIT_NAME . '-' . $this->random() . WPK_EXTENSION;
			$_SESSION['wpkkitname'] = $this->file_name;
			$pathinfo = pathinfo($file_data['thefile']['name']);
			if ($this->validate_uploaded_extension($pathinfo['extension'])) {
				$this->log_message('SUCCESS -> We got a valid file to be uploaded<br />');
				if ($this->upload($file_data)) {
					$this->log_message('SUCCESS -> The portability kit file was uploaded to ' . WPK_CONTENT_DIR . $this->file_name.'<br />');
					if (is_file(WPK_CONTENT_DIR . $this->file_name)) {
						if ($this->unzip()) {
							return true;
						}
						else {
							@unlink(WPK_CONTENT_DIR . $this->file_name);
							return false;
						}
					} 
					else {
						return false;
					}
				} else {
					$this->log_message('ERROR -> Could not upload the file to the directory<br />');
				}
			} else {
				$this->log_message('ERROR -> The file you are trying to upload is not a valid file, please upload only the zip file that was created earlier.<br />');
				return false;
			}
		} else {
			$this->log_message('ERROR -> You did not select any files to be uploaded<br />');
			return false;
		}
	}

	function restore_portability_kit($startFrom = 0) {
				
		if($this->port_themes) {
			if (!file_exists(WPK_CONTENT_DIR . 'wpk-themes.txt')) {
				$this->log_message('ERROR -> Corrupted Portability Kit. Themes information is missing.<br />');
				return false;
			}
			
			if(! $this->restore_themes()) {
				$this->log_message('ERROR -> Could not restore the theme due to some error.<br />');
				return false;
			}
		}
		
		if ($this->port_plugins) {
			if (!file_exists(WPK_CONTENT_DIR . 'wpk-plugins.txt')) {
				$this->log_message('ERROR -> Corrupted Portability Kit. Plugins information is missing.<br />');
				return false;
			}
			
			if(! $this->restore_plugins()) {
				$this->log_message('ERROR -> Could not restore the plugins due to some error.<br />');
				return false;
			}
		}
		
		return true;
	}

	/**
	 * restore the themes from old site
	 */
	function restore_themes() {
		if ( ! current_user_can('switch_themes') ) {
			$this->log_message('ERROR -> Current user is not allowed to switch themes<br />');
			return false;
		}
		$this->log_message('LOG -> Restoring Themes.<br />');
		$themes = file(WPK_CONTENT_DIR . "wpk-themes.txt");
		if(count($themes) > 0 && count($themes) == 2) {
			update_option('template', $themes[0]);
			update_option('stylesheet', $themes[1]);	
			//run all hooks on current theme
			do_action('switch_theme', get_current_theme());
			unlink(WPK_CONTENT_DIR . "wpk-themes.txt");
			return true;
		}
		else {
			$this->log_message('ERROR -> Could not activate the theme successfully due to improper restore kit.');
			return false;
		}
	}
	
	/**
	 * restores the plugins for the user 
	 * I had a bad experience with WPAU where plugins would
	 * not be activated if they had errors so let's skip those
	 * and start from next plugin
	 */
	function restore_plugins($start_from = 0) {
		$this->log_message('LOG -> Restoring Plugins.<br />');
		$plugins = file(WPK_CONTENT_DIR . "wpk-plugins.txt");
		if(count($plugins) > 0) {
			for ($i = $start_from; $i < count($plugins); $i++) {
				$this->log_message('LOG -> Restoring Plugins.<br />'. $plugins[$i] .'<br />');
				$this->reactivate_plugin($plugins[$i], $i);
			}
		}
		unlink(WPK_CONTENT_DIR . "wpk-plugins.txt");
		return true;
	}

	function reactivate_plugin($plugin_file, $our_id) {
		$plugin_file = trim($plugin_file);
		if (!current_user_can('edit_plugins')) {
			$this->log_message('Oops sorry you are not authorized to activate plugins.');
			return false;
		}
		clearstatcache(); //clear all stat cache
		$current = get_option('active_plugins');
		$path = "../";
		
		if (validate_file($path . $plugin_file)) {
			$file_name = ABSPATH . PLUGINDIR . "/" . $plugin_file;
			if (!file_exists($file_name)) {
				$this->log_message('ERROR -> Plugin ' . $plugin_file . ' file does not exist<br/>');
			}
			//sometimes plugin reactivation may fail so we need to redirect back 
			//to reactivate other plugins
			if (!in_array($plugin_file, $current)) {
				ob_start();
				$our_id = intval($our_id);
				$our_id++;
				echo '<script language="JavaScript" type="text/javascript"> ' .
				' window.location = "edit.php?page=' . WPK_PAGE . '&task=restore&subtask=restoreplug&pluginid=' . $our_id . '"' .
				'</script>';
				if ($file_included = @ include (ABSPATH . PLUGINDIR . '/' . $plugin_file)) {
					$current[] = $plugin_file;
					sort($current);
					update_option('active_plugins', $current);
					do_action('activate_' . $plugin_file);
					$this->log_message('SUCCESS -> Plugin <strong>' . $plugin_file . '</strong> was activated succesfully<br>');
				} else {
					$this->log_message('ERROR -> <span style="color:red">Plugin <strong>' . $plugin_file . '</strong> could not be activated succesfully. You will need to activate it manually.</span><br>');
				}
				ob_end_clean();
			} else {
				$this->log_message('ERROR -> Plugin <strong>' . $plugin_file . '</strong> is already activated<br>');
			}
		}
		return true;
	}
	
	/**
	 * ports settings into the db, will not be used as of yet
	 * the wp_options table has some missing features will which we
	 * will keep this on hold
	 * 
	 * this would have made my life easier to activate themes and plugins
	 * but lets keep it on hold and use the built in tool to do that
	 */
	function restore_settings() {
		global $wpdb;
		if (!file_exists(WPK_CONTENT_DIR . 'wpk-settings.txt')) {
			$this->log_message('ERROR -> Corrupted Portability Kit. Settings information is missing.<br />');
			return false;
		}
		$settings = file(WPK_CONTENT_DIR . "wpk-settings.txt");
		for ($i = 0; $i < count($settings); $i++) {
			$wpdb->query($settings[$i]);
		}
	}

	/**
	* Upload the files to the directory
	**/
	function upload($file_data) {
		if (is_uploaded_file($file_data['thefile']['tmp_name'])) {
			if (move_uploaded_file($file_data['thefile']['tmp_name'], WPK_CONTENT_DIR . $this->file_name)) {
				$this->log_message('SUCCESS -> Succesfully moved the uploaded file to the directory<br />');
				return true;
			} else {
				$this->log_message('ERROR -> Could Not Move Uploaded files to the directory<br />');
				return false;
			}
		} else {
			$this->log_message('ERROR -> Oops no files uploaded could be some error<br />', true);
			return false;
		}
	}

	function unzip() {
		if (!current_user_can('edit_files')) {
			$this->log_message('ERROR -> Oops sorry you are not authorized to do this');
			return false;
		}
		
		$unzipper = new PclZip(WPK_CONTENT_DIR . $this->file_name);
		$unzipArtchive = false;
		//minimal checks I can do to ensure not any zip files are 
		//accepted, cant beat a nuthead though
		if ($unzipper->extract(PCLZIP_OPT_PATH, "wpk-plugins.txt", 
											PCLZIP_OPT_EXTRACT_AS_STRING) == 0) {
			$unzipArtchive = true;
		}
		if ($unzipper->extract(PCLZIP_OPT_PATH, "wpk-themes.txt", 
											PCLZIP_OPT_EXTRACT_AS_STRING) == 0) {
			$unzipArtchive = true;
		}
		
		if($unzipArtchive) {
			$this->log_message('LOG -> Unzipping the files to ' . WPK_CONTENT_DIR);
			if ($unzipper->extract(PCLZIP_OPT_PATH, WPK_CONTENT_DIR) == 0) {
				$this->log_message('ERROR -> Could not unarchive the file maybe it is a corrupted archive. <br /> Please delete all the files before you can do this. Refresh or click here to delete all the files');
				return false;
			} else {
				$this->log_message('SUCCESS -> <br />All set all files have been extracted<br />');
				return true;
			}	
		}
		else {
			$this->log_message('ERROR -> Invalid zip file uploaded no plugin or theme information available');
			return false;
		}
		
	}

	function validate_file($file, $allowed_files = '') {
		if (false !== strpos($file, './'))
			return 1;

		if (':' == substr($file, 1, 1))
			return 2;

		if (!empty ($allowed_files) && (!in_array($file, $allowed_files)))
			return 3;

		return 0;
	}
}
?>