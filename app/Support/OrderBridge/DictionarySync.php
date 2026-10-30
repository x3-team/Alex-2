<?php

namespace App\Support\OrderBridge;

use App\Models\LabDictionarySnapshot;

class DictionarySync
{
    public function __construct(private DnkomClient $dnkom) {}

    public function run(): void
    {
        if (! BridgeConfig::backgroundEnabled() || ! BridgeConfig::outboundReady()) {
            return;
        }

        $sender = trim((string) config('order_bridge.dnkom.sender_unpaid'));
        $raw = $this->dnkom->exchange(DnkomXml::envelope('query-dictionaries-version', $sender));
        $dom = DnkomXml::document($raw);
        $version = $dom ? DnkomXml::version($dom) : null;
        if (! $version) {
            throw new LabBridgeException('dnkom_dictionaries');
        }
        if (LabDictionarySnapshot::query()->where('version', $version)->exists()) {
            return;
        }

        $payload = $this->dnkom->exchange(DnkomXml::envelope('query-dictionaries', $sender));
        LabDictionarySnapshot::query()->create([
            'version' => $version,
            'payload' => DnkomXml::toUtf8($payload),
            'synced_at' => now(),
        ]);
    }
}
