**Automap** is a fast map-based PHP autoloader.

Map-based autoloaders resolve symbols using **map files**. These files
are created offline by a program (included) which scans PHP script files
and extracts their symbols into a map file.

At runtime, map files are loaded by the main script and then used
to resolve undefined symbols and load the corresponding PHP script
files.

More information on [the project website](http://automap.tekwire.net).
