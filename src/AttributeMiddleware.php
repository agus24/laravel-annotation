<?php

namespace LaravelAnnotation;

use BackedEnum;
use Illuminate\Routing\ControllerMiddlewareOptions;
use LaravelAnnotation\Attribute\ClassMiddleware;
use LaravelAnnotation\Attribute\Middleware;
use ReflectionAttribute;
use ReflectionClass;

trait AttributeMiddleware
{
    /**
     * Get the middleware assigned to the controller.
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        $middlewares = (new ReflectionClass($this))->getAttributes("middleware");
        return array_merge($middlewares, $this->getMiddlewaresByAttributes());
    }

    /**
     * Get the controller middlewares by attributes
     *
     * @see Middleware
     *
     * @return array
     */
    public function getMiddlewaresByAttributes(): array
    {
        $middlewares = [];

        /** @return string[] */
        $filterArguments = function (array $arguments): array {
            $items = [];

            foreach ($arguments as $argument) {
                if (is_string($argument) && $argument !== '') $items[] = $argument;
                if ($argument instanceof BackedEnum && $argument->value !== '') $items[] = (string) $argument->value;
            }

            return $items;
        };

        /** @var ReflectionAttribute[] $attributes */
        $push = function (array $attributes, ?string $method = null) use (&$middlewares, $filterArguments) {
            foreach ($attributes as $attribute) {
                /** @var Middleware $middleware */
                $middleware = $attribute->newInstance();
                $arguments = [];

                if (!is_array($middleware->arguments)) {
                    $middleware->arguments = [$middleware->arguments];
                }

                foreach ($middleware->arguments as $argument) {
                    $items = $filterArguments(is_array($argument) ? $argument : [$argument]);
                    if ($items) {
                        $arguments[] = implode('|', $items);
                    }
                }

                $name = $middleware->name;
                if ($arguments) $name .= ':'.implode(',', $arguments);

                $middlewares[] = [
                    'middleware' => $name,
                    'options' => &$middleware->options
                ];

                $middlewareOptions = new ControllerMiddlewareOptions($middleware->options);

                if ($method) $middlewareOptions->only((array) $method);
                elseif ($middleware->only) $middlewareOptions->only((array) $middleware->only);
                elseif ($middleware->except) $middlewareOptions->except((array) $middleware->except);
            }
        };

        $class = new ReflectionClass($this);

        // ClassMiddleware (Higher priority, before methods)
        $push($class->getAttributes(ClassMiddleware::class));

        // Methods
        foreach ($class->getMethods() as $method) {
            $push($method->getAttributes(Middleware::class), $method->name);
        }

        // Class
        $push($class->getAttributes(Middleware::class));

        return $middlewares;
    }
}
