<?php

namespace App\Http\Controllers;

use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * M-Pesa Webhook Controller
 * 
 * Handles incoming payment notifications from M-Pesa.
 * Implements validation and idempotent processing.
 */
class MpesaWebhookController extends Controller
{
    public function __construct(
        private PaymentProcessingService $paymentProcessingService
    ) {}

    /**
     * Test endpoint (GET requests)
     * 
     * Returns endpoint status for testing and debugging.
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'M-Pesa Webhook Endpoint',
            'method' => 'POST',
            'description' => 'This endpoint accepts POST requests from M-Pesa for payment notifications.',
            'note' => 'To test this endpoint, use POST method with M-Pesa webhook payload.',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Handle M-Pesa confirmation webhook
     * 
     * This endpoint receives payment confirmations from M-Pesa.
     * M-Pesa expects a 200 response to acknowledge receipt.
     */
    public function confirmation(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('M-Pesa confirmation webhook received', [
                'payload' => $payload,
            ]);

            // Validate webhook payload structure
            $validator = Validator::make($payload, [
                'TransactionID' => 'required|string',
                'Amount' => 'required|numeric|min:0',
                'PhoneNumber' => 'required|string',
                'TransactionTime' => 'required|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid M-Pesa webhook payload', [
                    'errors' => $validator->errors()->all(),
                    'payload' => $payload,
                ]);
                
                // Still return 200 to M-Pesa to prevent retries
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid payload',
                ], 200);
            }

            // Process payment (idempotent)
            $payment = $this->paymentProcessingService->processPayment($payload);

            if ($payment) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment processed',
                    'payment_id' => $payment->id,
                ], 200);
            }

            // Payment already processed (duplicate)
            return response()->json([
                'status' => 'success',
                'message' => 'Payment already processed',
            ], 200);

        } catch (\InvalidArgumentException $e) {
            Log::error('Payment processing validation error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            // Return 200 to prevent M-Pesa retries for invalid data
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Payment processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Return 500 to trigger M-Pesa retry mechanism
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle M-Pesa validation webhook
     * 
     * M-Pesa calls this before sending confirmation to validate the account.
     * Must return 200 with ResultCode 0 to accept the payment.
     */
    public function validation(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('M-Pesa validation webhook received', [
                'payload' => $payload,
            ]);

            // Extract account details
            $accountType = $payload['BillRefNumber'] ? 'paybill' : 'till';
            $accountNumber = $accountType === 'paybill' 
                ? ($payload['BusinessShortCode'] ?? null)
                : ($payload['TillNumber'] ?? null);

            if (!$accountNumber) {
                Log::warning('Account number not found in validation payload', [
                    'payload' => $payload,
                ]);
                
                // Reject payment
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => 'Account not found',
                ], 200);
            }

            // Check if account exists and is active
            // Note: We use a simple check here. Full validation happens in confirmation.
            // This is just to tell M-Pesa whether to proceed.

            // Accept payment (validation happens in confirmation endpoint)
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Validation webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            // Reject on error
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Validation error',
            ], 200);
        }
    }
}
