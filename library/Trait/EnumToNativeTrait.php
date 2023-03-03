<?php

namespace SamanthaAdrichem\DaisyconApi\Trait;

use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

trait EnumToNativeTrait
{
	/**
	 * Convert enum value to string (native)
	 *
	 * @return string
	 */
	public function toNative(): string
	{
		return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->name);
	}

	/**
	 * Convert value to Enum
	 *
	 * @param string $name
	 * @return static|null
	 */
	public static function toEnum(string $name): ?static
	{
		foreach (static::cases() as $value)
		{
			if ($name === $value->toNative())
			{
				return $value;
			}
		}
		return null;
	}

}
