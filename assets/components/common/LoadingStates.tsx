import React from 'react';
import { motion } from 'framer-motion';

// Skeleton loader component
export const SkeletonLoader: React.FC<{ className?: string }> = ({ className = '' }) => (
  <div className={`animate-pulse bg-gray-200 rounded ${className}`} />
);

// Card skeleton
export const CardSkeleton: React.FC = () => (
  <div className="bg-white border border-gray-200 rounded-xl p-6 card-shadow">
    <div className="animate-pulse">
      <div className="flex items-center">
        <div className="w-12 h-12 bg-gray-200 rounded-lg"></div>
        <div className="ml-4 flex-1">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-2"></div>
          <div className="h-6 bg-gray-200 rounded w-1/3"></div>
        </div>
      </div>
    </div>
  </div>
);

// Table skeleton
export const TableSkeleton: React.FC<{ rows?: number; columns?: number }> = ({
  rows = 5,
  columns = 4
}) => (
  <div className="bg-white border border-gray-200 rounded-xl p-6 card-shadow">
    <div className="animate-pulse">
      {/* Header */}
      <div className="grid gap-4 mb-4" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
        {Array.from({ length: columns }).map((_, i) => (
          <div key={i} className="h-4 bg-gray-200 rounded"></div>
        ))}
      </div>

      {/* Rows */}
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <div key={rowIndex} className="grid gap-4 mb-3" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
          {Array.from({ length: columns }).map((_, colIndex) => (
            <div key={colIndex} className="h-3 bg-gray-100 rounded"></div>
          ))}
        </div>
      ))}
    </div>
  </div>
);

// Spinner component
export const LoadingSpinner: React.FC<{
  size?: 'sm' | 'md' | 'lg';
  color?: 'blue' | 'gray' | 'white';
}> = ({ size = 'md', color = 'blue' }) => {
  const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-8 h-8'
  };

  const colorClasses = {
    blue: 'text-blue-600',
    gray: 'text-gray-600',
    white: 'text-white'
  };

  return (
    <motion.div
      animate={{ rotate: 360 }}
      transition={{ duration: 1, repeat: Infinity, ease: "linear" }}
      className={`${sizeClasses[size]} ${colorClasses[color]}`}
    >
      <svg className="w-full h-full" fill="none" viewBox="0 0 24 24">
        <circle
          className="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          strokeWidth="4"
        />
        <path
          className="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        />
      </svg>
    </motion.div>
  );
};

// Button loading state
export const LoadingButton: React.FC<{
  loading: boolean;
  children: React.ReactNode;
  className?: string;
  disabled?: boolean;
  onClick?: () => void;
}> = ({ loading, children, className = '', disabled, onClick }) => (
  <button
    onClick={onClick}
    disabled={disabled || loading}
    className={`relative flex items-center justify-center ${className} ${
      (disabled || loading) ? 'opacity-50 cursor-not-allowed' : ''
    }`}
  >
    {loading && (
      <div className="absolute inset-0 flex items-center justify-center">
        <LoadingSpinner size="sm" color="white" />
      </div>
    )}
    <span className={loading ? 'opacity-0' : 'opacity-100'}>
      {children}
    </span>
  </button>
);

// Page loading overlay
export const PageLoadingOverlay: React.FC<{ message?: string }> = ({
  message = 'Ładowanie...'
}) => (
  <motion.div
    initial={{ opacity: 0 }}
    animate={{ opacity: 1 }}
    exit={{ opacity: 0 }}
    className="fixed inset-0 bg-white bg-opacity-80 backdrop-blur-sm z-50 flex items-center justify-center"
  >
    <div className="text-center">
      <LoadingSpinner size="lg" />
      <p className="mt-4 text-gray-600 text-lg">{message}</p>
    </div>
  </motion.div>
);

// Inline loading state for content areas
export const InlineLoader: React.FC<{
  message?: string;
  className?: string;
}> = ({ message = 'Ładowanie...', className = '' }) => (
  <div className={`flex items-center justify-center py-8 ${className}`}>
    <div className="text-center">
      <LoadingSpinner size="md" />
      <p className="mt-2 text-gray-600">{message}</p>
    </div>
  </div>
);

// Pricing calculation loader
export const PricingLoader: React.FC = () => (
  <motion.div
    initial={{ opacity: 0, scale: 0.95 }}
    animate={{ opacity: 1, scale: 1 }}
    className="bg-blue-50 border border-blue-200 rounded-lg p-6"
  >
    <div className="flex items-center">
      <LoadingSpinner size="md" color="blue" />
      <div className="ml-4">
        <p className="font-medium text-blue-900">Obliczanie cen...</p>
        <p className="text-sm text-blue-700">Porównujemy oferty przewoźników</p>
      </div>
    </div>

    {/* Animated progress bar */}
    <div className="mt-4 w-full bg-blue-200 rounded-full h-2">
      <motion.div
        className="bg-blue-600 h-2 rounded-full"
        initial={{ width: '0%' }}
        animate={{ width: '100%' }}
        transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
      />
    </div>
  </motion.div>
);

// Empty state component
export const EmptyState: React.FC<{
  icon: React.ReactNode;
  title: string;
  description: string;
  action?: {
    label: string;
    onClick: () => void;
  };
}> = ({ icon, title, description, action }) => (
  <div className="text-center py-12">
    <div className="text-gray-400 mb-4 flex justify-center">
      {icon}
    </div>
    <h3 className="text-lg font-medium text-gray-900 mb-2">{title}</h3>
    <p className="text-gray-600 mb-6 max-w-md mx-auto">{description}</p>
    {action && (
      <button
        onClick={action.onClick}
        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
      >
        {action.label}
      </button>
    )}
  </div>
);

// Error state component
export const ErrorState: React.FC<{
  title?: string;
  message: string;
  onRetry?: () => void;
}> = ({
  title = 'Wystąpił błąd',
  message,
  onRetry
}) => (
  <div className="bg-red-50 border border-red-200 rounded-lg p-6">
    <div className="flex items-center">
      <div className="text-red-400">
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <div className="ml-3 flex-1">
        <h3 className="text-sm font-medium text-red-800">{title}</h3>
        <p className="text-sm text-red-700 mt-1">{message}</p>
      </div>
      {onRetry && (
        <button
          onClick={onRetry}
          className="ml-3 px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors"
        >
          Spróbuj ponownie
        </button>
      )}
    </div>
  </div>
);