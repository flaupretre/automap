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
* This class manages options for Automap_Cmd
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//============================================================================

// <PHK:ignore>
require_once(dirname(__FILE__).'/external/phool/PHO_Display.php');
require_once(dirname(__FILE__).'/external/phool/PHO_Gotopt.php');
// <PHK:end>

class Automap_Cmd_Options
{

private static $options=array(
	'map_path' => 'auto.map',
	'base_path' => null,
	'append' => false,
	'output' => 'php://stdout',
	'input' => 'php://stdin',
	'format' => 'auto'
	);


//-----------------------

public static function options()
{
return self::$options;
}

//-----------------------

public static function option($opt)
{
if (isset(self::$options[$opt])) return self::$options[$opt];
throw new Exception("$opt: Unknown option");
}

//-----------------------

public static function get_options(&$args)
{
list($options,$args2)=PHO_Getopt::getopt2($args,'vqm:b:ao:i:f:'
	,array('verbose','quiet','map_path=','base_path=','append','output='
		,'input=','format='));

foreach($options as $option)
	{
	list($opt,$arg)=$option;
	switch($opt)
		{
		case 'v':
		case '--verbose':
			PHO_Display::inc_verbose();
			break;

		case 'q':
		case '--quiet':
			PHO_Display::dec_verbose();
			break;

		case 'm':
		case '--map_path':
			self::$options['map_path']=$arg;
			break;

		case 'b':
		case '--base_path':
			self::$options['base_path']=$arg;
			break;

		case 'a':
		case '--append':
			self::$options['append']=true;
			break;

		case 'o':
		case '--output':
			self::$options['output']=$arg;
			break;

		case 'i':
		case '--input':
			self::$options['input']=$arg;
			break;

		case 'f':
		case '--format':
			self::$options['format']=$arg;
			break;
		}
	}
$args=$args2;
}

//---------

//============================================================================
} // End of class
?>
