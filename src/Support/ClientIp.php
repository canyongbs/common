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


namespace CanyonGBS\Common\Support;

use CanyonGBS\Common\Exceptions\CloudflareIpRangesUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class ClientIp
{
    public const CACHE_KEY = 'cloudflare-ip-ranges';

    public const CLOUDFLARE_IPS_V4_URL = 'https://www.cloudflare.com/ips-v4';

    public const CLOUDFLARE_IPS_V6_URL = 'https://www.cloudflare.com/ips-v6';

    /**
     * Cloudflare's published edge IP ranges, used as a static fallback when the
     * live lists cannot be retrieved from Cloudflare.
     *
     * @see https://www.cloudflare.com/ips/
     *
     * @var array<int, string>
     */
    public const CLOUDFLARE_IP_RANGES = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * Resolve the real end-user IP address for the given request.
     *
     * Our requests flow Client -> Cloudflare -> AWS ALB -> application. When the
     * request is delivered through Cloudflare, the original visitor IP is taken from
     * the `CF-Connecting-IP` header. We only trust that header when the request was
     * genuinely delivered by Cloudflare (verified below), otherwise we fall back to
     * Laravel's standard resolved IP (the ALB's X-Forwarded-For chain).
     */
    public static function resolve(Request $request): ?string
    {
        if (self::isFromCloudflare($request)) {
            $cfConnectingIp = $request->headers->get('CF-Connecting-IP');

            if (is_string($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP) !== false) {
                return $cfConnectingIp;
            }
        }

        return $request->ip();
    }

    /**
     * Resolve Cloudflare's edge IP ranges, preferring the live published lists.
     *
     * The IPv4 and IPv6 lists are fetched from Cloudflare's published endpoints and
     * cached. When the cache is empty and the lists cannot be retrieved after a few
     * retries, a custom exception is reported and the bundled static list is used as
     * a fallback (briefly cached to avoid hammering Cloudflare during an outage).
     *
     * @return array<int, string>
     */
    public static function cloudflareIpRanges(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            /** @var array<int, string> $cached */
            return $cached;
        }

        try {
            $ranges = [
                ...self::fetchRanges(self::CLOUDFLARE_IPS_V4_URL),
                ...self::fetchRanges(self::CLOUDFLARE_IPS_V6_URL),
            ];

            Cache::put(self::CACHE_KEY, $ranges, now()->addWeek());

            return $ranges;
        } catch (Throwable $exception) {
            report($exception instanceof CloudflareIpRangesUnavailableException
                ? $exception
                : new CloudflareIpRangesUnavailableException(
                    'Unable to retrieve Cloudflare IP ranges; falling back to the bundled static list.',
                    previous: $exception,
                ));

            Cache::put(self::CACHE_KEY, self::CLOUDFLARE_IP_RANGES, now()->addHour());

            return self::CLOUDFLARE_IP_RANGES;
        }
    }

    /**
     * Fetch and parse a Cloudflare IP range list (one CIDR per line) from the given URL.
     *
     * Each endpoint must return a non-empty list. If either the IPv4 or IPv6 list comes
     * back empty we throw so the caller falls back to the complete static list, rather
     * than caching a partial set of ranges that is missing an entire address family.
     *
     * @return array<int, string>
     */
    private static function fetchRanges(string $url): array
    {
        $response = Http::retry(3, 200)->get($url)->throw();

        $lines = preg_split('/\r\n|\r|\n/', $response->body()) ?: [];

        $ranges = array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== '' && str_contains($line, '/'),
        ));

        if ($ranges === []) {
            throw new CloudflareIpRangesUnavailableException("Cloudflare returned an empty IP range list from [{$url}].");
        }

        return $ranges;
    }

    /**
     * Determine whether the request was delivered to our load balancer by Cloudflare.
     *
     * The ALB appends the real TCP peer (the Cloudflare edge that connected to it) as
     * the last entry of the X-Forwarded-For header. That entry is set by the ALB based
     * on the actual connection and cannot be spoofed by the client, so we verify it
     * falls within Cloudflare's published IP ranges. A client bypassing Cloudflare and
     * hitting the ALB directly will have their own (non-Cloudflare) IP appended here,
     * causing this check to fail and the resolver to fall back to the standard IP.
     */
    private static function isFromCloudflare(Request $request): bool
    {
        $forwardedFor = $request->headers->get('X-Forwarded-For');

        if (! is_string($forwardedFor) || $forwardedFor === '') {
            return false;
        }

        // X-Forwarded-For is an ordered, comma-separated list of hops where each proxy
        // appends the IP it received the request from. The last entry is therefore the
        // IP our ALB observed on the actual TCP connection (the Cloudflare edge when the
        // request came through Cloudflare). Earlier entries are client-supplied and can
        // be spoofed, so we only ever inspect this final, ALB-set hop.
        $hops = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));

        $peerIp = end($hops);

        if ($peerIp === false || filter_var($peerIp, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return IpUtils::checkIp($peerIp, self::cloudflareIpRanges());
    }
}
