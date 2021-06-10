<?php declare(strict_types=1);

namespace SupportPal\Pollcast\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveRequest extends FormRequest
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
            'channels'  => ['required', 'array'],
            'time'      => ['required'],
        ];
    }
}
