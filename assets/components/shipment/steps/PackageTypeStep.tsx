import React from 'react';
import { motion } from 'framer-motion';
import PackageCard from '../PackageCard';

interface PackageTypeStepProps {
  data: any;
  onChange: (updates: any) => void;
}

const packageTypes = [
  {
    id: 'envelope',
    name: 'Koperta',
    description: 'Dokumenty, płaskie przedmioty',
    icon: '📧',
    maxWeight: 2,
    maxDimensions: { length: 35, width: 25, height: 2 },
    basePrice: 8.99
  },
  {
    id: 'small_package',
    name: 'Mała paczka',
    description: 'Przedmioty do 5kg',
    icon: '📦',
    maxWeight: 5,
    maxDimensions: { length: 40, width: 30, height: 15 },
    basePrice: 12.99
  },
  {
    id: 'medium_package',
    name: 'Średnia paczka',
    description: 'Przedmioty do 15kg',
    icon: '📮',
    maxWeight: 15,
    maxDimensions: { length: 60, width: 40, height: 30 },
    basePrice: 19.99
  },
  {
    id: 'large_package',
    name: 'Duża paczka',
    description: 'Przedmioty do 30kg',
    icon: '📊',
    maxWeight: 30,
    maxDimensions: { length: 80, width: 60, height: 40 },
    basePrice: 34.99
  },
  {
    id: 'pallet',
    name: 'Paleta',
    description: 'Ciężkie i duże przedmioty',
    icon: '🏗️',
    maxWeight: 1000,
    maxDimensions: { length: 120, width: 80, height: 180 },
    basePrice: 89.99
  }
];

const PackageTypeStep: React.FC<PackageTypeStepProps> = ({ data, onChange }) => {
  const handlePackageSelect = (packageType: any) => {
    onChange({
      packageType: packageType.id,
      weight: data.weight || 1,
      dimensions: data.dimensions || {
        length: Math.min(20, packageType.maxDimensions.length),
        width: Math.min(15, packageType.maxDimensions.width),
        height: Math.min(10, packageType.maxDimensions.height)
      }
    });
  };

  const handleWeightChange = (weight: number) => {
    const selectedPackage = packageTypes.find(p => p.id === data.packageType);
    if (!selectedPackage || weight <= selectedPackage.maxWeight) {
      onChange({ weight });
    }
  };

  const handleDimensionChange = (dimension: string, value: number) => {
    const selectedPackage = packageTypes.find(p => p.id === data.packageType);
    if (!selectedPackage) return;

    const newDimensions = {
      ...data.dimensions,
      [dimension]: Math.min(value, selectedPackage.maxDimensions[dimension])
    };

    onChange({ dimensions: newDimensions });
  };

  const selectedPackage = packageTypes.find(p => p.id === data.packageType);

  return (
    <div className="space-y-8">
      {/* Package Type Selection */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-4">
          Wybierz typ przesyłki
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {packageTypes.map((packageType) => (
            <PackageCard
              key={packageType.id}
              packageType={packageType}
              isSelected={data.packageType === packageType.id}
              onSelect={() => handlePackageSelect(packageType)}
            />
          ))}
        </div>
      </div>

      {/* Package Details */}
      {selectedPackage && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-white border border-gray-200 rounded-xl p-6 card-shadow"
        >
          <h4 className="text-md font-semibold text-gray-900 mb-4">
            Parametry przesyłki
          </h4>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Weight Input */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Waga (kg)
              </label>
              <div className="relative">
                <input
                  type="number"
                  min="0.1"
                  max={selectedPackage.maxWeight}
                  step="0.1"
                  value={data.weight || ''}
                  onChange={(e) => handleWeightChange(parseFloat(e.target.value))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  placeholder="1.0"
                />
                <span className="absolute right-3 top-2 text-sm text-gray-500">
                  max {selectedPackage.maxWeight}kg
                </span>
              </div>
            </div>

            {/* Dimensions */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Wymiary (cm)
              </label>
              <div className="grid grid-cols-3 gap-2">
                <div>
                  <input
                    type="number"
                    min="1"
                    max={selectedPackage.maxDimensions.length}
                    value={data.dimensions?.length || ''}
                    onChange={(e) => handleDimensionChange('length', parseInt(e.target.value))}
                    className="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                    placeholder="L"
                  />
                  <span className="text-xs text-gray-500">max {selectedPackage.maxDimensions.length}</span>
                </div>
                <div>
                  <input
                    type="number"
                    min="1"
                    max={selectedPackage.maxDimensions.width}
                    value={data.dimensions?.width || ''}
                    onChange={(e) => handleDimensionChange('width', parseInt(e.target.value))}
                    className="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                    placeholder="W"
                  />
                  <span className="text-xs text-gray-500">max {selectedPackage.maxDimensions.width}</span>
                </div>
                <div>
                  <input
                    type="number"
                    min="1"
                    max={selectedPackage.maxDimensions.height}
                    value={data.dimensions?.height || ''}
                    onChange={(e) => handleDimensionChange('height', parseInt(e.target.value))}
                    className="w-full px-2 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                    placeholder="H"
                  />
                  <span className="text-xs text-gray-500">max {selectedPackage.maxDimensions.height}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Package Preview */}
          <div className="mt-6 p-4 bg-blue-50 rounded-lg">
            <div className="flex items-center">
              <span className="text-2xl mr-3">{selectedPackage.icon}</span>
              <div>
                <p className="font-medium text-gray-900">{selectedPackage.name}</p>
                <p className="text-sm text-gray-600">
                  {data.weight || 0}kg • {data.dimensions?.length || 0} × {data.dimensions?.width || 0} × {data.dimensions?.height || 0} cm
                </p>
              </div>
              <div className="ml-auto text-right">
                <p className="text-sm text-gray-500">Cena bazowa od</p>
                <p className="font-bold text-blue-600">{selectedPackage.basePrice} PLN</p>
              </div>
            </div>
          </div>
        </motion.div>
      )}
    </div>
  );
};

export default PackageTypeStep;