<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Tests\Functional;

use Firebase\JWT\JWT;
use SupportPal\Pollcast\Broadcasting\Socket;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Tests\TestCase;

use function app;
use function array_filter;
use function array_merge;
use function now;
use function request;
use function route;
use function session;
use function sprintf;

class VerifySocketIdMiddlewareTest extends TestCase
{
    public function testValidXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->postAjax(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    public function testNoXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'data'    => null,
                'message' => 'Socket ID is not set.',
            ]);
    }

    public function testInvalidXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        $headers = array_filter(array_merge($this->getAjaxHeaders(), [sprintf('HTTP_%s', Socket::HEADER) => 'abc']));

        $response = $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ], $headers);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'data'   => null,
            ]);

        // Assert message starts with the expected prefix
        $this->assertStringStartsWith('X-Socket-ID header is invalid:', $response->json('message'));
    }

    public function testExpiredXSocketIdHeader(): void
    {
        $channel = $this->setupChannelAndMember();

        // Create an expired token (issued 2 minutes ago, expired 1 minute ago)
        $socket = new Socket(app('config'), app('session.store'), request());
        $expiredToken = $this->createExpiredToken();

        $headers = array_filter(array_merge($this->getAjaxHeaders(), [sprintf('HTTP_%s', Socket::HEADER) => $expiredToken]));

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ], $headers)
            ->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'data'    => ['code' => 'TOKEN_EXPIRED'],
                'message' => 'X-Socket-ID header has expired.',
            ]);
    }

    public function testMalformedJwtToken(): void
    {
        $channel = $this->setupChannelAndMember();

        $headers = array_filter(array_merge($this->getAjaxHeaders(), [sprintf('HTTP_%s', Socket::HEADER) => 'malformed.jwt.token']));

        $response = $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ], $headers);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'data'   => null,
            ]);

        // Assert message starts with the expected prefix
        $this->assertStringStartsWith('X-Socket-ID header is invalid:', $response->json('message'));
    }

    public function testJwtTokenWithoutIdProperty(): void
    {
        $channel = $this->setupChannelAndMember();

        // Create a valid JWT token but without the 'id' property
        $payload = [
            'iat' => now()->getTimestamp(),
            'exp' => now()->addMinute()->getTimestamp(),
            // 'id' is intentionally missing
        ];

        $config = app('config');
        $key = $config->get('app.key');
        $algorithm = $config->get('broadcasting.connections.pollcast.algorithm', 'HS256');
        $tokenWithoutId = JWT::encode($payload, $key, $algorithm);

        $headers = array_filter(array_merge($this->getAjaxHeaders(), [sprintf('HTTP_%s', Socket::HEADER) => $tokenWithoutId]));

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ], $headers)
            ->assertStatus(401)
            ->assertJson([
                'status'  => 'error',
                'data'    => null,
                'message' => 'X-Socket-ID header is missing the id property.',
            ]);
    }

    public function testFallbackToSession(): void
    {
        $channel = $this->setupChannelAndMember();

        session([Socket::UUID => self::SOCKET_ID]);

        $this->post(route('supportpal.pollcast.receive'), [
            'channels' => [$channel->name],
            'time'     => now()->toDateTimeString()
        ])
            ->assertStatus(200);
    }

    private function setupChannelAndMember(): Channel
    {
        $channel = Channel::factory()->create(['name' => 'public-channel']);
        Member::factory()->create(['channel_id' => $channel->id, 'socket_id' => self::SOCKET_ID]);

        return $channel;
    }

    private function createExpiredToken(): string
    {
        $payload = [
            'id'  => self::SOCKET_ID,
            'iat' => now()->subMinutes(2)->getTimestamp(), // Issued 2 minutes ago
            'exp' => now()->subMinute()->getTimestamp(),   // Expired 1 minute ago
        ];

        $config = app('config');
        $key = $config->get('app.key');
        $algorithm = $config->get('broadcasting.connections.pollcast.algorithm', 'HS256');

        return JWT::encode($payload, $key, $algorithm);
    }
}
