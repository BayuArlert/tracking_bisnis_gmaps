import React, { useState, useEffect, useContext, useCallback } from "react";
import { AuthContext } from "../context/AuthContext";
import axios from "axios";
import toast from "react-hot-toast";
import { Card } from "../components/ui/card";
import Layout from "../components/Layout";
import HotZonesList from "../components/HotZonesList";
import CategoryBreakdown from "../components/CategoryBreakdown";
import GrowthMetrics from "../components/GrowthMetrics";
import LineChart from "../components/LineChart";
import HierarchicalLocationFilter from "../components/HierarchicalLocationFilter";
import { cleanAreaName } from "../lib/areaUtils";

interface AnalyticsSummary {
  total_businesses: number;
  new_businesses: number;
  recently_opened: number;
  high_confidence_new: number;
  growth_rate: number;
  categories_breakdown: CategoryData[];
  areas_breakdown: AreaData[];
}

interface CategoryData {
  category: string;
  total: number;
  new_count: number;
  avg_confidence: number;
}

interface AreaData {
  area: string;
  total: number;
  new_count: number;
  avg_confidence: number;
}

interface HotZone {
  area: string;
  total_businesses: number;
  new_businesses: number;
  avg_confidence: number;
  avg_lat: number;
  avg_lng: number;
}

interface TrendDataPoint {
  period: string;
  [key: string]: string | number;
}

const Analytics: React.FC = () => {
  const { API } = useContext(AuthContext);
  const [summary, setSummary] = useState<AnalyticsSummary | null>(null);
  const [hotZones, setHotZones] = useState<HotZone[]>([]);
  const [categoryTrends, setCategoryTrends] = useState<{ categories: string[], trends: TrendDataPoint[] }>({ categories: [], trends: [] });
  const [kecamatanTrends, setKecamatanTrends] = useState<{ kecamatan: string[], trends: TrendDataPoint[] }>({ kecamatan: [], trends: [] });
  const [loading, setLoading] = useState(true);
  const [selectedPeriod, setSelectedPeriod] = useState<'7' | '30' | '90'>('90');
  const [selectedKabupaten, setSelectedKabupaten] = useState<string | null>(null);
  const [selectedKecamatan, setSelectedKecamatan] = useState<string | null>(null);
  const [selectedDesa, setSelectedDesa] = useState<string | null>(null);

  const fetchAnalytics = useCallback(async () => {
    try {
      setLoading(true);

      // Build query parameters
      const params = new URLSearchParams({
        period: selectedPeriod,
        kabupaten: selectedKabupaten || '',
        kecamatan: selectedKecamatan || '',
        desa: selectedDesa || '',
      });

      // Fetch summary
      const summaryResponse = await axios.get<AnalyticsSummary>(
        `${API}/analytics/summary?${params}`
      );
      setSummary(summaryResponse.data);

      // Fetch hot zones
      const hotZonesResponse = await axios.get<{ hot_zones: HotZone[] }>(
        `${API}/analytics/hot-zones?${params}&limit=5`
      );
      setHotZones(hotZonesResponse.data.hot_zones);

      // Fetch category trends (Sesuai Brief: Tren mingguan per kategori)
      const categoryTrendsResponse = await axios.get<{ categories: string[], trends: TrendDataPoint[] }>(
        `${API}/analytics/trends-per-category?period=weekly&weeks=12&${params}`
      );
      setCategoryTrends(categoryTrendsResponse.data);

      // Fetch kecamatan trends (Sesuai Brief: Tren mingguan per kecamatan)
      const kecamatanTrendsResponse = await axios.get<{ kecamatan: string[], trends: TrendDataPoint[] }>(
        `${API}/analytics/trends-per-kecamatan?period=weekly&weeks=12&limit=5&${params}`
      );
      setKecamatanTrends(kecamatanTrendsResponse.data);

    } catch (error) {
      console.error('Error fetching analytics:', error);
      toast.error("Gagal memuat data analytics");
    } finally {
      setLoading(false);
    }
  }, [API, selectedPeriod, selectedKabupaten, selectedKecamatan, selectedDesa]);

  useEffect(() => {
    fetchAnalytics();
  }, [fetchAnalytics]);

  if (loading) {
    return (
      <Layout>
        <div className="p-6 min-h-screen">
          <div className="animate-pulse space-y-6">
            <div className="h-8 bg-gray-200 rounded w-1/3"></div>
            <div className="grid grid-cols-4 gap-6">
              {[1, 2, 3, 4].map(i => (
                <div key={i} className="h-32 bg-gray-200 rounded-2xl"></div>
              ))}
            </div>
            <div className="grid grid-cols-2 gap-6">
              {[1, 2].map(i => (
                <div key={i} className="h-96 bg-gray-200 rounded-2xl"></div>
              ))}
            </div>
          </div>
        </div>
      </Layout>
    );
  }

  if (!summary) {
    return (
      <Layout>
        <div className="p-8 min-h-screen">
          <div className="text-center max-w-md mx-auto mt-20">
            <h3 className="text-2xl font-bold text-gray-800 mb-4">
              Belum Ada Data Analytics
            </h3>
            <p className="text-gray-600 mb-8">
              Data analytics akan tersedia setelah ada data bisnis.
            </p>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout>
      <div className="p-6 min-h-screen">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-2">
            📊 Analytics Dashboard
          </h1>
          <p className="text-gray-600">
            Analisis mendalam pertumbuhan bisnis per periode & wilayah
          </p>
        </div>

        {/* Period Filter */}
        <Card className="p-6 mb-6 rounded-2xl shadow-lg">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold text-gray-900">Filter Periode</h3>
            <div className="flex space-x-2">
              {['7', '30', '90'].map(period => (
                <button
                  key={period}
                  onClick={() => setSelectedPeriod(period as any)}
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                    selectedPeriod === period
                      ? 'bg-blue-600 text-white shadow-md'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  {period} Hari
                </button>
              ))}
            </div>
          </div>
        </Card>

        {/* Location Filter */}
        <Card className="p-6 mb-6 rounded-2xl shadow-lg">
          <h3 className="text-lg font-semibold text-gray-900 mb-4">📍 Filter Lokasi</h3>
          <HierarchicalLocationFilter
            kabupaten={selectedKabupaten || undefined}
            kecamatan={selectedKecamatan || undefined}
            desa={selectedDesa || undefined}
            onKabupatenChange={(value) => {
              setSelectedKabupaten(value);
              setSelectedKecamatan(null); // Reset kecamatan when kabupaten changes
              setSelectedDesa(null); // Reset desa when kabupaten changes
            }}
            onKecamatanChange={(value) => {
              setSelectedKecamatan(value);
              setSelectedDesa(null); // Reset desa when kecamatan changes
            }}
            onDesaChange={(value) => setSelectedDesa(value)}
          />
        </Card>
        <div className="mb-8">
          <GrowthMetrics
            totalBusinesses={summary.total_businesses}
            newBusinesses={summary.new_businesses}
            recentlyOpened={summary.recently_opened}
            highConfidenceNew={summary.high_confidence_new}
            growthRate={summary.growth_rate}
            period={selectedPeriod}
          />
        </div>

        {/* Chart.js Line Charts - Professional and Accurate */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <LineChart
            data={categoryTrends.trends}
            lines={categoryTrends.categories}
            title="Tren Mingguan Penambahan per Kategori"
            height={450}
            showGrid={true}
            showLegend={true}
            showTooltips={true}
            animated={true}
          />
          <LineChart
            data={kecamatanTrends.trends}
            lines={kecamatanTrends.kecamatan}
            title="Tren Mingguan per Kecamatan (Top 5)"
            height={450}
            showGrid={true}
            showLegend={true}
            showTooltips={true}
            animated={true}
          />
        </div>

        {/* Hot Zones & Category Breakdown */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <HotZonesList hotZones={hotZones} title="Top 5 Kecamatan Paling Panas" />
          <CategoryBreakdown categories={summary.categories_breakdown} />
        </div>

        {/* Area Breakdown */}
        {summary.areas_breakdown && summary.areas_breakdown.length > 0 && (
          <div className="mb-8">
            <Card className="p-6 rounded-2xl shadow-lg">
              <h3 className="text-xl font-bold text-gray-900 mb-6">Breakdown per Area</h3>
              <div className="space-y-3">
                {summary.areas_breakdown.slice(0, 10).map((area, index) => (
                  <div key={area.area} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div className="flex items-center space-x-3">
                      <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm ${
                        index < 3 ? 'bg-gradient-to-br from-blue-500 to-blue-600' : 'bg-gray-400'
                      }`}>
                        {index + 1}
                      </div>
                      <div>
                        <div className="font-semibold text-gray-900">{cleanAreaName(area.area)}</div>
                        <div className="text-xs text-gray-600">
                          <span className="text-green-600 font-medium">{area.new_count} baru</span>
                          {' • '}
                          <span>{area.total} total</span>
                        </div>
                      </div>
                    </div>
                    <div className="text-sm font-medium text-gray-700">
                      Score: {Math.round(area.avg_confidence)}
                    </div>
                  </div>
                ))}
              </div>
            </Card>
          </div>
        )}
      </div>
    </Layout>
  );
};

export default Analytics;
