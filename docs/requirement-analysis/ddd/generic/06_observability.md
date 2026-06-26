# BC: Observability

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 1.0  
**Status:** Draft

---

## Responsibility

Logging, metrics, error tracking, dan uptime monitoring untuk seluruh environment. Semua open source dan self-hosted — kecuali Grafana Cloud free tier untuk staging.

---

## Stack per Environment

### Development — Laravel Telescope

Zero config, zero external service. Covers: HTTP requests, SQL queries, queue jobs, cache, exceptions, scheduled commands, log viewer.

```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

---

### Staging — Grafana Cloud Free Tier

Tidak perlu manage infra sendiri.

| Komponen                  | Batas Free  |
| ------------------------- | ----------- |
| Loki (logs)               | 50 GB/bulan |
| Prometheus (metrics)      | 10k series  |
| Grafana dashboard         | Unlimited   |
| Alerting (email, webhook) | Unlimited   |

---

### Production — Self-Hosted (1–2 GB VPS)

```
VictoriaMetrics   ← metrics (3x lebih ringan dari Prometheus, drop-in compatible)
Loki + Promtail   ← log aggregation (Promtail baca storage/logs/*.log)
Grafana           ← dashboard + alerting (Telegram, email)
Uptime Kuma       ← uptime monitoring (~50 MB RAM)
GlitchTip         ← self-hosted Sentry (compatible dengan sentry/sentry-laravel)
```

**Estimasi resource:**

| Komponen        | RAM         |
| --------------- | ----------- |
| VictoriaMetrics | ~150 MB     |
| Loki            | ~200 MB     |
| Grafana         | ~150 MB     |
| Uptime Kuma     | ~50 MB      |
| GlitchTip       | ~300 MB     |
| **Total**       | **~850 MB** |

---

## Integrasi Laravel

```bash
composer require sentry/sentry-laravel
composer require spatie/laravel-activitylog
```

**Health check endpoint:**

```php
Route::get('/health', fn() => response()->json([
    'status' => 'ok',
    'db'     => DB::connection()->getPdo() ? 'ok' : 'error',
    'cache'  => Cache::store()->put('health_check', 1, 10) ? 'ok' : 'error',
]));
```

**Log config production:**

```php
// LOG_LEVEL=warning di prod, LOG_LEVEL=debug di staging
// Promtail baca dari storage/logs/laravel-*.log → kirim ke Loki
```

**Activity log untuk audit trail:**

```php
activity('submission')->performedOn($submission)->causedBy($user)->log('submitted');
activity('review')->performedOn($summary)->causedBy($reviewer)->log('revision_requested');
```

---

## Alert Rules

| Alert         | Kondisi                                | Channel  |
| ------------- | -------------------------------------- | -------- |
| App down      | `/health` unreachable > 2 menit        | Telegram |
| Error spike   | > 10 error baru / 5 menit di GlitchTip | Telegram |
| Queue backlog | Failed jobs > 50                       | Telegram |
| Disk full     | Disk usage > 85%                       | Telegram |
| Slow queries  | Query time p95 > 1s                    | Email    |
