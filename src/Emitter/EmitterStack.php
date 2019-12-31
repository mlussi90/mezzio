<?php

/**
 * @see       https://github.com/mezzio/mezzio for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Mezzio\Emitter;

use InvalidArgumentException;
use Laminas\Diactoros\Response\EmitterInterface;
use Mezzio\Exception;
use Psr\Http\Message\ResponseInterface;
use SplStack;

/**
 * Provides an EmitterInterface implementation that acts as a stack of Emitters.
 *
 * The implementations emit() method iterates itself.
 *
 * When iterating the stack, the first emitter to return a value that is not
 * identical to boolean false will short-circuit iteration.
 */
class EmitterStack extends SplStack implements EmitterInterface
{
    /**
     * Emit a response
     *
     * Loops through the stack, calling emit() on each; any that return
     * a value other than boolean false will short-circuit, skipping
     * any remaining emitters in the stack.
     *
     * As such, return a boolean false value from an emitter to indicate it
     * cannot emit the response.
     *
     * @return false|null
     */
    public function emit(ResponseInterface $response)
    {
        foreach ($this as $emitter) {
            if (false !== $emitter->emit($response)) {
                return null;
            }
        }

        return false;
    }

    /**
     * Set an emitter on the stack by index.
     *
     * @param mixed $index
     * @param EmitterInterface $emitter
     * @return void
     * @throws InvalidArgumentException if not an EmitterInterface instance
     */
    public function offsetSet($index, $emitter)
    {
        $this->validateEmitter($emitter);
        parent::offsetSet($index, $emitter);
    }

    /**
     * Push an emitter to the stack.
     *
     * @param EmitterInterface $emitter
     * @return void
     * @throws InvalidArgumentException if not an EmitterInterface instance
     */
    public function push($emitter)
    {
        $this->validateEmitter($emitter);
        parent::push($emitter);
    }

    /**
     * Unshift an emitter to the stack.
     *
     * @param EmitterInterface $emitter
     * @return void
     * @throws InvalidArgumentException if not an EmitterInterface instance
     */
    public function unshift($emitter)
    {
        $this->validateEmitter($emitter);
        parent::unshift($emitter);
    }

    /**
     * Validate that an emitter implements EmitterInterface.
     *
     * @param mixed $emitter
     * @throws InvalidArgumentException for non-emitter instances
     */
    private function validateEmitter($emitter) : void
    {
        if (! $emitter instanceof EmitterInterface) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an EmitterInterface implementation',
                __CLASS__
            ));
        }
    }
}
