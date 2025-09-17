# Frontend Component Architecture - Sky Broker System

## Overview

This document outlines the comprehensive React-based frontend component architecture for the Sky Broker System customer dashboard and shipment ordering interface. The architecture follows modern React patterns with TypeScript, Tailwind CSS, and state management best practices.

## Technology Stack

- **React 18+** with TypeScript
- **Tailwind CSS** for styling with custom Sky Broker design system
- **Framer Motion** for animations and transitions
- **Zustand** for state management
- **React Router** for navigation
- **Heroicons** for consistent iconography
- **React Hook Form** for form handling

## Project Structure

```
assets/
├── components/
│   ├── common/              # Shared components
│   │   ├── Sidebar.tsx
│   │   ├── TopNavigation.tsx
│   │   ├── NotificationToast.tsx
│   │   └── LoadingStates.tsx
│   ├── dashboard/           # Dashboard-specific components
│   │   ├── DashboardLayout.tsx
│   │   ├── DashboardOverview.tsx
│   │   ├── StatCard.tsx
│   │   ├── ActivityChart.tsx
│   │   ├── RecentOrdersTable.tsx
│   │   └── AccountBalance.tsx
│   ├── shipment/           # Shipment ordering components
│   │   ├── ShipmentWizard.tsx
│   │   ├── StepIndicator.tsx
│   │   ├── PackageCard.tsx
│   │   ├── CarrierComparison.tsx
│   │   └── steps/
│   │       ├── PackageTypeStep.tsx
│   │       ├── AddressStep.tsx
│   │       ├── ServicesStep.tsx
│   │       └── SummaryStep.tsx
│   ├── forms/              # Form components
│   │   ├── AddressForm.tsx
│   │   ├── PackageForm.tsx
│   │   └── PaymentForm.tsx
│   └── ui/                 # Basic UI components
│       ├── Modal.tsx
│       ├── Button.tsx
│       ├── Input.tsx
│       ├── Select.tsx
│       └── Table.tsx
├── hooks/                  # Custom React hooks
│   ├── useDashboardData.ts
│   ├── usePricingCalculator.ts
│   ├── useShipmentForm.ts
│   └── useDebounce.ts
├── stores/                 # State management
│   ├── cartStore.ts
│   ├── userStore.ts
│   └── notificationStore.ts
├── services/              # API services
│   ├── apiClient.ts
│   └── websocketClient.ts
├── types/                 # TypeScript definitions
│   └── index.ts
└── styles/               # Global styles and configuration
    ├── tailwind.config.js
    ├── globals.css
    └── animations.css
```

## Core Components

### 1. Dashboard Components

#### DashboardLayout
- **Purpose**: Main layout wrapper for all dashboard pages
- **Features**:
  - Responsive sidebar navigation
  - Top navigation with user menu
  - Mobile-first design with hamburger menu
  - Toast notification system

#### DashboardOverview
- **Purpose**: Customer dashboard home page
- **Features**:
  - Real-time statistics cards
  - Activity charts and trends
  - Recent orders table
  - Account balance display
  - Loading states and error handling

#### StatCard
- **Purpose**: Reusable metric display component
- **Features**:
  - Icon-based visualization
  - Trend indicators (up/down arrows)
  - Color-coded themes
  - Hover animations

### 2. Shipment Ordering Components

#### ShipmentWizard
- **Purpose**: 4-step shipment creation process
- **Features**:
  - Progressive form validation
  - Real-time pricing calculations
  - Step navigation with progress indicator
  - Form data persistence
  - Smooth animations between steps

#### Package Type Selection
- **Components**: `PackageTypeStep`, `PackageCard`
- **Features**:
  - Visual package type cards (envelope, small, medium, large, pallet)
  - Dynamic weight and dimension validation
  - Real-time package preview
  - Base pricing display

#### Address Management
- **Components**: `AddressStep`, `AddressForm`
- **Features**:
  - Dual address forms (sender/receiver)
  - Postal code autocomplete
  - Address validation
  - Contact information management
  - International address support

#### Carrier Comparison
- **Components**: `ServicesStep`, `CarrierComparison`
- **Features**:
  - Real-time price comparison
  - Carrier feature comparison
  - Service type selection
  - Additional services configuration
  - Best price highlighting

### 3. Shared Components

#### Loading States
- **Components**: Multiple loading components
- **Features**:
  - Skeleton loaders for cards and tables
  - Spinner components in various sizes
  - Loading buttons with disabled states
  - Page-level loading overlays
  - Pricing calculation loaders

#### Modal System
- **Components**: `Modal`, `ConfirmationModal`, `FormModal`
- **Features**:
  - Animated enter/exit transitions
  - Backdrop blur effects
  - Keyboard navigation (ESC to close)
  - Focus management
  - Different sizes and types

#### Form Components
- **Components**: Various form inputs and controls
- **Features**:
  - Consistent styling across all forms
  - Real-time validation
  - Error state handling
  - Accessibility compliance
  - Custom form controls

## State Management

### Cart Store (Zustand)
```typescript
interface CartState {
  items: ShipmentItem[];
  totalPrice: number;
  currency: string;
  discount: number;
  // Actions for managing multi-shipment orders
  addItem: (item: ShipmentItem) => void;
  removeItem: (id: string) => void;
  updateItem: (id: string, updates: Partial<ShipmentItem>) => void;
  clearCart: () => void;
  bulkUpdateCarrier: (carrierCode: string) => void;
}
```

**Features**:
- Persistent cart data across browser sessions
- Multi-shipment order management
- Bulk operations for multiple shipments
- Automatic price calculations
- Discount application

### Real-time Pricing Updates
- Debounced API calls for price calculations
- Caching of recent pricing requests
- Automatic recalculation on parameter changes
- Loading states during calculations

## API Integration

### Pricing Calculator API
```typescript
// Real-time pricing calculations
const { calculatePricing, carrierOptions, loading } = usePricingCalculator();

// Compare all carriers
await calculatePricing({
  weight_kg: 2.5,
  dimensions_cm: { length: 30, width: 20, height: 15 },
  zone_code: 'domestic_standard',
  additional_services: ['insurance', 'pickup']
});
```

### Key API Endpoints
- `POST /api/v1/pricing/compare` - Compare carrier prices
- `POST /api/v1/pricing/calculate` - Calculate single carrier price
- `POST /api/v1/pricing/best-price` - Get best available price
- `GET /api/v1/pricing/carriers/available` - Get available carriers
- `POST /api/v1/shipments` - Create shipment order

## Design System

### Color Palette
```css
:root {
  --primary-50: #eff6ff;
  --primary-500: #3b82f6;
  --primary-600: #2563eb;
  --primary-700: #1d4ed8;
}
```

### Component Classes
- `.card-shadow` - Consistent card shadows
- `.nav-item` - Navigation item styling
- `.nav-item-active` - Active navigation state
- `.btn-primary` - Primary button styling
- `.status-badge` - Status indicator styling

### Responsive Design
- Mobile-first approach
- Breakpoints: `xs: 475px`, `sm: 640px`, `md: 768px`, `lg: 1024px`, `xl: 1280px`
- Collapsible sidebar on mobile
- Touch-friendly interface elements

## Performance Optimizations

### Code Splitting
- Route-based code splitting
- Component lazy loading
- Dynamic imports for heavy components

### Bundle Optimization
- Tree shaking for unused code
- Optimized Tailwind CSS purging
- Image optimization and lazy loading

### Caching Strategy
- API response caching
- Form data persistence
- Optimistic UI updates

## Accessibility Features

### WCAG 2.1 Compliance
- Semantic HTML structure
- ARIA labels and roles
- Keyboard navigation support
- Focus management
- Screen reader compatibility

### User Experience
- High contrast color ratios
- Touch target sizes (minimum 44px)
- Clear error messages
- Loading state feedback
- Progressive enhancement

## Animation System

### Framer Motion Integration
```typescript
// Smooth page transitions
<motion.div
  initial={{ opacity: 0, x: 20 }}
  animate={{ opacity: 1, x: 0 }}
  exit={{ opacity: 0, x: -20 }}
  transition={{ duration: 0.3 }}
>
  {content}
</motion.div>
```

### Animation Types
- Page transitions
- Modal enter/exit
- Loading states
- Micro-interactions
- Form validation feedback

## Testing Strategy

### Component Testing
- React Testing Library for component tests
- Jest for unit testing
- Mock Service Worker for API mocking
- Accessibility testing with jest-axe

### E2E Testing
- Playwright for end-to-end testing
- Critical user journey coverage
- Cross-browser testing
- Mobile device testing

## Development Workflow

### Hot Module Replacement
- Instant feedback during development
- State preservation across edits
- Error overlay for quick debugging

### Type Safety
- Strict TypeScript configuration
- API response type validation
- Component prop validation
- Build-time error catching

## Integration with Symfony Backend

### API Communication
- RESTful API endpoints
- JWT authentication
- Error handling and retry logic
- Request/response interceptors

### Real-time Features
- WebSocket connections for live updates
- Server-sent events for notifications
- Optimistic UI updates

## Deployment Considerations

### Build Optimization
- Production bundle analysis
- Asset optimization
- CDN integration for static assets
- Progressive Web App features

### Monitoring
- Error tracking with error boundaries
- Performance monitoring
- User analytics
- A/B testing framework

## Future Enhancements

### Planned Features
- Offline functionality
- Push notifications
- Advanced analytics dashboard
- Mobile app integration
- Multi-language support

### Scalability
- Micro-frontend architecture preparation
- Module federation setup
- Component library extraction
- Design system documentation

This architecture provides a solid foundation for a modern, scalable customer dashboard and shipment ordering system that delivers excellent user experience while maintaining high performance and accessibility standards.