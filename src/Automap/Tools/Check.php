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
* PECL extension nor in the PHK runtime. It may call PHK methods.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

namespace Automap\Tools {

if (!class_exists('Automap\Tools\Check',false)) 
{
class Check // Static only
{

//---
// Returns an array of error messages

public static function check($map)
{
$checked_targets=array();
$errors=array();
foreach($map->symbols() as $s)
	{
	try
		{
		$path=$s['path'];
		$ptype=$s['ptype'];
		$key=$ptype.$path;
		if (isset($checked_targets[$key])) continue;
		$checked_targets[$key]=true;
		switch($ptype)
			{
			case \Automap\Mgr::F_EXTENSION:
				// Do nothing
				break;

			case \Automap\Mgr::F_SCRIPT:
				\Phool\Display::trace('Checking script at '.$path);
				if (!is_file($path)) throw new \Exception($path.': File not found');
				if (\PHK::file_is_package($path))
					throw new \Exception($path.': File is a PHK package (should be a script)');
				break;

			case \Automap\Mgr::F_PACKAGE:
				\Phool\Display::trace('Checking package at '.$path);
				if (!is_file($path)) throw new \Exception($path.': File not found');
				if (!\PHK::file_is_package($path))
					throw new \Exception($path.': File is not a PHK package');

				// Suppress notice msg on multiple HALT_COMPILER definitions
				error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
				$phk_id=\PHK\Mgr::mount($path,\PHK::F_NO_MOUNT_SCRIPT);
				error_reporting($errlevel);
				$pkg=\PHK\Mgr::instance($phk_id);
				self::check(\Automap\Mgr::map($pkg->automap_id()));
				break;

			default:
				throw new \Exception("<$ptype>: Unknown target type");
			}
		}
	catch (\Exception $e)
		{
		$errors[]=\Automap\Mgr::type_to_string($s['stype']).' '.$s['symbol']
			.': '.$e->getMessage();
		}
	}
return $errors;
}

//---
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
