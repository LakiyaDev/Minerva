<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $domains = config('identity.staff.allowed_email_domains', []);

        $emailRules = ['required', 'string', 'email:rfc', 'max:255'];

        if ($domains !== []) {
            $emailRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($domains): void {
                $domain = strtolower((string) substr((string) strrchr((string) $value, '@'), 1));

                if (! in_array($domain, array_map('strtolower', $domains), true)) {
                    $fail('This email domain is not allowed for staff invitations.');
                }
            };
        }

        return [
            'email' => $emailRules,
            'role' => ['required', 'string', Rule::in(config('identity.staff.roles', []))],
        ];
    }
}
