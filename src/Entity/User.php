<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Generic User interface for the payment system
 * This serves as a base interface for all user types (SystemUser, CustomerUser, etc.)
 */
interface User extends UserInterface
{
    public function getId(): ?int;
    public function getEmail(): ?string;
    public function getFirstName(): ?string;
    public function getLastName(): ?string;
    public function getFullName(): string;
}