<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Documentation Controller for MEEST endpoints
 */
#[Route('/v2/api/meest/docs', name: 'api_meest_docs_')]
class MeestApiDocsController extends AbstractController
{
    /**
     * Get API documentation in OpenAPI format
     */
    #[Route('', name: 'openapi', methods: ['GET'])]
    public function getOpenApiDocs(): JsonResponse
    {
        $docs = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'MEEST Courier API',
                'description' => 'REST API for creating and managing MEEST shipments',
                'version' => '2.0.0',
                'contact' => [
                    'name' => 'SkyBroker API Support',
                    'email' => 'api@skybroker.pl'
                ]
            ],
            'servers' => [
                [
                    'url' => 'http://185.213.25.106/v2/api/meest',
                    'description' => 'Production server'
                ]
            ],
            'paths' => [
                '/parcels' => [
                    'post' => [
                        'summary' => 'Create a new shipment',
                        'description' => 'Create a new MEEST shipment with complete validation',
                        'tags' => ['Shipments'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CreateShipmentRequest'
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Shipment created successfully',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/ShipmentResponse'
                                        ]
                                    ]
                                ]
                            ],
                            '400' => [
                                'description' => 'Validation error',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/ErrorResponse'
                                        ]
                                    ]
                                ]
                            ],
                            '502' => [
                                'description' => 'Integration error with MEEST API'
                            ]
                        ]
                    ]
                ],
                '/parcels/return' => [
                    'post' => [
                        'summary' => 'Create a return shipment',
                        'description' => 'Create a return shipment for an existing order',
                        'tags' => ['Shipments'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CreateReturnShipmentRequest'
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '201' => [
                                'description' => 'Return shipment created successfully'
                            ]
                        ]
                    ]
                ],
                '/tracking/{trackingNumber}' => [
                    'get' => [
                        'summary' => 'Get tracking information',
                        'description' => 'Retrieve current tracking status and events',
                        'tags' => ['Tracking'],
                        'parameters' => [
                            [
                                'name' => 'trackingNumber',
                                'in' => 'path',
                                'required' => true,
                                'schema' => [
                                    'type' => 'string',
                                    'pattern' => '^ME\d{10}$',
                                    'example' => 'ME1234567890'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Tracking information retrieved'
                            ],
                            '404' => [
                                'description' => 'Tracking number not found'
                            ]
                        ]
                    ]
                ],
                '/shipments/{trackingNumber}' => [
                    'get' => [
                        'summary' => 'Get shipment details',
                        'description' => 'Retrieve complete shipment information',
                        'tags' => ['Shipments'],
                        'parameters' => [
                            [
                                'name' => 'trackingNumber',
                                'in' => 'path',
                                'required' => true,
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Shipment details retrieved'
                            ]
                        ]
                    ]
                ],
                '/labels/{trackingNumber}' => [
                    'get' => [
                        'summary' => 'Download shipping label',
                        'description' => 'Download PDF shipping label',
                        'tags' => ['Labels'],
                        'parameters' => [
                            [
                                'name' => 'trackingNumber',
                                'in' => 'path',
                                'required' => true,
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'PDF label file',
                                'content' => [
                                    'application/pdf' => [
                                        'schema' => [
                                            'type' => 'string',
                                            'format' => 'binary'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/info/countries' => [
                    'get' => [
                        'summary' => 'Get supported countries',
                        'description' => 'List all countries supported by MEEST',
                        'tags' => ['Information'],
                        'responses' => [
                            '200' => [
                                'description' => 'List of supported countries'
                            ]
                        ]
                    ]
                ],
                '/info/validate' => [
                    'post' => [
                        'summary' => 'Validate shipment data',
                        'description' => 'Validate shipment data without creating it',
                        'tags' => ['Information'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/CreateShipmentRequest'
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Validation passed'
                            ],
                            '400' => [
                                'description' => 'Validation failed'
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'CreateShipmentRequest' => [
                        'type' => 'object',
                        'required' => ['sender', 'recipient', 'parcel'],
                        'properties' => [
                            'sender' => [
                                '$ref' => '#/components/schemas/Address'
                            ],
                            'recipient' => [
                                '$ref' => '#/components/schemas/Address'
                            ],
                            'parcel' => [
                                '$ref' => '#/components/schemas/Parcel'
                            ],
                            'shipment_type' => [
                                'type' => 'string',
                                'enum' => ['standard', 'express', 'economy'],
                                'default' => 'standard'
                            ],
                            'special_instructions' => [
                                'type' => 'string',
                                'maxLength' => 500
                            ],
                            'reference' => [
                                'type' => 'string',
                                'maxLength' => 100
                            ],
                            'require_signature' => [
                                'type' => 'boolean',
                                'default' => false
                            ],
                            'saturday_delivery' => [
                                'type' => 'boolean',
                                'default' => false
                            ],
                            'delivery_date' => [
                                'type' => 'string',
                                'format' => 'date',
                                'description' => 'Preferred delivery date (ISO 8601)'
                            ]
                        ]
                    ],
                    'CreateReturnShipmentRequest' => [
                        'allOf' => [
                            [
                                '$ref' => '#/components/schemas/CreateShipmentRequest'
                            ],
                            [
                                'type' => 'object',
                                'required' => ['original_tracking_number'],
                                'properties' => [
                                    'original_tracking_number' => [
                                        'type' => 'string',
                                        'description' => 'Tracking number of the original shipment'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Address' => [
                        'type' => 'object',
                        'required' => [
                            'first_name', 'last_name', 'phone', 'email',
                            'country', 'city', 'address', 'postal_code'
                        ],
                        'properties' => [
                            'first_name' => [
                                'type' => 'string',
                                'maxLength' => 50
                            ],
                            'last_name' => [
                                'type' => 'string',
                                'maxLength' => 50
                            ],
                            'phone' => [
                                'type' => 'string',
                                'pattern' => '^[+]?[0-9]{10,15}$'
                            ],
                            'email' => [
                                'type' => 'string',
                                'format' => 'email'
                            ],
                            'country' => [
                                'type' => 'string',
                                'pattern' => '^[A-Z]{2}$',
                                'enum' => ['DE', 'CZ', 'SK', 'HU', 'RO', 'LT', 'LV', 'EE', 'UA', 'BG', 'PL']
                            ],
                            'city' => [
                                'type' => 'string',
                                'maxLength' => 100
                            ],
                            'address' => [
                                'type' => 'string',
                                'maxLength' => 200
                            ],
                            'postal_code' => [
                                'type' => 'string',
                                'maxLength' => 10
                            ],
                            'company' => [
                                'type' => 'string',
                                'maxLength' => 100
                            ],
                            'region1' => [
                                'type' => 'string',
                                'maxLength' => 100,
                                'description' => 'State/Province/Region (optional)'
                            ]
                        ]
                    ],
                    'Parcel' => [
                        'type' => 'object',
                        'required' => [
                            'weight', 'length', 'width', 'height',
                            'contents', 'value'
                        ],
                        'properties' => [
                            'weight' => [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.01,
                                'maximum' => 30.0,
                                'description' => 'Weight in kilograms'
                            ],
                            'length' => [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.1,
                                'maximum' => 120.0,
                                'description' => 'Length in centimeters'
                            ],
                            'width' => [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.1,
                                'maximum' => 120.0,
                                'description' => 'Width in centimeters'
                            ],
                            'height' => [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.1,
                                'maximum' => 120.0,
                                'description' => 'Height in centimeters'
                            ],
                            'contents' => [
                                'type' => 'string',
                                'maxLength' => 500,
                                'description' => 'Description of parcel contents'
                            ],
                            'description' => [
                                'type' => 'string',
                                'maxLength' => 500
                            ],
                            'value' => [
                                '$ref' => '#/components/schemas/ParcelValue'
                            ],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/ParcelItem'
                                ]
                            ]
                        ]
                    ],
                    'ParcelValue' => [
                        'type' => 'object',
                        'required' => ['localTotalValue', 'localCurrency'],
                        'properties' => [
                            'localTotalValue' => [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.01,
                                'maximum' => 10000.0,
                                'description' => 'Total declared value'
                            ],
                            'localCurrency' => [
                                'type' => 'string',
                                'pattern' => '^[A-Z]{3}$',
                                'description' => '3-letter ISO currency code for recipient country'
                            ]
                        ]
                    ],
                    'ParcelItem' => [
                        'type' => 'object',
                        'required' => ['name', 'quantity', 'value'],
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'maxLength' => 100
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'minimum' => 1
                            ],
                            'value' => [
                                'type' => 'object',
                                'required' => ['value'],
                                'properties' => [
                                    'value' => [
                                        'type' => 'number',
                                        'format' => 'float',
                                        'minimum' => 0.01
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'ShipmentResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean'
                            ],
                            'data' => [
                                '$ref' => '#/components/schemas/Shipment'
                            ],
                            'message' => [
                                'type' => 'string'
                            ]
                        ]
                    ],
                    'Shipment' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'format' => 'uuid'
                            ],
                            'tracking_number' => [
                                'type' => 'string',
                                'pattern' => '^ME\d{10}$'
                            ],
                            'shipment_id' => [
                                'type' => 'string'
                            ],
                            'status' => [
                                'type' => 'string',
                                'enum' => [
                                    'created', 'accepted', 'in_transit', 'out_for_delivery',
                                    'delivered', 'delivery_attempt', 'exception', 'returned',
                                    'cancelled', 'pending_pickup', 'at_sorting_facility',
                                    'customs_clearance', 'customs_cleared', 'customs_held'
                                ]
                            ],
                            'status_description' => [
                                'type' => 'string'
                            ],
                            'shipment_type' => [
                                'type' => 'string'
                            ],
                            'total_cost' => [
                                'type' => 'string',
                                'format' => 'decimal'
                            ],
                            'currency' => [
                                'type' => 'string'
                            ],
                            'has_label' => [
                                'type' => 'boolean'
                            ],
                            'estimated_delivery' => [
                                'type' => 'string',
                                'format' => 'date-time'
                            ],
                            'created_at' => [
                                'type' => 'string',
                                'format' => 'date-time'
                            ]
                        ]
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                                'example' => false
                            ],
                            'error' => [
                                'type' => 'string',
                                'enum' => ['validation_failed', 'integration_error', 'not_found', 'internal_error']
                            ],
                            'message' => [
                                'type' => 'string'
                            ],
                            'details' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'string'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $this->json($docs);
    }

    /**
     * Get example requests
     */
    #[Route('/examples', name: 'examples', methods: ['GET'])]
    public function getExamples(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'create_shipment' => [
                    'url' => '/v2/api/meest/parcels',
                    'method' => 'POST',
                    'example' => [
                        'sender' => [
                            'first_name' => 'Jan',
                            'last_name' => 'Kowalski',
                            'phone' => '+48123456789',
                            'email' => 'sender@example.com',
                            'country' => 'PL',
                            'city' => 'Warsaw',
                            'address' => 'ul. Marszałkowska 1',
                            'postal_code' => '00-624',
                            'company' => 'Example Company'
                        ],
                        'recipient' => [
                            'first_name' => 'Hans',
                            'last_name' => 'Mueller',
                            'phone' => '+4930123456789',
                            'email' => 'recipient@example.de',
                            'country' => 'DE',
                            'city' => 'Berlin',
                            'address' => 'Unter den Linden 1',
                            'postal_code' => '10117'
                        ],
                        'parcel' => [
                            'weight' => 2.5,
                            'length' => 30.0,
                            'width' => 20.0,
                            'height' => 15.0,
                            'contents' => 'Electronics - Smartphone',
                            'description' => 'Brand new smartphone in original packaging',
                            'value' => [
                                'localTotalValue' => 500.00,
                                'localCurrency' => 'EUR'
                            ],
                            'items' => [
                                [
                                    'name' => 'Smartphone',
                                    'quantity' => 1,
                                    'value' => [
                                        'value' => 500.00
                                    ]
                                ]
                            ]
                        ],
                        'shipment_type' => 'standard',
                        'special_instructions' => 'Handle with care - fragile electronics',
                        'reference' => 'ORDER-2024-001',
                        'require_signature' => true
                    ]
                ],
                'create_return_shipment' => [
                    'url' => '/v2/api/meest/parcels/return',
                    'method' => 'POST',
                    'example' => [
                        'original_tracking_number' => 'ME1234567890',
                        'contents' => 'Return - defective smartphone',
                        'description' => 'Customer return due to manufacturing defect',
                        'value' => 500.00,
                        'special_instructions' => 'Return processing required',
                        'reference' => 'RET-ORDER-2024-001'
                    ]
                ],
                'get_tracking' => [
                    'url' => '/v2/api/meest/tracking/ME1234567890',
                    'method' => 'GET'
                ],
                'validate_shipment' => [
                    'url' => '/v2/api/meest/info/validate',
                    'method' => 'POST',
                    'description' => 'Use the same structure as create_shipment to validate without creating'
                ]
            ]
        ]);
    }
}