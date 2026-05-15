<?php

namespace Athwari\ZktecoAdms\Http\Middleware;

use Athwari\ZktecoAdms\Services\AttendanceParser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that validates ZKTeco device requests.
 *
 * - Validates the SN query parameter format (1-64 alphanumeric/hyphen/underscore)
 * - Enforces the configured max body size limit
 */
class ValidateDeviceRequest
{
    public function __construct(private readonly AttendanceParser $parser)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Validate SN parameter
        $sn = $request->query('SN', '');

        if ($sn === '' || $sn === null) {
            return response('Missing SN parameter', 400);
        }

        if (! $this->parser->validateSerialNumber((string) $sn)) {
            return response('Invalid SN parameter', 400);
        }

        // Enforce body size limit
        $maxBodySize = config('zkteco-adms.max_body_size', 10 * 1024 * 1024);
        $contentLength = $request->header('Content-Length');

        if ($contentLength !== null && (int) $contentLength > $maxBodySize) {
            return response('Request body too large', 413);
        }

        // Check actual body size for POST requests
        if ($request->isMethod('POST')) {
            $body = $request->getContent();
            if (strlen($body) > $maxBodySize) {
                return response('Request body too large', 413);
            }
        }

        return $next($request);
    }
}
