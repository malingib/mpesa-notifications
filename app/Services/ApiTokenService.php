<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * API Token Service
 * 
 * Handles creation, revocation, and management of API tokens.
 */
class ApiTokenService
{
    /**
     * Create new API token
     * 
     * @param User $user Token owner
     * @param array $data Token data (name, scopes, expires_at)
     * @return array ['token' => ApiToken, 'plain_token' => string]
     */
    public function createToken(User $user, array $data): array
    {
        // Generate random token
        $plainToken = 'tks_' . Str::random(32);
        $tokenHash = Hash::make($plainToken);
        $tokenPrefix = substr($plainToken, 4, 8); // Skip 'tks_' prefix

        // Create token record
        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'name' => $data['name'] ?? 'API Token',
            'token' => $tokenHash,
            'token_prefix' => $tokenPrefix,
            'scopes' => $data['scopes'] ?? ['payments:read'],
            'expires_at' => isset($data['expires_at']) ? now()->parse($data['expires_at']) : null,
            'is_active' => true,
        ]);

        // Log token creation
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'api.token.created',
            'entity_type' => 'ApiToken',
            'entity_id' => $apiToken->id,
            'description' => "API token '{$apiToken->name}' created for user {$user->email}",
            'metadata' => [
                'scopes' => $apiToken->scopes,
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
            ],
        ]);

        return [
            'token' => $apiToken,
            'plain_token' => $plainToken, // Only shown once!
        ];
    }

    /**
     * Revoke API token
     */
    public function revokeToken(ApiToken $token): bool
    {
        $token->update([
            'is_active' => false,
            'deleted_at' => now(),
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'api.token.revoked',
            'entity_type' => 'ApiToken',
            'entity_id' => $token->id,
            'description' => "API token '{$token->name}' revoked",
        ]);

        return true;
    }

    /**
     * List tokens for user
     */
    public function getTokensForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return ApiToken::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
