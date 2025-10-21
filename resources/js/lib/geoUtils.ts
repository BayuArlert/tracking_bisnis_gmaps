/**
 * Geographic Utilities for Business Growth Tracker
 */

// Global flag to track if Google Maps is being loaded
let isLoadingGoogleMaps = false;
let googleMapsLoadedCallbacks: Array<() => void> = [];

/**
 * Load Google Maps API script
 */
export function loadGoogleMapsAPI(): Promise<void> {
  return new Promise((resolve, reject) => {
    // Already loaded and ready
    if (window.google && (window as any).google.maps && (window as any).google.maps.Map) {
      resolve();
      return;
    }

    // Currently loading - add callback
    if (isLoadingGoogleMaps) {
      googleMapsLoadedCallbacks.push(() => resolve());
      return;
    }

    // Start loading
    isLoadingGoogleMaps = true;

    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
    if (!apiKey) {
      console.error('Google Maps API key not configured. Please set VITE_GOOGLE_MAPS_API_KEY in your .env file');
      isLoadingGoogleMaps = false;
      reject(new Error('Google Maps API key not configured'));
      return;
    }

    // Create callback function that Google will call when ready
    const callbackName = 'initGoogleMapsCallback_' + Date.now();
    (window as any)[callbackName] = () => {
      // Wait a tick to ensure everything is initialized
      setTimeout(() => {
        isLoadingGoogleMaps = false;
        resolve();
        // Call all pending callbacks
        googleMapsLoadedCallbacks.forEach(cb => cb());
        googleMapsLoadedCallbacks = [];
        // Cleanup callback
        delete (window as any)[callbackName];
      }, 50);
    };

    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=marker,geometry&loading=async&callback=${callbackName}`;
    script.async = true;
    script.defer = true;
    
    script.onerror = () => {
      isLoadingGoogleMaps = false;
      const error = new Error('Failed to load Google Maps API');
      reject(error);
      console.error('Failed to load Google Maps API');
      delete (window as any)[callbackName];
    };

    document.head.appendChild(script);
  });
}

/**
 * Check if Google Maps API is loaded
 */
export function isGoogleMapsLoaded(): boolean {
  return !!(window.google && (window as any).google.maps && (window as any).google.maps.Map);
}

export interface LatLng {
  lat: number;
  lng: number;
}

export interface BoundingBox {
  north: number;
  south: number;
  east: number;
  west: number;
}

/**
 * Calculate bounding box for a set of points
 */
export function calculateBoundingBox(points: LatLng[]): BoundingBox | null {
  if (points.length === 0) {
    return null;
  }

  let north = points[0].lat;
  let south = points[0].lat;
  let east = points[0].lng;
  let west = points[0].lng;

  for (const point of points) {
    if (point.lat > north) north = point.lat;
    if (point.lat < south) south = point.lat;
    if (point.lng > east) east = point.lng;
    if (point.lng < west) west = point.lng;
  }

  return { north, south, east, west };
}

/**
 * Get center of bounding box
 */
export function getBoundingBoxCenter(bbox: BoundingBox): LatLng {
  return {
    lat: (bbox.north + bbox.south) / 2,
    lng: (bbox.east + bbox.west) / 2,
  };
}

/**
 * Calculate appropriate zoom level for bounding box
 */
export function getZoomLevelForBounds(bbox: BoundingBox): number {
  const latDiff = bbox.north - bbox.south;
  const lngDiff = bbox.east - bbox.west;
  const maxDiff = Math.max(latDiff, lngDiff);

  // Approximate zoom levels
  if (maxDiff > 10) return 8;
  if (maxDiff > 5) return 9;
  if (maxDiff > 2) return 10;
  if (maxDiff > 1) return 11;
  if (maxDiff > 0.5) return 12;
  if (maxDiff > 0.25) return 13;
  if (maxDiff > 0.125) return 14;
  return 15;
}

/**
 * Check if point is inside polygon
 */
export function isPointInPolygon(point: LatLng, polygon: LatLng[]): boolean {
  let inside = false;
  
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    const xi = polygon[i].lng;
    const yi = polygon[i].lat;
    const xj = polygon[j].lng;
    const yj = polygon[j].lat;
    
    const intersect = ((yi > point.lat) !== (yj > point.lat)) &&
      (point.lng < (xj - xi) * (point.lat - yi) / (yj - yi) + xi);
    
    if (intersect) inside = !inside;
  }
  
  return inside;
}

/**
 * Group nearby points into clusters
 */
export function clusterPoints(points: LatLng[], maxDistance: number = 1000): LatLng[][] {
  const clusters: LatLng[][] = [];
  const visited = new Set<number>();

  for (let i = 0; i < points.length; i++) {
    if (visited.has(i)) continue;

    const cluster: LatLng[] = [points[i]];
    visited.add(i);

    for (let j = i + 1; j < points.length; j++) {
      if (visited.has(j)) continue;

      const distance = calculateDistance(points[i], points[j]);
      if (distance <= maxDistance) {
        cluster.push(points[j]);
        visited.add(j);
      }
    }

    clusters.push(cluster);
  }

  return clusters;
}

/**
 * Calculate distance between two points in meters
 */
function calculateDistance(point1: LatLng, point2: LatLng): number {
  const R = 6371000; // Earth radius in meters
  const dLat = toRadians(point2.lat - point1.lat);
  const dLng = toRadians(point2.lng - point1.lng);

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRadians(point1.lat)) * Math.cos(toRadians(point2.lat)) *
    Math.sin(dLng / 2) * Math.sin(dLng / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

  return R * c;
}

/**
 * Convert degrees to radians
 */
function toRadians(degrees: number): number {
  return degrees * (Math.PI / 180);
}

/**
 * Format distance for display
 */
export function formatDistance(meters: number): string {
  if (meters < 1000) {
    return `${Math.round(meters)}m`;
  }
  return `${(meters / 1000).toFixed(1)}km`;
}

/**
 * Format area for display
 */
export function formatArea(squareMeters: number): string {
  if (squareMeters < 1000000) {
    return `${(squareMeters / 1000).toFixed(1)} km²`;
  }
  return `${(squareMeters / 1000000).toFixed(2)} km²`;
}
