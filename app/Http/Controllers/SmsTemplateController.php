<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Services\SmsTemplateService;

class SmsTemplateController extends Controller
{
    /**
     * Display user's SMS templates (optimized)
     */
    public function index()
    {
        $user = Auth::user();
        
        // Cache for 5 minutes
        $cacheKey = "user_sms_templates_{$user->id}_" . now()->format('Y-m-d-H-i');
        
        $data = Cache::remember($cacheKey, 300, function () use ($user) {
            return $this->getTemplatesData($user);
        });

        return view('sms-templates.index', $data);
    }

    /**
     * Show the form for creating a new template
     */
    public function create()
    {
        return view('sms-templates.create', [
            'availableVariables' => $this->getAvailableVariables(),
        ]);
    }

    /**
     * Store a newly created template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ], [
            'name.required' => 'Template name is required.',
            'message.required' => 'Template message is required.',
            'message.max' => 'Template message cannot exceed 500 characters.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('sms-templates.create')
                ->withErrors($validator)
                ->withInput();
        }

        $user = Auth::user();

        // If setting as default, unset other defaults
        if ($request->boolean('is_default')) {
            SmsTemplate::where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // Extract placeholders from template
        preg_match_all('/\{([^}]+)\}/', $request->message, $matches);
        $placeholders = array_unique($matches[1] ?? []);

        $template = SmsTemplate::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'message' => $request->message,
            'placeholders' => $placeholders,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ]);

        // Clear cache
        Cache::forget("user_sms_templates_{$user->id}_" . now()->format('Y-m-d-H-i'));

        return redirect()->route('sms-templates.index')
            ->with('success', 'SMS template created successfully.');
    }

    /**
     * Show the form for editing a template
     */
    public function edit($id)
    {
        $user = Auth::user();
        $template = SmsTemplate::where('user_id', $user->id)->findOrFail($id);

        return view('sms-templates.edit', [
            'template' => $template,
            'availableVariables' => $this->getAvailableVariables(),
        ]);
    }

    /**
     * Update a template
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $template = SmsTemplate::where('user_id', $user->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ], [
            'name.required' => 'Template name is required.',
            'message.required' => 'Template message is required.',
            'message.max' => 'Template message cannot exceed 500 characters.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('sms-templates.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        // If setting as default, unset other defaults
        if ($request->boolean('is_default')) {
            SmsTemplate::where('user_id', $user->id)
                ->where('id', '!=', $id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        // Extract placeholders from template
        preg_match_all('/\{([^}]+)\}/', $request->message, $matches);
        $placeholders = array_unique($matches[1] ?? []);

        $template->update([
            'name' => $request->name,
            'message' => $request->message,
            'placeholders' => $placeholders,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default', false),
        ]);

        // Clear cache
        Cache::forget("user_sms_templates_{$user->id}_" . now()->format('Y-m-d-H-i'));

        return redirect()->route('sms-templates.index')
            ->with('success', 'SMS template updated successfully.');
    }

    /**
     * Delete a template
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $template = SmsTemplate::where('user_id', $user->id)->findOrFail($id);
        
        $template->delete();

        // Clear cache
        Cache::forget("user_sms_templates_{$user->id}_" . now()->format('Y-m-d-H-i'));

        return redirect()->route('sms-templates.index')
            ->with('success', 'SMS template deleted successfully.');
    }

    /**
     * Preview template with sample data
     */
    public function preview(Request $request)
    {
        $templateService = app(SmsTemplateService::class);
        
        $template = $request->input('message', '');
        
        // Sample variables for preview
        $sampleVariables = [
            'amount' => 'KES 1,234.56',
            'amount_raw' => '1234.56',
            'currency' => 'KES',
            'receipt' => 'ABC123XYZ',
            'transaction_id' => 'TXN123456',
            'account_name' => 'My Business',
            'account_number' => '123456',
            'payer_name' => 'John Doe',
            'payer_phone' => '254712345678',
            'reference' => 'INV001',
            'date' => date('d/m/Y'),
            'time' => date('H:i'),
            'datetime' => date('d/m/Y H:i'),
            'description' => 'Payment received',
        ];

        try {
            $preview = $templateService->render($template, $sampleVariables);
            
            return response()->json([
                'success' => true,
                'preview' => $preview,
                'length' => strlen($preview),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get optimized templates data
     */
    private function getTemplatesData($user): array
    {
        // Stats
        $totalTemplates = SmsTemplate::where('user_id', $user->id)->count();
        $activeTemplates = SmsTemplate::where('user_id', $user->id)->where('is_active', true)->count();

        // Templates list (optimized)
        $templates = SmsTemplate::where('user_id', $user->id)
            ->select('id', 'name', 'message', 'is_active', 'is_default', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'totalTemplates' => $totalTemplates,
            'activeTemplates' => $activeTemplates,
            'templates' => $templates,
        ];
    }

    /**
     * Get available template variables
     */
    private function getAvailableVariables(): array
    {
        return [
            ['tag' => '{amount}', 'description' => 'Formatted amount with currency (e.g., KES 1,234.56)'],
            ['tag' => '{amount_raw}', 'description' => 'Raw amount without formatting (e.g., 1234.56)'],
            ['tag' => '{currency}', 'description' => 'Currency code (e.g., KES)'],
            ['tag' => '{receipt}', 'description' => 'Receipt number'],
            ['tag' => '{transaction_id}', 'description' => 'Transaction ID'],
            ['tag' => '{account_name}', 'description' => 'Merchant account name'],
            ['tag' => '{account_number}', 'description' => 'Paybill/Till number'],
            ['tag' => '{payer_name}', 'description' => 'Name of person who sent the payment'],
            ['tag' => '{payer_phone}', 'description' => 'Phone number of payer'],
            ['tag' => '{reference}', 'description' => 'Customer reference'],
            ['tag' => '{date}', 'description' => 'Transaction date (dd/mm/yyyy)'],
            ['tag' => '{time}', 'description' => 'Transaction time (HH:mm)'],
            ['tag' => '{datetime}', 'description' => 'Full date and time'],
            ['tag' => '{description}', 'description' => 'Transaction description'],
        ];
    }
}
