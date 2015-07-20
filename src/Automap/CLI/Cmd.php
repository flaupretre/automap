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
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*///==========================================================================

//=============================================================================
/**
* The main script of the CLI Automap manager tool.
*
* API status: Private
* Included in the PHK PHP runtime: No
* Implemented in the extension: No
*///==========================================================================

namespace Automap\CLI {

if (!class_exists('Automap\CLI\Cmd',false)) {

class Cmd
{
//---------

private static function errorAbort($msg,$usage=true)
{
if ($usage) $msg .= " - Use 'help' command for syntax";
throw new \Exception($msg);
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
            -a : Append. If the map file exists, add symbols without recreating it
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

  - setOption <name> <value>
        Sets an option in an existing map

  - unsetOption <name>
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
	$op=new Options;
	$op->parseAll($args);
	$action=(count($args)) ? array_shift($args) : 'help';

	switch($action) {
		case 'show':
			$map=new \Automap\Map($op->option('map_path'));
			$map->show($op->option('format'));
			break;

		case 'check':
			$id=\Automap\Mgr::load($op->option('map_path'),\Automap\Mgr::CRC_CHECK);
			$errs=\Automap\Tools\Check::check($id);
			if (count($errs)) {
				foreach($errs as $err) \Phool\Display::error($err);
				throw new \Exception("*** The check procedure found errors in file $mapfile");
			}
			\Phool\Display::info('Check OK');
			break;

		case 'setOption':
			if (count($args)!=2) self::errorAbort('setOption requires 2 arguments');
			list($name,$value)=$args;
			$map=new \Automap\Build\Creator();
			$map->readMapFile($op->option('map_path'));
			$map->setOption($name,$value);
			$map->save($op->option('map_path'));
			break;

		case 'unsetOption':
			if (count($args)!=1) self::errorAbort('unsetOption requires 1 argument');
			$name=array_shift($args);
			$map=new \Automap\Build\Creator();
			$map->readMapFile($op->option('map_path'));
			$map->unsetOption($name);
			$map->save($op->option('map_path'));
			break;

		case 'register_extensions':
			//-- Must be executed with :
			//-- php -n -d <Extension_dir> automap.phk register_extensions
			//-- in order to ignore extension preloading directives in php.ini
			//-- (if an extension is already loaded, we cannot determine which file
			//-- it came from). The '-d' flag is mandatory as long as PHP cannot
			//-- dl() outside of 'extension_dir'.

			$map=new \Automap\Build\Creator();
			if (($op->option('append')) && is_file($op->option('map_path')))
				$map->readMapFile($op->option('map_path'));
			$map->registerExtensionDir();
			$map->save($op->option('map_path'));
			break;

		case 'register':
			$map=new \Automap\Build\Creator();
			if (($op->option('append')) && is_file($op->option('map_path')))
				$map->readMapFile($op->option('map_path'));
			$abs_map_dir=\Phool\File::mkAbsolutePath(dirname($op->option('map_path')));
			if (!is_null($op->option('base_path')))
				$map->setOption('base_path',$op->option('base_path'));
			$abs_base=\Phool\File::combinePath($abs_map_dir,$map->option('base_path'));
			foreach($args as $rpath) {
				$abs_path=\Phool\File::combinePath($abs_base,$rpath);
				$map->registerPath($abs_path,$rpath);
			}
			$map->save($op->option('map_path'));
			break;

		case 'export':
			$map=new \Automap\Map($op->option('map_path'));
			$map->export($op->option('output'));
			break;

		case 'import':
			$map=new \Automap\Build\Creator();
			if (($op->option('append')) && is_file($op->option('map_path')))
				$map->readMapFile($op->option('map_path'));
			$map->import($op->option('input'));
			$map->save($op->option('map_path'));
			break;

		case 'help':
			self::usage();
			break;

		default:
			self::errorAbort("Unknown action: '$action'");
		}
}

//---
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
