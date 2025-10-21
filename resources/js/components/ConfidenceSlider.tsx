import * as React from "react";
import { Slider } from "../components/ui/slider";

interface Props {
  value: number; // 0-100
  onChange: (value: number) => void;
  className?: string;
  label?: string;
  showValue?: boolean;
}

const ConfidenceSlider: React.FC<Props> = ({
  value = 60,
  onChange,
  className = "",
  label = "Ambang Batas 'Baru'",
  showValue = true,
}) => {
  const handleChange = (values: number[]) => {
    onChange(values[0]);
  };

  const getConfidenceColor = (val: number) => {
    if (val >= 80) return "text-green-600";
    if (val >= 60) return "text-blue-600";
    if (val >= 40) return "text-yellow-600";
    return "text-gray-600";
  };

  const getConfidenceLabel = (val: number) => {
    if (val >= 80) return "Sangat Ketat";
    if (val >= 60) return "Ketat";
    if (val >= 40) return "Sedang";
    if (val >= 20) return "Longgar";
    return "Sangat Longgar";
  };

  return (
    <div className={`space-y-3 ${className}`}>
      <div className="flex items-center justify-between">
        <label className="text-sm font-medium text-gray-700">{label}</label>
        {showValue && (
          <div className="flex items-center gap-2">
            <span className={`text-sm font-semibold ${getConfidenceColor(value)}`}>
              {value}%
            </span>
            <span className="text-xs text-gray-500">
              ({getConfidenceLabel(value)})
            </span>
          </div>
        )}
      </div>

      <Slider
        value={[value]}
        onValueChange={handleChange}
        min={0}
        max={100}
        step={5}
        className="w-full"
      />

      <div className="flex justify-between text-xs text-gray-500">
        <span>0% (Semua)</span>
        <span>100% (Hanya Pasti Baru)</span>
      </div>

      <div className="text-xs text-gray-600 bg-gray-50 p-2 rounded">
        <strong>Keterangan:</strong> Semakin tinggi nilai, semakin ketat filter "bisnis baru".
        Nilai 60-75 direkomendasikan untuk keseimbangan antara akurasi dan kelengkapan.
      </div>
    </div>
  );
};

export default ConfidenceSlider;

