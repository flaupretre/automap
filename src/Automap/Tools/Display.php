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
* This class contains the methods we include in the PHK PHP runtime, but
* not in the PECL extension.
*
* As it is included in the PHK runtime, the code may reference PHK, but not Phool.
*
* API status: Public
* Included in the PHK PHP runtime: Yes
* Implemented in the extension: No
*///==========================================================================

namespace Automap\Tools {

if (!class_exists('\Automap\Tools\Display',false)) {

class Display // Static only
{
//---------
// Display the content of a map

public static function show(\Automap\Map $map,$format=null
	,$subfile_to_url_function=null)
{
	if (is_null($format)||($format='auto')) {
		\PHK::needPhpRuntime();
		$format=(\PHK\Tools\Util::envIsWeb() ? 'html' : 'text');
	}

	switch($format) {
		case 'text':
			self::showText($map,$subfile_to_url_function);
			break;

		case 'html':
			self::showHtml($map,$subfile_to_url_function);
			break;

		default:
			throw new \Exception("Unknown display format ($format)");
	}
}

//---------

private static function sortMethod($s1,$s2)
{
	return strcmp($s1['symbol'],$s2['symbol']);
}

//---------

private static function showText(\Automap\Map $map,$subfile_to_url_function=null)
{
	echo "\n* Global information :\n\n";
	echo '	Map version : '.$map->version()."\n";
	echo '	Min reader version : '.$map->minVersion()."\n";
	echo '	Symbol count : '.$map->symbolCount()."\n";

	echo "\n* Options :\n\n";

	$opts=$map->options();
	if (count($opts)) {
		foreach($opts as $name => $value)
		echo "$name: $value\n";
	} else {
		echo "<None>\n";
	}

	echo "\n* Symbols :\n\n";

	$stype_len=$symbol_len=4;
	$rpath_len=10;

	$symbols=$map->symbols();
	usort($symbols,array(__CLASS__,'sortMethod'));
	foreach($symbols as $s) {
		$stype_len=max($stype_len,strlen(\Automap\Mgr::typeToString($s['stype']))+2);
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

	foreach($symbols as $s) {
		echo '| '.str_pad(ucfirst(\Automap\Mgr::typeToString($s['stype'])),$stype_len-1,' ',STR_PAD_RIGHT)
			.'| '.str_pad($s['symbol'],$symbol_len-1,' ',STR_PAD_RIGHT)
			.'| '.$s['ptype'].' '
			.'| '.str_pad($s['rpath'],$rpath_len-1,' ',STR_PAD_RIGHT)
			."|\n";
	}
}

//---
// The same in HTML

private static function showHtml(\Automap\Map $map,$subfile_to_url_function=null)
{
	echo "<h2>Global information</h2>";

	echo '<table border=0>';
	echo '<tr><td>Map version:&nbsp;</td><td>'
		.htmlspecialchars($map->version()).'</td></tr>';
	echo '<tr><td>Min reader version:&nbsp;</td><td>'
		.htmlspecialchars($map->minVersion()).'</td></tr>';
	echo '<tr><td>Symbol count:&nbsp;</td><td>'
		.$map->symbolCount().'</td></tr>';
	echo '</table>';

	echo "<h2>Options</h2>";

	$opts=$map->options();
	if (count($opts)) {
		echo '<table border=0>';
		foreach ($opts as $name => $value) {
			echo '<tr><td>'.htmlspecialchars($name).':&nbsp;</td><td>'
				.htmlspecialchars($value).'</td></tr>';
		}
		echo '</table>';
	}
	else {
		echo "\n<p>&lt;None&gt;</p>\n";
	}

	echo "<h2>Symbols</h2>";

	echo '<table border=1 bordercolor="#BBBBBB" cellpadding=3 '
		.'cellspacing=0 style="border-collapse: collapse"><tr><th>Type</th>'
		.'<th>Name</th><th>FT</th><th>Defined in</th></tr>';
	$symbols=$map->symbols();
	usort($symbols,array(__CLASS__,'sortMethod'));
	foreach($symbols as $s) {
		echo '<tr><td>'.ucfirst(\Automap\Mgr::typeToString($s['stype'])).'</td><td>'
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
} // End of class
//===========================================================================
} // End of class_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
