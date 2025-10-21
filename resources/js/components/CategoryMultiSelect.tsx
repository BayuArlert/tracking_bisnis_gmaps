import * as React from "react";
import { useState, useEffect } from "react";
import axios from "axios";
import { Check, X } from "lucide-react";
import { Button } from "./ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuCheckboxItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";
import { Badge } from "./ui/badge";

const API = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

interface CategoryOption {
  value: string;
  label: string;
  icon: string;
}

interface Props {
  value: string[]; // Array of selected categories
  onChange: (categories: string[]) => void;
  className?: string;
}

// Icon mapping for categories (fallback if API doesn't provide icons)
const CATEGORY_ICONS: { [key: string]: string } = {
  "Café": "☕",
  "Restoran": "🍽️",
  "Sekolah": "🏫",
  "Villa": "🏡",
  "Hotel": "🏨",
  "Popular Spot": "📍",
  "Lainnya": "🏢",
};

const CategoryMultiSelect: React.FC<Props> = ({
  value = [],
  onChange,
  className = "",
}) => {
  const [categories, setCategories] = useState<CategoryOption[]>([]);
  const [loading, setLoading] = useState(false);

  // Fetch categories from database on mount
  useEffect(() => {
    fetchCategories();
  }, []);

  const fetchCategories = async () => {
    setLoading(true);
    try {
      // Fetch from CategoryMapping table via API
      const response = await axios.get(`${API}/scrape/categories`);
      
      const categoriesData = Array.isArray(response.data) 
        ? response.data 
        : [];
      
      const mappedCategories = categoriesData.map((cat: string) => ({
        value: cat,
        label: cat,
        icon: CATEGORY_ICONS[cat] || "🏢"
      }));
      
      setCategories(mappedCategories);
    } catch (error) {
      console.error("Failed to fetch categories:", error);
      // Fallback to default categories if API fails
      setCategories([
        { value: "Café", label: "Café", icon: "☕" },
        { value: "Restoran", label: "Restoran", icon: "🍽️" },
        { value: "Sekolah", label: "Sekolah", icon: "🏫" },
        { value: "Villa", label: "Villa", icon: "🏡" },
        { value: "Hotel", label: "Hotel", icon: "🏨" },
        { value: "Popular Spot", label: "Popular Spot", icon: "📍" },
        { value: "Lainnya", label: "Lainnya", icon: "🏢" },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleToggleCategory = (category: string) => {
    if (value.includes(category)) {
      // Remove category
      onChange(value.filter((c) => c !== category));
    } else {
      // Add category
      onChange([...value, category]);
    }
  };

  const handleSelectAll = () => {
    onChange(categories.map((c) => c.value));
  };

  const handleClearAll = () => {
    onChange([]);
  };

  const selectedCount = value.length;
  const buttonText =
    selectedCount === 0
      ? "Semua Kategori"
      : selectedCount === categories.length
      ? "Semua Kategori"
      : `${selectedCount} Kategori`;

  return (
    <div className={`space-y-2 ${className}`}>
      <label className="block text-sm font-medium text-gray-700">
        Kategori
      </label>
      
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" className="w-full justify-between" disabled={loading}>
            <span>{loading ? "Loading..." : buttonText}</span>
            {selectedCount > 0 && selectedCount < categories.length && (
              <Badge variant="secondary" className="ml-2 rounded-sm">
                {selectedCount}
              </Badge>
            )}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent className="w-64 z-50 max-h-[300px] overflow-y-auto">
          <DropdownMenuLabel className="flex items-center justify-between">
            <span>Pilih Kategori</span>
            <div className="flex gap-1">
              {selectedCount > 0 && selectedCount < categories.length && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleSelectAll}
                  className="h-6 text-xs px-2"
                >
                  Semua
                </Button>
              )}
              {selectedCount > 0 && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={handleClearAll}
                  className="h-6 text-xs px-2"
                >
                  Clear
                </Button>
              )}
            </div>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {categories.map((category) => (
            <DropdownMenuCheckboxItem
              key={category.value}
              checked={value.includes(category.value)}
              onCheckedChange={() => handleToggleCategory(category.value)}
              className="cursor-pointer"
            >
              <span className="mr-2">{category.icon}</span>
              {category.label}
            </DropdownMenuCheckboxItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      {/* Selected Categories Display */}
      {selectedCount > 0 && selectedCount < categories.length && (
        <div className="flex flex-wrap gap-1.5">
          {value.map((cat) => {
            const category = categories.find((c) => c.value === cat);
            if (!category) return null;
            
            return (
              <Badge
                key={cat}
                variant="secondary"
                className="text-xs px-2 py-0.5 cursor-pointer hover:bg-secondary/80"
                onClick={() => handleToggleCategory(cat)}
              >
                {category.icon} {category.label}
                <X className="ml-1 h-3 w-3" />
              </Badge>
            );
          })}
        </div>
      )}
    </div>
  );
};

export default CategoryMultiSelect;

