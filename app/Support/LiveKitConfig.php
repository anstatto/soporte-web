<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resuelve config LiveKit: settings de BD (Ajustes) pisan el .env.
 */
class LiveKitConfig
{
    public static function enabledFlag(): bool
    {
        $fromDb = Setting::get('livekit_enabled');
        if ($fromDb !== null && $fromDb !== '') {
            return filter_var($fromDb, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('livekit.enabled');
    }

    public static function url(): string
    {
        return (string) (Setting::get('livekit_url') ?: config('livekit.url') ?: '');
    }

    public static function apiKey(): string
    {
        return (string) (Setting::get('livekit_api_key') ?: config('livekit.api_key') ?: '');
    }

    public static function apiSecret(): string
    {
        return (string) (Setting::get('livekit_api_secret') ?: config('livekit.api_secret') ?: '');
    }

    public static function ringTimeout(): int
    {
        $fromDb = Setting::get('livekit_ring_timeout');
        if ($fromDb !== null && $fromDb !== '') {
            return max(15, (int) $fromDb);
        }

        return max(15, (int) config('livekit.ring_timeout', 45));
    }

    public static function allowVideo(): bool
    {
        $fromDb = Setting::get('livekit_allow_video');
        if ($fromDb !== null && $fromDb !== '') {
            return filter_var($fromDb, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('livekit.allow_video', false);
    }

    public static function maxCallMinutes(): int
    {
        $fromDb = Setting::get('livekit_max_call_minutes');
        if ($fromDb !== null && $fromDb !== '') {
            return max(0, (int) $fromDb);
        }

        return max(0, (int) config('livekit.max_call_minutes', 20));
    }

    public static function videoQuality(): string
    {
        $q = Setting::get('livekit_video_quality') ?: config('livekit.video_quality', 'low');

        return in_array($q, ['low', 'medium'], true) ? $q : 'low';
    }

    public static function ready(): bool
    {
        return self::enabledFlag()
            && filled(self::url())
            && filled(self::apiKey())
            && filled(self::apiSecret());
    }

    public static function status(): array
    {
        return [
            'enabled' => self::ready(),
            'flag' => self::enabledFlag(),
            'has_url' => filled(self::url()),
            'has_key' => filled(self::apiKey()),
            'has_secret' => filled(self::apiSecret()),
            'url_host' => filled(self::url())
                ? (string) preg_replace('#^(wss?://[^/]+).*#', '$1', self::url())
                : null,
            'ring_timeout' => self::ringTimeout(),
            'allow_video' => self::allowVideo(),
            'max_call_minutes' => self::maxCallMinutes(),
            'video_quality' => self::videoQuality(),
            'source' => filled(Setting::get('livekit_api_key')) || filled(Setting::get('livekit_url'))
                ? 'database'
                : 'env',
            'plan_hint' => 'LiveKit Cloud Build: 0$/mes · ~5.000 min participante WebRTC · tope duro (sin cobros extra)',
        ];
    }
}
