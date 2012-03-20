<?php
//=============================================================================
//
// Copyright Francois Laupretre <automap@tekwire.net>
//
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//
//       http://www.apache.org/licenses/LICENSE-2.0
//
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.
//
//=============================================================================
/**
* The main script to build and manage automaps. This script is a wrapper around
* the Automap_Creator class.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//============================================================================

// <PLAIN_FILE> //---------------
require_once(dirname(__FILE__).'/../classes/Automap_Creator.php');
// </PLAIN_FILE> //---------------

//---------
// <Automap>:ignore function send_error

function send_error($msg,$usage=true)
{
if ($usage) usage($msg);
else echo "** ERROR: $msg\n";
exit(1);
}

//---------
// <Automap>:ignore function usage

function usage($msg=null)
{
if (!is_null($msg)) echo "** ERROR: $msg\n";

echo "\nUsage: <action> <params...>\n";
echo "\nActions :\n\n";
echo "	- showmap <map file>\n";
echo "	- register_extensions <map file> (must be executed with 'php -n -d extension_dir=<dir>'\n";
echo "	- register_scripts <map file> <base dir> <relative file paths...>\n";
echo "	- export <map file> [output_file]\n";
echo "	- import <map file> [source_file]\n";
echo "	- help\n\n";

exit(is_null($msg) ? 0 : 1);
}

//---------
// Main

ini_set('display_errors',true);

try
{
array_shift($_SERVER['argv']);
$action=(count($_SERVER['argv'])) ? array_shift($_SERVER['argv']) : 'help';
$mapfile=(array_key_exists(0,$_SERVER['argv'])) ? $_SERVER['argv'][0] : null;

switch($action)
	{
	case 'showmap': //-- display <map file>
		if (is_null($mapfile)) send_error(null);
		$mnt=Automap::mount($mapfile);
		Automap::instance($mnt)->show();
		break;

	case 'register_extensions':
		//-- Must be executed with :
		//-- php -n -d <Extension_dir> Automap_Builder.php register_extensions
		//-- in order to ignore extension preloading directives in php.ini
		//-- (if an extension is already loaded, we cannot determine which file
		//-- it came from). The '-d' flag is mandatory as long as PHP cannot
		//-- dl() outside of 'extension_dir'.

		if (is_null($mapfile)) send_error(null);
		$mf=new Automap_Creator();
		$mf->register_extension_dir();
		$mf->dump($mapfile);
		break;

	case 'register_scripts':
		//-- register_scripts <map file> <$base> <script files (relative paths)>

		if (is_null($mapfile)) send_error(null);
		array_shift($_SERVER['argv']);
		$base=$_SERVER['argv'][0];
		$mf=new Automap_Creator();
		if (file_exists($mapfile)) $mf->get_mapfile($mapfile);

		array_shift($_SERVER['argv']);
		foreach($_SERVER['argv'] as $rfile)
			{
			$abs_path=$base.DIRECTORY_SEPARATOR.$rfile;
			$a=glob($abs_path);
			if (count($a)==0) throw new Exception($abs_path.': No such file');
			foreach($a as $afile) $mf->register_script($afile,$rfile);
			}
		$mf->dump($mapfile);
		break;

	case 'export': //-- export [<map file>]
		if (is_null($mapfile)) send_error(null);
		$output=isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : null;
		Automap::instance(Automap::mount($mapfile))->export($output);
		break;

	case 'import': //-- import <map file>
		if (is_null($mapfile)) send_error(null);
		$mf=new Automap_Creator();
		array_shift($_SERVER['argv']);
		foreach($_SERVER['argv'] as $rfile) $mf->import($rfile);
		$mf->dump($mapfile);
		break;

	case 'help':
		usage();
		break;

	default:
		send_error("Unknown action: '$action'");
	}
}
catch(Exception $e)
	{
	if (getenv('AUTOMAP_DEBUG')!==false) throw $e;
	else send_error($e->getMessage(),false);
	}

//============================================================================
?>
