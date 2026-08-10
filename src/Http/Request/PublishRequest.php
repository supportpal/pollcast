<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class PublishRequest extends FormRequest
{
    /**
     * Client events carry this prefix, as they do on Pusher, so that nothing a client publishes
     * can be mistaken for an event the server broadcast.
     */
    public const string EVENT_PREFIX = 'client-';

    /** Namespace reserved for the events this package writes itself. */
    public const string RESERVED_PREFIX = 'pollcast:';

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
            'channel_name' => ['bail', 'required', 'string', 'max:255'],
            // Bail so the prefix rules, which are string comparisons, only see a string.
            'event'        => [
                'bail',
                'required',
                'string',
                'max:255',
                'starts_with:' . self::EVENT_PREFIX,
                'doesnt_start_with:' . self::RESERVED_PREFIX,
            ],
            'data'         => ['required', 'array', 'max:100'],
        ];
    }
}
