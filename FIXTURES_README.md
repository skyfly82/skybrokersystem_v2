# Data Fixtures for Admin Dashboard Testing

This document provides information about the sample data created for testing the Sky Broker admin dashboard system.

## Quick Start

### Load Sample Data
```bash
# Drop and recreate database (if needed)
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force

# Load fixtures
php bin/console doctrine:fixtures:load --no-interaction
```

### Admin Login Credentials
- **URL**: http://185.213.25.106/v2/admin/dashboard
- **Email**: `admin@skybroker.com`
- **Password**: `admin123`

## Sample Data Overview

### System Users (5 records)
- **1 Admin User**: Main administrative account with full access
- **4 Department Users**: Support, Sales, Operations, and Marketing representatives
- All users have password: `password123` (except admin: `admin123`)

### Customers (13 records)
- **10 Business Customers**: Polish companies with VAT numbers and REGON
- **3 Individual Customers**: Private persons
- Realistic company names, addresses, and contact information
- Mix of active (90%) and inactive (10%) statuses

### Customer Users (22 records)
- **Business Customer Users**: 1-3 users per business customer
- **Roles**: Owner, Manager, Employee
- Password for all customer users: `customer123`
- Email format: `{firstname}.{lastname}@{company}.com`

### Orders (80 records)
- **Realistic Distribution**: Pending (10%), Confirmed (15%), Processing (20%), Shipped (25%), Delivered (25%), Canceled (5%)
- **Order Items**: 1-5 items per order with real product names
- **Date Range**: Created over the last 90 days
- **Products**: Electronics, furniture, office supplies, etc.

### Shipments (41 records)
- **Linked to Orders**: Created for shipped and delivered orders
- **Courier Services**: InPost, DHL Express, UPS
- **Tracking Numbers**: Realistic format per courier
- **Addresses**: Complete sender and recipient information
- **Optional Features**: 20% with COD, 10% with insurance

### Transactions (96 records)
- **Types**: Payment (80%), Refund (10%), Credit Top-up (10%)
- **Status Distribution**: Completed (80%), Pending (10%), Failed (10%)
- **Payment Methods**: PayNow, Stripe, Credit, Wallet
- **Linked to Orders**: Transactions created for confirmed+ orders

### Notifications (150 records)
- **Types**: Email, SMS, System notifications
- **Realistic Templates**: Order confirmations, shipping updates, system messages
- **Status Distribution**: Sent, pending, failed, delivered
- **Priority Levels**: Low, normal, high, urgent

### Courier Services (3 records)
- **InPost**: Polish domestic service with parcel lockers
- **DHL Express**: International and domestic express
- **UPS**: International service

## Testing Scenarios

### Dashboard Statistics
- View overall metrics: customers, orders, revenue, shipments
- Filter by date ranges to see trends
- Check status distributions

### Customer Management
- Browse business and individual customers
- Check customer details and associated users
- View customer order history

### Order Processing
- Review orders in different statuses
- Track order progression from pending to delivered
- View order items and totals

### Shipment Tracking
- Monitor shipment statuses across different carriers
- Check tracking numbers and delivery addresses
- View shipment costs and additional services

### Financial Overview
- Review transaction history and payment methods
- Check revenue trends and payment statuses
- Monitor refunds and credits

### Notification System
- View notification types and delivery status
- Check read/unread statuses
- Monitor system communications

## Data Relationships

- **Customers** → **Customer Users** (1:many)
- **Customers** → **Orders** (1:many)
- **Orders** → **Order Items** (1:many)
- **Orders** → **Shipments** (1:many)
- **Orders** → **Transactions** (1:many)
- **Customers** → **Notifications** (1:many)
- **Shipments** → **Courier Services** (many:1)

## Maintenance

### Reload Fixtures
```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### Clear Specific Data
```bash
# Clear all fixture data
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --force
```

### Add More Data
The fixtures are designed to be run multiple times. Each run will:
- Clear existing data
- Generate new random sample data
- Maintain realistic relationships and distributions

## Notes

- All timestamps are relative to fixture execution time
- Random data ensures variety in testing scenarios
- Entity relationships are properly maintained
- Passwords are properly hashed using Symfony's security system
- Polish business data follows local conventions (VAT, REGON, addresses)