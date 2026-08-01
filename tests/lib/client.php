<?php
/**
 * Minimal HTTP client with a cookie jar.
 *
 * Uses streams rather than a browser driver on purpose: only two files in the
 * whole app contain any JavaScript, so the rendered DOM adds nothing, and
 * plain requests are faster and fully deterministic.
 */

class MbClient
{
    private $base;
    private $cookies = array();

    public $lastStatus = 0;
    public $lastLocation = '';

    public function __construct($base)
    {
        $this->base = rtrim($base, '/');
    }

    public function get($path)
    {
        return $this->request('GET', $path, null);
    }

    public function post($path, array $fields)
    {
        return $this->request('POST', $path, http_build_query($fields));
    }

    /**
     * @return string response body ('' on transport failure)
     */
    private function request($method, $path, $body)
    {
        $url = $this->absolute($path);

        $headers = array('Connection: close');
        if ($this->cookies) {
            $pairs = array();
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . strlen($body);
        }

        $context = stream_context_create(array(
            'http' => array(
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 30,
                // Redirects are not followed: the crawler asserts on the status
                // of each individual page, and following them would hide which
                // one actually produced a failure.
                'follow_location' => 0,
                // Needed so 4xx/5xx bodies are returned rather than discarded,
                // since PHP error output is what the smoke test inspects.
                'ignore_errors' => true,
            ),
        ));

        $result = @file_get_contents($url, false, $context);
        $this->captureResponseMeta(isset($http_response_header) ? $http_response_header : array());

        return $result === false ? '' : $result;
    }

    private function captureResponseMeta($rawHeaders)
    {
        $this->lastStatus = 0;
        $this->lastLocation = '';

        foreach ($rawHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                $this->lastStatus = (int) $m[1];
                continue;
            }
            if (stripos($header, 'Location:') === 0) {
                $this->lastLocation = trim(substr($header, 9));
                continue;
            }
            if (stripos($header, 'Set-Cookie:') === 0) {
                $cookie = trim(substr($header, 11));
                if (preg_match('/^([^=]+)=([^;]*)/', $cookie, $m)) {
                    $this->cookies[trim($m[1])] = $m[2];
                }
            }
        }
    }

    private function absolute($path)
    {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        return $this->base . '/' . ltrim($path, '/');
    }
}
