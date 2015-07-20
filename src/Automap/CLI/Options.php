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
* This class manages command line options for Automap\CLI\Cmd
*
* API status: Private
* Included in the PHK PHP runtime: No
* Implemented in the extension: No
*///==========================================================================

namespace Automap\CLI {

if (!class_exists('Automap\CLI\Options',false)) {

class Options extends \Phool\Options\Base
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

protected function processOption($opt,$arg)
{
	switch($opt) {
		case 'v':
			\Phool\Display::incVerbose();
			break;

		case 'q':
			\Phool\Display::decVerbose();
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

//---
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
