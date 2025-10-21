import * as React from "react";
import { useState } from "react";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "./ui/select";
import { Input } from "./ui/input";
import { Button } from "./ui/button";
import { Calendar } from "lucide-react";

interface Props {
  value: string; // "30", "60", "90", "180", "custom", or "all"
  customStart?: string; // ISO date string
  customEnd?: string; // ISO date string
  onChange: (period: string, customStart?: string, customEnd?: string) => void;
  className?: string;
}

const PeriodFilter: React.FC<Props> = ({
  value,
  customStart,
  customEnd,
  onChange,
  className = "",
}) => {
  const [showCustom, setShowCustom] = useState(value === "custom");
  const [tempStart, setTempStart] = useState(customStart || "");
  const [tempEnd, setTempEnd] = useState(customEnd || "");

  const handlePeriodChange = (newValue: string) => {
    if (newValue === "custom") {
      setShowCustom(true);
      onChange(newValue, tempStart, tempEnd);
    } else {
      setShowCustom(false);
      onChange(newValue);
    }
  };

  const handleCustomApply = () => {
    if (tempStart && tempEnd) {
      onChange("custom", tempStart, tempEnd);
    }
  };

  const handleCustomClear = () => {
    setTempStart("");
    setTempEnd("");
    setShowCustom(false);
    onChange("all");
  };

  return (
    <div className={`space-y-3 ${className}`}>
      <div>
        <label className="block text-sm font-medium mb-1.5 text-gray-700">
          Periode
        </label>
        <Select value={value} onValueChange={handlePeriodChange}>
          <SelectTrigger className="w-full">
            <SelectValue placeholder="Pilih Periode" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Periode</SelectItem>
            <SelectItem value="30">30 Hari Terakhir</SelectItem>
            <SelectItem value="60">60 Hari Terakhir</SelectItem>
            <SelectItem value="90">90 Hari Terakhir</SelectItem>
            <SelectItem value="180">180 Hari Terakhir</SelectItem>
            <SelectItem value="custom">
              <span className="flex items-center gap-2">
                <Calendar className="h-4 w-4" />
                Custom Range
              </span>
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Custom Date Range Inputs */}
      {showCustom && (
        <div className="space-y-2 p-3 bg-gray-50 rounded-md border">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium mb-1 text-gray-600">
                Dari
              </label>
              <Input
                type="date"
                value={tempStart}
                onChange={(e) => setTempStart(e.target.value)}
                max={tempEnd || undefined}
                className="text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium mb-1 text-gray-600">
                Sampai
              </label>
              <Input
                type="date"
                value={tempEnd}
                onChange={(e) => setTempEnd(e.target.value)}
                min={tempStart || undefined}
                max={new Date().toISOString().split("T")[0]}
                className="text-sm"
              />
            </div>
          </div>
          <div className="flex gap-2 justify-end">
            <Button
              variant="outline"
              size="sm"
              onClick={handleCustomClear}
              className="text-xs"
            >
              Clear
            </Button>
            <Button
              size="sm"
              onClick={handleCustomApply}
              disabled={!tempStart || !tempEnd}
              className="text-xs"
            >
              Apply
            </Button>
          </div>
        </div>
      )}

      {/* Display selected period summary */}
      {value !== "all" && value !== "custom" && (
        <div className="text-xs text-gray-500">
          Menampilkan bisnis baru dalam {value} hari terakhir
        </div>
      )}
      {value === "custom" && customStart && customEnd && (
        <div className="text-xs text-gray-500">
          Dari {new Date(customStart).toLocaleDateString("id-ID")} sampai{" "}
          {new Date(customEnd).toLocaleDateString("id-ID")}
        </div>
      )}
    </div>
  );
};

export default PeriodFilter;

