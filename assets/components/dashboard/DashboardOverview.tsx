import React, { useEffect, useState } from 'react';
import StatCard from './StatCard';
import ActivityChart from './ActivityChart';
import RecentOrdersTable from './RecentOrdersTable';
import AccountBalance from './AccountBalance';
import { useDashboardData } from '../../hooks/useDashboardData';
import LoadingSpinner from '../ui/LoadingSpinner';

interface DashboardStats {
  totalOrders: number;
  inTransit: number;
  totalInvoices: number;
  monthlyRevenue: number;
}

const DashboardOverview: React.FC = () => {
  const { data, loading, error, refetch } = useDashboardData();
  const [stats, setStats] = useState<DashboardStats>({
    totalOrders: 0,
    inTransit: 0,
    totalInvoices: 0,
    monthlyRevenue: 0
  });

  useEffect(() => {
    if (data) {
      setStats({
        totalOrders: data.orders?.total || 0,
        inTransit: data.orders?.inTransit || 0,
        totalInvoices: data.invoices?.total || 0,
        monthlyRevenue: data.revenue?.monthly || 0
      });
    }
  }, [data]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <p className="text-red-700">Błąd podczas ładowania danych: {error}</p>
        <button
          onClick={refetch}
          className="mt-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
        >
          Spróbuj ponownie
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Pulpit</h1>
        <p className="mt-1 text-sm text-gray-600">
          Zarządzaj zamówieniami i przeglądaj aktywność
        </p>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-6">
        <StatCard
          title="Zamówienia"
          value={stats.totalOrders}
          icon="orders"
          color="blue"
          trend={{ value: 12, direction: 'up' }}
        />
        <StatCard
          title="W tranzycie"
          value={stats.inTransit}
          icon="shipment"
          color="yellow"
          trend={{ value: 3, direction: 'up' }}
        />
        <StatCard
          title="Faktury"
          value={stats.totalInvoices}
          icon="invoice"
          color="green"
          trend={{ value: 8, direction: 'up' }}
        />
        <StatCard
          title="Przychód (mies.)"
          value={`${stats.monthlyRevenue.toLocaleString()} PLN`}
          icon="revenue"
          color="purple"
          trend={{ value: 15, direction: 'up' }}
        />
      </div>

      {/* Account Balance */}
      <AccountBalance />

      {/* Charts and Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ActivityChart data={data?.chartData} />
        <div className="space-y-4">
          <h3 className="text-lg font-semibold text-gray-900">Ostatnia aktywność</h3>
          {/* Activity feed will be implemented here */}
          <div className="bg-white border border-gray-200 rounded-xl p-6 card-shadow">
            <p className="text-gray-500">Historia aktywności będzie dostępna wkrótce</p>
          </div>
        </div>
      </div>

      {/* Recent Orders */}
      <RecentOrdersTable orders={data?.recentOrders || []} />
    </div>
  );
};

export default DashboardOverview;