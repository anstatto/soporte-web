<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\LiveKitConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $status = LiveKitConfig::status();

        return Inertia::render('Ajustes/Index', [
            'settings' => Setting::forForm(),
            'envInfo' => [
                'app_url' => config('app.url'),
                'app_env' => config('app.env'),
                'timezone' => config('app.timezone'),
                'mail_mailer' => config('mail.default'),
                'mail_host' => config('mail.mailers.smtp.host'),
                'queue' => config('queue.default'),
                'broadcast' => config('broadcasting.default'),
                'livekit_ready' => $status['enabled'],
                'livekit_flag' => $status['flag'],
                'livekit_url' => $status['url_host'],
                'livekit_has_key' => $status['has_key'],
                'livekit_has_secret' => $status['has_secret'],
                'livekit_source' => $status['source'],
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'app_name' => 'required|string|max:120',
            'company_name' => 'required|string|max:120',
            'support_email' => 'nullable|email|max:120',
            'mail_from_name' => 'required|string|max:120',
            'mail_from_address' => 'nullable|email|max:120',
            'report_footer' => 'nullable|string|max:500',
            'tickets_per_page' => 'nullable|integer|min:10|max:100',
            'livekit_enabled' => 'nullable|boolean',
            'livekit_url' => 'nullable|string|max:255',
            'livekit_api_key' => 'nullable|string|max:255',
            'livekit_api_secret' => 'nullable|string|max:255',
            'livekit_ring_timeout' => 'nullable|integer|min:15|max:180',
            'livekit_allow_video' => 'nullable|boolean',
            'livekit_max_call_minutes' => 'nullable|integer|min:0|max:180',
            'livekit_video_quality' => 'nullable|in:low,medium',
        ]);

        $pairs = [
            'app_name' => $data['app_name'],
            'company_name' => $data['company_name'],
            'support_email' => $data['support_email'] ?? '',
            'mail_from_name' => $data['mail_from_name'],
            'mail_from_address' => $data['mail_from_address'] ?? '',
            'report_footer' => $data['report_footer'] ?? '',
            'tickets_per_page' => (string) ($data['tickets_per_page'] ?? 24),
            'livekit_enabled' => ! empty($data['livekit_enabled']) ? '1' : '0',
            'livekit_url' => trim((string) ($data['livekit_url'] ?? '')),
            'livekit_api_key' => trim((string) ($data['livekit_api_key'] ?? '')),
            'livekit_ring_timeout' => (string) ($data['livekit_ring_timeout'] ?? 45),
            'livekit_allow_video' => ! empty($data['livekit_allow_video']) ? '1' : '0',
            'livekit_max_call_minutes' => (string) ($data['livekit_max_call_minutes'] ?? 20),
            'livekit_video_quality' => $data['livekit_video_quality'] ?? 'low',
        ];

        $secret = trim((string) ($data['livekit_api_secret'] ?? ''));
        if ($secret !== '') {
            $pairs['livekit_api_secret'] = $secret;
        }

        Setting::setMany($pairs);
        Setting::applyToConfig();

        return redirect()->route('ajustes.index')->with('success', 'Ajustes guardados.');
    }

    public function testMail(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $data = $request->validate([
            'to' => 'required|email',
        ]);

        try {
            Setting::applyToConfig();
            Mail::raw(
                'Prueba de correo desde '.Setting::get('app_name').'. Si recibes esto, el mailer está operativo.',
                function ($message) use ($data) {
                    $message->to($data['to'])
                        ->subject('Prueba de correo — '.Setting::get('app_name'));
                }
            );

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Correo de prueba enviado a '.$data['to'].'.']);
            }

            return back()->with('success', 'Correo de prueba enviado a '.$data['to'].'.');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No se pudo enviar: '.$e->getMessage()], 422);
            }

            return back()->with('error', 'No se pudo enviar: '.$e->getMessage());
        }
    }
}
