import * as React from "react";
import { useState, useEffect } from "react";
import axios from "axios";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "../components/ui/select";
import { Button } from "../components/ui/button";
import { X } from "lucide-react";

const API = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

interface Kabupaten {
  id: number;
  name: string;
  priority: number;
  zones_count: number;
}

interface Kecamatan {
  id: number;
  name: string;
  parent_id: number;
  center_lat: number;
  center_lng: number;
}

interface Props {
  kabupaten?: string;
  kecamatan?: string;
  onKabupatenChange: (kabupaten: string | null) => void;
  onKecamatanChange: (kecamatan: string | null) => void;
  className?: string;
}

const HierarchicalLocationFilter: React.FC<Props> = ({
  kabupaten,
  kecamatan,
  onKabupatenChange,
  onKecamatanChange,
  className = "",
}) => {
  const [kabupatenList, setKabupatenList] = useState<Kabupaten[]>([]);
  const [kecamatanList, setKecamatanList] = useState<Kecamatan[]>([]);
  const [loadingKabupaten, setLoadingKabupaten] = useState(false);
  const [loadingKecamatan, setLoadingKecamatan] = useState(false);

  // Fetch kabupaten list on mount
  useEffect(() => {
    fetchKabupaten();
  }, []);

  // Fetch kecamatan when kabupaten changes
  useEffect(() => {
    if (kabupaten) {
      fetchKecamatan(kabupaten);
    } else {
      setKecamatanList([]);
      onKecamatanChange(null);
    }
  }, [kabupaten]);

  const fetchKabupaten = async () => {
    setLoadingKabupaten(true);
    try {
      const response = await axios.get(`${API}/regions/kabupaten`);
      if (response.data.success) {
        setKabupatenList(response.data.data);
      }
    } catch (error) {
      console.error("Failed to fetch kabupaten:", error);
    } finally {
      setLoadingKabupaten(false);
    }
  };

  const fetchKecamatan = async (kabupatenName: string) => {
    setLoadingKecamatan(true);
    try {
      const response = await axios.get(
        `${API}/regions/kecamatan/${encodeURIComponent(kabupatenName)}`
      );
      if (response.data.success) {
        setKecamatanList(response.data.data);
      }
    } catch (error) {
      console.error("Failed to fetch kecamatan:", error);
    } finally {
      setLoadingKecamatan(false);
    }
  };

  const handleKabupatenChange = (value: string) => {
    console.log('Kabupaten changed to:', value);
    if (value === "all") {
      onKabupatenChange(null);
      onKecamatanChange(null);
    } else {
      onKabupatenChange(value);
      onKecamatanChange(null); // Reset kecamatan when kabupaten changes
    }
  };

  const handleKecamatanChange = (value: string) => {
    if (value === "all") {
      onKecamatanChange(null);
    } else {
      onKecamatanChange(value);
    }
  };

  const handleClear = () => {
    onKabupatenChange(null);
    onKecamatanChange(null);
  };

  const hasSelection = kabupaten || kecamatan;

  return (
    <div className={`space-y-3 ${className}`}>
      {/* Main Filter Row */}
      <div className="flex flex-col lg:flex-row gap-3">
        {/* Kabupaten Dropdown */}
        <div className="flex-1 min-w-0">
          <label className="block text-sm font-medium mb-1.5 text-gray-700">
            Kabupaten
          </label>
          <Select
            value={kabupaten || "all"}
            onValueChange={handleKabupatenChange}
            disabled={loadingKabupaten}
          >
            <SelectTrigger className="w-full">
              <SelectValue placeholder={loadingKabupaten ? "Loading..." : "Semua Kabupaten"} />
            </SelectTrigger>
            <SelectContent className="max-h-[300px] overflow-y-auto">
              <SelectItem value="all">Semua Kabupaten</SelectItem>
              {kabupatenList.map((kab) => (
                <SelectItem key={kab.id} value={kab.name}>
                  {kab.name}
                  {kab.zones_count > 1 && (
                    <span className="text-xs text-gray-500 ml-2">
                      ({kab.zones_count} zona)
                    </span>
                  )}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Kecamatan Dropdown - only show if kabupaten is selected */}
        {kabupaten && (
          <div className="flex-1 min-w-0">
            <label className="block text-sm font-medium mb-1.5 text-gray-700">
              Kecamatan
            </label>
            <Select
              value={kecamatan || "all"}
              onValueChange={handleKecamatanChange}
              disabled={loadingKecamatan || kecamatanList.length === 0}
            >
              <SelectTrigger className="w-full">
                <SelectValue
                  placeholder={
                    loadingKecamatan
                      ? "Loading..."
                      : kecamatanList.length === 0
                      ? "Tidak ada kecamatan"
                      : "Semua Kecamatan"
                  }
                />
              </SelectTrigger>
            <SelectContent className="max-h-[300px] overflow-y-auto">
              <SelectItem value="all">Semua Kecamatan</SelectItem>
              {kecamatanList
                .filter((kec, index, self) => 
                  // Remove duplicates by name
                  index === self.findIndex(k => k.name === kec.name)
                )
                .map((kec) => (
                <SelectItem key={`${kec.id}-${kec.name}`} value={kec.name}>
                  {kec.name}
                </SelectItem>
              ))}
            </SelectContent>
            </Select>
          </div>
        )}

        {/* Clear Button */}
        {hasSelection && (
          <div className="flex items-end">
            <Button
              variant="outline"
              size="sm"
              onClick={handleClear}
              className="h-10 px-3 whitespace-nowrap"
              title="Clear location filters"
            >
              <X className="h-4 w-4 mr-1" />
              Clear
            </Button>
          </div>
        )}
      </div>

      {/* Selection Summary */}
      {hasSelection && (
        <div className="flex flex-wrap gap-2 text-sm">
          {kabupaten && (
            <span className="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded-md">
              Kabupaten: {kabupaten}
            </span>
          )}
          {kecamatan && (
            <span className="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded-md">
              Kecamatan: {kecamatan}
            </span>
          )}
        </div>
      )}
    </div>
  );
};

export default HierarchicalLocationFilter;

