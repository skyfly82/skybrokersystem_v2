<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Courier\Meest\Validation;

use App\Domain\Courier\Meest\Exception\MeestValidationException;
use App\Domain\Courier\Meest\Service\MeestBusinessValidator;
use App\Tests\Fixtures\MeestApiResponseFixtures;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for MeestBusinessValidator
 *
 * Tests cover business validation scenarios including:
 * - Country support validation
 * - Currency validation and mapping
 * - Value requirements (localTotalValue, items.value.value)
 * - Weight and dimension limits
 * - Business rules enforcement
 * - Error handling for validation failures
 */
class MeestBusinessValidatorTest extends TestCase
{
    private MeestBusinessValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new MeestBusinessValidator();
    }

    /**
     * Test country support validation
     */
    public function testIsCountrySupportedValid(): void
    {
        $supportedCountries = ['PL', 'US', 'DE', 'GB', 'FR'];

        foreach ($supportedCountries as $country) {
            $this->assertTrue(
                $this->validator->isCountrySupported($country),
                "Country {$country} should be supported"
            );
        }
    }

    /**
     * Test unsupported country validation
     */
    public function testIsCountrySupportedInvalid(): void
    {
        $unsupportedCountries = ['XX', 'ZZ', 'INVALID'];

        foreach ($unsupportedCountries as $country) {
            $this->assertFalse(
                $this->validator->isCountrySupported($country),
                "Country {$country} should not be supported"
            );
        }
    }

    /**
     * Test currency mapping for supported countries
     */
    public function testGetCurrencyForCountry(): void
    {
        $expectedMappings = [
            'PL' => 'PLN',
            'US' => 'USD',
            'DE' => 'EUR',
            'GB' => 'GBP',
            'FR' => 'EUR'
        ];

        foreach ($expectedMappings as $country => $expectedCurrency) {
            $currency = $this->validator->getCurrencyForCountry($country);
            $this->assertEquals(
                $expectedCurrency,
                $currency,
                "Currency for {$country} should be {$expectedCurrency}"
            );
        }
    }

    /**
     * Test currency mapping for unsupported country
     */
    public function testGetCurrencyForUnsupportedCountry(): void
    {
        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Unsupported country: XX');

        $this->validator->getCurrencyForCountry('XX');
    }

    /**
     * Test valid shipment data validation
     */
    public function testValidateShipmentDataValid(): void
    {
        $validData = MeestApiResponseFixtures::getCreateShipmentRequest();

        // Should not throw any exception
        $this->validator->validateShipmentData($validData);
        $this->assertTrue(true); // Assertion to confirm no exception
    }

    /**
     * Test validation failure for missing sender information
     */
    public function testValidateShipmentDataMissingSender(): void
    {
        $invalidData = [
            'recipient' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'country' => 'US'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ]
            ]
        ];

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Missing required sender information');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for missing recipient information
     */
    public function testValidateShipmentDataMissingRecipient(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'country' => 'PL'
            ],
            'parcel' => [
                'weight' => 1.0,
                'value' => [
                    'localTotalValue' => 100.0,
                    'localCurrency' => 'USD'
                ]
            ]
        ];

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Missing required recipient information');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for missing parcel information
     */
    public function testValidateShipmentDataMissingParcel(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => 'Jan',
                'last_name' => 'Kowalski',
                'country' => 'PL'
            ],
            'recipient' => [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'country' => 'US'
            ]
        ];

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Missing required parcel information');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for unsupported sender country
     */
    public function testValidateShipmentDataUnsupportedSenderCountry(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['sender']['country'] = 'XX'; // Unsupported country

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Sender country XX is not supported');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for unsupported recipient country
     */
    public function testValidateShipmentDataUnsupportedRecipientCountry(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['recipient']['country'] = 'XX'; // Unsupported country

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Recipient country XX is not supported');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for missing localTotalValue (MEEST requirement)
     */
    public function testValidateShipmentDataMissingLocalTotalValue(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        unset($invalidData['parcel']['value']['localTotalValue']);

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('parcel.value.localTotalValue is required');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for missing localCurrency (MEEST requirement)
     */
    public function testValidateShipmentDataMissingLocalCurrency(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        unset($invalidData['parcel']['value']['localCurrency']);

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('parcel.value.localCurrency is required');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for missing items.value.value (MEEST requirement)
     */
    public function testValidateShipmentDataMissingItemsValue(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        unset($invalidData['parcel']['items'][0]['value']['value']);

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('parcel.items.0.value.value is required');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for excessive weight
     */
    public function testValidateShipmentDataExcessiveWeight(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['weight'] = 35.0; // Exceeds 30kg limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Parcel weight cannot exceed 30.0 kg');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for excessive dimensions
     */
    public function testValidateShipmentDataExcessiveDimensions(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['length'] = 150.0; // Exceeds 120cm limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Parcel dimensions cannot exceed 120.0 cm per side');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for excessive value
     */
    public function testValidateShipmentDataExcessiveValue(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['value']['localTotalValue'] = 15000.0; // Exceeds 10000 limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Parcel value cannot exceed 10000.00 per currency');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for negative weight
     */
    public function testValidateShipmentDataNegativeWeight(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['weight'] = -1.0;

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Parcel weight must be positive');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for negative value
     */
    public function testValidateShipmentDataNegativeValue(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['value']['localTotalValue'] = -10.0;

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Parcel value must be positive');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for invalid email format
     */
    public function testValidateShipmentDataInvalidEmail(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['sender']['email'] = 'invalid-email-format';

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Invalid email format for sender');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation failure for invalid phone format
     */
    public function testValidateShipmentDataInvalidPhone(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['recipient']['phone'] = '123'; // Too short

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Invalid phone format for recipient');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test business rule: High value shipments require signature
     */
    public function testValidateHighValueShipmentRequiresSignature(): void
    {
        $highValueData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $highValueData['parcel']['value']['localTotalValue'] = 1500.0; // High value
        $highValueData['options']['require_signature'] = false; // But no signature required

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('High value shipments (>1000) require signature');

        $this->validator->validateShipmentData($highValueData);
    }

    /**
     * Test business rule: Return shipments require original tracking number
     */
    public function testValidateReturnShipmentRequiresOriginalTracking(): void
    {
        $returnData = MeestApiResponseFixtures::getCreateReturnShipmentRequest();
        unset($returnData['original_tracking_number']);

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Return shipments require original_tracking_number');

        $this->validator->validateShipmentData($returnData);
    }

    /**
     * Test business rule: Currency must match destination country
     */
    public function testValidateCurrencyMustMatchDestination(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['recipient']['country'] = 'PL'; // Poland
        $invalidData['parcel']['value']['localCurrency'] = 'USD'; // But using USD instead of PLN

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Currency USD does not match destination country PL (expected PLN)');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test business rule: Delivery date within 30 days
     */
    public function testValidateDeliveryDateWithin30Days(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['delivery_date'] = (new \DateTimeImmutable('+40 days'))->format('Y-m-d'); // 40 days in future

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Delivery date must be within 30 days');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test valid delivery date within limits
     */
    public function testValidateDeliveryDateValid(): void
    {
        $validData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $validData['delivery_date'] = (new \DateTimeImmutable('+15 days'))->format('Y-m-d'); // Valid date

        // Should not throw exception
        $this->validator->validateShipmentData($validData);
        $this->assertTrue(true);
    }

    /**
     * Test string length validation for reference
     */
    public function testValidateReferenceLengthLimit(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['reference'] = str_repeat('A', 101); // Exceeds 100 character limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Reference cannot exceed 100 characters');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test string length validation for special instructions
     */
    public function testValidateSpecialInstructionsLengthLimit(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['special_instructions'] = str_repeat('A', 501); // Exceeds 500 character limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Special instructions cannot exceed 500 characters');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test string length validation for contents
     */
    public function testValidateContentsLengthLimit(): void
    {
        $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
        $invalidData['parcel']['contents'] = str_repeat('A', 501); // Exceeds 500 character limit

        $this->expectException(MeestValidationException::class);
        $this->expectExceptionMessage('Contents description cannot exceed 500 characters');

        $this->validator->validateShipmentData($invalidData);
    }

    /**
     * Test validation for required sender fields
     */
    public function testValidateRequiredSenderFields(): void
    {
        $requiredFields = ['first_name', 'last_name', 'phone', 'email', 'country', 'city', 'address', 'postal_code'];

        foreach ($requiredFields as $field) {
            $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
            unset($invalidData['sender'][$field]);

            $this->expectException(MeestValidationException::class);
            $this->expectExceptionMessage("Missing required sender field: {$field}");

            try {
                $this->validator->validateShipmentData($invalidData);
            } catch (MeestValidationException $e) {
                $this->assertStringContains($field, $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Test validation for required recipient fields
     */
    public function testValidateRequiredRecipientFields(): void
    {
        $requiredFields = ['first_name', 'last_name', 'phone', 'email', 'country', 'city', 'address', 'postal_code'];

        foreach ($requiredFields as $field) {
            $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
            unset($invalidData['recipient'][$field]);

            $this->expectException(MeestValidationException::class);

            try {
                $this->validator->validateShipmentData($invalidData);
            } catch (MeestValidationException $e) {
                $this->assertStringContains($field, $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Test validation for required parcel fields
     */
    public function testValidateRequiredParcelFields(): void
    {
        $requiredFields = ['weight', 'contents'];

        foreach ($requiredFields as $field) {
            $invalidData = MeestApiResponseFixtures::getCreateShipmentRequest();
            unset($invalidData['parcel'][$field]);

            $this->expectException(MeestValidationException::class);

            try {
                $this->validator->validateShipmentData($invalidData);
            } catch (MeestValidationException $e) {
                $this->assertStringContains($field, $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Test postal code validation for different countries
     */
    public function testValidatePostalCodeFormats(): void
    {
        $validPostalCodes = [
            'PL' => ['00-001', '30-001', '80-001'],
            'US' => ['10001', '90210', '12345-6789'],
            'GB' => ['SW1A 1AA', 'M1 1AA', 'B33 8TH'],
            'DE' => ['10115', '80331', '20095']
        ];

        foreach ($validPostalCodes as $country => $codes) {
            foreach ($codes as $postalCode) {
                $validData = MeestApiResponseFixtures::getCreateShipmentRequest();
                $validData['sender']['country'] = $country;
                $validData['sender']['postal_code'] = $postalCode;
                $validData['recipient']['country'] = $country;
                $validData['recipient']['postal_code'] = $postalCode;

                if ($country !== 'US') {
                    // Adjust currency for non-US countries
                    $currency = $this->validator->getCurrencyForCountry($country);
                    $validData['parcel']['value']['localCurrency'] = $currency;
                }

                // Should not throw exception for valid postal codes
                $this->validator->validateShipmentData($validData);
                $this->assertTrue(true, "Postal code {$postalCode} should be valid for {$country}");
            }
        }
    }

    /**
     * Test getting supported countries list
     */
    public function testGetSupportedCountries(): void
    {
        $countries = $this->validator->getSupportedCountries();

        $this->assertIsArray($countries);
        $this->assertNotEmpty($countries);

        // Verify expected countries are included
        $expectedCountries = ['PL', 'US', 'DE', 'GB', 'FR'];
        foreach ($expectedCountries as $country) {
            $this->assertContains($country, $countries, "Country {$country} should be in supported countries list");
        }

        // Verify all entries are 2-character country codes
        foreach ($countries as $country) {
            $this->assertIsString($country);
            $this->assertEquals(2, strlen($country), "Country code {$country} should be 2 characters");
            $this->assertEquals(strtoupper($country), $country, "Country code {$country} should be uppercase");
        }
    }

    /**
     * Test validation error collection for multiple issues
     */
    public function testValidationErrorCollection(): void
    {
        $invalidData = [
            'sender' => [
                'first_name' => '', // Empty
                'email' => 'invalid-email', // Invalid format
                'country' => 'XX' // Unsupported
            ],
            'recipient' => [
                // Missing required fields
            ],
            'parcel' => [
                'weight' => -5.0, // Negative
                'value' => [
                    'localTotalValue' => 15000.0 // Too high
                ]
            ]
        ];

        try {
            $this->validator->validateShipmentData($invalidData);
            $this->fail('Should have thrown MeestValidationException');
        } catch (MeestValidationException $e) {
            $errors = $e->getValidationErrors();
            $this->assertIsArray($errors);
            $this->assertGreaterThan(1, count($errors), 'Should collect multiple validation errors');
        }
    }
}