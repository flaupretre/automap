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

//-------------

class Automap_Cmd_Options extends PHO_Options
{

// Short/long modifier args

protected $opt_modifiers=array(
	array('short' => 'v', 'long' => 'verbose', 'value' => false),
	array('short' => 'q', 'long' => 'quiet'  , 'value' => false),
	array('short' => 'm', 'long' => 'map_path'  , 'value' => true),
	array('short' => 'b', 'long' => 'base_path'  , 'value' => true),
	array('short' => 'a', 'long' => 'append'  , 'value' => false),
	array('short' => 'o', 'long' => 'output'  , 'value' => true),
	array('short' => 'i', 'long' => 'input'  , 'value' => true),
	array('short' => 'f', 'long' => 'format'  , 'value' => true)
	);

// Option values

protected $options=array(
	'map_path' => 'auto.map',
	'base_path' => null,
	'append' => false,
	'output' => 'php://stdout',
	'input' => 'php://stdin',
	'format' => 'auto'
	);



//-----------------------

protected function process_option($opt,$arg)
{
switch($opt)
	{
	case 'v':
		PHO_Display::inc_verbose();
		break;

	case 'q':
		PHO_Display::dec_verbose();
		break;

	case 'm':
		$this->options['map_path']=$arg;
		break;

	case 'b':
		$this->options['base_path']=$arg;
		break;

	case 'a':
		$this->options['append']=true;
		break;

	case 'o':
		$this->options['output']=$arg;
		break;

	case 'i':
		$this->options['input']=$arg;
		break;

	case 'f':
		$this->options['format']=$arg;
		break;
	}
}

//---------

//============================================================================
} // End of class
?>
