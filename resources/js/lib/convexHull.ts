/**
 * Convex Hull & Geometry Utilities for Map Preview Area
 */

export interface LatLng {
  lat: number;
  lng: number;
}

/**
 * Calculate convex hull using Graham Scan algorithm
 */
export function calculateConvexHull(points: LatLng[]): LatLng[] {
  if (points.length < 3) {
    return points;
  }

  // Find the point with lowest y-coordinate (and leftmost if tie)
  let lowestPoint = points[0];
  for (let i = 1; i < points.length; i++) {
    if (points[i].lat < lowestPoint.lat || 
        (points[i].lat === lowestPoint.lat && points[i].lng < lowestPoint.lng)) {
      lowestPoint = points[i];
    }
  }

  // Sort points by polar angle with respect to lowest point
  const sortedPoints = points.slice().sort((a, b) => {
    const angleA = Math.atan2(a.lat - lowestPoint.lat, a.lng - lowestPoint.lng);
    const angleB = Math.atan2(b.lat - lowestPoint.lat, b.lng - lowestPoint.lng);
    
    if (angleA === angleB) {
      // If angles are equal, sort by distance
      const distA = Math.sqrt(Math.pow(a.lat - lowestPoint.lat, 2) + Math.pow(a.lng - lowestPoint.lng, 2));
      const distB = Math.sqrt(Math.pow(b.lat - lowestPoint.lat, 2) + Math.pow(b.lng - lowestPoint.lng, 2));
      return distA - distB;
    }
    
    return angleA - angleB;
  });

  // Build convex hull
  const hull: LatLng[] = [sortedPoints[0], sortedPoints[1]];

  for (let i = 2; i < sortedPoints.length; i++) {
    while (hull.length >= 2 && !isLeftTurn(hull[hull.length - 2], hull[hull.length - 1], sortedPoints[i])) {
      hull.pop();
    }
    hull.push(sortedPoints[i]);
  }

  return hull;
}

/**
 * Check if three points make a left turn (counter-clockwise)
 */
function isLeftTurn(p1: LatLng, p2: LatLng, p3: LatLng): boolean {
  const crossProduct = (p2.lng - p1.lng) * (p3.lat - p1.lat) - (p2.lat - p1.lat) * (p3.lng - p1.lng);
  return crossProduct > 0;
}

/**
 * Calculate centroid (center point) of a set of points
 */
export function calculateCentroid(points: LatLng[]): LatLng {
  if (points.length === 0) {
    return { lat: 0, lng: 0 };
  }

  const sum = points.reduce(
    (acc, point) => ({
      lat: acc.lat + point.lat,
      lng: acc.lng + point.lng,
    }),
    { lat: 0, lng: 0 }
  );

  return {
    lat: sum.lat / points.length,
    lng: sum.lng / points.length,
  };
}

/**
 * Calculate bounding circle (smallest circle containing all points)
 */
export function calculateBoundingCircle(points: LatLng[]): { center: LatLng; radius: number } {
  if (points.length === 0) {
    return { center: { lat: 0, lng: 0 }, radius: 0 };
  }

  const center = calculateCentroid(points);
  
  // Calculate radius as the maximum distance from center
  let maxDistance = 0;
  for (const point of points) {
    const distance = calculateDistance(center, point);
    if (distance > maxDistance) {
      maxDistance = distance;
    }
  }

  return {
    center,
    radius: maxDistance,
  };
}

/**
 * Calculate distance between two points in meters (Haversine formula)
 */
export function calculateDistance(point1: LatLng, point2: LatLng): number {
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
 * Get area name from address (e.g., "Canggu", "Seminyak")
 */
export function extractAreaName(address: string): string {
  const parts = address.split(',').map(p => p.trim());
  
  // Try to find specific area names (kecamatan/desa level)
  for (const part of parts) {
    // Skip "Kabupaten", "Kota", "Bali", postal codes
    if (part.match(/^(Kabupaten|Kota|Bali|\d+)/)) {
      continue;
    }
    
    // Skip very short parts
    if (part.length < 3) {
      continue;
    }
    
    return part;
  }
  
  return parts[0] || 'Unknown';
}

/**
 * Format area summary for cluster
 * e.g., "10 café baru; pusat: Canggu-barat, Tabanan"
 */
export function formatClusterSummary(
  count: number,
  category: string,
  centerAddress: string,
  radius: number
): string {
  const areaName = extractAreaName(centerAddress);
  const radiusKm = (radius / 1000).toFixed(1);
  
  return `${count} ${category} baru; pusat: ${areaName} (radius ${radiusKm}km)`;
}

/**
 * Calculate area coverage in km²
 */
export function calculateAreaCoverage(points: LatLng[]): number {
  if (points.length < 3) {
    return 0;
  }

  const hull = calculateConvexHull(points);
  
  // Calculate polygon area using Shoelace formula
  let area = 0;
  for (let i = 0; i < hull.length; i++) {
    const j = (i + 1) % hull.length;
    area += hull[i].lng * hull[j].lat;
    area -= hull[j].lng * hull[i].lat;
  }
  area = Math.abs(area) / 2;

  // Convert from square degrees to square kilometers (approximate)
  // At equator: 1 degree ≈ 111km
  const kmPerDegree = 111;
  return area * kmPerDegree * kmPerDegree;
}
