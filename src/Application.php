<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler;

use Circli\Console\Definition;
use Circli\Console\SimpleCommandResolver;

final class Application extends \Circli\Console\Application
{
	public function __construct(Definition $definition)
	{
		parent::__construct(new SimpleCommandResolver());
		$this->setName('Bundle open-api specification');
		$this->addDefinition($definition);
		$this->setDefaultCommand($definition->getName(), true);
	}
}
