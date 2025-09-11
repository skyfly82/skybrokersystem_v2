import React, { useState, useEffect } from 'react';
import { api } from '../services/api.js';

export default function ShipmentTracker({ token, addToast }) {
  const [trackingNumber, setTrackingNumber] = useState('');
  const [shipmentData, setShipmentData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [recentShipments, setRecentShipments] = useState([]);

  useEffect(() => {
    loadRecentShipments();
  }, []);

  const loadRecentShipments = async () => {
    try {
      const response = await api.get('/customer/shipments');
      if (response.data) {
        setRecentShipments(response.data);
      }
    } catch (error) {
      console.error('Failed to load shipments:', error);
      // Fallback to mock data
      setRecentShipments([
        { id: 1, trackingNumber: 'INP240901001', carrier: 'InPost', status: 'in_transit', createdAt: '2025-09-01', destination: 'Warszawa' },
        { id: 2, trackingNumber: 'DHL240830002', carrier: 'DHL', status: 'delivered', createdAt: '2025-08-30', destination: 'Kraków' },
        { id: 3, trackingNumber: 'INP240828003', carrier: 'InPost', status: 'processing', createdAt: '2025-08-28', destination: 'Gdańsk' }
      ]);
    }
  };

  const trackShipment = async () => {
    if (!trackingNumber.trim()) {
      addToast({ title: 'Błąd', body: 'Wprowadź numer przesyłki', type: 'error' });
      return;
    }

    setLoading(true);
    
    try {
      // Use public tracking API - no auth needed
      const response = await fetch(`/api/v1/shipments/track/${trackingNumber}`);
      const data = await response.json();
      
      if (data.success) {
        setShipmentData(data.data);
        addToast({ title: 'Znaleziono', body: `Przesyłka ${trackingNumber} w systemie`, type: 'success' });
      } else {
        addToast({ title: 'Błąd', body: data.error || 'Nie znaleziono przesyłki', type: 'error' });
      }
    } catch (error) {
      console.error('Tracking error:', error);
      addToast({ title: 'Błąd', body: 'Błąd podczas śledzenia przesyłki', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'delivered': return '#10b981';
      case 'in_transit': return '#f59e0b';
      case 'processing': return '#6366f1';
      default: return '#6b7280';
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'delivered': return 'Dostarczona';
      case 'in_transit': return 'W transporcie';
      case 'processing': return 'Przetwarzana';
      default: return 'Nieznany';
    }
  };

  return React.createElement('div', { style: styles.container },
    React.createElement('div', { style: styles.section },
      React.createElement('h3', { style: styles.sectionTitle }, 'Śledź przesyłkę'),
      React.createElement('div', { style: styles.trackingForm },
        React.createElement('input', {
          style: styles.trackingInput,
          type: 'text',
          placeholder: 'Wprowadź numer przesyłki (np. INP240901001)',
          value: trackingNumber,
          onChange: (e) => setTrackingNumber(e.target.value),
          onKeyPress: (e) => e.key === 'Enter' && trackShipment()
        }),
        React.createElement('button', {
          style: { ...styles.trackButton, ...(loading ? styles.loading : {}) },
          onClick: trackShipment,
          disabled: loading
        }, loading ? 'Szukam...' : 'Śledź')
      )
    ),

    shipmentData && React.createElement('div', { style: styles.section },
      React.createElement('div', { style: styles.shipmentCard },
        React.createElement('div', { style: styles.shipmentHeader },
          React.createElement('div', { style: styles.shipmentInfo },
            React.createElement('h4', { style: styles.trackingNumber }, shipmentData.trackingNumber),
            React.createElement('div', { style: styles.carrier }, shipmentData.carrier)
          ),
          React.createElement('div', { style: styles.statusBadge },
            React.createElement('span', { 
              style: { ...styles.status, color: getStatusColor(shipmentData.status) }
            }, getStatusLabel(shipmentData.status))
          )
        ),
        
        React.createElement('div', { style: styles.shipmentDetails },
          React.createElement('div', { style: styles.detail },
            React.createElement('strong', null, 'Destination: '),
            shipmentData.destination
          ),
          React.createElement('div', { style: styles.detail },
            React.createElement('strong', null, 'Przewidywana dostawa: '),
            shipmentData.estimatedDelivery
          )
        ),

        React.createElement('div', { style: styles.timeline },
          React.createElement('h5', { style: styles.timelineTitle }, 'Historia przesyłki'),
          shipmentData.timeline.map((event, i) => React.createElement('div', { key: i, style: styles.timelineItem },
            React.createElement('div', { style: styles.timelineDate }, event.date),
            React.createElement('div', { style: styles.timelineContent },
              React.createElement('div', { style: styles.timelineStatus }, event.status),
              React.createElement('div', { style: styles.timelineLocation }, event.location),
              React.createElement('div', { style: styles.timelineDescription }, event.description)
            )
          ))
        )
      )
    ),

    React.createElement('div', { style: styles.section },
      React.createElement('h3', { style: styles.sectionTitle }, 'Ostatnie przesyłki'),
      React.createElement('div', { style: styles.recentShipments },
        recentShipments.map(shipment => React.createElement('div', { key: shipment.id, style: styles.recentItem },
          React.createElement('div', { style: styles.recentInfo },
            React.createElement('div', { style: styles.recentTracking }, shipment.tracking),
            React.createElement('div', { style: styles.recentCarrier }, shipment.carrier),
            React.createElement('div', { style: styles.recentDestination }, shipment.destination)
          ),
          React.createElement('div', { style: styles.recentMeta },
            React.createElement('div', { style: { ...styles.recentStatus, color: getStatusColor(shipment.status) } },
              getStatusLabel(shipment.status)
            ),
            React.createElement('div', { style: styles.recentDate }, shipment.created)
          ),
          React.createElement('button', {
            style: styles.quickTrack,
            onClick: () => {
              setTrackingNumber(shipment.tracking);
              trackShipment();
            }
          }, 'Śledź')
        ))
      )
    )
  );
}

const styles = {
  container: {
    display: 'grid',
    gap: 24
  },
  section: {
    border: '1px solid var(--border)',
    borderRadius: 12,
    background: '#fff',
    padding: 20,
    boxShadow: '0 8px 26px rgba(2,6,23,0.05)'
  },
  sectionTitle: {
    margin: '0 0 16px 0',
    fontSize: 18,
    fontWeight: 800
  },
  trackingForm: {
    display: 'flex',
    gap: 12
  },
  trackingInput: {
    flex: 1,
    padding: 12,
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: 14
  },
  trackButton: {
    padding: '12px 24px',
    border: 'none',
    borderRadius: 8,
    background: 'var(--primary)',
    color: '#fff',
    fontSize: 14,
    fontWeight: 600,
    cursor: 'pointer',
    minWidth: 100
  },
  loading: {
    opacity: 0.6,
    cursor: 'not-allowed'
  },
  shipmentCard: {
    border: '1px solid var(--border)',
    borderRadius: 8,
    padding: 16,
    background: 'var(--primary-50)'
  },
  shipmentHeader: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16
  },
  shipmentInfo: {
    display: 'flex',
    flexDirection: 'column',
    gap: 4
  },
  trackingNumber: {
    margin: 0,
    fontSize: 20,
    fontWeight: 800,
    fontFamily: 'monospace'
  },
  carrier: {
    fontSize: 14,
    color: 'var(--muted)'
  },
  statusBadge: {
    padding: '6px 12px',
    background: '#fff',
    borderRadius: 20,
    border: '1px solid var(--border)'
  },
  status: {
    fontSize: 14,
    fontWeight: 600
  },
  shipmentDetails: {
    display: 'grid',
    gap: 8,
    marginBottom: 20
  },
  detail: {
    fontSize: 14
  },
  timeline: {
    borderTop: '1px solid var(--border)',
    paddingTop: 16
  },
  timelineTitle: {
    margin: '0 0 16px 0',
    fontSize: 16,
    fontWeight: 700
  },
  timelineItem: {
    display: 'flex',
    gap: 16,
    marginBottom: 16,
    paddingBottom: 16,
    borderBottom: '1px solid #e5e7eb'
  },
  timelineDate: {
    fontSize: 12,
    color: 'var(--muted)',
    minWidth: 120,
    fontFamily: 'monospace'
  },
  timelineContent: {
    flex: 1
  },
  timelineStatus: {
    fontSize: 14,
    fontWeight: 600,
    marginBottom: 2
  },
  timelineLocation: {
    fontSize: 13,
    color: 'var(--primary)',
    marginBottom: 4
  },
  timelineDescription: {
    fontSize: 13,
    color: 'var(--muted)'
  },
  recentShipments: {
    display: 'grid',
    gap: 12
  },
  recentItem: {
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 12,
    border: '1px solid var(--border)',
    borderRadius: 8,
    background: '#fff'
  },
  recentInfo: {
    display: 'flex',
    flexDirection: 'column',
    gap: 2
  },
  recentTracking: {
    fontFamily: 'monospace',
    fontWeight: 600,
    fontSize: 14
  },
  recentCarrier: {
    fontSize: 12,
    color: 'var(--muted)'
  },
  recentDestination: {
    fontSize: 12,
    color: 'var(--muted)'
  },
  recentMeta: {
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'flex-end',
    gap: 2
  },
  recentStatus: {
    fontSize: 12,
    fontWeight: 600
  },
  recentDate: {
    fontSize: 11,
    color: 'var(--muted)'
  },
  quickTrack: {
    padding: '6px 12px',
    border: '1px solid var(--border)',
    borderRadius: 6,
    background: '#fff',
    fontSize: 12,
    cursor: 'pointer'
  }
};