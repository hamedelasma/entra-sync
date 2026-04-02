# O3 Entra Sync

Sync users from Microsoft Entra ID (Azure AD) to your Laravel application's database via the Microsoft Graph API.

## Installation

Add the path repository to your app's `composer.json`:

```json
"repositories": [
    {"type": "path", "url": "../o3-entra-sync"}
]
```

Then require the package:

```bash
composer require o3/entra-sync
```

## Setup

### 1. Publish config and migration

```bash
php artisan vendor:publish --tag=entra-sync-config
php artisan vendor:publish --tag=entra-sync-migrations
php artisan migrate
```

### 2. Environment variables

Add to your `.env`:

```env
ENTRA_TENANT_ID=your-tenant-id
ENTRA_CLIENT_ID=your-client-id
ENTRA_CLIENT_SECRET=your-client-secret
```

### 3. Add trait to User model

```php
use O3\EntraSync\Models\Concerns\HasEntraSync;

class User extends Authenticatable
{
    use HasEntraSync;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
```

### 4. Schedule the sync command

In `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('users:sync-from-entra')->daily();
```

## Azure / Entra Portal Setup

1. Go to **App Registrations** in the Azure portal
2. Select your app (or create a new one)
3. Go to **API permissions** > **Add a permission**
4. Select **Microsoft Graph** > **Application permissions**
5. Add **User.Read.All**
6. Click **Grant admin consent**

## Usage

### Sync command

```bash
# Run sync
php artisan users:sync-from-entra

# Preview changes without modifying the database
php artisan users:sync-from-entra --dry-run
```

The command will:
- **Create** users that exist in Entra but not in your database
- **Update** existing users matched by `microsoft_id` (or `email` as fallback)
- **Deactivate** users that have a `microsoft_id` but are no longer in Entra (`is_active = false`)

### Trait scopes and helpers

```php
// Query scopes
User::active()->get();       // where is_active = true
User::fromEntra()->get();    // where microsoft_id is not null

// Instance methods
$user->isEntraUser();        // true if microsoft_id is set
$user->deactivate();         // sets is_active = false
```

## Configuration

The published config file (`config/entra-sync.php`) allows you to customize:

- `tenant_id`, `client_id`, `client_secret` — Azure credentials
- `user_model` — the Eloquent model class to sync to (default: `App\Models\User`)
- `field_map` — mapping between local DB columns and Graph API fields

## How it works

- Uses **client credentials flow** (no user interaction required)
- Authenticates via `POST https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token`
- Fetches users from `GET https://graph.microsoft.com/v1.0/users`
- Handles pagination automatically via `@odata.nextLink`
- Caches the access token to minimize API calls
- New users get a random password (authentication should be handled via Socialite SSO)
- Logs warnings when email conflicts are detected (existing user with different `microsoft_id`)

## Testing

```bash
vendor/bin/pest
```

## License

Proprietary - O3 internal use only.
