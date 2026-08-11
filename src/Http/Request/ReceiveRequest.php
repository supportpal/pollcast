<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

use function is_array;
use function is_int;
use function is_string;

class ReceiveRequest extends FormRequest
{
    /** How many channels one poll may ask about. */
    public const int MAX_CHANNELS = 100;

    /** How many events one poll may ask for per channel. */
    public const int MAX_EVENTS = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'channels'     => ['required', 'array', 'max:' . self::MAX_CHANNELS],
            'channels.*'   => ['array', 'max:' . self::MAX_EVENTS],
            'channels.*.*' => ['string', 'max:255'],
            'time'         => ['required', 'date'],
        ];
    }

    /**
     * A channel may be given as a bare name instead of a name => events pair, which asks for
     * nothing but the messages addressed to the caller. Normalising it to the pair form here
     * lets the rules above describe one shape.
     */
    protected function prepareForValidation(): void
    {
        $channels = $this->input('channels');
        if (! is_array($channels)) {
            return;
        }

        $normalised = [];
        foreach ($channels as $name => $events) {
            if (is_int($name) && is_string($events)) {
                $normalised[$events] = [];
            } else {
                $normalised[$name] = $events;
            }
        }

        $this->merge(['channels' => $normalised]);
    }
}
