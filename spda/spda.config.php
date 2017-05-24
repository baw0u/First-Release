<?php
/**
 *  SPDA - Simple PDO Database Abstraction
 *
 * @author		Author: Vincent Herbaut - Ikon-K
 * @git 		https://github.com/baw0u/spda
 * @version     1.0
 *
 **/
namespace baw0u;

class spdaConfig{
    /* Default database connexion */
    public static $dbNname = 'xcrud'; // Your database name
    public static $dbUser = 'root'; // Your database username
    public static $dbPass = 'root'; // // Your database password
    public static $dbHost = 'localhost'; // Your database host, 'localhost' is default
    public static $dbEncoding = 'utf8'; // Your database encoding
    public static $autoReset = true; // Resets the conditions after the query
    public static $debug = true; // Display SQL Request in errors and show_request() method
}	