<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\event;

use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use pocketmine\timings\Timings;
use function count;

/**
 * This class is used to permit asynchronous event handling.
 *
 * When an event is called asynchronously, the event handlers are called by priority level.
 * When all the promises of a priority level have been resolved, the next priority level is called.
 */
abstract class AsyncEvent{
	/** @var array<class-string<AsyncEvent>, int> $delegatesCallDepth */
	private static array $delegatesCallDepth = [];
	private const MAX_EVENT_CALL_DEPTH = 50;

	/**
	 * @phpstan-return Promise<static>
	 */
	final public function call() : Promise{
		if(!isset(self::$delegatesCallDepth[$class = static::class])){
			self::$delegatesCallDepth[$class] = 0;
		}

		if(self::$delegatesCallDepth[$class] >= self::MAX_EVENT_CALL_DEPTH){
			//this exception will be caught by the parent event call if all else fails
			throw new \RuntimeException("Recursive event call detected (reached max depth of " . self::MAX_EVENT_CALL_DEPTH . " calls)");
		}

		$timings = Timings::getAsyncEventTimings($this);
		$timings->startTiming();

		++self::$delegatesCallDepth[$class];
		try{
			/** @phpstan-var PromiseResolver<static> $globalResolver */
			$globalResolver = new PromiseResolver();

			$handlers = AsyncHandlerListManager::global()->getHandlersFor(static::class);
			if(count($handlers) > 0){
				$this->processRemainingHandlers($handlers, fn() => $globalResolver->resolve($this), $globalResolver->reject(...));
			}else{
				$globalResolver->resolve($this);
			}

			return $globalResolver->getPromise();
		}finally{
			--self::$delegatesCallDepth[$class];
			$timings->stopTiming();
		}
	}

	/**
	 * @param AsyncRegisteredListener[] $handlers
	 * @phpstan-param list<AsyncRegisteredListener> $handlers
	 * @phpstan-param \Closure() : void $resolve
	 * @phpstan-param \Closure() : void $reject
	 */
	private function processRemainingHandlers(array $handlers, \Closure $resolve, \Closure $reject) : void{
		$currentPriority = null;
		$awaitPromises = [];
		foreach($handlers as $k => $handler){
			$priority = $handler->getPriority();
			if(count($awaitPromises) > 0 && $currentPriority !== null && $currentPriority !== $priority){
				//wait for concurrent promises from previous priority to complete
				break;
			}

			$currentPriority = $priority;
			if($handler->canBeCalledConcurrently()){
				unset($handlers[$k]);
				$promise = $handler->callAsync($this);
				if($promise !== null){
					$awaitPromises[] = $promise;
				}
			}else{
				if(count($awaitPromises) > 0){
					//wait for concurrent promises to complete
					break;
				}

				unset($handlers[$k]);
				$promise = $handler->callAsync($this);
				if($promise !== null){
					$promise->onCompletion(
						onSuccess: fn() => $this->processRemainingHandlers($handlers, $resolve, $reject),
						onFailure: $reject
					);
					return;
				}
			}
		}

		if(count($awaitPromises) > 0){
			Promise::all($awaitPromises)->onCompletion(
				onSuccess: fn() => $this->processRemainingHandlers($handlers, $resolve, $reject),
				onFailure: $reject
			);
		}else{
			$resolve();
		}
	}
}
