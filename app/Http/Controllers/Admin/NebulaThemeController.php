<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Contracts\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

/**
 * Nebula theme settings: logo (+ favicon), login background.
 *
 * Settings live in storage/app/nebula-theme.json and uploads in
 * public/themes/nebula/uploads, so no database migration is needed.
 */
class NebulaThemeController extends Controller
{
    public const SETTINGS_FILE = 'app/nebula-theme.json';
    public const UPLOAD_PATH = 'themes/nebula/uploads';
    public const UPLOAD_URL = '/themes/nebula/uploads';

    private const DEFAULTS = [
        'logo' => null,           // file name inside UPLOAD_PATH
        'logo_w' => 0,            // intrinsic size, used for the aspect ratio
        'logo_h' => 0,
        'logo_height' => 32,      // px, height of the logo in the navbar
        'show_name' => true,      // show the panel name next to the logo
        'bg_type' => 'gradient',  // gradient | image
        'bg_image' => null,
        'bg_overlay' => 45,       // % darkness over the image
        'bg_blur' => 0,           // px blur of the image
    ];

    private static ?array $cache = null;

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.nebula.index', [
            'settings' => self::settings(),
            'resolved' => self::resolved(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($request->has('reset')) {
            $current = self::settings();
            $this->deleteUpload($current['logo']);
            $this->deleteUpload($current['bg_image']);
            self::write(self::DEFAULTS);
            $this->alert->success('Nebula theme settings were reset to defaults.')->flash();

            return redirect()->route('admin.nebula');
        }

        $data = $request->validate([
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp,gif|max:2048',
            'bg_image' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:8192',
            'logo_height' => 'required|integer|between:20,64',
            'bg_type' => 'required|in:gradient,image',
            'bg_overlay' => 'required|integer|between:0,90',
            'bg_blur' => 'required|integer|between:0,24',
        ]);

        $settings = self::settings();

        // logo
        if ($request->boolean('remove_logo')) {
            $this->deleteUpload($settings['logo']);
            $settings['logo'] = null;
            $settings['logo_w'] = $settings['logo_h'] = 0;
        }
        if ($request->hasFile('logo')) {
            $this->deleteUpload($settings['logo']);
            [$settings['logo'], $settings['logo_w'], $settings['logo_h']] = $this->store($request->file('logo'), 'logo');
        }

        // login background
        if ($request->boolean('remove_bg')) {
            $this->deleteUpload($settings['bg_image']);
            $settings['bg_image'] = null;
        }
        if ($request->hasFile('bg_image')) {
            $this->deleteUpload($settings['bg_image']);
            [$settings['bg_image']] = $this->store($request->file('bg_image'), 'login-bg');
        }

        $settings['logo_height'] = (int) $data['logo_height'];
        $settings['show_name'] = $request->boolean('show_name');
        $settings['bg_type'] = $data['bg_type'];
        $settings['bg_overlay'] = (int) $data['bg_overlay'];
        $settings['bg_blur'] = (int) $data['bg_blur'];

        // "image" without an image falls back to the gradient
        if ($settings['bg_type'] === 'image' && empty($settings['bg_image'])) {
            $settings['bg_type'] = 'gradient';
            $this->alert->warning('Choose a background image first - the gradient is used until you upload one.')->flash();
        }

        self::write($settings);
        $this->alert->success('Nebula theme settings were saved.')->flash();

        return redirect()->route('admin.nebula');
    }

    /* ------------------------------------------------------------------ */

    /** Raw settings merged over the defaults (file names validated). */
    public static function settings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        $path = storage_path(self::SETTINGS_FILE);
        if (is_file($path)) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        }

        $settings = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        foreach (['logo', 'bg_image'] as $key) {
            $file = $settings[$key];
            if (!is_string($file) || !preg_match('/^[A-Za-z0-9._-]+$/', $file) || !is_file(public_path(self::UPLOAD_PATH . '/' . $file))) {
                $settings[$key] = null;
            }
        }

        return self::$cache = $settings;
    }

    /** Values the Blade views / CSS need (urls, clamped numbers). */
    public static function resolved(): array
    {
        $s = self::settings();

        $url = function (?string $file): ?string {
            if (!$file) {
                return null;
            }
            $mtime = @filemtime(public_path(self::UPLOAD_PATH . '/' . $file)) ?: time();

            return self::UPLOAD_URL . '/' . $file . '?v=' . $mtime;
        };

        $aspect = ($s['logo_w'] > 0 && $s['logo_h'] > 0) ? $s['logo_w'] / $s['logo_h'] : 1;
        $aspect = max(0.25, min(6, round($aspect, 3)));

        return [
            'has_logo' => (bool) $s['logo'],
            'logo_url' => $url($s['logo']),
            'logo_height' => max(20, min(64, (int) $s['logo_height'])),
            'logo_aspect' => $aspect,
            'show_name' => (bool) $s['show_name'],
            'has_bg' => $s['bg_type'] === 'image' && $s['bg_image'],
            'bg_url' => $url($s['bg_image']),
            'bg_overlay' => max(0, min(90, (int) $s['bg_overlay'])) / 100,
            'bg_blur' => max(0, min(24, (int) $s['bg_blur'])),
        ];
    }

    private static function write(array $settings): void
    {
        $path = storage_path(self::SETTINGS_FILE);
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        self::$cache = null;
    }

    /** @return array{0: string, 1: int, 2: int} stored file name, width, height */
    private function store(UploadedFile $file, string $prefix): array
    {
        $dir = public_path(self::UPLOAD_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $name = $prefix . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $file->move($dir, $name);

        $size = @getimagesize($dir . '/' . $name);

        return [$name, (int) ($size[0] ?? 0), (int) ($size[1] ?? 0)];
    }

    private function deleteUpload(?string $file): void
    {
        if ($file && preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
            @unlink(public_path(self::UPLOAD_PATH . '/' . $file));
        }
    }
}
