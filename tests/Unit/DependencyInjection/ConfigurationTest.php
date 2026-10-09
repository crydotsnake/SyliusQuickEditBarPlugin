<?php

declare(strict_types=1);

namespace Tests\SKrull\SyliusQuickEditBarPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SKrull\SyliusQuickEditBarPlugin\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testItShowsTheBarAtTheBottomAndListsTwentyProductVariantsByDefault(): void
    {
        self::assertSame(['position' => 'bottom', 'product' => ['max_variants' => 20]], $this->process([]));
    }

    public function testItAcceptsTheTopPosition(): void
    {
        self::assertSame('top', $this->process([['position' => 'top']])['position']);
    }

    public function testItRejectsAnUnknownPosition(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['position' => 'left']]);
    }

    public function testItAcceptsACustomNumberOfProductVariants(): void
    {
        self::assertSame(50, $this->process([['product' => ['max_variants' => 50]]])['product']['max_variants']);
    }

    public function testItRejectsLessThanOneProductVariant(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['product' => ['max_variants' => 0]]]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array{position: string, product: array{max_variants: int}}
     */
    private function process(array $configs): array
    {
        /** @var array{position: string, product: array{max_variants: int}} $config */
        $config = (new Processor())->processConfiguration(new Configuration(), $configs);

        return $config;
    }
}
