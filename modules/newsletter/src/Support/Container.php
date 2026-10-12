<?php
/**
 * PartoCMS - Newsletter DI Container
 *
 * A minimal PSR-11-inspired dependency injection container.
 * Supports:
 *   - bind(abstract, concrete, shared)
 *   - singleton(abstract, concrete)
 *   - instance(abstract, object)
 *   - Autowiring via Reflection (constructor injection)
 *
 * @package PartoCMS\Newsletter\Support
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Support;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container
{
    private static ?Container $instance = null;

    /** @var array<string, array{concrete: mixed, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    private function __construct() {}
    private function __clone() {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize singleton container.');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a binding.
     *
     * @param string $abstract Interface or class name
     * @param mixed  $concrete Class name, closure, or pre-built object
     * @param bool   $shared   True = singleton
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => $shared,
        ];
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Resolve a dependency.
     */
    public function get(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding  = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;
        $shared   = $binding['shared']   ?? false;

        $resolved = $this->build($concrete);

        if ($shared) {
            $this->instances[$abstract] = $resolved;
        }

        return $resolved;
    }

    /**
     * Build a concrete instance (with autowiring).
     */
    private function build(mixed $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }
        if (is_object($concrete)) {
            return $concrete;
        }
        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new RuntimeException("Cannot resolve: " . var_export($concrete, true));
        }

        $reflection = new ReflectionClass($concrete);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Not instantiable: {$concrete}");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $args[] = $this->resolveParameter($param);
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Resolve a single constructor parameter.
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        if ($param->allowsNull()) {
            return null;
        }

        $owner = $param->getDeclaringClass()?->getName() ?? 'unknown';
        throw new RuntimeException(
            "Cannot resolve \${$param->getName()} in {$owner}"
        );
    }

    /**
     * Reset for tests.
     */
    public function reset(): void
    {
        $this->bindings = [];
        $this->instances = [];
    }
}
