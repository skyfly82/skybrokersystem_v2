import React, { useEffect, useRef } from 'react';

// Simple Chart.js wrapper for dashboard charts
export default function Chart({ type, data, options = {}, height = 200 }) {
  const canvasRef = useRef(null);
  const chartRef = useRef(null);

  useEffect(() => {
    if (!canvasRef.current || !window.Chart) {
      // Load Chart.js dynamically if not available
      loadChartJS().then(() => {
        createChart();
      });
    } else {
      createChart();
    }

    return () => {
      if (chartRef.current) {
        chartRef.current.destroy();
      }
    };
  }, [type, data, options]);

  const loadChartJS = async () => {
    if (window.Chart) return;
    
    return new Promise((resolve) => {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
      script.onload = () => resolve();
      document.head.appendChild(script);
    });
  };

  const createChart = () => {
    if (!canvasRef.current || !window.Chart) return;

    if (chartRef.current) {
      chartRef.current.destroy();
    }

    const ctx = canvasRef.current.getContext('2d');
    chartRef.current = new window.Chart(ctx, {
      type,
      data,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: type !== 'line',
            position: 'bottom'
          }
        },
        scales: type !== 'doughnut' && type !== 'pie' ? {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            }
          }
        } : undefined,
        ...options
      }
    });
  };

  return React.createElement('div', { style: { height, position: 'relative' } },
    React.createElement('canvas', { ref: canvasRef })
  );
}