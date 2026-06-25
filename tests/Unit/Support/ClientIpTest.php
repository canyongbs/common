<?php

/*
<COPYRIGHT>

    Copyright © 2016-2026, Canyon GBS LLC. All rights reserved.

    Canyon GBS Common is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/common/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Canyon GBS Common are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

use CanyonGBS\Common\Exceptions\CloudflareIpRangesUnavailableException;
use CanyonGBS\Common\Support\ClientIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

/**
 * @param array<string, mixed> $server
 * @param array<string, string> $headers
 */
function makeRequest(array $server = [], array $headers = []): Request
{
    $request = Request::create('https://api.example.com/widgets/submission', 'POST', [], [], [], $server);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

beforeEach(function () {
    // Never allow a real outbound request to Cloudflare during tests.
    Http::preventStrayRequests();
});

describe('resolve()', function () {
    beforeEach(function () {
        // Fake the published lists so the resolver can verify requests against the
        // Cloudflare ranges without making a real external call.
        Http::fake([
            ClientIp::CLOUDFLARE_IPS_V4_URL => Http::response("173.245.48.0/20\n", 200),
            ClientIp::CLOUDFLARE_IPS_V6_URL => Http::response("2400:cb00::/32\n", 200),
        ]);
    });

    it('returns the CF-Connecting-IP when the request was delivered by Cloudflare', function () {
        // The ALB appends the Cloudflare edge IP (173.245.48.10) as the last X-Forwarded-For hop.
        $request = makeRequest(
            server: ['REMOTE_ADDR' => '10.0.0.5'],
            headers: [
                'X-Forwarded-For' => '203.0.113.10, 173.245.48.10',
                'CF-Connecting-IP' => '203.0.113.10',
            ],
        );

        expect(ClientIp::resolve($request))->toBe('203.0.113.10');
    });

    it('falls back to the request IP when the last forwarded hop is not a Cloudflare IP', function () {
        // A client hitting the ALB directly: their real IP (198.51.100.9) is appended last,
        // so the spoofed CF-Connecting-IP must be ignored.
        $request = makeRequest(
            server: ['REMOTE_ADDR' => '198.51.100.9'],
            headers: [
                'X-Forwarded-For' => '203.0.113.10, 198.51.100.9',
                'CF-Connecting-IP' => '203.0.113.10',
            ],
        );

        expect(ClientIp::resolve($request))->toBe('198.51.100.9');
    });

    it('falls back to the request IP when there is no CF-Connecting-IP header', function () {
        $request = makeRequest(
            server: ['REMOTE_ADDR' => '10.0.0.5'],
            headers: [
                'X-Forwarded-For' => '203.0.113.10, 173.245.48.10',
            ],
        );

        expect(ClientIp::resolve($request))->toBe($request->ip());
    });

    it('falls back to the request IP when there is no X-Forwarded-For header', function () {
        $request = makeRequest(
            server: ['REMOTE_ADDR' => '198.51.100.9'],
            headers: [
                'CF-Connecting-IP' => '203.0.113.10',
            ],
        );

        expect(ClientIp::resolve($request))->toBe('198.51.100.9');
    });

    it('ignores an invalid CF-Connecting-IP value and falls back', function () {
        $request = makeRequest(
            server: ['REMOTE_ADDR' => '198.51.100.9'],
            headers: [
                'X-Forwarded-For' => '203.0.113.10, 173.245.48.10',
                'CF-Connecting-IP' => 'not-an-ip',
            ],
        );

        expect(ClientIp::resolve($request))->toBe($request->ip());
    });
});

it('fetches the Cloudflare IP ranges from the published endpoints and caches them', function () {
    Cache::forget(ClientIp::CACHE_KEY);

    Http::fake([
        ClientIp::CLOUDFLARE_IPS_V4_URL => Http::response("198.51.100.0/24\n203.0.113.0/24\n", 200),
        ClientIp::CLOUDFLARE_IPS_V6_URL => Http::response("2001:db8::/32\n", 200),
    ]);

    expect(ClientIp::cloudflareIpRanges())->toBe([
        '198.51.100.0/24',
        '203.0.113.0/24',
        '2001:db8::/32',
    ]);

    expect(Cache::get(ClientIp::CACHE_KEY))->toBe([
        '198.51.100.0/24',
        '203.0.113.0/24',
        '2001:db8::/32',
    ]);

    // A second call is served from the cache without making further HTTP requests.
    ClientIp::cloudflareIpRanges();

    Http::assertSentCount(2);
});

it('falls back to the static list and reports an exception when the endpoints cannot be fetched', function () {
    Exceptions::fake();

    Cache::forget(ClientIp::CACHE_KEY);

    Http::fake([
        ClientIp::CLOUDFLARE_IPS_V4_URL => Http::response('', 500),
        ClientIp::CLOUDFLARE_IPS_V6_URL => Http::response('', 500),
    ]);

    expect(ClientIp::cloudflareIpRanges())->toBe(ClientIp::CLOUDFLARE_IP_RANGES);

    Exceptions::assertReported(CloudflareIpRangesUnavailableException::class);
});

it('falls back to the static list and reports an exception when the endpoints return an empty list', function () {
    Exceptions::fake();

    Cache::forget(ClientIp::CACHE_KEY);

    Http::fake([
        ClientIp::CLOUDFLARE_IPS_V4_URL => Http::response('', 200),
        ClientIp::CLOUDFLARE_IPS_V6_URL => Http::response('', 200),
    ]);

    expect(ClientIp::cloudflareIpRanges())->toBe(ClientIp::CLOUDFLARE_IP_RANGES);

    Exceptions::assertReported(CloudflareIpRangesUnavailableException::class);
});

it('falls back to the static list and reports an exception when only one endpoint returns an empty list', function () {
    Exceptions::fake();

    Cache::forget(ClientIp::CACHE_KEY);

    // The IPv4 list is valid but the IPv6 list comes back empty. We must NOT cache a
    // partial list that is missing all IPv6 ranges; instead fall back to the full static list.
    Http::fake([
        ClientIp::CLOUDFLARE_IPS_V4_URL => Http::response("198.51.100.0/24\n", 200),
        ClientIp::CLOUDFLARE_IPS_V6_URL => Http::response('', 200),
    ]);

    expect(ClientIp::cloudflareIpRanges())->toBe(ClientIp::CLOUDFLARE_IP_RANGES);

    expect(Cache::get(ClientIp::CACHE_KEY))->toBe(ClientIp::CLOUDFLARE_IP_RANGES);

    Exceptions::assertReported(CloudflareIpRangesUnavailableException::class);
});
