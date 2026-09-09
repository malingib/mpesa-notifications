<?php

namespace Tests\Unit\Services;

use App\Exceptions\TalksasaSmsException;
use App\Services\TalksasaSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TalksasaSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set test configuration
        config([
            'talksasa.sms.api_url' => 'https://api.talksasa.com/v1',
            'talksasa.sms.api_key' => 'test_key',
            'talksasa.sms.api_secret' => 'test_secret',
            'talksasa.sms.sender_id' => 'TEST',
        ]);
    }

    /** @test */
    public function it_sends_sms_successfully()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'test_token_123',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 1000.0,
            ], 200),
            'api.talksasa.com/v1/sms/send' => Http::response([
                'status' => 'success',
                'message_id' => 'MSG123',
            ], 200),
        ]);

        $service = app(TalksasaSmsService::class);
        $result = $service->sendSms('254712345678', 'Test message');

        $this->assertTrue($result);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.talksasa.com/v1/sms/send'
                && $request->method() === 'POST'
                && $request['to'] === '254712345678'
                && $request['message'] === 'Test message';
        });
    }

    /** @test */
    public function it_checks_balance_before_sending()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'test_token_123',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 0.5, // Low balance
            ], 200),
        ]);

        $service = app(TalksasaSmsService::class);

        $this->expectException(TalksasaSmsException::class);
        $this->expectExceptionMessage('Insufficient balance');

        $service->sendSms('254712345678', 'Test message');
    }

    /** @test */
    public function it_retries_on_transient_errors()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'test_token_123',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 1000.0,
            ], 200),
            'api.talksasa.com/v1/sms/send' => Http::sequence()
                ->push(['error' => 'Server error'], 500) // First attempt fails
                ->push(['error' => 'Server error'], 500) // Second attempt fails
                ->push(['status' => 'success', 'message_id' => 'MSG123'], 200), // Third succeeds
        ]);

        $service = app(TalksasaSmsService::class);
        $result = $service->sendSms('254712345678', 'Test message');

        $this->assertTrue($result);
        
        // Verify 3 requests were made
        Http::assertSentCount(3);
    }

    /** @test */
    public function it_does_not_retry_permanent_errors()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'test_token_123',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 1000.0,
            ], 200),
            'api.talksasa.com/v1/sms/send' => Http::response([
                'error' => 'Invalid phone number',
            ], 400),
        ]);

        $service = app(TalksasaSmsService::class);

        $this->expectException(TalksasaSmsException::class);
        $this->expectExceptionMessage('Invalid phone number');

        try {
            $service->sendSms('invalid', 'Test message');
        } catch (TalksasaSmsException $e) {
            $this->assertFalse($e->isRetryable());
            throw $e;
        }
    }

    /** @test */
    public function it_caches_authentication_token()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'cached_token_123',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 1000.0,
            ], 200)->times(2), // Called twice
        ]);

        $service = app(TalksasaSmsService::class);

        // First call - should authenticate
        $balance1 = $service->checkBalance();
        
        // Second call - should use cached token
        $balance2 = $service->checkBalance();

        $this->assertEquals(1000.0, $balance1);
        $this->assertEquals(1000.0, $balance2);
        
        // Token endpoint should only be called once
        Http::assertSentCount(function ($requests) {
            $tokenRequests = array_filter($requests, function ($req) {
                return str_contains($req->url(), '/auth/token');
            });
            return count($tokenRequests) === 1;
        });
    }

    /** @test */
    public function it_refreshes_token_on_401_error()
    {
        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::sequence()
                ->push(['token' => 'expired_token', 'expires_in' => 3600], 200)
                ->push(['token' => 'new_token', 'expires_in' => 3600], 200),
            'api.talksasa.com/v1/account/balance' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401) // First call with expired token
                ->push(['balance' => 1000.0], 200), // Second call with new token
        ]);

        // Set expired token in cache
        Cache::put('talksasa_api_token', 'expired_token', 3600);

        $service = app(TalksasaSmsService::class);
        $balance = $service->checkBalance();

        $this->assertEquals(1000.0, $balance);
    }

    /** @test */
    public function it_logs_all_sms_attempts()
    {
        Log::spy();

        Http::fake([
            'api.talksasa.com/v1/auth/token' => Http::response([
                'token' => 'test_token',
                'expires_in' => 3600,
            ], 200),
            'api.talksasa.com/v1/account/balance' => Http::response([
                'balance' => 1000.0,
            ], 200),
            'api.talksasa.com/v1/sms/send' => Http::response([
                'status' => 'success',
                'message_id' => 'MSG123',
            ], 200),
        ]);

        $service = app(TalksasaSmsService::class);
        $service->sendSms('254712345678', 'Test message');

        Log::shouldHaveReceived('info')
            ->with('SMS send attempt started', \Mockery::type('array'));
        
        Log::shouldHaveReceived('info')
            ->with('SMS sent successfully', \Mockery::type('array'));
    }
}
