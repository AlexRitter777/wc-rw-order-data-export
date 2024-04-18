<?php


/* Initialize Wordpress */

//use includes\LionCsvCreator;

define('BASE_PATH', find_wordpress_base_path() . "/");
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

function find_wordpress_base_path()
{
    $dir = dirname(__FILE__);
    do {
        //it is possible to check for other files here
        if (file_exists($dir . "/wp-config.php")) {
            return $dir;
        }
    } while ($dir = realpath("$dir/.."));
    return null;
}

/* Restrict access to admin only */
if (!current_user_can('administrator')) return;

require_once plugin_dir_path(__FILE__) . "config/debug.php";

$csvReport = $_SESSION['lr_entry_export']['data'];
unset($_SESSION['lr_entry_export']['data']);

//debug($csvReport); //only for testing, headers below and "echo" should be comment

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="EU_VAT_report.csv"');
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'wb');
foreach ($csvReport as $line) {
    fputcsv($fp, $line, ';');
}
fclose($fp);


