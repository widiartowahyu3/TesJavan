<?php

namespace App\Services;

use App\Exceptions\ApiLimitException;
use App\Models\NamePrediction;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NameLookupService
{
    /**
     * Gabungkan hasil genderize.io, nationalize.io, dan first.org untuk satu nama.
     *
     * Versi gratis genderize/nationalize hanya 10 request/hari per IP, jadi:
     * - hasil sukses di-cache per nama selama 1 hari,
     * - jika kena limit, pakai data yang sudah tersimpan di database (bila ada).
     */
    public function lookup(string $name): array
    {
        $key = 'lookup:'.mb_strtolower($name);

        if ($cached = Cache::get($key)) {
            return $cached + ['source' => 'cache'];
        }

        try {
            $result = $this->fetchFromApis($name);
        } catch (ApiLimitException $e) {
            $saved = NamePrediction::where('name', $name)->latest()->first();
            if ($saved) {
                return $this->fromSaved($saved) + ['source' => 'database'];
            }
            throw $e;
        }

        Cache::put($key, $result, now()->addDay());

        return $result + ['source' => 'api'];
    }

    private function fetchFromApis(string $name): array
    {
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('gender')->timeout(10)->get('https://api.genderize.io/', $this->query($name, 'genderize')),
            $pool->as('nation')->timeout(10)->get('https://api.nationalize.io/', $this->query($name, 'nationalize')),
        ]);

        foreach (['gender' => 'genderize.io', 'nation' => 'nationalize.io'] as $key => $label) {
            $response = $responses[$key];

            if (! $response instanceof Response) {
                throw new RuntimeException("Tidak bisa terhubung ke {$label}.");
            }
            if ($response->status() === 429) {
                $hours = (int) ceil(((int) $response->header('x-rate-limit-reset')) / 3600);
                throw new ApiLimitException("Batas request harian {$label} sudah habis (10/hari tanpa API key). Coba lagi sekitar {$hours} jam lagi, atau isi API key di .env.");
            }
            if ($response->failed()) {
                throw new RuntimeException("Gagal mengambil data dari {$label} (HTTP {$response->status()}).");
            }
        }

        $gender = $responses['gender']->json();
        $nation = $responses['nation']->json();
        $countryMap = $this->countries();

        $countries = collect($nation['country'] ?? [])
            ->map(fn ($c) => [
                'country_id' => $c['country_id'],
                'country_name' => $countryMap[$c['country_id']]['country'] ?? $c['country_id'],
                'region' => $countryMap[$c['country_id']]['region'] ?? null,
                'probability' => $c['probability'],
            ])
            ->values()
            ->all();

        $top = $countries[0] ?? null;

        return [
            'name' => $gender['name'] ?? $name,
            'gender' => $gender['gender'] ?? null,
            'gender_probability' => $gender['probability'] ?? null,
            'gender_count' => $gender['count'] ?? 0,
            'country_id' => $top['country_id'] ?? null,
            'country_name' => $top['country_name'] ?? null,
            'country_region' => $top['region'] ?? null,
            'country_probability' => $top['probability'] ?? null,
            'nationality_count' => $nation['count'] ?? 0,
            'countries' => $countries,
        ];
    }

    private function query(string $name, string $service): array
    {
        $apiKey = config("services.{$service}.key");

        return array_filter(['name' => $name, 'apikey' => $apiKey]);
    }

    private function fromSaved(NamePrediction $saved): array
    {
        return $saved->only([
            'name', 'gender', 'gender_probability', 'gender_count',
            'country_id', 'country_name', 'country_region', 'country_probability',
            'nationality_count',
        ]) + ['countries' => $saved->countries ?? []];
    }

    /**
     * Daftar negara dari first.org, di-cache 1 hari karena jarang berubah.
     *
     * @return array<string, array{country: string, region: string}>
     */
    public function countries(): array
    {
        return Cache::remember('first_org_countries', now()->addDay(), function () {
            $response = Http::timeout(15)->get('https://api.first.org/data/v1/countries', ['limit' => 500]);

            if ($response->failed()) {
                throw new RuntimeException('Gagal mengambil data negara dari first.org.');
            }

            return $response->json('data', []);
        });
    }
}
