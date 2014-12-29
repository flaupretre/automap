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
* The map instance
*
* When the PECL extension is not present, this class is instantiated when the
* map is loaded, and it is used by the autoloader.
*
* When the extension is present, this class is instantiated only after a call
* to Automap::map() and is not used by the autoloader.
*
* This file is included in the PHK PHP runtime.
*
* The whole file is <slow path>
* 
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

if (!class_exists('Automap_Map',false)) 
{
//===========================================================================
/**
* Automap runtime class
*
* This class allows to autoload PHP scripts and extensions by extension,
* constant, class, or function name.
*
* Static methods use map IDs. A map ID is a non null positive number, uniquely
* identifying a loaded map.
*
* Used for plain maps and package-wrapped maps. So, this class must support
* plain script files and packages.
*
* @package Automap
*/
//===========================================================================

class Automap_Map
{
/** The path of the map file (as given at creation time) */

private $path;			

/** @var array($key => array('T' => <symbol type>
	,'n' => <case-sensitive symbol name>
	,'t' => <target type>
	,'p' => <target relative path>)		The symbol table */

private $symbols=null;

/** @var array()	The map options */

private $options=null;	// array()

/** @var string The version of Automap_Creator that created the map file */

private $version;

/** The minimum runtime version needed to understand the map file */

private $min_version;

/** Load flags */

private $flags;

//-----

public function __construct($path,$flags=0)
{
$this->path=$path;
$this->flags=$flags;

try
{
//-- Get file content

if (($buf=@file_get_contents($this->path))===false)
	throw new Exception('Cannot read map file');
$bufsize=strlen($buf);
if ($bufsize<62) throw new Exception("Short file (size=$bufsize)");

//-- Check magic

if (substr($buf,0,14)!=Automap::MAGIC) throw new Exception('Bad Magic');

//-- Check min runtime version required by map

$this->min_version=trim(substr($buf,16,12));	
if (version_compare($this->min_version,Automap::VERSION) > 0)
	throw new Exception($this->path.': Cannot understand this map.'.
		' Requires at least Automap version '.$this->min_version);

//-- Check if the map format is not too old

$this->version=trim(substr($buf,30,12));
if (strlen($this->version)==0)
	throw new Exception('Invalid empty map version');
if (version_compare($this->version,Automap::MIN_MAP_VERSION) < 0)
	throw new Exception('Cannot understand this map. Format too old.');
$map_major_version=$this->version{0};

//-- Check file size

if (strlen($buf)!=($sz=(int)substr($buf,45,8)))
	throw new Exception('Invalid file size. Should be '.$sz);

//-- Check CRC

if (!($flags & Automap::NO_CRC_CHECK))
	{
	$crc=substr($buf,53,8);
	$buf=substr_replace($buf,'00000000',53,8);
	if ($crc!==hash('crc32',$buf)) throw new Exception('CRC error');
	}

//-- Read data
	
if (($buf=unserialize(substr($buf,61)))===false)
	throw new Exception('Cannot unserialize data from map file');
if (!is_array($buf))
	throw new Exception('Map file should contain an array');
if (!array_key_exists('options',$buf)) throw new Exception('No options array');
if (!is_array($this->options=$buf['options']))
	throw new Exception('Options should be an array');
if (!array_key_exists('map',$buf)) throw new Exception('No symbol table');
if (!is_array($bsymbols=$buf['map']))
	throw new Exception('Symbol table should contain an array');

//-- Process symbols

$this->symbols=array();
foreach($bsymbols as $bval)
	{
	$a=array();
	switch($map_major_version)
		{
		case '3':
			if (strlen($bval)<5) throw new Exception("Invalid value string: <$bval>");
			$a['T']=$bval{0};
			$a['t']=$bval{1};
			$ta=explode('|',substr($bval,2));
			if (count($ta)<2) throw new Exception("Invalid value string: <$bval>");
			$a['n']=$ta[0];
			$a['p']=$ta[1];
			break;

		default:
			throw new Exception("Cannot understand this map version ($map_major_version)");
		}
	$key=Automap::key($a['T'],$a['n']);
	$this->symbols[$key]=$a;
	}
}
catch (Exception $e)
	{
	$this->symbols=array(); // No retry later
	throw new Exception($path.': Cannot load map - '.$e->getMessage());
	}
}

//---
// These utility functions return 'read-only' properties

public function path() { return $this->path; }
public function flags() { return $this->flags; }
public function options() { return $this->options; }
public function version() { return $this->version; }
public function min_version() { return $this->min_version; }

//---

public function option($opt)
{
return (isset($this->options[$opt]) ? $this->options[$opt] : null);
}

//---

public function symbol_count()
{
return count($this->symbols);
}

//---

private function export_entry($entry)
{
return array(
	'stype'		=> $entry['T'],
	'symbol' 	=> $entry['n'],
	'ptype'		=> $entry['t'],
	'rpath'		=> $entry['p'],
	);
}

//---

public function get_symbol($type,$symbol)
{
$key=Automap::key($type,$symbol);
if (!isset($this->symbols[$key])) return false;
return $this->export_entry($this->symbols[$key]);
}

//---
// Return an array without keys (key format is private and may change)

public function symbols()
{
$ret=array();
foreach($this->symbols as $entry) $ret[]=$this->export_entry($entry);

return $ret;
}

//---
// Proxy to Automap_Display::show()

public function show($format=null,$subfile_to_url_function=null)
{
return Automap_Display::show($this,$format,$subfile_to_url_function);
}

//---
} // End of class Automap_Map
//===========================================================================
} // End of class_exists('Automap_Map')
//===========================================================================
?>
