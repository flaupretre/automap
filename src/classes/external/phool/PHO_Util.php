<?php
//----------------------------------------------------------------------------
/**
* @package Phool
*/
//----------------------------------------------------------------------------
/**
* @package Phool
*/

class PHO_Util
{

//---------

public static function env_is_web()
{
return (php_sapi_name()!='cli');
}

//----

public static function env_is_windows()
{
return (substr(PHP_OS, 0, 3) == 'WIN');
}

//---------
// Adapted from PEAR

public static function load_extension($ext)
{
if (extension_loaded($ext)) return;

if (PHP_OS == 'AIX') $suffix = 'a';
else $suffix = PHP_SHLIB_SUFFIX;

@dl('php_'.$ext.'.'.$suffix) || @dl($ext.'.'.$suffix);

if (!extension_loaded($ext)) throw new Exception("$ext: Cannot load extension");
}

//---------
// Require several extensions. Allows to list every extensions that are not
// present.

public static function load_extensions($ext_list)
{
$failed_ext=array();
foreach($ext_list as $ext)
	{
	try { self::load_extension($ext); }
	catch (Exception $e) { $failed_ext[]=$ext; }
	}
if (count($failed_ext))
	throw new Exception('Cannot load the following required extension(s): '
		.implode(' ',$failed_ext));
}

//---------
// Replacement for substr()
// Difference : returns '' instead of false (when index out of range)

public static function substr($buf,$position,$len=NULL)
{
$str=is_null($len) ? substr($buf,$position) : substr($buf,$position,$len);
if ($str===false) $str='';
return $str;
}

//---------
// This function must be called before every file access
// In PHP 6, magic_quotes_runtime is suppressed and set_magic_quotes_runtime()
// does not exist any more.

private static $mqr_exists=null;
private static $mqr_level=0;
private static $mqr_save;

public static function disable_mqr()
{
if (is_null(self::$mqr_exists))
	self::$mqr_exists=function_exists('set_magic_quotes_runtime');

if (!self::$mqr_exists) return;

if (self::$mqr_level==0)
	{
	self::$mqr_save=get_magic_quotes_runtime();
	set_magic_quotes_runtime(0);
	}
self::$mqr_level++;
}

//---------
// This function must be called after every file access

public static function restore_mqr()
{
if (is_null(self::$mqr_exists))
	self::$mqr_exists=function_exists('set_magic_quotes_runtime');

if (!self::$mqr_exists) return;

self::$mqr_level--;
if (self::$mqr_level==0) set_magic_quotes_runtime(self::$mqr_save);
}

//---------

public static function mk_array($data)
{
if (is_null($data)) return array();
if (!is_array($data)) $data=array($data);
return $data;
}

//---------------------------------

public static function call_method($object,$method,$args)
{
return call_user_func_array(array($object,$method),$args);
}

//----------
} // End of class
//=============================================================================
?>
