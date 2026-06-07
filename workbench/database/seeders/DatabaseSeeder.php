<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Trail\Trail\Models\TrailEvent;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory()->count(40)->create();

        $names = [
            'product.viewed', 'cart.updated', 'order.placed',
            'signup', 'activated', 'purchase',
            'landing.cta_clicked', 'dashboard.opened',
        ];

        $rows = [];
        for ($i = 0; $i < 2000; $i++) {
            $user = $users->random();
            $name = fake()->randomElement($names);
            $hasSubject = fake()->boolean(80);
            $occurredAt = now()->subMinutes(random_int(0, 60 * 24 * 30));

            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'subject_type' => $hasSubject ? $user->getMorphClass() : null,
                'subject_id' => $hasSubject ? $user->getKey() : null,
                'session_id' => 'sess-'.random_int(1, 200),
                'properties' => json_encode(['source' => fake()->randomElement(['web', 'mobile'])]),
                'context' => json_encode(['referrer' => fake()->randomElement(['google', 'newsletter', 'direct'])]),
                'value' => $name === 'purchase' ? fake()->randomFloat(2, 9, 499) : null,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            TrailEvent::insert($chunk);
        }
    }
}
