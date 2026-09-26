<?php

namespace App\Support\Clients;

use Illuminate\Support\Str;

class ClientNameNormalizer
{
    public function normalize(string $name): string
    {
        return (string) Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }
}
