<?php
//----------------------------------------------------------------------------
/**
* @package Phool
*/
//----------------------------------------------------------------------------
/**
* @package Phool
*/

class PHO_File
{

//----

public static function file_suffix($filename)
{
$dotpos=strrpos($filename,'.');
if ($dotpos===false) return '';

return strtolower(substr($filename,$dotpos+1));
}

//----
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

public static function combine_path($base,$path,$separ=false)
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
/**
* Adds or removes a trailing separator in a path
*
* @param string $path Input
* @param bool $flag true: add trailing sep, false: remove it
* @return bool The result path
*/

public static function trailing_separ($path, $separ)
{
$path=rtrim($path,'/\\');
if ($path=='') return '/';
if ($separ) $path=$path.'/';
return $path;
}

//---------------------------------
/**
* Determines if a given path is absolute or relative
*
* @param string $path The path to check
* @return bool True if the path is absolute, false if relative
*/

public static function is_absolute_path($path)
{
return ((strpos($path,':')!==false)
	||(strpos($path,'/')===0)
	||(strpos($path,'\\')===0));
}

//---------------------------------
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

public static function mk_absolute_path($path,$separ=false)
{
if (!self::is_absolute_path($path)) $path=self::combine_path(getcwd(),$path);
return self::trailing_separ($path,$separ);
}

//---------

public static function readfile($path)
{
if (($data=@file_get_contents($path))===false)
	throw new Exception($path.': Cannot get file content');
return $data;
}

//---------
// Throws exceptions and removes '.' and '..'

public static function scandir($path)
{
if (($subnames=scandir($path))===false)
	throw new Exception($path.': Cannot read directory');

$a=array();
foreach($subnames as $f)
	if (($f!='.') && ($f!='..')) $a[]=$f;

return $a;
}

//---------------------------------

public static function atomic_write($path,$data)
{
$tmpf=tempnam(dirname($path),'tmp_');

if (file_put_contents($tmpf,$data)!=strlen($data))
	throw new Exception($tmpf.": Cannot write");

// Windows does not support renaming to an existing file (looses atomicity)

if (PHO_Util::env_is_windows()) @unlink($path);

if (!rename($tmpf,$path))
	{
	unlink($tmpf);
	throw new Exception($path,'Cannot replace file');
	}
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

public static function path_unique_id($prefix,$path,&$mtime)
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

//----------
} // End of class
//=============================================================================
?>
