# Crowdin File Manager

This is CLI application to manage "Crowdin.net" project files using Crowdin Web API.
You can add and update files to translate to your Crowdin project.

Crowdin is a collaborative translation tool.

## Requirements

* PHP 5.3 or later (curl is required)

## How to Use

1. Configure "fuel/app/config/crowdin.php"

2. Check File Status
	$ oil r file:check
	
3. Add/Upload Files to Crowdin
	$ oil r file:update
	
	Note: Crowdin API response is sometimes very slow. 
		So 10 files are processed at one time.

## Reference

- Crowdin http://crowdin.net/
- Crowdin API Documentation http://crowdin.net/page/api/
