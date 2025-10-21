import * as React from "react";
import { Sheet } from "./ui/sheet";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import ReviewTimelineChart from "./ReviewTimelineChart";
import PhotoTimelineChart from "./PhotoTimelineChart";
import MiniMap from "./MiniMap";
import { 
  MapPin, 
  Phone, 
  Globe, 
  Clock, 
  Star, 
  MessageCircle, 
  Camera,
  ExternalLink,
  TrendingUp,
  Calendar
} from "lucide-react";

interface Business {
  id: number;
  name: string;
  category: string;
  area: string;
  address: string;
  rating: number;
  review_count: number;
  phone?: string;
  website?: string;
  first_seen: string;
  lat: number | string;
  lng: number | string;
  google_maps_url?: string;
  opening_hours?: any;
  price_level?: number;
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

interface Props {
  business: Business | null;
  isOpen: boolean;
  onClose: () => void;
}

const BusinessDetailDrawer: React.FC<Props> = ({ business, isOpen, onClose }) => {
  if (!business) return null;

  const metadata = business.indicators?.metadata_analysis;
  const confidence = business.indicators?.new_business_confidence || 0;
  const confidenceLevel = metadata?.confidence_level || 'low';
  
  const getConfidenceBadgeColor = (level: string) => {
    switch (level) {
      case 'high': return 'bg-green-100 text-green-800 border-green-200';
      case 'medium': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getBusinessAgeBadgeColor = (estimate: string) => {
    switch (estimate) {
      case 'ultra_new': return 'bg-red-100 !text-black border-red-100 hover:bg-red-200 hover:!text-black hover:border-red-200 hover:shadow-md';
      case 'very_new': return 'bg-orange-100 !text-black border-orange-100 hover:bg-orange-200 hover:!text-black hover:border-orange-200 hover:shadow-md';
      case 'new': return 'bg-sky-100 !text-black border-sky-100 hover:bg-sky-200 hover:!text-black hover:border-sky-200 hover:shadow-md';
      case 'recent': return 'bg-purple-100 !text-black border-purple-100 hover:bg-purple-200 hover:!text-black hover:border-purple-200 hover:shadow-md';
      case 'established': return 'bg-green-100 !text-black border-green-100 hover:bg-green-200 hover:!text-black hover:border-green-200 hover:shadow-md';
      case 'old': return 'bg-gray-100 !text-black border-gray-100 hover:bg-gray-200 hover:!text-black hover:border-gray-200 hover:shadow-md';
      default: return 'bg-gray-100 !text-black border-gray-100 hover:bg-gray-200 hover:!text-black hover:border-gray-200 hover:shadow-md';
    }
  };

  const getBusinessAgeLabel = (estimate: string) => {
    const labels: { [key: string]: string } = {
      'ultra_new': 'Ultra Baru (< 1 minggu)',
      'very_new': 'Sangat Baru (< 1 bulan)',
      'new': 'Baru (< 3 bulan)',
      'recent': 'Recent (< 1 tahun)',
      'established': 'Established (1-3 tahun)',
      'old': 'Lama (> 3 tahun)'
    };
    return labels[estimate] || estimate;
  };

  return (
    <Sheet open={isOpen} onClose={onClose}>
      {/* Header */}
      <div className="mb-6">
        <div className="flex items-start justify-between mb-3">
          <div className="flex-1">
            <h2 className="text-2xl font-bold text-gray-900 mb-2">{business.name}</h2>
            <div className="flex items-center gap-2 flex-wrap">
              <Badge variant="secondary">{business.category}</Badge>
              {metadata?.business_age_estimate && (
                <Badge className={getBusinessAgeBadgeColor(metadata.business_age_estimate)}>
                  {getBusinessAgeLabel(metadata.business_age_estimate)}
                </Badge>
              )}
            </div>
          </div>
        </div>
        
        {/* Rating & Reviews */}
        <div className="flex items-center gap-4 text-sm">
          <div className="flex items-center gap-1">
            <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
            <span className="font-semibold">{business.rating || 'N/A'}</span>
          </div>
          <div className="flex items-center gap-1">
            <MessageCircle className="h-4 w-4 text-gray-500" />
            <span>{business.review_count} reviews</span>
          </div>
          <div className="flex items-center gap-1">
            <Calendar className="h-4 w-4 text-gray-500" />
            <span>Sejak {new Date(business.first_seen).toLocaleDateString('id-ID')}</span>
          </div>
        </div>
      </div>

      {/* Confidence Score */}
      <Card className="p-4 mb-6 bg-gradient-to-r from-blue-50 to-purple-50 border-blue-200">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-700">New Business Confidence</p>
            <p className="text-2xl font-bold text-blue-600">{confidence}%</p>
          </div>
          <div className={`px-4 py-2 rounded-lg border ${getConfidenceBadgeColor(confidenceLevel)}`}>
            <p className="text-xs font-medium">
              {confidenceLevel === 'high' ? 'High Confidence' : confidenceLevel === 'medium' ? 'Medium Confidence' : 'Low Confidence'}
            </p>
          </div>
        </div>
      </Card>

      {/* Review Timeline */}
      <Card className="p-4 mb-6">
        <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <TrendingUp className="h-4 w-4" />
          Review Timeline
        </h3>
        <ReviewTimelineChart
          oldestReviewDate={metadata?.oldest_review_date || null}
          newestReviewDate={metadata?.newest_review_date || null}
          reviewCount={business.review_count}
        />
        {metadata?.oldest_review_date && (
          <p className="text-xs text-gray-600 mt-2">
            First review: {new Date(metadata.oldest_review_date).toLocaleDateString('id-ID')} 
            ({metadata.review_age_months || 0} bulan lalu)
          </p>
        )}
      </Card>

      {/* Photo Activity */}
      <Card className="p-4 mb-6">
        <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <Camera className="h-4 w-4" />
          Photo Activity
        </h3>
        <PhotoTimelineChart
          photoCount={metadata?.photo_count || 0}
          hasRecentActivity={metadata?.has_recent_activity || false}
        />
      </Card>

      {/* Mini Map */}
      <Card className="p-4 mb-6">
        <h3 className="font-semibold text-gray-900 mb-3 flex items-center gap-2">
          <MapPin className="h-4 w-4" />
          Location
        </h3>
        <MiniMap
          lat={Number(business.lat)}
          lng={Number(business.lng)}
          businessName={business.name}
        />
        <p className="text-xs text-gray-600 mt-2">
          {business.area || business.address}
        </p>
      </Card>

      {/* Business Information */}
      <Card className="p-4 mb-6">
        <h3 className="font-semibold text-gray-900 mb-3">Business Information</h3>
        <div className="space-y-3">
          <div className="flex items-start gap-3">
            <MapPin className="h-4 w-4 text-gray-500 mt-0.5" />
            <div className="flex-1">
              <p className="text-sm font-medium text-gray-700">Address</p>
              <p className="text-sm text-gray-600">{business.address || 'N/A'}</p>
            </div>
          </div>
          
          {business.phone && (
            <div className="flex items-start gap-3">
              <Phone className="h-4 w-4 text-gray-500 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-700">Phone</p>
                <a 
                  href={`tel:${business.phone}`}
                  className="text-sm text-blue-600 hover:underline"
                >
                  {business.phone}
                </a>
              </div>
            </div>
          )}
          
          {business.website && (
            <div className="flex items-start gap-3">
              <Globe className="h-4 w-4 text-gray-500 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-700">Website</p>
                <a 
                  href={business.website}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm text-blue-600 hover:underline flex items-center gap-1"
                >
                  {business.website.substring(0, 40)}{business.website.length > 40 ? '...' : ''}
                  <ExternalLink className="h-3 w-3" />
                </a>
              </div>
            </div>
          )}
          
          {business.opening_hours && (
            <div className="flex items-start gap-3">
              <Clock className="h-4 w-4 text-gray-500 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-700">Opening Hours</p>
                <p className="text-sm text-gray-600">
                  {business.opening_hours.open_now ? '🟢 Open Now' : '🔴 Closed'}
                </p>
              </div>
            </div>
          )}
        </div>
      </Card>

      {/* Indicators */}
      <Card className="p-4 mb-6">
        <h3 className="font-semibold text-gray-900 mb-3">New Business Indicators</h3>
        <div className="grid grid-cols-2 gap-2">
          {business.indicators?.recently_opened && (
            <Badge variant="outline" className="justify-center py-2 border-green-200 text-green-700">
              ✓ Recently Opened
            </Badge>
          )}
          {business.indicators?.few_reviews && (
            <Badge variant="outline" className="justify-center py-2 border-blue-200 text-blue-700">
              ✓ Few Reviews
            </Badge>
          )}
          {business.indicators?.low_rating_count && (
            <Badge variant="outline" className="justify-center py-2 border-purple-200 text-purple-700">
              ✓ Low Rating Count
            </Badge>
          )}
          {business.indicators?.has_recent_photo && (
            <Badge variant="outline" className="justify-center py-2 border-pink-200 text-pink-700">
              ✓ Recent Photos
            </Badge>
          )}
          {business.indicators?.review_spike && (
            <Badge variant="outline" className="justify-center py-2 border-orange-200 text-orange-700">
              ✓ Review Spike
            </Badge>
          )}
          {business.indicators?.rating_improvement && (
            <Badge variant="outline" className="justify-center py-2 border-teal-200 text-teal-700">
              ✓ Rating Improvement
            </Badge>
          )}
          {business.indicators?.is_truly_new && (
            <Badge variant="outline" className="justify-center py-2 border-red-200 text-red-700">
              ✓ Truly New
            </Badge>
          )}
          {business.indicators?.newly_discovered && (
            <Badge variant="outline" className="justify-center py-2 border-indigo-200 text-indigo-700">
              ✓ Newly Discovered
            </Badge>
          )}
        </div>
      </Card>

      {/* Quick Actions */}
      <div className="flex gap-3">
        <Button
          className="flex-1"
          onClick={() => {
            if (business.google_maps_url) {
              window.open(business.google_maps_url, '_blank');
            }
          }}
        >
          <MapPin className="h-4 w-4 mr-2" />
          View on Google Maps
        </Button>
        <Button
          variant="outline"
          onClick={onClose}
        >
          Close
        </Button>
      </div>
    </Sheet>
  );
};

export default BusinessDetailDrawer;

