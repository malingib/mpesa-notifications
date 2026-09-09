<?php

namespace App\Http\Controllers;

use App\Services\ApiTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API Token Controller
 * 
 * Handles API token creation, listing, and revocation.
 */
class ApiTokenController extends Controller
{
    public function __construct(
        private ApiTokenService $tokenService
    ) {}

    /**
     * Create new API token
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|in:payments:read,payments:write,merchants:read,merchants:write,sms:read,sms:write,*',
            'expires_at' => 'sometimes|date|after:now',
        ]);

        $user = $request->user();
        $result = $this->tokenService->createToken($user, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => [
                    'id' => $result['token']->id,
                    'name' => $result['token']->name,
                    'scopes' => $result['token']->scopes,
                    'expires_at' => $result['token']->expires_at?->toIso8601String(),
                    'created_at' => $result['token']->created_at->toIso8601String(),
                ],
                'plain_token' => $result['plain_token'], // Show only once!
            ],
            'message' => 'API token created. Store the plain_token securely - it will not be shown again.',
        ], 201);
    }

    /**
     * List user's tokens
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $this->tokenService->getTokensForUser($user);

        return response()->json([
            'status' => 'success',
            'data' => $tokens->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'token_prefix' => $token->token_prefix . '...',
                    'scopes' => $token->scopes,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'is_active' => $token->is_active,
                    'created_at' => $token->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Revoke token
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        $token = \App\Models\ApiToken::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->tokenService->revokeToken($token);

        return response()->json([
            'status' => 'success',
            'message' => 'Token revoked successfully',
        ]);
    }
}
