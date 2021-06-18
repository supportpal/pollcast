<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
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
            'channel_name' => ['required'],
        ];
    }
}
