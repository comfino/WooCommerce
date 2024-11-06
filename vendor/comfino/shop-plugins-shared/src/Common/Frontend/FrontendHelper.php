<?php

namespace Comfino\Common\Frontend;

final class FrontendHelper
{
    public static function getLogoAuthHash(string $platformCode, string $platformVersion, string $pluginVersion, int $buildTimestamp): string
    {
        return urlencode(base64_encode(self::getLogoAuthKey($platformCode, $platformVersion, $pluginVersion, $buildTimestamp)));
    }

    public static function getPaywallLogoAuthHash(string $platformCode, string $platformVersion, string $pluginVersion, string $apiKey, string $widgetKey, int $buildTimestamp): string
    {
        $authKey = self::getLogoAuthKey($platformCode, $platformVersion, $pluginVersion, $buildTimestamp) . $widgetKey;
        $authKey .= hash_hmac('sha3-256', $authKey, $apiKey, true);

        return urlencode(base64_encode($authKey));
    }

    public static function getLogoAuthKey(string $platformCode, string $platformVersion, string $pluginVersion, int $buildTimestamp): string
    {
        $packedPlatformVersion = pack('c*', ...array_map('intval', explode('.', $platformVersion)));
        $packedPluginVersion = pack('c*', ...array_map('intval', explode('.', $pluginVersion)));
        $platformVersionLength = pack('c', strlen($packedPlatformVersion));
        $pluginVersionLength = pack('c', strlen($packedPluginVersion));
        $packedBuildTimestamp = pack('J', $buildTimestamp); // unsigned 64-bit, big endian byte order

        $authKeyParts = [
            $platformCode,
            $platformVersionLength,
            $pluginVersionLength,
            $packedPlatformVersion,
            $packedPluginVersion,
            $packedBuildTimestamp,
        ];

        return implode($authKeyParts);
    }
}
