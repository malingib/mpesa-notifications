<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

/**
 * Talksasa Balance Service
 * 
 * Handles fetching SMS balance from Talksasa API
 */
class TalksasaBalanceService
{
    /**
     * Get SMS balance for a user
     * 
     * @param string $apiToken Talksasa API token
     * @return array|null Balance data or null on failure
     */
    public function getBalance(string $apiToken): ?array
    {
        if (empty($apiToken)) {
            return null;
        }

        // Cache balance for 5 minutes to reduce API calls
        $cacheKey = 'talksasa_balance_' . md5($apiToken);
        
        return Cache::remember($cacheKey, 300, function () use ($apiToken) {
            return $this->fetchBalanceFromApi($apiToken);
        });
    }

    /**
     * Fetch balance from Talksasa API
     * 
     * @param string $apiToken
     * @return array|null
     */
    private function fetchBalanceFromApi(string $apiToken): ?array
    {
        try {
            $client = new Client([
                'timeout' => 10,
                'verify' => true,
            ]);

            $response = $client->get('https://bulksms.talksasa.com/api/v3/balance', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);

            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            Log::info('Talksasa balance API response', [
                'status_code' => $response->getStatusCode(),
                'response' => $data,
                'raw_body' => $responseBody,
            ]);

            if (isset($data['status']) && $data['status'] === 'success') {
                // Handle different response formats
                $balance = $data['data'] ?? null;
                
                // If data is a string/number, use it directly
                if (is_numeric($balance)) {
                    return [
                        'success' => true,
                        'balance' => (float) $balance,
                        'raw' => $data,
                    ];
                }
                
                // If data is an array, try to extract balance
                if (is_array($balance)) {
                    // Try multiple possible field names (including remaining_balance)
                    $balanceValue = $balance['remaining_balance']
                        ?? $balance['balance'] 
                        ?? $balance['units'] 
                        ?? $balance['sms_units'] 
                        ?? $balance['amount'] 
                        ?? $balance['unit'] 
                        ?? $balance['sms_unit']
                        ?? $balance['credit']
                        ?? $balance['credits']
                        ?? null;
                    
                    if ($balanceValue !== null) {
                        // If it's numeric, return it
                        if (is_numeric($balanceValue)) {
                            return [
                                'success' => true,
                                'balance' => (float) $balanceValue,
                                'raw' => $data,
                            ];
                        }
                        // If it's a string, try to extract number (handles "Ksh3,121" format)
                        if (is_string($balanceValue)) {
                            // Remove currency symbols and extract number
                            $cleaned = preg_replace('/[^\d,.]/', '', $balanceValue);
                            $numericValue = str_replace(',', '', $cleaned);
                            if (is_numeric($numericValue)) {
                                return [
                                    'success' => true,
                                    'balance' => (float) $numericValue,
                                    'raw' => $data,
                                ];
                            }
                        }
                    }
                    
                    // If no balance found, return the whole array for debugging
                    return [
                        'success' => true,
                        'balance' => $balance,
                        'raw' => $data,
                    ];
                }
                
                // If data is a string, try to extract number from it
                if (is_string($balance)) {
                    if (preg_match('/[\d,]+\.?\d*/', $balance, $matches)) {
                        $numericValue = str_replace(',', '', $matches[0]);
                        if (is_numeric($numericValue)) {
                            return [
                                'success' => true,
                                'balance' => (float) $numericValue,
                                'raw' => $data,
                            ];
                        }
                    }
                }
                
                // Return success with raw data for debugging
                return [
                    'success' => true,
                    'balance' => $balance,
                    'raw' => $data,
                ];
            }

            Log::warning('Talksasa balance API returned non-success status', [
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to fetch balance',
                'raw' => $data,
            ];
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $data = json_decode($response->getBody(), true);

            Log::error('Talksasa balance API client error', [
                'status' => $response->getStatusCode(),
                'message' => $data['message'] ?? $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Invalid API token',
            ];
        } catch (RequestException $e) {
            Log::error('Talksasa balance API request failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed. Please try again later.',
            ];
        } catch (\Exception $e) {
            Log::error('Talksasa balance API unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An unexpected error occurred.',
            ];
        }
    }

    /**
     * Clear balance cache for a token
     * 
     * @param string $apiToken
     * @return void
     */
    public function clearCache(string $apiToken): void
    {
        $cacheKey = 'talksasa_balance_' . md5($apiToken);
        Cache::forget($cacheKey);
    }

    /**
     * Format balance for display
     * 
     * @param array|null $balanceData
     * @return string
     */
    public function formatBalance(?array $balanceData): string
    {
        if (!$balanceData) {
            Log::warning('Talksasa balance data is null');
            return 'N/A';
        }

        if (!isset($balanceData['success']) || !$balanceData['success']) {
            $message = $balanceData['message'] ?? 'Failed to fetch balance';
            Log::warning('Talksasa balance fetch failed', ['message' => $message, 'data' => $balanceData]);
            return 'N/A';
        }

        // First, try to get balance from the balanceData['balance'] field
        $balance = $balanceData['balance'] ?? null;
        
        // If balance is numeric, format and return it
        if ($balance !== null && is_numeric($balance)) {
            return number_format((float) $balance, 0, '.', ',');
        }
        
        // If balance is an array, extract numeric value
        if (is_array($balance)) {
            // Try remaining_balance first (Talksasa format)
            $balanceValue = $balance['remaining_balance']
                ?? $balance['balance'] 
                ?? $balance['units'] 
                ?? $balance['sms_units'] 
                ?? $balance['amount'] 
                ?? $balance['unit'] 
                ?? $balance['sms_unit']
                ?? $balance['credit']
                ?? $balance['credits']
                ?? null;
                
            if ($balanceValue !== null) {
                if (is_numeric($balanceValue)) {
                    return number_format((float) $balanceValue, 0, '.', ',');
                }
                
                // Try to extract from string if present (handles "Ksh3,121" format)
                if (is_string($balanceValue)) {
                    // Remove currency symbols and extract number
                    $cleaned = preg_replace('/[^\d,.]/', '', $balanceValue);
                    $numericValue = str_replace(',', '', $cleaned);
                    if (is_numeric($numericValue)) {
                        return number_format((float) $numericValue, 0, '.', ',');
                    }
                }
            }
        }
        
        // If balance is a string, try to extract number
        if (is_string($balance)) {
            if (preg_match('/[\d,]+\.?\d*/', $balance, $matches)) {
                $numericValue = str_replace(',', '', $matches[0]);
                if (is_numeric($numericValue)) {
                    return number_format((float) $numericValue, 0, '.', ',');
                }
            }
        }
        
        // Try to extract from raw data
        $raw = $balanceData['raw'] ?? [];
        if (isset($raw['data'])) {
            $data = $raw['data'];
            
            // If data is numeric
            if (is_numeric($data)) {
                return number_format((float) $data, 0, '.', ',');
            }
            
                // If data is an array, try common field names (including remaining_balance)
                if (is_array($data)) {
                    $balanceValue = $data['remaining_balance']
                        ?? $data['balance'] 
                        ?? $data['units'] 
                        ?? $data['sms_units'] 
                        ?? $data['amount'] 
                        ?? $data['unit'] 
                        ?? $data['sms_unit']
                        ?? $data['credit']
                        ?? $data['credits']
                        ?? null;
                        
                    if ($balanceValue !== null) {
                        if (is_numeric($balanceValue)) {
                            return number_format((float) $balanceValue, 0, '.', ',');
                        }
                        
                        // Try string extraction (handles "Ksh3,121" format)
                        if (is_string($balanceValue)) {
                            // Remove currency symbols and extract number
                            $cleaned = preg_replace('/[^\d,.]/', '', $balanceValue);
                            $numericValue = str_replace(',', '', $cleaned);
                            if (is_numeric($numericValue)) {
                                return number_format((float) $numericValue, 0, '.', ',');
                            }
                        }
                    }
                }
            
            // If data is a string, try to parse it
            if (is_string($data)) {
                if (preg_match('/[\d,]+\.?\d*/', $data, $matches)) {
                    $numericValue = str_replace(',', '', $matches[0]);
                    if (is_numeric($numericValue)) {
                        return number_format((float) $numericValue, 0, '.', ',');
                    }
                }
            }
        }
        
        // Log the full response for debugging
        Log::warning('Could not extract balance from response', [
            'balance_data' => $balanceData,
            'balance_type' => gettype($balance),
            'balance_value' => $balance,
            'raw_data' => $raw,
        ]);
        
        return 'N/A';
    }
}
