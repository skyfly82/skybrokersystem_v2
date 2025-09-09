# Sky

Modern courier brokerage platform built with Symfony 7.x and modern PHP practices.

## Project Overview

This is a complete rewrite of the original Laravel-based system, now built on:
- **Symfony 7.x** - Modern PHP framework
- **Doctrine ORM** - Enterprise-grade data management
- **API-First Architecture** - RESTful APIs with OpenAPI documentation
- **Modern Frontend** - React/Vue.js SPA
- **Docker** - Containerized development and deployment

## Development Status

🚧 **Under Development** - This project is currently being developed in phases:

### Phase 1: Core & Authentication (In Progress)
- [ ] Symfony 7 project setup
- [ ] Docker environment configuration
- [ ] Multi-guard authentication system
- [ ] Base entities and repositories
- [ ] API foundation with OpenAPI docs

### Phase 2: Business Logic (Planned)
- [ ] Courier service integrations (InPost, DHL)
- [ ] Order management system
- [ ] Payment processing
- [ ] Shipment tracking

### Phase 3: Frontend & UI (Planned)
- [ ] React/Vue.js admin panel
- [ ] Customer dashboard
- [ ] Mobile-responsive design
- [ ] Real-time notifications

### Phase 4: Advanced Features (In Progress)
- [x] Analytics and reporting (initial)
- [x] CMS functionality (initial)
- [x] Performance optimization (caching, indexes)
- [x] Monitoring and logging (channels + metrics)

Key additions:
- API endpoints: `/api/analytics/summary`, `/api/analytics/events`, `/api/cms/*`
- Admin views: `/admin/analytics`, `/admin/cms`
- Request metrics persisted to `v2_analytics_events` via event subscriber
- CMS pages stored in `v2_cms_pages` with ETag and cache headers

## Architecture

This project follows Domain-Driven Design (DDD) principles with:
- Clean architecture layers
- CQRS pattern for complex operations
- Event-driven communication
- Microservices-ready structure

## Original System

The original Laravel-based system can be found at [skyfly82/skybrokersystem](https://github.com/skyfly82/skybrokersystem).

## License

Proprietary - All rights reserved.
