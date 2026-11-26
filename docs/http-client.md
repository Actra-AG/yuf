# HTTP client

`CurlGetRequest`, `CurlPostRequest`, … describe a request, `CurlClient::send()` sends it (`execute()` with a client of
its own). Safe by default: only http and https, the certificate and the host name are verified, TLS 1.2 or newer,
redirects are never followed, credentials only over HTTPS (or to this machine), timeouts and a size limit. Failures
and status codes of 300 or more are reported in the response (`hasErrors()`), not thrown.

```php
$request = CurlPostRequest::createWithJsonBody(requestTargetUrl: 'https://api.example.com/v1/orders', jsonString: $json);
$request->useTokenAuthentication(token: $token);
$response = $client->send(request: $request);
```

A client keeps its connection for several requests to the same server; options never carry over to the next request.

## Requests to URLs of users (SSRF)

Webhooks, imports and link previews send requests to URLs that users entered. They must not reach internal hosts:

1. Resolve the host once (A and AAAA) and check every address against private and reserved ranges (e.g.
   `filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)`, plus the ranges
   the flags do not cover, such as `100.64.0.0/10`). No address left: do not send.
2. Pin the request to the checked addresses: `$request->pinToIpAddresses(ipAddresses: $addresses)`. curl connects only
   to them for the host and port of the URL (`CURLOPT_RESOLVE`) and still verifies the certificate against the host
   name. Without the pin, curl resolves the host again, and a DNS name with a short TTL can answer an internal address
   the second time (DNS rebinding).
3. Show users a generic error, not the message of cURL: it tells whether an internal port is open.

A pinned request is sent with a cURL handle of its own: it neither reuses a connection or DNS entry of the client nor
leaves one behind. Redirects are never followed, so the pin cannot be left. A URL with an IP address as host can only
be pinned to that address.
