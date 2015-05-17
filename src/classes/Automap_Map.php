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
* A map instance
*
* When the PECL extension is not present, this class is instantiated when the
* map is loaded, and it is used by the autoloader.
*
* When the extension is present, this class is instantiated only after a call
* to Automap::map() and is not used by the autoloader.
*
* This file is included in the PHK PHP runtime.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

//===========================================================================
/**
* Automap map instance
*
* This class allows to open and examine a map file. WHen the PECL extension is
* not present, it is used for autoloading.
*
* @package Automap
*/
//===========================================================================

if (!class_exists('Automap_Map',false)) 
{
class Automap_Map
{
/** The absolute path of the map file */

private $path;			

/** @var array(<key> => <target>)	The symbol table (filled from slots) */

private $symbols;

/** @var array(<ns> => <slot data>)	The symbols not loaded in the symbol table yet */

private $slots;

/** @var integer Symbol count of this map */

private $symcount;

/** @var array(<name> => <value>)	The map options */

private $options;

/** @var string The version of Automap_Creator that created the map file */

private $version;

/** @var string The minimum runtime version needed to understand the map file */

private $min_version;

/** @var integer Load flags */

private $flags;

/** @var string Absolute base path */

private $base_path;

//-----
/**
* Construct a map object from an existing map file (real or virtual)
*
* @param string $path Path of the map file to read
* @param integer $flags Combination of Automap load flags (@see Automap)
* @param string Reserved for internal use (PHK). Never set this.
*/

public function __construct($path,$flags=0,$_bp=null)
{
$this->path=self::mk_absolute_path($path);
$this->flags=$flags;

try
{
//-- Get file content

if (($buf=@file_get_contents($this->path))===false)
	throw new Exception('Cannot read map file');
$bufsize=strlen($buf);
if ($bufsize<70) throw new Exception("Short file (size=$bufsize)");

//-- Check magic

if (substr($buf,0,14)!=Automap::MAGIC) throw new Exception('Bad Magic');

//-- Check min runtime version required by map

$this->min_version=trim(substr($buf,14,12));	
if (version_compare($this->min_version,Automap::VERSION) > 0)
	throw new Exception($this->path.': Cannot understand this map.'.
		' Requires at least Automap version '.$this->min_version);

//-- Check if the map format is not too old

$this->version=trim(substr($buf,26,12));
if (strlen($this->version)==0)
	throw new Exception('Invalid empty map version');
if (version_compare($this->version,Automap::MIN_MAP_VERSION) < 0)
	throw new Exception('Cannot understand this map. Format too old.');
$map_major_version=$this->version{0};

//-- Check file size

if (strlen($buf)!=($sz=(int)substr($buf,38,8)))
	throw new Exception('Invalid file size. '.$sz.' should be '.strlen($buf));

//-- Check CRC

if (!($flags & Automap::CRC_CHECK))
	{
	$crc=substr($buf,46,8);
	$buf=substr_replace($buf,'00000000',46,8);
	if ($crc!==hash('adler32',$buf)) throw new Exception('CRC error');
	}

//-- Symbol count

$this->symcount=(int)substr($buf,54,8);

//-- Read data

$dsize=(int)substr($buf,62,8);
if (($buf=unserialize(substr($buf,70,$dsize)))===false)
	throw new Exception('Cannot unserialize data from map file');
if (!is_array($buf))
	throw new Exception('Map file should contain an array');
if (!array_key_exists('options',$buf)) throw new Exception('No options array');
if (!is_array($this->options=$buf['options']))
	throw new Exception('Options should be an array');
if (!array_key_exists('map',$buf)) throw new Exception('No symbol table');
if (!is_array($this->slots=$buf['map']))
	throw new Exception('Slot table should contain an array');
$this->symbols=array();

//-- Compute base path

if (!is_null($_bp)) $this->base_path=$_bp;
else $this->base_path=self::combine_path(dirname($this->path)
	,$this->option('base_path'),true);

}
catch (Exception $e)
	{
	$this->symbols=array(); // No retry later
	throw new Exception($path.': Cannot load map - '.$e->getMessage());
	}
}

//---------
/**
* Load a slot into the symbol table
*
* @param string $ns Normalized namespace. Must correspond to an existing slot (no check)
* @return null
*/

private function load_slot($ns)
{
$this->symbols=array_merge($this->symbols,unserialize($this->slots[$ns]));
unset($this->slots[$ns]);
}

//---------
/**
* Extracts the namespace from a symbol name
*
* The returned value has no leading/trailing separator.
*
* Do not use: access reserved for Automap classes
*
* @param string $name The symbol value (case sensitive)
* @return string Namespace. If no namespace, returns an empty string.
*/

public static function ns_key($name)
{
$name=trim($name,'\\');
$pos=strrpos($name,'\\');
if ($pos!==false) return substr($name,0,$pos);
else return '';
}

//---
// These utility functions return 'read-only' properties

public function path() { return $this->path; }
public function flags() { return $this->flags; }
public function options() { return $this->options; }
public function version() { return $this->version; }
public function min_version() { return $this->min_version; }
public function base_path() { return $this->base_path; }

//---

public function option($opt)
{
return (isset($this->options[$opt]) ? $this->options[$opt] : null);
}

//---

public function symbol_count()
{
return $this->symcount;
}

//---
// The entry we are exporting must be in the symbol table (no check)
// We need to use combine_path() because the registered path (rpath) can be absolute

private function export_entry($key)
{
$entry=$this->symbols[$key];

$a=array(
	'stype'		=> $key{0},
	'symbol' 	=> substr($key,1),
	'ptype'		=> $entry{0},
	'rpath'		=> substr($entry,1)
	);

$a['path']=(($a['ptype']===Automap::F_EXTENSION) ? $a['rpath']
	: self::combine_path($this->base_path,$a['rpath']));

return $a;
}

//---

public function get_symbol($type,$symbol)
{
$key=Automap::key($type,$symbol);
if (!($found=array_key_exists($key,$this->symbols)))
	{
	if (count($this->slots))
		{
		$ns=self::ns_key($symbol);
		if (array_key_exists($ns,$this->slots)) $this->load_slot($ns);
		$found=array_key_exists($key,$this->symbols);
		}
	}
return ($found ? $this->export_entry($key) : false);
}

//-------
/**
* Try to resolve a symbol using this map
*
* For performance reasons, we trust the map and don't check if the symbol is
* defined after loading the script/extension/package.
*
* @param string $type One of the Automap::T_xxx symbol types
* @param string Symbol name including namespace (no leading '\')
* @param integer $id Used to return the ID of the map where the symbol was found
* @return exported entry if found, false if not found
*/

public function resolve($type,$name,&$id)
{
if (($this->flags & Automap::NO_AUTOLOAD)
		|| (($entry=$this->get_symbol($type,$name))===false)) return false;

//-- Found

$path=$entry['path']; // Absolute path
switch($entry['ptype'])
	{
	case Automap::F_EXTENSION:
		if (!dl($path)) return false;
		break;

	case Automap::F_SCRIPT:
		//\Phool\Display::debug("Loading script file : $path");//TRACE
		{ require($path); }
		break;

	case Automap::F_PACKAGE:
		// Remove E_NOTICE messages if the test script is a package - workaround
		// to PHP bug #39903 ('__COMPILER_HALT_OFFSET__ already defined')
		// In case of embedded packages and maps, the returned ID corresponds to
		// the map where the symbol was finally found.
	
		error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
		$mnt=require($path);
		error_reporting($errlevel);
		$pkg=PHK_Mgr::instance($mnt);
		$id=$pkg->automap_id();
		return Automap::map($id)->resolve($type,$name,$id);
		break;

	default:
		throw new Exception('<'.$entry['ptype'].'>: Unknown target type');
	}
return $entry;
}

//---

public function symbols()
{
/* First, load every remaining slot */

foreach(array_keys($this->slots) as $ns) $this->load_slot($ns);

/* Then, convert every entry to the export format */

$ret=array();
foreach(array_keys($this->symbols) as $key) $ret[]=$this->export_entry($key);

return $ret;
}

//---
// Proxy to Automap_Display::show()

public function show($format=null,$subfile_to_url_function=null)
{
return Automap_Display::show($this,$format,$subfile_to_url_function);
}

//---

public function export($path=null)
{
if (is_null($path)) $path="php://stdout";
$fp=fopen($path,'w');
if (!$fp) throw new Exception("$path: Cannot open for writing");

foreach($this->symbols() as $s)
	{
	fwrite($fp,$s['stype'].'|'.$s['symbol'].'|'.$s['ptype'].'|'.$s['rpath']."\n");
	}

fclose($fp);
}

//---------------------------------
/**
* Transmits map elements to the PECL extension
*
* Reserved for internal use
*
* The first time a given map file is loaded, it is read by Automap_Map and
* transmitted to the extension. On subsequent requests, it is retrieved from
* persistent memory. This allows to code complex features in PHP and maintain
* the code in a single location without loosing in performance.
*
* @param string $version The version of data to transmit (reserved for future use)
* @return array
*/

public function _pecl_get_map($version)
{
$st=array();
foreach($this->symbols() as $s)
	{
	$st[]=array($s['stype'],$s['symbol'],$s['ptype'],$s['path']);
	}

return $st;
}

//============ Utilities (taken from external libs) ============
// We need to duplicate these methods here because this class is included in the
// PHK PHP runtime, which does not include the \Phool\xxx classes.

//----- Taken from \Phool\File
/**
* Combines a base path with another path
*
* The base path can be relative or absolute.
*
* The 2nd path can also be relative or absolute. If absolute, it is returned
* as-is. If it is a relative path, it is combined to the base path.
*
* Uses '/' as separator (to be compatible with stream-wrapper URIs).
*
* @param string $base The base path
* @param string|null $path The path to combine
* @param bool $separ true: add trailing sep, false: remove it
* @return string The resulting path
*/

private static function combine_path($base,$path,$separ=false)
{
if (($base=='.') || ($base=='') || self::is_absolute_path($path))
	$res=$path;
elseif (($path=='.') || is_null($path))
	$res=$base;
else	//-- Relative path : combine it to base
	$res=rtrim($base,'/\\').'/'.$path;

return self::trailing_separ($res,$separ);
}

/**
* Adds or removes a trailing separator in a path
*
* @param string $path Input
* @param bool $flag true: add trailing sep, false: remove it
* @return bool The result path
*/

private static function trailing_separ($path, $separ)
{
$path=rtrim($path,'/\\');
if ($path=='') return '/';
if ($separ) $path=$path.'/';
return $path;
}

/**
* Determines if a given path is absolute or relative
*
* @param string $path The path to check
* @return bool True if the path is absolute, false if relative
*/

private static function is_absolute_path($path)
{
return ((strpos($path,':')!==false)
	||(strpos($path,'/')===0)
	||(strpos($path,'\\')===0));
}

/**
* Build an absolute path from a given (absolute or relative) path
*
* If the input path is relative, it is combined with the current working
* directory.
*
* @param string $path The path to make absolute
* @param bool $separ True if the resulting path must contain a trailing separator
* @return string The resulting absolute path
*/

private static function mk_absolute_path($path,$separ=false)
{
if (!self::is_absolute_path($path)) $path=self::combine_path(getcwd(),$path);
return self::trailing_separ($path,$separ);
}

//---
} // End of class Automap_Map
//===========================================================================
} // End of class_exists('Automap_Map')
//===========================================================================
?>
