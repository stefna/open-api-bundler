<?php declare(strict_types=1);

namespace Stefna\OpenApiBundler\Enums;

enum SchemaType
{
	case Schema;
	case RequestBodies;
	case Responses;
	case Parameters;
	case Paths;
}
