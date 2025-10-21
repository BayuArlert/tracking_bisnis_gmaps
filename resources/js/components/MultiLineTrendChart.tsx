import React from 'react';
import { Card } from './ui/card';

interface TrendDataPoint {
  period: string;
  [key: string]: string | number; // Dynamic keys for categories/areas
}

interface MultiLineTrendChartProps {
  data: TrendDataPoint[];
  lines: string[]; // Line names (categories or kecamatan)
  title: string;
  type?: 'category' | 'kecamatan';
}

const MultiLineTrendChart: React.FC<MultiLineTrendChartProps> = ({ 
  data, 
  lines,
  title,
  type = 'category'
}) => {
  if (!data || data.length === 0) {
    return (
      <Card className="p-6 rounded-2xl shadow-lg">
        <h3 className="text-lg font-bold text-gray-900 mb-4">{title}</h3>
        <div className="text-center py-8 text-gray-500">
          <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p className="text-sm">Belum ada data trend</p>
        </div>
      </Card>
    );
  }

  // Calculate max value for scaling
  const allValues: number[] = [];
  data.forEach(point => {
    lines.forEach(line => {
      const value = Number(point[line]) || 0;
      allValues.push(value);
    });
  });
  const rawMaxValue = Math.max(...allValues, 1);
  
  // Calculate a "nice" maximum value for better Y-axis scaling
  const calculateNiceMaxValue = (value: number): number => {
    if (value === 0) return 100; // Minimum range for visibility
    
    // Determine the order of magnitude
    const exponent = Math.floor(Math.log10(value));
    const fraction = value / Math.pow(10, exponent);
    
    // Choose nice fractions: 1, 2, 5, 10
    let niceFraction;
    if (fraction <= 1) niceFraction = 1;
    else if (fraction <= 2) niceFraction = 2;
    else if (fraction <= 5) niceFraction = 5;
    else niceFraction = 10;
    
    const niceValue = niceFraction * Math.pow(10, exponent);
    
    // Ensure it's always above the actual max value with some padding
    return niceValue < value ? niceValue * 1.1 : niceValue;
  };
  
  const maxValue = calculateNiceMaxValue(rawMaxValue);

  // Color palette for lines - consistent with Chart.js colors
  const lineColors = [
    '#3B82F6', // blue
    '#10B981', // green
    '#8B5CF6', // purple
    '#F59E0B', // orange
    '#EF4444', // red
    '#EC4899', // pink
    '#6366F1', // indigo
  ];

  const getLineColor = (index: number): string => {
    return lineColors[index % lineColors.length];
  };

  // Ensure consistent color mapping by creating a color map
  const colorMap = new Map<string, string>();
  lines.forEach((line, index) => {
    colorMap.set(line, getLineColor(index));
  });

  return (
    <Card className="p-6 rounded-2xl shadow-lg">
      <h3 className="text-lg font-bold text-gray-900 mb-6">{title}</h3>
      
      {/* Chart */}
      <div className="relative h-64 mb-6">
        <div className="absolute left-0 top-0 bottom-0 w-12 text-xs text-gray-500">
          {[0, 0.25, 0.5, 0.75, 1].map((ratio, i) => {
            const value = maxValue * (1 - ratio);
            // Format number nicely (remove decimals for whole numbers)
            const displayValue = value % 1 === 0 ? Math.round(value) : Math.round(value * 10) / 10;
            
            return (
              <div 
                key={i} 
                className="absolute text-right pr-2"
                style={{ 
                  top: `calc(${ratio * 100}% - 6px)`,
                  height: '12px',
                  display: 'flex',
                  alignItems: 'center'
                }}
              >
                {displayValue}
              </div>
            );
          })}
        </div>
        <div className="ml-12 mr-4">
          <svg width="100%" height="100%" className="overflow-visible">
            {/* Grid lines */}
            {[0, 0.25, 0.5, 0.75, 1].map((ratio, i) => (
              <g key={i}>
                <line
                  x1="0"
                  y1={`${ratio * 100}%`}
                  x2="100%"
                  y2={`${ratio * 100}%`}
                  stroke="#E5E7EB"
                  strokeWidth="1"
                />
              </g>
            ))}

          {/* Lines for each category/kecamatan */}
          {lines.map((line, lineIndex) => {
            // Check if this line has any non-zero data
            const hasData = data.some(point => (Number(point[line]) || 0) > 0);
            
            // Skip rendering if no data
            if (!hasData) return null;
            
            const points = data.map((point, i) => {
              const x = (i / (data.length - 1)) * 100;
              const value = Number(point[line]) || 0;
              // Use full range from 0 to maxValue (0% to 100% from top)
              const y = (1 - (value / maxValue)) * 100;
              return `${x},${y}`;
            }).join(' ');

            return (
              <g key={line}>
                {/* Line path */}
                <polyline
                  points={points}
                  fill="none"
                  stroke={colorMap.get(line) || getLineColor(lineIndex)}
                  strokeWidth="2.5"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="transition-all duration-300 hover:stroke-width-4"
                />
                
                {/* Data points */}
                {data.map((point, i) => {
                  const x = (i / (data.length - 1)) * 100;
                  const value = Number(point[line]) || 0;
                  // Use full range from 0 to maxValue (0% to 100% from top)
                  const y = (1 - (value / maxValue)) * 100;
                  
                  // Only show data points for non-zero values
                  if (value === 0) return null;
                  
                  return (
                    <circle
                      key={i}
                      cx={`${x}%`}
                      cy={`${y}%`}
                      r="4"
                      fill={colorMap.get(line) || getLineColor(lineIndex)}
                      stroke="white"
                      strokeWidth="2"
                      className="hover:r-6 transition-all cursor-pointer"
                    >
                      <title>{`${line}: ${value} (${point.period})`}</title>
                    </circle>
                  );
                })}
              </g>
            );
          })}
        </svg>
          
          {/* X-axis labels */}
          <div 
            className="absolute left-0 right-0 text-xs text-gray-600 px-2"
            style={{ top: 'calc(100% + 8px)' }}
          >
            <div className="flex justify-between">
              {data.map((point, i) => (
                i % Math.ceil(data.length / 6) === 0 && (
                  <span key={i} className="truncate max-w-16">{point.period}</span>
                )
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-3 justify-center">
        {lines.map((line, index) => {
          const total = data.reduce((sum, point) => sum + (Number(point[line]) || 0), 0);
          
          // Only show legend items that have data
          if (total === 0) return null;
          
          return (
            <div key={line} className="flex items-center space-x-2 px-3 py-1.5 bg-gray-50 rounded-lg">
              <div 
                className="w-3 h-3 rounded-full"
                style={{ backgroundColor: colorMap.get(line) || getLineColor(index) }}
              />
              <span className="text-xs font-medium text-gray-700">
                {line}
              </span>
              <span className="text-xs text-gray-500">
                ({total})
              </span>
            </div>
          );
        })}
      </div>
    </Card>
  );
};

export default MultiLineTrendChart;
