<?php

namespace App\Services\Funnel\Checkout;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * The customer record an order belongs to. Follows the website's guest
 * checkout: an existing customer is found by phone number, then by email;
 * otherwise a new customer account is created from what was typed.
 *
 * One deliberate difference: the website sets a new guest's password to their
 * phone number, which anyone who knows the number could use to sign in. A funnel
 * customer gets an unguessable password instead and signs in the way any customer
 * with a forgotten password does.
 */
class CustomerResolver
{
    public function resolve(CheckoutInput $input, string $ip): User
    {
        $user = $this->find($input);

        if ($user === null) {
            try {
                $user = User::create([
                    'name' => $input->name,
                    'email' => $input->email,
                    'phone' => $input->phone,
                    'password' => bcrypt(Str::random(40)),
                    'last_ip' => $ip,
                ]);

                return $user;
            } catch (QueryException $e) {
                // Another order for the same number won the race: use that customer.
                $user = $this->find($input);
                if ($user === null) {
                    throw $e;
                }
            }
        }

        $user->update(['last_ip' => $ip]);

        return $user;
    }

    private function find(CheckoutInput $input): ?User
    {
        // Numbers are stored as typed, with or without the country code.
        $user = User::whereIn('phone', [$input->phone, '+88' . $input->phone, '88' . $input->phone])->first();

        if ($user === null && $input->email !== null) {
            $user = User::where('email', $input->email)->first();
        }

        return $user;
    }
}
