import * as React from "react";
import { useEffect, useRef, useState } from "react";
import { loadGoogleMapsAPI, isGoogleMapsLoaded } from "../lib/geoUtils";

/// <reference types="google.maps" />

interface Props {
  lat: number;
  lng: number;
  businessName: string;
  className?: string;
}

const MiniMap: React.FC<Props> = ({ lat, lng, businessName, className = "" }) => {
  const mapRef = useRef<HTMLDivElement>(null);
  const googleMapRef = useRef<any>(null);
  const markerRef = useRef<any>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  useEffect(() => {
    if (!mapRef.current || !lat || !lng) return;

    let isMounted = true;

    const initializeMap = async () => {
      try {
        // Load Google Maps API if not already loaded
        if (!isGoogleMapsLoaded()) {
          await loadGoogleMapsAPI();
        }

        if (!isMounted) return;

        // Wait a bit for API to fully initialize
        await new Promise(resolve => setTimeout(resolve, 100));

        if (!isMounted) return;

        const google = (window as any).google;

        // Verify Google Maps API is fully loaded
        if (!google || !google.maps || !google.maps.Map) {
          throw new Error('Google Maps API not fully initialized');
        }

        if (!mapRef.current || !isMounted) return;

        // Initialize map with mapId for AdvancedMarker support
        const map = new google.maps.Map(mapRef.current, {
          center: { lat, lng },
          zoom: 15,
          mapId: 'MINIMAP_ID', // Required for AdvancedMarkerElement
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: false,
          zoomControl: true,
          gestureHandling: "cooperative",
        });

        googleMapRef.current = map;

        // Wait for map to be ready
        await new Promise(resolve => setTimeout(resolve, 100));

        if (!isMounted) return;

        // Create marker content (for AdvancedMarkerElement)
        const markerContent = document.createElement('div');
        markerContent.innerHTML = `
          <div style="
            background: #ef4444;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            cursor: pointer;
          ">
            📍 ${businessName.substring(0, 20)}${businessName.length > 20 ? '...' : ''}
          </div>
        `;

        // Use AdvancedMarkerElement if available, fallback to Marker
        let marker;
        try {
          if (google.maps.marker && google.maps.marker.AdvancedMarkerElement) {
            marker = new google.maps.marker.AdvancedMarkerElement({
              position: { lat, lng },
              map: map,
              title: businessName,
              content: markerContent,
            });
          } else {
            // Fallback to deprecated Marker
            marker = new google.maps.Marker({
              position: { lat, lng },
              map: map,
              title: businessName,
              animation: google.maps.Animation.DROP,
            });
          }
        } catch (error) {
          console.warn('Error creating AdvancedMarker, using fallback:', error);
          marker = new google.maps.Marker({
            position: { lat, lng },
            map: map,
            title: businessName,
          });
        }

        markerRef.current = marker;

        // Add info window
        const infoWindow = new google.maps.InfoWindow({
          content: `<div style="padding: 8px; max-width: 200px;">
            <strong>${businessName}</strong>
          </div>`,
        });

        // Add click listener (works for both marker types)
        if (marker instanceof google.maps.Marker) {
          marker.addListener("click", () => {
            infoWindow.open(map, marker);
          });
        } else {
          marker.addListener("click", () => {
            infoWindow.open({ map, anchor: marker });
          });
        }

        if (isMounted) {
          setIsLoading(false);
          setHasError(false);
        }
      } catch (error) {
        console.error('Error loading/initializing map:', error);
        if (isMounted) {
          setHasError(true);
          setIsLoading(false);
        }
      }
    };

    initializeMap();

    // Cleanup
    return () => {
      isMounted = false;
      if (markerRef.current) {
        try {
          markerRef.current.setMap(null);
        } catch (e) {
          // Ignore cleanup errors
        }
      }
    };
  }, [lat, lng, businessName]);

  if (!lat || !lng) {
    return (
      <div className={`flex items-center justify-center h-48 bg-gray-100 rounded-lg ${className}`}>
        <p className="text-sm text-gray-500">Koordinat tidak tersedia</p>
      </div>
    );
  }

  if (hasError) {
    return (
      <div className={`flex flex-col items-center justify-center h-48 bg-gray-100 rounded-lg ${className}`}>
        <p className="text-sm text-gray-600 mb-2">⚠️ Map tidak dapat dimuat</p>
        <a
          href={`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-sm text-blue-600 hover:underline"
        >
          Buka di Google Maps
        </a>
      </div>
    );
  }

  return (
    <div className="relative">
      {isLoading && (
        <div className="absolute inset-0 flex items-center justify-center bg-gray-100 rounded-lg z-10">
          <div className="flex flex-col items-center space-y-2">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p className="text-sm text-gray-600">Loading map...</p>
          </div>
        </div>
      )}
      <div
        ref={mapRef}
        className={`w-full h-48 rounded-lg border border-gray-200 ${className}`}
        style={{ minHeight: "200px" }}
      />
    </div>
  );
};

export default MiniMap;

