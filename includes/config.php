<?php
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);

session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'portal_db');
define('DB_USER', 'root');
//define('DB_PASS', 'Z$VWe8lu-O_gtjJ(L%2uq+jq');
define('DB_PASS', 'aiKo7ie=');

define('SOIL_DB_HOST', 'localhost');
define('SOIL_DB_NAME', 'forecast');
define('SOIL_DB_USER', 'root');
//define('SOIL_DB_PASS', 'Z$VWe8lu-O_gtjJ(L%2uq+jq');
define('SOIL_DB_PASS', 'aiKo7ie=');

define('ROLE_ADMIN', 1);
define('ROLE_DEALER', 2);
define('ROLE_USER', 3);
