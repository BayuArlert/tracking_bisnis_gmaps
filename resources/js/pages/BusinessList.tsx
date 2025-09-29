import * as React from "react";
import { useState, useEffect, useContext, ChangeEvent } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import toast from "react-hot-toast";
import { Card } from "../components/ui/card";
import { Button } from "../components/ui/button";
import { Input } from "../components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../components/ui/select";
import Layout from "../components/Layout";
import { router } from '@inertiajs/react';

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
  // tambahkan field lain jika ada seperti meta, links, dll
}

interface Pagination {
  page: number;
  limit: number;
  hasMore: boolean;
}

interface Filters {
  area: string;
  category: string;
  data_age: string;
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
    data_age: "",
    radius: 5000, // Default 5km radius
    center_lat: -8.6500, // Bali center
    center_lng: 115.2167,
  });
  const [useRadiusFilter, setUseRadiusFilter] = useState<boolean>(false);
  const [searchTerm, setSearchTerm] = useState<string>("");
  const [pagination, setPagination] = useState<Pagination>({
    page: 0,
    limit: 20,
    hasMore: true,
  });
  const [filterOptions, setFilterOptions] = useState<{
    areas: string[];
    categories: string[];
  }>({
    areas: [],
    categories: [],
  });

  useEffect(() => {
    fetchFilterOptions();
  }, []);

  useEffect(() => {
    fetchBusinesses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters]);

  const fetchFilterOptions = async () => {
    try {
      const response = await axios.get<{
        areas: string[];
        categories: string[];
      }>(`${API}/businesses/filter-options`);
      setFilterOptions(response.data);
    } catch (error) {
      console.error("Error fetching filter options:", error);
    }
  };

  const fetchBusinesses = async (reset: boolean = true) => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        skip: (reset ? 0 : pagination.page * pagination.limit).toString(),
        limit: pagination.limit.toString(),
      });

      if (filters.area && filters.area !== "all") params.append("area", filters.area);
      if (filters.category && filters.category !== "all")
        params.append("category", filters.category);
      if (filters.data_age && filters.data_age !== "all")
        params.append("data_age", filters.data_age);
      if (useRadiusFilter) {
        params.append("use_radius", "true");
        params.append("radius", filters.radius.toString());
        if (filters.center_lat !== undefined) params.append("center_lat", filters.center_lat.toString());
        if (filters.center_lng !== undefined) params.append("center_lng", filters.center_lng.toString());
      }

      console.log('API URL:', `${API}/businesses?${params}`);
      const response = await axios.get<ApiResponse>(`${API}/businesses?${params}`);
      console.log('API Response Structure:', {
        type: typeof response.data,
        data: response.data,
        keys: Object.keys(response.data),
        sampleBusiness: response.data.data?.[0]
      });

      // Mengambil array businesses dari response
      const businessData = response.data.data || [];
      
      if (reset) {
        setBusinesses(businessData);
        setPagination((prev) => ({ ...prev, page: 0 }));
      } else {
        setBusinesses((prev) => [...prev, ...businessData]);
      }
      console.log('Data yang akan diset ke state:', businessData);

      setPagination((prev) => ({
        ...prev,
        hasMore: businessData.length === pagination.limit,
      }));
    } catch (error) {
      toast.error("Gagal memuat data bisnis");
      console.error("Error fetching businesses:", error);
    } finally {
      setLoading(false);
    }
  };

  const loadMore = () => {
    setPagination((prev) => ({ ...prev, page: prev.page + 1 }));
    fetchBusinesses(false);
  };

  const fetchNewData = async () => {
    try {
      toast("Mengambil data baru dari Google Maps...");
      const response = await axios.get(`${API}/businesses/new`);
      const { fetched, new: newCount } = response.data;
      
      toast.success(`Berhasil mengambil ${fetched} bisnis. ${newCount} bisnis baru ditambahkan.`);
      
      // Refresh data setelah fetch
      fetchBusinesses(true);
    } catch (error) {
      toast.error("Gagal mengambil data baru");
      console.error("Error fetching new data:", error);
    }
  };


  const exportCSV = () => {
    // Direct download approach - simpler and more reliable
    window.open(`${API}/export/csv`, '_blank');
    toast.success("Data berhasil diexport ke CSV");
  };

  const filteredBusinesses = Array.isArray(businesses) ? businesses.filter(
    (business) =>
      business.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      business.address.toLowerCase().includes(searchTerm.toLowerCase())
  ) : [];

  // Helper functions to clean display data
  const cleanAreaName = (area: string) => {
    // Remove numbers and extra spaces from area names
    // "Bali 80993" -> "Bali"
    let clean = area.replace(/\s+\d+/, '');
    clean = clean.trim();
    
    // Handle specific cases
    if (clean.includes('Bali')) {
      return 'Bali';
    }
    
    return clean;
  };

  const cleanCategoryName = (category: string) => {
    // Convert snake_case to Title Case
    // "beauty_salon" -> "Beauty Salon"
    let clean = category.replace(/_/g, ' ');
    clean = clean.replace(/\b\w/g, l => l.toUpperCase());
    
    return clean;
  };

  const getBusinessAgeLabel = (metadata: any) => {
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

  // ==== Subcomponent BusinessCard ====
  const BusinessCard: React.FC<{ business: Business }> = ({ business }) => (
    <Card className="business-card p-6 h-full flex flex-col bg-white border-0 shadow-lg hover:shadow-xl transition-all duration-300 rounded-2xl" data-testid={`business-${business.id}`}>
      {/* Header Section */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1 min-w-0">
          <h3 className="font-bold text-lg text-gray-900 mb-2 truncate">{business.name}</h3>
          <div className="flex items-center space-x-2 mb-2 flex-wrap">
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
              {cleanCategoryName(business.category)}
            </span>
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              {cleanAreaName(business.area)}
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
              className="flex items-center space-x-2 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white shadow-lg hover:shadow-xl transform transition-all duration-200 hover:-translate-y-0.5"
            >
              ⬇️ <span>Export CSV</span>
            </Button>
          </div>
        </div>

        {/* Filters */}
        <Card className="filter-section mb-8 p-8 shadow-lg rounded-2xl">
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-bold text-gray-900 mb-2">Filter Bisnis</h3>
              <p className="text-sm text-gray-600">Saring bisnis berdasarkan kriteria yang diinginkan</p>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">
                  Cari Bisnis
                </label>
                <Input
                  type="text"
                  placeholder="Nama bisnis atau alamat..."
                  value={searchTerm}
                  onChange={(e: ChangeEvent<HTMLInputElement>) =>
                    setSearchTerm(e.target.value)
                  }
                  data-testid="search-input"
                  className="w-full"
                />
              </div>
              
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">Area</label>
                <Select
                  value={filters.area || undefined}
                  onValueChange={(value) => setFilters({ ...filters, area: value || "" })}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Pilih area" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Area</SelectItem>
                    {filterOptions.areas.map((area) => (
                      <SelectItem key={area} value={area}>
                        {area}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">
                  Kategori
                </label>
                <Select
                  value={filters.category || undefined}
                  onValueChange={(value) => setFilters({ ...filters, category: value || "" })}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Pilih kategori" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Semua Kategori</SelectItem>
                    {filterOptions.categories.map((category) => (
                      <SelectItem key={category} value={category}>
                        {category}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              
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
                    <SelectItem value="ultra_new">Data Baru (kurang dari 1 minggu)</SelectItem>
                    <SelectItem value="very_new">Data Baru (kurang dari 1 bulan)</SelectItem>
                    <SelectItem value="new">Data Baru (kurang dari 3 bulan)</SelectItem>
                    <SelectItem value="recent">Data Recent (kurang dari 12 bulan)</SelectItem>
                    <SelectItem value="established">Data Established (1-3 tahun)</SelectItem>
                    <SelectItem value="old">Data Lama (lebih dari 3 tahun)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">Radius Pencarian</label>
                <div className="space-y-2">
                  <div className="flex items-center space-x-2">
                    <input
                      type="checkbox"
                      id="useRadius"
                      checked={useRadiusFilter}
                      onChange={(e) => setUseRadiusFilter(e.target.checked)}
                      className="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <label htmlFor="useRadius" className="text-sm text-gray-700">
                      Gunakan Filter Radius
                    </label>
                  </div>
                  {useRadiusFilter && (
                    <>
                      <input
                        type="range"
                        min="1000"
                        max="50000"
                        step="1000"
                        value={filters.radius}
                        onChange={(e) => setFilters({ ...filters, radius: parseInt(e.target.value) })}
                        className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                      />
                      <div className="flex justify-between text-xs text-gray-600">
                        <span>1km</span>
                        <span className="font-medium">{Math.round(filters.radius / 1000)}km</span>
                        <span>50km</span>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>
            
            <div className="flex items-center justify-between pt-4 border-t border-gray-200">
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span className="text-sm font-medium text-gray-700">
                  Menampilkan {filteredBusinesses.length} dari {businesses.length} bisnis
                </span>
              </div>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setFilters({ area: "", category: "", data_age: "", radius: 5000, center_lat: -8.6500, center_lng: 115.2167 });
                  setUseRadiusFilter(false);
                  setSearchTerm("");
                }}
                className="text-gray-600 border-gray-300 hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 font-medium"
              >
                🔄 Reset Filter
              </Button>
            </div>
          </div>
        </Card>

        {/* Business List */}
        {loading && businesses.length === 0 ? (
          <p>Memuat data...</p>
        ) : filteredBusinesses.length > 0 ? (
          <>
            <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8 auto-rows-fr">
              {filteredBusinesses.map((business) => (
                <BusinessCard key={business.id} business={business} />
              ))}
            </div>

            {pagination.hasMore && !loading && (
              <div className="text-center">
                <Button onClick={loadMore} variant="outline" className="px-8 py-3">
                  Muat Lebih Banyak
                </Button>
              </div>
            )}
          </>
        ) : (
          <div className="text-center py-12">Tidak Ada Bisnis Ditemukan</div>
        )}
      </div>
    </Layout>
  );
};

export default BusinessList;
