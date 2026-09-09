<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class FinancialDashboardController extends Controller
{
    protected FinancialReportService $financialReportService;

    public function __construct(FinancialReportService $financialReportService)
    {
        $this->financialReportService = $financialReportService;
    }

    /**
     * Display financial dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Default to current month
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        // Get financial metrics
        $metrics = $this->financialReportService->getFinancialMetrics($user, $startDate, $endDate);

        // Get revenue trends (daily for current period)
        $revenueTrends = $this->financialReportService->getRevenueTrends($user, $startDate, $endDate, 'daily');

        // Get P&L summary
        $profitLoss = $this->financialReportService->generateProfitLoss($user, $startDate, $endDate);

        // Get cash flow summary
        $cashFlow = $this->financialReportService->generateCashFlow($user, $startDate, $endDate);

        return view('financial.dashboard', compact(
            'metrics',
            'revenueTrends',
            'profitLoss',
            'cashFlow',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Generate Profit & Loss report
     */
    public function profitLoss(Request $request)
    {
        $user = Auth::user();

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $report = $this->financialReportService->generateProfitLoss($user, $startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('financial.profit-loss', compact('report', 'startDate', 'endDate'));
    }

    /**
     * Generate Cash Flow report
     */
    public function cashFlow(Request $request)
    {
        $user = Auth::user();

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $startDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);

        $report = $this->financialReportService->generateCashFlow($user, $startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('financial.cash-flow', compact('report', 'startDate', 'endDate'));
    }

    /**
     * Generate Balance Sheet
     */
    public function balanceSheet(Request $request)
    {
        $user = Auth::user();

        $asOfDate = $request->input('as_of_date', Carbon::today());
        $asOfDate = Carbon::parse($asOfDate);

        $report = $this->financialReportService->generateBalanceSheet($user, $asOfDate);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('financial.balance-sheet', compact('report', 'asOfDate'));
    }

    /**
     * Generate Tax Report
     */
    public function taxReport(Request $request)
    {
        $user = Auth::user();

        $year = $request->input('year', Carbon::now()->year);

        $report = $this->financialReportService->generateTaxReport($user, $year);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return view('financial.tax-report', compact('report', 'year'));
    }

    /**
     * Get revenue trends (API endpoint)
     */
    public function revenueTrends(Request $request)
    {
        $user = Auth::user();

        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->startOfMonth()));
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()->endOfMonth()));
        $period = $request->input('period', 'daily'); // daily, weekly, monthly

        $trends = $this->financialReportService->getRevenueTrends($user, $startDate, $endDate, $period);

        return response()->json($trends);
    }
}
