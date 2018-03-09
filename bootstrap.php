<?php

/**
 * Composer autoloader.
 */
require 'vendor/autoload.php';

/*
 * Options
 */

$options = array();

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();


//Login
$options['password'] = getenv('PASSWORD');
$options['email'] = getenv('EMAIL');
//Paths
$options['local_path'] = getenv('LOCAL_PATH');
$options['mangas_folder'] = getenv('MANGAS_FOLDER');
//Flags
$options['retry_download'] = boolval(getenv('RETRY_DOWNLOAD'));

define('BASE_FOLDER', $options['local_path']);
define('MANGAS_FOLDER', $options['mangas_folder']);
define('RETRY_DOWNLOAD', $options['retry_download']);

//laracasts
define('BASE_URL', 'http://goldenmangas.com/');
define('MANGAS_PATH', 'mangas');

/*
 * Vars
 */
set_time_limit(0);
