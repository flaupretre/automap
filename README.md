**Automap** is a fast map-based PHP autoloader.

Map-based autoloaders resolve symbols using **map files**. These files
are created offline by a program (included) which scans PHP script files
and extracts their symbols.

At runtime, map files are loaded by the main script and then used
to resolve undefined symbols and load the corresponding PHP script
files.

As a complement to the base Automap software, the optional 
[Automap PECL extension](http://pecl.php.net/package/automap)
acts as an accelerator, making Automap the fastest PHP autoloader
available so far.

More information in the [wiki](https://github.com/flaupretre/automap/wiki).
