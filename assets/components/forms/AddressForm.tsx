import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { MagnifyingGlassIcon, MapPinIcon } from '@heroicons/react/24/outline';

interface Address {
  street: string;
  city: string;
  postalCode: string;
  country: string;
  contactName: string;
  contactPhone: string;
  contactEmail?: string;
}

interface AddressFormProps {
  label: string;
  address: Address;
  onChange: (address: Address) => void;
  onValidate?: (isValid: boolean) => void;
  showContactFields?: boolean;
  disabled?: boolean;
}

interface PostalCodeSuggestion {
  postalCode: string;
  city: string;
  region: string;
}

const AddressForm: React.FC<AddressFormProps> = ({
  label,
  address,
  onChange,
  onValidate,
  showContactFields = true,
  disabled = false
}) => {
  const [errors, setErrors] = useState<Partial<Address>>({});
  const [postalSuggestions, setPostalSuggestions] = useState<PostalCodeSuggestion[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [isValidating, setIsValidating] = useState(false);

  // Validation rules
  const validateField = (field: keyof Address, value: string): string | null => {
    switch (field) {
      case 'postalCode':
        if (!/^\d{2}-\d{3}$/.test(value)) {
          return 'Kod pocztowy musi być w formacie XX-XXX';
        }
        break;
      case 'contactPhone':
        if (showContactFields && !/^(\+48\s?)?\d{3}\s?\d{3}\s?\d{3}$/.test(value.replace(/\s/g, ''))) {
          return 'Nieprawidłowy numer telefonu';
        }
        break;
      case 'contactEmail':
        if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          return 'Nieprawidłowy adres email';
        }
        break;
      case 'street':
      case 'city':
      case 'contactName':
        if (!value.trim()) {
          return 'To pole jest wymagane';
        }
        break;
    }
    return null;
  };

  // Handle field changes
  const handleFieldChange = (field: keyof Address, value: string) => {
    const newAddress = { ...address, [field]: value };
    onChange(newAddress);

    // Validate field
    const error = validateField(field, value);
    setErrors(prev => ({
      ...prev,
      [field]: error
    }));

    // Trigger postal code suggestions
    if (field === 'postalCode' && value.length >= 2) {
      fetchPostalSuggestions(value);
    } else if (field === 'postalCode') {
      setShowSuggestions(false);
    }
  };

  // Fetch postal code suggestions
  const fetchPostalSuggestions = async (postalCode: string) => {
    try {
      // Mock API call - replace with real postal code API
      const mockSuggestions: PostalCodeSuggestion[] = [
        { postalCode: '00-001', city: 'Warszawa', region: 'mazowieckie' },
        { postalCode: '30-001', city: 'Kraków', region: 'małopolskie' },
        { postalCode: '80-001', city: 'Gdańsk', region: 'pomorskie' },
      ].filter(suggestion =>
        suggestion.postalCode.startsWith(postalCode.replace('-', '').substring(0, 2))
      );

      setPostalSuggestions(mockSuggestions);
      setShowSuggestions(mockSuggestions.length > 0);
    } catch (error) {
      console.error('Failed to fetch postal suggestions:', error);
    }
  };

  // Handle postal code selection
  const handlePostalSelect = (suggestion: PostalCodeSuggestion) => {
    handleFieldChange('postalCode', suggestion.postalCode);
    handleFieldChange('city', suggestion.city);
    setShowSuggestions(false);
  };

  // Validate entire form
  const validateForm = (): boolean => {
    const newErrors: Partial<Address> = {};

    Object.keys(address).forEach(key => {
      const field = key as keyof Address;
      const error = validateField(field, address[field] || '');
      if (error) {
        newErrors[field] = error;
      }
    });

    setErrors(newErrors);
    const isValid = Object.keys(newErrors).length === 0;

    if (onValidate) {
      onValidate(isValid);
    }

    return isValid;
  };

  // Validate on address changes
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      validateForm();
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [address]);

  // Auto-format postal code
  const formatPostalCode = (value: string): string => {
    const digits = value.replace(/\D/g, '');
    if (digits.length <= 2) return digits;
    return `${digits.substring(0, 2)}-${digits.substring(2, 5)}`;
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center mb-4">
        <MapPinIcon className="w-5 h-5 text-gray-400 mr-2" />
        <h4 className="text-md font-semibold text-gray-900">{label}</h4>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* Street Address */}
        <div className="md:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Ulica i numer *
          </label>
          <input
            type="text"
            value={address.street || ''}
            onChange={(e) => handleFieldChange('street', e.target.value)}
            disabled={disabled}
            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
              errors.street ? 'border-red-300' : 'border-gray-300'
            } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
            placeholder="ul. Przykładowa 123"
          />
          {errors.street && (
            <p className="mt-1 text-sm text-red-600">{errors.street}</p>
          )}
        </div>

        {/* Postal Code */}
        <div className="relative">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Kod pocztowy *
          </label>
          <input
            type="text"
            value={address.postalCode || ''}
            onChange={(e) => handleFieldChange('postalCode', formatPostalCode(e.target.value))}
            disabled={disabled}
            maxLength={6}
            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
              errors.postalCode ? 'border-red-300' : 'border-gray-300'
            } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
            placeholder="00-000"
          />
          {errors.postalCode && (
            <p className="mt-1 text-sm text-red-600">{errors.postalCode}</p>
          )}

          {/* Postal Code Suggestions */}
          {showSuggestions && !disabled && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              className="absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-auto"
            >
              {postalSuggestions.map((suggestion, index) => (
                <button
                  key={index}
                  type="button"
                  onClick={() => handlePostalSelect(suggestion)}
                  className="w-full px-3 py-2 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none"
                >
                  <div className="font-medium">{suggestion.postalCode}</div>
                  <div className="text-sm text-gray-600">
                    {suggestion.city}, {suggestion.region}
                  </div>
                </button>
              ))}
            </motion.div>
          )}
        </div>

        {/* City */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Miasto *
          </label>
          <input
            type="text"
            value={address.city || ''}
            onChange={(e) => handleFieldChange('city', e.target.value)}
            disabled={disabled}
            className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
              errors.city ? 'border-red-300' : 'border-gray-300'
            } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
            placeholder="Warszawa"
          />
          {errors.city && (
            <p className="mt-1 text-sm text-red-600">{errors.city}</p>
          )}
        </div>

        {/* Country */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Kraj
          </label>
          <select
            value={address.country || 'PL'}
            onChange={(e) => handleFieldChange('country', e.target.value)}
            disabled={disabled}
            className={`w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
              disabled ? 'bg-gray-100 cursor-not-allowed' : ''
            }`}
          >
            <option value="PL">Polska</option>
            <option value="DE">Niemcy</option>
            <option value="CZ">Czechy</option>
            <option value="SK">Słowacja</option>
            <option value="LT">Litwa</option>
            <option value="LV">Łotwa</option>
            <option value="EE">Estonia</option>
          </select>
        </div>
      </div>

      {/* Contact Information */}
      {showContactFields && (
        <div className="pt-4 border-t border-gray-200">
          <h5 className="text-sm font-medium text-gray-900 mb-3">Dane kontaktowe</h5>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {/* Contact Name */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Imię i nazwisko *
              </label>
              <input
                type="text"
                value={address.contactName || ''}
                onChange={(e) => handleFieldChange('contactName', e.target.value)}
                disabled={disabled}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
                  errors.contactName ? 'border-red-300' : 'border-gray-300'
                } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                placeholder="Jan Kowalski"
              />
              {errors.contactName && (
                <p className="mt-1 text-sm text-red-600">{errors.contactName}</p>
              )}
            </div>

            {/* Contact Phone */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Telefon *
              </label>
              <input
                type="tel"
                value={address.contactPhone || ''}
                onChange={(e) => handleFieldChange('contactPhone', e.target.value)}
                disabled={disabled}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
                  errors.contactPhone ? 'border-red-300' : 'border-gray-300'
                } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                placeholder="+48 123 456 789"
              />
              {errors.contactPhone && (
                <p className="mt-1 text-sm text-red-600">{errors.contactPhone}</p>
              )}
            </div>

            {/* Contact Email */}
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email (opcjonalny)
              </label>
              <input
                type="email"
                value={address.contactEmail || ''}
                onChange={(e) => handleFieldChange('contactEmail', e.target.value)}
                disabled={disabled}
                className={`w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors ${
                  errors.contactEmail ? 'border-red-300' : 'border-gray-300'
                } ${disabled ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                placeholder="jan.kowalski@example.com"
              />
              {errors.contactEmail && (
                <p className="mt-1 text-sm text-red-600">{errors.contactEmail}</p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AddressForm;