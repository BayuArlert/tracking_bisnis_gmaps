import * as React from "react";
import { Card } from "./ui/card";
import { Button } from "./ui/button";
import { Badge } from "./ui/badge";
import { MapPin, TrendingUp } from "lucide-react";

interface ClusterInfo {
  count: number;
  category: string;
  center_area: string;
  center_kecamatan?: string;
  center_kabupaten?: string;
  period: string;
  radius: number;
  lat: number;
  lng: number;
  businesses: any[];
}

interface Props {
  cluster: ClusterInfo;
  onViewDetails?: () => void;
  className?: string;
}

const ClusterInfoWindow: React.FC<Props> = ({
  cluster,
  onViewDetails,
  className = "",
}) => {
  // Count businesses by category
  const categoryBreakdown = cluster.businesses.reduce((acc: { [key: string]: number }, business: any) => {
    const cat = business.category || 'Unknown';
    acc[cat] = (acc[cat] || 0) + 1;
    return acc;
  }, {} as { [key: string]: number });

  const topCategories = Object.entries(categoryBreakdown)
    .sort((a, b) => (b[1] as number) - (a[1] as number))
    .slice(0, 3);

  return (
    <Card className={`p-4 min-w-[280px] max-w-[320px] shadow-xl border-2 border-blue-200 ${className}`}>
      {/* Header */}
      <div className="mb-3">
        <h3 className="font-bold text-lg text-gray-900 mb-1 flex items-center gap-2">
          <TrendingUp className="h-5 w-5 text-blue-600" />
          {cluster.count} Bisnis Baru
        </h3>
        <p className="text-sm text-gray-600 flex items-center gap-1">
          <MapPin className="h-4 w-4" />
          Pusat: {cluster.center_kecamatan || cluster.center_area}
        </p>
        {cluster.center_kabupaten && (
          <p className="text-xs text-gray-500 ml-5">
            {cluster.center_kabupaten}
          </p>
        )}
      </div>

      {/* Cluster Metadata */}
      <div className="space-y-2 mb-4">
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-600">Periode:</span>
          <span className="font-medium text-gray-900">{cluster.period}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-600">Coverage:</span>
          <span className="font-medium text-gray-900">~{Math.round(cluster.radius)}m radius</span>
        </div>
      </div>

      {/* Category Breakdown */}
      {topCategories.length > 0 && (
        <div className="mb-4">
          <p className="text-xs font-medium text-gray-700 mb-2">Top Categories:</p>
          <div className="flex flex-wrap gap-1.5">
            {topCategories.map(([cat, count]: [string, number]) => (
              <Badge key={cat} variant="secondary" className="text-xs">
                {cat}: {count}
              </Badge>
            ))}
          </div>
        </div>
      )}

      {/* Action Button */}
      {onViewDetails && (
        <Button 
          className="w-full"
          onClick={onViewDetails}
        >
          Lihat Detail ({cluster.count} tempat)
        </Button>
      )}
    </Card>
  );
};

export default ClusterInfoWindow;

