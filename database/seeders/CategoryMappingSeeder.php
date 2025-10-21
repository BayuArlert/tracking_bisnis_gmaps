<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoryMapping;

class CategoryMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'brief_category' => 'Café',
                'google_types' => ['cafe', 'coffee_shop'],
                'keywords_id' => [
                    'warung kopi', 'kedai kopi', 'coffee roastery', 'kopi susu',
                    'kopi tubruk', 'kopi hitam', 'espresso', 'latte', 'cappuccino',
                    'coffee shop', 'coffee house', 'roastery', 'kopi lokal'
                ],
                'keywords_en' => [
                    'coffee shop', 'café', 'espresso bar', 'coffee house',
                    'coffee roastery', 'coffee bar', 'coffee corner', 'coffee stand'
                ],
                'text_search_queries' => [
                    'cafe in {area}',
                    'coffee shop in {area}',
                    'warung kopi {area}',
                    'kedai kopi {area}',
                    'coffee roastery {area}'
                ]
            ],
            [
                'brief_category' => 'Restoran',
                'google_types' => ['restaurant', 'food'],
                'keywords_id' => [
                    'restoran', 'rumah makan', 'tempat makan', 'warung makan',
                    'kafe', 'bistro', 'dining', 'kuliner', 'masakan',
                    'food court', 'food truck', 'warung nasi'
                ],
                'keywords_en' => [
                    'restaurant', 'dining', 'eatery', 'bistro', 'cafe',
                    'food court', 'food truck', 'dining room', 'kitchen'
                ],
                'text_search_queries' => [
                    'restaurant in {area}',
                    'restoran {area}',
                    'tempat makan {area}',
                    'rumah makan {area}',
                    'kuliner {area}'
                ]
            ],
            [
                'brief_category' => 'Sekolah',
                'google_types' => ['school', 'university'],
                'keywords_id' => [
                    'sekolah', 'sd', 'smp', 'sma', 'smk', 'tk', 'paud',
                    'universitas', 'institut', 'akademi', 'politeknik',
                    'sekolah dasar', 'sekolah menengah', 'sekolah tinggi'
                ],
                'keywords_en' => [
                    'school', 'university', 'college', 'academy', 'institute',
                    'elementary school', 'high school', 'middle school',
                    'kindergarten', 'preschool'
                ],
                'text_search_queries' => [
                    'school in {area}',
                    'sekolah {area}',
                    'universitas {area}',
                    'education {area}',
                    'learning center {area}'
                ]
            ],
            [
                'brief_category' => 'Villa',
                'google_types' => ['lodging'],
                'keywords_id' => [
                    'villa', 'penginapan', 'homestay', 'guesthouse',
                    'villa pribadi', 'villa mewah', 'villa resort',
                    'private villa', 'luxury villa', 'beach villa'
                ],
                'keywords_en' => [
                    'villa', 'private villa', 'luxury villa', 'beach villa',
                    'mountain villa', 'villa resort', 'villa rental',
                    'holiday villa', 'vacation villa'
                ],
                'text_search_queries' => [
                    'villa in {area}',
                    'private villa {area}',
                    'luxury villa {area}',
                    'villa rental {area}',
                    'beach villa {area}'
                ]
            ],
            [
                'brief_category' => 'Hotel',
                'google_types' => ['lodging', 'hotel'],
                'keywords_id' => [
                    'hotel', 'resort', 'penginapan', 'akomodasi',
                    'hotel bintang', 'boutique hotel', 'budget hotel',
                    'hotel mewah', 'resort hotel', 'hotel internasional'
                ],
                'keywords_en' => [
                    'hotel', 'resort', 'accommodation', 'boutique hotel',
                    'luxury hotel', 'budget hotel', 'business hotel',
                    'resort hotel', 'hotel chain'
                ],
                'text_search_queries' => [
                    'hotel in {area}',
                    'resort in {area}',
                    'accommodation {area}',
                    'hotel bintang {area}',
                    'boutique hotel {area}'
                ]
            ],
            [
                'brief_category' => 'Popular Spot',
                'google_types' => ['tourist_attraction', 'point_of_interest', 'park', 'natural_feature'],
                'keywords_id' => [
                    'pantai', 'beach', 'gunung', 'mountain', 'air terjun', 'waterfall',
                    'trekking', 'hiking', 'surf', 'surfing', 'diving', 'snorkeling',
                    'temple', 'pura', 'monument', 'museum', 'gallery', 'art',
                    'nature', 'alam', 'adventure', 'outdoor', 'camping', 'glamping'
                ],
                'keywords_en' => [
                    'beach', 'mountain', 'waterfall', 'hiking', 'trekking',
                    'surfing', 'diving', 'snorkeling', 'temple', 'monument',
                    'museum', 'gallery', 'nature', 'adventure', 'outdoor',
                    'camping', 'glamping', 'tourist attraction', 'landmark'
                ],
                'text_search_queries' => [
                    'tourist attraction in {area}',
                    'beach in {area}',
                    'waterfall in {area}',
                    'hiking in {area}',
                    'surfing in {area}',
                    'temple in {area}',
                    'nature spot {area}'
                ]
            ],
            [
                'brief_category' => 'Lainnya',
                'google_types' => ['coworking_space', 'shopping_mall', 'gym', 'spa', 'bar', 'night_club'],
                'keywords_id' => [
                    'coworking', 'co-working', 'workspace', 'mall', 'shopping',
                    'gym', 'fitness', 'spa', 'massage', 'bar', 'pub', 'club',
                    'nightclub', 'entertainment', 'hiburan', 'olahraga',
                    'kesehatan', 'beauty', 'kecantikan', 'salon'
                ],
                'keywords_en' => [
                    'coworking space', 'workspace', 'shopping mall', 'mall',
                    'gym', 'fitness center', 'spa', 'massage', 'bar', 'pub',
                    'nightclub', 'entertainment', 'beauty salon', 'salon'
                ],
                'text_search_queries' => [
                    'coworking space in {area}',
                    'shopping mall in {area}',
                    'gym in {area}',
                    'spa in {area}',
                    'bar in {area}',
                    'entertainment in {area}'
                ]
            ]
        ];

        foreach ($categories as $category) {
            CategoryMapping::create($category);
        }
    }
}
