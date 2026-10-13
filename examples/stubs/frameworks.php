<?php

/**
 * Minimal declarations of the Laravel and Symfony symbols used by examples/laravel and examples/symfony,
 * so that PHPStan can analyse the examples without installing either framework. Read by PHPStan only
 * (scanFiles), never loaded at runtime. Signatures are narrowed to what the examples call.
 */

declare(strict_types=1);

namespace Illuminate\Contracts\Foundation {
    interface Application
    {
        public function singleton(string $abstract, ?\Closure $concrete = null): void;
    }
}

namespace Illuminate\Support {
    use Illuminate\Contracts\Foundation\Application;

    abstract class ServiceProvider
    {
        protected Application $app;
    }
}

namespace Illuminate\Http {
    class Request
    {
        public function getContent(): string
        {
            return '';
        }

        /** @return string|array<int, string>|null */
        public function header(?string $key = null, mixed $default = null): string|array|null
        {
            return null;
        }
    }

    class Response
    {
        public function __construct(mixed $content = '', int $status = 200) {}
    }
}

namespace Psr\Log {
    interface LoggerInterface
    {
        /** @param array<mixed> $context */
        public function info(string $message, array $context = []): void;
    }
}

namespace {
    function config(?string $key = null, mixed $default = null): mixed
    {
        return null;
    }

    function logger(): Psr\Log\LoggerInterface
    {
        throw new \LogicException('stub');
    }
}

namespace Symfony\Component\HttpFoundation {
    class HeaderBag
    {
        public function get(string $key, ?string $default = null): ?string
        {
            return null;
        }
    }

    class Request
    {
        public HeaderBag $headers;

        public function getContent(): string
        {
            return '';
        }
    }

    class Response
    {
        public const HTTP_NO_CONTENT = 204;
        public const HTTP_BAD_REQUEST = 400;

        public function __construct(?string $content = '', int $status = 200) {}
    }
}

namespace Symfony\Component\DependencyInjection\Attribute {
    #[\Attribute(\Attribute::TARGET_PARAMETER)]
    class Autowire
    {
        public function __construct(mixed $value = null, ?string $service = null, ?string $expression = null, ?string $env = null, ?string $param = null) {}
    }
}

namespace Symfony\Component\Routing\Attribute {
    #[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
    class Route
    {
        /** @param list<string>|string $methods */
        public function __construct(?string $path = null, ?string $name = null, array|string $methods = []) {}
    }
}
