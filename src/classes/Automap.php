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
const VERSION='2.0.0';
const MIN_MAP_VERSION='1.1.0'; // Cannot load maps older than this version

const MAGIC="AUTOMAP  M\024\x8\6\3";// Magic value for map files (offset 0)

//---------

const T_FUNCTION='F';
const T_CONSTANT='C';
const T_CLASS='L';
const T_EXTENSION='E';

const F_SCRIPT='S';
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

private static $failure_handlers=array();

private static $success_handlers=array();

private static $support_constant_autoload; // whether the engine is able to
private static $support_function_autoload; // autoload functions/constants

//-- Load flags

// Reserved for future use

//---------

private static $automaps;	// Key=mnt ; Value=Automap instance

private static $mount_order; // Key=numeric(load order) ; Value=instance

//============ Utilities (please keep in sync with PHK_Util) ============

private static function is_web()
{
return (php_sapi_name()!='cli');
}

//---------------------------------
/**
* Computes a string uniquely identifying a given path on this host.
*
* Mount point unicity is based on a combination of device+inode+mtime.
*
* On systems which don't supply a valid inode number (eg Windows), we
* maintain a fake inode table, whose unicity is based on the path filtered
* through realpath(). It is not perfect because I am not sure that realpath
* really returns a unique 'canonical' path, but this is best solution I
* have found so far.
*
* @param string $path The path to be mounted
* @return string the computed mount point
* @throws Exception
*/

private static $simul_inode_array=array();
private static $simul_inode_index=1;

private static function path_unique_id($prefix,$path,&$mtime)
{
if (($s=stat($path))===false) throw new Exception("$path: File not found");

$dev=$s[0];
$inode=$s[1];
$mtime=$s[9];

if ($inode==0) // This system does not support inodes
	{
	$rpath=realpath($path);
	if ($rpath === false) throw new Exception("$path: Cannot compute realpath");

	if (isset(self::$simul_inode_array[$rpath]))
		$inode=self::$simul_inode_array[$rpath];
	else
		{ // Create a new slot
		$inode=self::$simul_inode_index++;	
		self::$simul_inode_array[$rpath]=$inode;
		}
	}

return sprintf('%s_%X_%X_%X',$prefix,$dev,$inode,$mtime);
}

//---------
// Combines a base directory and a relative path. If the base directory is
// '.', returns the relative part without modification
// Use '/' separator on stream-wrapper URIs

public static function combine_path($dir,$rpath)
{
if ($dir=='.' || $dir=='') return $rpath;
$rpath=trim($rpath,'/');
$rpath=trim($rpath,'\\');

$separ=(strpos($dir,':')!==false) ? '/' : DIRECTORY_SEPARATOR;
if (($dir==='/') || ($dir==='\\')) $separ='';
else
	{
	$c=substr($dir,-1,1);
	if (($c==='/') || ($c=='\\')) $dir=rtrim($dir,$c);
	}

return (($rpath==='.') ? $dir : $dir.$separ.$rpath);
}

//---------------------------------

private static function is_absolute_path($path)
{
return ((strpos($path,':')!==false)
	||(strpos($path,'/')===0)
	||(strpos($path,'\\')===0));
}

//---------------------------------

private static function mk_absolute_path($path,$separ=false)
{
if (!self::is_absolute_path($path))
	{
	$path=self::combine_path(getcwd(),$path);
	}

if ($path==='/') return $path;

$path=rtrim($path,'/\\');
if ($separ) $path=$path.'/';
return $path;
}

//================== Map manager (static methods) =======================

public static function path_id($path)
{
return self::path_unique_id('m',$path,$dummy);
}

//--------------

public static function min_map_version()
{
return self::MIN_MAP_VERSION;
}

//--------------

public static function init()	// Unpublished - Internal use only
{
self::$automaps=array();
self::$mount_order=array();

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

public static function register_success_handler($callable)
{
self::$success_handlers[]=$callable;
}

//-------- Key management -----------

// Combines a type and a symbol in a 'key'.
// Note: Extension names are case insensitive
// Unpublished. External use limited to Automap_Creator

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

//-------- Map mounting/unmounting -----------

/**
* Checks if a mount point is valid (if it corresponds to a currently mounted
* package)
*
* @param string $mnt Mount point to check
* @return boolean
*/

public static function is_mounted($mnt)
{
return isset(self::$automaps[$mnt]);
}

//-----
/**
* Same as is_mounted but throws an exception is the mount point is invalid.
*
* Returns the mount point so that it can be embedded in a call string.
*
* @param string $mnt Mount point to check
* @return string mount point (not modified)
* @throws Exception if mount point is invalid
*/

public static function validate($mnt)
{
if (!self::is_mounted($mnt)) throw new Exception($mnt.': Invalid mount point');

return $mnt;
}

//-----
/**
* Returns the Automap object corresponding to a given mount point
*
* @param string $mnt Mount point
* @return Automap instance
* @throws Exception if mount point is invalid
*/

public static function instance($mnt)
{
self::validate($mnt);

return self::$automaps[$mnt];
}

//-----
/**
* Returns the list of the defined mount points.
*
* @return array
*/

public static function mnt_list()
{
return array_keys(self::$automaps);
}

//---------
/**
* Mount an automap and returns the new (or previous, if already loaded)
* mount point.
*
* @param string $path The path of an existing automap file
* @param string $base_dir The base directory to use as a prefix (with trailing
*				separator).
* @param string $mnt The mount point to use. Reserved for stream wrappers.
*					 Should be null for plain files.
* @param int $flags Or-ed combination of mount flags.
* @return string the mount point
*/

public static function mount($path,$base_dir=null,$mnt=null,$flags=0)
{
try
{
if (is_null($mnt))
	{
	$dummy=null;
	$mnt=self::path_unique_id('m',$path,$dummy);
	}

if (self::is_mounted($mnt))
	{
	self::instance($mnt)->mnt_count++;
	return $mnt;
	}

if (is_null($base_dir)) $base_dir=dirname($path);
if (($base_dir!=='/') && ($base_dir!=='\\'))
	$base_dir = rtrim($base_dir,'\\/').DIRECTORY_SEPARATOR;

self::$mount_order[]
	=self::$automaps[$mnt]=new self($path,$base_dir,$mnt,$flags);
}
catch (Exception $e)
	{
	if (isset($mnt) && self::is_mounted($mnt)) unset(self::$automaps[$mnt]);
	throw new Exception($path.': Cannot mount - '.$e->getMessage());
	}

return $mnt;
}

//---------------------------------
/**
* Umounts a mounted map.
*
* We dont use __destruct because :
*	1. We don't want this to be called on script shutdown
*	2. Exceptions cannot be caught when sent from a destructor.
*
* Accepts to remove a non registered mount point without error
*
* @param string $mnt The mount point to umount
*/

public static function umount($mnt)
{
if (self::is_mounted($mnt))
	{
	$map=self::instance($mnt);
	if ((--$map->mnt_count) > 0) return;
	
	foreach (self::$mount_order as $order => $obj)
		{
		if ($obj===$map) self::$mount_order[$order]=null;
		}
	$map->invalidate();
	unset(self::$automaps[$mnt]);
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
foreach(array_reverse(self::$mount_order) as $map)
	{
	if ((!is_null($map)) && $map->resolve_key($key)) return true;
	}

foreach (self::$failure_handlers as $callable) $callable($type,$symbol);

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
// PHP runtime anymore. The priority is given to simplicity. So, realize() is
// now called by __construct().

private $path;
private $base_dir; // Prefix to combine with table entries (with trailing separator)
private $mnt;
private $flags;	 // Load flags;
private $mnt_count;

private $symbols=null;	// array($key => array('T' => <symbol type>
						// , 'n' => <case-sensitive symbol name>
						// , 't' => <target type>, 'p' => <target path>))
private $options=null;	// array()
private $version;		// The version of the Automap_Creator that created the map
private $min_version;	// The minimum runtime version able to understand the map file
private $valid;			// True if the instance is valid (still mounted)

//-----
// This object must be created from load() or from Automap_Creator.
// Making __construct() private avoids direct creation from elsewhere.
// base_dir is used only when resolving symbols.
// If base_dir is not set, it is taken as the directory where the map file lies

private function __construct($path,$base_dir,$mnt,$flags=0)
{
$this->path=self::mk_absolute_path($path);
$this->mnt=$mnt;
$this->base_dir=self::mk_absolute_path($base_dir,true);
$this->flags=$flags;
$this->mnt_count=1;
$this->valid=true;

$this->realize();
}

//-----

public function is_valid()
{
return ($this->valid);
}

//-----

private function check_valid()
{
if (!$this->is_valid()) throw new Exception('Accessing invalid (unmounted) Automap instance');
}

//-----

private function invalidate()
{
$this->valid=false;
}

//-----

private function realize()
{
if (!is_null($this->symbols)) return;

try
{
if (($buf=@file_get_contents($this->path))===false)
	throw new Exception($this->path.': Cannot read map file');

if (substr($buf,0,14)!=self::MAGIC) throw new Exception('Bad Magic');

$this->min_version=trim(substr($buf,16,12));	// Check min version
if (version_compare($this->min_version,self::VERSION) > 0)
	throw new Exception($this->path.': Cannot understand this map.'.
		' Requires at least Automap version '.$this->min_version);

$this->version=trim(substr($buf,30,12));
if (strlen($this->version)<1)
	throw new Exception('Invalid empty map version');
if (version_compare($this->version,self::MIN_MAP_VERSION) < 0)
	throw new Exception('Cannot understand this map. Format too old.');
$map_major_version=(int)($this->version{0});

if (strlen($buf)!=($sz=(int)substr($buf,45,8)))		// Check file size
	throw new Exception('Invalid file size. Should be '.$sz);

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
$this->symbols=array();

foreach($bsymbols as $bkey => $bval)
	{
	$a=array();
	switch($map_major_version)
		{
		case 1:
			if ((strlen($bkey)<2)||(strlen($bval)<2))
				throw new Exception('Invalid entry');
			$a['T']=$bkey{0};
			$a['n']=substr($bkey,1);
			$a['t']=$bval{0};
			$a['p']=substr($bval,1);
			break;
		default:
			if (strlen($bval)<5) throw new Exception("Invalid value string: <$bval>");
			$a['T']=$bval{0};
			$a['t']=$bval{1};
			$ta=explode('|',substr($bval,2));
			if (count($ta)<2) throw new Exception("Invalid value string: <$bval>");
			$a['n']=$ta[0];
			$a['p']=$ta[1];
			break;
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

//---

public function path()
{
$this->check_valid();

return $this->path;
}

//---

public function base_dir()
{
$this->check_valid();

return $this->base_dir;
}

//---

public function mnt()
{
$this->check_valid();

return $this->mnt;
}

//---

public function flags()
{
$this->check_valid();

return $this->flags;
}

//---

public function options()
{
$this->check_valid();

return $this->options;
}

//---

public function version()
{
$this->check_valid();

return $this->version;
}

//---

public function min_version()
{
$this->check_valid();

return $this->min_version;
}

//---

public function option($opt)
{
$this->check_valid();

return (isset($this->options[$opt]) ? $options[$opt] : null);
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
// Return an array without keys (keys are kept private and may change in the future)
// The returned path is an absolute path.

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
	$callable($entry['T'],$entry['n'],$this);
}

//---

private function abs_path($entry)
{
if ($entry['t']===self::F_PACKAGE) return $entry['p'];
else return $this->base_dir.$entry['p'];
}

//---
/**
* Resolves an Automap symbol.
*
* When the symbol is in a package, the search is recursive and the
* concerned (sub)package(s) are automatically mounted.
*
* @param string $key The key we are resolving
* @return boolean symbol could be resolved (true/false)
*/

private function resolve_key($key)
{
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
		self::instance($mnt)->resolve_key($key);
		break;

	default:
		throw new Exception('<'.$ftype.'>: Unknown file type');
	}

return true;
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
