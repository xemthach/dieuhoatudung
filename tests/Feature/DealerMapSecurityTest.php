<?php

namespace Tests\Feature;

use App\Services\Dealer\MapCoordinateParser;
use App\Services\Dealer\SafeMapLinkResolver;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DealerMapSecurityTest extends TestCase
{
    private function resolver(array $addresses, array $responses): SafeMapLinkResolver
    {
        return new class(app(MapCoordinateParser::class), $addresses, $responses) extends SafeMapLinkResolver
        {
            public int $requests = 0;

            public function __construct($parser, private array $ips, private array $responses)
            {
                parent::__construct($parser);
            }

            protected function addresses(string $host): array
            {
                return $this->ips;
            }

            protected function head(string $url, string $host, string $ip, float $timeout): array
            {
                $this->requests++;
                if ($timeout > 5 || $timeout <= 0) {
                    throw new \LogicException('Unbounded timeout');
                }

                return array_shift($this->responses) ?? ['status' => 200, 'location' => ''];
            }
        };
    }

    public static function privateAddresses(): array
    {
        return array_map(fn ($ip) => [$ip], ['127.0.0.1', '10.0.0.1', '172.16.1.2', '192.168.1.1', '169.254.169.254',
            '100.64.0.1', '198.18.0.1', '192.0.2.1', '0.0.0.0', '::1', 'fd00::1', 'fe80::1', '::ffff:127.0.0.1']);
    }

    #[DataProvider('privateAddresses')]
    public function test_private_or_metadata_dns_is_blocked_before_any_http(string $ip): void
    {
        $resolver = $this->resolver([$ip], []);
        try {
            $resolver->resolve('https://maps.app.goo.gl/example');
            $this->fail('Private request allowed');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, $resolver->requests);
        }
    }

    public function test_all_dns_answers_are_checked_and_redirects_cannot_escape_allowlist(): void
    {
        $resolver = $this->resolver(['8.8.8.8', '192.168.1.1'], []);
        try {
            $resolver->resolve('https://maps.app.goo.gl/example');
            $this->fail('Mixed DNS allowed');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, $resolver->requests);
        }
        $resolver = $this->resolver(['8.8.8.8'], [['status' => 302, 'location' => 'https://169.254.169.254/latest/meta-data']]);
        try {
            $resolver->resolve('https://maps.app.goo.gl/example');
            $this->fail('Redirect escaped allowlist');
        } catch (ValidationException $e) {
            $this->assertSame(1, $resolver->requests);
        }
    }

    public function test_redirects_resolve_to_preview_without_unbounded_fetching(): void
    {
        $resolver = $this->resolver(['8.8.8.8'], [['status' => 302, 'location' => 'https://www.google.com/maps/@10.25,106.5,15z']]);
        $this->assertSame(['latitude' => 10.25, 'longitude' => 106.5], $resolver->resolve('https://maps.app.goo.gl/example'));
        $this->assertSame(1, $resolver->requests);
        $loop = $this->resolver(['8.8.8.8'], array_fill(0, 5, ['status' => 302, 'location' => '/same']));
        $this->assertNull($loop->resolve('https://maps.app.goo.gl/example'));
        $this->assertLessThanOrEqual(4, $loop->requests);
        $noCoordinates = $this->resolver(['8.8.8.8'], [['status' => 200, 'location' => '']]);
        $this->assertNull($noCoordinates->resolve('https://maps.app.goo.gl/example'));
    }
}
