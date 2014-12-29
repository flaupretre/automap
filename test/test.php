<?php

define('MAP1','auto1.map');
define('MAP1_SYMCOUNT',8);

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

$id1=Automap::load(MAP1);
$t->check('load() returns int (1)',is_int($id1));

$map1=Automap::map($id1);
$t->check('map object is instance of Automap_Map (1)',($map1 instanceof Automap_Map));

$id2=Automap::load(MAP2);
$t->check('load() returns int (2)',is_int($id2));

$map2=Automap::map($id2);
$t->check('map object is instance of Automap_Map (2)',($map2 instanceof Automap_Map));

//---------------------------------
$t->start('Versions');

$t->check('version() returns string',is_string($map1->version()));
$t->check('min_version() returns string',is_string($map1->min_version()));

//---------------------------------
$t->start('Map IDs');

$t->check('$id1 id_is_active() is true',Automap::id_is_active($id1));

$t->check('1000 is not an active ID',!Automap::id_is_active(1000));

$t->check('String is not an active ID',!Automap::id_is_active('<bad>'));

//---------------------------------
$t->start('load/unload');

Automap::unload($id1);

$t->check('id_is_active() false on unloaded ID',!Automap::id_is_active($id1));

$ex=false;
try { $map1->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing an unloaded instance does not throw exception',!$ex);

$ex=false;
try { Automap::unload($id1); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an unloaded ID throws exception',$ex);

$ex=false;
try { Automap::unload(1000); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an invalid (numeric) ID throws exception',$ex);

$ex=false;
try { @Automap::unload('<bad>'); }
catch (Exception $e) { $ex=true; }
$t->check('Unloading an invalid (non-numeric) ID throws exception',$ex);

$prev_id1=$id1;
$id1=Automap::load(MAP1);

$ex=false;
try { $map1->options(); }
catch (Exception $e) { $ex=true; }
$t->check('Accessing a reloaded instance does not throw exception',!$ex);

$t->check('Reloaded ID is still inactive',!Automap::id_is_active($prev_id1));
$t->check('Reloaded ID is different',($id1 != $prev_id1));

$map1=Automap::map($id1);

//---------------------------------
$t->start('Map methods');

$t->check('Map path',$map1->path()===MAP1);

$t->check('Flags',$map1->flags()===0);

$a=$map1->options();
$t->check('Options = array()',((is_array($a))&&(count($a)==0)));

$t->check('Getting non-existant option returns null',is_null($map1->option('foo')));

$t->check('symbol_count()',$map1->symbol_count()===MAP1_SYMCOUNT);

$t->check('using_accelerator()',Automap::using_accelerator()===$extension_present);

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

$t->check('check() returns no error',Automap_Tools::check($id1)===0);

$t->check('get_symbol() returns false on non existing symbol',$map1->get_symbol(Automap::T_CLASS,'nosuchclass')===false);

//---------------------------------
$t->start('Explicit get methods');

$t->check('get_constant(<wrong name>) returns false',Automap::get_constant('invalid_name')===false);

$t->check('get_constant(<valid name>) returns true', Automap::get_constant('CONST11')===true);
$t->check('get_constant() defines constant',defined('CONST11'));

$t->check('get_function(<wrong name>) returns false',Automap::get_function('foo')===false);

$t->check('get_function(<valid name>) returns true', Automap::get_function('func12')===true);
$t->check('get_function() defines function',function_exists('func12'));

$t->check('get_class(<wrong name>) returns false',Automap::get_class('foo')===false);

$t->check('get_class(<valid name>) returns true', Automap::get_class('c13')===true);
$t->check('get_class() defines class',class_exists('c13',false));

//----------
$t->start('Explicit require methods');

$ex=false;
try { Automap::require_constant('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_constant(<wrong name>) throws exception',$ex);

$t->check('require_constant(<valid name>) returns true', Automap::require_constant('CONST21')===true);
$t->check('require_constant() defines constant',defined('CONST21'));

$ex=false;
try { Automap::require_function('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_function(<wrong name>) throws exception',$ex);

$t->check('require_function(<valid name>) returns true', Automap::require_function('func22')===true);
$t->check('require_function() defines function',function_exists('func22'));

$ex=false;
try { Automap::require_class('invalid_name'); }
catch (Exception $e) { $ex=true; }
$t->check('require_class(<wrong name>) throws exception',$ex);

$t->check('require_class(<valid name>) returns true', Automap::require_class('c23')===true);
$t->check('require_class() defines class',class_exists('c23',false));

//---------------------------------
$t->start('Autoloading');

$t->check('Intra-map', Message2::get('foo')==='FOO2');
$t->check('Inter-map', Message2x::get('foo')==='FOO1');

Automap::unload($id1);
$t->check('Cannot autoload from an unloaded map',!class_exists('c15',1));

//---------------------------------
$t->start('Load flags');

$flags=Automap::NO_AUTOLOAD;
$id1=Automap::load(MAP1,$flags);
$t->check('Cannot autoload from a map with flag NO_AUTOLOAD',!class_exists('c15',1));

$id11=Automap::load(MAP1);
$t->check('Autoload from a map with previous NO_AUTOLOAD',class_exists('c15',1));

Automap::unload($id1);
Automap::unload($id11);

//---------------------------------
$t->start('Success handler');

$id1=Automap::load(MAP1);

//------

function success_func($entry,$id)
{
$t=$GLOBALS['t'];
$stype=$entry['stype'];
$sname=$entry['symbol'];
$map=Automap::map($id);

$t->check('Handler receives the right map',$map->symbol_count()===MAP1_SYMCOUNT);
$t->check('Handler receives the right symbol type',$stype===Automap::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='c14');
$t->check('Success handler: checking file existence (absolute path)',file_exists($entry['path']));

$sym=$map->get_symbol($stype,$sname);

$t->check('Success handler: get_symbol(): returned element is array',is_array($sym));
$t->check('Success handler: get_symbol() returns correct symbol type',$sym['stype']===Automap::T_CLASS);
$t->check('Success handler: get_symbol() returns correct symbol name',$sym['symbol']==='c14');
$t->check('Success handler: get_symbol() returns correct path type',$sym['ptype']===Automap::F_SCRIPT);
$t->check('Success handler: get_symbol(): returned correct relative path',$sym['rpath']==='src1/classes/file14.php');
$t->check('Success handler: get_symbol(): checking file existence (relative path)',file_exists($sym['rpath']));

$GLOBALS['success_handler_called']=true;
}

//------

Automap::register_success_handler('success_func');
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

$t->check('Handler receives the right symbol type',$stype===Automap::T_CLASS);
$t->check('Handler receives the right symbol name',$sname==='Inexistent_Class');

$GLOBALS['failure_handler_called']=true;
}

Automap::register_failure_handler('failure_func');
$GLOBALS['success_handler_called']=false;
$GLOBALS['failure_handler_called']=false;
$t->check('Load invalid class through class_exists() fails',!class_exists('Inexistent_Class',1));
$t->check('Failure handler was called',$GLOBALS['failure_handler_called']);
$t->check('Success handler was not called',!$GLOBALS['success_handler_called']);

//----------------

$t->end();
?>
