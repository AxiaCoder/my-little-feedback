<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the one thing every later test depends on: that the kernel boots and
 * the service container compiles. When a bundle is misconfigured, this fails
 * first and with a readable message, instead of every other test failing at once.
 */
final class SmokeTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        // bootKernel() returns a non-nullable kernel; self::$kernel is nullable,
        // and static analysis is right to reject reading it directly.
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());
    }
}
