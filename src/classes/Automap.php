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
const VERSION='1.1.0';

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

//================== Map manager (static methods) =======================

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

// Combines a type and a symbol in a 'key'. The resulting string can be used
// as a key or a value with the appropriate prefix in an automap.

public static function key($type,$symbol)
{
// Extension names are case insensitive

if (($type==self::T_EXTENSION)
	||($type==self::T_FUNCTION)
	||($type==self::T_CLASS)) $symbol=strtolower($symbol);

return $type.$symbol;
}

//---------

public static function get_type_from_key($key)
{
if (strlen($key) <= 1) throw new Exception('Invalid key');

return $key{0};
}

//---------
// Extracts the symbol from a key. If the key contains
// a '|' character, ignores everything from this char.

public static function get_symbol_from_key($key)
{
if (strlen($key) <= 1) throw new Exception('Invalid key');

return substr($key,1,strcspn($key,'|',1));
}

//---------

public static function get_type_string($type)
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
* Given a file path, tries to determine if it is currently mounted. If it is
* the case, the corresponding mount point is returned. If not, an exception is
* thrown.
*
* @param string $path Path of an automap file
* @return the corresponding mount point
* @throws Exception if the file is not currently mounted
*/

public static function path_to_mnt($path)
{
$dummy=null;

$mnt=self::path_unique_id('m',$path,$dummy);

if (self::is_mounted($mnt)) return $mnt;

throw new Exception($path.': path is not mounted');
}

//---------
/**
* Mount an automap and returns the new (or previous, if already loaded)
* mount point.
*
* @param string $path The path of an existing automap file
* @param string $base_dir The base directory to use as a prefix (with trailing
*				separator).
* @param int $flags Or-ed combination of mount flags.
* @param string $mnt The mount point to use. Reserved for stream wrappers.
*					 Should be null for plain files.
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

if (is_null($base_dir))
	{
	$base_dir=dirname($path);
	if (($base_dir!=='/') && ($base_dir!=='\\'))
		$base_dir .= DIRECTORY_SEPARATOR;
	}

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
	unset(self::$automaps[$mnt]);
	}
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
								|| interface_exists($symbol,false);

	case self::T_EXTENSION:	return extension_loaded($symbol);
	}
}

//---------
// The autoload handler, the default type is 'class', hoping that future
// versions of PHP support function and constant autoloading.

public static function autoload_hook($symbol,$type=self::T_CLASS)
{
self::get_symbol($type,$symbol,true,false);
}

//---------
// resolve a symbol, i.e. load what needs to be loaded for the symbol to be
// defined. Returns true on success / false if unable to resolve symbol.

private static function get_symbol($type,$symbol,$autoload=false
	,$exception=false)
{
echo "get_symbol(".self::get_type_string($type).",$symbol)\n";//TRACE

if (!$autoload)
	{
	if (self::symbol_is_defined($type,$symbol)) return true;
	}

$key=self::key($type,$symbol);
foreach(array_reverse(self::$mount_order) as $map)
	{
	if ((!is_null($map)) && $map->resolve_key($key)) return true;
	}

foreach (self::$failure_handlers as $callable) $callable($key);

if ($exception) throw new Exception('Automap: Unknown '
	.self::get_type_string($type).': '.$symbol);

return false;
}

//---------

public static function get_function($symbol)
	{ return self::get_symbol(self::T_FUNCTION,$symbol,false,false); }

public static function get_constant($symbol)
	{ return self::get_symbol(self::T_CONSTANT,$symbol,false,false); }

public static function get_class($symbol)
	{ return self::get_symbol(self::T_CLASS,$symbol,false,false); }

public static function get_extension($symbol)
	{ return self::get_symbol(self::T_EXTENSION,$symbol,false,false); }

//---------

public static function require_function($symbol)
	{ return self::get_symbol(self::T_FUNCTION,$symbol,false,true); }

public static function require_constant($symbol)
	{ return self::get_symbol(self::T_CONSTANT,$symbol,false,true); }

public static function require_class($symbol)
	{ return self::get_symbol(self::T_CLASS,$symbol,false,true); }

public static function require_extension($symbol)
	{ return self::get_symbol(self::T_EXTENSION,$symbol,false,true); }

//=============== Instance (one per map) =================================
// Automap instance
// Used for plain maps and package-wrapped maps. So, this class must support
// plain script files and packages.
// Using a 2-stage creation. __construct creates a simple instance, and
// realize() really reads the map file.

private $path;
private $base_dir; // Prefix to combine with table entries (with trailing separator)
private $mnt;
private $flags;	 // Load flags;
private $mnt_count;

private $symbols=null;	// Null until realize()d
private $options=null;
private $version;
private $min_version;

//-----
// This object must be created from load() or from Automap_Creator.
// Making __construct() private avoids direct creation from elsewhere.
// base_dir is used only when resolving symbols.
// If base_dir is not set, it is taken as the directory where the map file lies

private function __construct($path,$base_dir,$mnt,$flags=0)
{
$this->path=$path;
$this->mnt=$mnt;
$this->base_dir=$base_dir;
$this->flags=$flags;

$this->mnt_count=1;
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
	throw new Exception('Cannot understand this automap.'.
		' Requires at least Automap version '.$this->min_version);

$this->version=trim(substr($buf,30,12));

if (strlen($buf)!=($sz=(int)substr($buf,45,8)))		// Check file size
	throw new Exception('Invalid file size. Should be '.$sz);

if (($buf=unserialize(substr($buf,53)))===false)
	throw new Exception('Cannot unserialize data from map file');

if (!is_array($buf))
	throw new Exception('Map file should contain an array');

if (!array_key_exists('map',$buf))
	throw new Exception('No symbol table');

if (!array_key_exists('options',$buf))
	throw new Exception('No options array');

if (!is_array($this->symbols=$buf['map']))
	throw new Exception('Symbol table should contain an array');

if (!is_array($this->options=$buf['options']))
	throw new Exception('Options should be an array');
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
self::validate($this->mnt);

return $this->path;
}

//---

public function base_dir()
{
self::validate($this->mnt);

return $this->base_dir;
}

//---

public function mnt()
{
self::validate($this->mnt);

return $this->mnt;
}

//---

public function flags()
{
self::validate($this->mnt);

return $this->flags;
}

//---

public function symbols()
{
self::validate($this->mnt);

$this->realize();
return $this->symbols;
}

//---

public function options()
{
self::validate($this->mnt);

$this->realize();
return $this->options;
}

//---

public function version()
{
self::validate($this->mnt);

$this->realize();
return $this->version;
}

//---

public function min_version()
{
self::validate($this->mnt);

$this->realize();
return $this->min_version;
}

//---

public function option($opt)
{
self::validate($this->mnt);

$this->realize();

return (isset($this->options[$opt]) ? $options[$opt] : null);
}

//---

public function symbol_count()
{
self::validate($this->mnt);

return count($this->symbols());
}

//---

private function call_success_handlers($key,$value)
{
foreach (self::$success_handlers as $callable)
	$callable($key,$this->mnt,$value);
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
$this->realize();
if (!isset($this->symbols[$key])) return false;

$value=$this->symbols[$key];
$fname=self::get_symbol_from_key($value);

switch($ftype=self::get_type_from_key($value))
	{
	case self::F_EXTENSION:
		if (!dl($fname)) return false;
		$this->call_success_handlers($key,$value);
		break;

	case self::F_SCRIPT:
		$file=$this->base_dir.$fname;
		//echo "Loading script file : $file\n";//TRACE
		{ require($file); }
		$this->call_success_handlers($key,$value);
		break;

	case self::F_PACKAGE:
		// Remove E_NOTICE messages if the test script is a package - workaround
		// to PHP bug #39903 ('__COMPILER_HALT_OFFSET__ already defined')

		$file=$this->base_dir.$fname;
		error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
		$mnt=require($file);
		error_reporting($errlevel);
		self::instance($mnt)->resolve_key($key);
		break;

	default:
		throw new Exception('<'.$ftype.'>: Unknown file type in map');
	}

return true;
}

//---------
// Display the content of a map

public function show($subfile_to_url_function=null)
{
self::validate($this->mnt);

$this->realize();

if ($html=self::is_web())
	{
	$this->html_show($subfile_to_url_function);
	return;
	}

echo "\n* Global information :\n\n";
echo '	Map version : '.$this->version."\n";
echo '	Min reader version : '.$this->min_version."\n";
echo '	Symbol count : '.$this->symbol_count()."\n";

echo "\n* Options :\n\n";
print_r($this->options);

echo "\n* Symbols :\n\n";

$ktype_len=$kname_len=4;
$fname_len=10;

foreach($this->symbols as $key => $value)
	{
	$ktype=self::get_type_string(self::get_type_from_key($key));
	$kname=self::get_symbol_from_key($key);

	$ftype=self::get_type_from_key($value);
	$fname=self::get_symbol_from_key($value);

	$ktype_len=max($ktype_len,strlen($ktype)+2);
	$kname_len=max($kname_len,strlen($kname)+2);
	$fname_len=max($fname_len,strlen($fname)+2);
	}

echo str_repeat('-',$ktype_len+$kname_len+$fname_len+8)."\n";
echo '|'.str_pad('Type',$ktype_len,' ',STR_PAD_BOTH);
echo '|'.str_pad('Name',$kname_len,' ',STR_PAD_BOTH);
echo '| T ';
echo '|'.str_pad('Defined in',$fname_len,' ',STR_PAD_BOTH);
echo "|\n";
echo '|'.str_repeat('-',$ktype_len);
echo '|'.str_repeat('-',$kname_len);
echo '|---';
echo '|'.str_repeat('-',$fname_len);
echo "|\n";

foreach($this->symbols as $key => $value)
	{
	$ktype=ucfirst(self::get_type_string(self::get_type_from_key($key)));
	$kname=self::get_symbol_from_key($key);

	$ftype=self::get_type_from_key($value);
	$fname=self::get_symbol_from_key($value);

	echo '| '.str_pad(ucfirst($ktype),$ktype_len-1,' ',STR_PAD_RIGHT);
	echo '| '.str_pad($kname,$kname_len-1,' ',STR_PAD_RIGHT);
	echo '| '.$ftype.' ';
	echo '| '.str_pad($fname,$fname_len-1,' ',STR_PAD_RIGHT);
	echo "|\n";
	}
}
//---
// The same in HTML

private function html_show($subfile_to_url_function=null)
{
echo "<h2>Global information</h2>";

echo '<table border=0>';
echo '<tr><td>Map version:&nbsp;</td><td>'
	.htmlspecialchars($this->version).'</td></tr>';
echo '<tr><td>Min reader version:&nbsp;</td><td>'
	.htmlspecialchars($this->min_version).'</td></tr>';
echo '<tr><td>Symbol count:&nbsp;</td><td>'
	.$this->symbol_count().'</td></tr>';
echo '</table>';

echo "<h2>Options</h2>";
echo '<pre>'.htmlspecialchars(print_r($this->options,true)).'</pre>';

echo "<h2>Symbols</h2>";

echo '<table border=1 bordercolor="#BBBBBB" cellpadding=3 '
	.'cellspacing=0 style="border-collapse: collapse"><tr><th>Type</th>'
	.'<th>Name</th><th>FT</th><th>Defined in</th></tr>';
foreach($this->symbols as $key => $value)
	{
	$ktype=ucfirst(self::get_type_string(self::get_type_from_key($key)));
	$kname=self::get_symbol_from_key($key);

	$ftype=self::get_type_from_key($value);
	$fname=self::get_symbol_from_key($value);

	echo '<tr><td>'.$ktype.'</td><td>'.htmlspecialchars($kname)
		.'</td><td align=center>'.$ftype.'</td><td>';
	if (!is_null($subfile_to_url_function)) 
		echo '<a href="'.call_user_func($subfile_to_url_function,$fname).'">';
	echo htmlspecialchars($fname);
	if (!is_null($subfile_to_url_function)) echo '</a>';
	echo '</td></tr>';
	}
echo '</table>';
}

//---

public function export($path=null)
{
self::validate($this->mnt);

$this->realize();

$file=(is_null($path) ? "STDOUT" : $path);
$fp=fopen($file,'w');
if (!$fp) throw new Exception("$file: Cannot open for writing");

foreach($this->symbols as $key => $value) fwrite($fp,"$key $value\n");

fclose($fp);
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
