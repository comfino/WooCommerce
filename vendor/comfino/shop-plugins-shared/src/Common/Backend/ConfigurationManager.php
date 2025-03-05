<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use Comfino\Common\Backend\Configuration\StorageAdapterInterface;

final class ConfigurationManager
{
    /**
     * @var int[]
     * @readonly
     */
    private $availConfigOptions;
    /**
     * @var string[]
     * @readonly
     */
    private $accessibleConfigOptions;
    /**
     * @var int
     * @readonly
     */
    private $options;
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Configuration\StorageAdapterInterface
     */
    private $storageAdapter;
    /**
     * @readonly
     * @var \Comfino\Api\SerializerInterface
     */
    private $serializer;
    // Data types of configuration options as bit masks.
    public const OPT_VALUE_TYPE_STRING = (1 << 0);
    public const OPT_VALUE_TYPE_INT = (1 << 1);
    public const OPT_VALUE_TYPE_FLOAT = (1 << 2);
    public const OPT_VALUE_TYPE_BOOL = (1 << 3);
    public const OPT_VALUE_TYPE_ARRAY = (1 << 4);
    public const OPT_VALUE_TYPE_JSON = (1 << 5);
    public const OPT_VALUE_TYPE_STRING_ARRAY = self::OPT_VALUE_TYPE_STRING | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_INT_ARRAY = self::OPT_VALUE_TYPE_INT | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_FLOAT_ARRAY = self::OPT_VALUE_TYPE_FLOAT | self::OPT_VALUE_TYPE_ARRAY;
    public const OPT_VALUE_TYPE_BOOL_ARRAY = self::OPT_VALUE_TYPE_BOOL | self::OPT_VALUE_TYPE_ARRAY;

    public const OPT_SERIALIZE_ARRAYS = 1 << 0;

    /**
     * @var $this|null
     */
    private static $instance;
    /**
     * @var mixed[]|null
     */
    private $configuration;
    /**
     * @var mixed[]
     */
    private $modified;
    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @param int $options Options (bit field with flags set by OPT_X constants).
     * @param int[] $availConfigOptions List of available configuration options with data types as pairs [OPTION_NAME => OPT_VALUE_TYPE].
     * @param string[] $accessibleConfigOptions List of accessible configuration options via REST endpoints.
     */
    public static function getInstance(
        array $availConfigOptions,
        array $accessibleConfigOptions,
        int $options,
        StorageAdapterInterface $storageAdapter,
        SerializerInterface $serializer
    ): self {
        if (self::$instance === null) {
            self::$instance = new self($availConfigOptions, $accessibleConfigOptions, $options, $storageAdapter, $serializer);
        }

        return self::$instance;
    }

    /**
     * @param int $options Options (bit field with flags set by OPT_X constants).
     * @param int[] $availConfigOptions List of available configuration options with data types as pairs [OPTION_NAME => OPT_VALUE_TYPE].
     * @param string[] $accessibleConfigOptions List of accessible configuration options via REST endpoints.
     */
    private function __construct(
        array $availConfigOptions,
        array $accessibleConfigOptions,
        int $options,
        StorageAdapterInterface $storageAdapter,
        SerializerInterface $serializer
    ) {
        $this->availConfigOptions = $availConfigOptions;
        $this->accessibleConfigOptions = $accessibleConfigOptions;
        $this->options = $options;
        $this->storageAdapter = $storageAdapter;
        $this->serializer = $serializer;
        $this->modified = array_combine(array_keys($availConfigOptions), array_fill(0, count($availConfigOptions), false));
    }

    public function __destruct()
    {
        $this->persist();
    }

    public function returnConfigurationOptions(): array
    {
        return $this->getConfigurationValues($this->accessibleConfigOptions);
    }

    public function updateConfigurationOptions(array $configurationOptions): void
    {
        $this->setConfigurationValues($configurationOptions, $this->accessibleConfigOptions);
    }

    /**
     * @return mixed
     */
    public function getConfigurationValue(string $optionName)
    {
        return $this->getConfiguration()[$optionName] ?? null;
    }

    /**
     * @param string[] $optionNames
     */
    public function getConfigurationValues(array $optionNames): array
    {
        return array_intersect_key($this->getConfiguration(), array_flip($optionNames));
    }

    /**
     * @param mixed $optionValue
     */
    public function setConfigurationValue(string $optionName, $optionValue): void
    {
        if (isset($this->availConfigOptions[$optionName])) {
            $this->getConfiguration()[$optionName] = $optionValue;
            $this->modified[$optionName] = true;
        }
    }

    public function setConfigurationValues(array $configurationOptions, ?array $accessibleOptions = null): void
    {
        if ($this->configuration === null) {
            $this->configuration = [];
        }

        foreach ($configurationOptions as $optionName => $optionValue) {
            if (empty($accessibleOptions) || in_array($optionName, $accessibleOptions, true)) {
                $this->configuration[$optionName] = $optionValue;
                $this->modified[$optionName] = true;
            }
        }
    }

    public function persist(): void
    {
        if ($this->configuration !== null && count($optionsToSave = array_intersect_key($this->configuration, array_filter($this->modified)))) {
            foreach ($optionsToSave as $optionName => &$optionValue) {
                if (($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_STRING) && is_string($optionValue)) {
                    $optionValue = trim($optionValue);
                }

                if (($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) && is_array($optionValue)) {
                    if ($this->options & self::OPT_SERIALIZE_ARRAYS) {
                        $optionValue = implode(',', $optionValue);
                    }
                } elseif ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_JSON) {
                    $optionValue = $this->serializer->serialize($optionValue);
                }
            }

            unset($optionValue);

            $this->storageAdapter->save($optionsToSave);

            $this->modified = array_merge($this->modified, array_combine(array_keys($optionsToSave), array_fill(0, count($optionsToSave), false)));
        }
    }

    private function &getConfiguration(): array
    {
        if ($this->configuration === null) {
            $this->configuration = [];

            $this->load();

            $this->loaded = true;
        } elseif (!$this->loaded) {
            $modifiedOptions = $this->configuration;

            $this->load();

            $this->configuration = array_merge($this->configuration, $modifiedOptions);
            $this->loaded = true;
        }

        return $this->configuration;
    }

    private function load(): void
    {
        foreach ($this->storageAdapter->load() as $optionName => $optionValue) {
            if (isset($this->availConfigOptions[$optionName])) {
                switch ($this->availConfigOptions[$optionName] & (~self::OPT_VALUE_TYPE_ARRAY)) {
                    case self::OPT_VALUE_TYPE_STRING:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            if (is_array($optionValue)) {
                                $this->configuration[$optionName] = array_map(
                                    static function ($value) : string {
                                        return (string) $value;
                                    },
                                    $optionValue
                                );
                            } else {
                                $this->configuration[$optionName] = (!empty($optionValue) ? array_map(
                                    static function ($value) : string {
                                        return (string) $value;
                                    },
                                    explode(',', $optionValue)
                                ) : ($optionValue !== null ? [] : null));
                            }
                        } else {
                            $this->configuration[$optionName] = ($optionValue !== null ? (string) $optionValue : null);
                        }

                        break;

                    case self::OPT_VALUE_TYPE_INT:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            if (is_array($optionValue)) {
                                $this->configuration[$optionName] = array_map(
                                    static function ($value) : int {
                                        return (int) $value;
                                    },
                                    $optionValue
                                );
                            } else {
                                $this->configuration[$optionName] = (!empty($optionValue) ? array_map(
                                    static function ($value) : int {
                                        return (int) $value;
                                    },
                                    explode(',', $optionValue)
                                ) : ($optionValue !== null ? [] : null));
                            }
                        } else {
                            $this->configuration[$optionName] = ($optionValue !== null ? (int) $optionValue : null);
                        }

                        break;

                    case self::OPT_VALUE_TYPE_FLOAT:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            if (is_array($optionValue)) {
                                $this->configuration[$optionName] = array_map(
                                    static function ($value) : float {
                                        return (float) $value;
                                    },
                                    $optionValue
                                );
                            } else {
                                $this->configuration[$optionName] = (!empty($optionValue) ? array_map(
                                    static function ($value) : float {
                                        return (float) $value;
                                    },
                                    explode(',', $optionValue)
                                ) : ($optionValue !== null ? [] : null));
                            }
                        } else {
                            $this->configuration[$optionName] = ($optionValue !== null ? (float) $optionValue : null);
                        }

                        break;

                    case self::OPT_VALUE_TYPE_BOOL:
                        if ($this->availConfigOptions[$optionName] & self::OPT_VALUE_TYPE_ARRAY) {
                            if (is_array($optionValue)) {
                                $this->configuration[$optionName] = array_map(
                                    static function ($value) : bool {
                                        return (bool) $value;
                                    },
                                    $optionValue
                                );
                            } else {
                                $this->configuration[$optionName] = (!empty($optionValue) ? array_map(
                                    static function ($value) : bool {
                                        return (bool) $value;
                                    },
                                    explode(',', $optionValue)
                                ) : ($optionValue !== null ? [] : null));
                            }
                        } else {
                            $this->configuration[$optionName] = (bool) $optionValue;
                        }

                        break;

                    case self::OPT_VALUE_TYPE_JSON:
                        if (is_array($optionValue)) {
                            $this->configuration[$optionName] = $optionValue;
                        } else {
                            $this->configuration[$optionName] = !empty($optionValue) ? $this->serializer->unserialize($optionValue) : null;
                        }

                        break;
                }
            }
        }
    }
}
