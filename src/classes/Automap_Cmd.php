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

// <PHK:ignore>
require_once(dirname(__FILE__).'/external/phool/PHO_Display.php');
require_once(dirname(__FILE__).'/external/phool/PHO_File.php');
require_once(dirname(__FILE__).'/Automap_Cmd_Options.php');
require_once(dirname(__FILE__).'/Automap_Creator.php');
// <PHK:end>

class Automap_Cmd
{
//---------

private static function error_abort($msg,$usage=true)
{
if ($usage) $msg .= " - Use 'help' command for syntax";
throw new Exception($msg);
}

//---------

private static function usage()
{
echo "
Available commands :

  - register [-a] [-b <base_path>] <relative paths...>
        Scans PHP scripts and builds a map. The relative paths can reference
        regular files and/or directories. Directories are scanned recursively
        and every PHP scripts they contain are scanned.
        Options :
            -a : If the map file exists, add symbols without recreating it
            -b <base_path> : Specifies a base path. If relative, the reference
                             is the map file directory.

  - show [-f {auto|html|text}]
        Displays the content of a map file
        Options :
            -f <format> : Output format. Default is 'auto'.

  - check
        Checks a map file

  - export [-o <path>]
        Exports the symbol table from a map file
        Options :
            -o <path> : path of file to create with exported data. Default is
                        to write to stdout.

    - import [-a] [-i <path>]
        Import symbols from an exported file
        Options :
            -i <path> : path of file where data will be read. Default is to read
                        from stdin.
            -a : If the map file exists, add symbols without recreating it

    - set_option <name> <value>
        Sets an option in an existing map

    - unset_option <name>
        Unsets an option in an existing map

    - help
        Display this message

Global options :

  -v : Increase verbose level (can be set more than once)
  -q : Decrease verbose level (can be set more than once)
  -m <path> : Specifies the path of the map file the command applies to. Default
              is './auto.map'.

More information at http://automap.tekwire.net\n\n";
}

//---------
// Main
// Options can be located before AND after the action keyword.

public static function run($args)
{
Automap_Cmd_Options::get_options($args);
$action=(count($args)) ? array_shift($args) : 'help';
Automap_Cmd_Options::get_options($args);
$opt=Automap_Cmd_Options::options();

switch($action)
	{
	case 'show':
		$map=Automap::instance(Automap::load($opt['map_path'],Automap::NO_AUTOLOAD));
		$map->show($opt['format']);
		break;

	case 'check':
		$map=Automap::instance(Automap::load($opt['map_path'],Automap::NO_AUTOLOAD));
		$c=Automap_Tools::check($map);
		if ($c) throw new Exception("*** The check procedure found $c error(s) in file $mapfile");
		break;

	case 'set_option':
		$mpath=$opt['map_path'];
		if (count($args)!=2) self::error_abort('set_option requires 2 arguments');
		list($name,$value)=$args;
		if (!is_file($mpath)) throw new Exception("$mpath: File not found");
		$map=new Automap_Creator();
		$map->read_map_file($mpath);
		$map->set_option($name,$value);
		$map->save($mpath);
		break;

	case 'unset_option':
		$mpath=$opt['map_path'];
		if (count($args)!=1) self::error_abort('set_option requires 1 argument');
		$name=array_shift($args);
		if (!is_file($mpath)) throw new Exception("$mpath: File not found");
		$map=new Automap_Creator();
		$map->read_map_file($mpath);
		$map->unset_option($name);
		$map->save($mpath);
		break;

	case 'register_extensions':
		//-- Must be executed with :
		//-- php -n -d <Extension_dir> Automap_Builder.php register_extensions
		//-- in order to ignore extension preloading directives in php.ini
		//-- (if an extension is already loaded, we cannot determine which file
		//-- it came from). The '-d' flag is mandatory as long as PHP cannot
		//-- dl() outside of 'extension_dir'.

		$map=new Automap_Creator();
		if (($opt['append']) && is_file($opt['map_path']))
			$map->read_map_file($opt['map_path']);
		$map->register_extension_dir();
		$map->save($opt['map_path']);
		break;

	case 'register':
		$map=new Automap_Creator();
		if (($opt['append']) && is_file($opt['map_path']))
			$map->read_map_file($opt['map_path']);
		$abs_map_dir=PHO_File::mk_absolute_path(dirname($opt['map_path']));
		if (!is_null($opt['base_path']))
			$map->set_option('base_path',$opt['base_path']);
		$abs_base=PHO_File::combine_path($abs_map_dir,$map->option('base_path'));
		foreach($args as $rpath)
			{
			$abs_path=PHO_File::combine_path($abs_base,$rpath);
			$map->register_path($abs_path,$rpath);
			}
		$map->save($opt['map_path']);
		break;

	case 'export':
		$map=Automap::instance(Automap::load($opt['map_path']),Automap::NO_AUTOLOAD);
		Automap_Tools::export($map,$opt['output']);
		break;

	case 'import':
		$map=new Automap_Creator();
		if (($opt['append']) && is_file($opt['map_path']))
			$map->read_map_file($opt['map_path']);
		$map->import($opt['input']);
		$map->save($opt['map_path']);
		break;

	case 'help':
		self::usage();
		break;

	default:
		self::error_abort("Unknown action: '$action'");
	}
}

//============================================================================
} // End of class
?>
