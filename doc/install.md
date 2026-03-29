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

### 2. Create a dashboard controller

The bundle does not provide its own routes. The application must create a controller that delegates to `DashboardControllerService`:

```php
namespace App\Controller;

use App\Ui\AdminDashboard; // your DashboardDefinitionInterface implementation
use Spipu\DashboardBundle\Entity\DashboardAcl;
use Spipu\DashboardBundle\Service\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route(path: '/dashboard/{action}/{id?}', name: 'app_dashboard')]
    public function main(
        DashboardControllerService $service,
        AdminDashboard $dashboard,
        string $action = '',
        ?int $id = null
    ): Response {
        $acl = (new DashboardAcl())->configure(
            canSelect:    $this->isGranted('ROLE_ADMIN'),
            canCreate:    $this->isGranted('ROLE_ADMIN'),
            canConfigure: $this->isGranted('ROLE_ADMIN'),
            canDelete:    $this->isGranted('ROLE_ADMIN'),
        );

        return $service->dispatch($dashboard, 'app_dashboard', $action, $id, $acl);
    }
}
```

`AdminDashboard` must implement `DashboardDefinitionInterface` (see [Creating Widgets](./widgets.md)).

### 3. Install assets

```bash
php bin/console spipu:assets:install
```

### 4. Register your widget sources

Tag services that implement `SourceDefinitionInterface` with `spipu.widget.source`:

```yaml
# config/services.yaml
App\WidgetSource\:
    resource: '../src/WidgetSource/'
    tags:
        - { name: spipu.widget.source }
```

## Admin UI

The dashboard is available at `/admin/dashboard/`. It displays all registered and enabled widget sources.

Access requires role `ROLE_ADMIN` (or a more specific role if configured in your security layer).

[back](./README.md)
