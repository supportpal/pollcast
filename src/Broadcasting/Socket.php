<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Broadcasting;

use Illuminate\Contracts\Session\Session;

use function uniqid;

class Socket
{
    private const UUID = 'pollcast:uuid';

    /** @var Session */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public function create(): self
    {
        if ($this->id() === null) {
            $this->session->put(self::UUID, uniqid('pollcast-', true));
        }

        return $this;
    }

    public function id(): ?string
    {
        return $this->session->get(self::UUID);
    }
}
