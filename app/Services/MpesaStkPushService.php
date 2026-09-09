<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * M-Pesa STK Push Service
 * 
 * Initiates STK Push (Lipa na M-Pesa Online) payments
 */
class MpesaStkPushService
{
    /**
     * Initiate STK Push payment
     * 
     * @param string $shortCode Business Shortcode or Till number
     * @param string $phoneNumber Customer phone number (254XXXXXXXXX format)
     * @param float $amount Amount to charge
     * @param string $consumerKey M-Pesa Consumer Key
     * @param string $consumerSecret M-Pesa Consumer Secret
     * @param string $passkey M-Pesa Passkey (required for Paybill)
     * @param string $callbackUrl Callback URL for payment confirmation
     * @param string $accountReference Account reference (optional)
     * @param string $transactionDesc Transaction description (optional)
     * @return array
     */
    public function initiateStkPush(
        string $shortCode,
        string $phoneNumber,
        float $amount,
        string $consumerKey,
        string $consumerSecret,
        string $passkey = '',
        string $callbackUrl = '',
        string $accountReference = 'Test Payment',
        string $transactionDesc = 'Credential Test',
        string $accountType = 'paybill', // 'paybill' or 'till'
        string $tillNumber = '' // Till number (if accountType is 'till')
    ): array {
        try {
            // Get access token
            $tokenResult = $this->getAccessToken($consumerKey, $consumerSecret);
            if (!$tokenResult['success']) {
                return [
                    'success' => false,
                    'message' => $tokenResult['message'],
                    'payload' => null,
                ];
            }

            $accessToken = $tokenResult['access_token'];
            $baseUrl = $tokenResult['base_url'];

            // Determine transaction type based on account type
            // Till numbers use CustomerBuyGoodsOnline
            // Paybill uses CustomerPayBillOnline
            $isTillNumber = $accountType === 'till';
            $transactionType = $isTillNumber ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline';

            // Generate password (Base64 encoded)
            // For Paybill: Password = Base64(BusinessShortCode + Passkey + Timestamp)
            // For Till: Password = Base64(BusinessShortCode + Passkey + Timestamp)
            // IMPORTANT: Both Till and Paybill use passkey in password generation
            // IMPORTANT: For Till numbers, shortCode MUST be the Business Shortcode, not the Till number
            $timestamp = date('YmdHis');
            // Both Till and Paybill use passkey in password
            // The difference is only in TransactionType (CustomerBuyGoodsOnline vs CustomerPayBillOnline)
            $password = base64_encode($shortCode . $passkey . $timestamp);

            // Prepare STK Push request
            // For Till numbers: BusinessShortCode = Business Shortcode, PartyB = Till number (if provided)
            // For Paybill: BusinessShortCode = Business Shortcode/Paybill, PartyB = Business Shortcode/Paybill
            $partyB = $isTillNumber && !empty($tillNumber) ? $tillNumber : $shortCode;
            
            $requestPayload = [
                'BusinessShortCode' => $shortCode, // Always use Business Shortcode
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => $transactionType,
                'Amount' => (int) $amount,
                'PartyA' => $phoneNumber, // Customer phone number
                'PartyB' => $partyB, // For Till: Till number, For Paybill: Business Shortcode
                'PhoneNumber' => $phoneNumber, // Customer phone number (must match PartyA)
                'CallBackURL' => $callbackUrl ?: url('/api/webhooks/mpesa/payment'),
                'AccountReference' => $accountReference,
                'TransactionDesc' => $transactionDesc,
            ];

            Log::info('Initiating STK Push', [
                'short_code' => $shortCode,
                'phone_number' => substr($phoneNumber, 0, 5) . '****' . substr($phoneNumber, -2),
                'amount' => $amount,
                'base_url' => $baseUrl,
                'transaction_type' => $transactionType,
                'is_till' => $isTillNumber,
                'has_passkey' => !empty($passkey),
                'password_length' => strlen($password),
                'timestamp' => $timestamp,
            ]);

            // Make STK Push request
            $client = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);

            $stkPushUrl = $baseUrl . '/mpesa/stkpush/v1/processrequest';

            // Log full request for debugging
            Log::info('STK Push request payload', [
                'url' => $stkPushUrl,
                'payload' => $requestPayload,
                'token_prefix' => substr($accessToken, 0, 20) . '...',
            ]);

            $response = $client->post($stkPushUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $requestPayload,
            ]);

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            Log::info('STK Push response', [
                'status_code' => $response->getStatusCode(),
                'response' => $responseData,
            ]);

            // Check if STK Push was initiated successfully
            if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
                return [
                    'success' => true,
                    'message' => 'STK Push initiated successfully! Check your phone to complete the payment.',
                    'payload' => $responseData,
                    'checkout_request_id' => $responseData['CheckoutRequestID'] ?? null,
                    'customer_message' => $responseData['CustomerMessage'] ?? 'STK Push sent to your phone',
                ];
            }

            // STK Push failed
            return [
                'success' => false,
                'message' => $responseData['CustomerMessage'] ?? $responseData['errorMessage'] ?? 'Failed to initiate STK Push.',
                'payload' => $responseData,
            ];

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $body = $response ? json_decode($response->getBody()->getContents(), true) : [];

            Log::error('STK Push client error', [
                'status' => $statusCode,
                'response' => $body,
                'short_code' => $shortCode,
                'base_url' => $baseUrl,
                'transaction_type' => $transactionType ?? 'unknown',
                'error_message' => $e->getMessage(),
            ]);

            // Provide more specific error messages
            $errorMessage = $body['errorMessage'] ?? $body['CustomerMessage'] ?? $e->getMessage();
            
            if ($statusCode === 404 && isset($body['errorCode']) && $body['errorCode'] === '404.001.03') {
                $errorMessage = 'Invalid Access Token. Common causes: ' .
                    '1) BusinessShortCode mismatch - The ShortCode must match the Consumer Key/Secret. ' .
                    '2) Token expired or invalid. ' .
                    '3) Consumer Key/Secret are incorrect or don\'t have STK Push permissions.';
            }

            return [
                'success' => false,
                'message' => 'M-Pesa API error: ' . $errorMessage,
                'payload' => $body,
            ];

        } catch (RequestException $e) {
            Log::error('STK Push network error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Network error connecting to M-Pesa API. Check your internet connection.',
                'payload' => null,
            ];

        } catch (\Exception $e) {
            Log::error('STK Push unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
                'payload' => null,
            ];
        }
    }

    /**
     * Get OAuth access token
     * 
     * @param string $consumerKey
     * @param string $consumerSecret
     * @return array
     */
    private function getAccessToken(string $consumerKey, string $consumerSecret): array
    {
        try {
            // Always use production M-Pesa API
            $baseUrl = 'https://api.safaricom.co.ke';
            
            Log::info('STK Push: Getting access token', [
                'base_url' => $baseUrl,
                'consumer_key_prefix' => substr($consumerKey, 0, 10) . '...',
            ]);

            $client = new Client([
                'timeout' => 10,
                'verify' => true,
            ]);

            $authUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';

            $response = $client->get($authUrl, [
                'auth' => [$consumerKey, $consumerSecret],
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $authData = json_decode($response->getBody()->getContents(), true);

            if (!isset($authData['access_token'])) {
                return [
                    'success' => false,
                    'message' => 'Failed to obtain access token. Invalid Consumer Key or Consumer Secret.',
                ];
            }

            return [
                'success' => true,
                'access_token' => $authData['access_token'],
                'base_url' => $baseUrl,
            ];

        } catch (\Exception $e) {
            Log::error('M-Pesa OAuth error in STK Push', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
        }
    }
}
