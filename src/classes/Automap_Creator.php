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

if (!class_exists('Automap_Creator',false)) 
{
//------------------------------------------

class Automap_Creator
{
const VERSION='3.0.0';		// Version set into the maps I produce
const MIN_VERSION='3.0.0'; // Minimum version of runtime to understand the maps I produce

//---------

private $symbols=array();	// array($key => array('T' => <symbol type>
							// , 'n' => <case-sensitive symbol name>
							// , 't' => <target type>, 'p' => <target path>))
private $options=array();

private $php_file_ext=array('php','inc','hh');

private $parser; // Must implement Automap_Parser_Interface

//---------

public function __construct($parser=null)
{
$this->set_parser($parser);
}

//---------

public function set_parser($parser=null)
{
if (is_null($parser)) $parser=new Automap_Parser();
$this->parser=$parser;
}

//---------

public function option($opt)
{
return (isset($this->options[$opt]) ? $this->options[$opt] : null);
}

//---------

public function set_option($option,$value)
{
PHO_Display::trace("Setting option $option=$value");

$this->options[$option]=$value;
}

//---------

public function unset_option($option)
{
PHO_Display::trace("Unsetting option $option");

if (isset($this->options[$option])) unset($this->options[$option]);
}

//---------
/**
* Set the list of file suffixes recognized as PHP source scripts
*
* Default list is 'php, 'inc, 'hh'.
*
* @param array|string If array, replace the list, otherwise add a suffix to the list
* @return null
*/

public function set_php_file_ext($a)
{
if (is_array($a)) $this->php_file_ext=$a;
else $this->php_file_ext[]=$a;
}

//---------

private function add_entry($va)
{
$key=Automap::key($va['T'],$va['n']);

// Filter namespace if filter specified

if (isset($va['f']))
	{
	$ns_list=$va['f'];
	if (is_string($ns_list)) $ns_list=array($ns_list);
	$ns=Automap_Map::ns_key($va['n']);
	$ok=false;
	foreach($ns_list as $item)
		{
		$item=trim($item,'\\');
		if ((($item=='')&&($ns==''))||($item!='')&&(strpos($ns.'\\',$item.'\\')===0))
			{
			$ok=true;
			break;
			}
		}
	if (!$ok)
		{
		PHO_Display::debug("$key rejected by namespace filter");
		return;
		}
	}

// Add symbol to map if no conflict

PHO_Display::debug("Adding symbol (key=<$key>, name=".$va['n']
	.", target=".$va['p'].' ('.$va['t'].')');

if (isset($this->symbols[$key]))
	{
	$entry=$this->symbols[$key];
	// If same target, it's OK
	if (($entry['t']!=$va['t'])||($entry['p']!=$va['p']))
		{
		echo "** Warning: Symbol multiply defined: "
			.Automap::type_to_string($va['T'])
			.' '.$va['n']."\n	Previous location (kept): "
			.Automap::type_to_string($entry['t'])
			.' '.$entry['p']."\n	New location (discarded): "
			.Automap::type_to_string($va['t'])
			.' '.$va['p']."\n";
		}
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
// Build an array containing only target information

private static function mk_varray($ftype,$fpath,$ns_filter=null)
{
$a=array('t' => $ftype, 'p' => $fpath);
if (!is_null($ns_filter)) $a['f']=$ns_filter;
return $a;
}

//---------

public function add_symbol($stype,$sname,$ftype,$fpath)
{
$va=self::mk_varray($ftype,$fpath);
$this->add_ts_entry($stype,$sname,$va);
}

//---------
// Remove the entries matching a given target

private function unregister_target($va)
{
$type=$va['t'];
$path=$va['p'];
PHO_Display::debug("Unregistering path (type=$type, path=$path)");

foreach(array_keys($this->symbols) as $key)
	{
	if (($this->symbols[$key]['t']===$type)&&($this->symbols[$key]['p']===$path))
		{
		PHO_Display::debug("Removing $key from symbol table");
		unset($this->symbols[$key]);
		}
	}
}

//---------
// Using adler32 as it is supposed to be the fastest algo. That's more than
// enough for a CRC check.
// Symbols are supposed to be normalized (no leading/trailing '\').

public function serialize()
{
//-- Store symbols in namespace slots

$slots=array();
foreach($this->symbols as $key => $va)
	{
	$target=$va['t'].$va['p'];
	$ns=Automap_Map::ns_key($va['n']);
	if (!array_key_exists($ns,$slots)) $slots[$ns]=array();
	$slots[$ns][$key]=$target;
	}

//-- Serialize

foreach(array_keys($slots) as $ns)
	{
	$slots[$ns]=serialize($slots[$ns]);
	}

$data=serialize(array('map' => $slots, 'options' => $this->options));

//-- Dump to file

$buf=Automap::MAGIC
	.str_pad(self::MIN_VERSION,12)
	.str_pad(self::VERSION,12)
	.str_pad(strlen($data)+70,8)
	.'00000000'
	.str_pad(count($this->symbols),8)
	.str_pad(strlen($data),8)
	.$data;

return substr_replace($buf,hash('adler32',$buf),46,8); // Insert CRC
}

//---------

public function save($path)
{
if (is_null($path)) throw new Exception('No path provided');

$data=$this->serialize();

PHO_Display::trace("$path: Writing map file");
PHO_File::atomic_write($path,$data);
}

//---------
// Register an extension in current map.
// $file=extension file (basename)

public function register_extension_file($file)
{
PHO_Display::trace("Registering extension : $file");

$va=self::mk_varray(Automap::F_EXTENSION,$file);
$this->unregister_target($va);

foreach($this->parser->parse_extension($file) as $sym)
	{
	$this->add_ts_entry($sym['type'],$sym['name'],$va);
	}
}

//---------
// Register every extension files in the extension directory
// We do several passes, as there are dependencies between extensions which
// must be loaded in a given order. We stop when a pass cannot load any file.

public function register_extension_dir()
{
$ext_dir=ini_get('extension_dir');
PHO_Display::trace("Scanning extensions directory ($ext_dir)\n");

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
	foreach($f_failed as $file)
		PHO_Display::warning("$file: This extension was not registered (load failed)");
	}
}

//---------------------------------
/**
* Normalize a destination path
*
* 1. Replace backslashes with forward slashes.
* 2. Remove trailing slashes
*
* @param string $rpath the path to normalize
* @return string the normalized path
*/

private static function normalize_path($path)
{
$path=rtrim(str_replace('\\','/',$path),'/');
if ($path=='') $path='/';
return $path;
}

//---------

public function register_script($fpath,$rpath,$ns_filter=null)
{
PHO_Display::trace("Registering script $fpath as $rpath");

// Force relative path

$va=self::mk_varray(Automap::F_SCRIPT,self::normalize_path($rpath),$ns_filter);
$this->unregister_target($va);

foreach($this->parser->parse_script_file($fpath) as $sym)
	{
	$this->add_ts_entry($sym['type'],$sym['name'],$va);
	}
}

//---------

private function is_mapfile($path)
{
return (substr(file_get_contents($path),0,strlen(Automap::MAGIC))===Automap::MAGIC);
}

//---------
/**
* Recursively scan a path and records symbols
*
* Scan retains PHP source files and phk packages only (based on file suffix)
*
* Only dirs and regular files are considered. Other types are ignored.
*
* @param string $fpath Path to register
* @param string $rpath Path to register to in map for $fpath
* @param string|array|null $ns_filter
*			List of authorized namespaces (empty string means no namespace)
*			If null, no filtering.
* @param string|null $file_pattern
*			File path preg pattern (File paths not matching this pattern are ignored)
*/

public function register_path($fpath,$rpath,$ns_filter=null,$file_pattern=null)
{
PHO_Display::trace("Registering path <$fpath> as <$rpath>");

switch($type=filetype($fpath))
	{
	case 'dir':
		foreach(PHO_File::scandir($fpath) as $entry)
			{
			$this->register_path($fpath.'/'.$entry,$rpath.'/'.$entry,$ns_filter);
			}
		break;

	case 'file':
		if ((!is_null($file_pattern)) && (!preg_match($file_pattern, $fpath))) continue;
		$suffix=strtolower(PHO_File::file_suffix($fpath));
		if ($suffix=='phk')
			$this->register_phk($fpath,$rpath);
		elseif (array_search($suffix,$this->php_file_ext)!==false)
			$this->register_script($fpath,$rpath,$ns_filter);
		else
			PHO_Display::trace("Ignoring file $fpath (not a PHP script)");
		break;
	}
}

//---------

public function read_map_file($fpath)
{
PHO_Display::trace("Reading map file ($fpath)");

$id=Automap::load($fpath,Automap::NO_AUTOLOAD);
$map=Automap::map($id);
$this->options=$map->options();
$this->symbols=array();
$this->merge_map_symbols($map);
Automap::unload($id);
}

//---------
/**
* Merge an existing map file into the current map
*
* Import symbols only. Options are ignored (including base path).
*
* @param string $fpath Path of the map to merge (input)
* @param Relative path to prepend to map target paths
* @return null
*/

public function merge_map_file($fpath,$rpath)
{
PHO_Display::debug("Merging map file from $fpath (rpath=$rpath)");

$map=new Automap_Map($fpath);
$this->merge_map_symbols($map,$rpath);
}

//---------

public function merge_map_symbols($map,$rpath='.')
{
foreach($map->symbols() as $va)
	{
	$va['rpath']=PHO_FIle::combine_path($rpath,$va['rpath']);
	$this->add_entry($va);
	}
}

//---------
// Register a PHK package

public function register_phk($fpath,$rpath)
{
PHO_Display::trace("Registering PHK package $fpath as $rpath");

$rpath=self::normalize_path($rpath);
PHO_Display::debug("Registering PHK package (path=$fpath, rpath=$rpath)");
$va=self::mk_varray(Automap::F_PACKAGE,$rpath);
$this->unregister_target($va);

$mnt=PHK_Mgr::mount($fpath,PHK::NO_MOUNT_SCRIPT);
$pkg=PHK_Mgr::instance($mnt);
$id=$pkg->automap_id();
if ($id) // If package has an automap
	{
	foreach(Automap::map($id)->symbols() as $sym)
		$this->add_ts_entry($sym['stype'],$sym['symbol'],$va);
	}
}

//---------

public function import($path=null)
{
if (is_null($path)) $path="php://stdin";

PHO_Display::trace("Importing map from $path");

$fp=fopen($path,'r');
if (!$fp) throw new Exception("$path: Cannot open for reading");

while(($line=fgets($fp))!==false)
	{
	if (($line=trim($line))==='') continue;
	list($stype,$sname,$ftype,$fname)=explode('|',$line);
	$va=self::mk_varray($ftype,$fname);
	$this->add_ts_entry($stype,$sname,$va);
	}
fclose($fp);
}

//---------
} // End of class Automap_Creator
//===========================================================================
} // End of class_exists('Automap_Creator')
//===========================================================================
?>
