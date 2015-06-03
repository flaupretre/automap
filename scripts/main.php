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

namespace Automap {

// main.php includes every class scripts. This way, it can be used out of phk

// <PHK:ignore>
require(dirname(__FILE__).'/../external/phool/src/Phool/Display.php');
require(dirname(__FILE__).'/../external/phool/src/Phool/File.php');
require(dirname(__FILE__).'/../external/phool/src/Phool/Options/Getopt.php');
require(dirname(__FILE__).'/../external/phool/src/Phool/Options/Base.php');
require(dirname(__FILE__).'/../external/phool/src/Phool/Util.php');
require(dirname(__FILE__).'/../external/phool/src/Phool/Debug/Counter.php');
require(dirname(__FILE__).'/../src/Automap/Mgr.php');
require(dirname(__FILE__).'/../src/Automap/Map.php');
require(dirname(__FILE__).'/../src/Automap/CLI/Cmd.php');
require(dirname(__FILE__).'/../src/Automap/CLI/Options.php');
require(dirname(__FILE__).'/../src/Automap/Build/Creator.php');
require(dirname(__FILE__).'/../src/Automap/Build/ParserInterface.php');
require(dirname(__FILE__).'/../src/Automap/Build/Parser.php');
require(dirname(__FILE__).'/../src/Automap/Tools/Display.php');
require(dirname(__FILE__).'/../src/Automap/Tools/Check.php');
// <PHK:end>

try
{
ini_set('display_errors',true);

$args=$_SERVER['argv'];
array_shift($args);
CLI\Cmd::run($args);
}
catch(\Exception $e)
	{
	if (getenv('SHOW_EXCEPTION')!==false) throw $e;
	else echo "*** ERROR: ".$e->getMessage()."\n\n";
	exit(1);
	}

} // End of namespace
//===========================================================================
