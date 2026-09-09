<?php

namespace App\Services;

use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Financial Report Service
 * 
 * Generates financial reports: P&L, Cash Flow, Balance Sheet, Tax Reports.
 */
class FinancialReportService
{
    /**
     * Generate Profit & Loss Statement
     */
    public function generateProfitLoss(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Revenue from paid invoices
        $revenue = Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereBetween('paid_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Revenue from payments (if not matched to invoices)
        $unmatchedPayments = Payment::where('user_id', $user->id)
            ->whereDoesntHave('invoicePayments')
            ->whereBetween('transaction_time', [$startDate, $endDate])
            ->sum('amount');

        $totalRevenue = $revenue + $unmatchedPayments;

        // Expenses
        $expenses = Expense::where('user_id', $user->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Expenses by category
        $expensesByCategory = Expense::where('user_id', $user->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->select('category_id', DB::raw('SUM(total_amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        // Gross Profit
        $grossProfit = $totalRevenue - $expenses;

        // Operating Expenses (all expenses for now)
        $operatingExpenses = $expenses;

        // Net Profit
        $netProfit = $grossProfit - $operatingExpenses;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'revenue' => [
                'invoiced' => $revenue,
                'unmatched_payments' => $unmatchedPayments,
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'total' => $expenses,
                'by_category' => $expensesByCategory,
            ],
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
        ];
    }

    /**
     * Generate Cash Flow Statement
     */
    public function generateCashFlow(User $user, Carbon $startDate, Carbon $endDate): array
    {
        // Operating Activities - Cash Inflows
        $cashInflows = Payment::where('user_id', $user->id)
            ->whereBetween('transaction_time', [$startDate, $endDate])
            ->sum('amount');

        // Operating Activities - Cash Outflows (expenses)
        $cashOutflows = Expense::where('user_id', $user->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('total_amount');

        // Net Cash Flow from Operations
        $netCashFlow = $cashInflows - $cashOutflows;

        // Cash flow by period (daily/weekly/monthly)
        $dailyCashFlow = $this->getDailyCashFlow($user, $startDate, $endDate);

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'operating_activities' => [
                'cash_inflows' => $cashInflows,
                'cash_outflows' => $cashOutflows,
                'net_cash_flow' => $netCashFlow,
            ],
            'daily_cash_flow' => $dailyCashFlow,
        ];
    }

    /**
     * Get daily cash flow breakdown
     */
    protected function getDailyCashFlow(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $payments = Payment::where('user_id', $user->id)
            ->whereBetween('transaction_time', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(transaction_time) as date'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $expenses = Expense::where('user_id', $user->id)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(expense_date) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $dailyFlow = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->toDateString();
            $inflow = $payments->get($dateStr)->total ?? 0;
            $outflow = $expenses->get($dateStr)->total ?? 0;

            $dailyFlow[] = [
                'date' => $dateStr,
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $inflow - $outflow,
            ];

            $currentDate->addDay();
        }

        return $dailyFlow;
    }

    /**
     * Generate Balance Sheet
     */
    public function generateBalanceSheet(User $user, Carbon $asOfDate): array
    {
        // Assets
        $cashBalance = Payment::where('user_id', $user->id)
            ->where('transaction_time', '<=', $asOfDate)
            ->sum('amount');

        $expensesTotal = Expense::where('user_id', $user->id)
            ->where('expense_date', '<=', $asOfDate)
            ->sum('total_amount');

        $cashBalance = $cashBalance - $expensesTotal;

        // Accounts Receivable (unpaid invoices)
        $accountsReceivable = Invoice::where('user_id', $user->id)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('issue_date', '<=', $asOfDate)
            ->sum('balance');

        $totalAssets = $cashBalance + $accountsReceivable;

        // Liabilities
        // For now, we don't track liabilities, but structure is ready
        $totalLiabilities = 0;

        // Equity
        $equity = $totalAssets - $totalLiabilities;

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'assets' => [
                'cash' => $cashBalance,
                'accounts_receivable' => $accountsReceivable,
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'total' => $totalLiabilities,
            ],
            'equity' => $equity,
        ];
    }

    /**
     * Generate Tax Report
     */
    public function generateTaxReport(User $user, int $year): array
    {
        $startDate = Carbon::create($year, 1, 1);
        $endDate = Carbon::create($year, 12, 31)->endOfDay();

        // Revenue (taxable income)
        $revenue = Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereYear('paid_date', $year)
            ->sum('total_amount');

        // Add unmatched payments
        $unmatchedPayments = Payment::where('user_id', $user->id)
            ->whereDoesntHave('invoicePayments')
            ->whereYear('transaction_time', $year)
            ->sum('amount');

        $totalRevenue = $revenue + $unmatchedPayments;

        // Expenses (deductible)
        $expenses = Expense::where('user_id', $user->id)
            ->whereYear('expense_date', $year)
            ->sum('total_amount');

        // Taxable Income
        $taxableIncome = $totalRevenue - $expenses;

        // VAT Collected (assuming 16% VAT on invoices)
        $vatCollected = Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereYear('paid_date', $year)
            ->sum(DB::raw('tax_amount'));

        // VAT Paid (on expenses)
        $vatPaid = Expense::where('user_id', $user->id)
            ->whereYear('expense_date', $year)
            ->sum('tax_amount');

        // Net VAT Payable
        $vatPayable = $vatCollected - $vatPaid;

        return [
            'year' => $year,
            'revenue' => [
                'invoiced' => $revenue,
                'unmatched_payments' => $unmatchedPayments,
                'total' => $totalRevenue,
            ],
            'expenses' => $expenses,
            'taxable_income' => $taxableIncome,
            'vat' => [
                'collected' => $vatCollected,
                'paid' => $vatPaid,
                'payable' => $vatPayable,
            ],
            'monthly_breakdown' => $this->getMonthlyTaxBreakdown($user, $year),
        ];
    }

    /**
     * Get monthly tax breakdown
     */
    protected function getMonthlyTaxBreakdown(User $user, int $year): array
    {
        $breakdown = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::create($year, $month, 1);
            $endDate = Carbon::create($year, $month, 1)->endOfMonth();

            $revenue = Invoice::where('user_id', $user->id)
                ->where('status', 'paid')
                ->whereBetween('paid_date', [$startDate, $endDate])
                ->sum('total_amount');

            $expenses = Expense::where('user_id', $user->id)
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->sum('total_amount');

            $breakdown[] = [
                'month' => $month,
                'month_name' => $startDate->format('F'),
                'revenue' => $revenue,
                'expenses' => $expenses,
                'net' => $revenue - $expenses,
            ];
        }

        return $breakdown;
    }

    /**
     * Get financial metrics
     */
    public function getFinancialMetrics(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $pAndL = $this->generateProfitLoss($user, $startDate, $endDate);
        $cashFlow = $this->generateCashFlow($user, $startDate, $endDate);

        // Calculate metrics
        $revenue = $pAndL['revenue']['total'];
        $expenses = $pAndL['expenses']['total'];
        $netProfit = $pAndL['net_profit'];

        // Growth metrics (compare to previous period)
        $previousStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1);
        $previousEnd = $startDate->copy()->subDay();
        $previousPAndL = $this->generateProfitLoss($user, $previousStart, $previousEnd);
        $previousRevenue = $previousPAndL['revenue']['total'];

        $revenueGrowth = $previousRevenue > 0 
            ? (($revenue - $previousRevenue) / $previousRevenue) * 100 
            : 0;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_profit' => $netProfit,
            'profit_margin' => $pAndL['profit_margin'],
            'revenue_growth' => $revenueGrowth,
            'cash_flow' => $cashFlow['operating_activities']['net_cash_flow'],
            'average_daily_revenue' => $revenue / max(1, $startDate->diffInDays($endDate) + 1),
            'expense_ratio' => $revenue > 0 ? ($expenses / $revenue) * 100 : 0,
        ];
    }

    /**
     * Get revenue trends
     */
    public function getRevenueTrends(User $user, Carbon $startDate, Carbon $endDate, string $period = 'daily'): array
    {
        $trends = [];

        if ($period === 'daily') {
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dayStart = $currentDate->copy()->startOfDay();
                $dayEnd = $currentDate->copy()->endOfDay();

                $revenue = Invoice::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('paid_date', [$dayStart, $dayEnd])
                    ->sum('total_amount');

                $trends[] = [
                    'date' => $currentDate->toDateString(),
                    'revenue' => $revenue,
                ];

                $currentDate->addDay();
            }
        } elseif ($period === 'weekly') {
            $currentDate = $startDate->copy()->startOfWeek();
            while ($currentDate->lte($endDate)) {
                $weekEnd = $currentDate->copy()->endOfWeek();

                $revenue = Invoice::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('paid_date', [$currentDate, $weekEnd])
                    ->sum('total_amount');

                $trends[] = [
                    'week_start' => $currentDate->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'revenue' => $revenue,
                ];

                $currentDate->addWeek();
            }
        } elseif ($period === 'monthly') {
            $currentDate = $startDate->copy()->startOfMonth();
            while ($currentDate->lte($endDate)) {
                $monthEnd = $currentDate->copy()->endOfMonth();

                $revenue = Invoice::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('paid_date', [$currentDate, $monthEnd])
                    ->sum('total_amount');

                $trends[] = [
                    'month' => $currentDate->format('Y-m'),
                    'month_name' => $currentDate->format('F Y'),
                    'revenue' => $revenue,
                ];

                $currentDate->addMonth();
            }
        }

        return $trends;
    }
}
