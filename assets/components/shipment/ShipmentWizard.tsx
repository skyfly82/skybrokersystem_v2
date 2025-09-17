import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import StepIndicator from './StepIndicator';
import PackageTypeStep from './steps/PackageTypeStep';
import AddressStep from './steps/AddressStep';
import ServicesStep from './steps/ServicesStep';
import SummaryStep from './steps/SummaryStep';
import { useShipmentForm } from '../../hooks/useShipmentForm';
import { usePricingCalculator } from '../../hooks/usePricingCalculator';

interface ShipmentData {
  packageType: string;
  weight: number;
  dimensions: {
    length: number;
    width: number;
    height: number;
  };
  senderAddress: Address;
  receiverAddress: Address;
  additionalServices: string[];
  selectedCarrier?: CarrierOption;
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
  estimatedDelivery: string;
  serviceType: string;
}

const steps = [
  { id: 1, title: 'Typ paczki', description: 'Wybierz rodzaj przesyłki' },
  { id: 2, title: 'Adresy', description: 'Podaj dane nadawcy i odbiorcy' },
  { id: 3, title: 'Usługi', description: 'Wybierz dodatkowe usługi' },
  { id: 4, title: 'Podsumowanie', description: 'Sprawdź i zatwierdź' }
];

const ShipmentWizard: React.FC = () => {
  const [currentStep, setCurrentStep] = useState(1);
  const [isCalculatingPrice, setIsCalculatingPrice] = useState(false);

  const {
    formData,
    updateFormData,
    validateStep,
    resetForm,
    isStepValid
  } = useShipmentForm();

  const {
    calculatePricing,
    carrierOptions,
    loading: pricingLoading,
    error: pricingError
  } = usePricingCalculator();

  // Calculate pricing when package details are complete
  useEffect(() => {
    if (currentStep >= 2 && formData.packageType && formData.weight && formData.dimensions) {
      handlePriceCalculation();
    }
  }, [formData.packageType, formData.weight, formData.dimensions, currentStep]);

  const handlePriceCalculation = async () => {
    if (!formData.senderAddress?.postalCode || !formData.receiverAddress?.postalCode) {
      return;
    }

    setIsCalculatingPrice(true);
    try {
      await calculatePricing({
        weight_kg: formData.weight,
        dimensions_cm: formData.dimensions,
        zone_code: determineZoneCode(formData.senderAddress.postalCode, formData.receiverAddress.postalCode),
        service_type: 'standard',
        additional_services: formData.additionalServices
      });
    } catch (error) {
      console.error('Pricing calculation failed:', error);
    } finally {
      setIsCalculatingPrice(false);
    }
  };

  const determineZoneCode = (senderPostal: string, receiverPostal: string): string => {
    // Simple zone determination logic - in real app this would be more sophisticated
    const senderCode = parseInt(senderPostal.replace('-', ''));
    const receiverCode = parseInt(receiverPostal.replace('-', ''));

    if (Math.abs(senderCode - receiverCode) < 10000) {
      return 'domestic_local';
    } else {
      return 'domestic_standard';
    }
  };

  const handleNext = async () => {
    const isValid = await validateStep(currentStep);
    if (isValid && currentStep < steps.length) {
      setCurrentStep(currentStep + 1);
    }
  };

  const handlePrevious = () => {
    if (currentStep > 1) {
      setCurrentStep(currentStep - 1);
    }
  };

  const handleStepClick = (stepNumber: number) => {
    if (stepNumber <= currentStep) {
      setCurrentStep(stepNumber);
    }
  };

  const handleSubmit = async () => {
    try {
      // Submit shipment order
      const response = await fetch('/api/v1/shipments', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
      });

      if (response.ok) {
        // Handle success
        resetForm();
        setCurrentStep(1);
      }
    } catch (error) {
      console.error('Shipment submission failed:', error);
    }
  };

  const renderStepContent = () => {
    switch (currentStep) {
      case 1:
        return (
          <PackageTypeStep
            data={formData}
            onChange={updateFormData}
          />
        );
      case 2:
        return (
          <AddressStep
            data={formData}
            onChange={updateFormData}
            onAddressValidate={handlePriceCalculation}
          />
        );
      case 3:
        return (
          <ServicesStep
            data={formData}
            carrierOptions={carrierOptions}
            onChange={updateFormData}
            isCalculating={isCalculatingPrice}
            pricingError={pricingError}
          />
        );
      case 4:
        return (
          <SummaryStep
            data={formData}
            onSubmit={handleSubmit}
          />
        );
      default:
        return null;
    }
  };

  return (
    <div className="max-w-4xl mx-auto">
      {/* Page Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900">Nowa przesyłka</h1>
        <p className="mt-1 text-sm text-gray-600">
          Skonfiguruj parametry przesyłki i wybierz najlepszą opcję dostawy
        </p>
      </div>

      {/* Step Indicator */}
      <StepIndicator
        steps={steps}
        currentStep={currentStep}
        onStepClick={handleStepClick}
      />

      {/* Step Content */}
      <div className="mt-8">
        <AnimatePresence mode="wait">
          <motion.div
            key={currentStep}
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            transition={{ duration: 0.3 }}
          >
            {renderStepContent()}
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Navigation Buttons */}
      <div className="mt-8 flex justify-between">
        <button
          onClick={handlePrevious}
          disabled={currentStep === 1}
          className="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          Wstecz
        </button>

        <div className="flex space-x-4">
          {currentStep < steps.length ? (
            <button
              onClick={handleNext}
              disabled={!isStepValid(currentStep)}
              className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Dalej
            </button>
          ) : (
            <button
              onClick={handleSubmit}
              disabled={!isStepValid(currentStep)}
              className="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Złóż zamówienie
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

export default ShipmentWizard;