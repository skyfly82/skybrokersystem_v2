<?php

declare(strict_types=1);

namespace App\Enum;

enum SecretCategory: string
{
    case COURIER_API_KEYS = 'courier_api_keys';
    case WEBHOOK_TOKENS = 'webhook_tokens';
    case PAYMENT_KEYS = 'payment_keys';
    case INTERNAL_TOKENS = 'internal_tokens';
    case THIRD_PARTY_API = 'third_party_api';
    case DATABASE_CREDENTIALS = 'database_credentials';
    case EMAIL_SMTP = 'email_smtp';
    case SMS_API = 'sms_api';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::COURIER_API_KEYS => 'Courier API Keys',
            self::WEBHOOK_TOKENS => 'Webhook Tokens',
            self::PAYMENT_KEYS => 'Payment Keys',
            self::INTERNAL_TOKENS => 'Internal Tokens',
            self::THIRD_PARTY_API => 'Third Party API',
            self::DATABASE_CREDENTIALS => 'Database Credentials',
            self::EMAIL_SMTP => 'Email SMTP',
            self::SMS_API => 'SMS API',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::COURIER_API_KEYS => 'API keys for courier services (InPost, DHL, etc.)',
            self::WEBHOOK_TOKENS => 'Tokens for webhook verification',
            self::PAYMENT_KEYS => 'Payment processor keys (PayNow, Stripe)',
            self::INTERNAL_TOKENS => 'Internal service authentication tokens',
            self::THIRD_PARTY_API => 'Third-party service API credentials',
            self::DATABASE_CREDENTIALS => 'Database connection credentials',
            self::EMAIL_SMTP => 'SMTP server credentials',
            self::SMS_API => 'SMS service provider credentials',
        };
    }

    public static function getAllWithDetails(): array
    {
        $categories = [];
        foreach (self::cases() as $category) {
            $categories[$category->value] = [
                'name' => $category->getDisplayName(),
                'description' => $category->getDescription(),
            ];
        }
        return $categories;
    }
}