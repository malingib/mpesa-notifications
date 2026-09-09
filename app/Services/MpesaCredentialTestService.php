<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use App\Services\MpesaStkPushService;

/**
 * M-Pesa Credential Test Service
 * 
 * Tests M-Pesa API credentials by attempting authentication
 */
class MpesaCredentialTestService
{
    public function __construct(
        private MpesaStkPushService $stkPushService
    ) {}
    /**
     * Test M-Pesa credentials
     * 
     * @param string $accountType paybill or till
     * @param string $accountNumber
     * @param string|null $consumerKey
     * @param string|null $consumerSecret
     * @param string|null $passkey Only required for paybill
     * @param string|null $phoneNumber Phone number for STK push test (optional)
     * @param bool $initiateStkPush Whether to initiate STK push test (default: false)
     * @return array
     */
    public function testCredentials(
        string $accountType,
        string $accountNumber,
        ?string $consumerKey = null,
        ?string $consumerSecret = null,
        ?string $passkey = null,
        ?string $phoneNumber = null,
        bool $initiateStkPush = false,
        ?string $tillNumber = null // Till number (for Till accounts, separate from Business Shortcode)
    ): array {
        // Validate required fields
        if (empty($accountNumber)) {
            return [
                'success' => false,
                'message' => 'Account number is required.',
            ];
        }

        // For paybill, passkey is required
        if ($accountType === 'paybill' && empty($passkey)) {
            return [
                'success' => false,
                'message' => 'Passkey is required for Paybill accounts.',
            ];
        }

        // If credentials are provided, test authentication
        if (!empty($consumerKey) && !empty($consumerSecret)) {
            // First test basic authentication
            $authResult = $this->testAuthentication($consumerKey, $consumerSecret, $accountType);
            
            if (!$authResult['success']) {
                return $authResult;
            }

            // If STK push test is requested and phone number is provided
            if ($initiateStkPush && !empty($phoneNumber)) {
                // Validate phone number format
                $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
                if (!$normalizedPhone) {
                    return [
                        'success' => false,
                        'message' => 'Invalid phone number format. Use format: 254712345678 or 0712345678',
                    ];
                }

                // For Till numbers, use Business Shortcode if available, otherwise use account number
                // For Paybill, use Business Shortcode if available, otherwise use account number
                // Note: Controller passes Business Shortcode as accountNumber when available
                $shortCodeForStk = $accountNumber;
                
                // Get callback URL
                $callbackUrl = url('/api/webhooks/mpesa/payment');

                // IMPORTANT: Both Till and Paybill require passkey for STK Push
                // Till numbers use Business Shortcode + Passkey in password generation
                // The difference is only in TransactionType (CustomerBuyGoodsOnline vs CustomerPayBillOnline)
                $passkeyForStk = $passkey ?? '';
                
                if (empty($passkeyForStk)) {
                    return [
                        'success' => false,
                        'message' => 'Passkey is required for STK Push test (both Till and Paybill require passkey).',
                    ];
                }

                // For Till numbers, use the Till number for PartyB
                // BusinessShortCode = Business Shortcode (from accountNumber parameter)
                // PartyB = Till number (from tillNumber parameter)
                $tillNumberForPartyB = ($accountType === 'till' && !empty($tillNumber)) ? $tillNumber : '';

                Log::info('Initiating STK Push for credential test', [
                    'short_code' => $shortCodeForStk,
                    'account_type' => $accountType,
                    'has_passkey' => !empty($passkeyForStk),
                    'phone_masked' => substr($normalizedPhone, 0, 5) . '****' . substr($normalizedPhone, -2),
                    'till_number_for_partyb' => $tillNumberForPartyB,
                ]);

                // Initiate STK push for 5 KES
                $stkResult = $this->stkPushService->initiateStkPush(
                    $shortCodeForStk, // Business Shortcode
                    $normalizedPhone,
                    5.00, // 5 KES test amount
                    $consumerKey,
                    $consumerSecret,
                    $passkeyForStk,
                    $callbackUrl,
                    'Credential Test',
                    'Testing M-Pesa credentials',
                    $accountType, // Pass account type to STK Push service
                    $tillNumberForPartyB // Till number for PartyB (if Till account)
                );

                if ($stkResult['success']) {
                    return [
                        'success' => true,
                        'message' => 'Credentials are valid! STK Push initiated. Check your phone (' . substr($normalizedPhone, -4) . ') to complete the 5 KES payment.',
                        'stk_push_initiated' => true,
                        'checkout_request_id' => $stkResult['checkout_request_id'] ?? null,
                        'customer_message' => $stkResult['customer_message'] ?? null,
                        'payload' => $stkResult['payload'] ?? null,
                    ];
                } else {
                    // STK push failed, but auth succeeded
                    return [
                        'success' => false,
                        'message' => 'Authentication successful, but STK Push failed: ' . ($stkResult['message'] ?? 'Unknown error'),
                        'auth_successful' => true,
                        'stk_push_failed' => true,
                        'payload' => $stkResult['payload'] ?? null,
                    ];
                }
            }

            // Just return auth result if STK push not requested
            return $authResult;
        }

        // If no credentials provided, just validate account number format
        return $this->validateAccountNumber($accountNumber, $accountType);
    }

    /**
     * Normalize phone number to E.164 format (254XXXXXXXXX)
     * 
     * @param string $phoneNumber
     * @return string|null
     */
    private function normalizePhoneNumber(string $phoneNumber): ?string
    {
        // Remove spaces and special characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Convert Kenyan format (254...) or (07...) to 254...
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '0') {
            return '254' . substr($phoneNumber, 1);
        } elseif (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            return $phoneNumber;
        } elseif (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 1) === '0') {
            return '254' . substr($phoneNumber, 1);
        }
        
        // If already in correct format
        if (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            return $phoneNumber;
        }
        
        return null;
    }

    /**
     * Test M-Pesa API authentication
     * 
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $accountType
     * @return array
     */
    private function testAuthentication(string $consumerKey, string $consumerSecret, string $accountType): array
    {
        try {
            // Always use production M-Pesa API
            $baseUrl = 'https://api.safaricom.co.ke';

            $client = new Client([
                'timeout' => 10,
                'verify' => true,
            ]);

            // Step 1: Get OAuth token
            $authUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
            
            $response = $client->get($authUrl, [
                'auth' => [$consumerKey, $consumerSecret],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $authData = json_decode($response->getBody()->getContents(), true);

            if (!isset($authData['access_token'])) {
                Log::warning('M-Pesa authentication failed - no access token', [
                    'response' => $authData,
                    'status_code' => $response->getStatusCode(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Authentication failed. Invalid Consumer Key or Consumer Secret.',
                ];
            }

            // Token obtained successfully - credentials are valid
            Log::info('M-Pesa credentials test successful', [
                'account_type' => $accountType,
                'has_token' => !empty($authData['access_token']),
                'token_expires_in' => $authData['expires_in'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Credentials are valid! Authentication successful. Access token obtained.',
                'token_obtained' => true,
            ];

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);

            Log::error('M-Pesa authentication client error', [
                'status' => $statusCode,
                'response' => $body,
            ]);

            if ($statusCode === 401) {
                return [
                    'success' => false,
                    'message' => 'Invalid credentials. Please check your Consumer Key and Consumer Secret.',
                ];
            }

            return [
                'success' => false,
                'message' => $body['error_description'] ?? 'Authentication failed. Please check your credentials.',
            ];

        } catch (RequestException $e) {
            Log::error('M-Pesa authentication request failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed. Please check your internet connection and try again.',
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa authentication unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate account number format
     * 
     * @param string $accountNumber
     * @param string $accountType
     * @return array
     */
    private function validateAccountNumber(string $accountNumber, string $accountType): array
    {
        // Basic validation
        if (!preg_match('/^\d+$/', $accountNumber)) {
            return [
                'success' => false,
                'message' => 'Account number must contain only digits.',
            ];
        }

        // Paybill numbers are typically 6 digits, Till numbers are 5 digits
        $length = strlen($accountNumber);
        
        if ($accountType === 'paybill' && ($length < 5 || $length > 7)) {
            return [
                'success' => false,
                'message' => 'Paybill number should be 5-7 digits.',
            ];
        }

        if ($accountType === 'till' && ($length < 4 || $length > 6)) {
            return [
                'success' => false,
                'message' => 'Till number should be 4-6 digits.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Account number format is valid. Add credentials to test authentication.',
        ];
    }
}
