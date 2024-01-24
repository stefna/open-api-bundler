<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Starburst;

use Starburst\Contracts\Bootloader;
use Starburst\Contracts\Extensions\CliCommandProvider;
use Starburst\Contracts\Extensions\DefinitionProvider;
use Stefna\DependencyInjection\Definition\DefinitionArray;
use Stefna\DependencyInjection\Helper\Autowire;
use Stefna\OpenApiBundler\Command\BundleCommand;
use Stefna\OpenApiBundler\Definition\BundleDefinition;

final class OpenApiBundleBootstrap implements Bootloader, CliCommandProvider, DefinitionProvider
{
	public function createDefinitionSource(): DefinitionSource
	{
		return new DefinitionArray([
			BundleCommand::class => Autowire::cls(),
		]);
	}

	public function createCliDefinitions(): array
	{
		$definition = new BundleDefinition();
		// replace command with one that will be fetched from container
		$definition->setCommand(BundleCommand::class);

		return [
			$definition,
		];
	}
}
