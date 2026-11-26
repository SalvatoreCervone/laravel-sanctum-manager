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
- **Interfaccia Grafica Web (Dashboard) con Gate**: dashboard Blade integrata (zero build step, light/dark mode, modali nativi `<dialog>`) per gestire tutti i comandi da browser — emissione, rotazione, revoca singola/di massa, pruning, catalogo scope, audit log e test email — protetta dal Laravel Gate `viewSanctumManager`.
- **Notifiche Email Automatiche**: invio automatico di email alla creazione (`TokenIssued`), revoca (`TokenRevoked`) e rotazione (`TokenRotated`) di un token, al proprietario del token e/o a destinatari amministrativi configurabili (senza mai esporre il secret in chiaro).
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

# Solo migrazioni opzionali (colonne estese, audit log e tabelle scope/gruppi)
php artisan vendor:publish --tag=sanctum-manager-migrations
php artisan migrate

# Solo migrazione tabelle scope & gruppi (`sanctum_scopes`, `sanctum_scope_groups`)
php artisan vendor:publish --tag=sanctum-manager-scopes-migration
php artisan migrate

# Solo viste Blade (Dashboard UI e template Email)
php artisan vendor:publish --tag=sanctum-manager-views

# Solo traduzioni (EN / IT)
php artisan vendor:publish --tag=sanctum-manager-lang
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

Tutte le impostazioni del pacchetto risiedono in `config/sanctum-manager.php` (pubblicabile con `php artisan sanctum-manager:install` o `php artisan vendor:publish --tag=sanctum-manager-config`).

### Legenda: dove si configurano le opzioni

| Modalità | Significato |
| --- | --- |
| **`.env` / `config`** | Ha una variabile d'ambiente dedicata in `.env` (e può essere personalizzata anche in `config/sanctum-manager.php`). |
| **Solo `config`** | Nessuna variabile `.env` predefinita: si modifica in `config/sanctum-manager.php` (dopo il publish). |
| **Pannello Web + `config`/codice** | Gestibile direttamente dalla Dashboard (CRUD persistito su DB o file JSON), oltre che da `config/sanctum-manager.php` o codice. |
| **Override da Pannello** | Il default globale si imposta in `.env`/`config`, ma può essere sovrascritto per singolo token o azione dalla Dashboard (e via CLI/API). |

> **Nota sulla Dashboard Web:** le impostazioni globali di sistema (Storage Mode, Audit Log, Access Gate, Notifiche Email) sono **mostrate in sola lettura** nel pannello per trasparenza e sicurezza: vanno modificate tramite `.env` o `config/sanctum-manager.php`. Dal pannello puoi invece gestire direttamente il catalogo **Scope & Gruppi**, **emettere/ruotare/revocare token** (con override di scadenza, IP, Origin, `max_uses`, prefisso, rate limit e grace period), **modificare gli scope** di un token, eseguire il **pruning** e inviare un'**email di test**.

---

### 1. Modalità di Storage (`storage_mode`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `storage_mode` | `SANCTUM_MANAGER_STORAGE_MODE` | `'zero_migration'` | **`.env` / `config`** *(sola lettura nel pannello)* | Definisce dove salvare i metadati estesi dei token (IP/Origin consentiti, `max_uses`, stato revoca, grace period, metadata custom):<br>• `zero_migration`: usa solo la tabella nativa `personal_access_tokens` (metadati nel JSON `abilities` + contatori in Cache).<br>• `extended_columns`: usa le colonne SQL dedicate aggiunte dalle migrazioni opzionali.<br>• `auto`: rileva automaticamente se le colonne estese esistono nel DB. |

### 2. Scope & Gruppi (`scopes`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `scopes.strict_validation` | — | `false` | **Solo `config`** *(sola lettura nel pannello)* | Se `true`, impedisce di emettere o aggiornare un token con scope non registrati lanciando `InvalidScopeException`. |
| `scopes.storage_driver` | `SANCTUM_MANAGER_SCOPES_DRIVER` | `'auto'` | **`.env` / `config`** *(sola lettura nel pannello)* | Driver di persistenza per gli scope e i gruppi creati/modificati dalla Dashboard:<br>• `auto`: usa le tabelle DB `sanctum_scopes` / `sanctum_scope_groups` se migrate, altrimenti il file JSON.<br>• `database`: salva sempre su DB.<br>• `file`: salva sempre su file JSON. |
| `scopes.storage_path` | `SANCTUM_MANAGER_SCOPES_PATH` | `null` | **`.env` / `config`** | Percorso assoluto del file JSON usato quando il driver attivo è `file`. Se `null`, usa `storage/app/sanctum-manager/scopes.json`. |
| `scopes.definitions` | — | `[]` | **Pannello Web + `config`/codice** | Catalogo degli scope applicativi (`description`, `group`, `implies`, `is_default`, `is_deprecated`). Gestibile anche dalla sezione *Scopes* della Dashboard o via `SanctumManager::defineScope()`. |
| `scopes.groups` | — | `[]` | **Pannello Web + `config`/codice** | Gruppi/preset di scope (es. `@readonly` → `['orders:read', 'users:read']`). Gestibili dalla sezione *Scopes* della Dashboard o via `SanctumManager::defineGroup()`. |

### 3. Emissione Token & Quote (`tokens`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `tokens.default_expiration_minutes` | — | `null` | **Solo `config`** *(override per singolo token dal pannello)* | Durata predefinita (in minuti) applicata ai nuovi token se non viene indicata una scadenza esplicita (`null` = nessuna scadenza). |
| `tokens.max_tokens_per_user` | — | `null` | **Solo `config`** | Numero massimo di token attivi contemporaneamente per singolo utente (`null` = illimitato). |
| `tokens.quota_exceeded_strategy` | — | `'throw_exception'` | **Solo `config`** | Comportamento al superamento di `max_tokens_per_user`: `throw_exception` blocca l'emissione con `TokenQuotaExceededException`, `revoke_oldest` revoca automaticamente il token più vecchio dell'utente. |
| `tokens.token_prefix` | `SANCTUM_TOKEN_PREFIX` | `''` | **`.env` / `config`** *(override per singolo token dal pannello)* | Prefisso aggiunto ai token in chiaro generati (es. `sm_live_`), utile per compatibilità con strumenti di secret scanning (GitHub, GitGuardian, ecc.). |
| `tokens.hash_prefix` | — | `null` | **Solo `config`** | Campo riservato per eventuali configurazioni avanzate di prefisso hash (`null` di default). |

### 4. Rotazione Token (`rotation`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `rotation.grace_period_minutes` | — | `30` | **Solo `config`** *(override per singola rotazione dal pannello)* | Periodo di grazia predefinito (in minuti) durante il quale il vecchio token resta valido insieme al nuovo dopo una rotazione, evitando errori sulle richieste in volo. |

### 5. Sicurezza & Vincoli (`security`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `security.keep_revoked_tokens` | — | `false` | **Solo `config`** | Se `false`, la revoca elimina subito il record del token dal DB. Se `true`, esegue una soft-revocation mantenendo il record per audit/storico fino al pruning. |
| `security.enforce_ip_restrictions` | — | `true` | **Solo `config`** *(gli IP consentiti per singolo token si impostano dal pannello in emissione)* | Attiva/disattiva globalmente la verifica degli IP/CIDR consentiti nel middleware `sanctum.constraints`. |
| `security.enforce_origin_restrictions` | — | `true` | **Solo `config`** *(gli Origin consentiti per singolo token si impostano dal pannello in emissione)* | Attiva/disattiva globalmente la verifica degli header `Origin`/`Referer` nel middleware `sanctum.constraints`. |

### 6. Rate Limiting (`rate_limiting`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `rate_limiting.default_per_minute` | — | `60` | **Solo `config`** *(override per singolo token dal pannello in emissione)* | Limite predefinito di richieste al minuto per il rate limiter `throttle:sanctum-token`. Sovrascrivibile sul singolo token via `metadata['rate_limit_per_minute']` o campo *Rate limit* nella Dashboard. |

### 7. Tracciamento Uso & Audit Log (`audit`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `audit.update_interval_seconds` | — | `300` | **Solo `config`** *(sola lettura nel pannello)* | Intervallo di debounce (in secondi, via Cache) per aggiornare `last_used_at` sul DB, evitando row-lock ad alto traffico. |
| `audit.log_requests` | — | `false` | **Solo `config`** *(sola lettura nel pannello)* | Se `true`, registra ogni richiesta autenticata con token (endpoint, metodo, IP, user-agent, stato). |
| `audit.driver` | — | `'log'` | **Solo `config`** *(sola lettura nel pannello)* | Dove scrivere i log quando `log_requests` è attivo: `log` (canale di log standard Laravel) oppure `database` (tabella `sanctum_token_logs`, visibile nella sezione *Audit Log* della Dashboard; richiede le migrazioni opzionali). |

### 8. Cache Lookup Token (`cache`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `cache.enabled` | — | `false` | **Solo `config`** | Memorizza in Cache le istanze `PersonalAccessToken` risolte da `findToken()` per ridurre le query al DB (invalidata automaticamente su revoca, rotazione e modifica scope). |
| `cache.ttl_seconds` | — | `60` | **Solo `config`** | Durata in secondi (TTL) dei token salvati in Cache. |
| `cache.prefix` | — | `'sanctum_mgr:token:'` | **Solo `config`** | Prefisso delle chiavi di Cache generate dal pacchetto. |

### 9. Endpoint API Headless (`routes`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `routes.enabled` | — | `false` | **Solo `config`** | Abilita gli endpoint REST JSON built-in per elencare scope e gestire i token dell'utente autenticato. |
| `routes.prefix` | — | `'api/sanctum-manager'` | **Solo `config`** | Prefisso URL delle rotte API headless. |
| `routes.middleware` | — | `['api', 'auth:sanctum']` | **Solo `config`** | Middleware applicati agli endpoint API headless. |

### 10. Dashboard Web & Controllo Accessi (`dashboard`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `dashboard.enabled` | `SANCTUM_MANAGER_DASHBOARD_ENABLED` | `true` | **`.env` / `config`** | Abilita o disabilita completamente l'interfaccia grafica web. |
| `dashboard.prefix` | `SANCTUM_MANAGER_DASHBOARD_PREFIX` | `'sanctum-manager'` | **`.env` / `config`** | Percorso URL su cui è montata la Dashboard (es. `/sanctum-manager`). |
| `dashboard.domain` | `SANCTUM_MANAGER_DASHBOARD_DOMAIN` | `null` | **`.env` / `config`** | Dominio o sottodominio dedicato opzionale per la Dashboard (es. `admin.example.com`). |
| `dashboard.middleware` | — | `['web', 'sanctum.dashboard']` | **Solo `config`** *(sola lettura nel pannello)* | Middleware applicati alla Dashboard (`sanctum.dashboard` controlla il Laravel Gate). |
| `dashboard.gate` | `SANCTUM_MANAGER_DASHBOARD_GATE` | `'viewSanctumManager'` | **`.env` / `config`** *(sola lettura nel pannello)* | Nome del Laravel Gate utilizzato per autorizzare l'accesso alla Dashboard. |
| `dashboard.allowed_emails` | `SANCTUM_MANAGER_ALLOWED_EMAILS` | `[]` | **`.env` / `config`** *(sola lettura nel pannello)* | Elenco di email autorizzate ad accedere al pannello (in `.env` separate da virgole, es. `admin@example.com,ops@example.com`). |
| `dashboard.allow_in_local` | `SANCTUM_MANAGER_ALLOW_IN_LOCAL` | `true` | **`.env` / `config`** *(sola lettura nel pannello)* | Se `true`, consente l'accesso automatico alla Dashboard quando `APP_ENV=local`. |
| `dashboard.per_page` | — | `20` | **Solo `config`** | Numero di token mostrati per pagina nella tabella principale della Dashboard. |

### 11. Notifiche Email (`mail`)

| Chiave | Variabile `.env` | Default | Dove si configura | A cosa serve |
| --- | --- | --- | --- | --- |
| `mail.enabled` | `SANCTUM_MANAGER_MAIL_ENABLED` | `false` | **`.env` / `config`** *(sola lettura nel pannello; test invio disponibile)* | Abilita l'invio automatico delle email di notifica sul ciclo di vita dei token (il secret in chiaro non viene mai incluso). |
| `mail.events.issued` | — | `true` | **Solo `config`** *(sola lettura nel pannello)* | Invia un'email quando viene emesso un nuovo token. |
| `mail.events.revoked` | — | `true` | **Solo `config`** *(sola lettura nel pannello)* | Invia un'email quando un token viene revocato. |
| `mail.events.rotated` | — | `true` | **Solo `config`** *(sola lettura nel pannello)* | Invia un'email quando un token viene ruotato. |
| `mail.notify_token_owner` | — | `true` | **Solo `config`** *(sola lettura nel pannello)* | Se `true`, invia la notifica anche all'email del proprietario del token. |
| `mail.recipients` | `SANCTUM_MANAGER_MAIL_RECIPIENTS` | `[]` | **`.env` / `config`** *(sola lettura nel pannello)* | Destinatari amministrativi/security aggiuntivi (in `.env` separati da virgole). |
| `mail.from.address` | `SANCTUM_MANAGER_MAIL_FROM_ADDRESS` | `null` | **`.env` / `config`** | Indirizzo email mittente dedicato (`null` = usa `MAIL_FROM_ADDRESS` globale di Laravel). |
| `mail.from.name` | `SANCTUM_MANAGER_MAIL_FROM_NAME` | `null` | **`.env` / `config`** | Nome mittente dedicato (`null` = usa `MAIL_FROM_NAME` globale di Laravel). |
| `mail.subject_prefix` | `SANCTUM_MANAGER_MAIL_SUBJECT_PREFIX` | `'[Sanctum Manager]'` | **`.env` / `config`** | Prefisso anteposto all'oggetto di tutte le email inviate dal pacchetto. |
| `mail.queue` | `SANCTUM_MANAGER_MAIL_QUEUE` | `false` | **`.env` / `config`** *(sola lettura nel pannello)* | Se `true`, accoda l'invio delle email tramite Laravel Queue invece di inviarle in modo sincrono. |
| `mail.queue_connection` | `SANCTUM_MANAGER_MAIL_QUEUE_CONNECTION` | `null` | **`.env` / `config`** | Connessione di coda specifica da usare quando `mail.queue` è `true` (`null` = default Laravel). |
| `mail.queue_name` | `SANCTUM_MANAGER_MAIL_QUEUE_NAME` | `null` | **`.env` / `config`** | Nome della coda su cui instradare le email quando `mail.queue` è `true` (`null` = coda predefinita). |

---

### Elenco completo variabili `.env` supportate

```env
# Storage & Prefisso Token
SANCTUM_MANAGER_STORAGE_MODE=zero_migration
SANCTUM_TOKEN_PREFIX=

# Scope & Gruppi dinamici (DB se migrato, altrimenti fallback su file JSON)
SANCTUM_MANAGER_SCOPES_DRIVER=auto
SANCTUM_MANAGER_SCOPES_PATH=

# Dashboard & Access Gate
SANCTUM_MANAGER_DASHBOARD_ENABLED=true
SANCTUM_MANAGER_DASHBOARD_PREFIX=sanctum-manager
SANCTUM_MANAGER_DASHBOARD_DOMAIN=
SANCTUM_MANAGER_DASHBOARD_GATE=viewSanctumManager
SANCTUM_MANAGER_ALLOWED_EMAILS=admin@example.com,ops@example.com
SANCTUM_MANAGER_ALLOW_IN_LOCAL=true

# Notifiche Email
SANCTUM_MANAGER_MAIL_ENABLED=false
SANCTUM_MANAGER_MAIL_RECIPIENTS=security@example.com
SANCTUM_MANAGER_MAIL_FROM_ADDRESS=
SANCTUM_MANAGER_MAIL_FROM_NAME=
SANCTUM_MANAGER_MAIL_SUBJECT_PREFIX="[Sanctum Manager]"
SANCTUM_MANAGER_MAIL_QUEUE=false
SANCTUM_MANAGER_MAIL_QUEUE_CONNECTION=
SANCTUM_MANAGER_MAIL_QUEUE_NAME=
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

### 6. Interfaccia Grafica Web (Dashboard) & Access Gate

Il pacchetto include una **Dashboard Web pronta all'uso** (zero build step, Light/Dark mode) disponibile su `/sanctum-manager` che permette di eseguire tutte le operazioni gestionali da browser:

- **Emissione Token**: selezione utente, scope/gruppi (`@readonly`), scadenza, IP/CIDR, Origin/Referer, `max_uses`, prefisso e rate limit, con visualizzazione *one-time* del token in chiaro e pulsante copia.
- **Rotazione Token**: rotazione con *grace period* configurabile e nuovo nome opzionale.
- **Revoca Singola & Revoca Totale Utente**: revoca di un singolo token o di tutti i token attivi di uno specifico utente con motivazione.
- **Pruning**: pulizia immediata di token scaduti/revocati e vecchi audit log.
- **Catalogo & Gestione Scope e Gruppi (CRUD)**: creazione, modifica ed eliminazione di scope (con gerarchie `implies`, flag `default`/`deprecated`) e gruppi/preset direttamente dalla UI, con persistenza automatica su **Database** (`sanctum_scopes` / `sanctum_scope_groups` se migrati) oppure **fallback su file JSON** (`storage/app/sanctum-manager/scopes.json`) unito alla configurazione.
- **Audit Log**: visualizzazione dei log d'uso (in modalità Extended Migrations).
- **Mail & Access**: riepilogo stato notifiche, Gate e invio di una **email di test** direttamente dalla UI.
- **Localizzazione EN / IT**: tutta la dashboard, i messaggi flash e le email sono tradotti in **Inglese** e **Italiano** (si adattano automaticamente a `APP_LOCALE=it` / `en`, personalizzabili pubblicando `--tag=sanctum-manager-lang`).

#### Controllo Accessi tramite Laravel Gate

L'accesso alla dashboard è protetto dal middleware `sanctum.dashboard` tramite il Gate **`viewSanctumManager`** (stile Laravel Horizon / Telescope):

1. **Via `.env` / configurazione** (per ambienti non locali):
   ```env
   SANCTUM_MANAGER_ALLOWED_EMAILS=admin@example.com,security@example.com
   ```
2. **Via Gate personalizzato** in `App\Providers\AppServiceProvider::boot()`:
   ```php
   use Illuminate\Support\Facades\Gate;

   Gate::define('viewSanctumManager', function ($user): bool {
       return in_array($user->email, [
           'admin@example.com',
       ], true) || $user->isAdmin();
   });
   ```

> In ambiente `local` l'accesso è consentito automaticamente se `SANCTUM_MANAGER_ALLOW_IN_LOCAL=true`; in produzione ogni utente non autorizzato dal Gate riceve `403 Forbidden`.

---

### 7. Notifiche Email (Emissione, Revoca, Rotazione)

Abilitando `SANCTUM_MANAGER_MAIL_ENABLED=true`, il pacchetto invia automaticamente email HTML responsive (personalizzabili pubblicando `--tag=sanctum-manager-views`) quando:

- viene **emesso** un nuovo token (`TokenIssuedMail` — include nome, scope, scadenza, IP/Origin; **non include mai** il token in chiaro),
- viene **revocato** un token (`TokenRevokedMail` — include nome e motivazione della revoca),
- viene **ruotato** un token (`TokenRotatedMail` — include nome vecchio/nuovo e scadenza del grace period).

Le email vengono inviate al proprietario del token (`notify_token_owner: true`) e/o agli indirizzi amministrativi configurati in `mail.recipients` (`SANCTUM_MANAGER_MAIL_RECIPIENTS`), con supporto opzionale all'invio in coda (`SANCTUM_MANAGER_MAIL_QUEUE=true`).

---

### 8. Eventi

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
