<?php

return [
    /*
    | LiveKit Cloud (plan gratis / más barato) o self-host.
    | Cloud: https://cloud.livekit.io → crear proyecto → copiar URL, API Key, Secret
    */
    'enabled' => (bool) env('LIVEKIT_ENABLED', false),
    'url' => env('LIVEKIT_URL', ''), // wss://xxx.livekit.cloud  o ws://localhost:7880
    'api_key' => env('LIVEKIT_API_KEY', ''),
    'api_secret' => env('LIVEKIT_API_SECRET', ''),
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 7200), // segundos
    'ring_timeout' => (int) env('LIVEKIT_RING_TIMEOUT', 45),
    /** false = solo audio (más barato / estira el plan gratis) */
    'allow_video' => (bool) env('LIVEKIT_ALLOW_VIDEO', false),
    'max_call_minutes' => (int) env('LIVEKIT_MAX_CALL_MINUTES', 20),
    'video_quality' => env('LIVEKIT_VIDEO_QUALITY', 'low'), // low|medium
];
