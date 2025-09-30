import React, { useEffect, useRef, useState, useCallback } from 'react';

interface Business {
  lat: number;
  lng: number;
  name: string;
  category: string;
  area: string;
  review_count: number;
  rating: number;
}

interface HeatmapProps {
  businesses: Business[];
  center?: { lat: number; lng: number };
  zoom?: number;
  height?: string;
}

declare global {
  interface Window {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    google: any;
    initGoogleMaps: () => void;
  }
}

const GoogleMapsHeatmap: React.FC<HeatmapProps> = ({ 
  businesses, 
  center = { lat: -8.6500, lng: 115.2167 }, // Bali center
  zoom = 11,
  height = "400px"
}) => {
  const mapRef = useRef<HTMLDivElement>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const mapInstance = useRef<any>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const heatmapInstance = useRef<any>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const markersRef = useRef<any[]>([]);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    // Load Google Maps API
    const loadGoogleMaps = () => {
      if (window.google && window.google.maps && window.google.maps.MapTypeId) {
        setIsLoaded(true);
        return;
      }

      const script = document.createElement('script');
      const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
      if (!apiKey) {
        console.error('Google Maps API key not configured. Please set VITE_GOOGLE_MAPS_API_KEY in your .env file');
        return;
      }
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=marker,geometry&loading=async&callback=initGoogleMaps`;
      script.async = true;
      script.defer = true;
      script.onerror = () => {
        console.error('Failed to load Google Maps API');
        setIsLoaded(false);
      };
      
      // Set global callback
      (window as unknown as { initGoogleMaps: () => void }).initGoogleMaps = () => {
        if (window.google && window.google.maps && window.google.maps.MapTypeId) {
          setIsLoaded(true);
        } else {
          console.error('Google Maps API not fully loaded');
          setIsLoaded(false);
        }
      };
      
      document.head.appendChild(script);
    };

    loadGoogleMaps();
  }, []);

  const initializeMap = useCallback(() => {
    if (!mapRef.current || !window.google || !window.google.maps || !window.google.maps.MapTypeId) {
      console.error('Google Maps API not fully loaded');
      return;
    }

    try {
      // Initialize map - try with mapId first, fallback to regular map with styles
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const mapOptions: any = {
        center: center,
        zoom: zoom,
        mapTypeId: window.google.maps.MapTypeId?.ROADMAP || 'roadmap'
      };

      // Try to use mapId for AdvancedMarkerElement support
      if (window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement) {
        mapOptions.mapId = 'DEMO_MAP_ID'; // Demo Map ID - works for development
        // Note: When using mapId, styles are controlled via Google Cloud Console
      } else {
        // Fallback to regular map with custom styles
        mapOptions.styles = [
          {
            featureType: "poi",
            elementType: "labels",
            stylers: [{ visibility: "off" }]
          }
        ];
      }

      mapInstance.current = new window.google.maps.Map(mapRef.current, mapOptions);

    // Clear existing markers
    markersRef.current.forEach(marker => marker.setMap(null));
    markersRef.current = [];

    // Create markers for each business using AdvancedMarkerElement
    businesses.forEach((business) => {
      // Create marker content element
      const markerContent = document.createElement('div');
      markerContent.style.cssText = `
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: ${getMarkerColor(business.category)};
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.2s ease;
      `;
      markerContent.textContent = getMarkerIcon(business.category);

      // Add hover effect
      markerContent.addEventListener('mouseenter', () => {
        markerContent.style.transform = 'scale(1.1)';
      });
      markerContent.addEventListener('mouseleave', () => {
        markerContent.style.transform = 'scale(1)';
      });

      // Try AdvancedMarkerElement first, fallback to regular Marker
      let marker;
      if (window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement) {
        marker = new window.google.maps.marker.AdvancedMarkerElement({
          position: { lat: business.lat, lng: business.lng },
          map: mapInstance.current,
          title: business.name,
          content: markerContent
        });
      } else {
        // Fallback to regular Marker
        marker = new window.google.maps.Marker({
          position: { lat: business.lat, lng: business.lng },
          map: mapInstance.current,
          title: business.name,
          icon: {
            path: window.google.maps.SymbolPath.CIRCLE,
            scale: 15,
            fillColor: getMarkerColor(business.category),
            fillOpacity: 0.8,
            strokeColor: '#ffffff',
            strokeWeight: 2
          },
          label: {
            text: getMarkerIcon(business.category),
            color: '#ffffff',
            fontSize: '12px',
            fontWeight: 'bold'
          }
        });
      }

      // Create info window
      const infoWindow = new window.google.maps.InfoWindow({
        content: `
          <div style="padding: 10px; max-width: 250px;">
            <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold;">${business.name}</h3>
            <p style="margin: 0 0 4px 0; color: #666; font-size: 14px;">${business.category}</p>
            <p style="margin: 0 0 4px 0; color: #666; font-size: 14px;">${business.area}</p>
            <div style="display: flex; align-items: center; margin: 4px 0;">
              <span style="color: #fbbf24; margin-right: 4px;">⭐</span>
              <span style="font-weight: bold;">${business.rating || 'N/A'}</span>
              <span style="margin-left: 8px; color: #666;">(${business.review_count} reviews)</span>
            </div>
          </div>
        `
      });

      marker.addListener('click', () => {
        infoWindow.open(mapInstance.current, marker);
      });

      markersRef.current.push(marker);
    });

    // Create density visualization using circles instead of deprecated heatmap
    const densityCircles = businesses.map(business => {
      const reviewCount = business.review_count || 0;
      const radius = Math.max(1000, Math.min(10000, reviewCount * 20)); // Scale radius based on review count
      const opacity = Math.min(0.6, Math.max(0.1, reviewCount / 100)); // Scale opacity based on review count
      
      const circle = new window.google.maps.Circle({
        strokeColor: '#FF0000',
        strokeOpacity: 0.8,
        strokeWeight: 2,
        fillColor: '#FF0000',
        fillOpacity: opacity,
        map: mapInstance.current,
        center: { lat: business.lat, lng: business.lng },
        radius: radius
      });

      return circle;
    });

    // Store circles for toggling
    heatmapInstance.current = {
      circles: densityCircles,
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      setMap: (map: any) => {
        densityCircles.forEach(circle => circle.setMap(map));
      },
      getMap: () => densityCircles[0]?.getMap()
    };
    } catch (error) {
      console.error('Error initializing map:', error);
    }
  }, [center, zoom, businesses]);

  useEffect(() => {
    if (isLoaded && mapRef.current && businesses.length > 0) {
      initializeMap();
    }
  }, [isLoaded, businesses, initializeMap]);

  const getMarkerIcon = (category: string): string => {
    const iconMap: { [key: string]: string } = {
      'restaurant': '🍽️',
      'hotel': '🏨',
      'cafe': '☕',
      'beauty_salon': '💄',
      'establishment': '🏢',
      'atm': '🏧',
      'bank': '🏦',
      'doctor': '🏥',
      'convenience_store': '🏪',
      'car_repair': '🔧',
      'campground': '⛺',
      'bar': '🍺',
      'locality': '📍',
      'neighborhood': '🏘️',
    };
    
    return iconMap[category] || '📍';
  };

  const getMarkerColor = (category: string): string => {
    const colorMap: { [key: string]: string } = {
      'restaurant': '#FF6B6B',
      'hotel': '#4ECDC4',
      'cafe': '#45B7D1',
      'beauty_salon': '#96CEB4',
      'establishment': '#FFEAA7',
      'atm': '#DDA0DD',
      'bank': '#98D8C8',
      'doctor': '#F7DC6F',
      'convenience_store': '#BB8FCE',
      'car_repair': '#85C1E9',
      'campground': '#F8C471',
      'bar': '#82E0AA',
      'locality': '#F1948A',
      'neighborhood': '#85C1E9',
    };
    
    return colorMap[category] || '#FFD93D';
  };

  const toggleHeatmap = () => {
    if (heatmapInstance.current) {
      const isVisible = heatmapInstance.current.getMap() !== null;
      heatmapInstance.current.setMap(isVisible ? null : mapInstance.current);
    }
  };

  const toggleMarkers = () => {
    markersRef.current.forEach(marker => {
      // Handle both AdvancedMarkerElement and regular Marker
      if (marker && typeof marker.getMap === 'function') {
        // Regular Marker - has getMap() and setMap() methods
        const isVisible = marker.getMap() !== null;
        marker.setMap(isVisible ? null : mapInstance.current);
      } else if (marker && typeof marker.map !== 'undefined') {
        // AdvancedMarkerElement - has map property
        const isVisible = marker.map !== null;
        marker.map = isVisible ? null : mapInstance.current;
      } else {
        // Fallback for other marker types
        console.warn('Unknown marker type:', marker);
      }
    });
  };

  if (!isLoaded) {
    return (
      <div 
        style={{ height }} 
        className="flex items-center justify-center bg-gray-100 rounded-lg"
      >
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
          <p className="text-gray-600">Loading Google Maps...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="relative">
      <div 
        ref={mapRef} 
        style={{ height }} 
        className="rounded-lg shadow-lg"
      />
      
      {/* Map Controls */}
      <div className="absolute top-4 right-4 space-y-2">
        <button
          onClick={toggleHeatmap}
          className="bg-white hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg shadow-md text-sm font-medium transition-colors"
        >
          🔥 Toggle Density Circles
        </button>
        <button
          onClick={toggleMarkers}
          className="bg-white hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg shadow-md text-sm font-medium transition-colors"
        >
          📍 Toggle Markers
        </button>
      </div>

      {/* Legend */}
      <div className="absolute bottom-4 left-4 bg-white rounded-lg shadow-md p-3 max-w-xs">
        <h4 className="font-semibold text-gray-900 mb-2">Legend</h4>
        <div className="space-y-1 text-sm">
          <div className="flex items-center space-x-2">
            <div className="w-3 h-3 bg-red-500 rounded-full"></div>
            <span>Restaurant</span>
          </div>
          <div className="flex items-center space-x-2">
            <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
            <span>Hotel</span>
          </div>
          <div className="flex items-center space-x-2">
            <div className="w-3 h-3 bg-green-500 rounded-full"></div>
            <span>Cafe</span>
          </div>
          <div className="flex items-center space-x-2">
            <div className="w-3 h-3 bg-purple-500 rounded-full"></div>
            <span>Beauty Salon</span>
          </div>
          <div className="flex items-center space-x-2">
            <div className="w-3 h-3 bg-orange-500 rounded-full"></div>
            <span>Other</span>
          </div>
        </div>
        <div className="mt-2 pt-2 border-t border-gray-200">
          <p className="text-xs text-gray-600">
            Heatmap intensity based on review count
          </p>
        </div>
      </div>
    </div>
  );
};

export default GoogleMapsHeatmap;
