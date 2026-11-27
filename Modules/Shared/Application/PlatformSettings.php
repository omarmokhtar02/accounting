<?php

namespace Modules\Shared\Application;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Typed key/value settings the platform admin can change at runtime (nothing here is hard-coded
 * policy: trial length, grace period and billing texts all live in this table). Defaults are
 * declared in code and used until an admin overrides them.
 */
final class PlatformSettings
{
    /** @var array<string, array{type: string, default: mixed}> */
    public const DEFINITIONS = [
        'trial.days' => ['type' => 'int', 'default' => 14],
        'trial.plan_code' => ['type' => 'string', 'default' => 'business'],
        'subscription.readonly_grace_days' => ['type' => 'int', 'default' => 7],
        'billing.instructions_en' => ['type' => 'string', 'default' => 'Contact us to activate your subscription.'],
        'billing.instructions_ar' => ['type' => 'string', 'default' => 'تواصل معنا لتفعيل اشتراكك.'],
        'billing.contact_email' => ['type' => 'string', 'default' => ''],
    ];

    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function get(string $key): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? throw new InvalidArgumentException("Unknown platform setting: {$key}");
        $stored = $this->all()[$key] ?? null;

        return $stored === null ? $definition['default'] : $this->cast($stored, $definition['type']);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $definition = self::DEFINITIONS[$key] ?? throw new InvalidArgumentException("Unknown platform setting: {$key}");

        if ($definition['type'] === 'int' && (! is_numeric($value) || (int) $value < 0)) {
            throw new InvalidArgumentException("Setting {$key} must be a non-negative integer.");
        }

        DB::table('platform_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'type' => $definition['type'], 'updated_at' => now(), 'created_at' => now()],
        );

        $this->cache = null;
    }

    /** @return array<string, mixed> every setting with its effective value (stored or default) */
    public function effective(): array
    {
        return collect(self::DEFINITIONS)->mapWithKeys(fn ($d, $key) => [$key => $this->get($key)])->all();
    }

    public function forget(): void
    {
        $this->cache = null;
    }

    /** @return array<string, string|null> */
    private function all(): array
    {
        return $this->cache ??= DB::table('platform_settings')->pluck('value', 'key')->all();
    }

    private function cast(string $value, string $type): mixed
    {
        return $type === 'int' ? (int) $value : $value;
    }
}
