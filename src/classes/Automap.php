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
* The Automap PHP runtime code
*
* This code is never used when the PHK PECL extension is present.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

if (!class_exists('Automap',false)) 
{

//===========================================================================
/**
* A loaded map
*
* This class is defined and used only by the PHP implementation of the Automap
* class. When the PECL extension is present, this class does not exist.
*/

class Automap_Loaded_Map
{
private $map;		// Automap_Map instance;
private $flags;		// Load flags;
private $path;		// Map absolute path
private $base_path;	// Absolute base path

//--------------------------

/**
* Load a file
*/

public function __construct($path,$id,$flags=0,$base_path=null)
{
$this->map=new Automap_Map($path,$flags);

$this->flags=$flags;

$this->path=self::mk_absolute_path($path);

if (!is_null($base_path)) $this->base_path=$base_path;
else $this->base_path=self::combine_path(dirname($this->path)
	,$this->map->option('base_path'),true);
}

//-------

public function __destruct()
{
unset($this->map);
}

//-----

public function map() { return $this->map; }
public function flags() { return $this->flags; }
public function base_path() { return $this->base_path; }

//-------
// We need to use combine_path() because the registered path can be absolute

public function get_symbol($type,$name)
{
$res=$this->map->get_symbol($type,$name);
if ($res===false) return false;
$res['path']=self::combine_path($this->base_path,$res['rpath']);
return $res;
}

//-------
/**
* Try to resolve a symbol using this map
*
* @return exported entry if found, false if not found
*/

public function resolve($type,$name,$id)
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
		//PHO_Display::info("Loading script file : $path");//TRACE
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
		return Automap::lmap($id)->resolve($type,$name,$id);
		break;

	default:
		throw new Exception('<'.$entry['ptype'].'>: Unknown target type');
	}
return array($id,$entry);
}

//============ Utilities (taken from external libs) ============
// We need to duplicate these methods here because this class is included in the
// PHK PHP runtime, which does not include the PHO_xxx classes.

//----- Taken from PHO_File
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

//----- Taken from PHO_File
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

//----- Taken from PHO_File
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

//----- Taken from PHO_File
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

} // End of class Automap_Loaded_Map

//===========================================================================
/**
* This class autoloads PHP scripts and extensions from an extension,
* constant, class, or function name.
*
* Methods use map IDs. A map ID is a non null positive number, uniquely
* identifying a loaded map.
*
* This is a static-only class. It is also implemented in the PECL extension
* and included in the PHK PHP runtime.
*
* @package Automap
*/
//===========================================================================

class Automap
{
/** Runtime API version */

const VERSION='3.0.0';

/** We cannot load maps older than this version */
 
const MIN_MAP_VERSION='3.0.0';

/** Map files start with this string */

const MAGIC="AUTOMAP  M\024\x8\6\3";// Magic value for map files (offset 0)

/** Symbol types */

const T_FUNCTION='F';
const T_CONSTANT='C';
const T_CLASS='L';
const T_EXTENSION='E';

/** Target types */

const F_SCRIPT='S';
const F_EXTENSION='X';
const F_PACKAGE='P';

/* Load flags */

/** Autoloader ignores maps loaded with this flag */

const NO_AUTOLOAD=1;

/** Dont't check CRC */

const NO_CRC_CHECK=2;

/** @var array Fixed value array containing a readable string for each
*              symbol/target type
*/

private static $type_strings=array(
	self::T_FUNCTION	=> 'function',
	self::T_CONSTANT	=> 'constant',
	self::T_CLASS		=> 'class',
	self::T_EXTENSION	=> 'extension',
	self::F_SCRIPT		=> 'script',
	self::F_EXTENSION	=> 'extension file',
	self::F_PACKAGE		=> 'package'
	);

/** @var array(callables) Registered failure handlers */

private static $failure_handlers=array();

/** @var array(callables) Registered success handlers */

private static $success_handlers=array();

/** @var bool Whether the PHP engine is able to autoload constants */

private static $support_constant_autoload; // 

/** @var bool Whether the PHP engine is able to autoload functions */

private static $support_function_autoload; // 

/** @var array(<map ID> => <Automap_Loaded_Map>) Array of active maps */

private static $maps=array();

/** @var integer The map ID of the next map load */

private static $load_index=1;

//================== Map manager (static methods) =======================

//--------------
/**
* Undocumented - Internal use only
*/

public static function init()
{
// Determines if function/constant autoloading is supported

$f=new ReflectionFunction('function_exists');
self::$support_function_autoload=($f->getNumberOfParameters()==2);

$f=new ReflectionFunction('defined');
self::$support_constant_autoload=($f->getNumberOfParameters()==2);
}

//=============== User handlers ===============

/**
* Register a failure handler
*
* Once registered, the failure handler is called each time a symbol resolution
* fails.
*
* There is no limit on the number of failure handlers that can be registered.
*
* Handlers cannot be unregistered.
*
* @param callable $callable
* @return null
*/

public static function register_failure_handler($callable)
{
self::$failure_handlers[]=$callable;
}

//--------------
/**
* Call every registered failure handlers
*
* Call provides two arguments : the symbol type (one of the 'T_' constants)
* and the symbol name.
*
* Handlers are called in registration order.
*
* @param string $type one of the 'T_' constants
* @param string $name The symbol name
* @return null
*/

private static function call_failure_handlers($type,$name)
{
foreach (self::$failure_handlers as $callable) $callable($type,$name);
}

//--------------
/**
* Register a success handler
*
* Once registered, the success handler is called each time a symbol resolution
* succeeds.
*
* The success handler receives two arguments : An array as returned by the
* get_symbol() method, and the map object where the symbol was found.
*
* There is no limit on the number of success handlers that can be registered.
*
* Handlers cannot be unregistered.
*
* @param callable $callable
* @return null
*/

public static function register_success_handler($callable)
{
self::$success_handlers[]=$callable;
}

//---

private function call_success_handlers($entry,$id)
{
foreach (self::$success_handlers as $callable)
	$callable($entry,$id);
}

//-------- Key management -----------
/**
* Combines a type and a symbol in a 'key'
*
* Extension names, functions, classes, and namespaces are case insensitive.
* Constants are case sensitive.
*
* Do not use: access reserved for Automap classes
*
* @param string $type one of the 'T_' constants
* @param string $name The symbol value (case sensitive)
* @return string Symbol key
*/

public static function key($type,$name)
{
$name=trim($name,'\\');

switch($type)
	{
	case self::T_EXTENSION:
	case self::T_FUNCTION:
	case self::T_CLASS:
		$name=strtolower($name);
		break;
	default:
		// lowercase namespace only
		$pos=strrpos($name,'\\');
		if ($pos!==false) $name=strtolower(substr($name,0,$pos)).'\\'.substr($name,$pos+1);
	}

return $type.$name;
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

public static function id_is_active($id)
{
return isset(self::$maps[$id]);
}

//-----
/**
* Same as id_is_active() but throws an exception if the map ID is invalid
*
* Returns the map ID so that it can be embedded in a call string.
*
* @param integer $id ID to check
* @return integer ID (not modified)
* @throws Exception if the ID is invalid (not loaded)
*/

private static function validate($id)
{
if (!self::id_is_active($id)) throw new Exception($id.': Invalid map ID');

return $id;
}

//-----
/**
* Returns the Automap_Map object corresponding to an active map ID
*
* @param string $id The map ID
* @return Automap_Map instance
* @throws Exception if map ID is invalid
*/

public static function map($id)
{
self::validate($id);

return self::$maps[$id]->map();
}

//-----
/**
* Returns the Automap_Loaded_Map object corresponding to an active map ID
*
* Reserved for internal use. This method is not implemented in the PECL code.
*
* @param string $id The map ID
* @return Automap_Loaded_Map instance
* @throws Exception if map ID is invalid
*/

public static function lmap($id)
{
self::validate($id);

return self::$maps[$id];
}

//-----
/**
* Returns the absolute base path corresponding to an active ID
*
* Needed because Automap_Loaded_Map objects exist in PHP only. So, they cannot
* be accessed when PECL extension is active.
*
* Reserved for internal use.
*
* @param string $id The map ID
* @return The absolute base path for this ID
* @throws Exception if map ID is invalid
*/

public static function base_path($id)
{
self::validate($id);

return self::$maps[$id]->base_path();
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
* @return int the map ID
*/

public static function load($path,$flags=0,$_bp=null)
{
$id=self::$load_index++;

try
{
$lmap=new Automap_Loaded_Map($path,$id,$flags,$_bp);
}
catch (Exception $e)
	{
	throw new Exception($path.': Cannot load - '.$e->getMessage());
	}

self::$maps[$id]=$lmap;
// PHO_Display::info("Loaded $path as ID $id");//TRACE
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
self::validate($id);

unset(self::$maps[$id]);
// PHO_Display::info("Unloaded ID $id");//TRACE
}

//---------------------------------

public static function using_accelerator()
{
return false;
}

//-------- Symbol resolution -----------

private static function symbol_is_defined($type,$name)
{
switch($type)
	{
	case self::T_CONSTANT:	return (self::$support_constant_autoload ?
		defined($name,false) : defined($name));

	case self::T_FUNCTION:	return (self::$support_function_autoload ?
		function_exists($name,false) : function_exists($name));

	case self::T_CLASS:		return class_exists($name,false)
								|| interface_exists($name,false)
								|| (function_exists('trait_exists') && trait_exists($name,false));

	case self::T_EXTENSION:	return extension_loaded($name);
	}
}

//---------
// The autoload handler, the default type is 'class', hoping that future
// versions of PHP support function and constant autoloading.
// Reserved for internal use

public static function autoload_hook($name,$type=self::T_CLASS)
{
self::resolve($type,$name,true,false);
}

//---------
// resolve a symbol, i.e. load what needs to be loaded for the symbol to be
// defined. Returns true on success / false if unable to resolve symbol.

private static function resolve($type,$name,$autoloading=false
	,$exception=false)
{
//echo "resolve(".self::type_to_string($type).",$name)\n";//TRACE

if ((!$autoloading)&&(self::symbol_is_defined($type,$name))) return true;

foreach(array_reverse(self::$maps,true) as $id => $map)
	{
	if (($res=$map->resolve($type,$name,$id))===false) continue;
	list($id,$entry)=$res;
	// PHO_Display::info("Symbol $name was resolved from ID $id");
	self::call_success_handlers($entry,$id);
	return true;
	}

// Failure

self::call_failure_handlers($type,$name);
if ($exception) throw new Exception('Automap: Unknown '
	.self::type_to_string($type).': '.$name);
return false;
}

//---------

public static function get_function($name)
	{ return self::resolve(self::T_FUNCTION,$name,false,false); }

public static function get_constant($name)
	{ return self::resolve(self::T_CONSTANT,$name,false,false); }

public static function get_class($name)
	{ return self::resolve(self::T_CLASS,$name,false,false); }

public static function get_extension($name)
	{ return self::resolve(self::T_EXTENSION,$name,false,false); }

//---------

public static function require_function($name)
	{ return self::resolve(self::T_FUNCTION,$name,false,true); }

public static function require_constant($name)
	{ return self::resolve(self::T_CONSTANT,$name,false,true); }

public static function require_class($name)
	{ return self::resolve(self::T_CLASS,$name,false,true); }

public static function require_extension($name)
	{ return self::resolve(self::T_EXTENSION,$name,false,true); }

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
