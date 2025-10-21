import React from 'react';
import { Card } from "../components/ui/card";
import { cleanAreaName } from '../lib/areaUtils';

interface HotZone {
  area: string;
  total_businesses: number;
  new_businesses: number;
  avg_confidence: number;
  avg_lat: number;
  avg_lng: number;
}

interface HotZonesListProps {
  hotZones: HotZone[];
  title?: string;
}

const HotZonesList: React.FC<HotZonesListProps> = ({ hotZones, title = "Top 5 Kecamatan Paling Panas" }) => {
  return (
    <Card className="p-6 rounded-2xl shadow-lg">
      <h3 className="text-xl font-bold text-gray-900 mb-6">{title}</h3>
      
      {hotZones.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          <svg className="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
          <p className="text-sm">Belum ada data hot zones</p>
        </div>
      ) : (
        <div className="space-y-3">
          {hotZones.map((zone, index) => (
            <div
              key={zone.area}
              className="flex items-center space-x-4 p-4 bg-gradient-to-r from-white to-gray-50 rounded-xl border border-gray-200 hover:shadow-md transition-shadow"
            >
              {/* Rank Badge */}
              <div className="flex-shrink-0">
                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-bold ${
                  index === 0 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' :
                  index === 1 ? 'bg-gradient-to-br from-gray-300 to-gray-500' :
                  index === 2 ? 'bg-gradient-to-br from-orange-400 to-orange-600' :
                  'bg-gradient-to-br from-blue-400 to-blue-600'
                }`}>
                  {index + 1}
                </div>
              </div>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <h4 className="font-semibold text-gray-900 text-lg truncate">{cleanAreaName(zone.area)}</h4>
                <div className="flex items-center space-x-4 mt-1">
                  <span className="text-sm text-gray-600">
                    🔥 <strong className="text-blue-600">{zone.new_businesses}</strong> bisnis baru
                  </span>
                  <span className="text-sm text-gray-600">
                    📊 <strong className="text-green-600">{zone.total_businesses}</strong> total
                  </span>
                </div>
                <div className="mt-1">
                  <div className="flex items-center space-x-2">
                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                      <div 
                        className="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full transition-all duration-300"
                        style={{ width: `${Math.min(100, zone.avg_confidence)}%` }}
                      />
                    </div>
                    <span className="text-xs font-medium text-gray-600">
                      {Math.round(zone.avg_confidence)} score
                    </span>
                  </div>
                </div>
              </div>

              {/* Growth Badge */}
              <div className="flex-shrink-0">
                <div className="text-center bg-green-100 px-3 py-2 rounded-lg">
                  <div className="text-xs text-green-600 font-medium">Growth</div>
                  <div className="text-lg font-bold text-green-700">
                    {zone.total_businesses > 0 
                      ? Math.round((zone.new_businesses / zone.total_businesses) * 100) 
                      : 0}%
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
};

export default HotZonesList;
