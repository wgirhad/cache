<?php

namespace wgirhad\Cache;

class InvalidArgumentException extends \InvalidArgumentException implements
    \Psr\SimpleCache\InvalidArgumentException
{
}
