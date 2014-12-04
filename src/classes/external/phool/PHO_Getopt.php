<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.php.net/license/3_0.txt.                                  |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Andrei Zmievski <andrei@php.net>                             |
// | Modified by Francois Laupretre <francois@tekwire.net>                |
// +----------------------------------------------------------------------+

//-------------------------------------------------------------------------
// Changes from original version: see class description
//-------------------------------------------------------------------------

/**
 * Command-line options parsing class.
 *
 * @author Andrei Zmievski <andrei@php.net>
 *
 */
class PHO_Getopt {
    /**
     * Parses the command-line options.
     *
     * The first parameter to this function should be the list of command-line
     * arguments without the leading reference to the running program.
     *
     * The second parameter is a string of allowed short options. Each of the
     * option letters can be followed by a colon ':' to specify that the option
     * requires an argument, or a double colon '::' to specify that the option
     * takes an optional argument.
     *
     * The third argument is an optional array of allowed long options. The
     * leading '--' should not be included in the option name. Options that
     * require an argument should be followed by '=', and options that take an
     * option argument should be followed by '=='.
     *
     * The return value is an array of two elements: the list of parsed
     * options and the list of non-option command-line arguments. Each entry in
     * the list of parsed options is a pair of elements - the first one
     * specifies the option, and the second one specifies the option argument,
     * if there was one.
     *
     * Long and short options can be mixed.
     *
     * Most of the semantics of this function are based on GNU getopt_long().
     *
     * Changes from original version:
	 *		- switch to PHP 5 object syntax
	 *		- make independant from PEAR error handling using exceptions
	 *
	 * @param array  $args           an array of command-line arguments
     * @param string $short_options  specifies the list of allowed short options
     * @param array  $long_options   specifies the list of allowed long options
     *
     * @return array two-element array containing the list of parsed options and
     * the non-option arguments
     *
     * @throws Exception
     *
     */
    public static function getopt2($args, $short_options, $long_options = null)
    {
        return self::doGetopt(2, $args, $short_options, $long_options);
    }

    /**
     * This function expects $args to start with the script name (POSIX-style).
     * Preserved for backwards compatibility.
     * @see getopt2()
     */    
    public static function getopt($args, $short_options, $long_options = null)
    {
        return self::doGetopt(1, $args, $short_options, $long_options);
    }

    /**
     * The actual implementation of the argument parsing code.
     */
    private static function doGetopt($version, $args, $short_options, $long_options = null)
    {
        if (empty($args)) {
            return array(array(), array());
        }
        $opts     = array();
        $non_opts = array();

        settype($args, 'array');

        if ($long_options) {
            sort($long_options);
        }

        /*
         * Preserve backwards compatibility with callers that relied on
         * erroneous POSIX fix.
         */
        if ($version < 2) {
            if (isset($args[0]{0}) && $args[0]{0} != '-') {
                array_shift($args);
            }
        }

        reset($args);
        while (list($i, $arg) = each($args)) {

            /* The special element '--' means explicit end of
               options. Treat the rest of the arguments as non-options
               and end the loop. */
            if ($arg == '--') {
                $non_opts = array_merge($non_opts, array_slice($args, $i + 1));
                break;
            }

            if ($arg{0} != '-' || (strlen($arg) > 1 && $arg{1} == '-' && !$long_options)) {
                $non_opts = array_merge($non_opts, array_slice($args, $i));
                break;
            } elseif (strlen($arg) > 1 && $arg{1} == '-') {
                self::_parseLongOption(substr($arg, 2), $long_options, $opts, $args);
            } else {
                self::_parseShortOption(substr($arg, 1), $short_options, $opts, $args);
            }
        }

        return array($opts, $non_opts);
    }

    private static function _parseShortOption($arg, $short_options, &$opts, &$args)
    {
        for ($i = 0; $i < strlen($arg); $i++) {
            $opt = $arg{$i};
            $opt_arg = null;

            /* Try to find the short option in the specifier string. */
            if (($spec = strstr($short_options, $opt)) === false || $arg{$i} == ':')
            {
                throw new Exception(__CLASS__.": unrecognized option -- $opt");
            }

            if (strlen($spec) > 1 && $spec{1} == ':') {
                if (strlen($spec) > 2 && $spec{2} == ':') {
                    if ($i + 1 < strlen($arg)) {
                        /* Option takes an optional argument. Use the remainder of
                           the arg string if there is anything left. */
                        $opts[] = array($opt, substr($arg, $i + 1));
                        break;
                    }
                } else {
                    /* Option requires an argument. Use the remainder of the arg
                       string if there is anything left. */
                    if ($i + 1 < strlen($arg)) {
                        $opts[] = array($opt,  substr($arg, $i + 1));
                        break;
                    } else if (list(, $opt_arg) = each($args))
                        /* Else use the next argument. */;
                    else
                        throw new Exception(__CLASS__.": option requires an argument -- $opt");
                }
            }

            $opts[] = array($opt, $opt_arg);
        }
    }

    private static function _parseLongOption($arg, $long_options, &$opts, &$args)
    {
        @list($opt, $opt_arg) = explode('=', $arg);
        $opt_len = strlen($opt);

        for ($i = 0; $i < count($long_options); $i++) {
            $long_opt  = $long_options[$i];
            $opt_start = substr($long_opt, 0, $opt_len);

            /* Option doesn't match. Go on to the next one. */
            if ($opt_start != $opt)
                continue;

            $opt_rest  = substr($long_opt, $opt_len);

            /* Check that the options uniquely matches one of the allowed
               options. */
            if ($opt_rest != '' && $opt{0} != '=' &&
                $i + 1 < count($long_options) &&
                $opt == substr($long_options[$i+1], 0, $opt_len)) {
                throw new Exception(__CLASS__.": option --$opt is ambiguous");
            }

            if (substr($long_opt, -1) == '=') {
                if (substr($long_opt, -2) != '==') {
                    /* Long option requires an argument.
                       Take the next argument if one wasn't specified. */;
                    if (!strlen($opt_arg) && !(list(, $opt_arg) = each($args))) {
                        throw new Exception(__CLASS__.": option --$opt requires an argument");
                    }
                }
            } else if ($opt_arg) {
                throw new Exception(__CLASS__.": option --$opt doesn't allow an argument");
            }

            $opts[] = array('--' . $opt, $opt_arg);
            return;
        }

        throw new Exception(__CLASS__.": unrecognized option --$opt");
    }

    /**
    * Safely read the $argv PHP array across different PHP configurations.
    * Will take care on register_globals and register_argc_argv ini directives
    *
    * @return array the $argv PHP array
	* @throws Exception if not registered
    */
    public static function readPHPArgv()
    {
        global $argv;
        if (!is_array($argv)) {
            if (!@is_array($_SERVER['argv'])) {
                if (!@is_array($GLOBALS['HTTP_SERVER_VARS']['argv'])) {
                    throw new Exception(__CLASS__.": Could not read cmd args (register_argc_argv=Off?)");
                }
                return $GLOBALS['HTTP_SERVER_VARS']['argv'];
            }
            return $_SERVER['argv'];
        }
        return $argv;
    }

}

?>
