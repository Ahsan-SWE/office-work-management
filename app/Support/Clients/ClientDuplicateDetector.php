<?php

namespace App\Support\Clients;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientDuplicateDetector
{
    public function __construct(
        private readonly ClientNameNormalizer $normalizer,
    ) {
    }

    public function find(string $name, ?int $excludeClientId = null): Collection
    {
        $normalized = $this->normalizer->normalize($name);

        if ($normalized === '') {
            return collect();
        }

        $query = Client::query()
            ->select(['id', 'client_code', 'name', 'normalized_name', 'status'])
            ->orderByDesc('created_at')
            ->limit(1000);

        if ($excludeClientId) {
            $query->whereKeyNot($excludeClientId);
        }

        return $query->get()
            ->map(function (Client $client) use ($normalized) {
                $score = 0.0;

                if ($client->normalized_name === $normalized) {
                    $score = 100.0;
                } else {
                    similar_text($client->normalized_name, $normalized, $score);

                    $contains = strlen($normalized) >= 6
                        && (
                            str_contains($client->normalized_name, $normalized)
                            || str_contains($normalized, $client->normalized_name)
                        );

                    if ($contains) {
                        $score = max($score, 90.0);
                    }
                }

                return [
                    'id' => $client->id,
                    'client_code' => $client->client_code,
                    'name' => $client->name,
                    'status' => $client->status->value,
                    'score' => round($score, 1),
                ];
            })
            ->filter(fn (array $candidate) => $candidate['score'] >= 88.0)
            ->sortByDesc('score')
            ->values()
            ->take(8);
    }
}
