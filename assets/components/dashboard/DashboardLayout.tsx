import React, { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from '../common/Sidebar';
import TopNavigation from '../common/TopNavigation';
import NotificationToast from '../common/NotificationToast';

interface DashboardLayoutProps {
  children?: React.ReactNode;
}

const DashboardLayout: React.FC<DashboardLayoutProps> = ({ children }) => {
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top Navigation */}
      <TopNavigation onMenuClick={() => setSidebarOpen(!sidebarOpen)} />

      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-40 md:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Main layout */}
      <div className="flex h-screen pt-16">
        {/* Sidebar */}
        <Sidebar
          isOpen={sidebarOpen}
          onClose={() => setSidebarOpen(false)}
        />

        {/* Main content */}
        <main className="flex-1 overflow-y-auto md:ml-0">
          <div className="p-6">
            {children || <Outlet />}
          </div>
        </main>
      </div>

      {/* Toast notifications */}
      <NotificationToast />
    </div>
  );
};

export default DashboardLayout;