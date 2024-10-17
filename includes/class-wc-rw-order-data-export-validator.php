<?php

class Wc_Rw_Order_Data_Export_Validator
{



    /**
     * Validates report_id (based on the result of PHP's uniqid() function a custom prefix).
     *
     * @param string $report_id
     * @return bool
     */
    public static function validate_report_id(string $report_id) : bool
    {
        return (bool) preg_match('/^wc_rw_ode_[a-zA-Z0-9]{13}$/', $report_id);
    }

    /**
     * Validates report type: must be a string with a length of up to 10 characters.
     *
     * @param string $type
     * @return bool
     */
    public static function validate_type(string $type) : bool
    {
        return strlen($type) <= 10;
    }

    /**
     * Validates success: must be '0' or '1'.
     *
     * @param string $success
     * @return bool
     */
    public static  function validate_success(string $success) : bool
    {
        return $success === '0' || $success === '1';
    }

    /**
     * Validates the count: must be a positive integer and should not exceed the maximum number of orders displayed per page.
     *
     * @param string $count
     * @return bool
     */
    public static  function validate_count(string $count) : bool
    {
        $per_page = get_option('edit_shop_order_per_page', 20); // 20 - default value
        return preg_match('/^\d+$/', $count) && intval($count) > 0 && intval($count) <= $per_page;
    }












}