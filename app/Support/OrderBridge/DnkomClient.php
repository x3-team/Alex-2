<?php

namespace App\Support\OrderBridge;

use Illuminate\Support\Facades\Http;

class DnkomClient
{
    public function exchange(string $xml): string
    {
        $url = trim((string) config('order_bridge.dnkom.url'));
        if ($url === '') {
            throw new LabBridgeException('dnkom_not_configured');
        }

        try {
            $response = Http::timeout(30)
                ->withBody($xml, 'text/xml; charset=windows-1251')
                ->accept('text/xml, application/pdf, */*')
                ->send('POST', $url);
        } catch (\Throwable $e) {
            throw new LabBridgeException('transport');
        }

        if (! $response->successful()) {
            throw new LabBridgeException('dnkom_http');
        }

        return $response->body();
    }
}
