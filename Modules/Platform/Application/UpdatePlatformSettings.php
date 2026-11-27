<?php

namespace Modules\Platform\Application;

use Modules\Shared\Application\PlatformSettings;

final class UpdatePlatformSettings
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly RecordPlatformAudit $audit,
    ) {}

    /**
     * Saves the given settings (only known keys) and records what changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function handle(array $values): void
    {
        $changes = [];

        foreach (PlatformSettings::DEFINITIONS as $key => $definition) {
            $field = str_replace('.', '_', $key);

            if (! array_key_exists($field, $values) || $values[$field] === null) {
                continue;
            }

            $old = $this->settings->get($key);

            if ((string) $old !== (string) $values[$field]) {
                $this->settings->set($key, $values[$field]);
                $changes[$key] = ['from' => $old, 'to' => $this->settings->get($key)];
            }
        }

        if ($changes !== []) {
            $this->audit->handle('settings_updated', 'platform_settings', null, $changes);
        }
    }
}
