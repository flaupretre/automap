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

//===========================================================================
/**
* Parser interface
*
* A parser class used to create a map must implement this interface
*
* Included in the PHK PHP runtime: No
* Implemented in the extension: No
*///==========================================================================

namespace Automap\Build {

if (!interface_exists('Automap\Build\ParserInterface',false)) 
{
interface ParserInterface
{
//---------------------------------
/**
* Extracts symbols from an extension
*
* @param string $file Extension name
* @return null
* @throw \Exception if extension cannot be loaded
*/

public function parseExtension($file);

//---------------------------------
/**
* Extracts symbols from a PHP script file
*
* @param string $path File to parse
* @return array of symbols
* @throws \Exception on parse error
*/

public function parseScriptFile($path);

//---------------------------------
/**
* Extracts symbols from a PHP script contained in a string
*
* @param string $buf The script to parse
* @return null
* @throws \Exception on parse error
*/

public function parseScript($buf);

//---
} // End of class
//===========================================================================
} // End of interface_exists
//===========================================================================
} // End of namespace
//===========================================================================
?>
