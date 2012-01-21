<?php
/**
* Crowdin File Manager
*
* @author     Kenji Suzuki https://github.com/kenjis
* @copyright  2012 Kenji Suzuki
* @license    MIT License http://www.opensource.org/licenses/mit-license.php
*/

namespace Fuel\Tasks;

class File
{
	static $docs_dir;
	static $file_type;
	static $cached_data;
	static $api_url;
	static $project_identifier;
	static $project_key;
	
	static $max_process = 10;
	
	/**
	* Show Usage
	*
	* Usage (from command line):
	*   php oil r file
	*
	* @return string
	*/
	public static function run()
	{
		echo <<<EOL
Usage:
  oil refine file:check  ... check status of local files
  oil refine file:update ... update files using Web API
EOL;
	}

	private static function get_config()
	{
		\Config::load('crowdin', 'crowdin');
		\Config::load('crowdin_filelist', 'crowdin_filelist');
		
		static::$docs_dir    = \Config::get('crowdin.docs_dir');
		static::$file_type   = \Config::get('crowdin.file_type');
		static::$cached_data = \Config::get('crowdin_filelist.filelist_data');
		
		static::$project_identifier = \Config::get('crowdin.crowdin.project-identifier');
		static::$project_key        = \Config::get('crowdin.crowdin.project-key');
		
		static::$api_url = 'http://api.crowdin.net/api/project/' . static::$project_identifier;
	}
	
	/**
	 * Update Crowdin Project Files with Web API
	 *
	 * Usage (from command line):
	 *   php oil r file:update
	 *
	 * @return string
	 */
	public static function update()
	{
		static::_update(true);
	}
	
	/**
	* Check Status of Local Files
	*
	* Usage (from command line):
	*   php oil r file:check
	*
	* @return string  file list of status
	*/
	public static function check()
	{
		static::_update(false);
	}
	
	private static function _update($commit = false)
	{
		static::get_config();

		$docs_dir    = static::$docs_dir;
		$cached_data = static::$cached_data;
		
		$list = \File::read_dir($docs_dir);
		$list = static::convert_filelist($list);
		//var_dump($list);
		
		$new     = 0;
		$update  = 0;
		$delete  = 0;
		$process = 0;
		
		foreach ($cached_data as $file => $val)
		{
			if ( ! in_array($file, $list))
			{
				// file deleted
				if ( ! $commit)
				{
					echo 'deleted: ', $file, "\n";
				}
				$delete++;
				
				if ($commit)
				{
					if ($process >= static::$max_process)
					{
						break;
					}
					
					$filename = $docs_dir . $file;
					$ret = static::delete_file($file, $filename);
					$process++;
					
					if ($ret === false)
					{
						$delete--;
					}
					
					if ($process >= static::$max_process)
					{
						break;
					}
				}
			}
		}
		
		foreach ($list as $file)
		{
			$fileinfo = pathinfo($file);
			
			// only process files with specific file extension
			if ($fileinfo['extension'] === static::$file_type['ext'])
			{
				$filename = $docs_dir . $file;
				$md5 = md5(file_get_contents($filename));
				
				if ( ! isset($cached_data[$file]['md5']))
				{
					// add file
					if ( ! $commit)
					{
						echo 'new: ', $file, "\n";
					}
					$new++;
					
					if ($commit)
					{
						if ($process >= static::$max_process)
						{
							break;
						}
						
						$ret = static::add_file($file, $filename);
						$process++;
						
						if ($ret === false)
						{
							$new--;
						}
						
						if ($process >= static::$max_process)
						{
							break;
						}
					}
				}
				else if ($md5 !== $cached_data[$file]['md5'])
				{
					// update file
					if ( ! $commit)
					{
						$cached_mtime = $cached_data[$file]['mtime'];
						$cached_mtime = new \DateTime('@' . $cached_mtime);
						$cached_size  = $cached_data[$file]['size'];
						
						$fileinfo = \File::file_info($filename);
						$size  = $fileinfo['size'];
						$mtime = $fileinfo['time_modified'];
						$mtime = new \DateTime('@' . $mtime);

						echo 'updated: ' . $file . "\n" .
							' ' . $cached_mtime->format('Y/m/d H:i:s') . ' -> ' . 
							$mtime->format('Y/m/d H:i:s') . "\n" .
							' ' . $cached_size . ' -> ' .
							$size . "\n";
					}
					$update++;
					
					if ($commit)
					{
						if ($process >= static::$max_process)
						{
							break;
						}
						
						$ret = static::update_file($file, $filename);
						$process++;
						
						if ($ret === false)
						{
							$update--;
						}
						
						if ($process >= static::$max_process)
						{
							break;
						}
					}
				}
			}
		}
		
		if ($commit)
		{
			\Config::set('crowdin_filelist.filelist_data', static::$cached_data);
			\Config::save('crowdin_filelist', 'crowdin_filelist');
			
			echo "\n";
			echo 'added: ' . $new . ', updated: ' . $update . ', deleted: ' .
					$delete . "\n";
		}
		else
		{
			echo "\n";
			echo $new . ' new files, ' . $update . ' updated files, ' . 
					$delete . ' deleted files' . "\n";
		}
	}
	
	/**
	* Convert Filelist Array to Single Dimension Array
	* 
	* @param  array   filelist array of \File::read_dir()
	* @param  string  directory
	* @return array
	*/
	private static function convert_filelist($arr, $dir = '')
	{
		static $list = array();
		
		foreach ($arr as $key => $val)
		{
			if (is_array($val))
			{
				static::convert_filelist($val, $dir . $key);
			}
			else
			{
				$list[] = $dir . $val;
			}
		}
		
		return $list;
	}
	
	private static function add_file($file, $local_path)
	{
		$request_url = static::$api_url .
						'/add-file?key=' . static::$project_key . '&type=' .
						static::$file_type['type'];
		$post_params = array();		
		$post_params['files[' . $file. ']'] = '@' . $local_path;
		
		//var_dump($request_url);
		//var_dump($post_params);
		
		static::unbuffered_echo('adding file: ' . $file . "\n");
		
		$result = static::post_to_api($request_url, $post_params);
		//var_dump($result);
		
		if ($result === false)
		{
			static::log_api_access_error(__METHOD__);
			return false;
		}
		else if (strpos($result, '<error>'))
		{
			$sxml = simplexml_load_string($result);
			
			$code = (string) $sxml->code;
			$msg = 'crowdin api error: adding file:' . $file . ' code:' . $code . ' msg:' . 
					(string) $sxml->message;
			\Log::error($msg, __METHOD__);
			static::unbuffered_echo($msg . "\n");
			
			// Specified directory not found
			if ($code == 17)
			{
				$ret = static::add_directory($file);
				if ($ret)
				{
					$ret = static::add_file($file, $local_path);
					return $ret;
				}
			}
			// File with such name already uploaded
			else if ($code == 5)
			{
				static::update_cache($file, $local_path);
			}
			
			return false;
		}
		else
		{
			static::update_cache($file, $local_path);
			return true;
		}
	}
	
	private static function log_api_access_error($method)
	{
		$msg = 'crowdin api error: Can\'t access to API';
		\Log::error($msg, $method);
		static::unbuffered_echo($msg . "\n");
	}
	
	private static function unbuffered_echo($str)
	{
		echo $str;
		ob_flush();
		flush();
	}
	
	private static function post_to_api($url, $post)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;
	}
	
	private static function update_file($file, $local_path)
	{
		$request_url = static::$api_url .
							'/update-file?key=' . static::$project_key;
		$post_params = array();
		$post_params['files[' . $file. ']'] = '@' . $local_path;
		
		//var_dump($request_url);
		//var_dump($post_params);
		
		static::unbuffered_echo('updating file: ' . $file . "\n");
		
		$result = static::post_to_api($request_url, $post_params);
		//var_dump($result);
		
		if ($result === false)
		{
			static::log_api_access_error(__METHOD__);
			return false;
		}
		else if (strpos($result, '<error>'))
		{
			$sxml = simplexml_load_string($result);
				
			$code = (string) $sxml->code;
			$msg = 'crowdin api error: adding file:' . $file . ' code:' . $code . ' msg:' .
			(string) $sxml->message;
			\Log::error($msg, __METHOD__);
			static::unbuffered_echo($msg . "\n");
			return false;
		}
		else
		{
			static::update_cache($file, $local_path);
			return true;
		}
	}
	
	private static function delete_file($file, $local_path)
	{
		$request_url = static::$api_url .
								'/delete-file?key=' . static::$project_key;
		$post_params = array();
		$post_params['file'] = $file;
		
		//var_dump($request_url);
		//var_dump($post_params);
		
		static::unbuffered_echo('deleting file: ' . $file . "\n");
		
		$result = static::post_to_api($request_url, $post_params);
		//var_dump($result);
		
		if ($result === false)
		{
			static::log_api_access_error(__METHOD__);
			return false;
		}
		else if (strpos($result, '<error>'))
		{
			$sxml = simplexml_load_string($result);
	
			$code = (string) $sxml->code;
			$msg = 'crowdin api error: deleting file:' . $file . ' code:' . $code . ' msg:' .
			(string) $sxml->message;
			\Log::error($msg, __METHOD__);
			static::unbuffered_echo($msg . "\n");
			return false;
		}
		else
		{
			unset(static::$cached_data[$file]);
			return true;
		}
	}
	
	private static function update_cache($file, $local_path)
	{
		$fileinfo = \File::file_info($local_path);
		$md5 = md5(file_get_contents($local_path));
		static::$cached_data[$file] = array(
							'size'  => $fileinfo['size'],
							'mtime' => $fileinfo['time_modified'],
							'md5'   => $md5,
		);
	}
	
	private static function add_directory($file)
	{
		$request_url = static::$api_url .
							'/add-directory?key=' . static::$project_key;
		$post_params = array();
		$post_params['name'] = dirname($file);
		
		//var_dump($request_url);
		//var_dump($post_params);
		
		static::unbuffered_echo('creating directory: ' . $post_params['name'] . "\n");
		
		$result = static::post_to_api($request_url, $post_params);
		//var_dump($result);
	
		if ($result === false)
		{
			static::log_api_access_error(__METHOD__);
			return false;
		}
		else if (strpos($result, '<error>'))
		{
			$sxml = simplexml_load_string($result);
			
			$code = (string) $sxml->code;
			$msg = 'crowdin api error: adding directory:' . $post_params['name'] . ' code:' . $code . ' msg:' .
					(string) $sxml->message;
			\Log::error($msg, __METHOD__);
			static::unbuffered_echo($msg . "\n");
			return false;
		}
		else
		{
			return true;
		}
	}
}

/* End of file tasks/file.php */
