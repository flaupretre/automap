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
* This class manages command line options. It is a wrapper above PHO_Getopt
*
* @copyright Francois Laupretre <phool@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category phool
* @package phool
*/
//============================================================================

// <PHK:ignore>
require_once(dirname(__FILE__).'/PHO_Getopt.php');
// <PHK:end>

abstract class PHO_Options
{
// These properties must be declared in the child class (see PHO_Dummy_Options)

// protected $opt_modifiers; /* Modifier args */
// protected $options;	/* Option values */

abstract protected function process_option($opt,$arg);

//-----------------------

public function options()
{
return $this->options;
}

//-----------------------

public function option($opt)
{
if (!array_key_exists($opt,$this->options))
	throw new Exception("$opt: Unknown option");
return $this->options[$opt];
}

//-----------------------

public function set($opt,$value)
{
if (!array_key_exists($opt,$this->options))
	throw new Exception("$opt: Unknown option");
$this->options=$value;
}

//-----------------------

public function parse(&$args)
{
$short_opts='';
$long_opts=array();
foreach($this->opt_modifiers as $mod)
	{
	$short_opts .= $mod['short'];
	$long=$mod['long'];
	if ($mod['value'])
		{
		$short_opts .= ':';
		$long .= '=';
		}
	$long_opts[]=$long;
	}

list($opts,$args2)=PHO_Getopt::getopt2($args,$short_opts,$long_opts);
foreach($opts as $opt_val)
	{
	list($opt,$arg)=$opt_val;
	if (strlen($opt)>2) // Convert long option to short
		{
		$opt=substr($opt,2);
		foreach($this->opt_modifiers as $mod)
			{
			if ($mod['long']==$opt)
				{
				$opt=$mod['short'];
				break;
				}
			}
		}
	$this->process_option($opt,$arg);
	}

$args=$args2;
}

//-----------------------

public function parse_all(&$args)
{
$res=array();

while(true)
	{
	$this->parse($args);
	if (!count($args)) break;
	$res[]=array_shift($args);
	}
$args=$res;
}

//---------

//============================================================================
} // End of class
?>
