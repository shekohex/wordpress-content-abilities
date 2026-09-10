<?php
/**
 * Minimal hand-rolled dependency container (PSR-11-like surface).
 *
 * @package ContentAbilities
 */

declare( strict_types=1 );

namespace ContentAbilities;

use Closure;
use RuntimeException;

/**
 * Tiny service container with factory autowiring for this plugin's services.
 */
final class Container {

	/** @var array<class-string, object> */
	private array $instances = array();

	/** @var array<class-string, Closure(self): object> */
	private array $factories = array();

	/**
	 * Registers an explicit factory.
	 *
	 * @template T of object
	 * @param class-string<T> $id
	 * @param Closure(self): T $factory
	 */
	public function bind( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Resolves a service, instantiating it (shared) on first use.
	 *
	 * @template T of object
	 * @param class-string<T> $id
	 * @return T
	 */
	public function make( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			/** @var T $instance */
			$instance = $this->instances[ $id ];
			return $instance;
		}

		if ( isset( $this->factories[ $id ] ) ) {
			/** @var T $instance */
			$instance = $this->factories[ $id ]( $this );
		} else {
			$instance = new $id( ...$this->resolveDependencies( $id ) );
		}

		$this->instances[ $id ] = $instance;
		return $instance;
	}

	/**
	 * Reflects constructor dependencies and resolves them recursively.
	 *
	 * @param class-string $id
	 * @return list<object>
	 */
	private function resolveDependencies( string $id ): array {
		$constructor = ( new \ReflectionClass( $id ) )->getConstructor();
		if ( null === $constructor ) {
			return array();
		}

		$args = array();
		foreach ( $constructor->getParameters() as $param ) {
			$type = $param->getType();
			if ( ! $type instanceof \ReflectionNamedType || $type->isBuiltin() ) {
				throw new RuntimeException( "Cannot resolve parameter \${$param->getName()} of {$id}." );
			}
			/** @var class-string<object> $class */
			$class  = $type->getName();
			$args[] = $this->make( $class );
		}

		return $args;
	}
}
