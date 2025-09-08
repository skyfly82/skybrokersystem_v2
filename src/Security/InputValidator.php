<?php

namespace App\Security;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Security-focused input validation and sanitization
 * Implements OWASP Input Validation principles
 */
class InputValidator
{
    private ValidatorInterface $validator;
    
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validate and sanitize email input
     */
    public function validateEmail(?string $email): array
    {
        if (!$email) {
            return ['valid' => false, 'error' => 'Email is required'];
        }

        $email = $this->sanitizeInput($email);
        
        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(['message' => 'Email cannot be blank']),
            new Assert\Email(['message' => 'Invalid email format']),
            new Assert\Length(['max' => 180, 'maxMessage' => 'Email too long'])
        ]);

        if (count($violations) > 0) {
            return ['valid' => false, 'error' => $violations[0]->getMessage()];
        }

        return ['valid' => true, 'value' => $email];
    }

    /**
     * Validate password strength
     */
    public function validatePassword(?string $password): array
    {
        if (!$password) {
            return ['valid' => false, 'error' => 'Password is required'];
        }

        // Check password strength
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
        }

        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one number'];
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one special character'];
        }

        return ['valid' => true, 'value' => $password];
    }

    /**
     * Validate NIP (Polish tax number)
     */
    public function validateNip(?string $nip): array
    {
        if (!$nip) {
            return ['valid' => false, 'error' => 'NIP is required'];
        }

        $nip = preg_replace('/[^0-9]/', '', $nip);
        
        if (strlen($nip) !== 10) {
            return ['valid' => false, 'error' => 'NIP must be exactly 10 digits'];
        }

        // NIP checksum validation
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $nip[$i] * $weights[$i];
        }
        
        $checksum = $sum % 11;
        if ($checksum == 10) {
            $checksum = 0;
        }

        if ($checksum != $nip[9]) {
            return ['valid' => false, 'error' => 'Invalid NIP checksum'];
        }

        return ['valid' => true, 'value' => $nip];
    }

    /**
     * Validate phone number
     */
    public function validatePhone(?string $phone): array
    {
        if (!$phone) {
            return ['valid' => true, 'value' => null]; // Phone is optional
        }

        $phone = $this->sanitizeInput($phone);
        
        $violations = $this->validator->validate($phone, [
            new Assert\Regex([
                'pattern' => '/^[+]?[0-9\s\-\(\)]{7,20}$/',
                'message' => 'Invalid phone number format'
            ]),
            new Assert\Length(['max' => 20, 'maxMessage' => 'Phone number too long'])
        ]);

        if (count($violations) > 0) {
            return ['valid' => false, 'error' => $violations[0]->getMessage()];
        }

        return ['valid' => true, 'value' => $phone];
    }

    /**
     * Validate and sanitize name fields
     */
    public function validateName(?string $name, string $fieldName = 'name'): array
    {
        if (!$name) {
            return ['valid' => false, 'error' => ucfirst($fieldName) . ' is required'];
        }

        $name = $this->sanitizeInput($name);
        
        $violations = $this->validator->validate($name, [
            new Assert\NotBlank(['message' => ucfirst($fieldName) . ' cannot be blank']),
            new Assert\Length(['min' => 2, 'max' => 100, 'minMessage' => ucfirst($fieldName) . ' too short', 'maxMessage' => ucfirst($fieldName) . ' too long']),
            new Assert\Regex([
                'pattern' => '/^[a-zA-Z\s\-\'\.]+$/',
                'message' => ucfirst($fieldName) . ' contains invalid characters'
            ])
        ]);

        if (count($violations) > 0) {
            return ['valid' => false, 'error' => $violations[0]->getMessage()];
        }

        return ['valid' => true, 'value' => $name];
    }

    /**
     * Validate JSON input
     */
    public function validateJson(string $json): array
    {
        if (empty($json)) {
            return ['valid' => false, 'error' => 'Request body cannot be empty'];
        }

        // Check for potential JSON bombs (excessive nesting or size)
        if (strlen($json) > 1048576) { // 1MB limit
            return ['valid' => false, 'error' => 'Request too large'];
        }

        $data = json_decode($json, true, 10); // Max depth of 10
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['valid' => false, 'error' => 'Invalid JSON format'];
        }

        return ['valid' => true, 'value' => $data];
    }

    /**
     * Sanitize input to prevent XSS
     */
    public function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove null bytes and control characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }

    /**
     * Validate allowed roles
     */
    public function validateRole(?string $role, array $allowedRoles): array
    {
        if (!$role) {
            return ['valid' => false, 'error' => 'Role is required'];
        }

        if (!in_array($role, $allowedRoles, true)) {
            return ['valid' => false, 'error' => 'Invalid role specified'];
        }

        return ['valid' => true, 'value' => $role];
    }

    /**
     * Validate status values
     */
    public function validateStatus(?string $status, array $allowedStatuses = ['active', 'inactive']): array
    {
        if (!$status) {
            return ['valid' => false, 'error' => 'Status is required'];
        }

        if (!in_array($status, $allowedStatuses, true)) {
            return ['valid' => false, 'error' => 'Invalid status specified'];
        }

        return ['valid' => true, 'value' => $status];
    }

    /**
     * Check for SQL injection patterns
     */
    public function detectSqlInjection(string $input): bool
    {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/(\bexec\b|\bexecute\b)/i',
            '/(\bor\b.*=.*)/i',
            '/(\band\b.*=.*)/i',
            '/(\'.*\')|(".*")/i',
            '/(--)|(\/\*.*\*\/)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}