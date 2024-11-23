<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Starburst;

use JsonPointer\ReferenceResolver\PackageVendorReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolver;
use JsonPointer\ReferenceResolver\ReferenceResolverCollection;
use Psr\Container\ContainerInterface;
use Starburst\Contracts\Bootloader;
use Starburst\Contracts\Extensions\CliCommandProvider;
use Starburst\Contracts\Extensions\DefinitionProvider;
use Starburst\Core\BootloaderManager;
use Stefna\DependencyInjection\Definition\DefinitionArray;
use Stefna\DependencyInjection\Definition\DefinitionSource;
use Stefna\DependencyInjection\Helper\Autowire;
use Stefna\OpenApiBundler\Command\BundleCommand;
use Stefna\OpenApiBundler\Command\InlineCommand;
use Stefna\OpenApiBundler\Definition\SchemaDefinition;
use Stefna\OpenApiBundler\SchemaConfig;
use Stefna\OpenApiBundler\Starburst\Definition\BundleDefinition;

final class OpenApiBundleBootstrap implements Bootloader, CliCommandProvider, DefinitionProvider
{
	public function createDefinitionSource(): DefinitionSource
	{
		return new DefinitionArray([
			BundleDefinition::class => Autowire::cls(),
			BundleCommand::class => Autowire::cls(),
			ReferenceResolver::class => static function (ContainerInterface $container) {
				$resolverCollection = new ReferenceResolverCollection();
				$packageResolver = new PackageVendorReferenceResolver();
				$resolverCollection->addResolver($packageResolver);
				BootloaderManager::run(SpecificationProvider::class, $container, $packageResolver, $resolverCollection);

				return $resolverCollection;
			},
			InlineCommand::class => static function (ContainerInterface $container) {
				/** @var SchemaConfig|null $config */
				$config = $container->has(SchemaConfig::class) ? $container->get(SchemaConfig::class) : null;
				/** @var ReferenceResolver|null $referenceResolver */
				$referenceResolver = $container->has(ReferenceResolver::class) ? $container->get(ReferenceResolver::class) : null;

				return new InlineCommand(
					$config,
					$referenceResolver,
				);
			},
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
