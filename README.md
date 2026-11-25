# Laravel Sanctum Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/salvatorecervone/laravel-sanctum-manager.svg?style=flat-square)](https://packagist.org/packages/salvatorecervone/laravel-sanctum-manager)
[![Total Downloads](https://img.shields.io/packagist/dt/salvatorecervone/laravel-sanctum-manager.svg?style=flat-square)](https://packagist.org/packages/salvatorecervone/laravel-sanctum-manager)
[![PHP](https://img.shields.io/badge/PHP-%5E8.2-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![Sanctum](https://img.shields.io/badge/Sanctum-%5E4.0-4A5568?style=flat-square)](https://laravel.com/docs/sanctum)
[![License](https://img.shields.io/packagist/l/salvatorecervone/laravel-sanctum-manager.svg?style=flat-square)](https://packagist.org/packages/salvatorecervone/laravel-sanctum-manager)

Gestione avanzata di **Scope / Abilities** e **Personal Access Token** per Laravel Sanctum con architettura **Zero-Migration First** (funziona out-of-the-box sulla tabella `personal_access_tokens` nativa di Sanctum) e **Migrazioni Opzionali** per colonne SQL dedicate e Audit Log storico.

---

## Requisiti

- PHP `^8.2`
- Laravel `^11.0` o `^12.0`
- Laravel Sanctum `^4.0` (installato automaticamente tramite Composer)

---

## Funzionalità principali

- **Zero-Migration First (+ Migrazioni Opzionali)**: funziona subito senza modificare lo schema del database; se desideri colonne SQL indicizzate (`revoked_at`, `allowed_ips`, `uses_count`, `metadata`) e una tabella di audit (`sanctum_token_logs`), puoi pubblicare le migrazioni opzionali in qualsiasi momento.
- **Auto-rilevamento di Laravel Sanctum**: `php artisan sanctum-manager:install` verifica se Sanctum è già configurato nel progetto e, in caso contrario, pubblica automaticamente la sua migrazione base.
- **Scope Registry & Gerarchie**: catalogo centralizzato di scope con descrizioni, gruppi/preset (`@readonly`), dipendenze transitive `implies` (con protezione da cicli infiniti), scope di default e scope deprecati.
- **Wildcard Avanzate (`ScopeMatcher`)**: supporto a `*`, namespace wildcard (`orders:*`), action wildcard (`*:read`) e wildcard intermedi (`orders:*:read`).
- **Rotazione Atomica con Grace Period**: rigenera un token mantenendo valido il vecchio per *N* minuti e aggiungendo automaticamente gli header HTTP `X-Sanctum-Token-Deprecated: true` e `X-Sanctum-Token-Grace-Expires`.
- **Sicurezza per singolo Token**: restrizioni IP/CIDR (IPv4/IPv6), restrizioni `Origin`/`Referer` con wildcard di sottodominio (`*.example.com`), limite utilizzi massimo (`max_uses`), quota massima di token per utente (`max_tokens_per_user`) e prefissi custom per secret scanning (`sm_live_...`).
- **Debounced Usage Logger & Token Caching**: riduce il carico sul database evitando `UPDATE` su `last_used_at` a ogni richiesta tramite debounce in Cache e offre caching opzionale su `findToken()` con invalidazione automatica.
- **Middleware & Attributi PHP 8**: `sanctum.constraints`, `sanctum.scopes`, `sanctum.scope.any`, `sanctum.attributes` (`#[RequiresScope]`, `#[RequiresAnyScope]`).
- **Rate Limiter Dinamico**: `throttle:sanctum-token` configurabile globalmente o per singolo token tramite `metadata['rate_limit_per_minute']`.
- **6 Comandi Artisan & Endpoint API Headless opzionali**.

---

## Installazione

### 1. Installa il pacchetto via Composer

```bash
composer require salvatorecervone/laravel-sanctum-manager
```

Il Service Provider (`SanctumManager\SanctumManagerServiceProvider`) e la Facade (`SanctumManager`) vengono registrati automaticamente grazie al package auto-discovery di Laravel.

### 2. Esegui il comando di installazione

#### Modalità Zero-Migration (consigliata per iniziare subito)

Pubblica `config/sanctum-manager.php` e, se necessario, la migrazione base di Laravel Sanctum:

```bash
php artisan sanctum-manager:install
```

#### Modalità Extended Columns + Audit Log (opzionale)

Se vuoi aggiungere colonne SQL dedicate su `personal_access_tokens` e la tabella storica `sanctum_token_logs`:

```bash
php artisan sanctum-manager:install --with-migrations
php artisan migrate
```

Imposta poi nel tuo `.env`:

```env
SANCTUM_MANAGER_STORAGE_MODE=extended_columns
```

> Puoi anche usare `SANCTUM_MANAGER_STORAGE_MODE=auto` per rilevare automaticamente se le colonne estese esistono nel database.

#### Pubblicazione manuale (alternativa)

Se preferisci pubblicare manualmente i file tramite `vendor:publish`:

```bash
# Solo file di configurazione
php artisan vendor:publish --tag=sanctum-manager-config

# Solo migrazioni opzionali
php artisan vendor:publish --tag=sanctum-manager-migrations
php artisan migrate
```

### 3. Configura il modello `User`

Aggiungi il trait `ManagesSanctumTokens` al tuo modello `User` (include già `Laravel\Sanctum\HasApiTokens`, quindi puoi sostituirlo direttamente):

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use SanctumManager\Traits\ManagesSanctumTokens;

class User extends Authenticatable
{
    use ManagesSanctumTokens;
}
```

---

## Configurazione

Il file `config/sanctum-manager.php` permette di configurare ogni aspetto del pacchetto:

| Sezione | Opzioni principali | Default |
| --- | --- | --- |
| `storage_mode` | `zero_migration`, `extended_columns`, `auto` | `zero_migration` |
| `scopes` | `strict_validation`, `definitions`, `groups` | `strict_validation: false` |
| `tokens` | `default_expiration_minutes`, `max_tokens_per_user`, `quota_exceeded_strategy`, `token_prefix` | `null` / `throw_exception` |
| `rotation` | `grace_period_minutes` | `30` |
| `security` | `keep_revoked_tokens`, `enforce_ip_restrictions`, `enforce_origin_restrictions` | `false` / `true` / `true` |
| `rate_limiting` | `default_per_minute` | `60` |
| `audit` | `update_interval_seconds`, `log_requests`, `driver` (`log` \| `database`) | `300` / `false` / `log` |
| `cache` | `enabled`, `ttl_seconds`, `prefix` | `false` / `60` |
| `routes` | `enabled`, `prefix`, `middleware` | `false` / `api/sanctum-manager` |

Variabili d'ambiente supportate di default:

```env
SANCTUM_MANAGER_STORAGE_MODE=zero_migration
SANCTUM_TOKEN_PREFIX=
```

---

## Utilizzo Rapido

### 1. Definire Scope, Gerarchie e Gruppi

Nel metodo `boot()` di `AppServiceProvider` (oppure direttamente in `config/sanctum-manager.php`):

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

#### Middleware disponibili

| Alias | Funzione |
| --- | --- |
| `sanctum.constraints` | Verifica revoca, scadenza, grace period, IP/CIDR, Origin/Referer, `max_uses` e registra l'utilizzo |
| `sanctum.scopes:a,b` | Richiede **tutti** gli scope indicati (con supporto wildcard e gerarchie) |
| `sanctum.scope.any:a,b` | Richiede **almeno uno** degli scope indicati |
| `sanctum.attributes` | Legge gli attributi PHP 8 `#[RequiresScope]` e `#[RequiresAnyScope]` su Controller e action |
| `throttle:sanctum-token` | Rate limiter dinamico per token (`metadata['rate_limit_per_minute']`) |

#### Esempio con Attributi PHP 8

```php
use Illuminate\Support\Facades\Route;
use SanctumManager\Attributes\RequiresAnyScope;
use SanctumManager\Attributes\RequiresScope;

// routes/api.php
Route::middleware(['auth:sanctum', 'sanctum.constraints', 'sanctum.attributes', 'throttle:sanctum-token'])
    ->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
    });

// app/Http/Controllers/OrderController.php
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

### 4. Comandi Artisan & Pruning Schedulato

| Comando | Descrizione |
| --- | --- |
| `php artisan sanctum-manager:install {--with-migrations} {--force}` | Configura il pacchetto (e pubblica Sanctum se assente) |
| `php artisan sanctum-manager:scopes` | Elenca tutti gli scope e i gruppi registrati |
| `php artisan sanctum-manager:issue {user} {name}` | Emette un token da CLI con scope, IP, scadenza e `max-uses` |
| `php artisan sanctum-manager:revoke {token?}` | Revoca un token per ID o tutti i token di un utente (`--user=`) |
| `php artisan sanctum-manager:rotate {token}` | Ruota un token con grace period configurabile (`--grace=`) |
| `php artisan sanctum-manager:prune {--days=30}` | Pulisce token scaduti/revocati e log obsoleti |

Per eseguire automaticamente il pruning giornaliero in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum-manager:prune --days=30')->daily();
```

### 5. Endpoint API Headless (Opzionali)

Impostando `'routes' => ['enabled' => true]` in `config/sanctum-manager.php`, vengono esposti automaticamente i seguenti endpoint JSON sotto `/api/sanctum-manager`:

- `GET /scopes` — Elenco scope e gruppi registrati
- `GET /tokens` — Lista dei token dell'utente autenticato
- `POST /tokens` — Creazione di un nuovo token
- `POST /tokens/{tokenId}/rotate` — Rotazione di un token
- `DELETE /tokens/{tokenId}` — Revoca di un token

### 6. Eventi

Il pacchetto emette eventi Laravel standard in `SanctumManager\Events\*`:

- `TokenIssued`
- `TokenRotated`
- `TokenRevoked`
- `TokenExpired`
- `TokenIpDenied`
- `TokenScopeDenied`

---

## Testing & Qualità del Codice

```bash
composer test     # PHPUnit (Zero-Migration Mode + Extended Mode)
composer analyse  # PHPStan / Larastan livello 8
composer format   # Laravel Pint
```

## Licenza

Distribuito con licenza **MIT**.
