import React, { useState, useEffect, useContext } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import toast from "react-hot-toast";
import { Card } from "../components/ui/card";
import { Button } from "../components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../components/ui/select";
import Layout from "../components/Layout";
import GoogleMapsHeatmap from "../components/GoogleMapsHeatmap";
import { router } from '@inertiajs/react';

// Type definitions
interface TrendData {
  period: string;
  count: number;
  category?: string;
  area?: string;
}

interface TopBusiness {
  id: number;
  name: string;
  category: string;
  area: string;
  rating: number;
  review_count: number;
  first_seen: string;
  indicators?: any;
}

interface StatisticsData {
  weekly_trends: TrendData[];
  monthly_trends: TrendData[];
  category_trends: TrendData[];
  area_trends: TrendData[];
  top_businesses: TopBusiness[];
  total_businesses: number;
  new_this_week: number;
  new_this_month: number;
  growth_rate: number;
}

interface HeatmapBusiness {
  lat: number;
  lng: number;
  name: string;
  category: string;
  area: string;
  review_count: number;
  rating: number;
}

const Statistics: React.FC = () => {
  const { API } = useContext(AuthContext);
  const [stats, setStats] = useState<StatisticsData | null>(null);
  const [heatmapData, setHeatmapData] = useState<HeatmapBusiness[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [heatmapLoading, setHeatmapLoading] = useState<boolean>(true);
  const [selectedPeriod, setSelectedPeriod] = useState<'weekly' | 'monthly'>('weekly');
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [selectedArea, setSelectedArea] = useState<string>('all');
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
    fetchStatistics();
    fetchHeatmapData();
  }, [selectedPeriod, selectedCategory, selectedArea]);

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

  const fetchStatistics = async (): Promise<void> => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        period: selectedPeriod,
        category: selectedCategory,
        area: selectedArea,
      });

      const response = await axios.get<StatisticsData>(`${API}/statistics?${params}`);
      setStats(response.data);
    } catch (error: any) {
      console.error('Error fetching statistics:', error);
      toast.error("Gagal memuat data statistik");
    } finally {
      setLoading(false);
    }
  };

  const fetchHeatmapData = async (): Promise<void> => {
    try {
      setHeatmapLoading(true);
      const params = new URLSearchParams({
        period: selectedPeriod,
        category: selectedCategory,
        area: selectedArea,
      });

      const response = await axios.get<{ businesses: HeatmapBusiness[] }>(`${API}/statistics/heatmap?${params}`);
      setHeatmapData(response.data.businesses);
    } catch (error: any) {
      console.error('Error fetching heatmap data:', error);
      toast.error("Gagal memuat data heatmap");
    } finally {
      setHeatmapLoading(false);
    }
  };

  const StatCard: React.FC<{ title: string; value: string | number; icon: React.ReactNode; color: string; growth?: number }> = ({ title, value, icon, color, growth }) => (
    <Card className="p-6 bg-white border-0 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 rounded-2xl">
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-gray-600 mb-2 truncate">{title}</p>
          <p className="text-2xl font-bold text-gray-900 mb-3 truncate">{value}</p>
          {growth !== undefined && (
            <div className="flex items-center">
              <div className={`flex items-center text-sm font-medium ${growth >= 0 ? 'text-emerald-600' : 'text-red-500'}`}>
                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d={growth >= 0 ? "M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" : "M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"}
                  />
                </svg>
                <span>{growth >= 0 ? '+' : ''}{growth}%</span>
              </div>
              <span className="text-gray-500 text-xs ml-2">vs periode sebelumnya</span>
            </div>
          )}
        </div>
        <div className={`w-12 h-12 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0 ml-2 ${color}`}>
          {icon}
        </div>
      </div>
    </Card>
  );

  const TrendChart: React.FC<{ data: TrendData[]; title: string }> = ({ data, title }) => {
    const maxValue = Math.max(...data.map(d => d.count));
    
    return (
      <Card className="p-6 bg-white border-0 shadow-lg rounded-2xl">
        <h3 className="text-lg font-bold text-gray-900 mb-4">{title}</h3>
        <div className="space-y-3">
          {data.map((item, index) => (
            <div key={index} className="flex items-center justify-between">
              <div className="flex-1">
                <div className="flex items-center justify-between mb-1">
                  <span className="text-sm font-medium text-gray-700">{item.period}</span>
                  <span className="text-sm font-bold text-gray-900">{item.count}</span>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div 
                    className="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full transition-all duration-500"
                    style={{ width: `${(item.count / maxValue) * 100}%` }}
                  ></div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </Card>
    );
  };

  const TopBusinessCard: React.FC<{ business: TopBusiness; rank: number }> = ({ business, rank }) => (
    <div className="flex items-center space-x-4 p-4 bg-gradient-to-r from-white to-blue-50/50 border border-blue-100 rounded-xl hover:shadow-md transition-all duration-200">
      <div className="flex-shrink-0">
        <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm ${
          rank === 1 ? 'bg-yellow-500' : 
          rank === 2 ? 'bg-gray-400' : 
          rank === 3 ? 'bg-orange-500' : 
          'bg-blue-500'
        }`}>
          {rank}
        </div>
      </div>
      <div className="flex-1 min-w-0">
        <h4 className="font-semibold text-gray-900 truncate">{business.name}</h4>
        <p className="text-sm text-gray-600 truncate">{business.category} • {business.area}</p>
        <div className="flex items-center space-x-4 text-sm mt-1">
          <span className="flex items-center text-amber-600 font-medium">
            <svg className="w-4 h-4 mr-1 fill-current" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            {business.rating}
          </span>
          <span className="text-gray-500">{business.review_count} review</span>
          <span className="text-gray-500 text-xs">
            {new Date(business.first_seen).toLocaleDateString('id-ID')}
          </span>
        </div>
      </div>
    </div>
  );

  if (loading) {
    return (
      <Layout>
        <div className="max-w-8xl mx-auto p-6">
          <div className="mb-8">
            <div className="h-8 bg-gray-200 rounded-lg w-1/3 mb-3 animate-pulse"></div>
            <div className="h-4 bg-gray-200 rounded w-2/3 animate-pulse"></div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {[1, 2, 3, 4].map((i) => (
              <Card key={i} className="p-6 animate-pulse rounded-2xl shadow-lg">
                <div className="h-4 bg-gray-200 rounded mb-4"></div>
                <div className="h-8 bg-gray-200 rounded mb-3"></div>
                <div className="h-3 bg-gray-200 rounded w-2/3"></div>
              </Card>
            ))}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <Card className="p-6 animate-pulse rounded-2xl shadow-lg">
              <div className="h-6 bg-gray-200 rounded w-1/2 mb-6"></div>
              <div className="space-y-4">
                {[1, 2, 3, 4, 5].map((i) => (
                  <div key={i} className="h-3 bg-gray-200 rounded"></div>
                ))}
              </div>
            </Card>
            <Card className="p-6 animate-pulse rounded-2xl shadow-lg">
              <div className="h-6 bg-gray-200 rounded w-1/2 mb-6"></div>
              <div className="space-y-4">
                {[1, 2, 3, 4, 5].map((i) => (
                  <div key={i} className="h-16 bg-gray-200 rounded"></div>
                ))}
              </div>
            </Card>
          </div>
        </div>
      </Layout>
    );
  }

  if (!stats) {
    return (
      <Layout>
        <div className="p-8 min-h-screen">
          <div className="text-center max-w-md mx-auto mt-20">
            <div className="w-24 h-24 bg-gradient-to-br from-blue-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
              <svg className="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 className="text-2xl font-bold text-gray-800 mb-4">
              Belum Ada Data Statistik
            </h3>
            <p className="text-gray-600 mb-8 leading-relaxed">
              Data statistik akan tersedia setelah ada data bisnis yang cukup untuk dianalisis.
            </p>
            <Button
              onClick={fetchStatistics}
              className="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-8 py-3 rounded-xl shadow-lg hover:shadow-xl transform transition-all duration-200 hover:-translate-y-0.5"
            >
              🔄 Refresh Data
            </Button>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="max-w-8xl mx-auto p-6">
        {/* Header Section */}
        <div className="mb-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 mb-2">
                Statistik & Tren Bisnis
              </h1>
              <p className="text-gray-600 text-lg">
                Analisis mendalam pertumbuhan bisnis baru di Bali
              </p>
            </div>
            <div className="flex space-x-2">
              <Button
                onClick={fetchStatistics}
                variant="outline"
                className="text-blue-600 border-blue-200 hover:bg-blue-50"
              >
                🔄 Refresh
              </Button>
            </div>
          </div>
        </div>

        {/* Filter Section */}
        <Card className="bg-gradient-to-r from-white to-blue-50/30 border-0 shadow-lg rounded-2xl mb-8">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-900 mb-1">Filter Statistik</h3>
                <p className="text-sm text-gray-600">Saring data berdasarkan periode, kategori, dan area</p>
              </div>
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                <span className="text-sm font-medium text-gray-700">
                  Data Real-time
                </span>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">Periode</label>
                <div className="flex bg-gray-100 rounded-xl p-1">
                  <button 
                    onClick={() => setSelectedPeriod('weekly')}
                    className={`px-4 py-2 text-sm font-medium rounded-lg shadow-sm transition-all duration-200 ${
                      selectedPeriod === 'weekly' 
                        ? 'text-white bg-blue-600 shadow-md' 
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                    }`}
                  >
                    📊 Mingguan
                  </button>
                  <button 
                    onClick={() => setSelectedPeriod('monthly')}
                    className={`px-4 py-2 text-sm font-medium rounded-lg shadow-sm transition-all duration-200 ${
                      selectedPeriod === 'monthly' 
                        ? 'text-white bg-blue-600 shadow-md' 
                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                    }`}
                  >
                    📅 Bulanan
                  </button>
                </div>
              </div>
              
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-gray-800">Kategori</label>
                <Select
                  value={selectedCategory || undefined}
                  onValueChange={(value) => setSelectedCategory(value || "")}
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
                <label className="block text-sm font-semibold text-gray-800">Area</label>
                <Select
                  value={selectedArea || undefined}
                  onValueChange={(value) => setSelectedArea(value || "")}
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
            </div>
          </div>
        </Card>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <StatCard
            title="Total Bisnis"
            value={stats.total_businesses.toLocaleString()}
            color="bg-gradient-to-br from-blue-500 to-blue-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            }
          />
          <StatCard
            title="Baru Minggu Ini"
            value={stats.new_this_week}
            color="bg-gradient-to-br from-emerald-500 to-green-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            }
          />
          <StatCard
            title="Baru Bulan Ini"
            value={stats.new_this_month}
            color="bg-gradient-to-br from-purple-500 to-purple-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            }
          />
          <StatCard
            title="Tingkat Pertumbuhan"
            value={`${stats.growth_rate >= 0 ? '+' : ''}${stats.growth_rate}%`}
            growth={stats.growth_rate}
            color="bg-gradient-to-br from-orange-500 to-orange-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            }
          />
        </div>

        {/* Charts Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <TrendChart 
            data={selectedPeriod === 'weekly' ? stats.weekly_trends : stats.monthly_trends}
            title={`Tren ${selectedPeriod === 'weekly' ? 'Mingguan' : 'Bulanan'}`}
          />
          <TrendChart 
            data={stats.category_trends}
            title="Tren per Kategori"
          />
        </div>

        {/* Heatmap Section */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl mb-8">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-bold text-gray-900">Heatmap Lokasi Bisnis Baru</h3>
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span className="text-sm font-medium text-gray-700">
                  {heatmapData.length} bisnis ditampilkan
                </span>
              </div>
            </div>
            
            {heatmapLoading ? (
              <div className="h-96 flex items-center justify-center bg-gray-100 rounded-lg">
                <div className="text-center">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                  <p className="text-gray-600">Loading heatmap...</p>
                </div>
              </div>
            ) : (
              <GoogleMapsHeatmap 
                businesses={heatmapData}
                height="500px"
                center={{ lat: -8.6500, lng: 115.2167 }}
                zoom={11}
              />
            )}
          </div>
        </Card>

        {/* Top Businesses Section */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-xl font-bold text-gray-900">Top 10 Bisnis Baru dengan Review Terbanyak</h3>
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span className="text-sm font-medium text-gray-700">
                  Berdasarkan jumlah review bulan ini
                </span>
              </div>
            </div>
            
            <div className="space-y-3">
              {stats.top_businesses.map((business, index) => (
                <TopBusinessCard 
                  key={business.id} 
                  business={business} 
                  rank={index + 1} 
                />
              ))}
            </div>
          </div>
        </Card>
      </div>
    </Layout>
  );
};

export default Statistics;
