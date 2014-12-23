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

// main.php includes every class scripts

// <PHK:ignore>
require(dirname(__FILE__).'/../classes/external/phool/PHO_Display.php');
require(dirname(__FILE__).'/../classes/external/phool/PHO_File.php');
require(dirname(__FILE__).'/../classes/external/phool/PHO_Getopt.php');
require(dirname(__FILE__).'/../classes/external/phool/PHO_Options.php');
require(dirname(__FILE__).'/../classes/external/phool/PHO_Util.php');
require(dirname(__FILE__).'/../classes/Automap.php');
require(dirname(__FILE__).'/../classes/Automap_Cmd.php');
require(dirname(__FILE__).'/../classes/Automap_Cmd_Options.php');
require(dirname(__FILE__).'/../classes/Automap_Creator.php');
require(dirname(__FILE__).'/../classes/Automap_Display.php');
require(dirname(__FILE__).'/../classes/Automap_Tools.php');
// <PHK:end>

try
{
ini_set('display_errors',true);

$args=$_SERVER['argv'];
array_shift($args);
Automap_Cmd::run($args);
}
catch(Exception $e)
	{
	if (getenv('AUTOMAP_DEBUG')!==false) throw $e;
	else echo "*** ERROR: ".$e->getMessage()."\n\n";
	exit(1);
	}
