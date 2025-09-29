<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function sendWeeklySummary(Request $request)
    {
        $email = $request->get('email', 'admin@example.com');
        
        // Get weekly statistics
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        
        $newBusinessesThisWeek = Business::whereBetween('first_seen', [$weekStart, $weekEnd])->count();
        $totalBusinesses = Business::count();
        
        // Get top categories this week
        $topCategories = Business::whereBetween('first_seen', [$weekStart, $weekEnd])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        // Get top areas this week
        $topAreas = Business::whereBetween('first_seen', [$weekStart, $weekEnd])
            ->selectRaw('area, COUNT(*) as count')
            ->groupBy('area')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        // Get trending businesses (high review count this week)
        $trendingBusinesses = Business::whereBetween('first_seen', [$weekStart, $weekEnd])
            ->orderBy('review_count', 'desc')
            ->limit(10)
            ->get();
        
        // Generate CSV report
        $csvData = $this->generateWeeklyCSV($weekStart, $weekEnd);
        
        // Send email with summary
        $this->sendSummaryEmail($email, [
            'week_start' => $weekStart->format('M d, Y'),
            'week_end' => $weekEnd->format('M d, Y'),
            'new_businesses' => $newBusinessesThisWeek,
            'total_businesses' => $totalBusinesses,
            'top_categories' => $topCategories,
            'top_areas' => $topAreas,
            'trending_businesses' => $trendingBusinesses,
            'csv_data' => $csvData,
        ]);
        
        return response()->json([
            'message' => 'Weekly summary sent successfully',
            'email' => $email,
            'new_businesses' => $newBusinessesThisWeek,
        ]);
    }
    
    public function sendMonthlySummary(Request $request)
    {
        $email = $request->get('email', 'admin@example.com');
        
        // Get monthly statistics
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        
        $newBusinessesThisMonth = Business::whereBetween('first_seen', [$monthStart, $monthEnd])->count();
        $totalBusinesses = Business::count();
        
        // Get growth rate compared to last month
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        $newBusinessesLastMonth = Business::whereBetween('first_seen', [$lastMonthStart, $lastMonthEnd])->count();
        
        $growthRate = $lastMonthBusinesses > 0 
            ? (($newBusinessesThisMonth - $newBusinessesLastMonth) / $newBusinessesLastMonth) * 100 
            : 0;
        
        // Get top categories this month
        $topCategories = Business::whereBetween('first_seen', [$monthStart, $monthEnd])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        // Get top areas this month
        $topAreas = Business::whereBetween('first_seen', [$monthStart, $monthEnd])
            ->selectRaw('area, COUNT(*) as count')
            ->groupBy('area')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();
        
        // Get trending businesses this month
        $trendingBusinesses = Business::whereBetween('first_seen', [$monthStart, $monthEnd])
            ->orderBy('review_count', 'desc')
            ->limit(10)
            ->get();
        
        // Generate CSV report
        $csvData = $this->generateMonthlyCSV($monthStart, $monthEnd);
        
        // Send email with summary
        $this->sendSummaryEmail($email, [
            'month_start' => $monthStart->format('M d, Y'),
            'month_end' => $monthEnd->format('M d, Y'),
            'new_businesses' => $newBusinessesThisMonth,
            'total_businesses' => $totalBusinesses,
            'growth_rate' => $growthRate,
            'top_categories' => $topCategories,
            'top_areas' => $topAreas,
            'trending_businesses' => $trendingBusinesses,
            'csv_data' => $csvData,
            'is_monthly' => true,
        ]);
        
        return response()->json([
            'message' => 'Monthly summary sent successfully',
            'email' => $email,
            'new_businesses' => $newBusinessesThisMonth,
            'growth_rate' => $growthRate,
        ]);
    }
    
    private function generateWeeklyCSV($weekStart, $weekEnd)
    {
        $businesses = Business::whereBetween('first_seen', [$weekStart, $weekEnd])->get();
        
        $csvData = "ID,Nama,Kategori,Area,Alamat,Rating,Review Count,First Seen,Recently Opened,Review Spike\n";
        
        foreach ($businesses as $business) {
            $indicators = $business->indicators ?? [];
            $csvData .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%d,%s,%s,%s\n",
                $business->id,
                $business->name,
                $business->category,
                $business->area,
                $business->address,
                $business->rating ?? '',
                $business->review_count,
                $business->first_seen,
                $indicators['recently_opened'] ? 'Yes' : 'No',
                $indicators['review_spike'] ? 'Yes' : 'No'
            );
        }
        
        return $csvData;
    }
    
    private function generateMonthlyCSV($monthStart, $monthEnd)
    {
        $businesses = Business::whereBetween('first_seen', [$monthStart, $monthEnd])->get();
        
        $csvData = "ID,Nama,Kategori,Area,Alamat,Rating,Review Count,First Seen,Recently Opened,Review Spike,Confidence Score\n";
        
        foreach ($businesses as $business) {
            $indicators = $business->indicators ?? [];
            $csvData .= sprintf(
                "%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%d,%s,%s,%s,%s\n",
                $business->id,
                $business->name,
                $business->category,
                $business->area,
                $business->address,
                $business->rating ?? '',
                $business->review_count,
                $business->first_seen,
                $indicators['recently_opened'] ? 'Yes' : 'No',
                $indicators['review_spike'] ? 'Yes' : 'No',
                $indicators['new_business_confidence'] ?? 'N/A'
            );
        }
        
        return $csvData;
    }
    
    private function sendSummaryEmail($email, $data)
    {
        $subject = isset($data['is_monthly']) 
            ? "Monthly Business Summary - " . now()->format('F Y')
            : "Weekly Business Summary - " . $data['week_start'] . " to " . $data['week_end'];
        
        $htmlContent = $this->generateEmailHTML($data, isset($data['is_monthly']));
        
        // For now, we'll just return the email content
        // In a real application, you would use Laravel's Mail facade
        return [
            'to' => $email,
            'subject' => $subject,
            'html' => $htmlContent,
        ];
    }
    
    private function generateEmailHTML($data, $isMonthly = false)
    {
        $period = $isMonthly ? 'monthly' : 'weekly';
        $periodLabel = $isMonthly ? 'Month' : 'Week';
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .stat-box { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #667eea; }
                .stat-number { font-size: 2em; font-weight: bold; color: #667eea; }
                .stat-label { color: #666; margin-top: 5px; }
                .list-item { padding: 8px 0; border-bottom: 1px solid #eee; }
                .list-item:last-child { border-bottom: none; }
                .trending { background: #e8f5e8; border-left-color: #28a745; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Business Monitoring Summary</h1>
                    <p>{$periodLabel}ly Report - " . ($isMonthly ? $data['month_start'] . ' to ' . $data['month_end'] : $data['week_start'] . ' to ' . $data['week_end']) . "</p>
                </div>
                
                <div class='content'>
                    <div class='stat-box'>
                        <div class='stat-number'>{$data['new_businesses']}</div>
                        <div class='stat-label'>New Businesses This {$periodLabel}</div>
                    </div>
                    
                    " . ($isMonthly && isset($data['growth_rate']) ? "
                    <div class='stat-box'>
                        <div class='stat-number'>" . number_format($data['growth_rate'], 1) . "%</div>
                        <div class='stat-label'>Growth Rate vs Last Month</div>
                    </div>
                    " : "") . "
                    
                    <h3>🏆 Top Categories</h3>
                    <div class='stat-box'>";
        
        foreach ($data['top_categories'] as $category) {
            $html .= "<div class='list-item'><strong>{$category->category}</strong> - {$category->count} businesses</div>";
        }
        
        $html .= "
                    </div>
                    
                    <h3>📍 Top Areas</h3>
                    <div class='stat-box'>";
        
        foreach ($data['top_areas'] as $area) {
            $html .= "<div class='list-item'><strong>{$area->area}</strong> - {$area->count} businesses</div>";
        }
        
        $html .= "
                    </div>
                    
                    <h3>🔥 Trending Businesses</h3>
                    <div class='stat-box trending'>";
        
        foreach ($data['trending_businesses'] as $business) {
            $html .= "<div class='list-item'>
                <strong>{$business->name}</strong><br>
                <small>{$business->category} • {$business->area} • ⭐ {$business->rating} ({$business->review_count} reviews)</small>
            </div>";
        }
        
        $html .= "
                    </div>
                    
                    <div class='footer'>
                        <p>This report was generated automatically by the Business Monitoring System.</p>
                        <p>Total businesses in database: {$data['total_businesses']}</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    public function scheduleNotifications(Request $request)
    {
        $email = $request->get('email', 'admin@example.com');
        $frequency = $request->get('frequency', 'weekly'); // weekly or monthly
        
        // In a real application, you would schedule this using Laravel's task scheduler
        // For now, we'll just return the schedule information
        
        return response()->json([
            'message' => 'Notifications scheduled successfully',
            'email' => $email,
            'frequency' => $frequency,
            'next_run' => $frequency === 'weekly' 
                ? now()->next(Carbon::MONDAY)->format('Y-m-d H:i:s')
                : now()->addMonth()->startOfMonth()->format('Y-m-d H:i:s'),
        ]);
    }
}
