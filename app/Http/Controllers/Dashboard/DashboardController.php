<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Domains\Dashboard\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
  protected DashboardService $dashboardService;

  public function __construct(DashboardService $dashboardService)
  {
    $this->dashboardService = $dashboardService;
  }

  public function index(Request $request)
  {
    $data = $this->dashboardService->getDashboardData();
    return response()->json($data);
  }

  public function getStats(Request $request)
  {
    $stats = $this->dashboardService->getStats();
    return response()->json($stats);
  }

  public function getTransactions(Request $request)
  {
    $transactions = $this->dashboardService->getTransactions();
    return response()->json($transactions);
  }

  public function getPointsPrograms(Request $request)
  {
    $pointsPrograms = $this->dashboardService->getPointsPrograms();
    return response()->json($pointsPrograms);
  }

  public function getPointsByCategory(Request $request)
  {
    $pointsByCategory = $this->dashboardService->getPointsByCategory();
    return response()->json($pointsByCategory);
  }

  public function getMonthlySpent(Request $request)
  {
    $monthlySpent = $this->dashboardService->getMonthlySpent();
    return response()->json($monthlySpent);
  }

  public function getRecommendations(Request $request)
  {
    $recommendations = $this->dashboardService->getRecommendations();
    return response()->json($recommendations);
  }
}