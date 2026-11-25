# Laravel Sanctum Manager

[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![Sanctum](https://img.shields.io/badge/Sanctum-%5E4.0-4A5568)](https://laravel.com/docs/sanctum)

Gestione avanzata di **Scope / Abilities** e **Personal Access Token** per Laravel Sanctum con architettura **Zero-Migration First** (funziona out-of-the-box sulla tabella `personal_access_tokens` nativa di Sanctum) e **Migrazioni Opzionali** per colonne SQL dedicate e Audit Log storico.

---

## Funzionalità principali

- **Zero-Migration First (+ Migrazioni Opzionali)**: funziona subito senza toccare il database; se vuoi colonne SQL indicizzate (`revoked_at`, `allowed_ips`, `uses_count`, `metadata`) e una tabella di audit (`sanctum_token_logs`), puoi pubblicare le migrazioni opzionali in qualsiasi momento.
- **Auto-installazione di Sanctum**: `laravel/sanctum` è una dipendenza Composer automatica e `php artisan sanctum-manager:install` rileva se Sanctum non è ancora migrato nel progetto pubblicando automaticamente la sua migrazione base.
- **Scope Registry & Gerarchie**: catalogo centralizzato di scope con descrizioni, gruppi/preset (`@readonly`), dipendenze transitive `implies` (con protezione da cicli infiniti), scope di default e scope deprecati.
- **Wildcard Avanzate (`ScopeMatcher`)**: supporto a `*`, namespace wildcard (`orders:*`), action wildcard (`*:read`) e wildcard intermedi (`orders:*:read`).
- **Rotazione Atomica con Grace Period**: rigenera un token mantenendo attivo il vecchio per $N$ minuti e allegando automaticamente gli header `X-Sanctum-Token-Deprecated: true` e `X-Sanctum-Token-Grace-Expires`.
- **Sicurezza per singolo Token**: restrizioni IP/CIDR (IPv4/IPv6), restrizioni Origin/Referer con wildcard di sottodominio (`*.example.com`), limite utilizzi massimo (`max_uses`), quota massima token per utente (`max_tokens_per_user`), prefissi custom per secret scanning (`sm_live_...`).
- **Debounced Usage Logger & Token Caching**: evita row-lock contention su `last_used_at` tramite debounce in Cache e offre caching opzionale di `findToken()` con invalidazione automatica su `saved`/`deleted`.
- **Middleware & Attributi PHP 8**: `sanctum.constraints`, `sanctum.scopes`, `sanctum.scope.any`, `sanctum.attributes` (`#[RequiresScope]`, `#[RequiresAnyScope]`).
- **Rate Limiter Dinamico**: `RateLimiter::for('sanctum-token', ...)` configurabile per singolo token via `metadata['rate_limit_per_minute']`.
- **6 Comandi Artisan & Endpoint API Headless opzionali**.

---

## Installazione

```bash
composer require salvatore/laravel-sanctum-manager
```

Avvia la configurazione guidata (installa automaticamente anche la migrazione base di Laravel Sanctum se non presente):

```bash
php artisan sanctum-manager:install
```

Se desideri invece abilitare anche le **migrazioni opzionali** per colonne SQL dedicate e tabella `sanctum_token_logs`:

```bash
php artisan sanctum-manager:install --with-migrations
```

Aggiungi il trait `ManagesSanctumTokens` al tuo modello `User` (include già `Laravel\Sanctum\HasApiTokens`):

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use SanctumManager\Traits\ManagesSanctumTokens;

class User extends Authenticatable
{
    use ManagesSanctumTokens;
}
```

---

## Utilizzo Rapido

### 1. Definire Scope, Gerarchie e Gruppi

Nel `boot()` del tuo `AppServiceProvider` (oppure in `config/sanctum-manager.php`):

```php
use SanctumManager\Facades\SanctumManager;

SanctumManager::defineScope('orders:read', 'Lettura ordini', group: 'orders', isDefault: true);
SanctumManager::defineScope('orders:write', 'Creazione/modifica ordini', group: 'orders', implies: ['orders:read']);
SanctumManager::defineScope('orders:admin', 'Gestione completa ordini', group: 'orders', implies: ['orders:write']);

SanctumManager::defineGroup('readonly', ['orders:read'], 'Accesso in sola lettura');
```

### 2. Emettere, Ruotare e Revocare Token

```php
use SanctumManager\DataTransferObjects\TokenOptions;

// Emissione con opzioni di sicurezza
$issued = $user->issueToken(
    name: 'CI/CD Pipeline',
    scopes: ['orders:write'], // espande automaticamente anche orders:read
    options: TokenOptions::make()
        ->expiresInDays(30)
        ->allowIps(['10.0.0.0/8', '203.0.113.42'])
        ->allowOrigins(['https://*.example.com'])
        ->maxUses(5000)
        ->withPrefix('sm_live_')
        ->withMetadata(['rate_limit_per_minute' => 300]),
);

$plainTextToken = $issued->plainTextToken;

// Rotazione con grace period di 30 minuti
$rotated = $user->rotateToken($issued->accessToken, gracePeriodMinutes: 30);

// Revoca singola, per scope o totale
$user->revokeToken($issued->accessToken, 'Compromesso');
$user->revokeTokensWithScope('orders:write', 'Permesso rimosso');
$user->revokeAllTokens('Logout globale');
```

### 3. Protezione Rotte con Middleware e Attributi PHP 8

```php
use SanctumManager\Attributes\RequiresAnyScope;
use SanctumManager\Attributes\RequiresScope;

// routes/api.php
Route::middleware(['auth:sanctum', 'sanctum.constraints', 'sanctum.attributes'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
});

// OrderController.php
#[RequiresScope('orders:read')]
class OrderController
{
    public function index() { /* ... */ }

    #[RequiresScope('orders:write', 'billing:charge')]
    public function store() { /* ... */ }

    #[RequiresAnyScope(['orders:cancel', 'orders:admin'])]
    public function destroy() { /* ... */ }
}
```

### 4. Comandi Artisan

| Comando | Descrizione |
| --- | --- |
| `php artisan sanctum-manager:install` | Configura il pacchetto (e pubblica Sanctum se assente) |
| `php artisan sanctum-manager:scopes` | Elenca tutti gli scope e i gruppi registrati |
| `php artisan sanctum-manager:issue {user} {name}` | Emette un token da CLI con scope, IP, scadenza e `max-uses` |
| `php artisan sanctum-manager:revoke {token?}` | Revoca un token per ID o tutti i token di un utente (`--user=`) |
| `php artisan sanctum-manager:rotate {token}` | Ruota un token con grace period configurabile (`--grace=`) |
| `php artisan sanctum-manager:prune` | Pulisce token scaduti/revocati e log obsoleti |

---

## Testing & Qualità del Codice

```bash
composer test     # PHPUnit (Zero-Migration Mode + Extended Mode)
composer analyse  # PHPStan / Larastan livello 8
composer format   # Laravel Pint
```

## Licenza

MIT
# laravel-sanctum-manager
