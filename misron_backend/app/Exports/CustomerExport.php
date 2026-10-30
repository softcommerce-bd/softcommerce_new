<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerExport implements FromCollection, WithMapping, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return User::where('user_type','customer')->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Address',
            'Country',
            'State',
            'City',
            'Postal Code',
            'Balance',
        ];
    }


    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->phone,
            $user->address,
            $user->country,
            $user->state,
            $user->city,
            $user->postal_code,
            $user->balance
        ];

    }

}
