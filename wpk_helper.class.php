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

/**
	Wordpress Portablility Kit upgrades helper class
	Helps sub classes log all data
	Helps to update db with logs
	Helps to run miscelleaneous functions
**/

if(! class_exists('PclZip')) {
  require_once('lib/pclzip.lib.php');
}


class wpkHelper {
	var $loggedData;
	var $errorData;
	var $errorFlag;
	var $fatalError; // if its flagged as a fatal error we cannot continue with further process
	
	function wpkHelper() {
		$this->loggedData = '';
		$this->errorData = '';
		$this->errorFlag = false;
		$this->fatalError = false;
	}
	
	/** log messages **/
	function log_message($logText) {
		$logText = str_replace('LOG', '<span style="font-weight:bold;">LOG</span>', $logText);
		$logText = str_replace('SUCCESS', '<span style="font-weight:bold;color:blue">SUCCESS</span>', $logText);
		$logText = str_replace('ERROR', '<span style="font-weight:bold;color:red">ERROR</span>', $logText);
    echo $logText;
		$this->loggedData .= $logText;
		//echo $logText;
	}
	
	function get_logs() {
		return $this->loggedData;
	}
	
	function logError($logError, $fatalError = false) {
		$this->errorFlag = true;
		$this->fatalError .= $fatalError;
		$this->errorData .= $logError;
	}
	
	/** create a random name **/
	function random() {
		$chars = "abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ023456789";
    srand((double)microtime()*1000000);
    $i = 0;
    $rand = '' ;

    while ($i <= 7) {
        $num = rand() % 33;
        $tmp = substr($chars, $num, 1);
        $rand = $rand . $tmp;
        $i++;
    }
    return $rand;
	}
	
	function write_log_to_disk($filePath, $fileName, $fileData) {
		$filePath = trailingslashit($filePath);
		if(@file_exists($filePath . $fileName))  @unlink($filePath . $fileName);
		$fileHandle = @fopen($filePath . $fileName, 'w');
		if(@fwrite($fileHandle, $fileData) === false) {
			echo '<br>Some error while writing the log file<br>';
			return false;
		}
		else {
			@fclose($fileHandle);
			return true;
		}
	}
	
	function print_array($theArray) {
		echo "<pre>";
		print_r($theArray);
		echo "</pre>";
	}
	
	function validate_uploaded_extension($extension) {
		$this->log_message("LOG -> File Extension is $extension<br />");
		if(in_array($extension,  array('zip'))) {
			return true;
		}
		else {
			return false;
		}
	}
	
	function chmod_R($path, $filemode) { 
    if (!is_dir($path))
       return chmod($path, $filemode);

    $dh = opendir($path);
    while ($file = readdir($dh)) {
        if($file != '.' && $file != '..') {
            $fullpath = $path.'/'.$file;
            if(!is_dir($fullpath)) {
              if (!chmod($fullpath, $filemode))
                 return FALSE;
            } else {
              if (!chmod_R($fullpath, $filemode))
                 return FALSE;
            }
        }
    }
 
    closedir($dh);
    
    if(chmod($path, $filemode))
      return TRUE;
    else 
      return FALSE;
	}
	
}

?>
