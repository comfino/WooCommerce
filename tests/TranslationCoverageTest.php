<?php

namespace Comfino\Tests;

/**
 * Every gettext source string of the plugin has a Polish translation, and the Polish file has no orphans. A source
 * string changed without regenerating the .pot/.po silently falls back to English.
 */
class TranslationCoverageTest extends \PHPUnit_Framework_TestCase
{
    private const DOMAIN = 'comfino-payment-gateway';
    private const GETTEXT_FUNCTIONS = ['__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e'];

    public function testEverySourceStringHasPolishTranslation(): void
    {
        $translations = $this->parsePoFile(dirname(__DIR__) . '/languages/comfino-payment-gateway-pl_PL.po');
        $missing = [];

        foreach ($this->collectSourceStrings() as $sourceString => $location) {
            if (!isset($translations[$sourceString]) || $translations[$sourceString] === '') {
                $missing[] = "$location: $sourceString";
            }
        }

        $this->assertSame([], $missing);
    }

    public function testPolishFileHasNoOrphans(): void
    {
        $sourceStrings = $this->collectSourceStrings();
        $headerStrings = $this->collectPluginHeaderStrings();
        $orphans = [];

        foreach (array_keys($this->parsePoFile(dirname(__DIR__) . '/languages/comfino-payment-gateway-pl_PL.po')) as $msgid) {
            if ($msgid !== '' && !isset($sourceStrings[$msgid]) && !in_array($msgid, $headerStrings, true)) {
                $orphans[] = $msgid;
            }
        }

        $this->assertSame([], $orphans);
    }

    /**
     * @return array<string, string> Source string => first location
     */
    private function collectSourceStrings(): array
    {
        $baseDir = dirname(__DIR__);
        $files = [$baseDir . '/comfino-payment-gateway.php'];

        foreach (['src', 'views'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$baseDir/$directory"));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $strings = [];

        foreach ($files as $file) {
            $tokens = array_values(array_filter(token_get_all(file_get_contents($file)), static function ($token): bool {
                return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }));

            foreach ($tokens as $index => $token) {
                if (!is_array($token) || $token[0] !== T_STRING || !in_array($token[1], self::GETTEXT_FUNCTIONS, true) ||
                    ($tokens[$index + 1] ?? null) !== '(' ||
                    !is_array($text = $tokens[$index + 2] ?? null) || $text[0] !== T_CONSTANT_ENCAPSED_STRING ||
                    ($tokens[$index + 3] ?? null) !== ',' ||
                    !is_array($domain = $tokens[$index + 4] ?? null) || $this->unquote($domain[1]) !== self::DOMAIN
                ) {
                    continue;
                }

                $string = $this->unquote($text[1]);

                if (!isset($strings[$string])) {
                    $strings[$string] = substr($file, strlen($baseDir) + 1) . ':' . $token[2];
                }
            }
        }

        return $strings;
    }

    /**
     * @return string[] Translatable plugin header values (WordPress translates them from the main plugin file)
     */
    private function collectPluginHeaderStrings(): array
    {
        $header = file_get_contents(dirname(__DIR__) . '/comfino-payment-gateway.php', false, null, 0, 2048);

        preg_match_all('/^\s*\*\s*(?:Plugin Name|Plugin URI|Description|Author|Author URI):\s*(.+)$/m', $header, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * @return array<string, string> msgid => msgstr
     */
    private function parsePoFile(string $path): array
    {
        $entries = [];
        $msgid = null;
        $msgstr = null;
        $field = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (strpos($line, 'msgid "') === 0) {
                if ($msgid !== null) {
                    $entries[$msgid] = $msgstr;
                }

                $msgid = stripcslashes(substr($line, 7, -1));
                $msgstr = '';
                $field = 'msgid';
            } elseif (strpos($line, 'msgstr "') === 0) {
                $msgstr = stripcslashes(substr($line, 8, -1));
                $field = 'msgstr';
            } elseif (strpos($line, '"') === 0 && $field !== null) {
                if ($field === 'msgid') {
                    $msgid .= stripcslashes(substr($line, 1, -1));
                } else {
                    $msgstr .= stripcslashes(substr($line, 1, -1));
                }
            } elseif (trim($line) === '' || strpos($line, '#') === 0) {
                $field = null;
            }
        }

        if ($msgid !== null) {
            $entries[$msgid] = $msgstr;
        }

        return $entries;
    }

    private function unquote(string $literal): string
    {
        $quote = $literal[0];
        $body = substr($literal, 1, -1);

        return $quote === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $body) : stripcslashes($body);
    }
}
