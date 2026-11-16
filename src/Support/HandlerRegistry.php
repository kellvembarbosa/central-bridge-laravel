<?php

namespace Applu\CentralBridge\Support;

use Illuminate\Contracts\Container\Container;
use LogicException;

/**
 * Resolves the product's handler for a contract: first the
 * `central-bridge.handlers` map, then a container binding of the contract.
 */
class HandlerRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string  $contract
     */
    public function has(string $contract): bool
    {
        return $this->configured($contract) !== null || $this->container->bound($contract);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T|null
     */
    public function resolve(string $contract): ?object
    {
        $class = $this->configured($contract);

        if ($class === null && ! $this->container->bound($contract)) {
            return null;
        }

        $handler = $this->container->make($class ?? $contract);

        if (! $handler instanceof $contract) {
            throw new LogicException(sprintf(
                'Central Bridge handler [%s] must implement [%s].',
                get_debug_type($handler),
                $contract,
            ));
        }

        return $handler;
    }

    private function configured(string $contract): ?string
    {
        $class = config('central-bridge.handlers', [])[$contract] ?? null;

        return is_string($class) && $class !== '' ? $class : null;
    }
}
