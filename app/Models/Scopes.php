<?php

namespace App\Models;

class Scopes
{
    const BUSINESSES_READ = 'businesses:read';
    const BUSINESSES_WRITE = 'businesses:write';
    const PRODUCTS_READ = 'products:read';
    const PRODUCTS_WRITE = 'products:write';
    const SERVICES_READ = 'services:read';
    const SERVICES_WRITE = 'services:write';
    const CUSTOMERS_READ = 'customers:read';
    const CUSTOMERS_WRITE = 'customers:write';
    const ORDERS_READ = 'orders:read';
    const ORDERS_WRITE = 'orders:write';
    const BOOKINGS_READ = 'bookings:read';
    const BOOKINGS_WRITE = 'bookings:write';
    const LEADS_READ = 'leads:read';
    const LEADS_WRITE = 'leads:write';
    const INVOICES_READ = 'invoices:read';
    const INVOICES_WRITE = 'invoices:write';
    const PAYMENTS_READ = 'payments:read';
    const PAYMENTS_WRITE = 'payments:write';
    const MEDIA_READ = 'media:read';
    const MEDIA_WRITE = 'media:write';
    const WEBHOOKS_READ = 'webhooks:read';
    const WEBHOOKS_WRITE = 'webhooks:write';
    const ADMIN = 'admin';

    public static function all(): array
    {
        return [
            self::BUSINESSES_READ,
            self::BUSINESSES_WRITE,
            self::PRODUCTS_READ,
            self::PRODUCTS_WRITE,
            self::SERVICES_READ,
            self::SERVICES_WRITE,
            self::CUSTOMERS_READ,
            self::CUSTOMERS_WRITE,
            self::ORDERS_READ,
            self::ORDERS_WRITE,
            self::BOOKINGS_READ,
            self::BOOKINGS_WRITE,
            self::LEADS_READ,
            self::LEADS_WRITE,
            self::INVOICES_READ,
            self::INVOICES_WRITE,
            self::PAYMENTS_READ,
            self::PAYMENTS_WRITE,
            self::MEDIA_READ,
            self::MEDIA_WRITE,
            self::WEBHOOKS_READ,
            self::WEBHOOKS_WRITE,
            self::ADMIN,
        ];
    }

    public static function group(string $resource): array
    {
        return [self::resource($resource, 'read'), self::resource($resource, 'write')];
    }

    public static function resource(string $resource, string $ability): string
    {
        return "{$resource}:{$ability}";
    }
}
