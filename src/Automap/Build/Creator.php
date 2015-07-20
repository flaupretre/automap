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

//=============================================================================
/**
* This class creates a map file
*
* Usage:
*	$map=new \Automap\Build\Creator();
*	...[populate using the different methods]...
*	$map->save(<path>);
*
* API status: Public
* Included in the PHK PHP runtime: No
* Implemented in the extension: No
*///==========================================================================

namespace Automap\Build {

if (!class_exists('Automap\Build\Creator',false)) {

class Creator
{
const VERSION='3.1.0';		// Version set into the maps I produce
const MIN_RUNTIME_VERSION='3.1.0'; // Minimum version of runtime able to understand the maps I produce

//---------

private $symbols;	// array($key => array('T' => <symbol type>
					// , 'n' => <case-sensitive symbol name>
					// , 't' => <target type>, 'p' => <target path>))
private $tsymbols; // array(composite target => array(symbol keys))

private $options=array();

private $php_file_ext=array('php','inc','hh');

private $parser; // Must implement \Automap\Build\ParserInterface

//---------

public function __construct($parser=null)
{
	$this->resetSymbolTable();
	$this->setParser($parser);
}

//---------

public function resetSymbolTable()
{
	$this->symbols=array();
	$this->tsymbols=array();
}

//---------

public function setParser($parser=null)
{
	if (is_null($parser)) $parser=new Parser();
	$this->parser=$parser;
}

//---------

public function option($opt)
{
	return (isset($this->options[$opt]) ? $this->options[$opt] : null);
}

//---------

public function setOption($option,$value)
{
	\Phool\Display::trace("Setting option $option=$value");

	$this->options[$option]=$value;
}

//---------

public function unsetOption($option)
{
	\Phool\Display::trace("Unsetting option $option");

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

public function setPhpFileExt($a)
{
	if (is_array($a)) $this->php_file_ext=$a;
	else $this->php_file_ext[]=$a;
}

//---------

private function addEntry($va)
{
	$key=\Automap\Map::key($va['T'],$va['n']);

	// Filter namespace if filter specified

	if (isset($va['f'])) {
		$ns_list=$va['f'];
		if (is_string($ns_list)) $ns_list=array($ns_list);
		$ns=\Automap\Map::nsKey($va['n']);
		$ok=false;
		foreach($ns_list as $item) {
			$item=trim($item,'\\');
			if ((($item=='')&&($ns==''))||($item!='')&&(strpos($ns.'\\',$item.'\\')===0)) {
				$ok=true;
				break;
			}
		}
		if (!$ok) {
			\Phool\Display::debug("$key rejected by namespace filter");
			return;
		}
	}

	// Add symbol to map if no conflict

	\Phool\Display::debug("Adding symbol (key=<$key>, name=".$va['n']
		.", target=".$va['p'].' ('.$va['t'].')');

	if (isset($this->symbols[$key])) {
		$entry=$this->symbols[$key];
		// If same target, it's OK
		if (($entry['t']!=$va['t'])||($entry['p']!=$va['p'])) {
			echo "** Warning: Symbol multiply defined: "
				.\Automap\Mgr::typeToString($va['T'])
				.' '.$va['n']."\n	Previous location (kept): "
				.\Automap\Mgr::typeToString($entry['t'])
				.' '.$entry['p']."\n	New location (discarded): "
				.\Automap\Mgr::typeToString($va['t'])
				.' '.$va['p']."\n";
		}
	} else {
		$this->symbols[$key]=$va;
		$target=$va['t'].$va['p'];
		if (!array_key_exists($target,$this->tsymbols)) $this->tsymbols[$target]=array();
		$this->tsymbols[$target][]=$key;
	}

	\Phool\Display::debug('Symbol count: '.count($this->symbols)
		.' - Mem used: '.(memory_get_usage(true)/(1024*1024)).' Mb');//TRACE
}

//---------

private function addTSEntry($stype,$sname,$va)
{
	$va['T']=$stype;
	$va['n']=$sname;
	$this->addEntry($va);
}

//---------

public function symbolCount()
{
	return count($this->symbols);
}

//---------
// Build an array containing only target information

private static function mkVarray($ftype,$fpath,$ns_filter=null)
{
	$a=array('t' => $ftype, 'p' => $fpath);
	if (!is_null($ns_filter)) $a['f']=$ns_filter;
	return $a;
}

//---------

public function addSymbol($stype,$sname,$ftype,$fpath)
{
	$va=self::mkVarray($ftype,$fpath);
	$this->addTSEntry($stype,$sname,$va);
}

//---------
// Remove the entries matching a given target

private function unregisterTarget($va)
{
	$target=$va['t'].$va['p'];
	\Phool\Display::debug("Unregistering target ($target)");

	if (array_key_exists($target,$this->tsymbols)) {
		foreach($this->tsymbols[$target] as $key) {
			\Phool\Display::debug("Removing $key from symbol table");
			unset($this->symbols[$key]);
		}
		unset($this->tsymbols[$target]);
	}
}

//---------
// Using adler32 as it is supposed to be the fastest algo. That's more than
// enough for a CRC check.
// Symbols are supposed to be normalized (no leading/trailing '\').

public function serialize()
{
	$data="<?php\n"
		."// ".\Automap\Map::MAGIC
		.' '.str_pad(self::MIN_RUNTIME_VERSION,12)
		.' '.str_pad(self::VERSION,12)
		."\n// ".str_pad(count($this->symbols),8)." 00000000 00000000"
		."\n//--- This file was generated by Automap\Build\Creator ---"
		."\n//--------- It must NEVER be modified manually -----------"
		."\n\n if (!isset(\$__bp)) \$__bp =";
	$bp=$this->option('base_path');
	if (is_null($bp)||($bp==='.')) $bp='';
	if (self::isAbsolutePath($bp)) $data .= "'$bp";
	else $data .= "__DIR__.'/$bp";
	if ($bp!=='') $data .= '/';
	$data .="';\nreturn array('options' => ".var_export($this->options,true)
		."\n,'info' => array('abs_bp'=>\$__bp)"
		."\n,'map' => array(\n";

	ksort($this->symbols);
	$first=true;
	foreach($this->symbols as $va) {
		if ($first) $first=false;
		else $data .= "\n,";

		$data .= '\''.addslashes($va['n']).$va['T'].'\'=>';
		if ($va['t']!=\Automap\Mgr::F_EXTENSION) $data .= '$__bp.';
		$data .= '\''.$va['p'].$va['t'].'\'';
	}

	$data .= "));\n";

	$data=substr_replace($data,str_pad(strlen($data),8),62,8); // Insert size
	$data=substr_replace($data,hash('adler32',$data),71,8); // Insert CRC

	return $data;
}

//---------

public function save($path)
{
	if (is_null($path)) throw new \Exception('No path provided');

	\Phool\Display::trace("$path: Writing map file");
	\Phool\File::atomicWrite($path,$this->serialize());
}

//---------
// Register an extension in current map.
// $file=extension file (basename)

public function registerExtensionFile($file)
{
	\Phool\Display::trace("Registering extension : $file");

	$va=self::mkVarray(\Automap\Mgr::F_EXTENSION,$file);
	$this->unregisterTarget($va);

	foreach($this->parser->parseExtension($file) as $sym) {
		$this->addTSEntry($sym['type'],$sym['name'],$va);
	}
}

//---------
// Register every extension files in the extension directory
// We do several passes, as there are dependencies between extensions which
// must be loaded in a given order. We stop when a pass cannot load any file.

public function registerExtensionDir()
{
	$ext_dir=ini_get('extension_dir');
	\Phool\Display::trace("Scanning extensions directory ($ext_dir)\n");

	//-- Multiple passes because of possible dependencies
	//-- Loop until everything is loaded or we cannot load anything more

	$f_to_load=array();
	$pattern='/\.'.PHP_SHLIB_SUFFIX.'$/';
	foreach(scandir($ext_dir) as $ext_file) {
		if (is_dir($ext_dir.DIRECTORY_SEPARATOR.$ext_file)) continue;
		if (preg_match($pattern,$ext_file)) $f_to_load[]=$ext_file;
	}

	while(true) {
		$f_failed=array();
		foreach($f_to_load as $key => $ext_file) {
			try {
				$this->registerExtensionFile($ext_file);
			} catch (\Exception $e) {
				$f_failed[]=$ext_file;
			}
		}
		//-- If we could load everything or if we didn't load anything, break
		if ((count($f_failed)==0)||(count($f_failed)==count($f_to_load))) break;
		$f_to_load=$f_failed;
	}

	if (count($f_failed)) {
		foreach($f_failed as $file) {
			\Phool\Display::warning("$file: This extension was not registered (load failed)");
		}
	}
}

/**
* Determines if a given path is absolute or relative
*
* @param string $path The path to check
* @return bool True if the path is absolute, false if relative
*/

private static function isAbsolutePath($path)
{
	return ((strpos($path,':')!==false)
		||(strpos($path,'/')===0)
		||(strpos($path,'\\')===0));
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

private static function normalizePath($path)
{
	$path=rtrim(str_replace('\\','/',$path),'/');
	if ($path=='') $path='/';
	return $path;
}

//---------

public function registerScriptFile($fpath,$rpath,$ns_filter=null)
{
	\Phool\Display::trace("Registering script $fpath as $rpath");

	// Force relative path

	$va=self::mkVarray(\Automap\Mgr::F_SCRIPT,self::normalizePath($rpath),$ns_filter);
	$this->unregisterTarget($va);

	foreach($this->parser->parseScriptFile($fpath) as $sym) {
		$this->addTSEntry($sym['type'],$sym['name'],$va);
	}
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

public function registerPath($fpath,$rpath,$ns_filter=null,$file_pattern=null)
{
	\Phool\Display::trace("Registering path <$fpath> as <$rpath>");

	switch($type=filetype($fpath)) {
		case 'dir':
			foreach(\Phool\File::scandir($fpath) as $entry)
				{
				$this->registerPath($fpath.'/'.$entry,$rpath.'/'.$entry,$ns_filter);
				}
			break;

		case 'file':
			if ((!is_null($file_pattern)) && (!preg_match($file_pattern, $fpath))) {
				return;
			}
			$suffix=strtolower(\Phool\File::fileSuffix($fpath));
			if ($suffix=='phk') {
				$this->registerPhkPkg($fpath,$rpath);
			} elseif (array_search($suffix,$this->php_file_ext)!==false) {
				$this->registerScriptFile($fpath,$rpath,$ns_filter);
			} else {
				\Phool\Display::trace("Ignoring file $fpath (not a PHP script)");
			}
			break;
	}
}

//---------
// Read a map file, replacing current symbol table, if any

public function readMapFile($fpath)
{
	\Phool\Display::trace("Reading map file ($fpath)");

	$map=new \Automap\Map($fpath);
	$this->options=$map->options();
	$this->resetSymbolTable();
	$this->mergeMapSymbols($map);
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

public function mergeMapFile($fpath,$rpath)
{
	\Phool\Display::debug("Merging map file from $fpath (rpath=$rpath)");

	$map=new \Automap\Map($fpath);
	$this->mergeMapSymbols($map,$rpath);
}

//---------

public function mergeMapSymbols($map,$rpath='.')
{
foreach($map->symbols() as $sym)
	{
	$va=self::mkVarray($sym['ptype'],\Phool\File::combinePath($rpath,$sym['rpath']));
	$this->addTSEntry($sym['stype'],$sym['symbol'],$va);
	}
}

//---------
// Register a PHK package

public function registerPhkPkg($fpath,$rpath)
{
	\Phool\Display::trace("Registering PHK package $fpath as $rpath");

	$rpath=self::normalizePath($rpath);
	\Phool\Display::debug("Registering PHK package (path=$fpath, rpath=$rpath)");
	$va=self::mkVarray(\Automap\Mgr::F_PACKAGE,$rpath);
	$this->unregisterTarget($va);

	$mnt=\PHK\Mgr::mount($fpath,\PHK::NO_MOUNT_SCRIPT);
	$pkg=\PHK\Mgr::instance($mnt);
	$id=$pkg->automapID();
	if ($id) { // If package has an automap
		foreach(\Automap\Mgr::map($id)->symbols() as $sym) {
			$this->addTSEntry($sym['stype'],$sym['symbol'],$va);
		}
	}
}

//---------

public function import($path=null)
{
	if (is_null($path)) $path="php://stdin";

	\Phool\Display::trace("Importing map from $path");

	$fp=fopen($path,'r');
	if (!$fp) throw new \Exception("$path: Cannot open for reading");

	while(($line=fgets($fp))!==false) {
		if (($line=trim($line))==='') continue;
		list($stype,$sname,$ftype,$fname)=explode('|',$line);
		$va=self::mkVarray($ftype,$fname);
		$this->addTSEntry($stype,$sname,$va);
	}
	fclose($fp);
}

//---
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
