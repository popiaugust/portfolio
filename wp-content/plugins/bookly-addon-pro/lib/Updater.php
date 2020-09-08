<?php
namespace BooklyPro\Lib;

use Bookly\Lib as BooklyLib;

/**
 * Class Updates
 * @package BooklyPro\Lib
 */
class Updater extends BooklyLib\Base\Updater
{
    function update_2_2()
    {
        $address_show_fields = (array) get_option( 'bookly_cst_address_show_fields' );
        $fields = array();
        foreach ( $address_show_fields as $field_name => $attributes ) {
            if ( (bool) $attributes['show'] ) {
                $fields[] = '{' . $field_name . '}';
            }
        }
        $this->addL10nOptions( array(
            'bookly_l10n_cst_address_template' => implode( ', ', $fields ),
        ) );
    }

    function update_2_1()
    {
        // Create WP role for bookly supervisor
        $capabilities = array();
        if ( $subscriber = get_role( 'subscriber' ) ) {
            $capabilities = $subscriber->capabilities;
        }

        // Fix subscribers access to dashboard with woocommerce.
        $capabilities['view_admin_dashboard'] = true;

        $capabilities['manage_bookly'] = true;

        add_role( 'bookly_administrator', 'Bookly Administrator', $capabilities );

        $this->addL10nOptions( array(
            'bookly_l10n_info_payment_step_with_100percents_off_price' => __( 'You are not required to pay for the booked services, click Next to complete the booking process.', 'bookly' ),
        ) );

        update_option( 'bookly_pr_data', array(
            'SW1wb3J0YW50ITxici8+SXQgbG9va3MgbGlrZSB5b3UgYXJlIHVzaW5nIGFuIGlsbGVnYWwgY29weSBvZiBCb29rbHkgUHJvLiBBbmQgaXQgbWF5IGNvbnRhaW4gYSBtYWxpY2lvdXMgY29kZSwgYSB0cm9qYW4gb3IgYSBiYWNrZG9vci4=',
            'Q29uc2lkZXIgc3dpdGNoaW5nIHRvIHRoZSBsZWdhbCBjb3B5IG9mIEJvb2tseSBQcm8gdGhhdCBpbmNsdWRlcyBhbGwgZmVhdHVyZXMsIGxpZmV0aW1lIGZyZWUgdXBkYXRlcywgYW5kIDI0Lzcgc3VwcG9ydC4=',
            'WW91IGNhbiBidXkgYSBsZWdhbCBjb3B5IG9uIG91ciB3ZWJzaXRlIDxhIGhyZWY9Imh0dHBzOi8vd3d3LmJvb2tpbmctd3AtcGx1Z2luLmNvbSIgdGFyZ2V0PSJfYmxhbmsiPnd3dy5ib29raW5nLXdwLXBsdWdpbi5jb208L2E+LCBvciBjb250YWN0IHVzIGF0IDxhIGhyZWY9Im1haWx0bzpzdXBwb3J0QGJvb2tseS5pbmZvIj5zdXBwb3J0QGJvb2tseS5pbmZvPC9hPiBmb3IgYW55IGFzc2lzdGFuY2Uu',
        ) );
    }

    function update_2_0()
    {
        if ( get_option( 'bookly_paypal_timeout', 'missing' ) === 'missing' ) {
            add_option( 'bookly_paypal_timeout', '0' );
        }
    }

    function update_1_8()
    {
        $this->upgradeCharsetCollate( array(
            'bookly_staff_categories',
            'bookly_staff_preference_orders',
        ) );
    }

    function update_1_4()
    {
        /** @global \wpdb $wpdb */
        global $wpdb;

        $self = $this;
        $notifications_table = $this->getTableName( 'bookly_notifications' );
        $notifications = array(
            'client_new_wp_user' => array( 'type' => 'customer_new_wp_user', 'name' => __( 'New customer\'s WordPress user login details', 'bookly' ) ),
            'customer_birthday'  => array( 'type' => 'customer_birthday', 'name' => __( 'Customer\'s birthday', 'bookly' ) ),
            'client_approved_appointment_cart' => array( 'type' => 'new_booking_combined', 'name' => __( 'Notification to customer about approved appointments', 'bookly' ) ),
            'client_pending_appointment_cart'  => array( 'type' => 'new_booking_combined', 'name' => __( 'Notification to customer about pending appointments', 'bookly' ) ),
        );

        // Changes in schema
        $disposable_options[] = $this->disposable( __FUNCTION__ . '-1', function () use ( $self, $wpdb, $notifications_table, $notifications ) {
            if ( ! $self->existsColumn( 'bookly_notifications', 'name' ) ) {
                $self->alterTables( array(
                    'bookly_notifications' => array(
                        'ALTER TABLE `%s` ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT "" AFTER `active`',
                    ),
                ) );
            }

            $update_name = 'UPDATE `' . $notifications_table . '` SET `name` = %s WHERE `type` = %s AND name = \'\'';
            foreach ( $notifications as $type => $value ) {
                $wpdb->query( $wpdb->prepare( $update_name, $value['name'], $type ) );

                switch ( substr( $type, 0, 6 ) ) {
                    case 'staff_':
                        $wpdb->query( sprintf( 'UPDATE `%s` SET `to_staff` = 1 WHERE `type` = "%s"', $notifications_table, $type ) );
                        break;
                    case 'client':
                        $wpdb->query( sprintf( 'UPDATE `%s` SET `to_customer` = 1 WHERE `type` = "%s"', $notifications_table, $type ) );
                        break;
                }
            }
        } );

        // WPML
        $disposable_options[] = $this->disposable( __FUNCTION__ . '-2', function () use ( $self, $wpdb, $notifications_table, $notifications ) {
            $records = $wpdb->get_results( $wpdb->prepare( 'SELECT id, `type`, `gateway` FROM `' . $notifications_table . '` WHERE COALESCE( `settings`, \'[]\' ) = \'[]\' AND `type` IN (' . implode( ', ', array_fill( 0, count( $notifications ), '%s' ) ) . ')', array_keys( $notifications ) ), ARRAY_A );
            $strings = array();
            foreach ( $records as $record ) {
                $type = $record['type'];
                if ( isset( $notifications[ $type ]['type'] ) && $type != $notifications[ $type ]['type'] ) {
                    $key   = sprintf( '%s_%s_%d', $record['gateway'], $type, $record['id'] );
                    $value = sprintf( '%s_%s_%d', $record['gateway'], $notifications[ $type ]['type'], $record['id'] );
                    $strings[ $key ] = $value;
                    if ( $record['gateway'] == 'email' ) {
                        $strings[ $key . '_subject' ] = $value . '_subject';
                    }
                }
            }
            $self->renameL10nStrings( $strings, false );
        } );

        // Add settings for notifications
        $disposable_options[] = $this->disposable( __FUNCTION__ . '-3', function () use ( $wpdb, $notifications_table, $notifications ) {
            $update_settings  = 'UPDATE `' . $notifications_table . '` SET `type` = %s, `settings` = %s, `active` = %d WHERE id = %d';
            $default_settings = '{"status":"any","option":2,"services":{"any":"any","ids":[]},"offset_hours":2,"perform":"before","at_hour":9,"before_at_hour":18,"offset_before_hours":-24,"offset_bidirectional_hours":0}';
            $records = $wpdb->get_results( $wpdb->prepare( 'SELECT id, `type`, `gateway`, `message`, `active`, `subject` FROM `' . $notifications_table . '` WHERE COALESCE( `settings`, \'[]\' ) = \'[]\' AND `type` IN (' . implode( ', ', array_fill( 0, count( $notifications ), '%s' ) ) . ')', array_keys( $notifications ) ), ARRAY_A );
            foreach ( $records as $record ) {
                $new_type = $notifications[ $record['type'] ]['type'];
                switch ( $record['type'] ) {
                    case 'client_approved_appointment_cart':
                    case 'client_pending_appointment_cart':
                        $new_active = get_option( 'bookly_cst_combined_notifications' ) ? $record['active'] : 0;
                        break;
                    default:
                        $new_active = $record['active'];
                }

                $wpdb->query( $wpdb->prepare( $update_settings, $new_type, $default_settings, $new_active, $record['id'] ) );
            }
        } );

        if ( get_option( 'bookly_cst_combined_notifications' ) == 0 ) {
            // Deactivate combine notifications
            $wpdb->query( 'UPDATE `' . $notifications_table . '` SET `active` = 0 WHERE `type` = \'new_booking_combined\'' );
        }
        delete_option( 'bookly_cst_combined_notifications' );
        foreach ( $disposable_options as $option_name ) {
            delete_option( $option_name );
        }
    }

    public function update_1_1()
    {
        global $wpdb;

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . Entities\StaffCategory::getTableName() . '` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`     VARCHAR(255) NOT NULL,
                `position` INT NOT NULL DEFAULT 9999
             ) ENGINE = INNODB
             DEFAULT CHARACTER SET = utf8
             COLLATE = utf8_general_ci'
        );

        $wpdb->query(
            'ALTER TABLE `' . BooklyLib\Entities\Staff::getTableName() . '`
             ADD CONSTRAINT
                FOREIGN KEY (category_id)
                REFERENCES ' . Entities\StaffCategory::getTableName() . '(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE'
        );

        $bookly_gc_event_client_info = __( 'Name', 'bookly' ) . ': {client_name}' . PHP_EOL . __( 'Email', 'bookly' ) . ': {client_email}' . PHP_EOL . __( 'Phone', 'bookly' ) . ': {client_phone}' . PHP_EOL . '{custom_fields}';
        if ( BooklyLib\Config::serviceExtrasActive() ) {
            $bookly_gc_event_client_info .= PHP_EOL . __( 'Extras', 'bookly' ) . ': {extras}' . PHP_EOL;
        }

        add_option( 'bookly_gc_event_client_info', $bookly_gc_event_client_info );
        add_option( 'bookly_gc_event_appointment_info', '' );
        delete_option( 'bookly_grace_hide_admin_notice_time' );
        $this->renameUserMeta( array( 'show_purchase_reminder' => 'bookly_show_purchase_reminder' ) );

        // Create WP role for bookly supervisor
        $capabilities = array();
        if ( $subscriber = get_role( 'subscriber' ) ) {
            $capabilities = $subscriber->capabilities;
        }
        $capabilities['manage_bookly_appointments'] = true;
        add_role( 'bookly_supervisor', 'Bookly Supervisor', $capabilities );
    }
}