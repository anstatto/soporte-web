<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'app_name' => 'RM Consuegra Soporte',
        'company_name' => 'RM Consuegra SRL',
        'support_email' => '',
        'mail_from_name' => 'Soporte RM Consuegra',
        'mail_from_address' => '',
        'report_footer' => 'Documento generado automáticamente. Confidencial — uso interno.',
        'tickets_per_page' => '24',
        'livekit_enabled' => '0',
        'livekit_url' => '',
        'livekit_api_key' => '',
        'livekit_api_secret' => '',
        'livekit_ring_timeout' => '45',
        /** Modo económico (plan Build gratis): video off por defecto */
        'livekit_allow_video' => '0',
        /** Corte automático de llamada (minutos). 0 = sin límite */
        'livekit_max_call_minutes' => '20',
        /** low | medium — solo si video está permitido */
        'livekit_video_quality' => 'low',
    ];

    /** Claves sensibles: en el formulario no se reenvían en claro. */
    public const SECRET_KEYS = [
        'livekit_api_secret',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }

        return $default ?? (static::DEFAULTS[$key] ?? null);
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            static::updateOrCreate(
                ['key' => $key],
                ['value' => $value === null ? null : (string) $value]
            );
        }

        Cache::forget('app.settings');
    }

    public static function allCached(): array
    {
        return Cache::remember('app.settings', 3600, function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public static function forForm(): array
    {
        $out = [];
        foreach (static::DEFAULTS as $key => $default) {
            if (in_array($key, static::SECRET_KEYS, true)) {
                $out[$key] = '';
                $out[$key.'_set'] = filled(static::get($key));
                continue;
            }
            $out[$key] = static::get($key, $default);
        }

        return $out;
    }

    public static function applyToConfig(): void
    {
        $name = static::get('app_name');
        if ($name) {
            config(['app.name' => $name]);
        }

        $fromName = static::get('mail_from_name');
        $fromAddress = static::get('mail_from_address');
        if ($fromName) {
            config(['mail.from.name' => $fromName]);
        }
        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
        }
    }
}
