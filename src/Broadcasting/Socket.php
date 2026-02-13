<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Broadcasting;

use DomainException;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use SupportPal\Pollcast\Exception\ExpiredSocketException;
use SupportPal\Pollcast\Exception\InvalidSocketException;
use SupportPal\Pollcast\Model\Channel;
use SupportPal\Pollcast\Model\Member;
use SupportPal\Pollcast\Model\Message;
use UnexpectedValueException;

use function is_string;
use function now;
use function sprintf;

class Socket
{
    use UsePusherChannelConventions;

    public const string HEADER = 'X-Socket-ID';

    /** Session key, only for backwards compatibility. */
    public const string UUID = 'pollcast:uuid';

    private ?string $id = null;

    public function __construct(
        private readonly Repository $config,
        private readonly Session $session,
        private Request $request
    ) {
        //
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function encode(): string
    {
        if ($this->id === null) {
            throw new InvalidSocketException('Socket ID is not set.');
        }

        $payload = [
            'id'  => $this->id,
            'iat' => now()->getTimestamp(), // Issued at
            'exp' => now()->addMinute()->getTimestamp(), // Expiry
        ];

        return JWT::encode($payload, $this->getKey(), $this->getAlgorithm());
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        if ($this->id === null) {
            throw new InvalidSocketException('Socket ID is not set.');
        }

        return $this->id;
    }

    public function getIdFromRequest(): string
    {
        $token = $this->request->header(self::HEADER);
        if (is_string($token)) {
            try {
                $decoded = JWT::decode($token, new Key($this->getKey(), $this->getAlgorithm()));
            } catch (ExpiredException) {
                throw new ExpiredSocketException(sprintf('%s header has expired.', self::HEADER));
            } catch (InvalidArgumentException | DomainException | UnexpectedValueException $e) {
                throw new InvalidSocketException(sprintf('%s header is invalid: %s', self::HEADER, $e->getMessage()));
            }

            return $decoded->id ?? throw new InvalidSocketException(sprintf('%s header is missing the id property.', self::HEADER));
        }

        throw new InvalidSocketException(sprintf('%s header is missing.', self::HEADER));
    }

    public function getIdFromSession(): ?string
    {
        return $this->session->get(self::UUID);
    }

    public function hasId(): bool
    {
        try {
            if ($this->getIdFromSession() !== null) {
                return true;
            }

            $this->getIdFromRequest();
        } catch (Exception) {
            return false;
        }

        return true;
    }

    public function createIdIfNotExists(): string
    {
        if (! $this->hasId()) {
            $id = $this->createId();
        } else {
            $id = $this->getIdFromSession() ?? $this->getIdFromRequest();
        }

        return $id;
    }

    public function createId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * @param mixed[]|null $data
     */
    public function joinChannel(string $name, ?array $data = null): void
    {
        /** @var Channel $channel */
        $channel = Channel::query()->firstOrCreate(['name' => $name]);
        $channel->touch();

        /** @var Member $member */
        $member = Member::query()->firstOrCreate([
            'channel_id' => $channel->id,
            'socket_id'  => $this->getId(),
        ], ['data' => $data]);

        if ($data === null) {
            return;
        }

        $this->joinedPresenceChannel($channel, $member, $data);
    }

    public function removeMemberFromChannel(Member $member, Channel $channel): void
    {
        $member->delete();

        if (! $this->isGuardedChannel($channel->name)) {
            return;
        }

        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_removed',
            'payload'    => $member->data ?? [],
        ]))->save();
    }

    /**
     * @param mixed[] $memberData
     */
    protected function joinedPresenceChannel(Channel $channel, Member $member, array $memberData): void
    {
        // Broadcast subscription succeeded event to the member.
        (new Message([
            'channel_id' => $channel->id,
            'member_id'  => $member->id,
            'event'      => 'pollcast:subscription_succeeded',
            'payload'    => Member::query()->where('channel_id', $channel->id)->pluck('data'),
        ]))->save();

        // Broadcast member added event to everyone in the channel.
        (new Message([
            'channel_id' => $channel->id,
            'event'      => 'pollcast:member_added',
            'payload'    => $memberData,
        ]))->save();
    }

    private function getKey(): string
    {
        return $this->config->get('app.key');
    }

    private function getAlgorithm(): string
    {
        return 'HS256';
    }
}
