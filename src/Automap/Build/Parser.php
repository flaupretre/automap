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
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*///==========================================================================

namespace Automap\Build {

//=============================================================================
//-- For PHP version < 5.3.0

// <Automap>:ignore constant T_NAMESPACE
if (!defined('T_NAMESPACE')) define('T_NAMESPACE',-2);
// <Automap>:ignore constant T_NS_SEPARATOR
if (!defined('T_NS_SEPARATOR'))	define('T_NS_SEPARATOR',-3);
// <Automap>:ignore constant T_CONST
if (!defined('T_CONST'))	define('T_CONST',-4);
// <Automap>:ignore constant T_TRAIT
if (!defined('T_TRAIT'))	define('T_TRAIT',-5);

//===========================================================================
/**
* The Automap parser
*
* This class analyzes PHP scripts, packages, or extensions to extract the
* symbols they define
*
* API status: Public
* Included in the PHK PHP runtime: No
* Implemented in the extension: No
*///==========================================================================

if (!class_exists('Automap\Build\Parser',false)) 
{
class Parser implements ParserInterface
{
//-- Parser states :

const ST_OUT=1;						// Upper level
const ST_FUNCTION_FOUND=\Automap\Mgr::T_FUNCTION; // Found 'function'. Looking for name
const ST_SKIPPING_BLOCK_NOSTRING=3; // In block, outside of string
const ST_SKIPPING_BLOCK_STRING=4;	// In block, in string
const ST_CLASS_FOUND=\Automap\Mgr::T_CLASS;	// Found 'class'. Looking for name
const ST_DEFINE_FOUND=6;			// Found 'define'. Looking for '('
const ST_DEFINE_2=7;				// Found '('. Looking for constant name
const ST_SKIPPING_TO_EOL=8;			// Got constant. Looking for EOL (';')
const ST_NAMESPACE_FOUND=9;			// Found 'namespace'. Looking for <whitespace>
const ST_NAMESPACE_2=10;			// Found 'namespace' and <whitespace>. Looking for name
const ST_CONST_FOUND=11;			// Found 'const'. Looking for name

const AUTOMAP_COMMENT=',// *<Automap>:(\S+)(.*)$,';

//---------

/** @var array(array('type' => <symbol type>,'name' => <case-sensitive symbol name>)) */

private $symbols;

/** @var array(symbol keys) A list of symbols to exclude */

private $exclude_list;

//---------------------------------------------------------------------------
/**
* Constructor
*/

public function __construct()
{
$this->symbols=array();
$this->exclude_list=array();
}

//---------------------------------

private function cleanup()
{
// Filter out excluded symbols
if (count($this->exclude_list))
	{
	foreach(array_keys($this->symbols) as $n)
		{
		$s=$this->symbols[$n];
		$key=\Automap\Map::key($s['type'],$s['name']);
		if (array_search($key,$this->exclude_list)!==false)
			unset($this->symbols[$n]);
		}
	}

$a=$this->symbols;
$this->symbols=array();
$this->exclude_list=array();

return $a;
}

//---------------------------------
/**
* Mark a symbol as excluded
*
* @param string $type one of the \Automap\Mgr::T_xx constants
* @param string $name The symbol name
* @return null
*/

private function exclude($type,$name)
{
$this->exclude_list[]=\Automap\Map::key($type,$name);
}

//---------------------------------
/**
* Add a symbol into the table
*
* Filter out the symbol from the exclude list
*
* @param string $type one of the \Automap\Mgr::T_xx constants
* @param string $name The symbol name
* @return null
*/

private function addSymbol($type,$name)
{
$this->symbols[]=array('type' => $type, 'name' => $name);
}

//---------------------------------
/**
* Extracts symbols from an extension
*
* @param string $file Extension name
* @return null
* @throw \Exception if extension cannot be loaded
*/

public function parseExtension($file)
{
$extension_list=get_loaded_extensions();

@dl($file);
$a=array_diff(get_loaded_extensions(),$extension_list);
if (($ext_name=array_pop($a))===NULL)
	throw new \Exception($file.': Cannot load extension');

$this->addSymbol(\Automap\Mgr::T_EXTENSION,$ext_name);

$ext=new \ReflectionExtension($ext_name);

foreach($ext->getFunctions() as $func)
	$this->addSymbol(\Automap\Mgr::T_FUNCTION,$func->getName());

foreach(array_keys($ext->getConstants()) as $constant)
	$this->addSymbol(\Automap\Mgr::T_CONSTANT,$constant);

foreach($ext->getClasses() as $class)
	$this->addSymbol(\Automap\Mgr::T_CLASS,$class->getName());
	
if (method_exists($ext,'getInterfaces')) // Compatibility
	{
	foreach($ext->getInterfaces() as $interface)
		$this->addSymbol(\Automap\Mgr::T_CLASS,$interface->getName());
	}

if (method_exists($ext,'getTraits')) // Compatibility
	{
	foreach($ext->getTraits() as $trait)
		$this->addSymbol(\Automap\Mgr::T_CLASS,$trait->getName());
	}

return $this->cleanup();
}

//---------------------------------
/**
* Combine a namespace with a symbol
*
* The leading and trailing backslashes are first suppressed from the namespace.
* Then, if the namespace is not empty it is prepended to the symbol using a
* backslash.
*
* @param string $ns Namespace (can be empty)
* @param string $symbol Symbol name (cannot be empty)
* @return string Fully qualified name without leading backslash
*/

private static function combineNSSymbol($ns,$symbol)
{
$ns=trim($ns,'\\');
return $ns.(($ns==='') ? '' : '\\').$symbol;
}

//---------------------------------
/**
* Register explicit declarations
*
* Format:
*	<double-slash> <Automap>:declare <type> <value>
*	<double-slash> <Automap>:ignore <type> <value>
*	<double-slash> <Automap>:ignore-file
*	<double-slash> <Automap>:skip-blocks
*
* @return bool false if indexing is disabled on this file
*/

private function parseAutomapDirectives($buf,&$skip_blocks)
{
$a=null;
if (preg_match_all('{^//\s+\<Automap\>:(\S+)(.*)$}m',$buf,$a,PREG_SET_ORDER)!=0)
	{
	foreach($a as $match)
		{
		switch ($cmd=$match[1])
			{
			case 'ignore-file':
				return false;

			case 'skip-blocks':
				$skip_blocks=true;
				break;

			case 'declare':
			case 'ignore':
				$type_string=strtolower(strtok($match[2],' '));
				$name=strtok(' ');
				if ($type_string===false || $name===false)
					throw new \Exception($cmd.': Directive needs 2 args');
				$type=\Automap\Mgr::stringToType($type_string);
				if ($cmd=='declare')
					$this->addSymbol($type,$name);
				else
					$this->exclude($type,$name);
				break;

			default:
				throw new \Exception($cmd.': Invalid Automap directive');
			}
		}
	}
return true;
}

//---------------------------------
/**
* Extracts symbols from a PHP script file
*
* @param string $path FIle to parse
* @return array of symbols
* @throws \Exception on parse error
*/

public function parseScriptFile($path)
{
try
	{
	// Don't run PECL accelerated read for virtual files
	$buf=((function_exists('\Automap\Ext\file_get_contents')
		&& (strpos($path,'://')===false)) ? 
		\Automap\Ext\file_get_contents($path)
		: file_get_contents($path));
	$ret=$this->parseScript($buf);
	return $ret;
	}
catch (\Exception $e)
	{ throw new \Exception("$path: ".$e->getMessage()); }
}

//---------------------------------
/**
* Extracts symbols from a PHP script contained in a string
*
* @param string $buf The script to parse
* @return array of symbols
* @throws \Exception on parse error
*/

public function parseScript($buf)
{
$buf=str_replace("\r",'',$buf);

$skip_blocks=false;

if (!$this->parseAutomapDirectives($buf,$skip_blocks)) return array();

if (function_exists('\Automap\Ext\parseTokens')) 
	{ // If PECL function is available
	$a=\Automap\Ext\parseTokens($buf,$skip_blocks);
	//var_dump($a);//TRACE
	foreach($a as $k) $this->addSymbol($k{0},substr($k,1));
	}
else
	{
	$this->parseTokens($buf,$skip_blocks);
	}

return $this->cleanup();
}

//---------------------------------
/**
* Extract symbols from script tokens
*/

private function parseTokens($buf,$skip_blocks)
{
$block_level=0;
$state=self::ST_OUT;
$name='';
$ns='';

// Note: Using php_strip_whitespace() before token_get_all does not improve
// performance.

foreach(token_get_all($buf) as $token)
	{
	if (is_string($token))
		{
		$tvalue=$token;
		$tnum=-1;
		$tname='String';
		}
	else
		{
		list($tnum,$tvalue)=$token;
		$tname=token_name($tnum);
		}
		
	if (($tnum==T_COMMENT)||($tnum==T_DOC_COMMENT)) continue;
	if (($tnum==T_WHITESPACE)&&($state!=self::ST_NAMESPACE_FOUND)) continue;

	//echo "$tname <$tvalue>\n";//TRACE
	switch($state)
		{
		case self::ST_OUT:
			switch($tnum)
				{
				case T_FUNCTION:
					$state=self::ST_FUNCTION_FOUND;
					break;
				case T_CLASS:
				case T_INTERFACE:
				case T_TRAIT:
					$state=self::ST_CLASS_FOUND;
					break;
				case T_NAMESPACE:
					$state=self::ST_NAMESPACE_FOUND;
					$name='';
					break;
				case T_CONST:
					$state=self::ST_CONST_FOUND;
					break;
				case T_STRING:
					if ($tvalue=='define') $state=self::ST_DEFINE_FOUND;
					$name='';
					break;
				// If this flag is set, we skip anything enclosed
				// between {} chars, ignoring any conditional block.
				case -1:
					if ($tvalue=='{' && $skip_blocks)
						{
						$state=self::ST_SKIPPING_BLOCK_NOSTRING;
						$block_level=1;
						}
					break;
				}
			break;

		case self::ST_NAMESPACE_FOUND:
			$state=($tnum==T_WHITESPACE) ? self::ST_NAMESPACE_2 : self::ST_OUT;
			break;
			
		case self::ST_NAMESPACE_2:
			switch($tnum)
				{
				case T_STRING:
					$name .=$tvalue;
					break;
				case T_NS_SEPARATOR:
					$name .= '\\';
					break;
				default:
					$ns=$name;
					$state=self::ST_OUT;
				}
			break;
			

		case self::ST_FUNCTION_FOUND:
			if (($tnum==-1)&&($tvalue=='('))
				{ // Closure : Ignore (no function name to get here)
				$state=self::ST_OUT;
				break;
				}
			 //-- Function returning ref: keep looking for name
			 if ($tnum==-1 && $tvalue=='&') break;
			// No break here !
		case self::ST_CLASS_FOUND:
			if ($tnum==T_STRING)
				{
				$this->addSymbol($state,self::combineNSSymbol($ns,$tvalue));
				}
			else throw new \Exception('Unrecognized token for class/function definition'
				."(type=$tnum ($tname);value='$tvalue'). String expected");
			$state=self::ST_SKIPPING_BLOCK_NOSTRING;
			$block_level=0;
			break;

		case self::ST_CONST_FOUND:
			if ($tnum==T_STRING)
				{
				$this->addSymbol(\Automap\Mgr::T_CONSTANT,self::combineNSSymbol($ns,$tvalue));
				}
			else throw new \Exception('Unrecognized token for constant definition'
				."(type=$tnum ($tname);value='$tvalue'). String expected");
			$state=self::ST_OUT;
			break;

		case self::ST_SKIPPING_BLOCK_STRING:
			if ($tnum==-1 && $tvalue=='"')
				$state=self::ST_SKIPPING_BLOCK_NOSTRING;
			break;

		case self::ST_SKIPPING_BLOCK_NOSTRING:
			if ($tnum==-1 || $tnum==T_CURLY_OPEN)
				{
				switch($tvalue)
					{
					case '"':
						$state=self::ST_SKIPPING_BLOCK_STRING;
						break;
					case '{':
						$block_level++;
						//TRACE echo "block_level=$block_level\n";
						break;
					case '}':
						$block_level--;
						if ($block_level==0) $state=self::ST_OUT;
						//TRACE echo "block_level=$block_level\n";
						break;
					}
				}
			break;

		case self::ST_DEFINE_FOUND:
			if ($tnum==-1 && $tvalue=='(') $state=self::ST_DEFINE_2;
			else throw new \Exception('Unrecognized token for constant definition'
				."(type=$tnum ($tname);value='$tvalue'). Expected '('");
			break;

		case self::ST_DEFINE_2:
			// Remember: T_STRING is incorrect in 'define' as constant name.
			// Current namespace is ignored in 'define' statement.
			if ($tnum==T_CONSTANT_ENCAPSED_STRING)
				{
				$schar=$tvalue{0};
				if ($schar=="'" || $schar=='"') $tvalue=trim($tvalue,$schar);
				$this->addSymbol(\Automap\Mgr::T_CONSTANT,$tvalue);
				}
			else throw new \Exception('Unrecognized token for constant definition'
				."(type=$tnum ($tname);value='$tvalue'). Expected quoted string constant");
			$state=self::ST_SKIPPING_TO_EOL;
			break;

		case self::ST_SKIPPING_TO_EOL:
			if ($tnum==-1 && $tvalue==';') $state=self::ST_OUT;
			break;
		}
	}
}

//---
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
