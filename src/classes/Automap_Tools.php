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
* This class contains auxiliary runtime features, not included in the
* PECL extension.
* This file is not included in the PHK PHP runtime.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

//------------------------------------------
/**
* This class is mainly used for features not to be included
* in the PECL extension and in the PHK PHP runtime. It may call PHK methods.
*
* @package Automap
*/

class Automap_Tools // Static only
{

//---
// Returns the number of errors found

public static function check($id)
{
$checked_targets=array();

$map=Automap::map($id);
$base_path=$map->base_path();

$c=0;
foreach($map->symbols() as $s)
	{
	try
		{
		$path=\Phool\File::combine_path($base_path,$s['rpath']);
		$ptype=$s['ptype'];
		$key=$ptype.$path;
		if (isset($checked_targets[$key])) continue;
		$checked_targets[$key]=true;
		switch($ptype)
			{
			case Automap::F_EXTENSION:
				// Do nothing
				break;

			case Automap::F_SCRIPT:
				\Phool\Display::trace('Checking script at '.$s['rpath']);
				if (!is_file($path)) throw new Exception($path.': File not found');
				if (PHK::file_is_package($path))
					throw new Exception($path.': File is a PHK package (should be a script)');
				break;

			case Automap::F_PACKAGE:
				\Phool\Display::trace('Checking package at '.$s['rpath']);
				if (!is_file($path)) throw new Exception($path.': File not found');
				if (!PHK::file_is_package($path))
					throw new Exception($path.': File is not a PHK package');

				// Suppress notice msg on multiple HALT_COMPILER definitions
				error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
				$id=PHK_Mgr::mount($path,PHK::F_NO_MOUNT_SCRIPT);
				error_reporting($errlevel);
				self::check(Automap::instance($id));
				break;

			default:
				throw new Exception("<$ptype>: Unknown target type");
			}
		}
	catch (Exception $e)
		{
		echo 'Error ('.Automap::type_to_string($s['stype']).' '.$s['symbol']
			.'): '.$e->getMessage()."\n";
		$c++;
		}
	}
return $c;
}

//---

public static function export(Automap_Map $map,$path=null)
{
if (is_null($path)) $path="php://stdout";
$fp=fopen($path,'w');
if (!$fp) throw new Exception("$path: Cannot open for writing");

foreach($map->symbols() as $s)
	{
	fwrite($fp,$s['stype'].'|'.$s['symbol'].'|'.$s['ptype'].'|'.$s['rpath']."\n");
	}

fclose($fp);
}

//---
} // End of class Automap_Tools
//===========================================================================
?>
