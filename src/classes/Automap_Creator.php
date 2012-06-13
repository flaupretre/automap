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

// For PHP version < 5.3.0

if (!defined('T_NAMESPACE')) define('T_NAMESPACE',-2);
if (!defined('T_NS_SEPARATOR'))	define('T_NS_SEPARATOR',-3);
if (!defined('T_CONST'))	define('T_CONST',-4);
if (!defined('T_TRAIT'))	define('T_TRAIT',-5);

if (!class_exists('Automap_Creator',false)) 
{
//------------------------------------------

class Automap_Creator
{
const VERSION='2.1.0';		// Version set into the maps I produce
const MIN_VERSION='2.0.0'; // Minimum version of runtime to understand the maps I produce

//---------

private $symbols=array();	// array($key => array('T' => <symbol type>
							// , 'n' => <case-sensitive symbol name>
							// , 't' => <target type>, 'p' => <target path>))
private $options=array();
private $flags;
private $path=null;

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

public function __construct($path=null,$flags=0)
{
$this->flags=$flags;

$this->path=$path;
if ((!is_null($path))&&is_file($path)) $this->read_map_file($path);
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

private function add_entry($va)
{
$key=Automap::key($va['T'],$va['n']);
if (isset($this->symbols[$key]))
	{
	$entry=$this->symbols[$key];
	echo "** Warning: Symbol multiply defined: ".Automap::type_to_string($va['T'])
		.' '.$va['n']."\n	Previous location: ".Automap::type_to_string($entry['t'])
		.' '.$entry['p']."\n	New location (ignored): ".Automap::type_to_string($va['t'])
		.' '.$va['p']."\n";
	}
else $this->symbols[$key]=$va;
}

//---------

private function add_ts_entry($stype,$sname,$va)
{
$va['T']=$stype;
$va['n']=$sname;
$this->add_entry($va);
}

//---------

public function symbol_count()
{
return count($this->symbols);
}

//---------

private static function mk_varray($ftype,$fpath)
{
return array('t' => $ftype, 'p' => $fpath);
}

//---------
// Remove the entries contaning $value

private function cleanup($va)
{
$ftype=$va['t'];
$fpath=$va['p'];
foreach(array_keys($this->symbols) as $key)
	{
	if (($this->symbols[$key]['t']===$ftype)&&($this->symbols[$key]['p']===$fpath))
		unset($this->symbols[$key]);
	}
}

//---------
// Ensures each key is present only once

public function serialize()
{
$bmap=array();
foreach($this->symbols as $key => $va)
	{
	$bmap[]=$va['T'].$va['t'].$va['n'].'|'.$va['p'];
	}
$data=serialize(array('map' => $bmap, 'options' => $this->options));

return Automap::MAGIC.' M'.str_pad(self::MIN_VERSION,12).' V'
	.str_pad(self::VERSION,12).' FS'.str_pad(strlen($data)+53,8).$data;
}

//---------

public function dump($path=null)
{
if (is_null($path)) $path=$this->path;
if (is_null($path)) throw new Exception('No path provided');

$data=$this->serialize();

self::atomic_write($path,$data);
}

//---------
// Register an extension in current map.
// $file=extension file (basename)

public function register_extension_file($file)
{
echo "INFO: Registering extension file: $file\n";

$va=self::mk_varray(Automap::F_EXTENSION,$file);
$this->cleanup($va);

$extension_list=get_loaded_extensions();

@dl($file);
$a=array_diff(get_loaded_extensions(),$extension_list);
if (($ext_name=array_pop($a))===NULL)
	throw new Exception($file.': Cannot load extension');

$ext=new ReflectionExtension($ext_name);

self::add_ts_entry(Automap::T_EXTENSION,$ext_name,$va);

foreach($ext->getFunctions() as $func)
	self::add_ts_entry(Automap::T_FUNCTION,$func->getName(),$va);

foreach(array_keys($ext->getConstants()) as $constant)
	self::add_ts_entry(Automap::T_CONSTANT,$constant,$va);

foreach($ext->getClasses() as $class)
	{
	self::add_ts_entry(Automap::T_CLASS,$class->getName(),$va);
	}
	
if (method_exists($ext,'getInterfaces')) // Compatibility
	{
	foreach($ext->getInterfaces() as $interface)
		{
		self::add_ts_entry(Automap::T_CLASS,$interface->getName(),$va);
		}
	}

if (method_exists($ext,'getTraits')) // Compatibility
	{
	foreach($ext->getTraits() as $trait)
		{
		self::add_ts_entry(Automap::T_CLASS,$trait->getName(),$va);
		}
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
$pattern='/\.'.PHP_SHLIB_SUFFIX.'$/';
foreach(scandir($ext_dir) as $ext_file)
	{
	if (is_dir($ext_dir.DIRECTORY_SEPARATOR.$ext_file)) continue;
	if (preg_match($pattern,$ext_file)) $f_to_load[]=$ext_file;
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

//--

private static function combine_ns_symbol($ns,$symbol)
{
$ns=trim($ns,'\\');
return $ns.(($ns==='') ? '' : '\\').$symbol;
}

//---------

private static function normalize_rpath($rpath)
{
return trim(str_replace('\\','/',$rpath),'/\\');
}

//---------

private static function combine_path($dpath,$fpath)
{
if ($dpath==='.') return $fpath;
return $dpath.(($dpath==='') ? '' : '/').$fpath;
}

//---------

public function register_script($fpath,$rpath)
{
//echo "INFO: Registering script $fpath as $rpath\n";//TRACE

if (($buf=php_strip_whitespace($fpath))==='') return;

// Force relative path

$va=self::mk_varray(Automap::F_SCRIPT,self::normalize_rpath($rpath));
$this->cleanup($va);

$symbols=array();

try
	{
	$symbols=self::get_script_symbols(file_get_contents($fpath),$fpath);
	}
catch (Exception $e)
	{ throw new Exception("File $fpath ".$e->getMessage()); }

foreach($symbols as $sa)
	{
	$this->add_ts_entry($sa[0],$sa[1],$va);
	}
}

//----

private static function add_symbol(&$a,$type,$symbol,$exclude_list)
{
foreach($exclude_list as $e)
	{
	if (($e[0]===$type)&&($e[1]===$symbol)) return;
	}

$a[]=array($type,$symbol);
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
const ST_NAMESPACE_FOUND=9;			// Found 'namespace'. Looking for <whitespace>
const ST_NAMESPACE_2=10;			// Found 'namespace' and <whitespace>. Looking for name
const ST_CONST_FOUND=11;			// Found 'const'. Looking for name

const AUTOMAP_COMMENT=',// *<Automap>:(\S+)(.*)$,';

//----

public static function get_script_symbols($buf,$path=null)
{
$buf=str_replace("\r",'',$buf);
$prefix=(is_null($path) ? '' : $path.' : ');

$symbols=array();
$exclude_list=array();

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
foreach(explode("\n",$buf) as $line)
	{
	$line_nb++;
	$line=trim($line);
	$lin=str_replace('	',' ',$line);	// Replace tabs with spaces
	if (!preg_match(self::AUTOMAP_COMMENT,$line,$regs)) continue;

	if ($regs[1]=='no-auto-index') return array();

	if ($regs[1]=='skip-blocks')
		{
		$skip_blocks=true;
		continue;
		}
	$type_string=strtolower(strtok($regs[2],' '));
	$name=strtok(' ');
	if ($type_string===false || $name===false) throw new Exception('Needs 2 args');
	$type=Automap::string_to_type($type_string);
	switch($regs[1])
		{
		case 'declare': // Add entry, even if set to be 'ignored'.
			self::add_symbol($symbols,$type,$name,array());
			break;

		case 'ignore': // Ignore this symbol in autoindex stage.
			$exclude_list[]=array($type,$name);
			break;

		default:
			throw new Exception($regs[1].': Invalid Automap command');
		}
	}
} catch (Exception $e)
	{ throw new Exception("(line $line_nb): ".$e->getMessage()); }

//-- Auto index

$block_level=0;
$state=self::ST_OUT;
$name='';
$ns='';

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
		
	if ($tnum==T_COMMENT) continue;
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
			if (($state==self::ST_FUNCTION_FOUND)&&($tnum==-1)&&($tvalue=='('))
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
				self::add_symbol($symbols,$state,self::combine_ns_symbol($ns,$tvalue),$exclude_list);
				}
			else
				{
				trigger_error($prefix.'Unrecognized token for class/function definition'
				."(type=$tnum ($tname);value='$tvalue'). String expected"
					,E_USER_WARNING);
				$state=self::ST_OUT;
				break;
				}
			$state=self::ST_SKIPPING_BLOCK_NOSTRING;
			$block_level=0;
			break;

		case self::ST_CONST_FOUND:
			if ($tnum==T_STRING)
				{
				self::add_symbol($symbols,Automap::T_CONSTANT,self::combine_ns_symbol($ns,$tvalue)
					,$exclude_list);
				}
			else trigger_error($prefix.'Unrecognized token for constant definition '
				."(type=$tnum ($tname);value='$tvalue'). String expected"
					,E_USER_WARNING);
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
			else
				{
				trigger_error($prefix.'Unrecognized token for constant definition '
					."(type=$tnum ($tname);value='$tvalue'). Expected '('"
					,E_USER_WARNING);
				$state=self::ST_SKIPPING_TO_EOL;
				}
			break;

		case self::ST_DEFINE_2:
			// Remember: T_STRING is incorrect in 'define' as constant name.
			// Current namespace is ignored in 'define' statement.
			if ($tnum==T_CONSTANT_ENCAPSED_STRING)
				{
				$schar=$tvalue{0};
				if ($schar=="'" || $schar=='"') $tvalue=trim($tvalue,$schar);
				self::add_symbol($symbols,Automap::T_CONSTANT,$tvalue,$exclude_list);
				}
			else trigger_error($prefix.'Unrecognized token for constant definition '
				."(type=$tnum ($tname);value='$tvalue'). Expected quoted string constant"
				,E_USER_WARNING);
			$state=self::ST_SKIPPING_TO_EOL;
			break;

		case self::ST_SKIPPING_TO_EOL:
			if ($tnum==-1 && $tvalue==';') $state=self::ST_OUT;
			break;
		}
	}
return $symbols;
}

//---------

private function is_mapfile($path)
{
return (substr(file_get_contents($path),0,strlen(Automap::MAGIC))===Automap::MAGIC);
}

//---------
// Ignore map files (must be merged explicitely when desired)

public function register_path($fpath,$rpath)
{
switch($type=filetype($fpath))
	{
	case 'dir':
		foreach(PHK_UTIL::scandir($fpath) as $entry)
			{
			$this->register_path(PHK_Util::combine_path($fpath,$entry)
				,PHK_Util::combine_path($rpath,$entry));
			}
		break;

	case 'file':
		if (PHK::file_is_package($fpath))
			{
			$this->register_phk($fpath,$rpath);
			}
		else
			{
			if (!self::is_mapfile($fpath)) $this->register_script($fpath,$rpath);
			}
		break;

	default:
		echo "Ignoring file $fpath (type=$type)\n";
	}
}

//---------

public function read_map_file($fpath)
{
$mnt=Automap::mount($fpath);
$map=Automap::instance($mnt);
$this->options=$map->options();
$this->symbols=array();
$this->merge_map_symbols($map);
Automap::umount($mnt);
}

//---------

public function merge_map_file($fpath,$rpath)
{
$mnt=Automap::mount($fpath);
$map=Automap::instance($mnt);
$this->merge_map_symbols($map,$rpath);
Automap::umount($mnt);
}


//---------

public function merge_map_symbols($map,$rpath='.')
{
foreach($map->symbols() as $va)
	{
	$this->add_ts_entry($va['stype'],$va['symbol'],self::mk_varray($va['ptype']
		,self::combine_path($rpath,$va['rpath'])));
	}
}

//---------

public function register_phk($fpath,$rpath)
{
$rpath=self::normalize_rpath($rpath);

$this->cleanup(self::mk_varray(Automap::F_PACKAGE,$rpath));

// We use the same mount point for packages and automaps

$mnt=PHK_Mgr::mount($fpath,PHK::F_NO_MOUNT_SCRIPT);

if (Automap::is_mounted($mnt)) // If package has an automap
	{
	foreach(Automap::instance($mnt)->symbols() as $sym)
		{
		//var_dump($key);//TRACE
		$va=array();
		$va['t']=Automap::F_PACKAGE;
		$va['p']=$rpath;
		$this->add_ts_entry($sym['stype'],$sym['symbol'],$va);
		}
	}
}

//---------

public function import($path)
{
$fp=is_null($path) ? STDIN : fopen($path,'r');

while(($line=fgets($fp))!==false)
	{
	if (($line=trim($line))==='') continue;
	list($stype,$sname,$ftype,$fname)=explode(' ',$line);
	$va=self::mk_varray($ftype,$fname);
	$this->add_ts_entry($stype,$sname,$va);
	}
if (!is_null($path)) fclose($fp);
}

//---------
} // End of class Automap_Creator
//===========================================================================
} // End of class_exists('Automap_Creator')
//===========================================================================
?>
