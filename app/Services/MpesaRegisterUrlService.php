<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * M-Pesa Register URL Service
 * 
 * Registers confirmation and validation URLs with M-Pesa API
 * Following M-Pesa C2B Register URL API documentation
 */
class MpesaRegisterUrlService
{
    /**
     * Register URLs with M-Pesa
     * 
     * @param string $shortCode Paybill or Till number
     * @param string $confirmationUrl URL to receive payment confirmations
     * @param string $validationUrl URL to validate payment requests
     * @param string $consumerKey M-Pesa Consumer Key
     * @param string $consumerSecret M-Pesa Consumer Secret
     * @param string $responseType Response type: Completed or Cancelled (default: Completed)
     * @return array
     */
    public function registerUrls(
        string $shortCode,
        string $confirmationUrl,
        string $validationUrl,
        string $consumerKey,
        string $consumerSecret,
        string $responseType = 'Completed'
    ): array {
        try {
            // Validate inputs
            $validation = $this->validateInputs($shortCode, $confirmationUrl, $validationUrl, $responseType);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                    'payload' => null,
                ];
            }

            // Get OAuth token
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

            // Register URLs
            $registerResult = $this->callRegisterUrlApi(
                $baseUrl,
                $accessToken,
                $shortCode,
                $confirmationUrl,
                $validationUrl,
                $responseType
            );

            return $registerResult;

        } catch (\Exception $e) {
            Log::error('M-Pesa Register URL unexpected error', [
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
            
            Log::info('M-Pesa OAuth: Getting access token', [
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
                Log::warning('M-Pesa OAuth failed - no access token', [
                    'response' => $authData,
                    'status_code' => $response->getStatusCode(),
                    'base_url' => $baseUrl,
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to obtain access token. Invalid Consumer Key or Consumer Secret.',
                ];
            }

            Log::info('M-Pesa OAuth: Token obtained successfully', [
                'token_length' => strlen($authData['access_token']),
                'expires_in' => $authData['expires_in'] ?? null,
                'base_url' => $baseUrl,
            ]);

            return [
                'success' => true,
                'access_token' => $authData['access_token'],
                'base_url' => $baseUrl,
            ];

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $body = $response ? json_decode($response->getBody()->getContents(), true) : [];

            Log::error('M-Pesa OAuth client error', [
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
            Log::error('M-Pesa OAuth network error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Network error connecting to M-Pesa API. Check your internet connection.',
            ];
        }
    }

    /**
     * Call M-Pesa Register URL API
     * 
     * @param string $baseUrl
     * @param string $accessToken
     * @param string $shortCode
     * @param string $confirmationUrl
     * @param string $validationUrl
     * @param string $responseType
     * @return array
     */
    private function callRegisterUrlApi(
        string $baseUrl,
        string $accessToken,
        string $shortCode,
        string $confirmationUrl,
        string $validationUrl,
        string $responseType
    ): array {
        try {
            $client = new Client([
                'timeout' => 30,
                'verify' => true,
            ]);

            $registerUrl = $baseUrl . '/mpesa/c2b/v1/registerurl';

            $requestPayload = [
                'ShortCode' => $shortCode,
                'ResponseType' => $responseType,
                'ConfirmationURL' => $confirmationUrl,
                'ValidationURL' => $validationUrl,
            ];

            Log::info('Registering URLs with M-Pesa', [
                'base_url' => $baseUrl,
                'short_code' => $shortCode,
                'short_code_type' => strlen($shortCode) >= 6 ? 'Business Shortcode (Paybill)' : 'Till Number',
                'confirmation_url' => $confirmationUrl,
                'validation_url' => $validationUrl,
                'response_type' => $responseType,
                'token_length' => strlen($accessToken),
                'token_prefix' => substr($accessToken, 0, 20) . '...',
                'api_endpoint' => $registerUrl,
            ]);

            // IMPORTANT: M-Pesa requires the ShortCode to match the Consumer Key/Secret
            // If you have both Business Shortcode and Till number, use Business Shortcode for URL registration
            // Business Shortcode is typically used for Paybill, Till number for Till accounts
            // The Consumer Key/Secret is tied to a specific ShortCode in M-Pesa Developer Portal
            
            // Debug: Log the exact payload being sent
            Log::info('M-Pesa Register URL Request Payload', [
                'payload' => $requestPayload,
                'headers' => [
                    'Authorization' => 'Bearer ' . substr($accessToken, 0, 20) . '...',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $response = $client->post($registerUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $requestPayload,
            ]);

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            Log::info('M-Pesa Register URL response', [
                'status_code' => $response->getStatusCode(),
                'response' => $responseData,
            ]);

            // Check if registration was successful
            if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
                return [
                    'success' => true,
                    'message' => 'URLs registered successfully! ' . ($responseData['ResponseDescription'] ?? ''),
                    'payload' => $responseData,
                ];
            }

            // Registration failed
            $errorMessage = $responseData['ResponseDescription'] ?? $responseData['errorMessage'] ?? 'Failed to register URLs.';
            
            // Provide specific guidance for common errors
            if (isset($responseData['errorCode']) && $responseData['errorCode'] === '401.003.01') {
                $errorMessage .= ' This usually means: 1) The ShortCode (Till/Paybill number) does not match the Consumer Key/Secret, or 2) The Consumer Key/Secret are incorrect.';
            }
            
            return [
                'success' => false,
                'message' => $errorMessage,
                'payload' => $responseData,
            ];

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 0;
            $body = $response ? json_decode($response->getBody()->getContents(), true) : [];

            Log::error('M-Pesa Register URL client error', [
                'status' => $statusCode,
                'response' => $body,
                'short_code' => $shortCode,
                'base_url' => $baseUrl,
            ]);

            $errorMessage = $body['errorMessage'] ?? $body['ResponseDescription'] ?? $e->getMessage();
            
            // Provide specific guidance for Invalid Access Token error
            if (isset($body['errorCode']) && $body['errorCode'] === '401.003.01') {
                $errorMessage .= ' Common causes: ';
                $errorMessage .= '1) ShortCode mismatch - The Till/Paybill number must match the Consumer Key/Secret. ';
                $errorMessage .= '2) Invalid credentials - Consumer Key or Consumer Secret is incorrect. ';
                $errorMessage .= '3) Consumer Key/Secret don\'t have URL registration permissions.';
            }
            
            return [
                'success' => false,
                'message' => 'M-Pesa API error: ' . $errorMessage,
                'payload' => $body,
            ];

        } catch (RequestException $e) {
            Log::error('M-Pesa Register URL network error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Network error connecting to M-Pesa API. Check your internet connection.',
                'payload' => null,
            ];
        }
    }

    /**
     * Validate inputs
     * 
     * @param string $shortCode
     * @param string $confirmationUrl
     * @param string $validationUrl
     * @param string $responseType
     * @return array
     */
    private function validateInputs(
        string $shortCode,
        string $confirmationUrl,
        string $validationUrl,
        string $responseType
    ): array {
        if (empty($shortCode)) {
            return [
                'valid' => false,
                'message' => 'Short Code (Paybill/Till number) is required.',
            ];
        }

        if (empty($confirmationUrl)) {
            return [
                'valid' => false,
                'message' => 'Confirmation URL is required.',
            ];
        }

        if (empty($validationUrl)) {
            return [
                'valid' => false,
                'message' => 'Validation URL is required.',
            ];
        }

        // Validate URL format
        if (!filter_var($confirmationUrl, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'message' => 'Invalid Confirmation URL format.',
            ];
        }

        if (!filter_var($validationUrl, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'message' => 'Invalid Validation URL format.',
            ];
        }

        // URLs must be HTTPS
        if (parse_url($confirmationUrl, PHP_URL_SCHEME) !== 'https') {
            return [
                'valid' => false,
                'message' => 'Confirmation URL must use HTTPS.',
            ];
        }

        if (parse_url($validationUrl, PHP_URL_SCHEME) !== 'https') {
            return [
                'valid' => false,
                'message' => 'Validation URL must use HTTPS.',
            ];
        }

        // Validate response type
        if (!in_array($responseType, ['Completed', 'Cancelled'])) {
            return [
                'valid' => false,
                'message' => 'Response Type must be either "Completed" or "Cancelled".',
            ];
        }

        return ['valid' => true];
    }
}
