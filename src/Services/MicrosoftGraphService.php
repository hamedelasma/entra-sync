<?php

declare(strict_types=1);

namespace HamedElasma\EntraSync\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final readonly class MicrosoftGraphService
{
    public function __construct(
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
    ) {}

    /**
     * Fetch all users from Microsoft Graph API.
     *
     * @return Collection<int, array{id: string, displayName: string, mail: ?string, userPrincipalName: string, department: ?string, jobTitle: ?string, mobilePhone: ?string, accountEnabled: bool}>
     */
    public function fetchAllUsers(): Collection
    {
        $token = $this->getAccessToken();

        $users = collect();
        $url = 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,mail,userPrincipalName,department,jobTitle,mobilePhone,accountEnabled';

        while ($url) {
            $response = Http::withToken($token)
                ->timeout(30)
                ->get($url);

            $response->throw();

            $data = $response->json();
            $users = $users->concat($data['value'] ?? []);
            $url = $data['@odata.nextLink'] ?? null;
        }

        return $users;
    }

    private function getAccessToken(): string
    {
        return Cache::remember('entra-sync:access-token', 3500, function (): string {
            $response = Http::asForm()
                ->timeout(15)
                ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);

            $response->throw();

            return $response->json('access_token');
        });
    }
}
