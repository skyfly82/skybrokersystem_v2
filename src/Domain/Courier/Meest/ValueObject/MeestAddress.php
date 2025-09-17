<?php

declare(strict_types=1);

namespace App\Domain\Courier\Meest\ValueObject;

use App\Domain\Courier\Meest\Exception\MeestValidationException;

/**
 * Value Object representing MEEST address
 */
readonly class MeestAddress
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $phone,
        public string $email,
        public string $country,
        public string $city,
        public string $address,
        public string $postalCode,
        public ?string $company = null,
        public ?string $region1 = null
    ) {
        $this->validateAddress();
    }

    private function validateAddress(): void
    {
        if (empty($this->firstName)) {
            throw new MeestValidationException('First name cannot be empty');
        }

        if (empty($this->lastName)) {
            throw new MeestValidationException('Last name cannot be empty');
        }

        if (empty($this->phone)) {
            throw new MeestValidationException('Phone cannot be empty');
        }

        if (empty($this->email) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new MeestValidationException('Valid email is required');
        }

        if (empty($this->country) || strlen($this->country) !== 2) {
            throw new MeestValidationException('Country must be a valid 2-letter ISO code');
        }

        if (empty($this->city)) {
            throw new MeestValidationException('City cannot be empty');
        }

        if (empty($this->address)) {
            throw new MeestValidationException('Address cannot be empty');
        }

        if (empty($this->postalCode)) {
            throw new MeestValidationException('Postal code cannot be empty');
        }
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function toArray(): array
    {
        $data = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
            'company' => $this->company
        ];

        if ($this->region1) {
            $data['region1'] = $this->region1;
        }

        return $data;
    }
}