<?php

namespace App\Services;

use App\Support\LiveKitConfig;
use Firebase\JWT\JWT;
use RuntimeException;

class LiveKitTokenService
{
    public function enabled(): bool
    {
        return LiveKitConfig::ready();
    }

    public function url(): string
    {
        return LiveKitConfig::url();
    }

    /**
     * Genera un Access Token de LiveKit (JWT HS256) sin SDK oficial.
     */
    public function createToken(
        string $identity,
        string $name,
        string $room,
        bool $canPublish = true,
        bool $canSubscribe = true,
    ): string {
        if (! $this->enabled()) {
            throw new RuntimeException('LiveKit no está configurado.');
        }

        $apiKey = LiveKitConfig::apiKey();
        $apiSecret = LiveKitConfig::apiSecret();
        $ttl = max(60, (int) config('livekit.token_ttl', 7200));
        $now = time();

        $payload = [
            'iss' => $apiKey,
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now - 10,
            'exp' => $now + $ttl,
            'video' => [
                'roomJoin' => true,
                'room' => $room,
                'canPublish' => $canPublish,
                'canSubscribe' => $canSubscribe,
                'canPublishData' => true,
            ],
        ];

        return JWT::encode($payload, $apiSecret, 'HS256');
    }
}
