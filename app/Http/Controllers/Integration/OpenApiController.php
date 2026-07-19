<?php

namespace App\Http\Controllers\Integration;

class OpenApiController extends BaseController
{
    public function spec()
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Ehlom Integration API',
                'description' => 'Central integration API for Ehlom ecosystem. Connect Hola directory, AI agents, restaurant ERP, school ERP, shopping systems, and portfolio websites.',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Ehlom API Support',
                    'url' => 'https://ehlom.com/support',
                ],
            ],
            'servers' => [
                ['url' => 'https://api.ehlom.com/v1', 'description' => 'Production'],
                ['url' => 'https://sandbox.ehlom.com/v1', 'description' => 'Sandbox'],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
                ['BearerAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'Ehlom API key (prefix: ehl_). Use X-API-Key header or Bearer token.',
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'OAuth2-token',
                        'description' => 'OAuth 2.0 access token obtained via authorization_code or client_credentials grant.',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'example' => false],
                            'message' => ['type' => 'string'],
                            'error' => ['type' => 'string'],
                        ],
                    ],
                    'Business' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                            'city' => ['type' => 'string'],
                            'state' => ['type' => 'string'],
                            'phone' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                            'website' => ['type' => 'string'],
                            'is_active' => ['type' => 'boolean'],
                            'is_verified' => ['type' => 'boolean'],
                            'enabled_modules' => ['type' => 'object'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Product' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'business_id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'price' => ['type' => 'number'],
                            'stock' => ['type' => 'integer'],
                            'sku' => ['type' => 'string'],
                            'is_active' => ['type' => 'boolean'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/api-keys' => [
                    'get' => ['summary' => 'List API keys', 'security' => [['ApiKeyAuth' => ['admin']]]],
                    'post' => ['summary' => 'Create API key', 'security' => [['ApiKeyAuth' => ['admin']]]],
                ],
                '/api-keys/{id}' => [
                    'get' => ['summary' => 'Get API key details'],
                    'patch' => ['summary' => 'Update API key'],
                    'delete' => ['summary' => 'Revoke API key'],
                ],
                '/api-keys/{id}/rotate' => ['post' => ['summary' => 'Rotate API key']],
                '/businesses' => ['get' => ['summary' => 'List businesses'], 'patch' => ['summary' => 'Update business']],
                '/businesses/{id}' => ['get' => ['summary' => 'Get business details']],
                '/products' => ['get' => ['summary' => 'List products'], 'post' => ['summary' => 'Create product']],
                '/products/{id}' => ['get' => ['summary' => 'Get product'], 'patch' => ['summary' => 'Update product'], 'delete' => ['summary' => 'Delete product']],
                '/services' => ['get' => ['summary' => 'List services'], 'post' => ['summary' => 'Create service']],
                '/services/{id}' => ['get' => ['summary' => 'Get service'], 'patch' => ['summary' => 'Update service'], 'delete' => ['summary' => 'Delete service']],
                '/customers' => ['get' => ['summary' => 'List customers']],
                '/customers/{id}' => ['get' => ['summary' => 'Get customer']],
                '/orders' => ['get' => ['summary' => 'List orders']],
                '/orders/{id}' => ['get' => ['summary' => 'Get order']],
                '/orders/{id}/status' => ['patch' => ['summary' => 'Update order status']],
                '/bookings' => ['get' => ['summary' => 'List bookings']],
                '/bookings/{id}' => ['get' => ['summary' => 'Get booking']],
                '/bookings/{id}/status' => ['patch' => ['summary' => 'Update booking status']],
                '/leads' => ['get' => ['summary' => 'List leads']],
                '/leads/{id}' => ['get' => ['summary' => 'Get lead']],
                '/leads/{id}/score' => ['patch' => ['summary' => 'Score a lead']],
                '/webhooks' => ['get' => ['summary' => 'List webhook subscriptions'], 'post' => ['summary' => 'Create webhook subscription']],
                '/webhooks/{id}' => ['get' => ['summary' => 'Get webhook'], 'patch' => ['summary' => 'Update webhook'], 'delete' => ['summary' => 'Delete webhook']],
                '/webhooks/{id}/deliveries' => ['get' => ['summary' => 'List webhook deliveries']],
                '/webhooks/deliveries/{id}/retry' => ['post' => ['summary' => 'Retry webhook delivery']],
                '/audit-logs' => ['get' => ['summary' => 'List audit logs']],
                '/audit-logs/{id}' => ['get' => ['summary' => 'Get audit log']],
            ],
            'x-webhooks' => [
                'order.created' => ['post' => ['summary' => 'A new order was created']],
                'order.updated' => ['post' => ['summary' => 'An order status was updated']],
                'booking.created' => ['post' => ['summary' => 'A new booking was created']],
                'booking.updated' => ['post' => ['summary' => 'A booking status was updated']],
                'product.created' => ['post' => ['summary' => 'A new product was created']],
                'product.updated' => ['post' => ['summary' => 'A product was updated']],
                'product.deleted' => ['post' => ['summary' => 'A product was deleted']],
                'service.created' => ['post' => ['summary' => 'A new service was created']],
                'service.updated' => ['post' => ['summary' => 'A service was updated']],
                'service.deleted' => ['post' => ['summary' => 'A service was deleted']],
                'lead.updated' => ['post' => ['summary' => 'A lead was scored or updated']],
                'invoice.paid' => ['post' => ['summary' => 'An invoice was paid']],
            ],
        ];

        return response()->json($spec);
    }
}
