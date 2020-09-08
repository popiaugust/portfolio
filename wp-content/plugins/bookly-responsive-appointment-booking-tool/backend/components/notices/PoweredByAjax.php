<?php
namespace Bookly\Backend\Components\Notices;

use Bookly\Lib;

/**
 * Class PoweredByAjax
 * @package Bookly\Backend\Components\Notices
 */
class PoweredByAjax extends Lib\Base\Ajax
{
    /**
     * Dismiss Powered by Bookly by notice.
     */
    public static function dismissPoweredByNotice()
    {
        update_user_meta( get_current_user_id(), 'bookly_dismiss_powered_by_notice', 1 );

        wp_send_json_success();
    }

    /**
     * Enable show Powered by Bookly.
     */
    public static function enableShowPoweredBy()
    {
        update_option( 'bookly_app_show_powered_by', '1' );

        wp_send_json_success();
    }
}