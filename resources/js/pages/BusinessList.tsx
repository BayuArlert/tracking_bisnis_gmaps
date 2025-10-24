import * as React from "react";
import { useState, useEffect, useContext, ChangeEvent, useCallback } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import toast from "react-hot-toast";
import { Card } from "../components/ui/card";
import { Button } from "../components/ui/button";
import { cleanAreaName } from "../lib/areaUtils";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../components/ui/select";
import Layout from "../components/Layout";
import HierarchicalLocationFilter from "../components/HierarchicalLocationFilter";
import PeriodFilter from "../components/PeriodFilter";
import CategoryMultiSelect from "../components/CategoryMultiSelect";
import ConfidenceSlider from "../components/ConfidenceSlider";
import BusinessDetailDrawer from "../components/BusinessDetailDrawer";

// ==== Types ====
interface Business {
  id: number;
  name: string;
  category: string;
  area: string;
  address: string;
  rating: number;
  review_count: number;
  phone?: string;
  website?: string;
  first_seen: string;
  lat: number | string;
  lng: number | string;
  google_maps_url?: string;
  indicators?: {
    recently_opened: boolean;
    few_reviews: boolean;
    low_rating_count: boolean;
    has_photos: boolean;
    has_recent_photo: boolean;
    rating_improvement: boolean;
    review_spike: boolean;
    is_truly_new: boolean;
    newly_discovered: boolean;
    new_business_confidence: number;
    metadata_analysis?: {
      oldest_review_date: string | null;
      newest_review_date: string | null;
      review_age_months: number | null;
      photo_count: number;
      has_recent_activity: boolean;
      business_age_estimate: string;
      confidence_level: string;
    };
  };
}

interface ApiResponse {
  data: Business[];
  total: number;
  count: number;
  skip: number;
  limit: number;
}

interface Pagination {
  page: number;
  limit: number;
  hasMore: boolean;
  per_page: number;
  total: number;
}

interface Filters {
  area: string;
  category: string;
  categories: string[]; // Multi-select categories
  data_age: string;
  period: string; // Preset period filter (30/60/90/180 days)
  customPeriodStart?: string;
  customPeriodEnd?: string;
  kabupaten: string | null; // Hierarchical filter
  kecamatan: string | null; // Hierarchical filter
  desa: string | null; // Hierarchical filter
  confidenceThreshold: number; // 0-100
  radius: number;
  center_lat?: number;
  center_lng?: number;
}

// ==== Component ====
const BusinessList = () => {
  const { API } = useContext(AuthContext);
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [filters, setFilters] = useState<Filters>({
    area: "",
    category: "",
    categories: [], // Multi-select categories
    data_age: "",
    period: "all", // all, 30, 60, 90, 180 days, custom
    customPeriodStart: undefined,
    customPeriodEnd: undefined,
    kabupaten: null, // Hierarchical location
    kecamatan: null, // Hierarchical location
    desa: null, // Hierarchical location
    confidenceThreshold: 60, // Default confidence threshold
    radius: 5000, // Default 5km radius
    center_lat: -8.6500, // Bali center
    center_lng: 115.2167,
  });
  const [useRadiusFilter, setUseRadiusFilter] = useState<boolean>(false);
  const [selectedBusiness, setSelectedBusiness] = useState<Business | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false);
  const [isExportingCSV, setIsExportingCSV] = useState(false);
  const [pagination, setPagination] = useState<Pagination>({
    page: 1,
    limit: 10000, // Fetch up to 10000 records from API (should cover all businesses)
    hasMore: true,
    per_page: 9, // Display only 9 per page (3x3 grid)
    total: 0,
  });
  const [filterOptions, setFilterOptions] = useState<{
    areas: string[];
    categories: string[];
  }>({
    areas: [],
    categories: [],
  });

  const fetchFilterOptions = useCallback(async () => {
    try {
      const response = await axios.get<{
        areas: string[];
        categories: string[];
      }>(`${API}/businesses/filter-options`);
      setFilterOptions(response.data);
    } catch (error) {
      console.error("Error fetching filter options:", error);
    }
  }, [API]);

  const fetchBusinesses = useCallback(async (reset: boolean = true) => {
    try {
      setLoading(true);
      
      // Build query parameters - optimized like Statistics page
      const params = new URLSearchParams({
        skip: '0',
        limit: pagination.limit.toString(),
      });

      // Apply filters similar to Statistics page
      if (filters.area && filters.area !== "all") params.append("area", filters.area);
      if (filters.category && filters.category !== "all") params.append("category", filters.category);
      if (filters.data_age && filters.data_age !== "all") params.append("data_age", filters.data_age);
      
      // Hierarchical location filters - same as Statistics
      if (filters.kabupaten) {
        params.append("kabupaten", filters.kabupaten);
      }
      if (filters.kecamatan) {
        params.append("kecamatan", filters.kecamatan);
      }
      if (filters.desa) {
        params.append("desa", filters.desa);
      }
      
      // Multi-select categories
      if (filters.categories.length > 0) {
        filters.categories.forEach(cat => params.append('categories[]', cat));
      }
      
      // Period filters
      if (filters.period !== 'all') {
        if (filters.period === 'custom' && filters.customPeriodStart && filters.customPeriodEnd) {
          params.append('date_from', filters.customPeriodStart);
          params.append('date_to', filters.customPeriodEnd);
        } else if (filters.period !== 'custom') {
          params.append('period', filters.period);
        }
      }
      
      // Confidence threshold
      if (filters.confidenceThreshold > 0) params.append('min_confidence', filters.confidenceThreshold.toString());
      
      // Radius filter
      if (useRadiusFilter) {
        params.append("use_radius", "true");
        params.append("radius", filters.radius.toString());
        if (filters.center_lat !== undefined) params.append("center_lat", filters.center_lat.toString());
        if (filters.center_lng !== undefined) params.append("center_lng", filters.center_lng.toString());
      }

      console.log('BusinessList API URL:', `${API}/businesses?${params}`);
      const url = `${API}/businesses?${params}`;
      console.log('BusinessList: API URL:', url);
      console.log('BusinessList: Params:', params.toString());
      
      const response = await axios.get<ApiResponse>(url);
      
      const businessData = response.data.data || [];
      const totalFromAPI = response.data.total || businessData.length;
      
      setBusinesses(businessData);
      setPagination((prev) => ({ 
        ...prev, 
        page: 1, 
        total: totalFromAPI,
        hasMore: businessData.length < totalFromAPI
      }));
      
      console.log(`BusinessList: Loaded ${businessData.length} of ${totalFromAPI} businesses`);

    } catch (error) {
      toast.error("Gagal memuat data bisnis");
      console.error("Error fetching businesses:", error);
    } finally {
      setLoading(false);
    }
  }, [API, filters, pagination.limit, useRadiusFilter]);

  useEffect(() => {
    fetchFilterOptions();
  }, [fetchFilterOptions]);

  useEffect(() => {
    // Reset pagination when filters change
    setPagination(prev => ({ ...prev, page: 1 }));
    
    // Debounced filter changes to prevent too many API calls
    const timeoutId = setTimeout(() => {
      fetchBusinesses();
    }, 300); // 300ms debounce

    return () => clearTimeout(timeoutId);
  }, [filters, fetchBusinesses]);

  const loadMore = () => {
    setPagination((prev) => ({ ...prev, page: prev.page + 1 }));
    fetchBusinesses(false);
  };

  const fetchNewData = async () => {
    const loadingToast = toast.loading("Mengambil data cafe baru dari Google Maps...", { 
      duration: Infinity // Toast tidak akan hilang otomatis
    });
    
    try {
      // Gunakan parameter yang spesifik untuk cafe di Kabupaten Tabanan
      const response = await axios.get(`${API}/businesses/new?area=Kabupaten%20Tabanan`);
      const { fetched, new: newCount, total_processed, method, total_unique_places, text_search_queries, nearby_search_points } = response.data;
      
      // Refresh data setelah fetch
      await fetchBusinesses(true);
      
      // Hapus loading toast dan tampilkan success
      toast.dismiss(loadingToast);
      toast.success(
        `✅ Berhasil! ${method === 'multiple_radius_search' ? 'Multiple Radius Search' : method} - ` +
        `Diproses ${total_processed} tempat, ${total_unique_places || 'N/A'} unique. ` +
        `Text Search: ${text_search_queries || 0} queries, Nearby: ${nearby_search_points || 0} points (radius 3-5km). ` +
        `${newCount} cafe baru ditambahkan ke database.`
      );
      
    } catch (error: any) {
      // Hapus loading toast dan tampilkan error
      toast.dismiss(loadingToast);
      const errorMessage = error.response?.data?.error || error.response?.data?.message || "Gagal mengambil data baru";
      toast.error(`❌ ${errorMessage}`);
      console.error("Error fetching new data:", error);
    }
  };


  const exportCSV = async () => {
    try {
      setIsExportingCSV(true);
      // Show loading toast with progress
      const loadingToast = toast.loading('Sedang mempersiapkan CSV... (mengambil semua data)');
      
      // Build query parameters from current filters
      const params = new URLSearchParams();
      
      // Add all current filters
      if (filters.area && filters.area !== 'all') params.append('area', filters.area);
      if (filters.category && filters.category !== 'all') params.append('category', filters.category);
      if (filters.categories.length > 0) {
        filters.categories.forEach(cat => params.append('categories[]', cat));
      }
      if (filters.data_age && filters.data_age !== 'all') params.append('data_age', filters.data_age);
      if (filters.period !== 'all') {
        if (filters.period === 'custom' && filters.customPeriodStart && filters.customPeriodEnd) {
          params.append('date_from', filters.customPeriodStart);
          params.append('date_to', filters.customPeriodEnd);
        } else if (filters.period !== 'custom') {
          params.append('period', filters.period);
        }
      }
      if (filters.kabupaten) params.append('kabupaten', filters.kabupaten);
      if (filters.kecamatan) params.append('kecamatan', filters.kecamatan);
      if (filters.confidenceThreshold > 0) params.append('min_confidence', filters.confidenceThreshold.toString());
      
      // Add radius filter if active
      if (useRadiusFilter && filters.radius && filters.center_lat && filters.center_lng) {
        params.append('use_radius', 'true');
        params.append('radius', filters.radius.toString());
        params.append('center_lat', filters.center_lat.toString());
        params.append('center_lng', filters.center_lng.toString());
      }
      
      // Use axios for better error handling and headers
      const response = await axios.get(`${API}/export/csv`, {
        params: params,
        responseType: 'blob', // Important for file download
        headers: {
          'Accept': 'text/csv, application/csv',
        },
        timeout: 30000, // 30 second timeout
      });
      
      // Generate filename with timestamp
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-').split('T')[0];
      const filename = `businesses_export_${timestamp}.csv`;
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      
      // Cleanup
      window.URL.revokeObjectURL(url);
      document.body.removeChild(link);
      
      // Dismiss loading and show success
      toast.dismiss(loadingToast);
      toast.success(`CSV berhasil diunduh! (${filename}) - Semua data yang sesuai filter`);
      
    } catch (error: any) {
      console.error('Export error:', error);
      
      // Show specific error message
      let errorMessage = 'Gagal mengunduh CSV';
      if (error.response?.status === 500) {
        errorMessage = 'Server error - coba lagi nanti';
      } else if (error.response?.status === 404) {
        errorMessage = 'Export endpoint tidak ditemukan';
      } else if (error.code === 'ECONNABORTED') {
        errorMessage = 'Timeout - data terlalu besar';
      }
      
      toast.error(errorMessage);
    } finally {
      setIsExportingCSV(false);
    }
  };

  const exportJSON = async () => {
    try {
      setIsExportingCSV(true);
      // Show loading toast with progress (same as CSV)
      const loadingToast = toast.loading('Sedang mempersiapkan JSON... (mengambil semua data)');
      
      // Build query parameters from current filters (same as CSV)
      const params = new URLSearchParams();
      
      // Add all current filters (same as CSV export)
      if (filters.area && filters.area !== 'all') params.append('area', filters.area);
      if (filters.category && filters.category !== 'all') params.append('category', filters.category);
      if (filters.categories.length > 0) {
        filters.categories.forEach(cat => params.append('categories[]', cat));
      }
      if (filters.data_age && filters.data_age !== 'all') params.append('data_age', filters.data_age);
      if (filters.period !== 'all') {
        if (filters.period === 'custom' && filters.customPeriodStart && filters.customPeriodEnd) {
          params.append('date_from', filters.customPeriodStart);
          params.append('date_to', filters.customPeriodEnd);
        } else if (filters.period !== 'custom') {
          params.append('period', filters.period);
        }
      }
      if (filters.kabupaten) params.append('kabupaten', filters.kabupaten);
      if (filters.kecamatan) params.append('kecamatan', filters.kecamatan);
      if (filters.confidenceThreshold > 0) params.append('min_confidence', filters.confidenceThreshold.toString());
      
      // Add radius filter if active
      if (useRadiusFilter && filters.radius && filters.center_lat && filters.center_lng) {
        params.append('use_radius', 'true');
        params.append('radius', filters.radius.toString());
        params.append('center_lat', filters.center_lat.toString());
        params.append('center_lng', filters.center_lng.toString());
      }
      
      // Use axios for better error handling and headers (same as CSV)
      const response = await axios.get(`${API}/export/json`, {
        params: params,
        responseType: 'blob', // Important for file download
        headers: {
          'Accept': 'application/json',
        },
        timeout: 30000, // 30 second timeout
      });
      
      // Generate filename with timestamp (same as CSV)
      const timestamp = new Date().toISOString().replace(/[:.]/g, '-').split('T')[0];
      const filename = `businesses_export_${timestamp}.json`;
      
      // Create download link (same as CSV)
      const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/json' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      
      // Cleanup
      window.URL.revokeObjectURL(url);
      document.body.removeChild(link);
      
      // Dismiss loading and show success (same as CSV)
      toast.dismiss(loadingToast);
      toast.success(`JSON berhasil diunduh! (${filename}) - Semua data yang sesuai filter`);
      
    } catch (error: any) {
      console.error('Export error:', error);
      
      // Show specific error message (same as CSV)
      let errorMessage = 'Gagal mengunduh JSON';
      if (error.response?.status === 500) {
        errorMessage = 'Server error - coba lagi nanti';
      } else if (error.response?.status === 404) {
        errorMessage = 'Export endpoint tidak ditemukan';
      } else if (error.code === 'ECONNABORTED') {
        errorMessage = 'Timeout - data terlalu besar';
      }
      
      toast.error(errorMessage);
    } finally {
      setIsExportingCSV(false);
    }
  };

  const filteredBusinesses = Array.isArray(businesses) ? businesses.filter(
    (business) => {
      
      // Period filter (based on first_seen date)
      if (filters.period && filters.period !== 'all' && filters.period !== 'custom') {
        const days = parseInt(filters.period);
        const firstSeenDate = new Date(business.first_seen);
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - days);
        
        if (firstSeenDate < cutoffDate) {
          return false; // Business is older than the period
        }
      }
      
      // Custom period filter
      if (filters.period === 'custom' && filters.customPeriodStart && filters.customPeriodEnd) {
        const firstSeenDate = new Date(business.first_seen);
        const startDate = new Date(filters.customPeriodStart);
        const endDate = new Date(filters.customPeriodEnd);
        
        if (firstSeenDate < startDate || firstSeenDate > endDate) {
          return false;
        }
      }
      
      // Multi-select categories filter
      if (filters.categories.length > 0) {
        const businessCategory = business.category || '';
        // Case-insensitive comparison
        const normalizedBusinessCategory = businessCategory.toLowerCase();
        const normalizedFilterCategories = filters.categories.map(cat => cat.toLowerCase());
        
        if (!normalizedFilterCategories.includes(normalizedBusinessCategory)) {
          return false;
        }
      }
      
      // Note: Hierarchical location filtering (kabupaten/kecamatan) is handled by backend
      // No need to filter again in frontend to avoid duplication
      
      // Confidence threshold filter
      if (filters.confidenceThreshold > 0) {
        const confidence = business.indicators?.new_business_confidence || 0;
        if (confidence < filters.confidenceThreshold) {
          return false;
        }
      }
      
      return true;
    }
  ) : [];

  // Display area helper: prefer specific kabupaten/kota parsed from address when area is generic "Bali"
  const getDisplayArea = (business: Business) => {
    const areaName = cleanAreaName(business.area);
    if (areaName === 'Bali' && business.address) {
      const addr = business.address.toLowerCase();
      // Try "Kabupaten X" first
      const kabMatch = addr.match(/kabupaten\s+([a-z\s]+?)(,|$)/i);
      if (kabMatch && kabMatch[1]) {
        const kab = kabMatch[1].trim().replace(/\b\w/g, (l) => l.toUpperCase());
        return `Kabupaten ${kab}`;
      }
      // Try "Kota X"
      const kotaMatch = addr.match(/kota\s+([a-z\s]+?)(,|$)/i);
      if (kotaMatch && kotaMatch[1]) {
        const kota = kotaMatch[1].trim().replace(/\b\w/g, (l) => l.toUpperCase());
        return `Kota ${kota}`;
      }
      // Try "Badung Regency" -> "Kabupaten Badung"
      const regencyMatch = addr.match(/([a-z\s]+?)\s+regency/i);
      if (regencyMatch && regencyMatch[1]) {
        const reg = regencyMatch[1].trim().replace(/\b\w/g, (l) => l.toUpperCase());
        return `Kabupaten ${reg}`;
      }
    }
    return areaName;
  };

  // Helper function to get area icons - Based on ACTUAL DATA
  const getAreaIcon = (areaName: string) => {
    const iconMap: { [key: string]: string } = {
      'Bali': '🏝️',
      'Kabupaten Badung': '🏝️',
      'Jimbaran': '🐟',
      'Sanur': '🌅',
      'Luar Bali': '🚫',
    };
    
    return iconMap[areaName] || '📍';
  };

  const cleanCategoryName = (category: string | null | undefined) => {
    // Handle null/undefined category
    if (!category) return 'Unknown';
    
    // Convert snake_case to Title Case
    // "beauty_salon" -> "Beauty Salon"
    let clean = category.replace(/_/g, ' ');
    clean = clean.replace(/\b\w/g, l => l.toUpperCase());
    
    return clean;
  };

  const getBusinessAgeLabel = (metadata: { business_age_estimate?: string }) => {
    if (!metadata) return '';
    
    switch (metadata.business_age_estimate) {
      case 'ultra_new':
        return '🔥 Ultra Baru';
      case 'very_new':
        return '🆕 Sangat Baru';
      case 'new':
        return '🆕 Baru';
      case 'recent':
        return '📅 Recent';
      case 'established':
        return '🏢 Established';
      case 'old':
        return '📅 Sudah Lama';
      default:
        return '❓ Tidak Diketahui';
    }
  };

  const getConfidenceColor = (confidence: number) => {
    if (confidence >= 80) return 'bg-green-100 text-green-800';
    if (confidence >= 60) return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
  };

  // ==== Handler for opening detail drawer ====
  const handleBusinessClick = (business: Business) => {
    setSelectedBusiness(business);
    setIsDrawerOpen(true);
  };

  // ==== Subcomponent BusinessCard ====
  const BusinessCard: React.FC<{ business: Business }> = ({ business }) => (
    <Card 
      className="business-card p-6 h-full flex flex-col bg-white border-0 shadow-lg hover:shadow-xl transition-all duration-300 rounded-2xl cursor-pointer" 
      data-testid={`business-${business.id}`}
      onClick={() => handleBusinessClick(business)}
    >
      {/* Header Section */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1 min-w-0">
          <h3 className="font-bold text-lg text-gray-900 mb-2 truncate">{business.name}</h3>
          <div className="flex items-center space-x-2 mb-2 flex-wrap">
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              {cleanCategoryName(business.category)}
            </span>
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              {getDisplayArea(business)}
            </span>
          </div>
          <p className="text-sm text-gray-600 mb-3 line-clamp-2 break-words">{business.address}</p>
        </div>
        
        {/* Status Indicators - Focus on New Business Indicators */}
        <div className="flex flex-col space-y-1 ml-3 flex-shrink-0">
          {/* Recently Opened Indicator */}
          {business.indicators?.recently_opened && (
            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
              ✅ Recently Opened
            </span>
          )}
          
          {/* Review Spike Indicator */}
          {business.indicators?.review_spike && (
            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
              📈 Review Spike
            </span>
          )}
          
          {/* Few Reviews Indicator */}
          {business.indicators?.few_reviews && (
            <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              📝 Few Reviews
            </span>
          )}
          
          {/* Business Age Estimate */}
          {business.indicators?.metadata_analysis?.business_age_estimate && (
            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
              business.indicators.metadata_analysis.business_age_estimate === 'ultra_new' ? 'bg-red-100 text-red-800' :
              business.indicators.metadata_analysis.business_age_estimate === 'very_new' ? 'bg-green-100 text-green-800' :
              business.indicators.metadata_analysis.business_age_estimate === 'new' ? 'bg-blue-100 text-blue-800' :
              business.indicators.metadata_analysis.business_age_estimate === 'recent' ? 'bg-yellow-100 text-yellow-800' :
              'bg-gray-100 text-gray-800'
            }`}>
              {getBusinessAgeLabel(business.indicators.metadata_analysis)}
            </span>
          )}
          
          {/* Confidence Score */}
          {business.indicators?.new_business_confidence && business.indicators.new_business_confidence > 60 && (
            <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getConfidenceColor(business.indicators.new_business_confidence)}`}>
              🎯 {business.indicators.new_business_confidence}% Confidence
            </span>
          )}
        </div>
      </div>

      {/* Key Metrics Section */}
      <div className="grid grid-cols-2 gap-4 mb-4">
        <div className="bg-gray-50 rounded-lg p-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <span className="text-yellow-500">⭐</span>
              <span className="text-sm font-medium text-gray-700">Rating</span>
            </div>
            <span className="font-bold text-gray-900">{business.rating || 'N/A'}</span>
          </div>
        </div>
        
        <div className="bg-gray-50 rounded-lg p-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <span className="text-blue-500">💬</span>
              <span className="text-sm font-medium text-gray-700">Reviews</span>
            </div>
            <span className="font-bold text-gray-900">{business.review_count}</span>
          </div>
        </div>
      </div>

      {/* Additional Info Section */}
      <div className="space-y-2 mb-4 text-sm">
        <div className="flex items-center justify-between">
          <span className="text-gray-600">📅 First Seen:</span>
          <span className="font-medium text-gray-900">
            {new Date(business.first_seen).toLocaleDateString("id-ID")}
          </span>
        </div>
        
        {business.indicators?.metadata_analysis?.review_age_months && (
          <div className="flex items-center justify-between">
            <span className="text-gray-600">📊 Review Age:</span>
            <span className="font-medium text-gray-900">
              {business.indicators.metadata_analysis.review_age_months} months
            </span>
          </div>
        )}
        
        {business.indicators?.metadata_analysis?.photo_count && (
          <div className="flex items-center justify-between">
            <span className="text-gray-600">📸 Photos:</span>
            <span className="font-medium text-gray-900">
              {business.indicators.metadata_analysis.photo_count}
            </span>
          </div>
        )}
        
        {business.website && (
          <div className="flex items-center justify-between">
            <span className="text-gray-600">🌐 Website:</span>
            <a 
              href={business.website}
              target="_blank"
              rel="noopener noreferrer"
              className="font-medium text-blue-600 hover:text-blue-800 hover:underline truncate max-w-[150px]"
              onClick={(e) => e.stopPropagation()}
            >
              {business.website.replace(/^https?:\/\/(www\.)?/, '').substring(0, 25)}{business.website.length > 30 ? '...' : ''}
            </a>
          </div>
        )}
      </div>

      {/* Footer Section */}
      <div className="mt-auto pt-4 border-t border-gray-100">
        <div className="flex items-center justify-between">
          <a
            href={business.google_maps_url || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(business.address || business.name)}`}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
            data-testid={`maps-link-${business.id}`}
          >
            <span className="mr-1">📍</span>
            View on Maps
          </a>
          
          <div className="text-xs text-gray-500">
            <div>Lat: {business.lat ? Number(business.lat).toFixed(4) : 'N/A'}</div>
            <div>Lng: {business.lng ? Number(business.lng).toFixed(4) : 'N/A'}</div>
          </div>
        </div>
      </div>
    </Card>
  );

  return (
    <Layout>
      <div className="bg-gradient-to-br from-gray-50 to-blue-50 min-h-screen p-6">
        {/* Header */}
        <div className="flex items-center justify-between mb-8">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-2">Daftar Bisnis Baru</h1>
            <p className="text-gray-600">
              Pantau dan kelola daftar bisnis baru
            </p>
          </div>
          <div className="flex space-x-3">
            <Button 
              onClick={fetchNewData} 
              className="flex items-center space-x-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white shadow-lg hover:shadow-xl transform transition-all duration-200 hover:-translate-y-0.5"
            >
              🔍 <span>Fetch Data Baru</span>
            </Button>
            <Button 
              onClick={exportCSV} 
              disabled={isExportingCSV}
              className="flex items-center space-x-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white shadow-lg hover:shadow-xl transform transition-all duration-200 hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isExportingCSV ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                  <span>Exporting...</span>
                </>
              ) : (
                <>
              ⬇️ <span>Export CSV</span>
                </>
              )}
            </Button>
            <Button 
              onClick={exportJSON} 
              className="flex items-center space-x-2 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white shadow-lg hover:shadow-xl transform transition-all duration-200 hover:-translate-y-0.5"
            >
              📦 <span>Export JSON</span>
            </Button>
          </div>
        </div>

        {/* Filters */}
        <Card className="filter-section mb-8 p-8 shadow-lg rounded-2xl filter-container">
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">Filter Bisnis</h3>
              <p className="text-sm text-gray-600">Saring bisnis berdasarkan kriteria yang diinginkan</p>
            </div>
            
            {/* Hierarchical Location Filter - Full Width - Same as Statistics */}
            <div className="bg-white p-4 rounded-lg border border-gray-200 filter-container">
              <h4 className="text-sm font-semibold text-gray-800 mb-3">📍 Lokasi</h4>
              <HierarchicalLocationFilter
                kabupaten={filters.kabupaten || undefined}
                kecamatan={filters.kecamatan || undefined}
                desa={filters.desa || undefined}
                onKabupatenChange={(value) => {
                  console.log('BusinessList - Kabupaten changed to:', value);
                  setFilters(prev => ({ 
                    ...prev, 
                    kabupaten: value, 
                    kecamatan: null, // Reset kecamatan when kabupaten changes
                    desa: null // Reset desa when kabupaten changes
                  }));
                }}
                onKecamatanChange={(value) => {
                  console.log('BusinessList - Kecamatan changed to:', value);
                  setFilters(prev => ({ 
                    ...prev, 
                    kecamatan: value,
                    desa: null // Reset desa when kecamatan changes
                  }));
                }}
                onDesaChange={(value) => {
                  console.log('BusinessList - Desa changed to:', value);
                  setFilters(prev => ({ ...prev, desa: value }));
                }}
              />
              </div>
              
            {/* Grid Layout for Other Filters */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {/* NEW: Multi-select Categories */}
              <CategoryMultiSelect
                value={filters.categories}
                onChange={(categories) => setFilters({ ...filters, categories })}
              />

              {/* NEW: Period Filter with Presets */}
              <PeriodFilter
                value={filters.period}
                customStart={filters.customPeriodStart}
                customEnd={filters.customPeriodEnd}
                onChange={(period, start, end) => 
                  setFilters({ ...filters, period, customPeriodStart: start, customPeriodEnd: end })
                }
              />
              
              {/* Data Age Filter (Keep existing) */}
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">Usia Data</label>
                <Select
                  value={filters.data_age || undefined}
                  onValueChange={(value) =>
                    setFilters({ ...filters, data_age: value || "" })
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Pilih usia data" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Usia</SelectItem>
                    <SelectItem value="ultra_new">Ultra Baru (&lt; 1 minggu)</SelectItem>
                    <SelectItem value="very_new">Sangat Baru (&lt; 1 bulan)</SelectItem>
                    <SelectItem value="new">Baru (&lt; 3 bulan)</SelectItem>
                    <SelectItem value="recent">Recent (&lt; 12 bulan)</SelectItem>
                    <SelectItem value="established">Established (1-3 tahun)</SelectItem>
                    <SelectItem value="old">Lama (&gt; 3 tahun)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              </div>
              
            {/* NEW: Confidence Threshold Slider */}
            <ConfidenceSlider
              value={filters.confidenceThreshold}
              onChange={(value) => setFilters({ ...filters, confidenceThreshold: value })}
              label="Ambang Batas Confidence Score"
            />
            
            {/* Filter Status Indicator - Same as Statistics */}
            <div className="mt-6 p-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl border border-blue-100">
              <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2">
                  <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                <span className="text-sm font-medium text-gray-700">
                    Filter Aktif: {filters.kabupaten && `Kabupaten ${filters.kabupaten}`}
                    {filters.kecamatan && ` > ${filters.kecamatan}`}
                    {filters.categories.length > 0 && ` | ${filters.categories.length} kategori`}
                    {filters.period !== 'all' && ` | ${filters.period} hari`}
                    {filters.confidenceThreshold > 0 && ` | Confidence ≥${filters.confidenceThreshold}%`}
                  </span>
                </div>
                <div className="flex items-center space-x-2">
                  <span className="text-xs text-gray-600">
                    Menampilkan {filteredBusinesses.length} dari {businesses.length} bisnis
                </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setFilters({ 
                    area: "", 
                    category: "", 
                    categories: [],
                    data_age: "", 
                    period: "all",
                    customPeriodStart: undefined,
                    customPeriodEnd: undefined,
                    kabupaten: null,
                    kecamatan: null,
                    desa: null,
                    confidenceThreshold: 60,
                    radius: 5000, 
                    center_lat: -8.6500, 
                    center_lng: 115.2167 
                  });
                      setUseRadiusFilter(false);
                    }}
                className="text-gray-600 border-gray-300 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 font-medium"
              >
                🔄 Reset Filter
              </Button>
                </div>
              </div>
            </div>
          </div>
        </Card>

        {/* Business List */}
        {loading && businesses.length === 0 ? (
          <p>Memuat data...</p>
        ) : filteredBusinesses.length > 0 ? (
          <>
            {/* Paginated businesses display */}
            {(() => {
              const startIndex = (pagination.page - 1) * pagination.per_page;
              const endIndex = startIndex + pagination.per_page;
              const paginatedBusinesses = filteredBusinesses.slice(startIndex, endIndex);
              
              return (
                <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8 auto-rows-fr">
                  {paginatedBusinesses.map((business, index) => (
                    <BusinessCard key={`${business.id}-${index}`} business={business} />
                  ))}
                </div>
              );
            })()}

            {/* Pagination */}
            {(() => {
              const totalPages = Math.ceil(filteredBusinesses.length / pagination.per_page);
              const startIndex = (pagination.page - 1) * pagination.per_page;
              const endIndex = Math.min(startIndex + pagination.per_page, filteredBusinesses.length);
              
              if (totalPages <= 1) return null;
              
              return (
                <div className="flex items-center justify-between mt-6 pt-6 border-t border-gray-200">
                  <div className="text-sm text-gray-600">
                    Menampilkan {startIndex + 1}-{endIndex} dari {filteredBusinesses.length} bisnis
                  </div>
                  <div className="flex items-center space-x-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setPagination(prev => ({ ...prev, page: prev.page - 1 }))}
                      disabled={pagination.page === 1}
                    >
                      Previous
                    </Button>
                    <span className="text-sm text-gray-600">
                      Halaman {pagination.page} dari {totalPages}
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setPagination(prev => ({ ...prev, page: prev.page + 1 }))}
                      disabled={pagination.page === totalPages}
                    >
                      Next
                    </Button>
                  </div>
                </div>
              );
            })()}
          </>
        ) : (
          <div className="text-center py-12">Tidak Ada Bisnis Ditemukan</div>
        )}
      </div>

      {/* Business Detail Drawer */}
      <BusinessDetailDrawer
        business={selectedBusiness}
        isOpen={isDrawerOpen}
        onClose={() => setIsDrawerOpen(false)}
      />
    </Layout>
  );
};

export default BusinessList;
