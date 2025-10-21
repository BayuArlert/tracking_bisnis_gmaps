import React, { useEffect, useRef, useState, useCallback } from 'react';
import { MarkerClusterer } from '@googlemaps/markerclusterer';
import { calculateConvexHull, calculateCentroid, calculateBoundingCircle, formatClusterSummary } from '../lib/convexHull';
import { cleanAreaName } from '../lib/areaUtils';

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
  const markersRef = useRef<any[]>([]);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const markerClustererRef = useRef<any>(null);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const convexHullPolygonRef = useRef<any>(null);
  const [isLoaded, setIsLoaded] = useState(false);
  const [showClusters, setShowClusters] = useState(true);
  const [showConvexHull, setShowConvexHull] = useState(false);

  useEffect(() => {
    // Load Google Maps API with safe DOM access
    const loadGoogleMaps = () => {
      try {
        if (window.google && window.google.maps && (window.google.maps as any).MapTypeId) {
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
        
        // Set global callback with error handling
        (window as unknown as { initGoogleMaps: () => void }).initGoogleMaps = () => {
          try {
            if (window.google && window.google.maps && (window.google.maps as any).MapTypeId) {
              setIsLoaded(true);
            } else {
              console.error('Google Maps API not fully loaded');
              setIsLoaded(false);
            }
          } catch (error) {
            console.error('Error in Google Maps callback:', error);
            setIsLoaded(false);
          }
        };

        // Safe DOM access
        const head = document.head;
        if (head && head.appendChild) {
          head.appendChild(script);
        }
      } catch (error) {
        console.error('Error loading Google Maps API:', error);
        setIsLoaded(false);
      }
    };

    loadGoogleMaps();
  }, []);

  const initializeMap = useCallback(() => {
    if (!mapRef.current || !window.google || !window.google.maps || !(window.google.maps as any).MapTypeId) {
      console.error('Google Maps API not fully loaded');
      return;
    }

    try {
      // Initialize map with mapId for AdvancedMarkerElement support
      // Note: When using mapId, styles should be controlled via Cloud Console
      // not through the styles property to avoid warnings
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const mapOptions: any = {
        center: center,
        zoom: zoom,
        mapTypeId: (window.google.maps as any).MapTypeId?.ROADMAP || 'roadmap',
        mapId: 'DEMO_MAP_ID', // Required for AdvancedMarkerElement
        // Removed styles property - use Cloud Console for styling when mapId is present
        // For styling without mapId, see: https://developers.google.com/maps/documentation/javascript/styling
      };

      mapInstance.current = new window.google.maps.Map(mapRef.current, mapOptions);

    // Clear existing markers and clusterer
    if (markerClustererRef.current) {
      markerClustererRef.current.clearMarkers();
      markerClustererRef.current = null;
    }
    
    markersRef.current.forEach(marker => marker.setMap(null));
    markersRef.current = [];
    
    // Clear convex hull polygon
    if (convexHullPolygonRef.current) {
      convexHullPolygonRef.current.setMap(null);
      convexHullPolygonRef.current = null;
    }

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

      // Use AdvancedMarkerElement as recommended by Google Maps API
      let marker;
      try {
        // Check if AdvancedMarkerElement is available
        if (window.google?.maps?.marker?.AdvancedMarkerElement) {
          marker = new (window.google.maps as any).marker.AdvancedMarkerElement({
            position: { lat: business.lat, lng: business.lng },
            map: mapInstance.current,
            title: business.name,
            content: markerContent
          });
        } else {
          // Fallback to regular Marker with warning
          console.warn('AdvancedMarkerElement not available, using deprecated Marker. Please update your Google Maps API key with Advanced Markers enabled.');
          marker = new window.google.maps.Marker({
            position: { lat: business.lat, lng: business.lng },
            map: mapInstance.current,
            title: business.name,
            icon: {
              path: (window.google.maps as any).SymbolPath.CIRCLE,
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
          } as any);
        }
      } catch (error) {
        console.error('Error creating marker:', error);
        // Final fallback
        marker = new window.google.maps.Marker({
          position: { lat: business.lat, lng: business.lng },
          map: mapInstance.current,
          title: business.name
        } as any);
      }

      // Create info window
      const infoWindow = new window.google.maps.InfoWindow({
        content: `
          <div style="padding: 10px; max-width: 250px;">
            <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold;">${business.name}</h3>
            <p style="margin: 0 0 4px 0; color: #666; font-size: 14px;">${business.category}</p>
            <p style="margin: 0 0 4px 0; color: #666; font-size: 14px;">${cleanAreaName(business.area)}</p>
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

    // Initialize marker clusterer
    if (showClusters && markersRef.current.length > 0) {
      markerClustererRef.current = new MarkerClusterer({
        map: mapInstance.current,
        markers: markersRef.current,
        // Use default algorithm (no need for SuperClusterAlgorithm)
        onClusterClick: (event: any, cluster: any, map: any) => {
          // Get markers in this cluster
          const clusterMarkers = cluster.markers;
          const clusterBusinesses = businesses.filter((b, index) => 
            clusterMarkers.includes(markersRef.current[index])
          );
          
          // Calculate and show convex hull
          showClusterPreview(clusterBusinesses, map);
        },
      });
    }

    } catch (error) {
      console.error('Error initializing map:', error);
    }
  }, [center, zoom, businesses, showClusters]);

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

  const toggleClusters = () => {
    setShowClusters(!showClusters);
  };

  const toggleConvexHull = () => {
    setShowConvexHull(!showConvexHull);
    if (!showConvexHull && businesses.length > 0) {
      // Show convex hull for all businesses
      showClusterPreview(businesses, mapInstance.current);
    } else if (convexHullPolygonRef.current) {
      convexHullPolygonRef.current.setMap(null);
      convexHullPolygonRef.current = null;
    }
  };

  const showClusterPreview = (clusterBusinesses: Business[], map: any) => {
    if (clusterBusinesses.length < 3) {
      return; // Need at least 3 points for convex hull
    }

    // Clear previous convex hull
    if (convexHullPolygonRef.current) {
      convexHullPolygonRef.current.setMap(null);
    }

    // Calculate convex hull
    const points = clusterBusinesses.map(b => ({ lat: b.lat, lng: b.lng }));
    const hull = calculateConvexHull(points);
    const centroid = calculateCentroid(points);
    const boundingCircle = calculateBoundingCircle(points);

    // Draw convex hull polygon
    const polygon = new (window.google.maps as any).Polygon({
      paths: hull,
      strokeColor: '#3B82F6',
      strokeOpacity: 0.8,
      strokeWeight: 2,
      fillColor: '#3B82F6',
      fillOpacity: 0.2,
      map: map,
    });

    convexHullPolygonRef.current = polygon;

    // Add center marker using AdvancedMarkerElement if available
    let centerMarker;
    try {
      if (window.google?.maps?.marker?.AdvancedMarkerElement) {
        // Create custom marker content for center
        const centerMarkerContent = document.createElement('div');
        centerMarkerContent.innerHTML = '📍';
        centerMarkerContent.style.cssText = `
          background-color: #EF4444;
          border: 2px solid #ffffff;
          border-radius: 50%;
          width: 20px;
          height: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 12px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        `;
        
        centerMarker = new (window.google.maps as any).marker.AdvancedMarkerElement({
          position: centroid,
          map: map,
          content: centerMarkerContent,
        });
      } else {
        // Fallback to regular Marker
        centerMarker = new window.google.maps.Marker({
          position: centroid,
          map: map,
          icon: {
            path: (window.google.maps as any).SymbolPath.CIRCLE,
            scale: 8,
            fillColor: '#EF4444',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 2
          },
        } as any);
      }
    } catch (error) {
      console.error('Error creating center marker:', error);
      // Final fallback
      centerMarker = new window.google.maps.Marker({
        position: centroid,
        map: map,
      } as any);
    }

    // Create summary info window
    const category = clusterBusinesses[0]?.category || 'businesses';
    const summary = formatClusterSummary(
      clusterBusinesses.length,
      category,
      clusterBusinesses[0]?.area || '',
      boundingCircle.radius
    );

    const summaryWindow = new window.google.maps.InfoWindow({
      content: `
        <div style="padding: 12px; max-width: 300px;">
          <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: bold; color: #1F2937;">
            📍 Preview Area
          </h3>
          <p style="margin: 0; color: #4B5563; font-size: 14px;">
            ${summary}
          </p>
          <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #E5E7EB;">
            <p style="margin: 0; font-size: 12px; color: #6B7280;">
              Jumlah bisnis: <strong>${clusterBusinesses.length}</strong><br>
              Area coverage: <strong>${(boundingCircle.radius / 1000).toFixed(2)} km radius</strong>
            </p>
          </div>
        </div>
      `,
      position: centroid,
    });

    summaryWindow.open(map);

    // Store for cleanup
    markersRef.current.push(centerMarker);
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
          onClick={toggleClusters}
          className={`w-full px-3 py-2 rounded-lg shadow-md text-sm font-medium transition-colors ${
            showClusters 
              ? 'bg-blue-500 hover:bg-blue-600 text-white' 
              : 'bg-white hover:bg-gray-50 text-gray-700'
          }`}
        >
          🔵 {showClusters ? 'Clusters ON' : 'Clusters OFF'}
        </button>
        <button
          onClick={toggleConvexHull}
          className={`w-full px-3 py-2 rounded-lg shadow-md text-sm font-medium transition-colors ${
            showConvexHull 
              ? 'bg-purple-500 hover:bg-purple-600 text-white' 
              : 'bg-white hover:bg-gray-50 text-gray-700'
          }`}
        >
          📐 {showConvexHull ? 'Preview Area ON' : 'Preview Area OFF'}
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
