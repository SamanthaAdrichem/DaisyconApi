<?php

namespace SamanthaAdrichem\DaisyconApi;

use SamanthaAdrichem\DaisyconApi\Trait\EnumToNativeTrait;

enum MethodEnum
{
	use EnumToNativeTrait;

	case Delete;
	case Get;
	case GetAsPost;
	case Post;
	case Put;
}
