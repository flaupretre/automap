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
* This class contains auxiliary runtime features, not included in the
* PECL extension.
*
* @copyright Francois Laupretre <automap@tekwire.net>
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, V 2.0
* @category Automap
* @package Automap
*/
//===========================================================================

if (!class_exists('Automap_Tools',false)) 
{
//------------------------------------------
/**
* This class is mainly used by Automap_Cmd for features not to be included
* in the PECL extension. Some of these features usr the PHK code.
*
* @package Automap
*/

class Automap_Tools // Static only
{

//---
// Returns the number of errors found

public function check(Automap $map)
{
$checked_packages=array();

$c=0;
foreach($map->symbols() as $s)
	{
	try
		{
		switch($s['ptype'])
			{
			case Automap::F_EXTENSION:
				// Do nothing
				break;

			case Automap::F_SCRIPT:
				$path=$s['path'];
				if (!is_file($path)) throw new Exception($path.': File not found');
				break;

			case Automap::F_PACKAGE:
				$path=$s['path'];
				if (!is_file($path)) throw new Exception($path.': File not found');
				if (!PHK::file_is_package($path))
					throw new Exception($path.': File is not a PHK package');
				if (!isset($checked_packages[$path]))
					{
					// Suppress notice msg on multiple HALT_COMPILER definitions
					error_reporting(($errlevel=error_reporting()) & ~E_NOTICE);
					$mnt=PHK_Mgr::mount($path,PHK::F_NO_MOUNT_SCRIPT);
					error_reporting($errlevel);
					self::check(Automap::instance($mnt));
					$checked_packages[$path]=true;
					}
				break;

			default:
				throw new Exception('<'.$s['ptype'].'>: Unknown target type');
			}
		}
	catch (Exception $e)
		{
		echo 'Error ('.Automap::type_to_string($s['stype']).' '.$s['symbol']
			.'): '.$e->getMessage()."\n";
		$c++;
		}
	}
return $c;
}

//---------
// Display the content of a map (text or HTML depending on the current context)

public function show($map,$subfile_to_url_function=null)
{
if ($html=PHK_Util::is_web()) self::show_html($map,$subfile_to_url_function);
else self::show_text($map,$subfile_to_url_function);
}

//---------

public function show_text($map,$subfile_to_url_function=null)
{
echo "\n* Global information :\n\n";
echo '	Map version : '.$map->version()."\n";
echo '	Min reader version : '.$map->min_version()."\n";
echo '	Symbol count : '.$map->symbol_count()."\n";

echo "\n* Options :\n\n";
print_r($map->options());

echo "\n* Symbols :\n\n";

$stype_len=$symbol_len=4;
$rpath_len=10;

foreach($map->symbols() as $s)
	{
	$stype_len=max($stype_len,strlen(Automap::type_to_string($s['stype']))+2);
	$symbol_len=max($symbol_len,strlen($s['symbol'])+2);
	$rpath_len=max($rpath_len,strlen($s['rpath'])+2);
	}

echo str_repeat('-',$stype_len+$symbol_len+$rpath_len+8)."\n";
echo '|'.str_pad('Type',$stype_len,' ',STR_PAD_BOTH);
echo '|'.str_pad('Name',$symbol_len,' ',STR_PAD_BOTH);
echo '| T ';
echo '|'.str_pad('Defined in',$rpath_len,' ',STR_PAD_BOTH);
echo "|\n";
echo '|'.str_repeat('-',$stype_len);
echo '|'.str_repeat('-',$symbol_len);
echo '|---';
echo '|'.str_repeat('-',$rpath_len);
echo "|\n";

foreach($map->symbols() as $s)
	{
	echo '| '.str_pad(ucfirst(Automap::type_to_string($s['stype'])),$stype_len-1,' ',STR_PAD_RIGHT)
		.'| '.str_pad($s['symbol'],$symbol_len-1,' ',STR_PAD_RIGHT)
		.'| '.$s['ptype'].' '
		.'| '.str_pad($s['rpath'],$rpath_len-1,' ',STR_PAD_RIGHT)
		."|\n";
	}
}

//---
// The same in HTML

private function show_html($map,$subfile_to_url_function=null)
{
echo "<h2>Global information</h2>";

echo '<table border=0>';
echo '<tr><td>Map version:&nbsp;</td><td>'
	.htmlspecialchars($map->version()).'</td></tr>';
echo '<tr><td>Min reader version:&nbsp;</td><td>'
	.htmlspecialchars($map->min_version()).'</td></tr>';
echo '<tr><td>Symbol count:&nbsp;</td><td>'
	.$map->symbol_count().'</td></tr>';
echo '</table>';

echo "<h2>Options</h2>";
echo '<pre>'.htmlspecialchars(print_r($map->options(),true)).'</pre>';

echo "<h2>Symbols</h2>";

echo '<table border=1 bordercolor="#BBBBBB" cellpadding=3 '
	.'cellspacing=0 style="border-collapse: collapse"><tr><th>Type</th>'
	.'<th>Name</th><th>FT</th><th>Defined in</th></tr>';
foreach($map->symbols() as $s)
	{
	echo '<tr><td>'.ucfirst(Automap::type_to_string($s['stype'])).'</td><td>'
		.htmlspecialchars($s['symbol'])
		.'</td><td align=center>'.$s['ptype'].'</td><td>';
	if (!is_null($subfile_to_url_function)) 
		echo '<a href="'.call_user_func($subfile_to_url_function,$s['rpath']).'">';
	echo htmlspecialchars($s['rpath']);
	if (!is_null($subfile_to_url_function)) echo '</a>';
	echo '</td></tr>';
	}
echo '</table>';
}

//---

public function export($map,$path=null)
{
$file=(is_null($path) ? "php://stdout" : $path);
$fp=fopen($file,'w');
if (!$fp) throw new Exception("$file: Cannot open for writing");

foreach($map->symbols() as $s)
	{
	fwrite($fp,$s['stype'].'|'.$s['symbol'].'|'.$s['ptype'].'|'.$s['rpath']."\n");
	}

fclose($fp);
}

//---
} // End of class Automap_Tools
//===========================================================================
} // End of class_exists('Automap_Tools')
//===========================================================================
?>
