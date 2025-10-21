import * as React from "react";
import { useState, useEffect, useContext } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";
import { TrendingUp, MapPin, Flame } from "lucide-react";

interface HotZone {
  area: string;
  kabupaten: string;
  kecamatan: string;
  count: number;
  growth_percentage: number;
  category_breakdown: { [key: string]: number };
}

interface Props {
  period?: number; // days
  category?: string;
  limit?: number;
  onZoneClick?: (zone: HotZone) => void;
  className?: string;
}

const Top5HotZones: React.FC<Props> = ({
  period = 90,
  category = 'all',
  limit = 5,
  onZoneClick,
  className = "",
}) => {
  const { API } = useContext(AuthContext);
  const [hotZones, setHotZones] = useState<HotZone[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchHotZones();
  }, [period, category]);

  const fetchHotZones = async () => {
    setLoading(true);
    try {
      const response = await axios.get(`${API}/analytics/hot-zones`, {
        params: { period, category, limit }
      });
      setHotZones(response.data.data || []);
    } catch (error) {
      console.error("Failed to fetch hot zones:", error);
    } finally {
      setLoading(false);
    }
  };

  const getRankColor = (rank: number) => {
    switch (rank) {
      case 1: return 'text-yellow-600 bg-yellow-50 border-yellow-200';
      case 2: return 'text-gray-600 bg-gray-50 border-gray-200';
      case 3: return 'text-orange-600 bg-orange-50 border-orange-200';
      default: return 'text-blue-600 bg-blue-50 border-blue-200';
    }
  };

  const getFlameCount = (rank: number) => {
    if (rank === 1) return 3;
    if (rank === 2) return 2;
    return 1;
  };

  if (loading) {
    return (
      <Card className={`p-6 ${className}`}>
        <div className="animate-pulse space-y-4">
          <div className="h-6 bg-gray-200 rounded w-1/2"></div>
          {[1, 2, 3, 4, 5].map(i => (
            <div key={i} className="h-16 bg-gray-100 rounded"></div>
          ))}
        </div>
      </Card>
    );
  }

  return (
    <Card className={`p-6 ${className}`}>
      {/* Header */}
      <div className="mb-6">
        <h3 className="text-lg font-bold text-gray-900 mb-1 flex items-center gap-2">
          <Flame className="h-5 w-5 text-orange-500" />
          Top {limit} Kecamatan Paling Panas
        </h3>
        <p className="text-sm text-gray-600">
          {period} hari terakhir 
          {category !== 'all' && ` - ${category}`}
        </p>
      </div>

      {/* Hot Zones List */}
      {hotZones.length > 0 ? (
        <div className="space-y-3">
          {hotZones.map((zone, index) => {
            const rank = index + 1;
            const flameCount = getFlameCount(rank);
            
            return (
              <div
                key={zone.area}
                className={`p-4 rounded-lg border-2 ${getRankColor(rank)} transition-all hover:shadow-md ${
                  onZoneClick ? 'cursor-pointer' : ''
                }`}
                onClick={() => onZoneClick?.(zone)}
              >
                <div className="flex items-center gap-3">
                  {/* Rank */}
                  <div className="flex-shrink-0">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center text-xl font-bold border-2 ${getRankColor(rank)}`}>
                      #{rank}
                    </div>
                  </div>

                  {/* Zone Info */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <h4 className="font-semibold text-gray-900 truncate">
                        {zone.kecamatan}
                      </h4>
                      {Array.from({ length: flameCount }).map((_, i) => (
                        <Flame
                          key={i}
                          className="h-4 w-4 text-orange-500 fill-orange-500"
                        />
                      ))}
                    </div>
                    <p className="text-xs text-gray-600 mb-2">
                      <MapPin className="h-3 w-3 inline mr-1" />
                      {zone.kabupaten}
                    </p>
                    <div className="flex items-center gap-2 text-xs">
                      <span className="font-medium text-gray-700">
                        {zone.count} bisnis baru
                      </span>
                      {Object.entries(zone.category_breakdown).slice(0, 3).map(([cat, count]) => (
                        <Badge key={cat} variant="secondary" className="text-xs">
                          {cat}: {count}
                        </Badge>
                      ))}
                    </div>
                  </div>

                  {/* Growth */}
                  <div className="flex-shrink-0 text-right">
                    <div className="flex items-center gap-1 text-lg font-bold text-green-600">
                      <TrendingUp className="h-5 w-5" />
                      +{zone.growth_percentage}%
                    </div>
                    <p className="text-xs text-gray-500">vs prev period</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      ) : (
        <div className="text-center py-8 text-gray-500">
          <Flame className="h-12 w-12 mx-auto mb-2 text-gray-300" />
          <p>Tidak ada data hot zones</p>
        </div>
      )}
    </Card>
  );
};

export default Top5HotZones;

