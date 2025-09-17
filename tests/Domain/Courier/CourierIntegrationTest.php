<?php

namespace App\Tests\Domain\Courier;

use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\Exception\CourierIntegrationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CourierIntegrationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testShipmentRequestValidation()
    {
        $validData = $this->getValidData();

        $shipmentRequest = ShipmentRequestDTO::fromArray($validData);
        $violations = $this->validator->validate($shipmentRequest);

        $this->assertCount(0, $violations, 'Validation should pass for valid data');
    }

    public function testShipmentRequestInvalidData()
    {
        $invalidData = [
            'senderName' => '',
            'senderEmail' => 'invalid-email',
            'senderAddress' => '',
            'recipientName' => '',
            'recipientEmail' => 'another-invalid-email',
            'recipientAddress' => '',
            'weight' => -1,
            'serviceType' => 'unknown'
        ];

        $shipmentRequest = ShipmentRequestDTO::fromArray($invalidData);
        $violations = $this->validator->validate($shipmentRequest);

        $this->assertGreaterThan(0, count($violations), 'Validation should fail for invalid data');

        // Check for specific validation errors
        $violationMessages = [];
        foreach ($violations as $violation) {
            $violationMessages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        // Verify we have expected validation errors
        $this->assertStringContainsString('senderName:', implode(', ', $violationMessages));
        $this->assertStringContainsString('senderEmail:', implode(', ', $violationMessages));
        $this->assertStringContainsString('weight:', implode(', ', $violationMessages));
        $this->assertStringContainsString('serviceType:', implode(', ', $violationMessages));
    }

    public function testShipmentRequestSpecificValidationConstraints()
    {
        // Test individual validation constraints

        // Test empty senderName
        $data = $this->getValidData();
        $data['senderName'] = '';
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test invalid email
        $data = $this->getValidData();
        $data['senderEmail'] = 'invalid-email';
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test negative weight
        $data = $this->getValidData();
        $data['weight'] = -1;
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test invalid service type
        $data = $this->getValidData();
        $data['serviceType'] = 'invalid';
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test sender name too short (less than 2 characters)
        $data = $this->getValidData();
        $data['senderName'] = 'A';
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test sender name too long (more than 100 characters)
        $data = $this->getValidData();
        $data['senderName'] = str_repeat('A', 101);
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));

        // Test zero weight (should fail because of Positive constraint)
        $data = $this->getValidData();
        $data['weight'] = 0;
        $shipmentRequest = ShipmentRequestDTO::fromArray($data);
        $violations = $this->validator->validate($shipmentRequest);
        $this->assertGreaterThan(0, count($violations));
    }

    private function getValidData(): array
    {
        return [
            'senderName' => 'John Doe',
            'senderEmail' => 'john@example.com',
            'senderAddress' => '123 Sender St, City, Country',
            'recipientName' => 'Jane Smith',
            'recipientEmail' => 'jane@example.com',
            'recipientAddress' => '456 Recipient Rd, City, Country',
            'weight' => 5.5,
            'serviceType' => 'standard'
        ];
    }

    public function testCourierIntegrationException()
    {
        $context = ['request_id' => '12345', 'endpoint' => '/shipments'];
        $exception = new CourierIntegrationException(
            'API Error', 
            500, 
            null, 
            $context
        );

        $this->assertEquals('API Error', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
        $this->assertEquals($context, $exception->getContext());

        $exceptionArray = $exception->toArray();
        $this->assertArrayHasKey('message', $exceptionArray);
        $this->assertArrayHasKey('code', $exceptionArray);
        $this->assertArrayHasKey('trace', $exceptionArray);
        $this->assertArrayHasKey('context', $exceptionArray);
    }
}