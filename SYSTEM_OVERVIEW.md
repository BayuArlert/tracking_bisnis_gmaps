# 🚀 Business Monitoring System - System Overview

## 📋 System Architecture

### **Backend (Laravel 11)**
- **Framework**: Laravel 11 with PHP 8.4+
- **Database**: MySQL with JSON columns for flexible data storage
- **API**: RESTful API with Laravel Sanctum authentication
- **Caching**: Redis/Memory caching for performance optimization
- **Queue**: Laravel Queue for background jobs

### **Frontend (React 19)**
- **Framework**: React 19 with TypeScript
- **UI Library**: Tailwind CSS + shadcn/ui components
- **State Management**: React Context + useState
- **Maps**: Google Maps API integration
- **Charts**: Chart.js for data visualization

## 🔧 Core Features

### **1. Business Scraping System**
- **Google Places API Integration**: Text Search + Place Details
- **Smart Filtering**: Pre-filtering + Post-filtering with category validation
- **Cost Optimization**: Caching, rate limiting, adaptive thresholds
- **New Business Detection**: 17 indicators with confidence scoring

### **2. Data Management**
- **Business Data**: Name, category, location, ratings, reviews
- **Category System**: 7 categories with keyword mapping
- **Location Hierarchy**: Kabupaten → Kecamatan → Desa
- **Temporal Data**: First seen, last fetched, review spikes

### **3. Analytics & Reporting**
- **Dashboard**: Real-time statistics and metrics
- **Trend Analysis**: Weekly/monthly business growth trends
- **Heatmap Visualization**: Geographic distribution of businesses
- **Export Options**: CSV and JSON export with filtering

### **4. User Interface**
- **Responsive Design**: Mobile-first approach
- **Interactive Maps**: Google Maps with markers and heatmap
- **Data Tables**: Sortable, filterable business listings
- **Real-time Updates**: Auto-refresh data and statistics

## 🗂️ File Structure

```
app/
├── Console/Commands/          # Artisan commands
├── Http/Controllers/          # API controllers
├── Jobs/                     # Background jobs
├── Models/                   # Eloquent models
├── Services/                 # Business logic services
└── Providers/                # Service providers

resources/js/
├── components/               # Reusable UI components
├── pages/                    # Page components
├── context/                  # React context
├── lib/                      # Utility functions
└── types/                    # TypeScript definitions

database/
├── migrations/               # Database schema
└── seeders/                  # Data seeders
```

## 🔄 Data Flow

### **Scraping Process**
1. **Text Search**: Query Google Places API with optimized queries
2. **Pre-filtering**: Filter by review count and basic criteria
3. **Place Details**: Get detailed information for candidates
4. **Post-filtering**: Validate category and business type
5. **Save Data**: Store in database with confidence scoring

### **Analytics Process**
1. **Data Aggregation**: Query database with filters
2. **Caching**: Cache expensive queries for performance
3. **API Response**: Return JSON data to frontend
4. **Visualization**: Render charts and maps

## ⚡ Performance Optimizations

### **Database**
- **Selective Queries**: Only fetch required columns
- **Indexing**: Optimized indexes on frequently queried columns
- **Caching**: Redis caching for expensive queries
- **Pagination**: Efficient pagination for large datasets

### **API**
- **Rate Limiting**: Prevent API abuse
- **Caching**: Cache API responses
- **Batch Operations**: Process multiple items efficiently
- **Lazy Loading**: Load data on demand

### **Frontend**
- **Code Splitting**: Lazy load components
- **Memoization**: Prevent unnecessary re-renders
- **Debouncing**: Optimize search and filter inputs
- **Image Optimization**: Optimize map markers and images

## 🔒 Security Features

### **Authentication**
- **Laravel Sanctum**: Token-based authentication
- **Rate Limiting**: 5 attempts per minute for login
- **Password Hashing**: bcrypt with 12 rounds

### **API Security**
- **CORS Protection**: Restricted origins
- **Input Validation**: Validate all inputs
- **SQL Injection**: Eloquent ORM protection
- **XSS Protection**: React's built-in sanitization

## 📊 Monitoring & Logging

### **Logging**
- **Structured Logging**: JSON format with context
- **Log Levels**: Debug, Info, Warning, Error
- **Log Rotation**: Automatic log file rotation
- **Performance Metrics**: API call tracking

### **Monitoring**
- **Error Tracking**: Exception handling and reporting
- **Performance Monitoring**: Query execution times
- **API Usage**: Google Places API usage tracking
- **Cost Tracking**: Real-time cost estimation

## 🚀 Deployment

### **Production Requirements**
- **PHP**: 8.4+ with required extensions
- **Database**: MySQL 8.0+
- **Cache**: Redis (recommended)
- **Queue**: Redis or Database
- **Storage**: Local or S3-compatible

### **Environment Variables**
```env
# Application
APP_NAME="Business Monitoring System"
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=your_host
DB_DATABASE=your_database

# Google Maps
VITE_GOOGLE_MAPS_API_KEY=your_api_key

# Cache
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

## 🔧 Maintenance

### **Regular Tasks**
- **Database Cleanup**: Remove old sessions and snapshots
- **Cache Clearing**: Clear expired cache entries
- **Log Rotation**: Manage log file sizes
- **Backup**: Regular database backups

### **Monitoring**
- **Health Checks**: API endpoint monitoring
- **Performance**: Query performance monitoring
- **Errors**: Error rate monitoring
- **Costs**: API usage cost tracking

---

**Last Updated**: October 24, 2025  
**Version**: 2.0  
**Status**: Production Ready ✅
