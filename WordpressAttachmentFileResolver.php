<?php

/**
 * Scans a Wordpress asset directory and creates an associative array of existing files
 */
class WordpressAttachmentFileResolver {
	public static $default_directory = '';

	public static $default_file_cache_folder = '';

	public $directory = '';

	public $_file_cache_location = '';

	/**
	 * @var array|null
	 */
	public $_runtime_cache = null;

	public function __construct($directory = null, $default_file_cache_folder = null) {
		if ($directory !== null) {
			$this->directory = $directory;
		} else if (static::$default_directory) {
			$this->directory = static::$default_directory;
		} else {
			throw new Exception(__CLASS__.' must either pass in $directory as first parameter or set $default_directory config.');
		}

		// Detect if directory even exists
		if (!is_dir($this->directory)) {
			if (file_exists($this->directory)) {
				throw new Exception('"'.$this->directory.'" is a file, not a directory.');
			} else {
				throw new Exception('"'.$this->directory.'" directory does not exist.');
			}
		}

		// File caching is optional
		if ($default_file_cache_folder !== null) {
			$this->_file_cache_location = $default_file_cache_folder;
		} else if (static::$default_file_cache_folder) {
			$this->_file_cache_location = static::$default_file_cache_folder;
		}
		if ($this->_file_cache_location) {
			$this->_file_cache_location = $this->_file_cache_location . DIRECTORY_SEPARATOR . get_class($this) .'_';
		}
	}

	/**
	 * Check if filename is image based on filename
	 *
	 * @return boolean
	 */
	public function isFileExtensionImage($name) {
		static $imageExtensions = array(
			'jpg' => true,
			'png' => true,
			'jpeg' => true,
			'bmp' => true,
			'ico' => true,
			'gif' => true,
			'tiff' => true,
			'jpeg-large' => true,
			'jpg-large' => true,
		);
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		return ($imageExtensions && isset($imageExtensions[$ext]) && $imageExtensions[$ext]);
	}

	/** 
	 * @return string
	 */
	public function extractYearAndMonth($name) {
		return static::extract_year_and_month($name);
	}

	/** 
	 * Get the year/month folder from name.
	 * Uses isset() over count() for performance in 5.x
	 *
	 * Pass in a name like:
	 * - C:\wamp\www\MySite\assets\WordpressUploads\2013\12\02075943\sydharbour-bridge.jpg
	 *
	 * Return:
	 * - 2013/12
	 *
	 * @return string
	 */
	public static function extract_year_and_month($name) {
		$forwardSlashCount = substr_count($name, '/');
		$backSlashCount = substr_count($name, '\\');
		$parts = array();
		if ($backSlashCount > $forwardSlashCount) {
			$parts = explode('\\', $name);
		} else {
			$parts = explode('/', $name);
		}
		foreach ($parts as $i => $part) {
			if (// count($part) == 4
				$part && isset($part[3]) && !isset($part[4]) &&
				// Ensure start of year is 1 or 2 (ie. 1970, 2015)
				($part[0] === '1' || $part[0] === '2')) 
			{
				$month = isset($parts[$i + 1]) ? $parts[$i + 1] : null;

				// count($month) == 2
				if ($month && isset($month[1]) && !isset($month[2])) {
					return $part.'/'.$month; // ie. 2016/02
				}
			}
		}
		return '';
	}

	/** 
	 * Get the year/month/name.jpg folder from filepath.
	 * Uses isset() over count() for performance in 5.x
	 *
	 * Pass names like:
	 * - C:\wamp\www\MySite\assets\WordpressUploads\2015\08\happy.jpg
	 * - C:\wamp\www\MySite\assets\WordpressUploads\2013\12\02075943\sydharbour-bridge.jpg
	 * - C:\wamp\www\MySite\assets\WordpressUploads\2015\08\happy-300x200.jpg
	 *
	 * Returns respectively:
	 * - 2015/08/happy.jpg
	 * - 2013/12/02075943/sydharbour-bridge.jpg
	 * - 2015/08/happy.jpg (only if you provide $dimensions array to extract width/height)
	 *
	 * @param $dimensions If provided, will 
	 * @return string
	 */
	public static function extract_all_after_year($name, &$dimensions = null) {
		$forwardSlashCount = substr_count($name, '/');
		$backSlashCount = substr_count($name, '\\');
		$parts = array();
		if ($backSlashCount > $forwardSlashCount) {
			$parts = explode('\\', $name);
		} else {
			$parts = explode('/', $name);
		}
		foreach ($parts as $index => $part) {
			if (// count($part) == 4
				$part && isset($part[3]) && !isset($part[4]) &&
				// Ensure start of year is 1 or 2 (ie. 1970, 2015)
				($part[0] === '1' || $part[0] === '2')) 
			{
				$month = isset($parts[$index + 1]) ? $parts[$index + 1] : null;

				// count($month) == 2
				if ($month && isset($month[1]) && !isset($month[2])) {
					if ($dimensions !== null) {
						// Manually stich the parts together
						$count = count($parts);
						$result = '';
						for ($i = $index; $i < $count - 1; ++$i) {
							$result .= $parts[$i].'/';
						}
						$lastPart = $parts[$count-1];
						$result .= $lastPart;
						$matches = array();
						// Extract dimensions from files like "2014/01/deng-300x200.jpg"
						preg_match("#\d{1,5}x\d{1,5}#", $lastPart, $matches);
						if ($matches) {
							$dimensionString = $matches[0];
							$dimensions = explode('x', $dimensionString);
							// Remove '-300x200' from filename so it references the original file
							// and not a thumbnail
							$result = str_replace('-'.$dimensionString, '', $result);
						} else {
							$dimensions = false;
						}
						return $result; // ie. 2015/08/happy.jpg
					} else {
						return implode('/', array_slice($parts, $index)); // ie. 2015/08/happy.jpg
					}
				}
			}
		}
		return '';
	}

	/**
	 * Returns an array of the files that have that basename.
	 *
	 * If false = Cannot find file in database or on HDD
	 * If null  = Invalid data/array passed in.
	 *
	 * @return array|false|null
	 */
	public function getFilepathsFromRecord($wpData) {
		if (isset($wpData['guid'])) {
			// NOTE(Jake): Attempts to access cache to avoid function call overhead.
			$files = ($this->_runtime_cache !== null) ? $this->_runtime_cache : $this->getFilesRecursive();
			$basename = basename($wpData['guid']);
			if (isset($files[$basename])) {
				return $files[$basename];
			}
			return false;
		}
		return null;
	}

	public function getFilesRecursive(){
		if ($this->_runtime_cache !== null) {
			return $this->_runtime_cache;
		}
		if (!$this->isFlushingCache() && ($results = $this->getFileCache(__FUNCTION__))) {
			return $this->_runtime_cache = $results;
		}

		// Get files
	    $results = array();
	    self::_get_files_recursive_sub($this->directory, $results);

	    $this->setFileCache(__FUNCTION__, $results);
	    return $this->_runtime_cache = $results;
	}

	public function isFlushingCache() {
		if (isset($_GET['flush']) || isset($_GET['flush_wp_file'])) {
			return true;
		}
		return false;
	}

	//
	// Protected
	//

	public static function _get_files_recursive_sub($dir, &$results = array()){
	    $files = scandir($dir);

	    foreach($files as $key => $value){
	        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
	        if(!is_dir($path)) {
	        	$basename = basename($path);
	            $results[$basename][] = $path;
	        } else if($value != "." && $value != "..") {
	            self::_get_files_recursive_sub($path, $results);
	        }
	    }

	    return $results;
	}

	//
	// File Caching
	//
	public function getFileCache($name) {
		if (!$this->_file_cache_location) {
			return null;
		}
		$path = $this->_file_cache_location . $name;
		if(file_exists($path)) {
            $result = json_decode(file_get_contents($path), true);
            return $result;
        }
        return false;
	}

	public function setFileCache($name, $data) {
		if (!$this->_file_cache_location) {
			return null;
		}
        $path = $this->_file_cache_location . $name;
        file_put_contents($path, json_encode($data));
        
        return true;
    }

    public function clearCacheFile($name) {
    	if (!$this->_file_cache_location) {
			throw new Exception('_file_cache_location not set on '.get_class($this).', cannot clear.');
		}
        $path = $this->_file_cache_location . $name;
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }
}