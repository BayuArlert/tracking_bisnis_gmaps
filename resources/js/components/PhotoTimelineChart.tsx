import * as React from "react";

interface Props {
  photoCount: number;
  hasRecentActivity: boolean;
  className?: string;
}

const PhotoTimelineChart: React.FC<Props> = ({
  photoCount,
  hasRecentActivity,
  className = "",
}) => {
  // Simple visualization for photo activity
  const getPhotoActivity = () => {
    if (photoCount === 0) {
      return [];
    }

    // Create simple bars representing photo distribution
    const bars = 8;
    const data = [];
    
    for (let i = 0; i < bars; i++) {
      // Simulate activity distribution (higher towards recent if hasRecentActivity)
      const baseValue = photoCount / bars;
      const recencyBoost = hasRecentActivity && i >= bars / 2 ? 1.5 : 0.8;
      const randomness = Math.random() * 0.4 + 0.8;
      const value = baseValue * recencyBoost * randomness;
      data.push(value);
    }
    
    return data;
  };

  const activityData = getPhotoActivity();
  
  if (activityData.length === 0) {
    return (
      <div className={`text-sm text-gray-500 ${className}`}>
        Tidak ada foto
      </div>
    );
  }

  const maxValue = Math.max(...activityData, 1);
  
  return (
    <div className={`space-y-3 ${className}`}>
      <div className="flex items-end justify-between h-16 gap-1">
        {activityData.map((value, index) => (
          <div
            key={index}
            className={`flex-1 rounded-t transition-all ${
              hasRecentActivity && index >= activityData.length / 2
                ? 'bg-green-500 hover:bg-green-600'
                : 'bg-purple-500 hover:bg-purple-600'
            }`}
            style={{
              height: `${(value / maxValue) * 100}%`,
              minHeight: '4px'
            }}
            title={`~${Math.round(value)} photos`}
          />
        ))}
      </div>
      
      <div className="flex justify-between text-xs text-gray-600">
        <span>Older</span>
        <div className="flex items-center gap-1">
          <span className="font-semibold">{photoCount} photos</span>
          {hasRecentActivity && (
            <span className="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-green-100 text-green-800">
              ✓ Recent
            </span>
          )}
        </div>
        <span>Recent</span>
      </div>
    </div>
  );
};

export default PhotoTimelineChart;

