import React, { useState, useEffect, useContext, useCallback } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import toast from "react-hot-toast";
import { Card } from "../components/ui/card";
import { Button } from "../components/ui/button";
import Layout from "../components/Layout";
import { router } from '@inertiajs/react';
import { cleanAreaName } from "../lib/areaUtils";

// Type definitions
interface Business {
  id: string | number;
  name: string;
  category: string;
  area: string;
  address: string;
  rating: number;
  review_count: number;
  recently_opened?: boolean;
  review_spike?: boolean;
  few_reviews?: boolean;
  has_recent_photo?: boolean;
  first_seen?: string;
}

interface DashboardStats {
  total_new_businesses: number;
  weekly_growth: number;
  growth_rate: number;
  top_category: string;
  top_area: string;
  recently_opened_count: number;
  trending_count: number;
  recent_businesses: Business[];
}

interface StatCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  color: string;
  growth?: number;
}

interface AuthContextType {
  API: string;
}

const Dashboard: React.FC = () => {
  const { API } = useContext(AuthContext) as AuthContextType;
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [notificationEmail, setNotificationEmail] = useState<string>('');
  const [notificationFrequency, setNotificationFrequency] = useState<'weekly' | 'monthly'>('weekly');
  const [sendingNotification, setSendingNotification] = useState<boolean>(false);

  // Display area helper: prefer specific kabupaten/kota parsed from address when area is generic "Bali"
  const getDisplayArea = (business: Business) => {
    const areaName = cleanAreaName(business.area);
    if (areaName === 'Bali' && business.address) {
      const addr = business.address.toLowerCase();
      // Try "Kabupaten X" first
      const kabMatch = addr.match(/kabupaten\s+([a-z\s]+?)(,|$)/i);
      if (kabMatch && kabMatch[1]) {
        const kab = kabMatch[1].trim().replace(/\b\w/g, (l: string) => l.toUpperCase());
        return `Kabupaten ${kab}`;
      }
      // Try "Kota X"
      const kotaMatch = addr.match(/kota\s+([a-z\s]+?)(,|$)/i);
      if (kotaMatch && kotaMatch[1]) {
        const kota = kotaMatch[1].trim().replace(/\b\w/g, (l: string) => l.toUpperCase());
        return `Kota ${kota}`;
      }
      // Try "Badung Regency" -> "Kabupaten Badung"
      const regencyMatch = addr.match(/([a-z\s]+?)\s+regency/i);
      if (regencyMatch && regencyMatch[1]) {
        const reg = regencyMatch[1].trim().replace(/\b\w/g, (l: string) => l.toUpperCase());
        return `Kabupaten ${reg}`;
      }
    }
    return areaName;
  };

  const fetchDashboardStats = useCallback(async (): Promise<void> => {
    try {
      setLoading(true);
      const response = await axios.get<DashboardStats>(`${API}/dashboard/stats`);
      setStats(response.data);
    } catch (error: unknown) {
      console.error('Error fetching stats:', error);
      // If no data exists, offer to initialize
      if (error && typeof error === 'object' && 'response' in error && 
          error.response && typeof error.response === 'object' && 'status' in error.response &&
          error.response.status === 500) {
        setStats(null);
      } else {
        toast.error("Gagal memuat data dashboard");
      }
    } finally {
      setLoading(false);
    }
  }, [API]);

  useEffect(() => {
    fetchDashboardStats();
  }, [fetchDashboardStats]);

  const sendNotification = async (type: 'weekly' | 'monthly'): Promise<void> => {
    if (!notificationEmail) {
      toast.error("Please enter email address");
      return;
    }

    try {
      setSendingNotification(true);
      const endpoint = type === 'weekly' 
        ? `${API}/notifications/weekly-summary`
        : `${API}/notifications/monthly-summary`;
      
      await axios.post(endpoint, { email: notificationEmail });
      toast.success(`${type === 'weekly' ? 'Weekly' : 'Monthly'} summary sent successfully!`);
    } catch (error) {
      console.error(`Failed to send ${type} summary:`, error);
      toast.error(`Failed to send ${type} summary`);
    } finally {
      setSendingNotification(false);
    }
  };

  const scheduleNotifications = async (): Promise<void> => {
    if (!notificationEmail) {
      toast.error("Please enter email address");
      return;
    }

    try {
      setSendingNotification(true);
      await axios.post(`${API}/notifications/schedule`, {
        email: notificationEmail,
        frequency: notificationFrequency
      });
      toast.success(`Notifications scheduled for ${notificationFrequency} delivery`);
    } catch (error) {
      console.error("Failed to schedule notifications:", error);
      toast.error("Failed to schedule notifications");
    } finally {
      setSendingNotification(false);
    }
  };

  const StatCard: React.FC<StatCardProps> = ({ title, value, icon, color, growth }) => (
    <Card className="p-4 sm:p-6 bg-white border-0 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 rounded-2xl"
      data-testid={`stat-${title.toLowerCase().replace(/\s+/g, '-')}`}>
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <p className="text-xs sm:text-sm font-medium text-gray-600 mb-2 truncate">{title}</p>
          <p className="text-xl sm:text-2xl font-bold text-gray-900 mb-3 truncate">{value}</p>
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
                <span>{growth >= 0 ? '+' : ''}{growth}</span>
              </div>
              <span className="text-gray-500 text-xs ml-2">vs minggu lalu</span>
            </div>
          )}
        </div>
        <div className={`w-10 h-10 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center shadow-lg flex-shrink-0 ml-2 ${color}`}>
          {icon}
        </div>
      </div>
    </Card>
  );

  if (loading) {
    return (
      <Layout>
        <div className="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto">
          <div className="mb-6 sm:mb-8">
            <div className="h-6 sm:h-8 bg-gray-200 rounded-lg w-1/2 sm:w-1/3 mb-3 animate-pulse"></div>
            <div className="h-3 sm:h-4 bg-gray-200 rounded w-2/3 sm:w-1/2 animate-pulse"></div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
            {[1, 2, 3, 4].map((i) => (
              <Card key={i} className="p-4 sm:p-6 animate-pulse rounded-2xl shadow-lg">
                <div className="h-3 sm:h-4 bg-gray-200 rounded mb-3 sm:mb-4"></div>
                <div className="h-6 sm:h-8 bg-gray-200 rounded mb-2 sm:mb-3"></div>
                <div className="h-2 sm:h-3 bg-gray-200 rounded w-2/3"></div>
              </Card>
            ))}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
            <Card className="p-4 sm:p-6 animate-pulse rounded-2xl shadow-lg">
              <div className="h-5 sm:h-6 bg-gray-200 rounded w-1/2 sm:w-1/3 mb-4 sm:mb-6"></div>
              <div className="space-y-3 sm:space-y-4">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="p-3 sm:p-4 bg-gray-100 rounded-lg">
                    <div className="h-3 sm:h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div className="h-2 sm:h-3 bg-gray-200 rounded w-1/2"></div>
                  </div>
                ))}
              </div>
            </Card>

            <Card className="p-4 sm:p-6 animate-pulse rounded-2xl shadow-lg">
              <div className="h-5 sm:h-6 bg-gray-200 rounded w-1/2 sm:w-1/3 mb-4 sm:mb-6"></div>
              <div className="h-64 sm:h-80 bg-gray-200 rounded-lg"></div>
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
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <h3 className="text-2xl font-bold text-gray-800 mb-4">
              Loading Data...
            </h3>
            <p className="text-gray-600 mb-8 leading-relaxed">
              Data bisnis akan muncul setelah sistem mengambil data dari Google Places API.
            </p>
            <Button
              onClick={fetchDashboardStats}
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
        <div className="mb-6 sm:mb-8">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">
            Dashboard Monitoring Bisnis
          </h1>
              <p className="text-gray-600 text-base sm:text-lg">
            Pantau pertumbuhan bisnis baru di Bali secara real-time
          </p>
            </div>
            <div className="flex space-x-2">
              <Button
                onClick={fetchDashboardStats}
                variant="outline"
                className="text-blue-600 border-blue-200 hover:bg-blue-50"
              >
                🔄 Refresh
              </Button>
            </div>
          </div>
        </div>

        {/* Stats Cards - Row 1 */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
          <StatCard
            title="Total Bisnis Baru"
            value={stats.total_new_businesses?.toLocaleString() || '0'}
            growth={stats.weekly_growth}
            color="bg-gradient-to-br from-blue-500 to-blue-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            }
          />
          <StatCard
            title="Pertumbuhan Mingguan"
            value={`${(stats.weekly_growth || 0) > 0 ? '+' : ''}${stats.weekly_growth || 0}`}
            growth={stats.growth_rate || 0}
            color="bg-gradient-to-br from-emerald-500 to-green-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            }
          />
          <StatCard
            title="Kategori Terpopuler"
            value={stats.top_category || 'N/A'}
            color="bg-gradient-to-br from-purple-500 to-purple-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            }
          />
        </div>

        {/* Stats Cards - Row 2 */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
          <StatCard
            title="Area Terpopuler"
            value={stats.top_area || 'N/A'}
            color="bg-gradient-to-br from-orange-500 to-orange-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            }
          />
          <StatCard
            title="Recently Opened"
            value={stats.recently_opened_count || 0}
            color="bg-gradient-to-br from-green-500 to-green-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            }
          />
          <StatCard
            title="Trending"
            value={stats.trending_count || 0}
            color="bg-gradient-to-br from-red-500 to-red-600"
            icon={
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            }
          />
        </div>

        {/* Quick Actions */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl mb-8">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-900 mb-1">Aksi Cepat</h3>
                <p className="text-sm text-gray-600">Akses fitur utama dashboard dengan mudah</p>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Button
                onClick={() => router.visit('/businesslist')}
                className="h-16 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-left justify-start"
              >
                <div className="flex items-center space-x-3">
                  <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    📊
                  </div>
                  <div>
                    <div className="font-semibold">Daftar Bisnis</div>
                    <div className="text-sm opacity-90">Lihat semua bisnis baru</div>
                  </div>
              </div>
              </Button>
              
              <Button
                onClick={() => router.visit('/statistics')}
                className="h-16 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white text-left justify-start"
              >
                <div className="flex items-center space-x-3">
                  <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    📈
                  </div>
                  <div>
                    <div className="font-semibold">Statistik & Tren</div>
                    <div className="text-sm opacity-90">Analisis mendalam</div>
                  </div>
              </div>
              </Button>
              
              <Button
                onClick={() => window.open('/api/export/csv', '_blank')}
                className="h-16 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white text-left justify-start"
              >
                <div className="flex items-center space-x-3">
                  <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    📥
                  </div>
                  <div>
                    <div className="font-semibold">Export Data</div>
                    <div className="text-sm opacity-90">Download CSV</div>
                  </div>
              </div>
              </Button>
              </div>
            </div>
        </Card>

        {/* Trend Chart Section */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl mb-8">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-900 mb-1">Tren Pertumbuhan Bisnis</h3>
                <p className="text-sm text-gray-600">Grafik pertumbuhan bisnis baru per minggu</p>
              </div>
              <div className="flex space-x-2">
                <Button
                  variant="outline"
                  size="sm"
                  className="text-blue-600 border-blue-200 hover:bg-blue-50"
                >
                  📊 Mingguan
                </Button>
              <Button
                variant="outline"
                size="sm"
                  className="text-gray-600 border-gray-200 hover:bg-gray-50"
                >
                  📅 Bulanan
                </Button>
              </div>
            </div>
            
            {/* Simple Trend Visualization */}
            <div className="h-64 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl flex items-center justify-center">
              <div className="text-center">
                <div className="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                  📈
                </div>
                <h4 className="text-lg font-semibold text-gray-700 mb-2">Grafik Tren</h4>
                <p className="text-sm text-gray-500 mb-4">Visualisasi tren pertumbuhan bisnis</p>
                <Button
                  onClick={() => router.visit('/statistics')}
                  className="bg-blue-600 hover:bg-blue-700 text-white"
                >
                  Lihat Grafik Lengkap
              </Button>
              </div>
            </div>
          </div>
        </Card>

        {/* Top Businesses Section */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl mb-8">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-900 mb-1">Top 10 Bisnis dengan Review Terbanyak</h3>
                <p className="text-sm text-gray-600">Bisnis paling populer bulan ini</p>
              </div>
              <Button
                onClick={() => router.visit('/businesslist')}
                variant="outline"
                size="sm"
                className="text-blue-600 border-blue-200 hover:bg-blue-50"
              >
                Lihat Semua
              </Button>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {(stats.recent_businesses || [])
                .sort((a, b) => b.review_count - a.review_count)
                .slice(0, 6)
                .map((business: Business, index: number) => (
                  <div
                    key={business.id}
                    className="p-4 bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl hover:shadow-md transition-all duration-200"
                  >
                    <div className="flex items-start justify-between mb-3">
                      <div className="flex items-center space-x-2">
                        <div className="w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs font-bold">
                          {index + 1}
                        </div>
                        <h4 className="font-semibold text-gray-900 text-sm">{business.name}</h4>
                      </div>
                    </div>
                    <p className="text-xs text-gray-600 mb-2">{business.category} • {getDisplayArea(business)}</p>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-1 text-amber-600">
                        <svg className="w-3 h-3 fill-current" viewBox="0 0 20 20">
                          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                        </svg>
                        <span className="text-xs font-medium">{business.rating}</span>
                      </div>
                      <span className="text-xs font-bold text-yellow-600">{business.review_count} review</span>
                    </div>
                  </div>
                ))}
            </div>
          </div>
        </Card>

        {/* Content Grid */}
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 sm:gap-8">
          {/* Recent Businesses Card */}
          <Card className="bg-white border-0 shadow-lg rounded-2xl overflow-hidden">
            <div className="p-4 sm:p-6">
              <div className="flex items-center justify-between mb-4 sm:mb-6">
                <h3 className="text-lg sm:text-xl font-bold text-gray-900">Bisnis Terbaru</h3>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => router.visit('/businesslist')}
                    className="text-blue-600 border-blue-200 hover:bg-blue-50"
                  >
                    Lihat Semua
                  </Button>
              </div>
              <div className="space-y-3 sm:space-y-4">
                {(stats.recent_businesses || []).slice(0, 5).map((business: Business) => (
                  <div
                    key={business.id}
                    className="p-3 sm:p-4 bg-gradient-to-r from-white to-blue-50/50 border border-blue-100 rounded-xl hover:shadow-md transition-all duration-200 hover:-translate-y-0.5"
                    data-testid={`recent-business-${business.name}`}
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h4 className="font-semibold text-gray-900 mb-1 text-base">{business.name}</h4>
                        <p className="text-sm text-gray-600 mb-3">{business.category} • {getDisplayArea(business)}</p>
                        <div className="flex items-center space-x-4 text-sm">
                          <span className="flex items-center text-amber-600 font-medium">
                            <svg className="w-4 h-4 mr-1 fill-current" viewBox="0 0 20 20">
                              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                            {business.rating}
                          </span>
                          <span className="text-gray-500">{business.review_count} review</span>
                        </div>
                      </div>
                      <div className="flex flex-col space-y-1">
                        {/* Status Indikator */}
                        <div className="flex flex-col space-y-1">
                        {business.recently_opened && (
                            <span className="px-2 py-1 bg-emerald-100 text-emerald-700 text-xs font-medium rounded-full flex items-center">
                              ✅ Recently Opened
                          </span>
                        )}
                        {business.review_spike && (
                            <span className="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-medium rounded-full flex items-center">
                              📈 Lonjakan Review
                            </span>
                          )}
                          {business.few_reviews && (
                            <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-full flex items-center">
                              📝 Review Sedikit
                            </span>
                          )}
                          {business.has_recent_photo && (
                            <span className="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-medium rounded-full flex items-center">
                              📸 Foto Recent
                          </span>
                        )}
                        </div>
                        
                        {/* Tanggal Muncul */}
                        <div className="text-xs text-gray-500 mt-2">
                          Muncul: {business.first_seen ? new Date(business.first_seen).toLocaleDateString('id-ID') : 'N/A'}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </Card>

          {/* Status Indikator Card */}
          <Card className="bg-white border-0 shadow-lg rounded-2xl overflow-hidden">
            <div className="p-4 sm:p-6">
              <h3 className="text-lg sm:text-xl font-bold text-gray-900 mb-4 sm:mb-6">Status Indikator Bisnis</h3>
              <div className="space-y-4">
                {/* Recently Opened */}
                <div className="flex items-center justify-between p-3 bg-emerald-50 rounded-lg border border-emerald-200">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center">
                      <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Recently Opened</p>
                      <p className="text-sm text-gray-600">Bisnis dengan label "Baru Dibuka"</p>
                    </div>
                  </div>
                  <span className="text-lg font-bold text-emerald-600">
                    {stats.recently_opened_count || 0}
                  </span>
                </div>

                {/* Review Spike */}
                <div className="flex items-center justify-between p-3 bg-orange-50 rounded-lg border border-orange-200">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center">
                      <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Lonjakan Review</p>
                      <p className="text-sm text-gray-600">Bisnis dengan peningkatan review drastis</p>
                    </div>
                  </div>
                  <span className="text-lg font-bold text-orange-600">
                    {stats.trending_count || 0}
                  </span>
                </div>

                {/* Foto Recent */}
                <div className="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-200">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                      <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Foto Recent</p>
                      <p className="text-sm text-gray-600">Bisnis dengan foto upload &lt;3 bulan</p>
                    </div>
                  </div>
                  <span className="text-lg font-bold text-blue-600">
                    {(stats.recent_businesses || []).filter(b => b.has_recent_photo).length}
                  </span>
                </div>

                {/* Review Sedikit */}
                <div className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                  <div className="flex items-center space-x-3">
                    <div className="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                      <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    </div>
                    <div>
                      <p className="font-medium text-gray-900">Review Sedikit</p>
                      <p className="text-sm text-gray-600">Bisnis dengan &lt;10 review</p>
                    </div>
                  </div>
                  <span className="text-lg font-bold text-gray-600">
                    {(stats.recent_businesses || []).filter(b => b.few_reviews).length}
                  </span>
                </div>
              </div>
            </div>
          </Card>
        </div>

        {/* Notification Section */}
        <Card className="bg-white border-0 shadow-lg rounded-2xl">
          <div className="p-6">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h3 className="text-xl font-bold text-gray-900 mb-1">Email Notifications</h3>
                <p className="text-sm text-gray-600">Get automated summaries of new businesses</p>
              </div>
              <div className="flex items-center space-x-2">
                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                <span className="text-sm font-medium text-gray-700">
                  Automated Reports Available
                </span>
              </div>
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-800 mb-2">Email Address</label>
                  <input
                    type="email"
                    placeholder="admin@example.com"
                    value={notificationEmail}
                    onChange={(e) => setNotificationEmail(e.target.value)}
                    className="w-full p-3 text-sm border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white shadow-sm transition-all duration-200 hover:border-gray-400"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-semibold text-gray-800 mb-2">Frequency</label>
                  <div className="flex bg-gray-100 rounded-lg p-1">
                    <button 
                      onClick={() => setNotificationFrequency('weekly')}
                      className={`px-4 py-2 text-sm font-medium rounded-md shadow-sm transition-all duration-200 ${
                        notificationFrequency === 'weekly' 
                          ? 'text-white bg-blue-600' 
                          : 'text-gray-600 hover:text-gray-900'
                      }`}
                    >
                      Weekly
                    </button>
                    <button 
                      onClick={() => setNotificationFrequency('monthly')}
                      className={`px-4 py-2 text-sm font-medium rounded-md shadow-sm transition-all duration-200 ${
                        notificationFrequency === 'monthly' 
                          ? 'text-white bg-blue-600' 
                          : 'text-gray-600 hover:text-gray-900'
                      }`}
                    >
                      Monthly
                    </button>
                  </div>
                </div>
              </div>
              
              <div className="space-y-3">
                <Button
                  onClick={() => sendNotification('weekly')}
                  disabled={sendingNotification || !notificationEmail}
                  className="w-full bg-green-600 hover:bg-green-700 text-white"
                >
                  {sendingNotification ? (
                    <div className="flex items-center space-x-2">
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      <span>Sending...</span>
                    </div>
                  ) : (
                    "📧 Send Weekly Summary"
                  )}
                </Button>
                
                <Button
                  onClick={() => sendNotification('monthly')}
                  disabled={sendingNotification || !notificationEmail}
                  className="w-full bg-purple-600 hover:bg-purple-700 text-white"
                >
                  {sendingNotification ? (
                    <div className="flex items-center space-x-2">
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                      <span>Sending...</span>
                    </div>
                  ) : (
                    "📅 Send Monthly Summary"
                  )}
                </Button>
                
                <Button
                  onClick={scheduleNotifications}
                  disabled={sendingNotification || !notificationEmail}
                  variant="outline"
                  className="w-full text-blue-600 border-blue-200 hover:bg-blue-50"
                >
                  {sendingNotification ? (
                    <div className="flex items-center space-x-2">
                      <div className="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                      <span>Scheduling...</span>
                    </div>
                  ) : (
                    `⏰ Schedule ${notificationFrequency} Notifications`
                  )}
                </Button>
              </div>
            </div>
            
            <div className="mt-6 pt-4 border-t border-gray-200">
              <div className="flex items-center space-x-2 text-sm text-gray-600">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                  Notifications include CSV export, top businesses, growth statistics, and trending categories.
                </span>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </Layout>
  );
};

export default Dashboard;