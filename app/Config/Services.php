<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    public static function siteSettings(bool $getShared = true): \App\Services\Settings
    {
        return $getShared
            ? static::getSharedInstance('siteSettings')
            : new \App\Services\Settings();
    }

    public static function auth(bool $getShared = true): \App\Services\Auth
    {
        return $getShared
            ? static::getSharedInstance('auth')
            : new \App\Services\Auth();
    }

    public static function audit(bool $getShared = true): \App\Services\Audit
    {
        return $getShared
            ? static::getSharedInstance('audit')
            : new \App\Services\Audit();
    }

    /** Named `bernieNotify` so it cannot collide with a future framework service. */
    public static function bernieNotify(bool $getShared = true): \App\Services\Notify
    {
        return $getShared
            ? static::getSharedInstance('bernieNotify')
            : new \App\Services\Notify();
    }

    public static function availability(bool $getShared = true): \App\Services\Availability
    {
        return $getShared
            ? static::getSharedInstance('availability')
            : new \App\Services\Availability();
    }

    public static function bookings(bool $getShared = true): \App\Services\Bookings
    {
        return $getShared
            ? static::getSharedInstance('bookings')
            : new \App\Services\Bookings();
    }

    public static function payments(bool $getShared = true): \App\Services\Payments
    {
        return $getShared
            ? static::getSharedInstance('payments')
            : new \App\Services\Payments();
    }
}
