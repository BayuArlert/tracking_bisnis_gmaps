import React from 'react';
import { Card } from "../components/ui/card";

interface GrowthMetricsProps {
  totalBusinesses: number;
  newBusinesses: number;
  recentlyOpened: number;
  highConfidenceNew: number;
  growthRate: number;
  period: string;
}

const GrowthMetrics: React.FC<GrowthMetricsProps> = ({
  totalBusinesses,
  newBusinesses,
  recentlyOpened,
  highConfidenceNew,
  growthRate,
  period,
}) => {
  const getPeriodLabel = (period: string): string => {
    const labels: { [key: string]: string } = {
      '7': '7 hari',
      '30': '30 hari',
      '60': '60 hari',
      '90': '90 hari',
      '180': '180 hari',
      'all': 'semua waktu',
    };
    return labels[period] || period;
  };

  const metrics = [
    {
      title: 'Total Bisnis',
      value: totalBusinesses.toLocaleString(),
      icon: '🏢',
      color: 'from-blue-500 to-blue-600',
      subtitle: 'Dalam database',
    },
    {
      title: 'Bisnis Baru',
      value: newBusinesses.toLocaleString(),
      icon: '✨',
      color: 'from-green-500 to-green-600',
      subtitle: `${getPeriodLabel(period)} terakhir`,
    },
    {
      title: 'Recently Opened',
      value: recentlyOpened.toLocaleString(),
      icon: '🎉',
      color: 'from-purple-500 to-purple-600',
      subtitle: 'Dengan label "baru"',
    },
    {
      title: 'High Confidence',
      value: highConfidenceNew.toLocaleString(),
      icon: '⭐',
      color: 'from-orange-500 to-orange-600',
      subtitle: 'Score ≥ 60',
    },
  ];

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      {metrics.map((metric, index) => (
        <Card key={index} className={`p-6 rounded-2xl shadow-lg bg-gradient-to-br ${metric.color} text-white overflow-hidden relative`}>
          {/* Background Icon */}
          <div className="absolute -right-4 -bottom-4 text-8xl opacity-10">
            {metric.icon}
          </div>

          {/* Content */}
          <div className="relative z-10">
            <div className="flex items-center justify-between mb-2">
              <h4 className="text-sm font-medium opacity-90">{metric.title}</h4>
              <span className="text-2xl">{metric.icon}</span>
            </div>
            
            <div className="text-3xl font-bold mb-1">
              {metric.value}
            </div>
            
            <div className="text-xs opacity-80">
              {metric.subtitle}
            </div>

            {/* Growth Rate for "Bisnis Baru" */}
            {index === 1 && growthRate !== 0 && (
              <div className="mt-3 pt-3 border-t border-white border-opacity-20">
                <div className="flex items-center space-x-1">
                  <span className="text-xs opacity-90">Growth Rate:</span>
                  <span className={`text-sm font-bold ${growthRate > 0 ? 'text-green-200' : 'text-red-200'}`}>
                    {growthRate > 0 ? '↗' : '↘'} {Math.abs(growthRate).toFixed(1)}%
                  </span>
                </div>
              </div>
            )}
          </div>
        </Card>
      ))}
    </div>
  );
};

export default GrowthMetrics;
