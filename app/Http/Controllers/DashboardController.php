<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requests as ServiceRequest;
use App\Models\Category;
use App\Models\ManpowerDeployment;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get total requests by category
     */
    public function getTotalRequestsByCategory()
    {
        $data = Category::withCount('requests')->get(); // Now the 'requests' method will work
    
        return response()->json([
            'isSuccess' => true,
            'categories' => $data
        ]);
    }
    

    /**
     * Get total requests by status
     */
    public function getTotalRequestsByStatus()
    {
        $data = ServiceRequest::selectRaw("status, COUNT(*) as total")
                     ->groupBy('status')
                     ->pluck('total', 'status');

        return response()->json([
            'isSuccess' => true,
            'requests_by_status' => $data
        ]);
    }

    /**
     * Get total requests per month
     */
    public function getMonthlyRequests()
    {
        $data = ServiceRequest::selectRaw("MONTHNAME(created_at) as month, COUNT(*) as total")
                     ->groupBy('month')
                     ->pluck('total', 'month');

        return response()->json([
            'isSuccess' => true,
            'monthly_requests' => $data
        ]);
    }

    /**
     * Get total manpower by team
     */
    public function getTotalManpowerByCategory()
    {
        $data = Category::withCount('personnel')->get();
    
        return response()->json([
            'isSuccess' => true,
            'manpower_by_category' => $data
        ]);
    }
    

    /**
     * Get all dashboard statistics in one response
     */
    public function getDashboardSummary()
    {
        return response()->json([
            'isSuccess' => true,
            'total_requests_by_category' => Category::withCount('requests')->get(),
            'total_requests_by_status' => ServiceRequest::selectRaw("status, COUNT(*) as total")
                                                    ->groupBy('status')
                                                    ->pluck('total', 'status'),
            'monthly_requests' => ServiceRequest::selectRaw("MONTHNAME(created_at) as month, COUNT(*) as total")
                                                    ->groupBy('month')
                                                    ->pluck('total', 'month'),
            // Get manpower stats based on the 'personnel' relationship in Category
            'manpower_stats' => Category::withCount('personnel')->get()->map(function ($category) {
                return [
                    'category_name' => $category->category_name,
                    'manpower_count' => $category->personnel_count // Using the count of personnel per category
                ];
            }),
        ]);
    }
}