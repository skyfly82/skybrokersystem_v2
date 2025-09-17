import { useState, useCallback } from 'react';

interface PricingRequest {
  weight_kg: number;
  dimensions_cm: {
    length: number;
    width: number;
    height: number;
  };
  zone_code: string;
  service_type: string;
  additional_services?: string[];
  customer_id?: string;
}

interface CarrierOption {
  code: string;
  name: string;
  price: number;
  currency: string;
  estimatedDelivery: string;
  serviceType: string;
  logoUrl?: string;
  features: string[];
}

interface PricingResponse {
  success: boolean;
  data: {
    carriers: CarrierOption[];
    bestPrice: CarrierOption;
    averagePrice: number;
    savings: number;
  };
}

export const usePricingCalculator = () => {
  const [carrierOptions, setCarrierOptions] = useState<CarrierOption[]>([]);
  const [bestPrice, setBestPrice] = useState<CarrierOption | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const calculatePricing = useCallback(async (request: PricingRequest) => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/v1/pricing/compare', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Pricing calculation failed');
      }

      const data: PricingResponse = await response.json();

      if (data.success) {
        setCarrierOptions(data.data.carriers);
        setBestPrice(data.data.bestPrice);
      } else {
        throw new Error('Invalid response format');
      }

      return data.data;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
      setError(errorMessage);
      console.error('Pricing calculation error:', err);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const calculateBestPrice = useCallback(async (request: PricingRequest) => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('/api/v1/pricing/best-price', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || 'Best price calculation failed');
      }

      const data = await response.json();

      if (data.success) {
        setBestPrice(data.data);
        return data.data;
      } else {
        throw new Error('Invalid response format');
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
      setError(errorMessage);
      console.error('Best price calculation error:', err);
      throw err;
    } finally {
      setLoading(false);
    }
  }, []);

  const validateCarrier = useCallback(async (carrierCode: string, request: PricingRequest) => {
    try {
      const response = await fetch(`/api/v1/pricing/carriers/${carrierCode}/validate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      });

      if (!response.ok) {
        return false;
      }

      const data = await response.json();
      return data.success && data.data.can_handle;
    } catch (err) {
      console.error('Carrier validation error:', err);
      return false;
    }
  }, []);

  const getAvailableCarriers = useCallback(async (
    zoneCode: string,
    weightKg: number,
    dimensions: { length: number; width: number; height: number }
  ) => {
    try {
      const params = new URLSearchParams({
        zone_code: zoneCode,
        weight_kg: weightKg.toString(),
        length: dimensions.length.toString(),
        width: dimensions.width.toString(),
        height: dimensions.height.toString(),
      });

      const response = await fetch(`/api/v1/pricing/carriers/available?${params}`);

      if (!response.ok) {
        throw new Error('Failed to fetch available carriers');
      }

      const data = await response.json();
      return data.success ? data.data.carriers : [];
    } catch (err) {
      console.error('Get available carriers error:', err);
      return [];
    }
  }, []);

  const reset = useCallback(() => {
    setCarrierOptions([]);
    setBestPrice(null);
    setError(null);
  }, []);

  return {
    carrierOptions,
    bestPrice,
    loading,
    error,
    calculatePricing,
    calculateBestPrice,
    validateCarrier,
    getAvailableCarriers,
    reset,
  };
};