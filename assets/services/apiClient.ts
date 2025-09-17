// API Client for Sky Broker System
class ApiClient {
  private baseURL: string;
  private token: string | null = null;

  constructor(baseURL: string = '/api/v1') {
    this.baseURL = baseURL;
    this.loadToken();
  }

  private loadToken(): void {
    this.token = localStorage.getItem('auth_token');
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseURL}${endpoint}`;

    const config: RequestInit = {
      headers: {
        'Content-Type': 'application/json',
        ...(this.token && { Authorization: `Bearer ${this.token}` }),
        ...options.headers,
      },
      ...options,
    };

    try {
      const response = await fetch(url, config);

      // Handle authentication errors
      if (response.status === 401) {
        this.clearToken();
        window.location.href = '/login';
        throw new Error('Authentication required');
      }

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      console.error('API Request failed:', error);
      throw error;
    }
  }

  // Authentication methods
  setToken(token: string): void {
    this.token = token;
    localStorage.setItem('auth_token', token);
  }

  clearToken(): void {
    this.token = null;
    localStorage.removeItem('auth_token');
  }

  // Generic HTTP methods
  async get<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET' });
  }

  async post<T>(endpoint: string, data?: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: data ? JSON.stringify(data) : undefined,
    });
  }

  async put<T>(endpoint: string, data?: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: data ? JSON.stringify(data) : undefined,
    });
  }

  async patch<T>(endpoint: string, data?: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PATCH',
      body: data ? JSON.stringify(data) : undefined,
    });
  }

  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }

  // File upload method
  async uploadFile<T>(endpoint: string, file: File, additionalData?: Record<string, any>): Promise<T> {
    const formData = new FormData();
    formData.append('file', file);

    if (additionalData) {
      Object.entries(additionalData).forEach(([key, value]) => {
        formData.append(key, String(value));
      });
    }

    return this.request<T>(endpoint, {
      method: 'POST',
      headers: {
        ...(this.token && { Authorization: `Bearer ${this.token}` }),
      },
      body: formData,
    });
  }
}

// API Endpoints and methods
export class DashboardAPI {
  constructor(private client: ApiClient) {}

  async getCustomerDashboard() {
    return this.client.get('/dashboard/customer');
  }

  async getSystemDashboard() {
    return this.client.get('/dashboard/system');
  }

  async getStatistics(period: string = '30d') {
    return this.client.get(`/dashboard/statistics?period=${period}`);
  }
}

export class PricingAPI {
  constructor(private client: ApiClient) {}

  async calculatePrice(data: any) {
    return this.client.post('/pricing/calculate', data);
  }

  async compareCarriers(data: any) {
    return this.client.post('/pricing/compare', data);
  }

  async getBestPrice(data: any) {
    return this.client.post('/pricing/best-price', data);
  }

  async calculateBulk(data: any) {
    return this.client.post('/pricing/bulk', data);
  }

  async getAvailableCarriers(params: {
    zone_code: string;
    weight_kg: number;
    length: number;
    width: number;
    height: number;
  }) {
    const query = new URLSearchParams(params as any).toString();
    return this.client.get(`/pricing/carriers/available?${query}`);
  }

  async validateCarrier(carrierCode: string, data: any) {
    return this.client.post(`/pricing/carriers/${carrierCode}/validate`, data);
  }
}

export class ShipmentAPI {
  constructor(private client: ApiClient) {}

  async createShipment(data: any) {
    return this.client.post('/shipments', data);
  }

  async getShipments(params?: {
    page?: number;
    limit?: number;
    status?: string;
    carrier?: string;
  }) {
    const query = params ? new URLSearchParams(params as any).toString() : '';
    return this.client.get(`/shipments${query ? `?${query}` : ''}`);
  }

  async getShipment(id: string) {
    return this.client.get(`/shipments/${id}`);
  }

  async updateShipment(id: string, data: any) {
    return this.client.patch(`/shipments/${id}`, data);
  }

  async cancelShipment(id: string) {
    return this.client.post(`/shipments/${id}/cancel`);
  }

  async trackShipment(trackingNumber: string) {
    return this.client.get(`/shipments/track/${trackingNumber}`);
  }
}

export class OrderAPI {
  constructor(private client: ApiClient) {}

  async getOrders(params?: {
    page?: number;
    limit?: number;
    status?: string;
  }) {
    const query = params ? new URLSearchParams(params as any).toString() : '';
    return this.client.get(`/orders${query ? `?${query}` : ''}`);
  }

  async getOrder(id: string) {
    return this.client.get(`/orders/${id}`);
  }

  async createOrder(data: any) {
    return this.client.post('/orders', data);
  }

  async updateOrderStatus(id: string, status: string) {
    return this.client.patch(`/orders/${id}/status`, { status });
  }
}

export class BillingAPI {
  constructor(private client: ApiClient) {}

  async getAccountBalance() {
    return this.client.get('/billing/balance');
  }

  async getTransactions(params?: {
    page?: number;
    limit?: number;
    type?: string;
  }) {
    const query = params ? new URLSearchParams(params as any).toString() : '';
    return this.client.get(`/billing/transactions${query ? `?${query}` : ''}`);
  }

  async getInvoices(params?: {
    page?: number;
    limit?: number;
    status?: string;
  }) {
    const query = params ? new URLSearchParams(params as any).toString() : '';
    return this.client.get(`/billing/invoices${query ? `?${query}` : ''}`);
  }

  async downloadInvoice(id: string) {
    return this.client.get(`/billing/invoices/${id}/download`);
  }

  async addCredit(amount: number, paymentMethod: string) {
    return this.client.post('/billing/credit/add', { amount, payment_method: paymentMethod });
  }
}

export class CustomerAPI {
  constructor(private client: ApiClient) {}

  async getProfile() {
    return this.client.get('/customer/profile');
  }

  async updateProfile(data: any) {
    return this.client.patch('/customer/profile', data);
  }

  async getCompanyInfo() {
    return this.client.get('/customer/company');
  }

  async updateCompanyInfo(data: any) {
    return this.client.patch('/customer/company', data);
  }

  async getNotifications() {
    return this.client.get('/customer/notifications');
  }

  async markNotificationRead(id: string) {
    return this.client.patch(`/customer/notifications/${id}/read`);
  }
}

// Create and export API instance
const apiClient = new ApiClient();

export const api = {
  dashboard: new DashboardAPI(apiClient),
  pricing: new PricingAPI(apiClient),
  shipments: new ShipmentAPI(apiClient),
  orders: new OrderAPI(apiClient),
  billing: new BillingAPI(apiClient),
  customer: new CustomerAPI(apiClient),
  client: apiClient,
};

export default api;