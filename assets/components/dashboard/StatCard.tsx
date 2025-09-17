import React from 'react';
import {
  ClipboardDocumentListIcon,
  TruckIcon,
  CreditCardIcon,
  CurrencyDollarIcon
} from '@heroicons/react/24/outline';

interface StatCardProps {
  title: string;
  value: string | number;
  icon: 'orders' | 'shipment' | 'invoice' | 'revenue';
  color: 'blue' | 'yellow' | 'green' | 'purple';
  trend?: {
    value: number;
    direction: 'up' | 'down';
  };
}

const iconMap = {
  orders: ClipboardDocumentListIcon,
  shipment: TruckIcon,
  invoice: CreditCardIcon,
  revenue: CurrencyDollarIcon,
};

const colorMap = {
  blue: {
    bg: 'bg-blue-50',
    icon: 'text-blue-600',
    trend: 'text-blue-600'
  },
  yellow: {
    bg: 'bg-yellow-50',
    icon: 'text-yellow-600',
    trend: 'text-yellow-600'
  },
  green: {
    bg: 'bg-green-50',
    icon: 'text-green-600',
    trend: 'text-green-600'
  },
  purple: {
    bg: 'bg-purple-50',
    icon: 'text-purple-600',
    trend: 'text-purple-600'
  }
};

const StatCard: React.FC<StatCardProps> = ({ title, value, icon, color, trend }) => {
  const IconComponent = iconMap[icon];
  const colors = colorMap[color];

  return (
    <div className="bg-white border border-gray-200 rounded-xl p-6 card-shadow transition-transform hover:scale-105">
      <div className="flex items-center">
        <div className={`p-2 ${colors.bg} rounded-lg`}>
          <IconComponent className={`w-8 h-8 ${colors.icon}`} />
        </div>
        <div className="ml-4 flex-1">
          <p className="text-sm font-medium text-gray-500">{title}</p>
          <div className="flex items-center">
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            {trend && (
              <div className={`ml-2 flex items-center text-xs ${colors.trend}`}>
                <svg
                  className={`w-3 h-3 mr-1 ${
                    trend.direction === 'up' ? 'transform rotate-0' : 'transform rotate-180'
                  }`}
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
                    clipRule="evenodd"
                  />
                </svg>
                <span>+{trend.value}%</span>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default StatCard;