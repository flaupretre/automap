<?php
//============================================================================
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License (LGPL) as
// published by the Free Software Foundation, either version 3 of the License,
// or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//============================================================================
/**
* @copyright Francois Laupretre <francois@tekwire.net>
* @license http://www.gnu.org/licenses GNU Lesser General Public License, V 3.0
*/
//============================================================================

//============================================================================
/**
* Static functions used to display messages (normal, trace, debug...)
*/

class PHO_Display
{
const MAX_VERBOSE_LEVEL=2;	// Highest message level
const MIN_VERBOSE_LEVEL=-3; // Lowest message level -1

// Note: the minimal verbose level allows to hide every messages

private static $prefix=array(
	 2 => '>> ',			// Debug
	 1 => '> ',				// Trace
	 0 => '',				// Info
	-1 => '*Warning* ', 	// Warning
	-2 => "\n***Error*** "	// Error
	);

/** @var integer Verbose level, default=0 */

private static $verbose_level=0;

/** @var integer Array containing the error msgs since the beginning */

private static $errors=array();

//----------------------------------------------------------------------------
/**
* Increment verbose level
*
* @return void
*/

public static function inc_verbose()
{
if (self::$verbose_level < self::MAX_VERBOSE_LEVEL) self::$verbose_level++;
}

//----------------------------------------------------------------------------
/**
* Decrement verbose level
*
* @return void
*/

public static function dec_verbose()
{
if (self::$verbose_level > self::MIN_VERBOSE_LEVEL) self::$verbose_level--;
}

//----------------------------------------------------------------------------
/**
* Set verbose level
*
* @param integer $level integer
* @return void
*/

public static function set_verbose($level)
{
self::$verbose_level=$level;
}

//----------------------------------------------------------------------------
/**
* Conditionnally display a string to stderr
*
* Display the string if the message level is less or equal to the verbose level
*
* @param string $msg The message
* @param integer $level The message level
* @return void
*/

private static function _display($msg,$level)
{
if ($level <= self::$verbose_level)
	{
	$msg=self::$prefix[$level].$msg."\n";
	if (defined('STDERR')) fprintf(STDERR,"%s",$msg);
	else echo $msg;
	}
}

//----------------------------------------------------------------------------
/**
* Display an error message
*
* @param string $msg The message
* @return void
*/

public static function error($msg)
{
self::_display($msg,-2);
self::$errors[]=$msg;
}

//----------------------------------------------------------------------------
/**
* Display a warning message
*
* @param string $msg The message
* @return void
*/

public static function warning($msg)
{
self::_display($msg,-1);
}

//----------------------------------------------------------------------------
/**
* Return the current error count
*
* @return int
*/

public static function error_count()
{
return count(self::$errors);

}

//----------------------------------------------------------------------------
/**
* Return the error array
*
* @return array
*/

public static function get_errors()
{
return self::$errors;
}

//----------------------------------------------------------------------------
/**
* Display a level 0 message
*
* @param string $msg The message
* @return void
*/

public static function msg($msg)
{
self::_display($msg,0);
}

//----------------------------------------------------------------------------
/**
* Display an info message
*
* @param string $msg The message
* @return void
*/

public static function info($msg)
{
self::_display($msg,0);
}

//----------------------------------------------------------------------------
/**
* Display a trace message
*
* @param string $msg The message
* @return void
*/

public static function trace($msg)
{
self::_display($msg,1);
}

//----------------------------------------------------------------------------
/**
* Display a debug message
*
* @param string $msg The message
* @return void
*/

public static function debug($msg)
{
self::_display($msg,2);
}

//----------------------------------------------------------------------------
/**
* Convert a boolean to a displayable string
*
* @param bool $val The boolean value to convert
* @return string The string to display
*/

public static function bool_str($val)
{
return ($val ? 'yes' : 'no');
}

//----------------------------------------------------------------------------
/**
* Converts a variable through var_dump()
*
* @param any $var The value to convert
* @return string The dumped value
*/

public static function vdump($var)
{
ob_start();
var_dump($var);
return ob_get_clean();
}

//----------------------------------------------------------------------------
/**
* Display current stack trace
*
* @return void
*/

public static function show_trace()
{
$e=new Exception();
print_r($e->getTrace());
}

//----------------------------------------------------------------------------
/**
* Return displayable type of a variable
*
* @param any $var
* @return string
*/

public static function var_type($var)
{
return is_object($var) ? 'object '.get_class($var) : gettype($var);
}

//----------------------------------------------------------------------------
/**
* Convert a boolean to a displayable string
*
* @param any $var
* @return string
*/

public static function bool2str($var)
{
return $var ? 'Yes' : 'No';
}

//----------------------------------------------------------------------------
} // End of class PHO_Display
?>