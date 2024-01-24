<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler;

use Circli\Console\SimpleCommandResolver;
use Stefna\OpenApiBundler\Definition\BundleDefinition;

final class Application extends \Circli\Console\Application
{
	public function __construct()
	{
		parent::__construct(new SimpleCommandResolver());
		$this->setName('Bundle open-api specification');
		$this->addDefinition(new BundleDefinition());
		$this->setDefaultCommand(BundleDefinition::NAME, true);
	}
}
