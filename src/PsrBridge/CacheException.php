<?php

namespace Dburiy\PsrBridge;

use Exception;
use Psr\Cache\CacheException as PsrCacheException;

class CacheException extends Exception implements PsrCacheException
{

}
