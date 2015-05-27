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
* The Automap (PHP) manager.
*
* This code is never used when the PHK PECL extension is present.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

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

namespace Automap {

if (!class_exists('Automap\Mgr',false)) 
{
class Mgr
{
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

/** Check CRC */

const CRC_CHECK=2;

/** Load is done by the PECL extension - Reserved for internal use */

const PECL_LOAD=4;

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

private static $failureHandlers=array();

/** @var array(callables) Registered success handlers */

private static $successHandlers=array();

/** @var bool Whether the PHP engine is able to autoload constants */

private static $supportConstantAutoload; // 

/** @var bool Whether the PHP engine is able to autoload functions */

private static $supportFunctionAutoload; // 

/** @var array(<map ID> => <\Automap\Map>) Array of active maps */

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

$f=new \ReflectionFunction('function_exists');
self::$supportFunctionAutoload=($f->getNumberOfParameters()==2);

$f=new \ReflectionFunction('defined');
self::$supportConstantAutoload=($f->getNumberOfParameters()==2);
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

public static function registerFailureHandler($callable)
{
self::$failureHandlers[]=$callable;
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

private static function callFailureHandlers($type,$name)
{
foreach (self::$failureHandlers as $callable) $callable($type,$name);
}

//--------------
/**
* Register a success handler
*
* Once registered, the success handler is called each time a symbol resolution
* succeeds.
*
* The success handler receives two arguments : An array as returned by the
* getSymbol() method, and the ID of the map where the symbol was found.
*
* There is no limit on the number of success handlers that can be registered.
*
* Handlers cannot be unregistered.
*
* @param callable $callable
* @return null
*/

public static function registerSuccessHandler($callable)
{
self::$successHandlers[]=$callable;
}

//---

private static function callSuccessHandlers($entry,$id)
{
foreach (self::$successHandlers as $callable)
	$callable($entry,$id);
}

//-------- Key management -----------
/**
* Combines a type and a symbol in a 'key'
*
* Starting with version 3.0, Automap is fully case-sensitive. This allows for
* higher performance and cleaner code.
*
* Do not use: access reserved for Automap classes
*
* @param string $type one of the 'T_' constants
* @param string $name The symbol value (case sensitive)
* @return string Symbol key
*/

public static function key($type,$name)
{
return $type.trim($name,'\\');
}

//---------

public static function typeToString($type)
{
if (!isset(self::$type_strings[$type]))
	throw new \Exception("$type: Invalid type");

return self::$type_strings[$type];
}

//---------

public static function stringToType($string)
{
$type=array_search($string,self::$type_strings,true);

if ($type===false) throw new \Exception("$type: Invalid type");

return $type;
}

//-------- Map loading/unloading -----------

/**
* Checks if a map ID is active (if it corresponds to a loaded map)
*
* @param integer $id ID to check
* @return boolean
*/

public static function isActiveID($id)
{
return isset(self::$maps[$id]);
}

//-----
/**
* Same as isActiveID() but throws an exception if the map ID is invalid
*
* Returns the map ID so that it can be embedded in a call string.
*
* @param integer $id ID to check
* @return integer ID (not modified)
* @throws \Exception if the ID is invalid (not loaded)
*/

private static function validate($id)
{
if (!self::isActiveID($id)) throw new \Exception($id.': Invalid map ID');

return $id;
}

//-----
/**
* Returns the \Automap\Map object corresponding to an active map ID
*
* @param string $id The map ID
* @return \Automap\Map instance
* @throws \Exception if map ID is invalid
*/

public static function map($id)
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

public static function activeIDs()
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
$map=new \Automap\Map($path,$flags,$_bp);

$id=self::$load_index++;
self::$maps[$id]=$map;
// \Phool\Display::info("Loaded $path as ID $id");//TRACE
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
* @return null
*/

public static function unload($id)
{
self::validate($id);

unset(self::$maps[$id]);
// \Phool\Display::info("Unloaded ID $id");//TRACE
}

//---------------------------------

public static function usingAccelerator()
{
return false;
}

//-------- Symbol resolution -----------

private static function symbolIsDefined($type,$name)
{
switch($type)
	{
	case self::T_CONSTANT:	return (self::$supportConstantAutoload ?
		defined($name,false) : defined($name));

	case self::T_FUNCTION:	return (self::$supportFunctionAutoload ?
		function_exists($name,false) : function_exists($name));

	case self::T_CLASS:		return class_exists($name,false)
								|| interface_exists($name,false)
								|| (function_exists('trait_exists') && trait_exists($name,false));

	case self::T_EXTENSION:	return extension_loaded($name);
	}
}

//---------
/**
* The autoload handler
*
* Reserved for internal use
*
* @param string $name Symbol name
* @param string Symbol type. One of the T_xxx constants. The default type is 'class',
*   and cannot be anything else as long as PHP does not support function/constant
*   autoloading.
*/

public static function autoloadHook($name,$type=self::T_CLASS)
{
self::resolve($type,$name,true,false);
}

//---------
/**
* Resolve a symbol
*
* , i.e. load what needs to be loaded for the symbol to be
* defined.
*
* In order to optimize the PHK case, maps are searched in reverse order
* (newest first).
*
* Warning: Autoload mechanism is not reentrant. This function cannot reference
* an unknow class (like \Phool\Display).
*
* @param string $type Symbol type
* @param string $name Symbol name
* @param bool $autoloading Whether this was called by the PHP autoloader
* @param bool $exception Whether we must throw an exception if the resolution fails
* @return true on success / false if unable to resolve symbol
* @throw \Exception
*/

private static function resolve($type,$name,$autoloading=false
	,$exception=false)
{
//echo "Resolving $type$name\n";//TRACE

if ((!$autoloading)&&(self::symbolIsDefined($type,$name))) return true;

foreach(array_reverse(self::$maps,true) as $id => $map)
	{
	if (($entry=$map->resolve($type,$name,$id))===false) continue;
	//echo "Symbol $name was resolved from ID $id\n";
	self::callSuccessHandlers($entry,$id);
	return true;
	}

// Failure

self::callFailureHandlers($type,$name);
if ($exception) throw new \Exception('Automap: Unknown '
	.self::typeToString($type).': '.$name);
return false;
}

//---------
// Methods for explicit resolutions

public static function getFunction($name)
	{ return self::resolve(self::T_FUNCTION,$name,false,false); }

public static function getConstant($name)
	{ return self::resolve(self::T_CONSTANT,$name,false,false); }

public static function getClass($name)
	{ return self::resolve(self::T_CLASS,$name,false,false); }

public static function getExtension($name)
	{ return self::resolve(self::T_EXTENSION,$name,false,false); }

//---------

public static function requireFunction($name)
	{ return self::resolve(self::T_FUNCTION,$name,false,true); }

public static function requireConstant($name)
	{ return self::resolve(self::T_CONSTANT,$name,false,true); }

public static function requireClass($name)
	{ return self::resolve(self::T_CLASS,$name,false,true); }

public static function requireExtension($name)
	{ return self::resolve(self::T_EXTENSION,$name,false,true); }

//---
} // End of class
//===========================================================================

// Registers the automap callback (append)

if (!defined('_AUTOMAP_DISABLE_REGISTER'))
	{
	if (!extension_loaded('spl'))
		throw new \Exception("Automap requires the SPL extension");

	spl_autoload_register('\Automap\Mgr::autoloadHook');
	}

Mgr::init();

//---
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
