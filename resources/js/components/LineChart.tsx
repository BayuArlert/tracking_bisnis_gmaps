import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js';
import { Line } from 'react-chartjs-2';
import { Card } from './ui/card';

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

interface DataPoint {
  period: string;
  [key: string]: string | number;
}

interface LineChartProps {
  data: DataPoint[];
  lines: string[];
  title: string;
  height?: number;
  showGrid?: boolean;
  showLegend?: boolean;
  showTooltips?: boolean;
  animated?: boolean;
}

const LineChart: React.FC<LineChartProps> = ({
  data,
  lines,
  title,
  height = 400,
  showGrid = true,
  showLegend = true,
  showTooltips = true,
  animated = true,
}) => {
  // Chart.js color palette
  const colors = [
    {
      border: '#3B82F6', // blue-500
      background: 'rgba(59, 130, 246, 0.1)',
      pointBackground: '#3B82F6',
      pointBorder: '#ffffff',
    },
    {
      border: '#10B981', // emerald-500
      background: 'rgba(16, 185, 129, 0.1)',
      pointBackground: '#10B981',
      pointBorder: '#ffffff',
    },
    {
      border: '#8B5CF6', // violet-500
      background: 'rgba(139, 92, 246, 0.1)',
      pointBackground: '#8B5CF6',
      pointBorder: '#ffffff',
    },
    {
      border: '#F59E0B', // amber-500
      background: 'rgba(245, 158, 11, 0.1)',
      pointBackground: '#F59E0B',
      pointBorder: '#ffffff',
    },
    {
      border: '#EF4444', // red-500
      background: 'rgba(239, 68, 68, 0.1)',
      pointBackground: '#EF4444',
      pointBorder: '#ffffff',
    },
    {
      border: '#EC4899', // pink-500
      background: 'rgba(236, 72, 153, 0.1)',
      pointBackground: '#EC4899',
      pointBorder: '#ffffff',
    },
  ];

  // Prepare chart data
  const chartData = {
    labels: data.map(point => point.period),
    datasets: lines.map((line, index) => {
      const color = colors[index % colors.length];
      return {
        label: line,
        data: data.map(point => Number(point[line]) || 0),
        borderColor: color.border,
        backgroundColor: color.background,
        pointBackgroundColor: color.pointBackground,
        pointBorderColor: color.pointBorder,
        pointBorderWidth: 2,
        pointRadius: 6,
        pointHoverRadius: 8,
        borderWidth: 3,
        fill: true,
        tension: 0.1, // Smooth curves
      };
    }),
  };

  // Chart options
  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: showLegend,
        position: 'bottom' as const,
        labels: {
          usePointStyle: true,
          padding: 20,
          font: {
            size: 12,
            family: 'Inter, sans-serif',
          },
        },
      },
      title: {
        display: false, // We handle title in Card component
      },
      tooltip: {
        enabled: showTooltips,
        mode: 'index' as const,
        intersect: false,
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        titleColor: '#ffffff',
        bodyColor: '#ffffff',
        borderColor: '#e5e7eb',
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: true,
        callbacks: {
          title: function(context: any) {
            return `Periode: ${context[0].label}`;
          },
          label: function(context: any) {
            return `${context.dataset.label}: ${context.parsed.y.toLocaleString()} bisnis`;
          },
        },
      },
    },
    scales: {
      x: {
        display: true,
        grid: {
          display: showGrid,
          color: '#e5e7eb',
          drawBorder: false,
        },
        ticks: {
          font: {
            size: 11,
            family: 'Inter, sans-serif',
          },
          color: '#6b7280',
          maxRotation: 45,
          minRotation: 0,
        },
      },
      y: {
        display: true,
        beginAtZero: true, // Always start from 0
        grid: {
          display: showGrid,
          color: '#e5e7eb',
          drawBorder: false,
        },
        ticks: {
          font: {
            size: 11,
            family: 'Inter, sans-serif',
          },
          color: '#6b7280',
          callback: function(value: any) {
            return value.toLocaleString();
          },
        },
      },
    },
    animation: {
      duration: animated ? 1000 : 0,
      easing: 'easeInOutQuart' as const,
    },
    interaction: {
      mode: 'index' as const,
      intersect: false,
    },
  };

  if (!data || data.length === 0) {
    return (
      <Card className="p-6 rounded-2xl shadow-lg">
        <h3 className="text-lg font-bold text-gray-900 mb-4">{title}</h3>
        <div className="flex items-center justify-center h-64 text-gray-500">
          <div className="text-center">
            <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <p className="text-sm">Belum ada data trend</p>
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card className="p-6 rounded-2xl shadow-lg">
      <h3 className="text-lg font-bold text-gray-900 mb-6">{title}</h3>
      
      {/* Chart Container */}
      <div style={{ height: `${height}px` }}>
        <Line data={chartData} options={options} />
      </div>
      
      {/* Additional Info */}
      <div className="mt-4 text-sm text-gray-600">
        <div className="flex justify-between items-center">
          <span>Total periode: {data.length} minggu</span>
          <span>
            Total bisnis: {data.reduce((sum, point) => 
              sum + lines.reduce((lineSum, line) => 
                lineSum + (Number(point[line]) || 0), 0
              ), 0
            ).toLocaleString()}
          </span>
        </div>
      </div>
    </Card>
  );
};

export default LineChart;
