<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMapping extends Model
{
    protected $fillable = [
        'brief_category',
        'google_types',
        'keywords_id',
        'keywords_en',
        'text_search_queries',
    ];

    protected $casts = [
        'google_types' => 'array',
        'keywords_id' => 'array',
        'keywords_en' => 'array',
        'text_search_queries' => 'array',
    ];

    /**
     * Get all keywords (Indonesian + English)
     */
    public function getAllKeywordsAttribute(): array
    {
        $keywords = [];
        
        if ($this->keywords_id) {
            $keywords = array_merge($keywords, $this->keywords_id);
        }
        
        if ($this->keywords_en) {
            $keywords = array_merge($keywords, $this->keywords_en);
        }
        
        return array_unique($keywords);
    }

    /**
     * Check if a Google type matches this category
     */
    public function matchesGoogleType(string $googleType): bool
    {
        return in_array($googleType, $this->google_types ?? []);
    }

    /**
     * Check if a business name contains keywords for this category
     */
    public function matchesKeywords(string $businessName): bool
    {
        $name = strtolower($businessName);
        
        foreach ($this->getAllKeywordsAttribute() as $keyword) {
            if (str_contains($name, strtolower($keyword))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get text search queries for a specific area
     */
    public function getTextSearchQueriesForArea(string $area): array
    {
        $queries = [];
        
        if ($this->text_search_queries) {
            foreach ($this->text_search_queries as $template) {
                $queries[] = str_replace('{area}', $area, $template);
            }
        }
        
        return $queries;
    }

    /**
     * Scope for specific brief category
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('brief_category', $category);
    }
}
