<?php

define('MAP1','auto1.map');
define('MAP1_SYMCOUNT',9);

define('MAP2','auto2.map');

//------------------------------------------------------------------------

require dirname(__FILE__).'/Tester.php';

$extension_present=extension_loaded('phk');
$t=$GLOBALS['t']=new Tester('Automap runtime ('.($extension_present ? 'with' : 'whithout')
	.' PECL accelerator)');

//------------------------------------------------------------------------
$t->start('Include Automap.phk');

$res=require(dirname(__FILE__).'/../automap.phk');
$t->check('include() returns NULL',is_string($res));

//---------------------------------
$t->start('Load maps');

$id1=\Automap\Mgr::load(MAP1);
$t->check('load() returns int (1)',is_int($id1));

$map1=\Automap\Mgr::map($id1);
$t->check('map object is instance of \Automap\Map (1)',($map1 instanceof \Automap\Map));

$id2=\Automap\Mgr::load(MAP2);
$t->check('load() returns int (2)',is_int($id2));

$map2=\Automap\Mgr::map($id2);
$t->check('map object is instance of \Automap\Map (2)',($map2 instanceof \Automap\Map));

//---------------------------------
$t->start('Versions');

$t->check('version() returns string',is_string($map1->version()));
$t->check('minVersion() returns string',is_string($map1->minVersion()));

//---------------------------------
$t->start('Map IDs');

$t->check('$id1 isActiveID() is true',\Automap\Mgr::isActiveID($id1));

$t->check('1000 is not an active ID',!\Automap\Mgr::isActiveID(1000));

$t->check('String is not an active ID',!\Automap\Mgr::isActiveID('<bad>'));

//---------------------------------
$t->start('load/unload');

\Automap\Mgr::unload($id1);

$t->check('isActiveID() false on unloaded ID',!\Automap\Mgr::isActiveID($id1));

$ex=false;
try { $map1->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing an unloaded instance does not throw exception',!$ex);

$ex=false;
try { \Automap\Mgr::unload($id1); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an unloaded ID throws exception',$ex);

$ex=false;
try { \Automap\Mgr::unload(1000); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an invalid (numeric) ID throws exception',$ex);

$ex=false;
try { @\Automap\Mgr::unload('<bad>'); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an invalid (non-numeric) ID throws exception',$ex);

$prev_id1=$id1;
$id1=\Automap\Mgr::load(MAP1);

$ex=false;
try { $map1->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing a reloaded instance does not throw exception',!$ex);

$t->check('Reloaded ID is still inactive',!\Automap\Mgr::isActiveID($prev_id1));
$t->check('Reloaded ID is different',($id1 != $prev_id1));

$map1=\Automap\Mgr::map($id1);

//---------------------------------
$t->start('Map methods');

$t->check('Map path',$map1->path()===__DIR__.'/'.MAP1);

$t->check('Flags',$map1->flags()===0);

$a=$map1->options();
$t->check('Options = array()',((is_array($a))&&(count($a)==0)));

$t->check('Getting non-existant option returns null',is_null($map1->option('foo')));

$t->check('symbolCount()',$map1->symbolCount()===MAP1_SYMCOUNT);

$t->check('usingAccelerator()',\Automap\Mgr::usingAccelerator()===$extension_present);

//---------------------------------
$t->start('symbols() method');

$syms=$map1->symbols();
$t->check('symbols() returns array',is_array($syms));
$t->check('symbols() / array count',count($syms)==MAP1_SYMCOUNT);
foreach ($syms as $sym)
	{
	$t->check('symbols(): element is array',is_array($sym));
	$t->check('symbols(): element contains <stype> index',isset($sym['stype']));
	$t->check('symbols(): element contains <symbol> index',isset($sym['symbol']));
	$t->check('symbols(): element contains <ptype> index',isset($sym['ptype']));
	$t->check('symbols(): element contains <rpath> index',isset($sym['rpath']));
	$t->check('symbols(): checking rpath',file_exists($sym['rpath']));
	}

$found_c16=false;
$found_c_excl=false;
$found_func1=false;
foreach ($syms as $sym)
	{
	if (($sym['stype']===\Automap\Mgr::T_CLASS)&&($sym['symbol']=='c16')) $found_c16=true;
	if (($sym['stype']===\Automap\Mgr::T_CLASS)&&($sym['symbol']=='c_excl')) $found_c_excl=true;
	if (($sym['stype']===\Automap\Mgr::T_FUNCTION)&&($sym['symbol']=='exp_func1')) $found_func1=true;
	}
$t->check('Directive no-auto-index works',!$found_c16);
$t->check('Directive ignore works',!$found_c_excl);
$t->check('Directive declare works',$found_func1);

$t->check('check() returns no error',count($map1->check())===0);

$t->check('getSymbol() returns false on non existing symbol',$map1->getSymbol(\Automap\Mgr::T_CLASS,'nosuchclass')===false);

//---------------------------------
$t->start('Explicit get methods');

$t->check('getConstant(<wrong name>) returns false',\Automap\Mgr::getConstant('invalid_name')===false);

$t->check('getConstant(<valid name>) returns true', \Automap\Mgr::getConstant('CONST11')===true);
$t->check('getConstant() defines constant',defined('CONST11'));

$t->check('getFunction(<wrong name>) returns false',\Automap\Mgr::getFunction('foo')===false);

$t->check('getFunction(<valid name>) returns true', \Automap\Mgr::getFunction('func12')===true);
$t->check('getFunction() defines function',function_exists('func12'));

$t->check('getClass(<wrong name>) returns false',\Automap\Mgr::getClass('foo')===false);

$t->check('getClass(<valid name>) returns true', \Automap\Mgr::getClass('c13')===true);
$t->check('getClass() defines class',class_exists('c13',false));

//----------
$t->start('Explicit require methods');

$ex=false;
try { \Automap\Mgr::requireConstant('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('requireConstant(<wrong name>) throws exception',$ex);

$t->check('requireConstant(<valid name>) returns true', \Automap\Mgr::requireConstant('CONST21')===true);
$t->check('requireConstant() defines constant',defined('CONST21'));

$ex=false;
try { \Automap\Mgr::requireFunction('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('requireFunction(<wrong name>) throws exception',$ex);

$t->check('requireFunction(<valid name>) returns true', \Automap\Mgr::requireFunction('func22')===true);
$t->check('requireFunction() defines function',function_exists('func22'));

$ex=false;
try { \Automap\Mgr::requireClass('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('requireClass(<wrong name>) throws exception',$ex);

$t->check('requireClass(<valid name>) returns true', \Automap\Mgr::requireClass('c23')===true);
$t->check('requireClass() defines class',class_exists('c23',false));

//---------------------------------
$t->start('Autoloading');

$t->check('Intra-map', Message2::get('foo')==='FOO2');
$t->check('Inter-map', Message2x::get('foo')==='FOO1');

\Automap\Mgr::unload($id1);
$t->check('Cannot autoload from an unloaded map',!class_exists('c15',1));

//---------------------------------
$t->start('Load flags');

$flags=\Automap\Mgr::NO_AUTOLOAD;
$id1=\Automap\Mgr::load(MAP1,$flags);
$t->check('Cannot autoload from a map with flag NO_AUTOLOAD',!class_exists('c15',1));

$id11=\Automap\Mgr::load(MAP1);
$t->check('Autoload from a map with previous NO_AUTOLOAD',class_exists('c15',1));

\Automap\Mgr::unload($id1);
\Automap\Mgr::unload($id11);

//---------------------------------
$t->start('Success handler');

$id1=\Automap\Mgr::load(MAP1);

//------

function success_func($entry,$id)
{
$t=$GLOBALS['t'];
$stype=$entry['stype'];
$sname=$entry['symbol'];
$map=\Automap\Mgr::map($id);

$t->check('Handler receives the right map',$map->symbolCount()===MAP1_SYMCOUNT);
$t->check('Handler receives the right symbol type',$stype===\Automap\Mgr::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='c14');
$t->check('Success handler: checking file existence (absolute path)',file_exists($entry['path']));

$sym=$map->getSymbol($stype,$sname);

$t->check('Success handler: getSymbol(): returned element is array',is_array($sym));
$t->check('Success handler: getSymbol() returns correct symbol type',$sym['stype']===\Automap\Mgr::T_CLASS);
$t->check('Success handler: getSymbol() returns correct symbol name',$sym['symbol']==='c14');
$t->check('Success handler: getSymbol() returns correct path type',$sym['ptype']===\Automap\Mgr::F_SCRIPT);
$t->check('Success handler: getSymbol(): returned correct relative path',$sym['rpath']==='src1/classes/file14.php');
$t->check('Success handler: getSymbol(): checking file existence (relative path)',file_exists($sym['rpath']));

$GLOBALS['success_handler_called']=true;
}

//------

\Automap\Mgr::registerSuccessHandler('success_func');
$GLOBALS['success_handler_called']=false;
$GLOBALS['failure_handler_called']=false;
$t->check('Load class through class_exists()',class_exists('c14',1));
$t->check('Success handler was called',$GLOBALS['success_handler_called']);
$t->check('Failure handler was not called',!$GLOBALS['failure_handler_called']);

//---------------------------------
$t->start('Failure handler');

//------

function failure_func($stype,$sname)
{
$t=$GLOBALS['t'];

$t->check('Handler receives the right symbol type',$stype===\Automap\Mgr::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='Inexistent_Class');

$GLOBALS['failure_handler_called']=true;
}

\Automap\Mgr::registerFailureHandler('failure_func');
$GLOBALS['success_handler_called']=false;
$GLOBALS['failure_handler_called']=false;
$t->check('Load invalid class through class_exists() fails',!class_exists('Inexistent_Class',1));
$t->check('Failure handler was called',$GLOBALS['failure_handler_called']);
$t->check('Success handler was not called',!$GLOBALS['success_handler_called']);

//----------------

$t->end();
?>
