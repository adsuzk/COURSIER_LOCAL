<?php
// Lightweight HTTP helper to perform GET/POST with short timeouts and graceful failures.
// Use this instead of raw file_get_contents($url) for remote/internal HTTP calls that may block.

function http_request_safe(string $url, array $opts = []): array {
    // opts: method (GET|POST), headers (array), body (string), timeout (seconds), connect_timeout (seconds), json_decode (bool)
    $method = strtoupper($opts['method'] ?? 'GET');
    $headers = $opts['headers'] ?? [];
    $body = $opts['body'] ?? null;
    $timeout = isset($opts['timeout']) ? (int)$opts['timeout'] : 5; // total timeout default 5s
    $connect_timeout = isset($opts['connect_timeout']) ? (int)$opts['connect_timeout'] : 2; // connect timeout 2s
    $return = ['ok' => false, 'status' => null, 'body' => null, 'error' => null];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = $k . ': ' . $v;
        }
        if (!empty($hdrs)) curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        // Safe defaults: do not verify peer for local dev, but allow opt-in via opts
        $verify = $opts['verify_peer'] ?? null;
        if ($verify === null) {
            // if URL is localhost or 127.0.0.1, skip peer verification to avoid cert issues
            $host = parse_url($url, PHP_URL_HOST);
            if ($host === '127.0.0.1' || $host === 'localhost') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            }
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$verify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        }

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $return['error'] = "curl($errno): $err";
            return $return;
        }
        $return['ok'] = ($http_code >= 200 && $http_code < 300);
        $return['status'] = $http_code;
        $return['body'] = $resp;
        return $return;
    }

    // Fallback to stream context with timeouts
    $context_opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", array_map(function($k,$v){ return "$k: $v"; }, array_keys($headers), $headers)),
            'timeout' => $timeout,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    if ($body !== null) {
        $context_opts['http']['content'] = $body;
    }
    $ctx = stream_context_create($context_opts);
    set_error_handler(function(){});
    $resp = @file_get_contents($url, false, $ctx);
    restore_error_handler();
    if ($resp === false) {
        $return['error'] = 'file_get_contents failed';
        return $return;
    }
    // attempt to get HTTP response code
    $status = null;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
    }
    $return['ok'] = ($status === null) ? true : ($status >= 200 && $status < 300);
    $return['status'] = $status;
    $return['body'] = $resp;
    return $return;
}

function http_get_safe(string $url, array $opts = []) {
    $r = http_request_safe($url, $opts + ['method' => 'GET']);
    return $r;
}

function http_post_safe(string $url, $body = null, array $opts = []) {
    $opts['method'] = 'POST';
    $opts['body'] = $body;
    return http_request_safe($url, $opts);
}

// Convenience wrapper that returns body on success or false on failure
function http_get_body_safe(string $url, array $opts = []) {
    $r = http_get_safe($url, $opts);
    if ($r['ok']) return $r['body'];
    return false;
}

?>