<?php



/**
 * Class Wc_Rw_Order_Data_Export_Debug
 * Handles debugging and error logging for the plugin.
 */
class Wc_Rw_Order_Data_Export_Debug {

    /**
     * Prints a readable array structure for debugging.
     *
     * @param mixed $arr The data to print.
     */
    public static function wc_rw_order_data_export_array_debug(array $arr) {
        echo '<pre>' . print_r($arr, true) . '</pre>';
    }



    public static function wc_rw_order_data_export_error(string $error, bool $make_log = true, int $http_status_code = 500) : void
    {
        $backtrace = debug_backtrace();

        $file = $backtrace[1]['file'] ?? 'Unknown file';
        $line = $backtrace[1]['line'] ?? 'Unknown line';

        if ($make_log) {
            error_log("Error: $error. File: $file, Line: $line");
        }

    }




}



