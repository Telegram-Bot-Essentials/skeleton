<?php

declare(strict_types=1);

namespace TelegramBotEssentials\Skeleton\Tests;

use TelegramBotEssentials\Essence\Testing\TestCase as EssenceTestCase;
use TelegramBotEssentials\Skeleton\TbeSkeletonServiceProvider;

abstract class TestCase extends EssenceTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            TbeSkeletonServiceProvider::class,
        ]);
    }
}
