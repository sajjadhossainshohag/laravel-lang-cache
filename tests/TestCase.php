<?php

namespace SajjadHossainShohag\LangCache\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SajjadHossainShohag\LangCache\LangCacheServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [LangCacheServiceProvider::class];
    }
}
