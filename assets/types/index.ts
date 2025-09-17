// Core types for Sky Broker System

export interface User {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  fullName: string;
  phone?: string;
  roles: string[];
  status: 'active' | 'inactive' | 'pending';
  emailVerified: boolean;
  lastLoginAt?: string;
  createdAt: string;
}

export interface CustomerUser extends User {
  customerRole: 'owner' | 'manager' | 'employee' | 'viewer';
  customer: Customer;
}

export interface Customer {
  id: string;
  companyName: string;
  taxNumber?: string;
  regon?: string;
  address?: string;
  postalCode?: string;
  city?: string;
  country: string;
  phone?: string;
  email?: string;
  website?: string;
  status: 'active' | 'inactive' | 'suspended';
  accountBalance: number;
  creditLimit: number;
  createdAt: string;
  updatedAt?: string;
}

export interface Address {
  street: string;
  city: string;
  postalCode: string;
  country: string;
  contactName: string;
  contactPhone: string;
  contactEmail?: string;
}

export interface Shipment {
  id: string;
  orderNumber: string;
  trackingNumber?: string;
  status: ShipmentStatus;
  carrier: Carrier;
  serviceType: string;

  // Package details
  packageType: PackageType;
  weight: number;
  dimensions: Dimensions;
  value?: number;
  currency: string;

  // Addresses
  senderAddress: Address;
  receiverAddress: Address;

  // Pricing
  basePrice: number;
  additionalServices: AdditionalService[];
  totalPrice: number;

  // Dates
  createdAt: string;
  pickupDate?: string;
  estimatedDeliveryDate?: string;
  actualDeliveryDate?: string;

  // Tracking
  trackingEvents: TrackingEvent[];

  // Customer
  customer: Customer;
  customerUser: CustomerUser;
}

export interface Order {
  id: string;
  orderNumber: string;
  status: OrderStatus;
  shipments: Shipment[];
  totalAmount: number;
  currency: string;

  // Customer
  customer: Customer;
  customerUser: CustomerUser;

  // Dates
  createdAt: string;
  updatedAt?: string;
  completedAt?: string;
}

export interface Carrier {
  code: string;
  name: string;
  logoUrl?: string;
  website?: string;
  trackingUrl?: string;
  maxWeightKg: number;
  maxDimensions: Dimensions;
  supportedCountries: string[];
  serviceTypes: ServiceType[];
  features: string[];
  isActive: boolean;
}

export interface ServiceType {
  code: string;
  name: string;
  description: string;
  estimatedDeliveryDays: number;
  features: string[];
}

export interface AdditionalService {
  code: string;
  name: string;
  description: string;
  price: number;
  currency: string;
  type: 'insurance' | 'pickup' | 'delivery' | 'packaging' | 'other';
}

export interface Dimensions {
  length: number;
  width: number;
  height: number;
}

export interface TrackingEvent {
  id: string;
  timestamp: string;
  status: string;
  location: string;
  description: string;
  carrier: string;
}

export interface PriceCalculationRequest {
  carrier_code?: string;
  zone_code: string;
  weight_kg: number;
  dimensions_cm: Dimensions;
  service_type: string;
  currency: string;
  additional_services?: string[];
  customer_id?: string;
}

export interface PriceCalculationResponse {
  carrier_code: string;
  carrier_name: string;
  service_type: string;
  base_price: number;
  additional_services_price: number;
  total_price: number;
  currency: string;
  estimated_delivery_date: string;
  features: string[];
}

export interface CarrierComparison {
  carriers: PriceCalculationResponse[];
  best_price: PriceCalculationResponse;
  average_price: number;
  total_carriers: number;
  savings: number;
}

export interface DashboardStats {
  totalOrders: number;
  inTransit: number;
  delivered: number;
  totalRevenue: number;
  monthlyRevenue: number;
  averageOrderValue: number;
  conversionRate: number;
}

export interface ChartData {
  labels: string[];
  datasets: {
    label: string;
    data: number[];
    backgroundColor?: string;
    borderColor?: string;
    fill?: boolean;
  }[];
}

export interface Transaction {
  id: string;
  type: 'payment' | 'refund' | 'fee' | 'credit';
  amount: number;
  currency: string;
  description: string;
  status: 'pending' | 'completed' | 'failed' | 'cancelled';
  paymentMethod?: string;
  referenceNumber?: string;
  createdAt: string;
  completedAt?: string;
}

export interface Invoice {
  id: string;
  invoiceNumber: string;
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
  amount: number;
  currency: string;
  dueDate: string;
  paidAt?: string;
  customer: Customer;
  items: InvoiceItem[];
  createdAt: string;
}

export interface InvoiceItem {
  id: string;
  description: string;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  shipment?: Shipment;
}

export interface Notification {
  id: string;
  type: 'info' | 'success' | 'warning' | 'error';
  title: string;
  message: string;
  isRead: boolean;
  createdAt: string;
  data?: Record<string, any>;
}

// Enums
export type PackageType = 'envelope' | 'small_package' | 'medium_package' | 'large_package' | 'pallet';

export type ShipmentStatus =
  | 'created'
  | 'processing'
  | 'ready_for_pickup'
  | 'picked_up'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'failed_delivery'
  | 'returned'
  | 'cancelled';

export type OrderStatus =
  | 'draft'
  | 'pending'
  | 'processing'
  | 'shipped'
  | 'delivered'
  | 'cancelled'
  | 'refunded';

// Form types
export interface ShipmentFormData {
  packageType: PackageType;
  weight: number;
  dimensions: Dimensions;
  value?: number;

  senderAddress: Address;
  receiverAddress: Address;

  selectedCarrier?: PriceCalculationResponse;
  additionalServices: string[];

  // Special instructions
  pickupInstructions?: string;
  deliveryInstructions?: string;

  // Insurance
  insuranceValue?: number;

  // Pickup preferences
  pickupDate?: string;
  pickupTimeSlot?: string;
}

// API Response types
export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T = any> {
  data: T[];
  pagination: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

// Error types
export interface ApiError {
  message: string;
  code?: string;
  context?: Record<string, any>;
  status?: number;
}

// Theme and UI types
export interface Theme {
  primary: string;
  secondary: string;
  accent: string;
  neutral: string;
  base: string;
  info: string;
  success: string;
  warning: string;
  error: string;
}

export interface ToastMessage {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  message: string;
  duration?: number;
  persistent?: boolean;
}

// Component Props types
export interface BaseComponentProps {
  className?: string;
  children?: React.ReactNode;
}

export interface FormFieldProps extends BaseComponentProps {
  label?: string;
  error?: string;
  required?: boolean;
  disabled?: boolean;
  placeholder?: string;
}

// Utility types
export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>;
export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>;
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};