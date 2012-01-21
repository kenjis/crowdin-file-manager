<?php
/**
* Crowdin File Manager
*
* @author     Kenji Suzuki https://github.com/kenjis
* @copyright  2012 Kenji Suzuki
* @license    MIT License http://www.opensource.org/licenses/mit-license.php
*/

return array(
	/**
	 * Directory in where files to add to Crowdin Project are
	 */
	'docs_dir' => '/path/to/docs/',
	
	/**
	 * File Type
	 *   'ext' is file extenstion
	 *   'type' is file type of Crowdin API
	 *     See http://crowdin.net/page/api/add-file
	 */
	'file_type' =>
		array(
			'ext'  => 'html',
			'type' => 'html',
		),
	
	/**
	 * Your Project Info on Crowdin
	 *   See Crowdin project settings page, API tab
	 */
	'crowdin' => 
		array(
			'project-identifier' => 'your Project Identifier',
			'project-key'        => 'your API Key',
		),
);
