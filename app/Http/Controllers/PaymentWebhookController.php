<?php

namespace App\Http\Controllers;

use App\DTOs\PaymentDto;
use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentIngestionService;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Payment Webhook Controller
 * 
 * Handles incoming payment notifications from M-Pesa.
 * Single endpoint for fast, idempotent payment ingestion.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private PaymentIngestionService $ingestionService,
        private PaymentReconciliationService $reconciliationService
    ) {}

    /**
     * Test endpoint (GET requests)
     * 
     * Returns endpoint status for testing and debugging.
     * M-Pesa may also send GET requests for validation.
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'M-Pesa Payment Webhook Endpoint',
            'method' => 'POST',
            'endpoint' => '/api/webhooks/mpesa/payment',
            'description' => 'This endpoint accepts POST requests from M-Pesa for payment notifications.',
            'note' => 'To test this endpoint, use POST method with M-Pesa webhook payload.',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Handle incoming payment notification
     * 
     * M-Pesa sends payment confirmations to this endpoint.
     * Must respond quickly (< 200ms) to prevent retries.
     * 
     * @param PaymentWebhookRequest $request Validated webhook request
     * @return JsonResponse
     */
    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        // Log incoming webhook for debugging
        Log::info('Payment webhook received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload_keys' => array_keys($request->all()),
            'raw_payload' => $request->all(),
        ]);
        
        try {
            $payload = $request->validated();
            
            Log::info('Webhook payload validated', [
                'transaction_id' => $payload['TransactionID'] ?? null,
                'amount' => $payload['Amount'] ?? null,
                'till_number' => $payload['TillNumber'] ?? null,
                'business_shortcode' => $payload['BusinessShortCode'] ?? null,
            ]);
            
            // Normalize payload to internal format
            $paymentDto = PaymentDto::fromMpesaPayload($payload);
            
            Log::info('Payment DTO created', [
                'transaction_id' => $paymentDto->transactionId,
                'account_type' => $paymentDto->accountType,
                'account_number' => $paymentDto->accountNumber,
                'amount' => $paymentDto->amount,
            ]);
            
            // Process payment (idempotent, async SMS)
            $payment = $this->ingestionService->ingest($paymentDto);
            
            if ($payment) {
                Log::info('Payment ingested successfully', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $paymentDto->transactionId,
                    'amount' => $paymentDto->amount,
                    'merchant_id' => $payment->merchant_id,
                    'user_id' => $payment->user_id,
                ]);
                
                // Attempt auto-matching to invoices (async, non-blocking)
                try {
                    $matchedInvoice = $this->reconciliationService->autoMatchPayment($payment);
                    if ($matchedInvoice) {
                        Log::info('Payment auto-matched to invoice', [
                            'payment_id' => $payment->id,
                            'invoice_id' => $matchedInvoice->id,
                            'invoice_number' => $matchedInvoice->invoice_number,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log but don't fail webhook if auto-matching fails
                    Log::warning('Auto-matching failed for payment', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Return M-Pesa expected format
                return response()->json([
                    'ResultCode' => 0,
                    'ResultDesc' => 'Accepted',
                ], 200);
            }
            
            // Payment already processed (duplicate)
            Log::info('Duplicate payment detected', [
                'transaction_id' => $paymentDto->transactionId,
            ]);
            
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Already processed',
            ], 200);
            
        } catch (\InvalidArgumentException $e) {
            Log::warning('Invalid payment payload or merchant not found', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($request->all()),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return success to prevent M-Pesa retries for invalid data
            // Invalid data should be logged but not retried
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Invalid payload - logged',
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Payment ingestion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload_keys' => array_keys($request->all()),
                'payload' => $request->all(),
            ]);
            
            // Return 500 to trigger M-Pesa retry mechanism
            // Only for processing errors, not validation errors
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Processing error',
            ], 500);
        }
    }
}
