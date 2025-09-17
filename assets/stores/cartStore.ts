import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface ShipmentItem {
  id: string;
  packageType: string;
  weight: number;
  dimensions: {
    length: number;
    width: number;
    height: number;
  };
  senderAddress: Address;
  receiverAddress: Address;
  selectedCarrier: CarrierOption;
  additionalServices: string[];
  price: number;
  estimatedDelivery: string;
}

interface Address {
  street: string;
  city: string;
  postalCode: string;
  country: string;
  contactName: string;
  contactPhone: string;
  contactEmail?: string;
}

interface CarrierOption {
  code: string;
  name: string;
  price: number;
  currency: string;
  estimatedDelivery: string;
  serviceType: string;
}

interface CartState {
  items: ShipmentItem[];
  totalPrice: number;
  currency: string;
  discount: number;

  // Actions
  addItem: (item: Omit<ShipmentItem, 'id'>) => void;
  removeItem: (id: string) => void;
  updateItem: (id: string, updates: Partial<ShipmentItem>) => void;
  clearCart: () => void;
  applyDiscount: (amount: number) => void;
  calculateTotal: () => void;

  // Multi-shipment helpers
  duplicateItem: (id: string) => void;
  bulkUpdateCarrier: (carrierCode: string) => void;
  getItemsByCarrier: (carrierCode: string) => ShipmentItem[];
}

const generateId = () => Math.random().toString(36).substr(2, 9);

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: [],
      totalPrice: 0,
      currency: 'PLN',
      discount: 0,

      addItem: (item) => {
        const newItem: ShipmentItem = {
          ...item,
          id: generateId(),
        };

        set((state) => ({
          items: [...state.items, newItem],
        }));

        get().calculateTotal();
      },

      removeItem: (id) => {
        set((state) => ({
          items: state.items.filter(item => item.id !== id),
        }));

        get().calculateTotal();
      },

      updateItem: (id, updates) => {
        set((state) => ({
          items: state.items.map(item =>
            item.id === id ? { ...item, ...updates } : item
          ),
        }));

        get().calculateTotal();
      },

      clearCart: () => {
        set({
          items: [],
          totalPrice: 0,
          discount: 0,
        });
      },

      applyDiscount: (amount) => {
        set({ discount: amount });
        get().calculateTotal();
      },

      calculateTotal: () => {
        const { items, discount } = get();
        const subtotal = items.reduce((sum, item) => sum + item.price, 0);
        const total = Math.max(0, subtotal - discount);

        set({ totalPrice: total });
      },

      duplicateItem: (id) => {
        const { items, addItem } = get();
        const item = items.find(item => item.id === id);

        if (item) {
          const { id: _, ...itemData } = item;
          addItem(itemData);
        }
      },

      bulkUpdateCarrier: (carrierCode) => {
        // This would require fetching new pricing for all items
        // Implementation would depend on pricing API structure
        console.log('Bulk update carrier to:', carrierCode);
      },

      getItemsByCarrier: (carrierCode) => {
        return get().items.filter(item => item.selectedCarrier.code === carrierCode);
      },
    }),
    {
      name: 'shipment-cart',
      partialize: (state) => ({
        items: state.items,
        discount: state.discount,
      }),
    }
  )
);