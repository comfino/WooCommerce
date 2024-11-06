<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitda91cb319f0d473a3d4a60607a3e6ea6
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInitda91cb319f0d473a3d4a60607a3e6ea6', 'loadClassLoader'), true, false);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitda91cb319f0d473a3d4a60607a3e6ea6', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitda91cb319f0d473a3d4a60607a3e6ea6::getInitializer($loader));

        $loader->setClassMapAuthoritative(true);
        $loader->register(false);

        $filesToLoad = \Composer\Autoload\ComposerStaticInitda91cb319f0d473a3d4a60607a3e6ea6::$files;
        $requireFile = \Closure::bind(static function ($fileIdentifier, $file) {
            if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;

                require $file;
            }
        }, null, null);
        foreach ($filesToLoad as $fileIdentifier => $file) {
            $requireFile($fileIdentifier, $file);
        }

        return $loader;
    }
}
