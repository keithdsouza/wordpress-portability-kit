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

class wpkCreateKit extends wpkHelper {
	var $archive_name;
	var $archiver;
	var $port_plugins;
	var $port_themes;
	var $port_settings;
	var $plugin_data;
	var $theme_data;
	var $is_file_written;
	var $file_name;
	
	function wpkCreateKit($port_plugins = true, $port_themes = true, $port_settings = false) {
		$this->is_file_written = false;
		$this->files = array();
		$this->archive_name = WPK_CONTENT_DIR;
		$this->file_name = WPK_KIT_NAME . '-' . $this->random() . WPK_EXTENSION;
		$this->port_plugins = $port_plugins;
		$this->port_themes = $port_themes;
		$this->port_settings = $port_settings;
		
		$_SESSION['wpkkitname'] = $this->file_name;
		$this->archiver = new PclZip($this->archive_name . $this->file_name);
		$this->log_message('<br /><br /><br /><strong>Creating</strong> files backup archive at '.$this->file_name.'<br /><br /><br />');
	}
	
	/** creates a archive based on current object **/
	function create_portability_kit() {
		global $wpdb;
		if( ! current_user_can('edit_files')) {
			echo 'Oops sorry you are not authorized to do this';
			return false;
		}
		
		if($this->port_plugins) {
			$currentPlugins = get_option('active_plugins');
			array_splice($currentPlugins, array_search(WPK_PAGE, $currentPlugins), 1 );
			$this->print_array($currentPlugins);
			$path = "../";
			foreach($currentPlugins as $plugin) {
				if ( $this->validate_file($path.$plugin) ) {
					$this->plugin_data .= $plugin."\n";
					if(stristr($plugin, "/") === FALSE) {}
					else {
						$pluginDir = split("/", $plugin);
						$plugin = $pluginDir[0];
					}
					$this->archive_dir(WPK_PLUGIN_DIR .$plugin, WPK_PLUGIN_DIR .$plugin, WPK_PLUGIN_DIR .$plugin, true);
				}
        $this->log_message("Archiving Plugin $plugin<br/> ");
			}
			
			$handle = fopen(WPK_CONTENT_DIR.'wpk-plugins.txt', 'w');
			if($handle) {
					fwrite($handle, $this->plugin_data);
					fclose($handle);
				}
				array_push($this->files, trailingslashit(WPK_CONTENT_DIR) . 'wpk-plugins.txt');
		}
    $this->log_message("Finished Archiving Plugins<br/> ");
		
		if($this->port_themes) {
			$ct = current_theme_info();
			$templateDir = $ct->template_dir;
			$templateDir = str_replace('wp-content/themes/', '', $templateDir);
			$this->theme_data .= $ct->template."\n";
			$this->theme_data .= $ct->stylesheet."\n";
			$this->archive_dir(WPK_THEMES_DIR.$templateDir, WPK_THEMES_DIR.$templateDir, WPK_THEMES_DIR.$templateDir, true);
			$handle = fopen(WPK_CONTENT_DIR.'wpk-themes.txt', 'w');
			if($handle) {
				fwrite($handle, $this->theme_data);
				fclose($handle);
			}
			array_push($this->files, trailingslashit(WPK_CONTENT_DIR) . 'wpk-themes.txt');
		}
		$this->log_message("Finished Archiving Themes<br/> ");
		$kit_complete = false;	
		if($this->write_to_disk()) {
			$this->log_message("Wrote File To Disk<br/> ");
      $kit_complete = true;
		}
    $this->log_message("After Writing the Files to Disk<br/> ");
		if($this->port_plugins) {
			unlink(WPK_CONTENT_DIR . "wpk-plugins.txt");
		}
		if($this->port_themes) {
			unlink(WPK_CONTENT_DIR . "wpk-themes.txt");
		}
		if($this->port_settings) {
			unlink(WPK_CONTENT_DIR . "wpk-settings.txt");
		}
    $this->log_message("Before Returning Back<br/> ");
		return $kit_complete;
	} //end createArchive
	
	function archive_dir($start, $dirName, $zipPath, $addSubDir = false){
    $basename = pathinfo($start);
    $basename = $basename['basename'];
		$this->log_message("Archiving " . WPK_PLUGIN_DIR .$start."<br>");
		$this->log_message("BASENAME IS ".$basename."<br>");
    $ls=array();
		if(is_dir($start)) {
			$dir = dir($start);
	    while($item = $dir->read()) {
	        if(($item != "." && $item != ".." && is_dir($start. $item)) 
							&& $addSubDir) {
							$this->archive_dir($start. $item, $start .  $item, $zipPath .  $item);
							
	        }
					else{
	            if( ( $item!="."&&$item!=".." ) && ( ! is_dir($start. $item) ) ) {
									array_push($this->files, trailingslashit($dirName) . $item);
									$this->log_message("Adding File $dirName/$item $zipPath/$item<br />");
	            }
	        }
	    }
		}
		else {
			if( $start != "." && $start != ".." ) {
				array_push($this->files, $dirName );
				$this->log_message("Adding File $dirName/$start $zipPath/$start<br />");
       }
		}
    
	}  //end archiveDir
	
	function write_to_disk() {
    $this->log_message("Inside Writ to Disk<br/> ");
		$v_list = $this->archiver->create($this->files, PCLZIP_OPT_REMOVE_PATH, WPK_CONTENT_DIR);
    $this->log_message("What happened here ?<br/> ");
		if ($v_list == 0) {
			$this->log_message('Could not archive the files '. $this->archiver->errorInfo(true));
		 	$this->is_file_written = false;
		 	return false;
  	}
		else {
			$this->log_message('<br /><strong>Succesfully Created </strong>files backup archive at '. $this->archive_name .'<br /><br />');
			if(is_file($this->archive_name)) {
				@chmod($this->archive_name, 0646);
			}
			$this->is_file_written = true;
			return true;
		}
    $this->log_message("Finished Writing to Disk<br/> ");
	} //end writeToDisk

	
	
	function validate_file($file, $allowed_files = '') {
		if ( false !== strpos($file, './'))
			return 1;

		if (':' == substr($file,1,1))
			return 2;

		if ( !empty($allowed_files) && (! in_array($file, $allowed_files)) )
			return 3;

		return 0;
	}

}

?>