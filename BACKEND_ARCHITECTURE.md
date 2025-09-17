# Backend Architecture - Customer Dashboard & Shipment Ordering System

## Overview

This document outlines the complete backend architecture for the customer dashboard and shipment ordering system built with Symfony 7.x, following Domain-Driven Design (DDD) and clean architecture principles.

## Architecture Components

### 1. API Controllers Structure

#### Customer Dashboard API (`/api/v1/customer/dashboard`)
- **DashboardController**: Comprehensive dashboard statistics and metrics
  - `GET /stats` - Overall dashboard statistics
  - `GET /shipments/stats` - Shipment-specific statistics
  - `GET /revenue/analytics` - Revenue analytics with time grouping
  - `GET /activity` - Recent activity feed
  - `GET /performance` - Performance metrics comparison
  - `GET /couriers/usage` - Courier service usage statistics

#### Shipment Management API (`/api/v1/customer/shipments`)
- **ShipmentController**: Complete shipment lifecycle management
  - `GET /` - List shipments with filtering and pagination
  - `POST /` - Create new shipment (4-step workflow)
  - `GET /{id}` - Get detailed shipment information
  - `PUT /{id}` - Update shipment details
  - `POST /{id}/cancel` - Cancel shipment
  - `GET /{id}/tracking` - Get tracking information
  - `GET /{id}/label` - Download shipment label
  - `POST /calculate-price` - Real-time pricing calculation
  - `POST /bulk` - Bulk operations on shipments

#### Address Book Management API (`/api/v1/customer/addresses`)
- **AddressBookController**: Address management for efficient shipment creation
  - `GET /` - List customer addresses with filtering
  - `POST /` - Create new address
  - `GET /{id}` - Get address details
  - `PUT /{id}` - Update address
  - `DELETE /{id}` - Delete address
  - `POST /validate` - Validate address against courier services
  - `POST /suggest` - Get address suggestions
  - `POST /{id}/set-default` - Set default address
  - `POST /import` - Import addresses from external sources
  - `GET /export` - Export addresses

#### Payment & Balance Management API (`/api/v1/customer/payments`)
- **PaymentController**: Payment processing and balance management
  - `GET /balance` - Get current balance information
  - `GET /balance/history` - Get balance transaction history
  - `POST /balance/topup` - Top up account balance
  - `GET /` - List customer payments
  - `GET /{id}` - Get payment details
  - `GET /invoices` - List customer invoices
  - `GET /invoices/{id}` - Get invoice details
  - `POST /invoices/{id}/pay` - Pay invoice
  - `GET /invoices/{id}/download` - Download invoice PDF
  - `GET /analytics` - Payment analytics
  - `POST /recurring/setup` - Setup recurring payments

### 2. Domain Services

#### CustomerDashboardService
```php
- getComprehensiveStats(int $customerId, int $periodDays): array
- getShipmentStatistics(int $customerId, int $periodDays): array
- getFinancialStatistics(int $customerId, int $periodDays): array
- getPerformanceMetrics(int $customerId, int $periodDays): array
- getCourierServiceUsage(int $customerId, int $periodDays): array
- getRecentActivity(int $customerId, int $limit, int $offset): array
- getRevenueAnalytics(int $customerId, int $periodDays, string $groupBy): array
- getPerformanceComparison(int $customerId, int $currentPeriod, int $comparePeriod): array
```

#### PricingCalculatorService
```php
- calculateShipmentPricing(array $shipmentData): array
- calculateBulkPricing(array $shipmentsData): array
- comparePricing(array $shipmentData, array $courierCodes): array
- getBestPriceOption(array $shipmentData): array
- validateCarrierRequirements(string $carrierCode, array $shipmentData): array
```

#### ShipmentService
```php
- getCustomerShipments(int $customerId, array $filters, array $pagination): array
- getCustomerShipment(int $customerId, int $shipmentId): ?Shipment
- createShipment(int $customerId, array $data): Shipment
- updateShipment(Shipment $shipment, array $data): Shipment
- cancelShipment(Shipment $shipment, string $reason): void
- getTrackingInformation(Shipment $shipment): array
- getShipmentLabel(Shipment $shipment): array
- performBulkAction(int $customerId, string $action, array $shipmentIds, array $data): array
```

### 3. Enhanced Database Entities

#### CustomerAddress Entity
```php
class CustomerAddress
{
    private int $id;
    private Customer $customer;
    private string $name;                    // Address nickname/label
    private string $type;                    // 'sender', 'recipient', 'both'
    private string $contactName;
    private ?string $companyName;
    private string $email;
    private string $phone;
    private string $address;
    private string $postalCode;
    private string $city;
    private string $country;
    private ?string $additionalInfo;
    private bool $isDefault;
    private bool $isActive;
    private bool $isValidated;
    private ?array $validationData;          // Courier validation results
    private int $usageCount;                 // Track address usage
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $lastUsedAt;
}
```

#### CustomerBalance Entity
```php
class CustomerBalance
{
    private int $id;
    private Customer $customer;
    private string $currentBalance;
    private string $creditLimit;
    private string $availableCredit;
    private string $reservedAmount;          // Reserved for pending shipments
    private string $totalSpent;              // Lifetime spending
    private string $totalTopUps;             // Lifetime top-ups
    private string $currency;
    private bool $autoTopUpEnabled;
    private ?string $autoTopUpTrigger;       // Balance threshold
    private ?string $autoTopUpAmount;        // Auto top-up amount
    private ?string $autoTopUpMethod;        // Payment method
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $lastTopUpAt;
    private ?\DateTimeImmutable $lastTransactionAt;
}
```

#### Enhanced Shipment Entity (existing, with optimizations)
- Comprehensive tracking support
- Multi-courier integration
- Real-time status updates
- Label management
- Cost tracking and analytics

### 4. API Routes Design

The API follows RESTful principles with clear resource-based URLs:

```yaml
# Dashboard & Analytics
GET    /api/v1/customer/dashboard/stats
GET    /api/v1/customer/dashboard/shipments/stats
GET    /api/v1/customer/dashboard/revenue/analytics

# Shipment Management
GET    /api/v1/customer/shipments
POST   /api/v1/customer/shipments
GET    /api/v1/customer/shipments/{id}
PUT    /api/v1/customer/shipments/{id}
POST   /api/v1/customer/shipments/{id}/cancel
GET    /api/v1/customer/shipments/{id}/tracking
GET    /api/v1/customer/shipments/{id}/label

# Address Management
GET    /api/v1/customer/addresses
POST   /api/v1/customer/addresses
GET    /api/v1/customer/addresses/{id}
PUT    /api/v1/customer/addresses/{id}
DELETE /api/v1/customer/addresses/{id}

# Payment & Balance
GET    /api/v1/customer/payments/balance
POST   /api/v1/customer/payments/balance/topup
GET    /api/v1/customer/payments/invoices
POST   /api/v1/customer/payments/invoices/{id}/pay

# Pricing & Validation
POST   /api/v1/pricing/calculate
POST   /api/v1/pricing/compare
POST   /api/v1/addresses/validate
```

### 5. 4-Step Shipment Ordering Workflow

#### Step 1: Address Selection/Creation
- Choose from address book or enter new addresses
- Real-time address validation
- Auto-complete suggestions

#### Step 2: Package Details & Pricing
- Enter package dimensions and weight
- Select service type and options
- Real-time pricing calculation across all couriers
- Best price recommendations

#### Step 3: Service Selection & Confirmation
- Compare courier services and prices
- Select preferred service
- Add special instructions
- Review order summary

#### Step 4: Payment & Finalization
- Choose payment method (balance/card)
- Process payment
- Create shipment with courier
- Generate tracking number and label

### 6. Key Features

#### Real-time Pricing Integration
- **InPost API Integration**: Direct pricing and service selection
- **Multi-Courier Support**: DHL, InPost, and future couriers
- **Bulk Pricing**: Discounts for multiple shipments
- **Price Comparison**: Side-by-side courier comparison

#### Comprehensive Dashboard
- **Statistics Dashboard**: Shipment counts, success rates, costs
- **Revenue Analytics**: Time-based revenue analysis with charts
- **Performance Metrics**: On-time delivery, cancellation rates
- **Activity Feed**: Recent shipments and status changes
- **Courier Usage**: Breakdown by courier service

#### Advanced Address Management
- **Smart Address Book**: Autocomplete and usage tracking
- **Address Validation**: Real-time validation against courier APIs
- **Default Addresses**: Quick selection for frequent use
- **Import/Export**: Bulk address management
- **Usage Analytics**: Most used addresses for optimization

#### Balance & Payment System
- **Real-time Balance**: Current balance with credit limit tracking
- **Auto Top-up**: Automatic balance replenishment
- **Payment History**: Complete transaction history
- **Invoice Management**: PDF generation and payment processing
- **Multiple Payment Methods**: PayNow, Stripe, bank transfer

### 7. Integration Points

#### InPost API Integration
- **Shipment Creation**: Direct integration with InPost API
- **Parcel Locker Support**: Location selection and booking
- **Real-time Tracking**: Status updates via webhooks
- **Label Generation**: PDF label creation and download

#### Future Courier Integrations
- **DHL Express**: International shipping
- **Additional Couriers**: Extensible architecture for new providers

#### Payment Gateway Integration
- **PayNow**: Polish payment system
- **Stripe**: International payment processing
- **Bank Transfers**: Direct bank integration

### 8. Security & Performance

#### Authentication & Authorization
- **JWT-based Authentication**: Secure API access
- **Role-based Access Control**: Customer user roles
- **API Rate Limiting**: Prevent abuse
- **Input Validation**: Comprehensive data validation

#### Performance Optimizations
- **Database Indexing**: Optimized queries for large datasets
- **Caching Strategy**: Redis caching for frequent data
- **Pagination**: Efficient data loading
- **Background Processing**: Async operations for heavy tasks

#### Data Protection
- **Encryption**: Sensitive data encryption
- **Audit Logging**: Complete audit trail
- **GDPR Compliance**: Data protection compliance
- **Backup Strategy**: Regular data backups

### 9. Monitoring & Analytics

#### Application Monitoring
- **Performance Metrics**: Response times and throughput
- **Error Tracking**: Comprehensive error logging
- **API Analytics**: Usage patterns and optimization
- **Health Checks**: System health monitoring

#### Business Analytics
- **Revenue Tracking**: Financial performance metrics
- **Customer Behavior**: Usage patterns and trends
- **Courier Performance**: Service quality metrics
- **Operational Efficiency**: Process optimization insights

### 10. Future Extensibility

#### Microservices Architecture
- **Service Separation**: Clear domain boundaries
- **API Gateway**: Centralized API management
- **Event-Driven Communication**: Async message processing
- **Scalable Infrastructure**: Horizontal scaling support

#### Additional Features
- **Mobile API**: Native mobile app support
- **Webhook System**: Real-time notifications
- **Third-party Integrations**: ERP and accounting systems
- **Multi-tenant Support**: Enterprise customer support

## Conclusion

This backend architecture provides a robust, scalable foundation for the customer dashboard and shipment ordering system. The design follows modern architectural patterns, ensures high performance, and provides extensive flexibility for future enhancements and integrations.

The 4-step ordering workflow, comprehensive dashboard analytics, and real-time pricing integration create a superior user experience while maintaining system reliability and performance.