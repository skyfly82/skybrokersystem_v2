<?php

namespace App\Tests\Domain\Courier;

use App\Domain\Courier\DTO\ShipmentRequestDTO;
use App\Domain\Courier\Exception\CourierIntegrationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class CourierIntegrationTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
    }

    public function testShipmentRequestValidation()
    {
        $validData = [
            'senderName' => 'John Doe',
            'senderEmail' => 'john@example.com',
            'senderAddress' => '123 Sender St, City, Country',
            'recipientName' => 'Jane Smith',
            'recipientEmail' => 'jane@example.com',
            'recipientAddress' => '456 Recipient Rd, City, Country',
            'weight' => 5.5,
            'serviceType' => 'standard'
        ];

        $shipmentRequest = ShipmentRequestDTO::fromArray($validData);
        $violations = $this->validator->validate($shipmentRequest);

        $this->assertCount(0, $violations, 'Validation should pass for valid data');
    }

    public function testShipmentRequestInvalidData()
    {
        $invalidData = [
            'senderName' => '',
            'senderEmail' => 'invalid-email',
            'weight' => -1,
            'serviceType' => 'unknown'
        ];

        $shipmentRequest = ShipmentRequestDTO::fromArray($invalidData);
        $violations = $this->validator->validate($shipmentRequest);

        $this->assertGreaterThan(0, count($violations), 'Validation should fail for invalid data');
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