<?php
declare(strict_types = 1);
/**
 * OriginPHP Framework
 * Copyright 2018 - 2019 Jamiel Sharief.
 *
 * Licensed under The MIT License
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * @copyright   Copyright (c) Jamiel Sharief
 * @link        https://www.originphp.com
 * @license     https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Origin\Publisher;

use Origin\Publisher\Exception\PublisherException;
use Origin\Core\Exception\InvalidArgumentException;

class Publisher
{
    /**
     * @var \Origin\Publisher\Publisher
     */
    protected static $instance;

    /**
     * Holds the listeners
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * Gets the instance of the HookTrait
     *
     * @return \Origin\Publisher\Publisher
     */
    public static function instance() : Publisher
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Subscribes an object
     *
     * $this->Publisher->subscribe($this);
     * $this->Publisher->subscribe($this,['on'=>[
     *      'beforeSave','afterSave'
     *        ]
     *    ]);
     *
     * Example implementation
     *
     * class User
     * {
     *
     *   public __construct(){
     *      // Does not have to be in construct
     *      $this->Publisher = new Publisher();
     *      $this->Publisher->subscribe($this);
     *   }
     *
     *   // Gets the local event manager instance
     *   public function Publisher(){
     *      if(!isset($this->Publisher)){
     *         $this->Publisher = new Publisher();
     *       }
     *      return $this->Publisher;
     *    }
     * }
     *
     *
     * @param object|string $object either object or class name
     * @param array $events
     * @return void
     */
    /**
     * Subscribes an object
     *
     * @param object|string $object
     * @param array $options You can pass the following option keys
     *   - on: an array of methods that this object that will listen to, by default it will listen to all
     *   - queue: default:false set to true to queue in the background
     * @return bool
     */
    public function subscribe($object, array $options = []) : bool
    {
        $options += ['on' => null,'queue' => null];
        if ($options['queue'] and is_object($object)) {
            throw new InvalidArgumentException('Subscribe using queue requires a class name');
        }
        $this->listeners[] = [
            'object' => $object,
            'options' => $options
        ];

        return true;
    }

    /**
     * Broadcasts a event to all subscribers with any number of arguments supplied (if any)
     *
     * $events = new Publisher();
     * $events->publish('beforeSave',$entity, $saveOptions);
     * $events->publish('startup');
     *
     * @param string $event
     * @return void
     */
    public function publish(string $event) : void
    {
        $args = func_get_args();
        array_shift($args);

        $globalListeners = static::instance()->listeners();
        $listeners = array_merge($globalListeners, $this->listeners);

        foreach ($listeners as $listener) {
            $options = $listener['options'];
            $object = $listener['object'];
            if ($options['on']) {
                if (! in_array($event, (array)$options['on'])) {
                    continue;
                }
            }

            # Queue Listener
            if ($options['queue']) {
                $result = (new ListenerJob())->dispatch($object, $event, $args);
                if (! $result) {
                    throw new PublisherException('Error dispatching ListenerJob');
                }
            }

            # Create object if needed
            if (is_string($object)) {
                $object = new $object();
            }
           
            if ($this->dispatch($object, $event, $args) === false) {
                break;
            }
        }
    }

    /**
     * Dispatches the event to the listener
     * @internal return type can be anything only false is important to Publisher. Callbacks should
     * only work on Listener instances
     *
     * @param object|callable $object
     * @param string $event
     * @param array $args
     * @return bool
     */
    public function dispatch($object, string $event, array $args = []) : bool
    {
        /**
         * Work with listenter
         */
        if ($object instanceof Listener) {
            return $object->dispatch($event, $args);
        }
        /**
         * Work with any object
         */
        if (method_exists($object, $event)) {
            if (call_user_func_array([$object,$event], $args) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the configured listeners (subscribers)
     *
     * @return array
     */
    public function listeners() : array
    {
        return $this->listeners;
    }

    /**
     * Clear the listeners
     *
     * @return void
     */
    public function clear() : void
    {
        $this->listeners = [];
    }
}
