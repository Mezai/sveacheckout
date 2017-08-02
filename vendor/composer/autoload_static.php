<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7f3462834b83874848b3759b9fff8e9a
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Svea\\Checkout\\' => 14,
            'SveaCheckout\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Svea\\Checkout\\' => 
        array (
            0 => __DIR__ . '/..' . '/sveaekonomi/checkout/src',
        ),
        'SveaCheckout\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7f3462834b83874848b3759b9fff8e9a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7f3462834b83874848b3759b9fff8e9a::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}