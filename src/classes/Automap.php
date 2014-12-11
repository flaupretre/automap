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
* The Automap runtime code
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

if (!class_exists('Automap',false)) 
{
//------------------------------------------
/**
* Automap runtime class
*
* This class allows to autoload PHP scripts and extensions by extension,
* constant, class, or function name.
*
* @package Automap
*/

class Automap
{
const VERSION='3.0.0';
const MIN_MAP_VERSION='2.0.0'; // Cannot load maps older than this version

const MAGIC="AUTOMAP  M\024\x8\6\3";// Magic value for map files (offset 0)

//---------

const T_FUNCTION='F';	// Symbol types
const T_CONSTANT='C';
const T_CLASS='L';
const T_EXTENSION='E';

const F_SCRIPT='S';		// Target types
const F_EXTENSION='X';
const F_PACKAGE='P';

private static $type_strings=array(
	self::T_FUNCTION	=> 'function',
	self::T_CONSTANT	=> 'constant',
	self::T_CLASS		=> 'class',
	self::T_EXTENSION	=> 'extension',
	self::F_SCRIPT		=> 'script',
	self::F_EXTENSION	=> 'extension file',
	self::F_PACKAGE		=> 'package'
	);

//-- Load flags

const NO_AUTOLOAD=1;	// Don't use this map for autoloading

//-- Private properties

private static $failure_handlers=array();

private static $success_handlers=array();

private static $support_constant_autoload; // whether the PHP engine is able to
private static $support_function_autoload; // autoload functions/constants

//---------
// This array contains the active maps
// Key=<map ID> ; Value=Automap instance

private static $maps=array();

private static $load_index=1; // The map ID of the next map load

//============ Utilities (taken from external libs) ============

//---------------------------------
// Taken from PHO_File
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

//---------------------------------
// Taken from PHO_File
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

//---------------------------------
// Taken from PHO_File
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

//---------------------------------
// Taken from PHO_File
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

//================== Map manager (static methods) =======================

//--------------

public static function min_map_version()
{
return self::MIN_MAP_VERSION;
}

//--------------
// Undocumented - Internal use only

public static function init()
{
// Determines if function/constant autoloading is supported

$f=new ReflectionFunction('function_exists');
self::$support_function_autoload=($f->getNumberOfParameters()==2);

$f=new ReflectionFunction('defined');
self::$support_constant_autoload=($f->getNumberOfParameters()==2);
}

//-------- User handlers -----------

public static function register_failure_handler($callable)
{
self::$failure_handlers[]=$callable;
}

//--------

private static function call_failure_handlers($type,$symbol)
{
foreach (self::$failure_handlers as $callable) $callable($type,$symbol);
}

//--------

public static function register_success_handler($callable)
{
self::$success_handlers[]=$callable;
}

//-------- Key management -----------

// Combines a type and a symbol in a 'key'.
// Note: Extension names are case insensitive
// Undocumented. External use limited to Automap_Creator

public static function key($type,$symbol)
{
$symbol=trim($symbol,'\\');

switch($type)
	{
	case self::T_EXTENSION:
	case self::T_FUNCTION:
	case self::T_CLASS:
		$symbol=strtolower($symbol);
		break;
	default:
		// lowercase namespace only
		$pos=strrpos($symbol,'\\');
		if ($pos!==false) $symbol=strtolower(substr($symbol,0,$pos)).'\\'.substr($symbol,$pos+1);
	}

return $type.$symbol;
}

//---------

public static function type_to_string($type)
{
if (!isset(self::$type_strings[$type]))
	throw new Exception("$type: Invalid type");

return self::$type_strings[$type];
}

//---------

public static function string_to_type($string)
{
$type=array_search($string,self::$type_strings,true);

if ($type===false) throw new Exception("$type: Invalid type");

return $type;
}

//-------- Map loading/unloading -----------

/**
* Checks if a map ID is active (if it corresponds to a loaded map)
*
* @param integer $id ID to check
* @return boolean
*/

public static function is_active($id)
{
return isset(self::$maps[$id]);
}

//-----
/**
* Same as is_active() but throws an exception if the map ID is invalid
*
* Returns the map ID so that it can be embedded in a call string.
*
* @param integer $id ID to check
* @return integer ID (not modified)
* @throws Exception if the ID is invalid (not loaded)
*/

public static function validate($id)
{
if (!self::is_active($id)) throw new Exception($id.': Invalid map ID');

return $id;
}

//-----
/**
* Returns the Automap object corresponding to an active map ID
*
* @param string $id The map ID
* @return Automap instance
* @throws Exception if map ID is invalid
*/

public static function instance($id)
{
self::validate($id);

return self::$maps[$id];
}

//-----
/**
* Returns the list of currently active IDs.
*
* @return array
*/

public static function active_ids()
{
return array_keys(self::$maps);
}

//---------
/**
* Loads a map file and returns its ID.
*
* @param string $path The path of the map file to load
* @param integer $flags Load flags
* @param string $_bp Reserved for internal operations. Never set this param.
* @return string the map ID
*/

public static function load($path,$flags=0,$_bp=null)
{
$id=self::$load_index++;
try
{
$map=new self($path,$id,$_bp,$flags);
}
catch (Exception $e)
	{
	throw new Exception($path.': Cannot load - '.$e->getMessage());
	}

self::$maps[$id]=$map;
return $id;
}

//---------------------------------
/**
* Unloads a map
*
* We dont use __destruct because :
*	1. We don't want this to be called on script shutdown
*	2. Exceptions cannot be caught when sent from a destructor.
*
* If the input ID is invalid, it is silently ignored.
*
* @param string $id The map ID to unload
*/

public static function unload($id)
{
if (self::is_active($id))
	{
	$map=self::instance($id);
	$map->invalidate();
	unset(self::$maps[$id]);
	}
}

//---------------------------------

public static function using_accelerator()
{
return false;
}

//-------- Symbol resolution -----------

private static function symbol_is_defined($type,$symbol)
{
switch($type)
	{
	case self::T_CONSTANT:	return (self::$support_constant_autoload ?
		defined($symbol,false) : defined($symbol));

	case self::T_FUNCTION:	return (self::$support_function_autoload ?
		function_exists($symbol,false) : function_exists($symbol));

	case self::T_CLASS:		return class_exists($symbol,false)
								|| interface_exists($symbol,false)
								|| (function_exists('trait_exists') && trait_exists($symbol,false));

	case self::T_EXTENSION:	return extension_loaded($symbol);
	}
}

//---------
// The autoload handler, the default type is 'class', hoping that future
// versions of PHP support function and constant autoloading.
// Unpublished

public static function autoload_hook($symbol,$type=self::T_CLASS)
{
self::resolve_symbol($type,$symbol,true,false);
}

//---------
// resolve a symbol, i.e. load what needs to be loaded for the symbol to be
// defined. Returns true on success / false if unable to resolve symbol.

private static function resolve_symbol($type,$symbol,$autoloading=false
	,$exception=false)
{
//echo "resolve_symbol(".self::type_to_string($type).",$symbol)\n";//TRACE

if ((!$autoloading)&&(self::symbol_is_defined($type,$symbol))) return true;

$key=self::key($type,$symbol);
foreach(array_reverse(self::$maps) as $map)
	{
	if ((!($map->flags() & self::NO_AUTOLOAD)) && $map->resolve_key($key))
		return true;
	}

// Failure

self::call_failure_handlers($type,$symbol);
if ($exception) throw new Exception('Automap: Unknown '
	.self::type_to_string($type).': '.$symbol);
return false;
}

//---------

public static function get_function($symbol)
	{ return self::resolve_symbol(self::T_FUNCTION,$symbol,false,false); }

public static function get_constant($symbol)
	{ return self::resolve_symbol(self::T_CONSTANT,$symbol,false,false); }

public static function get_class($symbol)
	{ return self::resolve_symbol(self::T_CLASS,$symbol,false,false); }

public static function get_extension($symbol)
	{ return self::resolve_symbol(self::T_EXTENSION,$symbol,false,false); }

//---------

public static function require_function($symbol)
	{ return self::resolve_symbol(self::T_FUNCTION,$symbol,false,true); }

public static function require_constant($symbol)
	{ return self::resolve_symbol(self::T_CONSTANT,$symbol,false,true); }

public static function require_class($symbol)
	{ return self::resolve_symbol(self::T_CLASS,$symbol,false,true); }

public static function require_extension($symbol)
	{ return self::resolve_symbol(self::T_EXTENSION,$symbol,false,true); }

//=============== Instance (one per map) =================================
// Automap instance
// Used for plain maps and package-wrapped maps. So, this class must support
// plain script files and packages.
// Note: now that the PECL extension exists, there's no need to accelerate the
// PHP runtime anymore. The priority is given to simplicity.

private $path;			// The absolute path of the map file
private $base_path;		// Absolute base path
private $id;			// Map ID
private $symbols=null;	// array($key => array('T' => <symbol type>
						// , 'n' => <case-sensitive symbol name>
						// , 't' => <target type>, 'p' => <target relative path>)
private $flags;			// Load flags
private $options=null;	// array()
private $version;		// The version of the Automap_Creator that created the map
private $min_version;	// The minimum runtime version able to understand the map file
private $valid;			// True if the instance is valid (still loaded). Needed
						// because the instance can still exist after the map is
						// unloaded.

//-----
// This object must be created from load().
// Making __construct() private avoids direct creation from elsewhere.

private function __construct($path,$id,$base_path,$flags)
{
$this->path=self::mk_absolute_path($path);
$this->id=$id;
$this->flags=$flags;
$this->valid=true;

try
{
//-- Get file content

if (($buf=@file_get_contents($this->path))===false)
	throw new Exception('Cannot read map file');
$bufsize=strlen($buf);
if ($bufsize<54) throw new Exception("Short file (size=$bufsize)");

//-- Check magic

if (substr($buf,0,14)!=self::MAGIC) throw new Exception('Bad Magic');

//-- Check min runtime version required by map

$this->min_version=trim(substr($buf,16,12));	
if (version_compare($this->min_version,self::VERSION) > 0)
	throw new Exception($this->path.': Cannot understand this map.'.
		' Requires at least Automap version '.$this->min_version);

//-- Check if the map format is not too old

$this->version=trim(substr($buf,30,12));
if (strlen($this->version)==0)
	throw new Exception('Invalid empty map version');
if (version_compare($this->version,self::MIN_MAP_VERSION) < 0)
	throw new Exception('Cannot understand this map. Format too old.');
$map_major_version=$this->version{0};

//-- Check file size

if (strlen($buf)!=($sz=(int)substr($buf,45,8)))
	throw new Exception('Invalid file size. Should be '.$sz);

//-- Read data
	
if (($buf=unserialize(substr($buf,53)))===false)
	throw new Exception('Cannot unserialize data from map file');
if (!is_array($buf))
	throw new Exception('Map file should contain an array');
if (!array_key_exists('options',$buf)) throw new Exception('No options array');
if (!is_array($this->options=$buf['options']))
	throw new Exception('Options should be an array');
if (!array_key_exists('map',$buf)) throw new Exception('No symbol table');
if (!is_array($bsymbols=$buf['map']))
	throw new Exception('Symbol table should contain an array');

//-- Compute base path
// When set, the base_path arg is an absolute path (with trailing separ)

if (!is_null($base_path)) $this->base_path=$base_path;
else $this->base_path=self::combine_path(dirname($this->path)
	,$this->option('base_path'),true);

//-- Process symbols

$this->symbols=array();
foreach($bsymbols as $bval)
	{
	$a=array();
	switch($map_major_version)
		{
		case '2':
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
	$key=self::key($a['T'],$a['n']);
	$this->symbols[$key]=$a;
	}
}
catch (Exception $e)
	{
	$this->symbols=array(); // No retry later
	throw new Exception($this->path.': Cannot load map - '.$e->getMessage());
	}
}

//-----
// We need to use combine_path() because the registered path can be absolute

private function abs_path($entry)
{
return self::combine_path($this->base_path,$entry['p']);
}

//-----

public function is_valid()
{
return ($this->valid);
}

//-----

private function check_valid()
{
if (!$this->is_valid()) throw new Exception('Accessing invalid (unloaded) map instance');
}

//-----

private function invalidate()
{
$this->valid=false;
}


//---
// These utility functions return 'read-only' properties

public function path() { $this->check_valid(); return $this->path; }
public function id() { $this->check_valid(); return $this->id; }
public function flags() { $this->check_valid(); return $this->flags; }
public function options() { $this->check_valid(); return $this->options; }
public function version() { $this->check_valid(); return $this->version; }
public function min_version() { $this->check_valid(); return $this->min_version; }

//---

public function option($opt)
{
$this->check_valid();

return (isset($this->options[$opt]) ? $this->options[$opt] : null);
}

//---

public function symbol_count()
{
$this->check_valid();

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
	'path'		=> $this->abs_path($entry)
	);
}

//---

public function get_symbol($type,$symbol)
{
$key=self::key($type,$symbol);
if (!isset($this->symbols[$key])) return false;
return $this->export_entry($this->symbols[$key]);
}

//---
// Return an array without keys (key format is private and may change)

public function symbols()
{
$this->check_valid();

$ret=array();
foreach($this->symbols as $entry) $ret[]=$this->export_entry($entry);

return $ret;
}

//---

private function call_success_handlers($entry)
{
foreach (self::$success_handlers as $callable)
	$callable($this->export_entry($entry),$this);
}

//---
/**
* Resolves a symbol.
*
* When the symbol is in a package, the search is recursive and the
* concerned (sub)package(s) are automatically loaded.
*
* @param string $key The key we are resolving
* @return boolean symbol could be resolved (true/false)
*/

private function resolve_key($key)
{
//echo "resolve_key($key)\n";//TRACE

if (!isset($this->symbols[$key])) return false;

$entry=$this->symbols[$key];
$stype=$entry['T'];
$sname=$entry['n'];
$ftype=$entry['t'];
$path=$this->abs_path($entry);

switch($ftype)
	{
	case self::F_EXTENSION:
		if (!dl($path)) return false;
		$this->call_success_handlers($entry);
		break;

	case self::F_SCRIPT:
		//echo "Loading script file : $path\n";//TRACE
		{ require($path); }
		$this->call_success_handlers($entry);
		break;

	case self::F_PACKAGE:
		// Remove E_NOTICE messages if the test script is a package - workaround
		// to PHP bug #39903 ('__COMPILER_HALT_OFFSET__ already defined')

		error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
		$mnt=require($path);
		error_reporting($errlevel);
		// Don't call success handlers for a package (recursion)
		$pkg=PHK_Mgr::instance($mnt);
		$id=$pkg->automap_id();
		self::instance($id)->resolve_key($key);
		// Don't umount the package
		break;

	default:
		throw new Exception('<'.$ftype.'>: Unknown target type');
	}

return true;
}

//---
// Proxy to Automap_Display::show()

public function show($format=null,$subfile_to_url_function=null)
{
return Automap_Display::show($this,$format,$subfile_to_url_function);
}

//---
} // End of class Automap
//===========================================================================

// Registers the automap callback (needs SPL). We support only the SPL
// registration process because defining an _autoload() function is too
// intrusive.

if (!defined('_AUTOMAP_DISABLE_REGISTER'))
	{
	if (!extension_loaded('spl'))
		throw new Exception("Automap requires the SPL extension");

	spl_autoload_register('Automap::autoload_hook');
	}

Automap::init();

} // End of class_exists('Automap')
//===========================================================================
?>
