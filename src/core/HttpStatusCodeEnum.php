<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

enum HttpStatusCodeEnum: int
{
    case HTTP_UNKNOWN = 0;
    // 1xx - Information
    case HTTP_CONTINUE = 100;
    case HTTP_SWITCHING_PROTOCOLS = 101;
    case HTTP_PROCESSING = 102;
    case HTTP_CONNECTION_TIME_OUT = 118;

    // 2xx - Successful operation
    case HTTP_OK = 200;
    case HTTP_CREATED = 201;
    case HTTP_ACCEPTED = 202;
    case HTTP_NON_AUTHORATIVE_INFORMATION = 203;
    case HTTP_NO_CONTENT = 204;
    case HTTP_RESET_CONTENT = 205;
    case HTTP_PARTIAL_CONTENT = 206;
    case HTTP_MULTI_STATUS = 207;

    // 3xx - Redirection
    case HTTP_MULTIPLE_CHOICES = 300;
    case HTTP_MOVED_PERMANENTLY = 301;
    case HTTP_FOUND = 302;
    case HTTP_SEE_OTHER = 303;
    case HTTP_NOT_MODIFIED = 304;
    case HTTP_USE_PROXY = 305;
    case HTTP_SWITCH_PROXY = 306;
    case HTTP_TEMPORARY_REDIRECT = 307;

    // 4xx - Client error
    case HTTP_BAD_REQUEST = 400;
    case HTTP_UNAUTHORIZED = 401;
    case HTTP_PAYMENT_REQUIRED = 402;
    case HTTP_FORBIDDEN = 403;
    case HTTP_NOT_FOUND = 404;
    case HTTP_METHOD_NOT_ALLOWED = 405;
    case HTTP_NOT_ACCEPTABLE = 406;
    case HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    case HTTP_REQUEST_TIME_OUT = 408;
    case HTTP_CONFLICT = 409;
    case HTTP_GONE = 410;
    case HTTP_LENGTH_REQUIRED = 411;
    case HTTP_PRECONDITION_FAILED = 412;
    case HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    case HTTP_REQUEST_URL_TOO_LONG = 414;
    case HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    case HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    case HTTP_EXPECTATION_FAILED = 417;
    case HTTP_TOO_MANY_CONNECTIONS = 421;
    case HTTP_UNPROCESSABLE_ENTITY = 422;
    case HTTP_LOCKED = 423;
    case HTTP_FAILE_DEPENDENCY = 424;
    case HTTP_UNORDERED_COLLECTION = 425;
    case HTTP_UPGRADE_REQUIRED = 426;
    case HTTP_UNAVAILABLE_FOR_LEGAL_REASON = 451;

    // 5xx - Server error
    case HTTP_INTERNAL_SERVER_ERROR = 500;
    case HTTP_NOT_IMPLEMENTED = 501;
    case HTTP_BAD_GATEWAY = 502;
    case HTTP_SERVICE_UNAVAILABLE = 503;
    case HTTP_GATEWAY_TIME_OUT = 504;
    case HTTP_VERSION_NOT_SUPPORTED = 505;
    case HTTP_VARIANT_ALSO_NEGOTIATES = 506;
    case HTTP_INSUFFICIENT_STORAGE = 507;
    case HTTP_BANDWITH_LIMIT_EXCEEDED = 509;
    case HTTP_NOT_EXTENDED = 510;

    public function getStatusHeader(): string
    {
        $statusHeader = 'HTTP/1.1 ' . $this->value;
        $statusCodeDescription = $this->getStatusCodeDescription();
        if ($statusCodeDescription !== null) {
            $statusHeader .= ' ' . $statusCodeDescription;
        }

        return $statusHeader;
    }

    public function getStatusCodeDescription(): ?string
    {
        return match ($this) {
            HttpStatusCodeEnum::HTTP_OK => 'OK',
            HttpStatusCodeEnum::HTTP_NOT_MODIFIED => 'Not Modified',
            HttpStatusCodeEnum::HTTP_BAD_REQUEST => 'Bad Request',
            HttpStatusCodeEnum::HTTP_UNAUTHORIZED => 'Unauthorized',
            HttpStatusCodeEnum::HTTP_PAYMENT_REQUIRED => 'Payment Required',
            HttpStatusCodeEnum::HTTP_FORBIDDEN => 'Forbidden',
            HttpStatusCodeEnum::HTTP_NOT_FOUND => 'Not found',
            HttpStatusCodeEnum::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
            HttpStatusCodeEnum::HTTP_NOT_ACCEPTABLE => 'Not Acceptable',
            HttpStatusCodeEnum::HTTP_PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
            HttpStatusCodeEnum::HTTP_REQUEST_TIME_OUT => 'Request Time-out',
            HttpStatusCodeEnum::HTTP_CONFLICT => 'Conflict',
            HttpStatusCodeEnum::HTTP_GONE => 'Gone',
            HttpStatusCodeEnum::HTTP_LENGTH_REQUIRED => 'Length Required',
            HttpStatusCodeEnum::HTTP_PRECONDITION_FAILED => 'Precondition Failed',
            HttpStatusCodeEnum::HTTP_REQUEST_ENTITY_TOO_LARGE => 'Request Entity Too Large',
            HttpStatusCodeEnum::HTTP_REQUEST_URL_TOO_LONG => 'Request-URI Too Long',
            HttpStatusCodeEnum::HTTP_UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
            HttpStatusCodeEnum::HTTP_NOT_IMPLEMENTED => 'Not Implemented',
            HttpStatusCodeEnum::HTTP_BAD_GATEWAY => 'Bad Gateway',
            HttpStatusCodeEnum::HTTP_SERVICE_UNAVAILABLE => 'Service Unavailable',
            HttpStatusCodeEnum::HTTP_GATEWAY_TIME_OUT => 'Gateway Time-out',
            HttpStatusCodeEnum::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version not supported',
            default => null,
        };
    }
}
