# Creating Dashboard Widgets

[back](./README.md)

## Overview

A dashboard widget (called a "source") is defined by two interfaces:

- **`SourceDefinitionInterface`** — describes the widget: provides a `Source` definition object (code, entity, data provider, filters) and lists the roles required to see it
- **`SourceDataDefinitionInterface`** — provides the actual data when the bundle cannot compute it automatically (required only for `SourceFromDefinition`-based widgets)

## Source Types

The bundle provides three concrete `Source` subclasses to choose from:

| Class | Data provider | Use case |
|-------|--------------|---------|
| `SourceSql` | Doctrine DBAL (raw SQL) | Count/sum on any DB table |
| `SourceDql` | Doctrine ORM (DQL) | Count/sum on a mapped entity |
| `SourceFromDefinition` | Custom PHP class | Any data source (API, file, complex query…) |

## Creating a SQL/DQL Widget

Implement `SourceDefinitionInterface` and return a `SourceSql` or `SourceDql` from `getDefinition()`:

```php
namespace App\WidgetSource;

use Spipu\DashboardBundle\Entity\Source\SourceSql;
use Spipu\DashboardBundle\Source\SourceDefinitionInterface;

class OrderCountWidget implements SourceDefinitionInterface
{
    public function getDefinition(): SourceSql
    {
        return (new SourceSql('order-count', 'my_orders_table'))
            ->setDateField('created_at')           // column used for period filtering
            ->setValueExpression('COUNT(main.id)') // SQL expression (default for SourceSql)
            ->setSuffix(' orders');                // displayed after the number
    }

    public function getRolesNeeded(): array
    {
        return ['ROLE_ADMIN']; // empty array = accessible to all admin users
    }
}
```

For a DQL-backed widget, use `SourceDql` instead — it uses a Doctrine entity class as the second argument:

```php
use Spipu\DashboardBundle\Entity\Source\SourceDql;

return (new SourceDql('user-count', \App\Entity\User::class))
    ->setDateField('createdAt');
```

## Creating a Custom Widget (SourceFromDefinition)

When automatic SQL/DQL aggregation is not sufficient, implement both interfaces and use `SourceFromDefinition`:

```php
namespace App\WidgetSource;

use Spipu\DashboardBundle\Entity\Source\SourceFromDefinition;
use Spipu\DashboardBundle\Service\Ui\WidgetRequest;
use Spipu\DashboardBundle\Source\SourceDataDefinitionInterface;
use Spipu\DashboardBundle\Source\SourceDefinitionInterface;

class RevenueWidget implements SourceDefinitionInterface, SourceDataDefinitionInterface
{
    public function __construct(private OrderRepository $orders) {}

    public function getDefinition(): SourceFromDefinition
    {
        return new SourceFromDefinition('revenue', $this);
    }

    public function getRolesNeeded(): array
    {
        return [];
    }

    // SourceDataDefinitionInterface

    public function getValue(WidgetRequest $request): float
    {
        $period = $request->getPeriod();
        return $this->orders->sumRevenueBetween($period->getDateFrom(), $period->getDateTo());
    }

    public function getPreviousValue(WidgetRequest $request): float
    {
        // Return value for the previous equivalent period (used by value_compare widget type)
        return 0.;
    }

    public function getValues(WidgetRequest $request): array
    {
        // Return time-series data for graph widget type: [timestamp => value, ...]
        return [];
    }

    public function getSpecificValues(WidgetRequest $request): array
    {
        // Return arbitrary data for specific/custom widget type
        return [];
    }
}
```

## Registering the Widget

Tag the service with `spipu.widget.source`:

```yaml
# config/services.yaml
App\WidgetSource\:
    resource: '../src/WidgetSource/'
    tags:
        - { name: spipu.widget.source }
```

## Available Period Types

The available periods are defined as constants on `PeriodService`:

| Constant | Value | Description |
|----------|-------|-------------|
| `PeriodService::PERIOD_HOUR` | `'hour'` | Last hour |
| `PeriodService::PERIOD_DAY_CURRENT` | `'day-current'` | Today (from midnight to now) |
| `PeriodService::PERIOD_DAY_FULL` | `'day-full'` | Yesterday (full day) |
| `PeriodService::PERIOD_WEEK` | `'week'` | Last 7 days |
| `PeriodService::PERIOD_MONTH` | `'month'` | Last 30 days |
| `PeriodService::PERIOD_YEAR` | `'year'` | Last 12 months |
| `PeriodService::PERIOD_CUSTOM` | `'custom'` | Custom date range |

## Period Object

The `Period` object (available via `$request->getPeriod()`) provides:

| Method | Returns | Description |
|--------|---------|-------------|
| `getDateFrom()` | `DateTimeInterface` | Start of period |
| `getDateTo()` | `DateTimeInterface` | End of period (exclusive) |
| `getDateToReal()` | `DateTimeInterface` | End of period minus one step (inclusive) |
| `getType()` | `string` | One of the `PERIOD_*` values |
| `getStep()` | `int` | Duration of one graph step in seconds |

## WidgetRequest

The `WidgetRequest` object passed to `SourceDataDefinitionInterface` methods provides:

| Method | Description |
|--------|-------------|
| `getPeriod(): ?Period` | The selected period |
| `getFilters(): array` | All filter values keyed by filter code |
| `getFilterValueString(string $key): string` | Single filter value as string |
| `getFilterValueArray(string $key): array` | Filter value as array (for multi-select) |

## Widget Filters

To add filters, call `addFilter()` on the `Source` with a `SourceFilter` object:

```php
use Spipu\DashboardBundle\Entity\Source\SourceFilter;
use App\Form\Options\OrderStatusOptions;

return (new SourceSql('orders', 'my_orders'))
    ->setDateField('created_at')
    ->addFilter(
        new SourceFilter(
            'status',            // filter code
            'order.label.status',// translation key for the label
            'status',            // entity/table field for automatic SQL/DQL filtering
            new OrderStatusOptions(), // OptionsInterface providing the choices
            true                 // translate option labels (default: false)
        )
    );
```

The selected filter values are available in `WidgetRequest::getFilters()` or `getFilterValueString()`.

For `SourceFromDefinition` widgets, automatic SQL filtering does not apply — read `$request->getFilters()` manually in your `getValue()` / `getSpecificValues()` implementations.

## Source Configuration Reference

Key methods available on all `Source` subclasses:

| Method | Description |
|--------|-------------|
| `setDateField(?string)` | Column/field used for period date range filtering (`null` = no period) |
| `setValueExpression(string)` | SQL/DQL expression to aggregate (e.g. `COUNT(main.id)`, `SUM(main.amount)`) |
| `setSuffix(string)` | Text appended after the displayed number |
| `setType(string)` | Value type: `SourceDefinitionInterface::TYPE_INT` or `TYPE_FLOAT` |
| `setLowerBetter(bool)` | If `true`, a lower value is displayed as better (inverts color coding) |
| `addFilter(SourceFilter)` | Add a filter to the widget |
| `setSpecificDisplay(string $icon, string $template)` | Enable specific display with a custom Twig template |
| `setConditions(array)` | SQL WHERE conditions applied before aggregation |

[back](./README.md)