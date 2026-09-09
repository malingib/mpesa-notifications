<?php

namespace App\Services;

use App\Exceptions\TalksasaSmsException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Talksasa Bulk SMS Service
 * 
 * Production-grade service for Talksasa Bulk SMS API integration.
 * Handles authentication, balance checking, retry logic, and error handling.
 */
class TalksasaSmsService
{
    private Client $httpClient;
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $senderId;
    private int $timeout;
    private int $maxRetries;
    private int $retryDelay;
    private float $lowBalanceThreshold;

    private const TOKEN_CACHE_KEY = 'talksasa_api_token';
    private const TOKEN_CACHE_TTL = 3600; // 1 hour

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('talksasa.sms.api_url', 'https://api.talksasa.com/v1'), '/');
        $this->apiKey = Config::get('talksasa.sms.api_key');
        $this->apiSecret = Config::get('talksasa.sms.api_secret');
        $this->senderId = Config::get('talksasa.sms.sender_id', 'TALKSASA');
        $this->timeout = Config::get('talksasa.sms.timeout', 30);
        $this->maxRetries = Config::get('talksasa.sms.retry_attempts', 3);
        $this->retryDelay = Config::get('talksasa.sms.retry_delay', 1);
        $this->lowBalanceThreshold = Config::get('talksasa.sms.low_balance_threshold', 100.0);

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Send SMS message
     * 
     * @param string $phoneNumber Recipient phone number (E.164 format)
     * @param string $message SMS message text
     * @param array $options Additional options (sender_id, api_token, etc.)
     * @return bool Success status
     * @throws TalksasaSmsException
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): bool
    {
        $attempt = 0;
        $lastException = null;

        // Check if API token is provided (per-user tokens)
        $hasApiToken = !empty($options['api_token']);
        
        Log::info('SMS send attempt started', [
            'phone_number' => $this->maskPhone($phoneNumber),
            'message_length' => strlen($message),
            'sender_id' => $options['sender_id'] ?? $this->senderId,
            'has_api_token' => $hasApiToken,
            'api_token_provided' => isset($options['api_token']),
            'api_token_length' => isset($options['api_token']) ? strlen((string)$options['api_token']) : 0,
        ]);

        // Skip balance check when using per-user API tokens
        // Balance check is only for global config (not needed for per-user tokens)
        // When API token is provided, we skip balance check entirely
        if ($hasApiToken) {
            Log::info('Skipping balance check - using per-user API token');
        } else {
            // Only run balance check if no API token provided (global config)
            try {
                Log::info('Running balance check - using global config');
                $balance = $this->checkBalance();
                $estimatedCost = $this->estimateSmsCost($message);

                if ($balance < $estimatedCost) {
                    throw TalksasaSmsException::permanent(
                        "Insufficient balance. Required: {$estimatedCost}, Available: {$balance}",
                        402,
                        ['balance' => $balance, 'required' => $estimatedCost]
                    );
                }

                if ($balance < $this->lowBalanceThreshold) {
                    Log::warning('Low balance warning', [
                        'balance' => $balance,
                        'threshold' => $this->lowBalanceThreshold,
                    ]);
                }
            } catch (TalksasaSmsException $e) {
                Log::error('Balance check failed', [
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]);
                throw $e;
            }
        }

        // Retry loop with exponential backoff
        while ($attempt < $this->maxRetries) {
            try {
                // Use provided API token (required for per-user tokens)
                // Talksasa uses Bearer token authentication directly - no need to get token
                if (empty($options['api_token'])) {
                    Log::error('API token missing in SMS options', [
                        'options_keys' => array_keys($options),
                        'has_api_token_key' => isset($options['api_token']),
                    ]);
                    throw TalksasaSmsException::permanent(
                        'API token is required. Please configure your Talksasa API token in Settings.',
                        401,
                        []
                    );
                }
                
                $token = $options['api_token'];
                $senderId = $options['sender_id'] ?? $this->senderId;
                
                Log::info('Sending SMS to Talksasa API', [
                    'endpoint' => 'https://bulksms.talksasa.com/api/v3/sms/send',
                    'has_token' => !empty($token),
                    'token_length' => strlen((string)$token),
                    'sender_id' => $senderId,
                    'recipient' => $this->maskPhone($phoneNumber),
                ]);
                
                // Use Talksasa API v3 format
                $client = new Client([
                    'timeout' => $this->timeout,
                    'verify' => true,
                ]);
                
                $response = $client->post('https://bulksms.talksasa.com/api/v3/sms/send', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'recipient' => $phoneNumber,
                        'sender_id' => $senderId,
                        'type' => 'plain',
                        'message' => $message,
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $responseBody = json_decode($response->getBody()->getContents(), true);

                if ($statusCode === 200 || $statusCode === 201) {
                    // Check response format from Talksasa API
                    $status = $responseBody['status'] ?? null;
                    
                    if ($status === 'success') {
                        Log::info('SMS sent successfully', [
                            'phone_number' => $this->maskPhone($phoneNumber),
                            'message_id' => $responseBody['data']['message_id'] ?? $responseBody['message_id'] ?? null,
                            'response' => $responseBody,
                        ]);

                        return true;
                    } else {
                        // API returned error
                        $errorMessage = $responseBody['message'] ?? 'Unknown error';
                        throw TalksasaSmsException::permanent(
                            'SMS API error: ' . $errorMessage,
                            $statusCode,
                            ['response' => $responseBody]
                        );
                    }
                }

                // Handle non-2xx responses
                throw $this->createExceptionFromResponse($statusCode, $responseBody);

            } catch (TalksasaSmsException $e) {
                $lastException = $e;

                // Don't retry permanent errors
                if (!$e->isRetryable()) {
                    Log::error('SMS send failed - permanent error', [
                        'phone_number' => $this->maskPhone($phoneNumber),
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'context' => $e->getContext(),
                    ]);
                    throw $e;
                }

                $attempt++;
                
                if ($attempt < $this->maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    
                    Log::warning('SMS send failed - retrying', [
                        'phone_number' => $this->maskPhone($phoneNumber),
                        'attempt' => $attempt,
                        'max_retries' => $this->maxRetries,
                        'delay_seconds' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    sleep($delay);
                }

            } catch (RequestException $e) {
                $lastException = $this->handleHttpException($e);
                
                if (!$lastException->isRetryable()) {
                    throw $lastException;
                }

                $attempt++;
                
                if ($attempt < $this->maxRetries) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    sleep($delay);
                }

            } catch (\Exception $e) {
                Log::error('Unexpected error sending SMS', [
                    'phone_number' => $this->maskPhone($phoneNumber),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw TalksasaSmsException::permanent(
                    'Unexpected error: ' . $e->getMessage(),
                    500,
                    ['exception' => get_class($e)],
                    $e
                );
            }
        }

        // All retries exhausted
        Log::error('SMS send failed after all retries', [
            'phone_number' => $this->maskPhone($phoneNumber),
            'attempts' => $attempt,
            'last_error' => $lastException?->getMessage(),
        ]);

        throw TalksasaSmsException::transient(
            'SMS send failed after ' . $this->maxRetries . ' attempts',
            500,
            ['attempts' => $attempt],
            $lastException
        );
    }

    /**
     * Check account balance
     * 
     * @return float Account balance
     * @throws TalksasaSmsException
     */
    public function checkBalance(): float
    {
        try {
            $token = $this->getAuthToken();

            $response = $this->httpClient->get('/account/balance', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode === 200) {
                $balance = (float) ($responseBody['balance'] ?? 0);
                
                Log::info('Balance checked', [
                    'balance' => $balance,
                ]);

                return $balance;
            }

            throw $this->createExceptionFromResponse($statusCode, $responseBody);

        } catch (TalksasaSmsException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw $this->handleHttpException($e);
        } catch (\Exception $e) {
            throw TalksasaSmsException::permanent(
                'Failed to check balance: ' . $e->getMessage(),
                500,
                [],
                $e
            );
        }
    }

    /**
     * Get account information
     * 
     * @return array Account details
     * @throws TalksasaSmsException
     */
    public function getAccountInfo(): array
    {
        try {
            $token = $this->getAuthToken();

            $response = $this->httpClient->get('/account/info', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode === 200) {
                return $responseBody;
            }

            throw $this->createExceptionFromResponse($statusCode, $responseBody);

        } catch (TalksasaSmsException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw $this->handleHttpException($e);
        } catch (\Exception $e) {
            throw TalksasaSmsException::permanent(
                'Failed to get account info: ' . $e->getMessage(),
                500,
                [],
                $e
            );
        }
    }

    /**
     * Get or refresh authentication token
     * 
     * @return string API token
     * @throws TalksasaSmsException
     */
    private function getAuthToken(): string
    {
        // Check cache first
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cachedToken) {
            return $cachedToken;
        }

        // Authenticate to get new token
        try {
            $response = $this->httpClient->post('/auth/token', [
                'json' => [
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if ($statusCode === 200 || $statusCode === 201) {
                $token = $responseBody['token'] ?? $responseBody['access_token'] ?? null;
                $expiresIn = $responseBody['expires_in'] ?? self::TOKEN_CACHE_TTL;

                if (!$token) {
                    throw TalksasaSmsException::permanent(
                        'Token not found in authentication response',
                        500,
                        ['response' => $responseBody]
                    );
                }

                // Cache token
                Cache::put(self::TOKEN_CACHE_KEY, $token, $expiresIn - 60); // Cache 1 minute less than expiry

                Log::info('API token obtained', [
                    'expires_in' => $expiresIn,
                ]);

                return $token;
            }

            throw $this->createExceptionFromResponse($statusCode, $responseBody);

        } catch (RequestException $e) {
            $exception = $this->handleHttpException($e);
            
            Log::error('Authentication failed', [
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ]);

            throw TalksasaSmsException::permanent(
                'Failed to authenticate with Talksasa API',
                401,
                [],
                $exception
            );
        } catch (\Exception $e) {
            throw TalksasaSmsException::permanent(
                'Authentication error: ' . $e->getMessage(),
                500,
                [],
                $e
            );
        }
    }

    /**
     * Handle HTTP exceptions and convert to TalksasaSmsException
     */
    private function handleHttpException(RequestException $e): TalksasaSmsException
    {
        $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
        $responseBody = null;

        if ($e->hasResponse()) {
            try {
                $responseBody = json_decode($e->getResponse()->getBody()->getContents(), true);
            } catch (\Exception $ex) {
                // Ignore JSON decode errors
            }
        }

        // Determine if error is retryable
        $isRetryable = $this->isRetryableError($statusCode);

        $message = $responseBody['message'] ?? $responseBody['error'] ?? $e->getMessage();
        
        if ($isRetryable) {
            return TalksasaSmsException::transient(
                $message ?: 'HTTP error: ' . $statusCode,
                $statusCode ?: 500,
                ['response' => $responseBody],
                $e
            );
        }

        return TalksasaSmsException::permanent(
            $message ?: 'HTTP error: ' . $statusCode,
            $statusCode ?: 400,
            ['response' => $responseBody],
            $e
        );
    }

    /**
     * Create exception from API response
     */
    private function createExceptionFromResponse(int $statusCode, ?array $responseBody): TalksasaSmsException
    {
        $isRetryable = $this->isRetryableError($statusCode);
        $message = $responseBody['message'] ?? $responseBody['error'] ?? "API returned status {$statusCode}";

        if ($isRetryable) {
            return TalksasaSmsException::transient(
                $message,
                $statusCode,
                ['response' => $responseBody]
            );
        }

        return TalksasaSmsException::permanent(
            $message,
            $statusCode,
            ['response' => $responseBody]
        );
    }

    /**
     * Determine if HTTP status code is retryable
     */
    private function isRetryableError(int $statusCode): bool
    {
        // Retryable: 5xx errors, 429 (rate limit)
        if ($statusCode >= 500) {
            return true;
        }

        if ($statusCode === 429) {
            return true;
        }

        // Network errors (0 status code)
        if ($statusCode === 0) {
            return true;
        }

        return false;
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        return $this->retryDelay * (2 ** ($attempt - 1));
    }

    /**
     * Estimate SMS cost based on message length
     * 
     * @param string $message
     * @return float Estimated cost
     */
    private function estimateSmsCost(string $message): float
    {
        $length = strlen($message);
        
        // Standard SMS: 160 characters = 1 SMS
        // Long SMS: > 160 characters = multiple SMS
        $smsCount = ceil($length / 160);
        
        // Assume 1 KES per SMS (adjust based on actual pricing)
        return (float) $smsCount;
    }

    /**
     * Mask phone number for logging (privacy)
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) > 7) {
            return substr($phone, 0, 3) . '****' . substr($phone, -3);
        }
        return '****';
    }

    /**
     * Validate API configuration
     */
    public function validateConfiguration(): bool
    {
        return !empty($this->baseUrl) 
            && !empty($this->apiKey) 
            && !empty($this->apiSecret);
    }
}
