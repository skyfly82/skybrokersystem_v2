# Credit Payment System Implementation

## Overview

This document describes the complete implementation of the deferred payment (credit limit) system for the Sky Broker System. The system follows Domain-Driven Design (DDD) principles and provides comprehensive credit account management with configurable terms, limits, and payment processing.

## Architecture

### Domain Structure
```
src/Domain/Payment/
├── Entity/
│   ├── Payment.php (existing)
│   ├── CreditAccount.php
│   └── CreditTransaction.php
├── Repository/
│   ├── CreditAccountRepository.php
│   └── CreditTransactionRepository.php
├── DTO/
│   ├── CreditAuthorizationRequestDTO.php
│   ├── CreditAuthorizationResponseDTO.php
│   ├── CreditSettlementRequestDTO.php
│   ├── CreditSettlementResponseDTO.php
│   └── CreditAccountStatusDTO.php
├── Contracts/
│   └── CreditServiceInterface.php
├── Service/
│   ├── CreditService.php
│   └── CreditPaymentHandler.php
├── Exception/
│   └── CreditException.php
└── Command/
    ├── ProcessOverdueCreditTransactionsCommand.php
    └── CreditAccountManagementCommand.php
```

## Core Features

### 1. Credit Account Management
- **Account Types**: Individual and business credit accounts
- **Status Management**: Pending approval, active, suspended, closed
- **Credit Limits**: Configurable with overdraft protection
- **Payment Terms**: NET 15, 30, 45, 60, 90 days
- **Multi-currency Support**: PLN, EUR, USD, GBP

### 2. Payment Processing
- **Authorization**: Reserve credit limit for pending payments
- **Settlement**: Finalize authorized payments and create charges
- **Cancellation**: Cancel authorized payments and release holds
- **Refunds**: Process refunds and restore available credit

### 3. Credit Monitoring
- **Overdue Tracking**: Automatic detection of overdue payments
- **Interest Calculation**: Configurable interest rates for overdue amounts
- **Overdraft Fees**: Automatic fees for overdrafted accounts
- **Account Reviews**: Scheduled credit limit reviews

## Entity Relationships

### CreditAccount
```php
- id: int
- user: User (ManyToOne)
- accountNumber: string (unique)
- accountType: string (individual/business)
- status: string (pending_approval/active/suspended/closed)
- creditLimit: decimal(10,2)
- availableCredit: decimal(10,2)
- usedCredit: decimal(10,2)
- overdraftLimit: decimal(10,2)
- paymentTermDays: int
- currency: string
- interestRate: decimal(5,2)
- overdraftFee: decimal(5,2)
- timestamps and metadata
```

### CreditTransaction
```php
- id: int
- creditAccount: CreditAccount (ManyToOne)
- payment: Payment (ManyToOne, nullable)
- transactionId: string (unique)
- transactionType: string (authorization/charge/payment/refund/adjustment/fee/interest)
- status: string (pending/authorized/settled/failed/cancelled/refunded/overdue)
- amount: decimal(10,2)
- currency: string
- dueDate: DateTimeImmutable
- timestamps and metadata
```

## Payment Flow

### 1. Authorization Flow
```php
// 1. Create payment request
$request = new CreditAuthorizationRequestDTO([
    'payment_id' => 'PAY_12345',
    'amount' => '500.00',
    'currency' => 'PLN',
    'description' => 'Shipment payment',
    'payment_term_days' => 30
]);

// 2. Authorize payment
$response = $creditService->authorizePayment($user, $request);

// 3. Check authorization result
if ($response->isSuccess()) {
    // Payment authorized - credit limit reserved
    $transactionId = $response->getTransactionId();
    $dueDate = $response->getDueDate();
}
```

### 2. Settlement Flow
```php
// 1. Create settlement request
$request = new CreditSettlementRequestDTO([
    'transaction_id' => $transactionId,
    'settle_amount' => '500.00', // optional, defaults to full amount
    'notes' => 'Payment settled'
]);

// 2. Settle payment
$response = $creditService->settlePayment($request);

// 3. Check settlement result
if ($response->isSuccess()) {
    // Payment settled - charge created with due date
    $settledAmount = $response->getSettledAmount();
    $settledAt = $response->getSettledAt();
}
```

### 3. Account Status Check
```php
$statusDTO = $creditService->getCreditAccountStatus($user);

echo "Available Credit: " . $statusDTO->getAvailableCredit();
echo "Credit Utilization: " . $statusDTO->getCreditUtilization() . "%";
echo "Overdue Transactions: " . $statusDTO->getOverdueTransactions();
```

## Configuration

### Environment Variables
Add to your `.env` file:
```env
# Credit Service Configuration
CREDIT_ENABLED=true
```

### Service Configuration
Services are automatically registered in `config/services.yaml`:
```yaml
App\Domain\Payment\Contracts\CreditServiceInterface:
    alias: App\Domain\Payment\Service\CreditService

App\Domain\Payment\Service\CreditService:
    arguments:
        - '@App\Domain\Payment\Repository\CreditAccountRepository'
        - '@App\Domain\Payment\Repository\CreditTransactionRepository'
        - '@doctrine.orm.entity_manager'
        - '@monolog.logger'
        - '%env(bool:CREDIT_ENABLED)%'
```

## Database Migration

Run the migration to create the credit tables:
```bash
php bin/console doctrine:migrations:migrate
```

This creates:
- `v2_credit_accounts` - Credit account information
- `v2_credit_transactions` - All credit-related transactions

## API Endpoints

### Credit Payment Management
```
POST   /api/payments/credit/create           - Create and authorize credit payment
POST   /api/payments/credit/settle/{id}      - Settle authorized payment
POST   /api/payments/credit/cancel/{id}      - Cancel authorized payment
POST   /api/payments/credit/refund/{id}      - Process payment refund
GET    /api/payments/credit/status/{id}      - Get payment status
GET    /api/payments/credit/info             - Get service information
```

### Credit Account Management
```
POST   /api/payments/credit/account/create   - Create credit account
GET    /api/payments/credit/account/status   - Get account status
```

## Console Commands

### Process Overdue Transactions
```bash
# Process overdue transactions and apply fees/interest
php bin/console credit:process-overdue

# Dry run to see what would be processed
php bin/console credit:process-overdue --dry-run

# Limit processing to specific number of transactions
php bin/console credit:process-overdue --limit=50
```

### Account Management
```bash
# Create credit account
php bin/console credit:account-management create --user-id=1 --credit-limit=5000 --account-type=business

# Update credit limit
php bin/console credit:account-management update-limit --user-id=1 --credit-limit=10000 --reason="Increased limit"

# Suspend account
php bin/console credit:account-management suspend --user-id=1 --reason="Risk assessment"

# Show account status
php bin/console credit:account-management status --user-id=1

# List accounts
php bin/console credit:account-management list --status=active --limit=20
```

## Usage Examples

### Creating a Credit Payment
```bash
curl -X POST http://185.213.25.106/v2/api/payments/credit/create \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "amount": "250.00",
    "currency": "PLN",
    "description": "InPost shipment payment",
    "payment_term_days": 30
  }'
```

### Checking Account Status
```bash
curl -X GET "http://185.213.25.106/v2/api/payments/credit/account/status?user_id=1"
```

### Creating a Credit Account
```bash
curl -X POST http://185.213.25.106/v2/api/payments/credit/account/create \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "account_type": "business",
    "credit_limit": "5000.00",
    "payment_term_days": 45,
    "currency": "PLN"
  }'
```

## Error Handling

The system provides comprehensive error handling with specific error codes:

- `CREDIT_ACCOUNT_NOT_FOUND` - Credit account doesn't exist
- `CREDIT_ACCOUNT_INACTIVE` - Account is not active
- `INSUFFICIENT_CREDIT` - Not enough available credit
- `CREDIT_LIMIT_EXCEEDED` - Payment would exceed credit limit
- `AUTHORIZATION_FAILED` - Payment authorization failed
- `SETTLEMENT_FAILED` - Payment settlement failed
- `PAYMENT_OVERDUE` - Payment is overdue

## Security Features

1. **Input Validation**: All DTOs include validation constraints
2. **Transaction Integrity**: Database transactions ensure consistency
3. **Comprehensive Logging**: All operations are logged for audit
4. **Permission Checks**: Service validates account status before operations
5. **Error Masking**: Internal errors are logged but not exposed to clients

## Monitoring and Maintenance

### Key Metrics to Monitor
- Credit utilization rates
- Overdue payment amounts
- Account suspension rates
- Payment settlement success rates
- Interest and fee collections

### Scheduled Tasks
Set up cron jobs for:
```bash
# Process overdue transactions daily
0 6 * * * php /var/www/skybrokersystem_v2/bin/console credit:process-overdue

# Generate credit reports weekly
0 8 * * 1 php /var/www/skybrokersystem_v2/bin/console credit:generate-reports
```

## Integration Points

### With InPost Service
The credit system integrates with InPost shipment processing:
```php
// When creating shipment with credit payment
$creditPayment = $creditPaymentHandler->createPayment(
    $user,
    $shipmentCost,
    'PLN',
    "InPost shipment: {$shipmentId}"
);

// Settle when shipment is confirmed
if ($shipment->isConfirmed()) {
    $creditPaymentHandler->settlePayment($creditPayment['payment_id']);
}
```

### With Notification System
Credit events trigger notifications:
- Payment authorization confirmations
- Payment due date reminders
- Overdue payment alerts
- Credit limit warnings

## Testing

The implementation includes comprehensive error handling and logging. Test the system by:

1. Creating credit accounts with different configurations
2. Processing payments with various amounts and terms
3. Testing overdue processing with backdated transactions
4. Verifying refund processing
5. Testing account suspension and reactivation

## Future Enhancements

Potential future improvements:
1. **Automated Credit Scoring**: AI-based credit limit recommendations
2. **Payment Plans**: Installment payment options
3. **Credit Reporting**: Integration with credit bureaus
4. **Dynamic Interest Rates**: Market-based rate adjustments
5. **Multi-tenant Support**: Separate credit pools for different business units

---

## Summary

The credit payment system provides a complete, production-ready solution for deferred payments with:

✅ **Comprehensive Account Management** - Create, update, suspend, and close credit accounts
✅ **Flexible Payment Processing** - Authorization, settlement, cancellation, and refund flows  
✅ **Automated Overdue Handling** - Interest calculation and fee processing
✅ **Multi-currency Support** - PLN, EUR, USD, GBP with configurable limits
✅ **Configurable Terms** - NET 15/30/45/60/90 day payment terms
✅ **Console Management Tools** - Commands for account and transaction management
✅ **REST API Integration** - Complete API for frontend integration
✅ **Comprehensive Logging** - Full audit trail for compliance
✅ **Error Handling** - Robust exception handling with specific error codes
✅ **Database Migrations** - Ready-to-run database schema

The system is now ready for integration with the existing Sky Broker platform and can handle real-world credit payment scenarios with proper security, monitoring, and maintenance capabilities.