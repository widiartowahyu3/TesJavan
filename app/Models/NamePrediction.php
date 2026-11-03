<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NamePrediction extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'gender_probability',
        'gender_count',
        'country_id',
        'country_name',
        'country_region',
        'country_probability',
        'nationality_count',
        'countries',
    ];

    protected function casts(): array
    {
        return [
            'gender_probability' => 'float',
            'country_probability' => 'float',
            'gender_count' => 'integer',
            'nationality_count' => 'integer',
            'countries' => 'array',
        ];
    }
}
