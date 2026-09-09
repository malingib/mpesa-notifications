<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\TalksasaBalanceService;
use App\Services\MpesaCredentialTestService;
use App\Services\MpesaRegisterUrlService;
use App\Models\Merchant;

class SettingsController extends Controller
{
    protected $talksasaBalanceService;
    protected $mpesaCredentialTestService;
    protected $mpesaRegisterUrlService;

    public function __construct(
        TalksasaBalanceService $talksasaBalanceService,
        MpesaCredentialTestService $mpesaCredentialTestService,
        MpesaRegisterUrlService $mpesaRegisterUrlService
    ) {
        $this->talksasaBalanceService = $talksasaBalanceService;
        $this->mpesaCredentialTestService = $mpesaCredentialTestService;
        $this->mpesaRegisterUrlService = $mpesaRegisterUrlService;
    }

    /**
     * Display user settings
     */
    public function index()
    {
        $user = Auth::user();
        
        // Decode settings if they exist
        $settings = $user->settings ?? [];
        
        return view('settings.index', [
            'user' => $user,
            'settings' => $settings,
        ]);
    }

    /**
     * Update SMS settings
     */
    public function updateSmsSettings(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'sender_id' => ['required', 'string', 'max:11'],
            'talksasa_api_token' => ['required', 'string'],
            'notification_numbers' => ['nullable', 'array'],
            'notification_numbers.*' => ['nullable', 'string', 'regex:/^[0-9+]{10,15}$/'],
        ], [
            'sender_id.required' => 'Sender ID is required.',
            'sender_id.max' => 'Sender ID cannot exceed 11 characters.',
            'talksasa_api_token.required' => 'Talksasa API token is required.',
            'notification_numbers.*.regex' => 'Phone number must be 10-15 digits with optional country code.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.index')
                ->withErrors($validator)
                ->withInput();
        }

        // Get current settings
        $settings = $user->settings ?? [];
        
        // Filter out empty notification numbers
        $notificationNumbers = array_filter(
            $request->notification_numbers ?? [],
            fn($number) => !empty(trim($number))
        );
        
        // Update SMS settings
        $settings['sms'] = [
            'sender_id' => $request->sender_id,
            'talksasa_api_token' => $request->talksasa_api_token,
            'notification_numbers' => array_values($notificationNumbers), // Re-index array
        ];

        // Save settings
        $user->settings = $settings;
        $user->save();

        // Clear balance cache when API token changes
        $this->talksasaBalanceService->clearCache($request->talksasa_api_token);

        return redirect()->route('settings.index')
            ->with('success', 'SMS settings updated successfully.');
    }

    /**
     * Update payment account settings
     */
    public function updatePaymentSettings(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'account_number' => ['required', 'string'],
            'account_type' => ['required', 'in:paybill,till'],
            'consumer_key' => ['nullable', 'string'],
            'consumer_secret' => ['nullable', 'string'],
            'passkey' => ['nullable', 'string'],
            'business_shortcode' => ['nullable', 'string'],
        ], [
            'account_number.required' => 'Account number is required.',
            'account_type.required' => 'Account type is required.',
            'account_type.in' => 'Account type must be either paybill or till.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings.index')
                ->withErrors($validator)
                ->withInput();
        }

        // Get current settings
        $settings = $user->settings ?? [];
        
        // Update payment settings
        $settings['payment'] = [
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
            'consumer_key' => $request->consumer_key,
            'consumer_secret' => $request->consumer_secret,
            'passkey' => $request->passkey,
            'business_shortcode' => $request->business_shortcode,
        ];

        // Save settings
        $user->settings = $settings;
        $user->save();

        // Create or update merchant record
        // Use withoutGlobalScopes to ensure merchant is created regardless of tenant context
        $merchant = Merchant::withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $user->id,
                'account_type' => $request->account_type,
                'account_number' => $request->account_number,
            ],
            [
                'account_name' => $request->account_type === 'paybill' 
                    ? 'Paybill ' . $request->account_number 
                    : 'Till ' . $request->account_number,
                'is_active' => true,
                'sms_enabled' => true,
            ]
        );

        Log::info('Merchant created/updated', [
            'merchant_id' => $merchant->id,
            'user_id' => $user->id,
            'account_type' => $request->account_type,
            'account_number' => $request->account_number,
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Payment account settings updated successfully.');
    }

    /**
     * Test Talksasa API connection
     */
    public function testSmsConnection(Request $request)
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];
        
        // Check if token is provided in request (for testing before saving)
        $token = $request->input('token') ?? $settings['sms']['talksasa_api_token'] ?? null;
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Talksasa API token not configured. Please enter your API token first.',
            ], 400);
        }

        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 10,
                'verify' => true,
            ]);
            
            $response = $client->get('https://bulksms.talksasa.com/api/v3/sms', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (isset($data['status']) && $data['status'] === 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! API token is valid.',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Connection failed. Please check your API token.',
            ], 400);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $data = json_decode($response->getBody(), true);
            
            return response()->json([
                'success' => false,
                'message' => $data['message'] ?? 'Invalid API token. Please check your credentials.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get SMS balance (AJAX endpoint)
     * 
     * Returns the current SMS balance from Talksasa API.
     * Uses the API token configured in user settings.
     */
    public function getBalance(Request $request)
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];
        
        if (!isset($settings['sms']['talksasa_api_token'])) {
            return response()->json([
                'success' => false,
                'balance' => 'N/A',
                'message' => 'Talksasa API token not configured. Please configure it in Settings.',
            ]);
        }

        // Clear cache to force fresh fetch on each request
        $this->talksasaBalanceService->clearCache($settings['sms']['talksasa_api_token']);
        
        $balanceData = $this->talksasaBalanceService->getBalance($settings['sms']['talksasa_api_token']);

        if (!$balanceData) {
            \Illuminate\Support\Facades\Log::error('Talksasa balance service returned null', [
                'user_id' => $user->id,
                'has_token' => !empty($settings['sms']['talksasa_api_token']),
            ]);
            
            return response()->json([
                'success' => false,
                'balance' => 'N/A',
                'message' => 'Failed to fetch balance. Please check your API token.',
            ]);
        }

        $formattedBalance = $this->talksasaBalanceService->formatBalance($balanceData);
        
        \Illuminate\Support\Facades\Log::info('Balance fetch result', [
            'success' => $balanceData['success'] ?? false,
            'formatted_balance' => $formattedBalance,
            'raw_data' => $balanceData,
        ]);

        return response()->json([
            'success' => $balanceData['success'] ?? false,
            'balance' => $formattedBalance,
            'message' => $balanceData['message'] ?? null,
            'debug' => [
                'raw_balance' => $balanceData['balance'] ?? null,
                'raw_data' => $balanceData['raw'] ?? null,
            ],
        ]);
    }

    /**
     * Test M-Pesa payment credentials
     */
    public function testPaymentCredentials(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'account_type' => ['required', 'in:paybill,till'],
            'account_number' => ['required', 'string'],
            'consumer_key' => ['nullable', 'string'],
            'consumer_secret' => ['nullable', 'string'],
            'passkey' => ['nullable', 'string'],
            'phone_number' => ['nullable', 'string'],
            'initiate_stk_push' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        // Check if STK push test is requested
        $initiateStkPush = $request->boolean('initiate_stk_push', false);
        $phoneNumber = $request->phone_number;

        // If STK push is requested but no phone number provided, return error
        if ($initiateStkPush && empty($phoneNumber)) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number is required for STK Push test. Enter your phone number to receive the test payment prompt.',
            ], 400);
        }

        // Passkey is required for STK push (both Till and Paybill)
        if ($initiateStkPush && empty($request->passkey)) {
            return response()->json([
                'success' => false,
                'message' => 'Passkey is required for STK Push test (both Till and Paybill require passkey).',
            ], 400);
        }

        // Get Business Shortcode if available (for STK push, use Business Shortcode like URL registration)
        $businessShortcode = $request->business_shortcode;
        
        // CRITICAL: For Till numbers, Business Shortcode is REQUIRED for STK Push
        // Till numbers cannot initiate STK Push without their associated Business Shortcode
        if ($initiateStkPush && $request->account_type === 'till' && empty($businessShortcode)) {
            return response()->json([
                'success' => false,
                'message' => 'Business Shortcode is required for Till number STK Push. Till numbers must use their associated Business Shortcode for STK Push requests.',
            ], 400);
        }
        
        // For STK Push: Always use Business Shortcode if available (required for Till, preferred for Paybill)
        // For Till: Business Shortcode is mandatory
        // For Paybill: Business Shortcode is preferred, but can fall back to account number
        $shortCodeForTest = $businessShortcode ?? $request->account_number;

        Log::info('Testing M-Pesa credentials', [
            'account_type' => $request->account_type,
            'account_number' => $request->account_number,
            'business_shortcode' => $businessShortcode,
            'shortcode_for_test' => $shortCodeForTest,
            'initiate_stk_push' => $initiateStkPush,
            'has_phone' => !empty($phoneNumber),
        ]);

        // For Till numbers, pass the Till number separately for PartyB field
        $tillNumber = ($request->account_type === 'till') ? $request->account_number : null;

        $result = $this->mpesaCredentialTestService->testCredentials(
            $request->account_type,
            $shortCodeForTest, // Use Business Shortcode if available
            $request->consumer_key,
            $request->consumer_secret,
            $request->passkey,
            $phoneNumber,
            $initiateStkPush,
            $tillNumber // Pass Till number for PartyB field
        );

        return response()->json($result);
    }

    /**
     * Register URLs with M-Pesa
     */
    public function registerUrls(Request $request)
    {
        $user = Auth::user();
        $settings = $user->settings ?? [];

        $validator = Validator::make($request->all(), [
            'account_number' => ['required', 'string'],
            'account_type' => ['required', 'in:paybill,till'],
            'consumer_key' => ['required', 'string'],
            'consumer_secret' => ['required', 'string'],
            'confirmation_url' => ['required', 'url'],
            'validation_url' => ['required', 'url'],
            'response_type' => ['nullable', 'in:Completed,Cancelled'],
            'business_shortcode' => ['nullable', 'string'],
        ], [
            'account_number.required' => 'Account number is required.',
            'account_type.required' => 'Account type is required.',
            'consumer_key.required' => 'Consumer Key is required.',
            'consumer_secret.required' => 'Consumer Secret is required.',
            'confirmation_url.required' => 'Confirmation URL is required.',
            'confirmation_url.url' => 'Confirmation URL must be a valid URL.',
            'validation_url.required' => 'Validation URL is required.',
            'validation_url.url' => 'Validation URL must be a valid URL.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'payload' => null,
            ], 400);
        }

        // Use provided URLs or fallback to form values
        $confirmationUrl = $request->confirmation_url;
        $validationUrl = $request->validation_url;
        $responseType = $request->response_type ?? 'Completed';

        // If URLs not provided, use the webhook endpoint
        if (empty($confirmationUrl)) {
            $confirmationUrl = url('/api/webhooks/mpesa/payment');
        }
        if (empty($validationUrl)) {
            $validationUrl = url('/api/webhooks/mpesa/payment');
        }

        // Ensure URLs are HTTPS
        $confirmationUrl = str_replace('http://', 'https://', $confirmationUrl);
        $validationUrl = str_replace('http://', 'https://', $validationUrl);

        // IMPORTANT: Use Business Shortcode for URL registration if provided
        // Consumer Key/Secret is tied to Business Shortcode, not Till number
        // If Business Shortcode is provided, use it; otherwise use account_number
        $shortCodeForRegistration = $request->business_shortcode ?? $request->account_number;

        Log::info('Registering URLs - Request received', [
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
            'business_shortcode_from_request' => $request->business_shortcode,
            'business_shortcode_from_form' => $request->input('business_shortcode'),
            'all_request_data' => $request->except(['consumer_key', 'consumer_secret']),
            'shortcode_for_registration' => $shortCodeForRegistration,
            'consumer_key_prefix' => substr($request->consumer_key ?? '', 0, 10) . '...',
        ]);
        
        // Warn if Business Shortcode is not provided but account_type is till
        if (empty($request->business_shortcode) && $request->account_type === 'till') {
            Log::warning('Registering URLs - Business Shortcode not provided for Till account', [
                'account_number' => $request->account_number,
                'note' => 'If Consumer Key/Secret is tied to Business Shortcode, URL registration may fail',
            ]);
        }

        $result = $this->mpesaRegisterUrlService->registerUrls(
            $shortCodeForRegistration,
            $confirmationUrl,
            $validationUrl,
            $request->consumer_key,
            $request->consumer_secret,
            $responseType
        );

        return response()->json($result);
    }
}
