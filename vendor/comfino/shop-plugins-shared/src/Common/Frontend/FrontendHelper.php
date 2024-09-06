<?php

namespace Comfino\Common\Frontend;

final class FrontendHelper
{
    public static function getLogoAuthHash(string $platformCode, string $platformVersion, string $pluginVersion): string
    {
        return urlencode(base64_encode(self::getLogoAuthKey($platformCode, $platformVersion, $pluginVersion)));
    }

    public static function getPaywallLogoAuthHash(string $platformCode, string $platformVersion, string $pluginVersion, string $apiKey, string $widgetKey): string
    {
        $authKey = self::getLogoAuthKey($platformCode, $platformVersion, $pluginVersion) . $widgetKey;
        $authKey .= hash_hmac('sha3-256', $authKey, $apiKey, true);

        return urlencode(base64_encode($authKey));
    }

    public static function getLogoAuthKey(string $platformCode, string $platformVersion, string $pluginVersion): string
    {
        $packedPlatformVersion = pack('c*', ...array_map('intval', explode('.', $platformVersion)));
        $packedPluginVersion = pack('c*', ...array_map('intval', explode('.', $pluginVersion)));
        $platformVersionLength = pack('c', strlen($packedPlatformVersion));
        $pluginVersionLength = pack('c', strlen($packedPluginVersion));

        $authKeyParts = [
            $platformCode,
            $platformVersionLength,
            $pluginVersionLength,
            $packedPlatformVersion,
            $packedPluginVersion,
        ];

        return implode($authKeyParts);
    }
}
