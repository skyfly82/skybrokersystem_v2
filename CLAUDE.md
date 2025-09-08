# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sky is a complete rewrite of a courier brokerage platform, migrating from Laravel to **Symfony 7.x** with modern PHP practices. This is currently in **early development phase** with only basic project structure established.

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
- **Database**:  MySQL 8.0
- **Cache**: Redis
- **Queue**: Symfony Messenger
- **API**: RESTful with OpenAPI 3.0 documentation
- **Container**: Docker with docker-compose

## Development Status

✅ **Symfony 7.1 Environment Ready**: Basic Symfony 7.1 application is set up with webapp pack installed. Ready for domain implementation.

### Implementation Phases
1. **Phase 1**: Foundation & Authentication (4-6 weeks) - *In Progress*
2. **Phase 2**: Core Business Logic (8-10 weeks) - *Planned*
3. **Phase 3**: Frontend & UI (6-8 weeks) - *Planned*
4. **Phase 4**: Advanced Features (4-6 weeks) - *Planned*

## Development Commands

The Symfony 7.1 application is now set up and ready for development:

```bash
# Basic Symfony commands
composer install                           # Install dependencies
php bin/console about                      # Show environment info
php bin/console debug:router               # List all routes
php bin/console cache:clear                # Clear cache
php bin/console doctrine:migrations:migrate # Run database migrations (when DB is set up)

# Development server
# Application runs at http://185.213.25.106/v2/

# Testing
php bin/phpunit                            # Run test suite
php bin/phpunit tests/                     # Run specific test directory

# Code generation (MakerBundle)
php bin/console make:controller            # Create new controller
php bin/console make:entity                # Create new entity
php bin/console make:form                  # Create form class
php bin/console make:command               # Create console command

# Database management
php bin/console doctrine:database:create   # Create database
php bin/console doctrine:schema:update --force # Update database schema
php bin/console doctrine:fixtures:load     # Load test data

# API & Debugging
php bin/console debug:container            # Show services
php bin/console debug:config               # Show configuration
php bin/console messenger:consume async   # Process message queue
```

## API Endpoints

Currently available endpoints:
- `GET http://185.213.25.106/v2/` - API welcome message and information
- `GET http://185.213.25.106/v2/health` - Health check endpoint
- `GET http://185.213.25.106/v2/_profiler` - Symfony Web Profiler (dev environment)

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
- no dcker will be used we will  work on http://185.213.25.106/ directly
