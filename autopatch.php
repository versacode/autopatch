<?php
/*
Copyright (c) 2020 versacode

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/
/* Autopatch v1.01 */
$config = array(
"API_SERVER" => "https://account.versacode.org/api/", //versacode API server end-point
/*
	"API_KEY" => "", //your API key here
	"PROJECT_ID" => "", //project ID
	"REMOVE_PATCHES" => true, //automatically delete patch files when done
	*/
/* SOFTWARE DETAILS */
/*
	"DIRECTORY_PATH" => "", //Path to target directory where the patches should apply (i.e /var/www/html)
	"PATCH_NUMBER" => "all", //Patches to apply: can be sequence number, "all" or "lN" short for 'last N patches' where N is a number indicating the amount of latest patches to apply
	*/

/* "APPLY_SQL" => true, //preference to automatically apply SQL changes */
/* "AGREE_DISCLAIMER" => true, //agree to MySQL imports disclaimer automatically: useful when running unattended */

/* MYSQL DETAILS (only when APPLY_SQL is true): */
/*
	"MYSQL_HOST" => "",
	"MYSQL_PORT" => "",
	"MYSQL_USER" => "",
	"MYSQL_DATABASE" => "",
	"MYSQL_PASSWORD" => "",
	*/
);
function ParseConfig($arg)
{
	if (strpos($arg, "=") !== false)
	{
		$data = explode("=", $arg);
		SetConfig(strtoupper($data[0]), $data[1]);
	}
}
for ($i = 1; $i < count($argv); $i++)
{
	ParseConfig($argv[$i]);
}
class Helper {
	private $foreground = array();
	private $background = array();
	private $prefix = array();
	public function __construct()
	{
		$this->foreground['black'] = '0;30';
		$this->foreground['blue'] = '0;34';
		$this->foreground['green'] = '0;32';
		$this->foreground['red'] = '0;31';
		$this->foreground['white'] = '1;37';

		$this->background['red'] = '41';
		$this->background['green'] = '42';
		$this->background['yellow'] = '43';

		$this->prefix = array("red" => "[ERROR] ", "yellow" => "[WARNING] ", "green" => "[OK] ", "blue" => "[INFO] ");
	}
	public function status($string, $color = null, $highlight = null)
	{
		$output = "";
		if (isset($this->foreground[$color]))
		{
			$output .= "\033[" . $this->foreground[$color] . "m";
		}
		if (isset($this->background[$highlight]))
		{
			$output .= "\033[" . $this->background[$highlight] . "m";
		}
		if (isset($this->prefix[$highlight]))
			$string = $this->prefix[$highlight] . $string;
		else if (isset($this->prefix[$color]))
			$string = $this->prefix[$color] . $string;

		$output .=  $string . "\033[0m";
		echo $output."\r\n";
	}
}
$helper = new Helper();
function _command_exists($command)
{
	$which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? "where" : "which";
	return is_executable(trim(exec($which. " ". escapeshellarg($command), $output, $exit_code)));
}
function status($string, $color = null, $highlight = null)
{
	global $helper;
	$helper->status($string, $color, $highlight);
}
function _is_curl_installed()
{
	if  (in_array ('curl', get_loaded_extensions())) {
		return true;
	}
	else {
		status("cURL extension is not installed.", "black", "yellow");
		return false;
	}
}
function _is_allow_url_fopen()
{
	if(ini_get('allow_url_fopen'))
	{
		return true;
	}
	else
	{
		status("allow_url_fopen is false.", "black", "yellow");
		return false;
	}
}
function curl($url)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	$output = curl_exec ($ch);

	if(curl_exec($ch) === false)
		status("cURL: " . curl_error($ch), "red");

	curl_close($ch);
	return $output;
}
function SetConfig($variable, $value)
{
	global $config;
	if ($value == "")
		unset($config[$variable]);
	else
		$config[$variable] = $value;
}
function GetConfig($variable, $desc = false)
{
	global $config;
	if (!isset($config[$variable]))
		SetConfig($variable, Prompt("Enter " . ($desc? $desc : $variable)));
	return $config[$variable];
}
function SendRequest($endpoint, $module)
{
	$url = GetConfig("API_SERVER", "API Server URL").GetConfig("API_KEY", "API User Key")."/".$endpoint;
	$output = "";

	if($module === 1)
		$output = curl($url);
	else if ($module === 2)
		$output = file_get_contents($url);
	else
		status("Unexpected input.", "red");

	return $output;
}
function GetAPIResponse($result)
{
	$result = $result? json_decode($result, true) : false;
	if (!$result) status("Received invalid response.", "black", "yellow");
	return $result;
}
function GetHistory($module)
{
	status("Retrieving available patches...", "blue");
	return GetAPIResponse(SendRequest("history/".GetConfig("PROJECT_ID", "Project ID"), $module));
}
function ExecuteCmd($cmd)
{
	if (!isset($GLOBALS['VALID_CMDS'])) $GLOBALS['VALID_CMDS'] = array("cd" => true);
	if (strpos($cmd, " ") !== false)
	{
		$executable = trim(explode(" ", $cmd)[0]);
		if (!isset($GLOBALS['VALID_CMDS'][$executable])) 
		{
			if(_command_exists($executable))
				$GLOBALS['VALID_CMDS'][$executable] = true;
			else
				status("Could not find " . escapeshellarg($executable). ". The script may not function correctly.", "black", "yellow");
		}
	}
	exec($cmd, $output, $exit_code);
	return $exit_code;
}
function GetPatch($sha, $module, $directory = false)
{
	if (!$directory)
		$directory = sys_get_temp_dir();

	status("Downloading patch [$sha]...", "blue");
	$patch = SendRequest("patch/".GetConfig("PROJECT_ID", "Project ID")."/".$sha, $module);
	$tmp = false;
	if ($patch)
	{
		$tmp = tempnam($directory, "VC") . ".patch";
		if (file_put_contents($tmp,$patch))
			status("Patch downloaded to " . $tmp, "green");
		else
			status("Failed to download patch to " . $tmp. ". Ensure you have write permissions and try again.", "red");
	}
	return $tmp;
}
function GetFiles($directory)
{
	return scandir($directory);
}
function MonitorDirectory($directory)
{
	$output = false;
	if (!isset($GLOBALS['_MONITOR']))
		$GLOBALS['_MONITOR'] = array();
	$files = is_dir($directory)? GetFiles($directory) : false;
	if ($files)
	{
		if (isset($GLOBALS['_MONITOR'][$directory]))
			$output = array_diff($files, $GLOBALS['_MONITOR'][$directory]);
		$GLOBALS['_MONITOR'][$directory] = $files;
	}
	return $output;
}
function ShowHistory($histories)
{
	if ($histories)
	{
		$i = 0;
		status("List of available patches: \r\n", "green");
		foreach ($histories as $history)
		{
			$i++;
			echo $i.". " . $history['desc'] . " [".$history['date']."]"."\r\n";
		}
		echo "\r\n";
	}
	else
	{
		status("Unexpected input.", "red");
	}
}
function ProcessSQLFiles($db_files, $db_directory)
{
	foreach ($db_files as $key => $file)
	{
		if (strtolower(substr($file, -4)) == ".sql" && GetConfig("APPLY_SQL", "preference to automatically apply SQL files (saved inside /database/) [Y/*]"))
		{
			status("DISCLAIMER: You have chosen to automatically allow SQL imports. Note that this script does not take backups and cannot revert your database to the state it was prior to applying the patch. It is recommended to take a backup now if you have not yet. Type 'YES' if you are sure you would still like to proceed.", "black", "yellow");
			if (strtolower(GetConfig("AGREE_DISCLAIMER", "'YES' to agree to the disclaimer, type anything else to skip future SQL imports")) == "yes")
			{
				if (file_exists($db_directory.$file))
				{
					if (ExecuteCmd("mysql --host=".escapeshellarg(GetConfig("MYSQL_HOST", "MySQL Host"))." --port=".escapeshellarg(GetConfig("MYSQL_PORT", "MySQL PORT"))." -u ".escapeshellarg(GetConfig("MYSQL_USER", "MySQL User"))." ".escapeshellarg(GetConfig("MYSQL_DATABASE", "MySQL Database"))." -p".escapeshellarg(GetConfig("MYSQL_PASSWORD", "MySQL Password"))." < " . $db_directory.$file) === 0)
						status("SQL imported.", null, "green");
					else
						status("SQL import failed: " . $db_directory. $file, null, "red");
				}
				else
					status("UNEXPECTED: SQL file no longer exists. Skipping..", null, "red");
			}
		}
	}
}
function ProcessPatchList($histories, $directory, $module)
{
	foreach ($histories as $history)
	{
		$patch = GetPatch($history['sha'], $module);
		if (ExecuteCmd("cd  ". escapeshellarg($directory) . " && git apply --whitespace=fix --check " . escapeshellarg($patch)) !== 0)
			status(escapeshellarg($patch). " Patch does not apply: the source code files might be different.", null, "red");
		else
		{
			$db_directory = rtrim($directory, '/') . "/database/";
			if (!is_dir($db_directory))
				mkdir($db_directory);
			else if (is_file($db_directory)) { status("FATAL: Stopping because ".$db_directory. " is a file. Please remove the file and re-run this script.", null, "red"); exit(1); }
			$db_files = MonitorDirectory($db_directory);
			if (ExecuteCmd("cd  ". escapeshellarg($directory) . " && git apply --whitespace=fix " . escapeshellarg($patch)) === 0)
				status(escapeshellarg($patch). " Patch applied.", null, "green");
			$db_files = MonitorDirectory($db_directory);
			ProcessSQLFiles($db_files, $db_directory);
		}
		if (GetConfig("REMOVE_PATCHES", "preference to automatically delete patch files when done"))
			unlink($patch);
	}
}
function Prompt($message)
{
	$output = false;
	while ($output === false) $output = readline($message . ": ");
	return $output;
}
/* MAIN LOGIC */
$module = _is_curl_installed()? 1 : (_is_allow_url_fopen()? 2 : false); //Check for existence of cURL or ability to use remote file_get_contents
if (!$module)
{
	status("FATAL: Neither cURL nor allow_url_fopen are enabled. Please install the cURL extension by executing `apt-get install php-curl` on Debian-based systems or enable `allow_url_fopen` in your PHP configuration file (usually at /etc/php.ini or /etc/php/{VERSION}/cli/php.ini) and try again.", "red");
	exit(1); //Exit if cannot use cURL or remote file_get_contents
}
$histories = GetHistory($module); //Use available method to fetch patches (cURL preferred)
if ($histories) //If there are available patches
{
	$histories = array_reverse($histories); //versacode returns the patches according to date in descending order, we will sort them ascendingly
	ShowHistory($histories); //Display available patches to user
askOption:
	$option = strtolower(GetConfig("PATCH_NUMBER", "a patch number to apply; type sequence from patches above, type \"lN\" to apply last N patches, or type \"all\" to apply all in order (recommended)")); //Ask user which patch to apply
	if (!(is_numeric($option) && (int)$option <= count($histories) && (int)$option > 0 ) && $option != "all" && !preg_match("/l\d+/", $option)) //Ensure valid input
	{
		SetConfig("PATCH_NUMBER", "");
		goto askOption;
	}
	/* Get directory where the patches apply */
	$directory = GetConfig("DIRECTORY_PATH", "path to target directory");
	while (!is_dir($directory)) //Insist on a valid directory path
	{
		SetConfig("DIRECTORY_PATH", "");
		$directory = GetConfig("DIRECTORY_PATH", "path to target directory");
	}
	/* Prepare list of patches to apply based on input */
	if (preg_match("/l(\d+)/", $option, $option_args)) //If input was in the form of l(Numbers)..
	{
		$histories = array_splice($histories, -$option_args[1]); //Get last N patches
	}
	else if ($option != "all") //In case it is not "all"; $option is always in lower case so conversion to lower case is not necessary
	{
		$option -= 1; //Option is used as an index in the $histories array; subtract 1 since it starts from zero
		$histories = array($histories[$option]); //Get patch at $option
	}
	ProcessPatchList($histories, $directory, $module);
}
else
{
	status("No patches available. Ensure correct API credentials or try again later.", "red");
}
?>