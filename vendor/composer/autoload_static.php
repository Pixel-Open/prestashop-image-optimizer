<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf15fe053389ca45be8317f88ab685bb1
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Pixel\\Module\\ImageOptimizer\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Pixel\\Module\\ImageOptimizer\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf15fe053389ca45be8317f88ab685bb1::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf15fe053389ca45be8317f88ab685bb1::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitf15fe053389ca45be8317f88ab685bb1::$classMap;

        }, null, ClassLoader::class);
    }
}
