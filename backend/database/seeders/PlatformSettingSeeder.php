<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Content\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * Baseline public website copy — schema v1.1 §4.8 (decision D2, FR-10).
 *
 * These rows exist so the site has real copy on a fresh install and so the
 * dashboard has something to edit. Values are JSON, which lets a single table
 * hold hero copy, list-shaped rules and feature flags side by side.
 *
 * Idempotent: re-running only inserts missing keys, so edits made in the
 * dashboard are never overwritten by a deploy.
 */
final class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $settings */
        $settings = [
            'homepage' => [
                'hero' => [
                    'title' => 'Science, communicated well.',
                    'subtitle' => 'The Minerva Scientific Communication Initiative turns rigorous research into writing the public can actually read.',
                    'primary_cta_label' => 'Read the latest',
                    'primary_cta_href' => '/articles',
                    'secondary_cta_label' => 'About the initiative',
                    'secondary_cta_href' => '/about',
                ],
                'highlights' => [
                    ['title' => 'Inter-university', 'body' => 'Open to undergraduates from every participating university.'],
                    ['title' => 'Peer reviewed', 'body' => 'Every published article passes editorial review before it goes live.'],
                    ['title' => 'Year-round', 'body' => 'Workshops, talks and a public e-magazine, not just a one-off contest.'],
                ],
            ],
            'initiative' => [
                'about' => [
                    'title' => 'About Minerva',
                    'body' => 'Minerva is run by the IEEE EMBS Student Branch Chapter of the University of Sri Jayewardenepura to raise the standard of science writing among Sri Lankan undergraduates.',
                ],
            ],
            'competition' => [
                'rules' => [
                    'intro' => 'Entries are judged on accuracy, clarity and originality.',
                    'sections' => [
                        ['title' => 'Eligibility', 'body' => 'Open to undergraduates of participating universities.'],
                        ['title' => 'Submission', 'body' => 'One article per entrant per round, written in English.'],
                        ['title' => 'Judging', 'body' => 'Editors score against a published rubric; decisions are final.'],
                    ],
                ],
            ],
        ];

        foreach ($settings as $group => $keys) {
            foreach ($keys as $key => $value) {
                PlatformSetting::query()->firstOrCreate(
                    ['setting_group' => $group, 'setting_key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
