<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KmhDemoDataSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\DemoData\\Tests\\', __DIR__);
