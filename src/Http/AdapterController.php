<?php

namespace Nine\Laravel\Server\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Nine\Adapter\AdapterHandler;

/**
 * POST /nine/authenticate, /nine/user, /nine/event. Signature checks, routing and
 * response signing are done by Nine\Adapter\AdapterHandler (9in/php-sdk).
 */
class AdapterController extends Controller
{
    public function __invoke(Request $request, AdapterHandler $handler)
    {
        // The path exactly as 9in called it (base path included, query string excluded).
        $path = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);

        $result = $handler->handle($request->getMethod(), $path, $request->headers->all(), $request->getContent());

        return new Response($result->body, $result->status, $result->headers);
    }
}
