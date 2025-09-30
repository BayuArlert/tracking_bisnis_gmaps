# Railway Deployment Configuration

## Environment Variables Setup

To fix the CORS issues when deploying to Railway, you need to set the following environment variables in your Railway project:

### Required Environment Variables

1. **APP_URL** - Your Railway backend URL
   ```
   APP_URL=https://your-backend-service.up.railway.app
   ```

2. **FRONTEND_URL** - Your Railway frontend URL  
   ```
   FRONTEND_URL=https://trackingbisnisgmaps-production.up.railway.app
   ```

3. **APP_KEY** - Laravel application key
   ```
   php artisan key:generate --show
   ```

4. **VITE_GOOGLE_MAPS_API_KEY** - Your Google Maps API key
   ```
   VITE_GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
   ```

### How to Set Environment Variables in Railway

1. Go to your Railway project dashboard
2. Click on your backend service
3. Go to the "Variables" tab
4. Add each environment variable with its value
5. Redeploy your service

### Database Configuration

If you're using a database, make sure to set up the database environment variables:

```
DB_CONNECTION=mysql
DB_HOST=your-railway-db-host
DB_PORT=3306
DB_DATABASE=your-database-name
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

### What Was Fixed

1. **API URL Configuration**: Updated `AuthContext.tsx` to automatically detect the correct API URL based on environment (localhost for development, current origin for production)

2. **CORS Configuration**: Updated `config/cors.php` to allow Railway frontend URLs

3. **TrustProxies Middleware**: Already configured to trust all proxies (correct for Railway)

### Testing

After setting up the environment variables and redeploying:

1. Check that the frontend can load without CORS errors
2. Verify that authentication works
3. Test API calls from the frontend
4. Check browser console for any remaining errors

### Troubleshooting

If you still see CORS errors:
1. Verify that `FRONTEND_URL` matches your actual Railway frontend URL exactly
2. Check that `APP_URL` is set correctly
3. Make sure both services are deployed and running
4. Check Railway logs for any backend errors
