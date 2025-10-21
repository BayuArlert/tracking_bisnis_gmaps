/**
 * Utility functions for cleaning area names
 */

/**
 * Clean area name by removing numeric codes (like postal codes)
 * Based on ACTUAL DATA in database
 * 
 * @param area - Raw area name from database
 * @returns Clean area name for display
 * 
 * Examples:
 * "Bali 80993" -> "Bali"
 * "Jimbaran" -> "Jimbaran"
 * "Sanur" -> "Sanur"
 * "Jakarta 12345" -> "Luar Bali"
 */
export function cleanAreaName(area: string): string {
  if (!area) return 'Unknown';
  
  // Remove numeric codes (5+ digits) but preserve business counts in parentheses
  // This regex removes space + 5+ digits but keeps parentheses content
  let clean = area.replace(/\s+\d{5,}/, '');
  clean = clean.trim();
  
  // Handle specific cases based on ACTUAL DATA in database
  
  // If it's just numbers (postal codes), skip
  if (/^\d+$/.test(clean)) {
    return 'Luar Bali';
  }
  
  // If contains "Kabupaten Badung", keep as is
  if (clean.toLowerCase().includes('kabupaten badung')) {
    return 'Kabupaten Badung';
  }
  
  // If contains "Kabupaten Tabanan", keep as is
  if (clean.toLowerCase().includes('kabupaten tabanan')) {
    return 'Kabupaten Tabanan';
  }
  
  // If contains "Kabupaten Bangli", keep as is
  if (clean.toLowerCase().includes('kabupaten bangli')) {
    return 'Kabupaten Bangli';
  }
  
  // If contains "Kabupaten Buleleng", keep as is
  if (clean.toLowerCase().includes('kabupaten buleleng')) {
    return 'Kabupaten Buleleng';
  }
  
  // If contains "Kabupaten Gianyar", keep as is
  if (clean.toLowerCase().includes('kabupaten gianyar')) {
    return 'Kabupaten Gianyar';
  }
  
  // If contains "Kabupaten Karangasem", keep as is
  if (clean.toLowerCase().includes('kabupaten karangasem')) {
    return 'Kabupaten Karangasem';
  }
  
  // If contains "Kabupaten Klungkung", keep as is
  if (clean.toLowerCase().includes('kabupaten klungkung')) {
    return 'Kabupaten Klungkung';
  }
  
  // If contains "Kota Denpasar", keep as is
  if (clean.toLowerCase().includes('kota denpasar')) {
    return 'Kota Denpasar';
  }
  
  // If contains "Jimbaran", keep as is (found in data)
  if (clean.toLowerCase().includes('jimbaran')) {
    return 'Jimbaran';
  }
  
  // If contains "Sanur", keep as is (found in data)
  if (clean.toLowerCase().includes('sanur')) {
    return 'Sanur';
  }
  
  // If contains "Bali" (without specific area), map to "Bali"
  if (clean.toLowerCase().includes('bali')) {
    return 'Bali';
  }
  
  // If it's clearly not Bali, return "Luar Bali"
  const nonBaliAreas = [
    'jawa timur', 'jakarta', 'surabaya', 'bandung', 'yogyakarta', 
    'solo', 'semarang', 'malang', 'medan', 'palembang',
    'makassar', 'manado', 'pontianak', 'balikpapan',
    'lombok', 'flores', 'sumba', 'timor', 'papua',
    'kalimantan', 'sumatra', 'sulawesi', 'nusa tenggara',
    'west java', 'kota bandung', 'kota semarang', 'kota denpasar',
    'kabupaten jember', 'kabupaten sayan', 'kabupaten sigi'
    // Removed Bali kabupatens: bangli, buleleng, gianyar, karangasem, klungkung, tabanan
  ];
  
  for (const nonBali of nonBaliAreas) {
    if (clean.toLowerCase().includes(nonBali)) {
      return 'Luar Bali';
    }
  }
  
  // If it's just "Kabupaten" or "Kota" without specific name, skip
  if (['kabupaten', 'kota'].includes(clean.toLowerCase())) {
    return 'Luar Bali';
  }
  
  // Default: keep the clean name if it looks reasonable
  return clean;
}

/**
 * Clean area name for display in business cards, maps, etc.
 * This version is more aggressive - removes ALL numbers except parentheses
 * 
 * @param area - Raw area name from database
 * @returns Clean area name for display
 */
export function cleanAreaNameForDisplay(area: string): string {
  if (!area) return '';
  
  // Remove ALL numeric codes but preserve parentheses content
  let clean = area.replace(/\s+\d{5,}/, '');
  clean = clean.trim();
  
  // Handle specific cases
  if (clean.includes('Bali')) {
    return 'Bali';
  }
  
  return clean;
}

/**
 * Extract business count from area name
 * 
 * @param area - Area name that might contain count like "Bali (17)"
 * @returns Number if found, null otherwise
 */
export function extractBusinessCount(area: string): number | null {
  if (!area) return null;
  
  const match = area.match(/\((\d+)\)/);
  return match ? parseInt(match[1], 10) : null;
}

/**
 * Format area name with count
 * 
 * @param area - Clean area name
 * @param count - Business count
 * @returns Formatted string like "Bali (17)"
 */
export function formatAreaWithCount(area: string, count: number): string {
  return `${area} (${count})`;
}

