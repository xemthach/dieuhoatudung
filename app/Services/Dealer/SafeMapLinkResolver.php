<?php

namespace App\Services\Dealer;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class SafeMapLinkResolver
{
    private float $deadline;

    public function __construct(private MapCoordinateParser $parser) {}

    public function resolve(string $url): ?array
    {
        $this->deadline = microtime(true) + 5;
        for ($hop = 0; $hop <= 3; $hop++) {
            $parts = $this->parser->validateUrl($url);
            if ($coordinates = $this->parser->parse($url)) {
                return $coordinates;
            }
            $host = strtolower($parts['host']);
            $ips = $this->addresses($host);
            if ($ips === []) {
                throw new RuntimeException('Không phân giải được host bản đồ.');
            }
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
                    || str_starts_with(strtolower($ip), '::ffff:')) {
                    throw new RuntimeException('Host bản đồ không được trỏ tới mạng riêng.');
                }
            }
            $remaining = $this->deadline - microtime(true);
            if ($remaining <= 0) {
                throw new RuntimeException('Hết thời gian phân giải bản đồ.');
            }
            $response = $this->head($url, $host, $ips[0], $remaining);
            if (! in_array($response['status'], [301, 302, 303, 307, 308], true)) {
                return null;
            }
            $location = $response['location'];
            if ($location === '' || strlen($location) > 4096) {
                return null;
            }
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
        }

        return null;
    }

    protected function addresses(string $host): array
    {
        // Bound DNS as well as HTTP. If process execution is unavailable, the
        // Admin receives the manual-coordinate fallback; never connect unpinned.
        $php = (new PhpExecutableFinder)->find(false);
        if (! $php) {
            throw new RuntimeException('PHP CLI is required for bounded DNS resolution.');
        }
        $script = '$r=dns_get_record($argv[1], DNS_A|DNS_AAAA)?:[];echo json_encode(array_values(array_filter(array_map(static fn($r)=>$r["ip"]??$r["ipv6"]??null,$r))));';
        $process = new Process([$php, '-r', $script, $host]);
        $remaining = $this->deadline - microtime(true);
        if ($remaining <= 0) {
            throw new RuntimeException('DNS timeout.');
        }
        $process->setTimeout(min(2, $remaining))->mustRun();
        if (strlen($process->getOutput()) > 65536) {
            throw new RuntimeException('DNS response too large.');
        }

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function head(string $url, string $host, string $ip, float $timeout): array
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('cURL is required for pinned map connections.');
        }
        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
        $response = (new Client)->request('HEAD', $url, [
            'allow_redirects' => false, 'http_errors' => false, 'timeout' => $timeout, 'connect_timeout' => min(2, $timeout),
            'verify' => true, 'proxy' => '',
            'on_headers' => static function (ResponseInterface $response) {
                $bytes = 0;
                foreach ($response->getHeaders() as $key => $values) {
                    $bytes += strlen($key) + strlen(implode(',', $values));
                }
                if ($bytes > 65536 || (int) $response->getHeaderLine('Content-Length') > 65536) {
                    throw new RuntimeException('Map response too large.');
                }
            },
            'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$address], CURLOPT_MAXFILESIZE => 65536],
        ]);

        return ['status' => $response->getStatusCode(), 'location' => $response->getHeaderLine('Location')];
    }
}
