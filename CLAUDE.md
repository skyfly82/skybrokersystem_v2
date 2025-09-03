# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SkyBrokerSystem v2 is a complete rewrite of a courier brokerage platform, migrating from Laravel to **Symfony 7.x** with modern PHP practices. This is currently in **early development phase** with only basic project structure established.

## Architecture & Design Principles

The project follows **Domain-Driven Design (DDD)** with these architectural patterns:
- **Clean Architecture** with distinct layers
- **CQRS** pattern for complex operations  
- **Event-driven communication**
- **API-First development** with OpenAPI documentation
- **Hexagonal Architecture**
- **Microservices-ready structure**

## Core Business Domains

The system is organized around these primary domains:
1. **User Management** - Multi-guard authentication (System users, Customer users)
2. **Customer Management** - Companies and individual customers
3. **Order Processing** - Shipments, orders, status tracking
4. **Courier Integration** - InPost, DHL API integrations
5. **Payment Processing** - PayNow, Stripe transactions
6. **Notification System** - SMS, Email, Push notifications
7. **CMS & Content** - Pages, media, banners
8. **Reporting & Analytics** - Dashboards and statistics

## Technology Stack

- **Backend**: Symfony 7.x, PHP 8.3+, Doctrine ORM
- **Frontend**: React 18+ or Vue.js 3+ with TypeScript (planned)
- **Database**: PostgreSQL 15+ (migrating from MySQL)
- **Cache**: Redis
- **Queue**: Symfony Messenger
- **API**: RESTful with OpenAPI 3.0 documentation
- **Container**: Docker with docker-compose

## Development Status

⚠️ **Early Development Phase**: The project currently only contains documentation and basic structure. The actual Symfony application, Docker setup, and business logic are not yet implemented.

### Implementation Phases
1. **Phase 1**: Foundation & Authentication (4-6 weeks) - *In Progress*
2. **Phase 2**: Core Business Logic (8-10 weeks) - *Planned*
3. **Phase 3**: Frontend & UI (6-8 weeks) - *Planned*
4. **Phase 4**: Advanced Features (4-6 weeks) - *Planned*

## Development Commands

Since the Symfony application is not yet set up, standard development commands are not available. Once implemented, expect:

```bash
# Symfony commands (when available)
composer install
php bin/console doctrine:migrations:migrate
php bin/console server:run

# Docker commands (when Docker setup is complete)
docker-compose up -d
docker-compose exec app composer install

# Testing (when test suite is implemented)
php bin/phpunit
```

## Migration Context

This project replaces a Laravel-based system with these components:
- **44 Controllers** → Symfony Controllers/Actions
- **28 Models** → Doctrine Entities  
- **113+ Blade Templates** → React/Vue.js Components
- **19 Service Classes** → Domain Services
- **40 Database Migrations** → Doctrine Migrations

## Key Integration Points

- **InPost API** - Polish courier service integration
- **DHL API** - International courier service
- **PayNow** - Polish payment system
- **Stripe** - International payment processing

## References

- Original Laravel system: [skyfly82/skybrokersystem](https://github.com/skyfly82/skybrokersystem)
- Detailed implementation plan: `IMPLEMENTATION_PLAN.md`