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
* The Automap_Creator class
*
* This class creates map files
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//============================================================================

// <PLAIN_FILE> //---------------
require_once(dirname(__FILE__).'/Automap.php');
// </PLAIN_FILE> //---------------

if (!class_exists('Automap_Creator',false)) 
{
//------------------------------------------

class Automap_Creator
{
const VERSION='1.1.0';
const MIN_VERSION='1.1.0'; // Minimum version of reader to understand the map

//---------

private $symbols=array();
private $options=array();
private $flags;

//---------
// Utilities (taken from PHK_Util)

private static function atomic_write($path,$data)
{
$tmpf=tempnam(dirname($path),'tmp_');

if (file_put_contents($tmpf,$data)!=strlen($data))
	throw new Exception($tmpf.": Cannot write");

// Windows does not support renaming to an existing file (looses atomicity)

if (PHK_Util::is_windows()) @unlink($path);

if (!rename($tmpf,$path))
	{
	unlink($tmpf);
	throw new Exception($path.': Cannot replace file');
	}
}

//---------
// Creates an empty object

public function __construct($flags=0)
{
$this->flags=$flags;
}

//---------

public function add_option($key,$value)
{
$this->options[$key]=$value;
}

//---------

public function set_options($options)
{
if (!is_array($options))
	throw new Exception("set_options: arg should be an array");

$this->options=$options;
}

//---------
// Can be called with type/symbol or with type=null/symbol=key
// Replace symbol even if previously defined

public function add_entry($type,$symbol,$value,$exclude_list=null)
{
$key=is_null($type) ? $symbol : Automap::key($type,$symbol);

if ((!is_null($exclude_list)) && (array_search($key,$exclude_list)!==false))
	return;

$this->symbols[$key]=$value;
}

//---------

public function symbol_count()
{
return count($this->symbols);
}

//---------
// Remove the entries contaning $value

private function cleanup($value)
{
foreach(array_keys($this->symbols,$value) as $key) unset($this->symbols[$key]);
}

//---------

public function get_mapfile($path,$flags=0)
{
$source_mnt=Automap::mount($path,null,null,$flags);
$source_map=Automap::instance($source_mnt);
$this->symbols=$source_map->symbols();
$this->options=$source_map->options();

Automap::umount($source_mnt);
}

//---------

public function serialize()
{
$data=serialize(array('map' => $this->symbols
	, 'options' => $this->options));

return Automap::MAGIC.' M'.str_pad(self::MIN_VERSION,12).' V'
	.str_pad(self::VERSION,12).' FS'.str_pad(strlen($data)+53,8).$data;
}

//---------

public function dump($path)
{
$data=$this->serialize();

self::atomic_write($path,$data);
}

//---------
// Register an extension in current map.
// $file=extension file (basename)

public function register_extension_file($file)
{
echo "INFO: Registering extension file: $file\n";

$value=Automap::key(Automap::F_EXTENSION,$file); // Key & value = same format
$this->cleanup($value);

$extension_list=get_loaded_extensions();

@dl($file);
$a=array_diff(get_loaded_extensions(),$extension_list);
if (($ext_name=array_pop($a))===NULL)
	throw new Exception($file.': Cannot load extension');

$ext=new ReflectionExtension($ext_name);

self::add_entry(Automap::T_EXTENSION,$ext_name,$value);

foreach($ext->getFunctions() as $func)
	self::add_entry(Automap::T_FUNCTION,$func->getName(),$value);

foreach(array_keys($ext->getConstants()) as $constant)
	self::add_entry(Automap::T_CONSTANT,$constant,$value);

foreach($ext->getClasses() as $class)
	{
	self::add_entry(Automap::T_CLASS,$class->getName(),$value);
	}
}

//---------
// Register every extension files in the extension directory
// We do several passes, as there are dependencies between extensions which
// must be loaded in a given order. We stop when a pass cannot load any file.

public function register_extension_dir()
{
$ext_dir=ini_get('extension_dir');
echo "INFO: Scanning directory : $ext_dir\n";

//-- Multiple passes because of possible dependencies
//-- Loop until everything is loaded or we cannot load anything more

$f_to_load=array();
$pattern='\.'.PHP_SHLIB_SUFFIX.'$';
foreach(scandir($ext_dir) as $ext_file)
	{
	if (is_dir($ext_dir.DIRECTORY_SEPARATOR.$ext_file)) continue;
	if (ereg($pattern,$ext_file)!==false) $f_to_load[]=$ext_file;
	}

while(true)
	{
	$f_failed=array();
	foreach($f_to_load as $key => $ext_file)
		{
		try { $this->register_extension_file($ext_file); }
		catch (Exception $e) { $f_failed[]=$ext_file; }
		}
	//-- If we could load everything or if we didn't load anything, break
	if ((count($f_failed)==0)||(count($f_failed)==count($f_to_load))) break;
	$f_to_load=$f_failed;
	}

if (count($f_failed))
	{
	$msg="These extensions were not registered because they could"
		." not be loaded :";
	foreach($f_failed as $file)	$msg.=" $file";
	trigger_error($msg,E_USER_WARNING);
	}
}

//---------
//-- This function extracts the function, class, and constant names out of a
//-- PHP script.

//-- States :

const ST_OUT=1;						// Upper level
const ST_FUNCTION_FOUND=Automap::T_FUNCTION; // Found 'function'. Looking for name
const ST_SKIPPING_BLOCK_NOSTRING=3; // In block, outside of string
const ST_SKIPPING_BLOCK_STRING=4;	// In block, in string
const ST_CLASS_FOUND=Automap::T_CLASS;	// Found 'class'. Looking for name
const ST_DEFINE_FOUND=6;			// Found 'define'. Looking for '('
const ST_DEFINE_2=7;				// Found '('. Looking for constant name
const ST_SKIPPING_TO_EOL=8;			// Got constant. Looking for EOL (';')

const AUTOMAP_COMMENT='// *<Automap>:([^ ]+)(.*)$';

//--

public function register_script($file,$automap_path)
{
//echo "INFO: Registering script $file as $automap_path\n";//TRACE

if (($buf=php_strip_whitespace($file))==='') return;

// Force relative path

$value=Automap::key(Automap::F_SCRIPT
	,trim(str_replace('\\','/',$automap_path),'/\\'));

$this->cleanup($value);

// Register explicit declarations
//Format:
//	<double-slash> <Automap>:declare <type> <value>
//	<double-slash> <Automap>:ignore <type> <value>
//	<double-slash> <Automap>:no-auto-index
//	<double-slash> <Automap>:skip-blocks

$skip_blocks=false;
$exclude_list=array();
$regs=false;
$line_nb=0;

try {
foreach(file($file) as $line)
	{
	$line_nb++;
	$line=trim($line);
	$lin=str_replace('	',' ',$line);	// Replace tabs with spaces
	if (ereg(self::AUTOMAP_COMMENT,$line,$regs)===false) continue;

	if ($regs[1]=='no-auto-index') return;

	if ($regs[1]=='skip-blocks')
		{
		$skip_blocks=true;
		continue;
		}
	$type=strtolower(strtok($regs[2],' '));
	$name=strtok(' ');
	if ($type===false || $name===false) throw new Exception('Needs 2 args');
	$type_letter=Automap::string_to_type($type);
	$key=Automap::key($type_letter,$name);
	switch($regs[1])
		{
		case 'declare': // Add entry, even if set to be 'ignored'.
			$this->add_entry(null,$key,$value);
			break;

		case 'ignore': // Ignore this symbol in autoindex stage.
			$exclude_list[]=$key;
			break;

		default:
			throw new Exception($regs[1].': Invalid Automap command');
		}
	}
} catch (Exception $e)
	{ throw new Exception("$file (line $line_nb): ".$e->getMessage()); }

//-- Auto index

$block_level=0;
$state=self::ST_OUT;

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

	if ($tnum==T_WHITESPACE || $tnum==T_COMMENT) continue;

	//echo "$tname <$tvalue>\n";//TRACE
	switch($state)
		{
		case self::ST_OUT:
			switch($tnum)
				{
				case T_FUNCTION:
					$state=self::ST_FUNCTION_FOUND; break;
				case T_CLASS:
				case T_INTERFACE:
					$state=self::ST_CLASS_FOUND; break;
				case T_STRING:
					if ($tvalue=='define') $state=self::ST_DEFINE_FOUND;
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

		case self::ST_FUNCTION_FOUND:
		case self::ST_CLASS_FOUND:
			if ($tnum==-1 && $tvalue=='&') break; //-- Function returning ref
			if ($tnum==T_STRING)
				$this->add_entry($state,$tvalue,$value,$exclude_list);
			else trigger_error($file.": Cannot get name (type=$tname;value=$tvalue)"					,E_USER_WARNING);
			$state=self::ST_SKIPPING_BLOCK_NOSTRING;
			$block_level=0;
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
			else
				{
				trigger_error("Unrecognized token for constant definition (type=$tnum;value=$tvalue). Waited for '(' string"
					,E_USER_WARNING);
				$state=self::ST_SKIPPING_TO_EOL;
				}
			break;

		case self::ST_DEFINE_2:
			// Accept T_STRING, even if it is incorrect
			if ($tnum==T_CONSTANT_ENCAPSED_STRING || $tnum==T_STRING)
				{
				$schar=$tvalue{0};
				if ($schar=="'" || $schar=='"') $tvalue=trim($tvalue,$schar);
				$this->add_entry(Automap::T_CONSTANT,$tvalue,$value,$exclude_list);
				}
			else trigger_error('Unrecognized token for constant definition '
				."(type=$tname;value=$tvalue). Waited for string constant"
				,E_USER_WARNING);
			$state=self::ST_SKIPPING_TO_EOL;
			break;

		case self::ST_SKIPPING_TO_EOL:
			if ($tnum==-1 && $tvalue==';') $state=self::ST_OUT;
			break;
		}
	}
}

//---------
// Here, we must use 'Automap' and not 'parent' because the package always
// registers its map via Automap::mount()

public function register_package($file,$automap_path)
{
$value=Automap::key(Automap::F_PACKAGE,trim($automap_path,'/\\'));

// We use the same mount point for packages and automaps

$mnt=require($file);
if (Automap::is_mounted($mnt)) // If package has an automap
	{
	foreach(array_keys(Automap::instance($mnt)->symbols()) as $key)
		{
		//var_dump($key);//TRACE
		$this->add_entry(null,$key,$value);
		}
	}
else echo "No automap found in package\n";
}

//---------

public function import($path)
{
$fp=is_null($path) ? STDIN : fopen($path,'r');

while(($line=fgets($fp))!==false)
	{
	if (($line=trim($line))==='') continue;
	list($key,$value)=explode(' ',$line,2);
	$this->add_entry(null,$key,$value);
	}
if (!is_null($path)) fclose($fp);
}

//---------
} // End of class Automap_Creator
//===========================================================================
} // End of class_exists('Automap_Creator')
//===========================================================================
?>
