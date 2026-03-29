# Installing Spipu Dashboard Bundle

[back](./README.md)

## Requirements

- PHP 8.1+
- Symfony 6.4+
- `spipu/core-bundle`
- `spipu/ui-bundle`

## Installation

```bash
composer require spipu/dashboard-bundle
```

## Configuration

### 1. Register the bundle

In `config/bundles.php`:

```php
return [
    // ...
    Spipu\CoreBundle\SpipuCoreBundle::class => ['all' => true],
    Spipu\UiBundle\SpipuUiBundle::class => ['all' => true],
    Spipu\DashboardBundle\SpipuDashboardBundle::class => ['all' => true],
];
```

### 2. Import routes

In `config/routes.yaml`:

```yaml
spipu_dashboard:
    resource: '@SpipuDashboardBundle/config/routes.yaml'
```

### 3. Install assets

```bash
php bin/console spipu:assets:install
```

### 4. Register your widget sources

Tag services that implement both `SourceDefinitionInterface` and `SourceDataDefinitionInterface` with `spipu.dashboard.source`:

```yaml
# config/services.yaml
App\WidgetSource\:
    resource: '../src/WidgetSource/'
    tags:
        - { name: spipu.dashboard.source }
```

## Admin UI

The dashboard is available at `/admin/dashboard/`. It displays all registered and enabled widget sources.

Access requires role `ROLE_ADMIN` (or a more specific role if configured in your security layer).

[back](./README.md)
