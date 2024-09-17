<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Starburst;

use Starburst\Contracts\Bootloader;
use Starburst\Contracts\Extensions\CliCommandProvider;
use Starburst\Contracts\Extensions\DefinitionProvider;
use Stefna\DependencyInjection\Definition\DefinitionArray;
use Stefna\DependencyInjection\Definition\DefinitionSource;
use Stefna\DependencyInjection\Helper\Autowire;
use Stefna\OpenApiBundler\Command\BundleCommand;
use Stefna\OpenApiBundler\Command\InlineCommand;
use Stefna\OpenApiBundler\Definition\SchemaDefinition;
use Stefna\OpenApiBundler\Starburst\Definition\BundleDefinition;

final class OpenApiBundleBootstrap implements Bootloader, CliCommandProvider, DefinitionProvider
{
	public function createDefinitionSource(): DefinitionSource
	{
		return new DefinitionArray([
			BundleDefinition::class => Autowire::cls(),
			BundleCommand::class => Autowire::cls(),
			InlineCommand::class => Autowire::cls(),
		]);
	}

	public function createCliDefinitions(): array
	{
		return [
			BundleDefinition::class,
			new SchemaDefinition('inline', InlineCommand::class, true),
		];
	}
}
