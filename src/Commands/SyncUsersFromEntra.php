<?php

declare(strict_types=1);

namespace HamedElasma\EntraSync\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use HamedElasma\EntraSync\Services\MicrosoftGraphService;

class SyncUsersFromEntra extends Command
{
    protected $signature = 'users:sync-from-entra {--dry-run : Show what would happen without making changes}';

    protected $description = 'Sync users from Microsoft Entra ID to the local database';

    public function handle(): int
    {
        $config = config('entra-sync');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be made.');
        }

        $service = new MicrosoftGraphService(
            tenantId: $config['tenant_id'],
            clientId: $config['client_id'],
            clientSecret: $config['client_secret'],
        );

        try {
            $entraUsers = $service->fetchAllUsers();
        } catch (\Throwable $e) {
            $this->error("Failed to fetch users from Entra: {$e->getMessage()}");
            Log::error('Entra sync failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $fieldMap = $config['field_map'];
        $userModel = $config['user_model'];
        $allowedDomains = $config['allowed_domains'] ?? [];

        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $skipped = 0;
        $warnings = 0;

        $entraIds = [];

        foreach ($entraUsers as $entraUser) {
            $email = $entraUser[$fieldMap['email']] ?? $entraUser['userPrincipalName'] ?? null;

            if (! $email) {
                $this->warn("Skipping user {$entraUser['id']} — no email or userPrincipalName.");
                $skipped++;

                continue;
            }

            if (! empty($allowedDomains)) {
                $emailDomain = Str::after($email, '@');
                $domainMatch = false;

                foreach ($allowedDomains as $domain) {
                    if (Str::lower($emailDomain) === Str::lower(trim($domain))) {
                        $domainMatch = true;

                        break;
                    }
                }

                if (! $domainMatch) {
                    $skipped++;

                    continue;
                }
            }

            $entraIds[] = $entraUser[$fieldMap['microsoft_id']];

            $localUser = $userModel::where('microsoft_id', $entraUser[$fieldMap['microsoft_id']])->first()
                ?? $userModel::where('email', $email)->first();

            $attributes = [
                'microsoft_id' => $entraUser[$fieldMap['microsoft_id']],
                'name' => $entraUser[$fieldMap['name']] ?? '',
                'email' => $email,
                'department' => $entraUser[$fieldMap['department']] ?? null,
                'job_title' => $entraUser[$fieldMap['job_title']] ?? null,
                'phone' => $entraUser[$fieldMap['phone']] ?? null,
                'is_active' => $entraUser['accountEnabled'] ?? true,
            ];

            if ($localUser) {
                // Check for email conflict: local user matched by email has a different microsoft_id
                if ($localUser->microsoft_id && $localUser->microsoft_id !== $entraUser[$fieldMap['microsoft_id']]) {
                    $this->warn("Email conflict: {$email} exists with microsoft_id {$localUser->microsoft_id}, Entra has {$entraUser[$fieldMap['microsoft_id']]}.");
                    Log::warning('Entra sync email conflict', [
                        'email' => $email,
                        'local_microsoft_id' => $localUser->microsoft_id,
                        'entra_microsoft_id' => $entraUser[$fieldMap['microsoft_id']],
                    ]);
                    $warnings++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("  [UPDATE] {$email}");
                } else {
                    $localUser->update($attributes);
                    $this->line("  Updated: {$email}");
                    Log::info('Entra sync updated user', ['email' => $email]);
                }
                $updated++;
            } else {
                if ($dryRun) {
                    $this->line("  [CREATE] {$email}");
                } else {
                    $userModel::create(array_merge($attributes, [
                        'password' => bcrypt(Str::random(32)),
                    ]));
                    $this->line("  Created: {$email}");
                    Log::info('Entra sync created user', ['email' => $email]);
                }
                $created++;
            }
        }

        // Deactivate users no longer in Entra
        if (! empty($entraIds)) {
            $toDeactivate = $userModel::whereNotNull('microsoft_id')
                ->whereNotIn('microsoft_id', $entraIds)
                ->where('is_active', true)
                ->get();

            foreach ($toDeactivate as $user) {
                if ($dryRun) {
                    $this->line("  [DEACTIVATE] {$user->email}");
                } else {
                    $user->update(['is_active' => false]);
                    $this->line("  Deactivated: {$user->email}");
                    Log::info('Entra sync deactivated user', ['email' => $user->email]);
                }
                $deactivated++;
            }
        }

        $this->newLine();
        $this->info('Sync complete:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Deactivated', $deactivated],
                ['Skipped', $skipped],
                ['Warnings', $warnings],
            ]
        );

        return self::SUCCESS;
    }
}
