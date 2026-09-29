<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    false,
    true,
    true
);

$errorMiddleware->setDefaultErrorHandler(
    static function (
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) use ($app): ResponseInterface {
        $status = $exception instanceof HttpException
            ? $exception->getStatusCode()
            : 500;
        $response = $app->getResponseFactory()->createResponse($status);
        $message = $status >= 500
            ? 'Server error.'
            : $response->getReasonPhrase() . '.';

        $response->getBody()->write((string) json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
);

return $app;
