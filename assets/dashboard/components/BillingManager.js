import React, { useState, useEffect } from 'react';
import { api } from '../services/api.js';

export default function BillingManager({ token, addToast }) {
  const [invoices, setInvoices] = useState([]);
  const [payments, setPayments] = useState([]);
  const [activeTab, setActiveTab] = useState('invoices');
  const [billingStats, setBillingStats] = useState({
    totalAmount: '0.00',
    paidAmount: '0.00',
    pendingAmount: '0.00',
    overdueAmount: '0.00'
  });

  useEffect(() => {
    loadBillingData();
  }, []);

  const loadBillingData = async () => {
    try {
      // Load data from real API endpoints
      const [invoicesResponse, paymentsResponse, statsResponse] = await Promise.all([
        api.get('/customer/invoices'),
        api.get('/customer/payments'),
        api.get('/customer/billing/stats')
      ]);

      if (invoicesResponse.success && invoicesResponse.data) {
        setInvoices(invoicesResponse.data);
      }

      if (paymentsResponse.success && paymentsResponse.data) {
        setPayments(paymentsResponse.data);
      }

      if (statsResponse.success && statsResponse.data) {
        setBillingStats({
          totalAmount: statsResponse.data.totalAmount.toFixed(2),
          paidAmount: statsResponse.data.paidAmount.toFixed(2),
          pendingAmount: statsResponse.data.pendingAmount.toFixed(2),
          overdueAmount: statsResponse.data.overdueAmount.toFixed(2)
        });
      }

    } catch (error) {
      console.error('Failed to load billing data:', error);
      addToast({ title: 'Błąd', body: 'Nie udało się załadować danych rozliczeniowych', type: 'error' });
      
      // Fallback to mock data on error
      const mockInvoices = [
        {
          id: 'INV-2025-001',
          invoiceNumber: 'INV-2025-001',
          date: '2025-09-10',
          dueDate: '2025-09-24',
          amount: 299.99,
          status: 'paid',
          items: [
            { description: 'Przesyłka INP240901001', quantity: 1, unitPrice: 18.45, totalPrice: 18.45 },
            { description: 'Przesyłka INP240902002', quantity: 1, unitPrice: 22.30, totalPrice: 22.30 },
            { description: 'Przesyłka DHL240903001', quantity: 1, unitPrice: 45.00, totalPrice: 45.00 }
          ]
        }
      ];

      const mockPayments = [
        {
          id: 'PAY-001',
          paymentId: 'PAY-001',
          invoiceId: 'INV-2025-001',
          date: '2025-09-11',
          amount: 299.99,
          method: 'transfer',
          methodLabel: 'Przelew bankowy',
          status: 'completed',
          statusLabel: 'Zakończona'
        }
      ];

      setInvoices(mockInvoices);
      setPayments(mockPayments);
      setBillingStats({
        totalAmount: '299.99',
        paidAmount: '299.99',
        pendingAmount: '0.00',
        overdueAmount: '0.00'
      });
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'paid': case 'completed': return '#10b981';
      case 'pending': case 'processing': return '#f59e0b';
      case 'overdue': case 'failed': return '#ef4444';
      default: return '#6b7280';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'paid': return 'Opłacona';
      case 'pending': return 'Oczekująca';
      case 'overdue': return 'Zaległość';
      case 'completed': return 'Zakończona';
      case 'processing': return 'W toku';
      case 'failed': return 'Niepowodzenie';
      default: return status;
    }
  };

  const downloadInvoice = async (invoiceId) => {
    try {
      addToast({ title: 'Pobieranie', body: `Generowanie faktury ${invoiceId}...`, type: 'info' });
      
      const response = await fetch(`/api/v1/customer/invoices/${invoiceId}/download`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `invoice-${invoiceId}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        addToast({ title: 'Sukces', body: `Faktura ${invoiceId} została pobrana`, type: 'success' });
      } else {
        throw new Error('Download failed');
      }
    } catch (error) {
      console.error('Download error:', error);
      addToast({ title: 'Błąd', body: 'Nie udało się pobrać faktury', type: 'error' });
    }
  };

  const payInvoice = async (invoiceId) => {
    try {
      const response = await api.post(`/customer/invoices/${invoiceId}/pay`, {
        method: 'card'
      });

      if (response.success && response.data.paymentUrl) {
        addToast({ title: 'Płatność', body: 'Przekierowanie do płatności...', type: 'info' });
        window.open(response.data.paymentUrl, '_blank');
      } else {
        addToast({ title: 'Płatność', body: `Płatność za fakturę ${invoiceId} została zainicjowana`, type: 'success' });
        // Refresh data after payment initiation
        setTimeout(loadBillingData, 1000);
      }
    } catch (error) {
      console.error('Payment error:', error);
      addToast({ title: 'Błąd', body: 'Nie udało się zainicjować płatności', type: 'error' });
    }
  };

  return React.createElement('div', { style: styles.container },
    // Stats overview
    React.createElement('div', { style: styles.statsGrid },
      React.createElement('div', { style: styles.statCard },
        React.createElement('div', { style: styles.statValue }, billingStats.totalAmount, ' PLN'),
        React.createElement('div', { style: styles.statLabel }, 'Łącznie do zapłaty')
      ),
      React.createElement('div', { style: { ...styles.statCard, ...styles.statPaid } },
        React.createElement('div', { style: styles.statValue }, billingStats.paidAmount, ' PLN'),
        React.createElement('div', { style: styles.statLabel }, 'Opłacone')
      ),
      React.createElement('div', { style: { ...styles.statCard, ...styles.statPending } },
        React.createElement('div', { style: styles.statValue }, billingStats.pendingAmount, ' PLN'),
        React.createElement('div', { style: styles.statLabel }, 'Oczekujące')
      ),
      React.createElement('div', { style: { ...styles.statCard, ...styles.statOverdue } },
        React.createElement('div', { style: styles.statValue }, billingStats.overdueAmount, ' PLN'),
        React.createElement('div', { style: styles.statLabel }, 'Zaległości')
      )
    ),

    // Tabs
    React.createElement('div', { style: styles.tabsContainer },
      React.createElement('div', { style: styles.tabs },
        React.createElement('button', {
          style: { ...styles.tab, ...(activeTab === 'invoices' ? styles.tabActive : {}) },
          onClick: () => setActiveTab('invoices')
        }, 'Faktury'),
        React.createElement('button', {
          style: { ...styles.tab, ...(activeTab === 'payments' ? styles.tabActive : {}) },
          onClick: () => setActiveTab('payments')
        }, 'Płatności')
      ),

      React.createElement('div', { style: styles.tabContent },
        activeTab === 'invoices' && React.createElement('div', { style: styles.invoicesGrid },
          invoices.map(invoice => React.createElement('div', { key: invoice.id, style: styles.invoiceCard },
            React.createElement('div', { style: styles.invoiceHeader },
              React.createElement('div', { style: styles.invoiceInfo },
                React.createElement('h4', { style: styles.invoiceId }, invoice.id),
                React.createElement('div', { style: styles.invoiceDate }, 'Data wystawienia: ', invoice.date),
                React.createElement('div', { style: styles.invoiceDue }, 'Termin płatności: ', invoice.dueDate)
              ),
              React.createElement('div', { style: styles.invoiceAmount }, invoice.amount.toFixed(2), ' PLN')
            ),

            React.createElement('div', { style: styles.invoiceStatus },
              React.createElement('span', {
                style: { ...styles.statusBadge, backgroundColor: getStatusColor(invoice.status) + '20', color: getStatusColor(invoice.status) }
              }, getStatusLabel(invoice.status))
            ),

            React.createElement('div', { style: styles.invoiceItems },
              React.createElement('h5', { style: styles.itemsTitle }, 'Pozycje:'),
              invoice.items.map((item, i) => React.createElement('div', { key: i, style: styles.invoiceItem },
                React.createElement('span', { style: styles.itemDescription }, item.description),
                React.createElement('span', { style: styles.itemAmount }, (item.quantity * item.price).toFixed(2), ' PLN')
              ))
            ),

            React.createElement('div', { style: styles.invoiceActions },
              React.createElement('button', {
                style: styles.actionBtn,
                onClick: () => downloadInvoice(invoice.id)
              }, 'Pobierz PDF'),
              invoice.status === 'pending' || invoice.status === 'overdue' ? React.createElement('button', {
                style: { ...styles.actionBtn, ...styles.payBtn },
                onClick: () => payInvoice(invoice.id)
              }, 'Zapłać') : null
            )
          ))
        ),

        activeTab === 'payments' && React.createElement('div', { style: styles.paymentsTable },
          React.createElement('div', { style: styles.tableHeader },
            ['ID Płatności', 'Faktura', 'Data', 'Kwota', 'Metoda', 'Status'].map(h =>
              React.createElement('div', { key: h, style: styles.headerCell }, h)
            )
          ),
          payments.map(payment => React.createElement('div', { key: payment.id, style: styles.tableRow },
            React.createElement('div', { style: styles.cell }, payment.id),
            React.createElement('div', { style: styles.cell }, payment.invoiceId),
            React.createElement('div', { style: styles.cell }, payment.date),
            React.createElement('div', { style: styles.cell }, payment.amount.toFixed(2), ' PLN'),
            React.createElement('div', { style: styles.cell }, 
              payment.method === 'transfer' ? 'Przelew' : payment.method === 'card' ? 'Karta' : payment.method
            ),
            React.createElement('div', { style: styles.cell },
              React.createElement('span', {
                style: { ...styles.statusBadge, backgroundColor: getStatusColor(payment.status) + '20', color: getStatusColor(payment.status) }
              }, getStatusLabel(payment.status))
            )
          ))
        )
      )
    )
  );
}

const styles = {
  container: {
    display: 'grid',
    gap: 24
  },
  statsGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: 16
  },
  statCard: {
    padding: 20,
    border: '1px solid var(--border)',
    borderRadius: 12,
    background: '#fff',
    textAlign: 'center',
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)'
  },
  statPaid: {
    background: 'linear-gradient(135deg, #dcfce7, #bbf7d0)'
  },
  statPending: {
    background: 'linear-gradient(135deg, #fef3c7, #fed7aa)'
  },
  statOverdue: {
    background: 'linear-gradient(135deg, #fee2e2, #fca5a5)'
  },
  statValue: {
    fontSize: 24,
    fontWeight: 800,
    marginBottom: 4,
    color: 'var(--primary)'
  },
  statLabel: {
    fontSize: 12,
    color: 'var(--muted)',
    textTransform: 'uppercase',
    letterSpacing: 0.5
  },
  tabsContainer: {
    border: '1px solid var(--border)',
    borderRadius: 12,
    background: '#fff',
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)',
    overflow: 'hidden'
  },
  tabs: {
    display: 'flex',
    borderBottom: '1px solid var(--border)'
  },
  tab: {
    flex: 1,
    padding: 16,
    border: 'none',
    background: '#fff',
    fontSize: 14,
    fontWeight: 600,
    cursor: 'pointer',
    color: 'var(--muted)'
  },
  tabActive: {
    background: 'var(--primary-50)',
    color: 'var(--primary)',
    borderBottom: '2px solid var(--primary)'
  },
  tabContent: {
    padding: 20
  },
  invoicesGrid: {
    display: 'grid',
    gap: 16
  },
  invoiceCard: {
    border: '1px solid var(--border)',
    borderRadius: 8,
    padding: 16,
    background: '#fff'
  },
  invoiceHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12
  },
  invoiceInfo: {
    display: 'flex',
    flexDirection: 'column',
    gap: 4
  },
  invoiceId: {
    margin: 0,
    fontSize: 18,
    fontWeight: 800,
    fontFamily: 'monospace'
  },
  invoiceDate: {
    fontSize: 12,
    color: 'var(--muted)'
  },
  invoiceDue: {
    fontSize: 12,
    color: 'var(--muted)'
  },
  invoiceAmount: {
    fontSize: 20,
    fontWeight: 800,
    color: 'var(--primary)'
  },
  invoiceStatus: {
    marginBottom: 16
  },
  statusBadge: {
    padding: '4px 12px',
    borderRadius: 16,
    fontSize: 12,
    fontWeight: 600
  },
  invoiceItems: {
    marginBottom: 16
  },
  itemsTitle: {
    margin: '0 0 8px 0',
    fontSize: 14,
    fontWeight: 600
  },
  invoiceItem: {
    display: 'flex',
    justifyContent: 'space-between',
    padding: '4px 0',
    fontSize: 13,
    borderBottom: '1px solid #f3f4f6'
  },
  itemDescription: {
    color: 'var(--text)'
  },
  itemAmount: {
    fontWeight: 600
  },
  invoiceActions: {
    display: 'flex',
    gap: 8,
    justifyContent: 'flex-end'
  },
  actionBtn: {
    padding: '8px 16px',
    border: '1px solid var(--border)',
    borderRadius: 6,
    background: '#fff',
    fontSize: 12,
    fontWeight: 600,
    cursor: 'pointer'
  },
  payBtn: {
    background: 'var(--primary)',
    borderColor: 'var(--primary)',
    color: '#fff'
  },
  paymentsTable: {
    border: '1px solid var(--border)',
    borderRadius: 8,
    overflow: 'hidden'
  },
  tableHeader: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr 1fr 1fr 1fr 1fr',
    background: 'var(--primary-50)',
    borderBottom: '1px solid var(--border)'
  },
  tableRow: {
    display: 'grid',
    gridTemplateColumns: '1fr 1fr 1fr 1fr 1fr 1fr',
    borderBottom: '1px solid var(--border)'
  },
  headerCell: {
    padding: 12,
    fontSize: 12,
    fontWeight: 800,
    textTransform: 'uppercase'
  },
  cell: {
    padding: 12,
    fontSize: 14,
    display: 'flex',
    alignItems: 'center'
  }
};