import React from 'react';
import { Card } from './ui/card';

interface CategoryData {
  category: string;
  total: number;
  new_count: number;
  avg_confidence: number;
}

interface CategoryBreakdownProps {
  categories: CategoryData[];
  title?: string;
}

const CategoryBreakdown: React.FC<CategoryBreakdownProps> = ({ 
  categories, 
  title = "Breakdown per Kategori" 
}) => {
  const maxTotal = Math.max(...categories.map(c => c.total), 1);

  const getCategoryIcon = (category: string): string => {
    const iconMap: { [key: string]: string } = {
      'cafe': '☕',
      'coffee_shop': '☕',
      'restaurant': '🍽️',
      'hotel': '🏨',
      'lodging': '🏠',
      'school': '🏫',
      'university': '🎓',
      'tourist_attraction': '🗿',
      'point_of_interest': '📍',
      'park': '🌳',
      'gym': '💪',
      'spa': '💆',
      'bar': '🍺',
      'night_club': '🎵',
    };
    
    const lowerCategory = category.toLowerCase();
    return iconMap[lowerCategory] || '📍';
  };

  const getCategoryColor = (index: number): string => {
    const colors = [
      'from-blue-400 to-blue-600',
      'from-green-400 to-green-600',
      'from-purple-400 to-purple-600',
      'from-orange-400 to-orange-600',
      'from-pink-400 to-pink-600',
      'from-indigo-400 to-indigo-600',
      'from-red-400 to-red-600',
    ];
    return colors[index % colors.length];
  };

  return (
    <Card className="p-6 rounded-2xl shadow-lg">
      <h3 className="text-xl font-bold text-gray-900 mb-6">{title}</h3>
      
      {categories.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p className="text-sm">Belum ada data kategori</p>
        </div>
      ) : (
        <div className="space-y-4">
          {categories.map((cat, index) => (
            <div key={cat.category} className="space-y-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <span className="text-2xl">{getCategoryIcon(cat.category)}</span>
                  <div>
                    <div className="font-semibold text-gray-900">
                      {cat.category}
                    </div>
                    <div className="text-xs text-gray-600">
                      <span className="text-green-600 font-medium">{cat.new_count} baru</span>
                      {' • '}
                      <span>{cat.total} total</span>
                    </div>
                  </div>
                </div>
                <div className="text-right">
                  <div className="text-lg font-bold text-gray-900">
                    {cat.total}
                  </div>
                  <div className="text-xs text-gray-500">
                    {Math.round(cat.avg_confidence)} score
                  </div>
                </div>
              </div>
              
              {/* Progress Bar */}
              <div className="relative">
                <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                  <div 
                    className={`h-3 bg-gradient-to-r ${getCategoryColor(index)} transition-all duration-500 rounded-full`}
                    style={{ width: `${(cat.total / maxTotal) * 100}%` }}
                  />
                </div>
                
                {/* New Count Overlay */}
                {cat.new_count > 0 && (
                  <div 
                    className="absolute top-0 left-0 h-3 bg-yellow-400 bg-opacity-60 rounded-full"
                    style={{ width: `${(cat.new_count / maxTotal) * 100}%` }}
                  />
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Total Summary */}
      {categories.length > 0 && (
        <div className="mt-6 pt-6 border-t border-gray-200">
          <div className="flex justify-between items-center">
            <div className="text-sm font-semibold text-gray-700">Total</div>
            <div className="flex items-center space-x-4">
              <div className="text-center">
                <div className="text-sm text-gray-600">Baru</div>
                <div className="text-xl font-bold text-green-600">
                  {categories.reduce((sum, c) => sum + c.new_count, 0)}
                </div>
              </div>
              <div className="text-center">
                <div className="text-sm text-gray-600">Total</div>
                <div className="text-xl font-bold text-blue-600">
                  {categories.reduce((sum, c) => sum + c.total, 0)}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </Card>
  );
};

export default CategoryBreakdown;
