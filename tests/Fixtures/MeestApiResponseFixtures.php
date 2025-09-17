<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

/**
 * MEEST API Response Fixtures
 *
 * Real-world API response data from correspondence including:
 * - Test credentials (BLPAMST / h+gl3P3(Wl)
 * - Test tracking number (BLP68A82A025DBC2PLTEST01)
 * - Authentication responses
 * - Shipment creation responses
 * - Tracking responses with all status scenarios
 * - Error responses ("Non-unique parcel number", "Any routing not found")
 * - Label generation responses
 * - Webhook payloads
 */
class MeestApiResponseFixtures
{
    /**
     * Authentication success response
     */
    public static function getAuthSuccessResponse(): array
    {
        return [
            'access_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2FwaS5tZWVzdC5jb20vdjIvYXBpL2F1dGgiLCJpYXQiOjE3MDQ4ODc2MDAsImV4cCI6MTcwNDg5MTIwMCwibmJmIjoxNzA0ODg3NjAwLCJqdGkiOiJYWWZVdHBOMGprVldaUWg0Iiwic3ViIjoidGVzdF91c2VyIiwicHJ2IjoiZGY5Y2JlYjRmNWJhNTllMmFhYTA4OWYxN2ZlZTEwYjY4YWQ4ODVjMSJ9.test_signature_hash',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'username' => 'BLPAMST',
                'company' => 'Test Company',
                'permissions' => ['create_shipment', 'track_shipment', 'generate_label']
            ],
            'api_version' => 'v2.1',
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Authentication failure response
     */
    public static function getAuthFailureResponse(): array
    {
        return [
            'error' => 'invalid_credentials',
            'message' => 'Invalid username or password',
            'code' => 401,
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Create shipment success response
     */
    public static function getCreateShipmentSuccessResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'shipment_id' => 'MEEST-SHIP-2024-001',
            'status' => 'created',
            'status_code' => '100',
            'estimated_delivery' => '2024-01-15T10:00:00Z',
            'total_cost' => 25.50,
            'currency' => 'USD',
            'label_url' => 'https://api.meest.com/labels/BLP68A82A025DBC2PLTEST01.pdf',
            'barcode' => 'BLP68A82A025DBC2PLTEST01',
            'service_type' => 'standard',
            'created_at' => '2024-01-10T10:00:00Z',
            'metadata' => [
                'route' => 'PL-US',
                'zone' => 'international',
                'estimated_transit_days' => 5,
                'signature_required' => false
            ]
        ];
    }

    /**
     * Create return shipment success response
     */
    public static function getCreateReturnShipmentSuccessResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST02',
            'shipment_id' => 'MEEST-RETURN-2024-001',
            'status' => 'created',
            'status_code' => '100',
            'original_tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'estimated_delivery' => '2024-01-20T10:00:00Z',
            'total_cost' => 15.75,
            'currency' => 'USD',
            'label_url' => 'https://api.meest.com/labels/BLP68A82A025DBC2PLTEST02.pdf',
            'service_type' => 'return',
            'created_at' => '2024-01-10T10:00:00Z',
            'metadata' => [
                'return_reason' => 'customer_request',
                'original_shipment_id' => 'MEEST-SHIP-2024-001',
                'createReturnParcel' => true
            ]
        ];
    }

    /**
     * Tracking response for test package BLP68A82A025DBC2PLTEST01
     */
    public static function getTrackingSuccessResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'in_transit',
            'status_code' => '300',
            'status_description' => 'Package is in transit',
            'last_update' => '2024-01-10T14:30:00Z',
            'estimated_delivery' => '2024-01-15T10:00:00Z',
            'current_location' => 'Warsaw Distribution Center, Poland',
            'recipient_surname' => 'Kowalski',
            'pickup_date' => '2024-01-08T09:15:00Z',
            'delivery_attempts' => 0,
            'signature_required' => false,
            'events' => [
                [
                    'timestamp' => '2024-01-10T14:30:00Z',
                    'status' => 'in_transit',
                    'status_code' => '300',
                    'location' => 'Warsaw Distribution Center, Poland',
                    'description' => 'Package is in transit to destination country',
                    'facility_type' => 'sorting_hub'
                ],
                [
                    'timestamp' => '2024-01-10T09:15:00Z',
                    'status' => 'collected',
                    'status_code' => '200',
                    'location' => 'Warsaw, Poland',
                    'description' => 'Package collected from sender',
                    'facility_type' => 'pickup_point'
                ],
                [
                    'timestamp' => '2024-01-08T15:45:00Z',
                    'status' => 'accepted',
                    'status_code' => '150',
                    'location' => 'Warsaw Processing Center, Poland',
                    'description' => 'Package accepted at MEEST facility',
                    'facility_type' => 'processing_center'
                ],
                [
                    'timestamp' => '2024-01-08T10:00:00Z',
                    'status' => 'created',
                    'status_code' => '100',
                    'location' => 'Warsaw, Poland',
                    'description' => 'Shipment created and labeled',
                    'facility_type' => 'origin'
                ]
            ],
            'metadata' => [
                'last_mile_tracking' => 'LM025DBC2PL',
                'service_type' => 'standard',
                'weight' => 1.5,
                'dimensions' => '20x15x10 cm',
                'value' => 100.0,
                'currency' => 'USD'
            ]
        ];
    }

    /**
     * Tracking response for delivered package
     */
    public static function getTrackingDeliveredResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'delivered',
            'status_code' => '500',
            'status_description' => 'Package delivered successfully',
            'last_update' => '2024-01-15T16:30:00Z',
            'delivered_at' => '2024-01-15T16:30:00Z',
            'delivery_location' => 'New York, NY 10001, USA',
            'recipient_surname' => 'Smith',
            'signature_obtained' => true,
            'delivery_proof' => 'https://api.meest.com/proof/BLP68A82A025DBC2PLTEST01.jpg',
            'events' => [
                [
                    'timestamp' => '2024-01-15T16:30:00Z',
                    'status' => 'delivered',
                    'status_code' => '500',
                    'location' => 'New York, NY 10001, USA',
                    'description' => 'Package delivered to recipient',
                    'facility_type' => 'destination'
                ],
                [
                    'timestamp' => '2024-01-15T08:00:00Z',
                    'status' => 'out_for_delivery',
                    'status_code' => '400',
                    'location' => 'New York Local Hub, USA',
                    'description' => 'Package out for delivery',
                    'facility_type' => 'local_hub'
                ],
                [
                    'timestamp' => '2024-01-14T22:15:00Z',
                    'status' => 'arrived_at_local_hub',
                    'status_code' => '606',
                    'location' => 'New York Local Hub, USA',
                    'description' => 'Package arrived at local delivery hub',
                    'facility_type' => 'local_hub'
                ]
            ]
        ];
    }

    /**
     * Tracking response for exception scenario
     */
    public static function getTrackingExceptionResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'exception',
            'status_code' => '999',
            'status_description' => 'Package held at customs for inspection',
            'last_update' => '2024-01-12T10:00:00Z',
            'current_location' => 'US Customs Facility, New York',
            'exception_reason' => 'customs_inspection',
            'exception_details' => 'Additional documentation required for customs clearance',
            'estimated_resolution' => '2024-01-16T10:00:00Z',
            'events' => [
                [
                    'timestamp' => '2024-01-12T10:00:00Z',
                    'status' => 'exception',
                    'status_code' => '999',
                    'location' => 'US Customs Facility, New York',
                    'description' => 'Package held for customs inspection',
                    'facility_type' => 'customs'
                ],
                [
                    'timestamp' => '2024-01-11T18:30:00Z',
                    'status' => 'customs_clearance',
                    'status_code' => '350',
                    'location' => 'US Customs Facility, New York',
                    'description' => 'Package submitted for customs clearance',
                    'facility_type' => 'customs'
                ]
            ],
            'required_actions' => [
                'Contact sender for additional documentation',
                'Provide commercial invoice',
                'Confirm package contents'
            ]
        ];
    }

    /**
     * Tracking response for delayed package
     */
    public static function getTrackingDelayedResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'in_transit',
            'status_code' => '300',
            'status_description' => 'Package delayed due to weather conditions',
            'last_update' => '2024-01-10T08:00:00Z', // 3 days ago - stale
            'estimated_delivery' => '2024-01-18T10:00:00Z', // Extended delivery
            'current_location' => 'Frankfurt Transit Hub, Germany',
            'delay_reason' => 'weather_conditions',
            'delay_details' => 'Flight delays due to severe weather in destination area',
            'original_estimated_delivery' => '2024-01-15T10:00:00Z',
            'events' => [
                [
                    'timestamp' => '2024-01-10T08:00:00Z',
                    'status' => 'delayed',
                    'status_code' => '350',
                    'location' => 'Frankfurt Transit Hub, Germany',
                    'description' => 'Package delayed due to weather conditions',
                    'facility_type' => 'transit_hub'
                ],
                [
                    'timestamp' => '2024-01-09T14:20:00Z',
                    'status' => 'in_transit',
                    'status_code' => '300',
                    'location' => 'Frankfurt Transit Hub, Germany',
                    'description' => 'Package arrived at international transit hub',
                    'facility_type' => 'transit_hub'
                ]
            ]
        ];
    }

    /**
     * Error response for non-unique parcel number
     */
    public static function getNonUniqueParcelNumberError(): array
    {
        return [
            'error' => 'validation_failed',
            'message' => 'Non-unique parcel number',
            'code' => 409,
            'details' => [
                'field' => 'tracking_number',
                'value' => 'BLP68A82A025DBC2PLTEST01',
                'reason' => 'Tracking number already exists in the system'
            ],
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Error response for no routing found
     */
    public static function getNoRoutingFoundError(): array
    {
        return [
            'error' => 'routing_error',
            'message' => 'Any routing not found',
            'code' => 422,
            'details' => [
                'origin_country' => 'PL',
                'destination_country' => 'XX',
                'reason' => 'No routing available for the specified destination'
            ],
            'supported_destinations' => ['US', 'DE', 'GB', 'FR', 'IT', 'ES', 'NL', 'BE'],
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Error response for shipment not found during tracking
     */
    public static function getShipmentNotFoundError(): array
    {
        return [
            'error' => 'not_found',
            'message' => 'Shipment not found',
            'code' => 404,
            'details' => [
                'tracking_number' => 'INVALID123456789',
                'reason' => 'No shipment found with the provided tracking number'
            ],
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Error response for rate limit exceeded
     */
    public static function getRateLimitError(): array
    {
        return [
            'error' => 'rate_limit_exceeded',
            'message' => 'Rate limit exceeded',
            'code' => 429,
            'details' => [
                'limit' => 100,
                'period' => 'hour',
                'retry_after' => 3600
            ],
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Label generation success response
     */
    public static function getLabelSuccessResponse(): array
    {
        return [
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'label_url' => 'https://api.meest.com/labels/BLP68A82A025DBC2PLTEST01.pdf',
            'label_format' => 'PDF',
            'label_size' => 'A4',
            'generated_at' => '2024-01-10T10:00:00Z',
            'expires_at' => '2024-01-17T10:00:00Z',
            'metadata' => [
                'barcode' => 'BLP68A82A025DBC2PLTEST01',
                'qr_code' => 'https://api.meest.com/track/BLP68A82A025DBC2PLTEST01',
                'file_size' => 245760 // bytes
            ]
        ];
    }

    /**
     * Label generation failure response
     */
    public static function getLabelFailureResponse(): array
    {
        return [
            'error' => 'label_generation_failed',
            'message' => 'Cannot generate label for this shipment',
            'code' => 422,
            'details' => [
                'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
                'reason' => 'Shipment not ready for label generation'
            ],
            'timestamp' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Webhook payload for status update
     */
    public static function getWebhookStatusUpdate(): array
    {
        return [
            'event_type' => 'status_update',
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'in_transit',
            'status_code' => '300',
            'location' => 'Warsaw Distribution Center, Poland',
            'timestamp' => '2024-01-10T14:30:00Z',
            'description' => 'Package is in transit to destination country',
            'metadata' => [
                'facility_id' => 'WAR001',
                'facility_type' => 'sorting_hub',
                'country' => 'PL'
            ],
            'webhook_id' => 'wh_2024011014300001',
            'delivered_at' => '2024-01-10T14:30:05Z'
        ];
    }

    /**
     * Webhook payload for delivery confirmation
     */
    public static function getWebhookDeliveryConfirmation(): array
    {
        return [
            'event_type' => 'delivery_confirmation',
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'delivered',
            'status_code' => '500',
            'location' => 'New York, NY 10001, USA',
            'timestamp' => '2024-01-15T16:30:00Z',
            'description' => 'Package delivered successfully',
            'delivery_details' => [
                'recipient_name' => 'John Smith',
                'signature_obtained' => true,
                'delivery_photo' => 'https://api.meest.com/proof/BLP68A82A025DBC2PLTEST01.jpg',
                'delivery_note' => 'Left at front door as requested'
            ],
            'metadata' => [
                'delivery_attempt' => 1,
                'total_transit_time' => 168, // hours
                'on_time' => true
            ],
            'webhook_id' => 'wh_2024011516300001',
            'delivered_at' => '2024-01-15T16:30:05Z'
        ];
    }

    /**
     * Webhook payload for exception
     */
    public static function getWebhookException(): array
    {
        return [
            'event_type' => 'exception',
            'tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'status' => 'exception',
            'status_code' => '999',
            'location' => 'US Customs Facility, New York',
            'timestamp' => '2024-01-12T10:00:00Z',
            'description' => 'Package held for customs inspection',
            'exception_details' => [
                'exception_type' => 'customs_hold',
                'reason' => 'Additional documentation required',
                'estimated_resolution' => '2024-01-16T10:00:00Z',
                'required_actions' => [
                    'Contact sender for commercial invoice',
                    'Provide package contents declaration'
                ]
            ],
            'metadata' => [
                'facility_id' => 'NYC_CUSTOMS_001',
                'facility_type' => 'customs',
                'country' => 'US'
            ],
            'webhook_id' => 'wh_2024011210000001',
            'delivered_at' => '2024-01-12T10:00:05Z'
        ];
    }

    /**
     * Countries and currencies response
     */
    public static function getCountriesCurrenciesResponse(): array
    {
        return [
            'supported_countries' => [
                ['code' => 'PL', 'name' => 'Poland', 'currency' => 'PLN'],
                ['code' => 'US', 'name' => 'United States', 'currency' => 'USD'],
                ['code' => 'DE', 'name' => 'Germany', 'currency' => 'EUR'],
                ['code' => 'GB', 'name' => 'United Kingdom', 'currency' => 'GBP'],
                ['code' => 'FR', 'name' => 'France', 'currency' => 'EUR'],
                ['code' => 'IT', 'name' => 'Italy', 'currency' => 'EUR'],
                ['code' => 'ES', 'name' => 'Spain', 'currency' => 'EUR'],
                ['code' => 'NL', 'name' => 'Netherlands', 'currency' => 'EUR'],
                ['code' => 'BE', 'name' => 'Belgium', 'currency' => 'EUR'],
                ['code' => 'UA', 'name' => 'Ukraine', 'currency' => 'UAH']
            ],
            'total_count' => 10,
            'last_updated' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Batch tracking response with mixed results
     */
    public static function getBatchTrackingResponse(): array
    {
        return [
            'request_id' => 'batch_20240110_001',
            'total_requested' => 3,
            'successful' => 2,
            'failed' => 1,
            'results' => [
                'BLP68A82A025DBC2PLTEST01' => self::getTrackingSuccessResponse(),
                'BLP68A82A025DBC2PLTEST02' => self::getTrackingDeliveredResponse()
            ],
            'errors' => [
                'INVALID123456789' => self::getShipmentNotFoundError()
            ],
            'processed_at' => '2024-01-10T10:00:00Z'
        ];
    }

    /**
     * Get sample request data for shipment creation
     */
    public static function getCreateShipmentRequest(): array
    {
        return [
            'sender' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'phone' => '+48123456789',
                'email' => 'jan.kowalski@example.com',
                'company' => 'Test Company Sp. z o.o.',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Testowa 1',
                'postal_code' => '00-001',
                'region1' => 'Mazowieckie'
            ],
            'recipient' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone' => '+1234567890',
                'email' => 'john.smith@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => '123 Test Street, Apt 4B',
                'postal_code' => '10001',
                'region1' => 'NY'
            ],
            'parcel' => [
                'weight' => 1.5,
                'length' => 20.0,
                'width' => 15.0,
                'height' => 10.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Documents and promotional materials',
                'description' => 'Business documents package',
                'items' => [
                    [
                        'description' => 'Business contracts',
                        'quantity' => 1,
                        'weight' => 0.5,
                        'value' => [
                            'value' => 50.0,
                            'currency' => 'USD'
                        ]
                    ],
                    [
                        'description' => 'Promotional brochures',
                        'quantity' => 10,
                        'weight' => 1.0,
                        'value' => [
                            'value' => 50.0,
                            'currency' => 'USD'
                        ]
                    ]
                ]
            ],
            'service_type' => 'standard',
            'options' => [
                'require_signature' => false,
                'saturday_delivery' => false
            ],
            'special_instructions' => 'Handle with care - contains important documents',
            'reference' => 'ORDER-2024-001'
        ];
    }

    /**
     * Get sample return shipment request
     */
    public static function getCreateReturnShipmentRequest(): array
    {
        return [
            'original_tracking_number' => 'BLP68A82A025DBC2PLTEST01',
            'sender' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'phone' => '+1234567890',
                'email' => 'john.smith@example.com',
                'country' => 'US',
                'city' => 'New York',
                'address' => '123 Test Street, Apt 4B',
                'postal_code' => '10001'
            ],
            'recipient' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'phone' => '+48123456789',
                'email' => 'jan.kowalski@example.com',
                'company' => 'Test Company Sp. z o.o.',
                'country' => 'PL',
                'city' => 'Warsaw',
                'address' => 'ul. Testowa 1',
                'postal_code' => '00-001'
            ],
            'parcel' => [
                'weight' => 1.0,
                'length' => 15.0,
                'width' => 10.0,
                'height' => 8.0,
                'value' => [
                    'localTotalValue' => 50.0,
                    'localCurrency' => 'USD'
                ],
                'contents' => 'Returned merchandise',
                'description' => 'Customer return package'
            ],
            'createReturnParcel' => true,
            'return_reason' => 'customer_request',
            'special_instructions' => 'Return processing required'
        ];
    }

    /**
     * Get all fixture data organized by category
     */
    public static function getAllFixtures(): array
    {
        return [
            'authentication' => [
                'success' => self::getAuthSuccessResponse(),
                'failure' => self::getAuthFailureResponse()
            ],
            'shipment_creation' => [
                'success' => self::getCreateShipmentSuccessResponse(),
                'return_success' => self::getCreateReturnShipmentSuccessResponse(),
                'request' => self::getCreateShipmentRequest(),
                'return_request' => self::getCreateReturnShipmentRequest()
            ],
            'tracking' => [
                'success' => self::getTrackingSuccessResponse(),
                'delivered' => self::getTrackingDeliveredResponse(),
                'exception' => self::getTrackingExceptionResponse(),
                'delayed' => self::getTrackingDelayedResponse(),
                'batch' => self::getBatchTrackingResponse()
            ],
            'labels' => [
                'success' => self::getLabelSuccessResponse(),
                'failure' => self::getLabelFailureResponse()
            ],
            'errors' => [
                'non_unique_parcel' => self::getNonUniqueParcelNumberError(),
                'no_routing' => self::getNoRoutingFoundError(),
                'shipment_not_found' => self::getShipmentNotFoundError(),
                'rate_limit' => self::getRateLimitError()
            ],
            'webhooks' => [
                'status_update' => self::getWebhookStatusUpdate(),
                'delivery_confirmation' => self::getWebhookDeliveryConfirmation(),
                'exception' => self::getWebhookException()
            ],
            'countries' => self::getCountriesCurrenciesResponse()
        ];
    }
}