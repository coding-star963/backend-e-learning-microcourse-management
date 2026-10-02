# Backend — Laravel REST API

REST API for the E-Learning & Micro-Course Management System, used by the React web portal (`../frontend`) and the Flutter student app (`../mobile`).

See the [project README](../README.md) for setup, demo accounts, email and upload settings.

## Quick reference

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
php artisan test
```

- Routes: `routes/api.php` (all under `/api`, protected routes use `auth:sanctum` + `active`; role checks use `role:administrator,teacher`).
- Controllers: `app/Http/Controllers/<Module>/`; ownership rules live on the models (`Course::isManageableBy`, `Course::isVisibleTo`, `Enrollment::isVisibleTo`, `Announcement::scopeVisibleTo`).
- `GET /api/dashboard` returns the admin, teacher or student dashboard depending on the signed-in role.
