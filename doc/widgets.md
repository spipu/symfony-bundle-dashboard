# Creating Dashboard Widgets

[back](./README.md)

## Overview

A dashboard widget (called a "source") is defined by two interfaces:

- **`SourceDefinitionInterface`** — describes the widget: its code, label, template, and what filters and periods it supports
- **`SourceDataDefinitionInterface`** — provides the actual data for a given period and set of filter values

Both must be implemented by the same class (or you can use the abstract `Source` base class).

## Creating a Widget

```php
namespace App\WidgetSource;

use Spipu\DashboardBundle\Source\SourceDefinitionInterface;
use Spipu\DashboardBundle\Source\SourceDataDefinitionInterface;
use Spipu\DashboardBundle\Entity\Period;
use Spipu\DashboardBundle\Entity\SourceFilter;

class OrdersWidget implements SourceDefinitionInterface, SourceDataDefinitionInterface
{
    public function __construct(private OrderRepository $orderRepository) {}

    // SourceDefinitionInterface

    public function getCode(): string
    {
        return 'orders';
    }

    public function getName(): string
    {
        return 'dashboard.widget.orders';  // Translation key
    }

    public function getTemplate(): string
    {
        return '@App/widget/orders.html.twig';
    }

    public function getWeight(): int
    {
        return 10;  // Lower = appears first
    }

    public function getPeriodTypes(): array
    {
        // Periods this widget supports
        return [
            Period::TYPE_TODAY,
            Period::TYPE_YESTERDAY,
            Period::TYPE_LAST_7_DAYS,
            Period::TYPE_LAST_30_DAYS,
            Period::TYPE_THIS_MONTH,
            Period::TYPE_LAST_MONTH,
        ];
    }

    public function getFilters(): array
    {
        return [];  // No filters for this widget
    }

    // SourceDataDefinitionInterface

    public function getData(Period $period, array $filters): array
    {
        $from = $period->getFrom();
        $to   = $period->getTo();

        $total  = $this->orderRepository->countBetween($from, $to);
        $amount = $this->orderRepository->sumAmountBetween($from, $to);

        return [
            'total'  => $total,
            'amount' => $amount,
        ];
    }
}
```

## Registering the Widget

Tag the service with `spipu.dashboard.source`:

```yaml
App\WidgetSource\OrdersWidget:
    tags:
        - { name: spipu.dashboard.source }
```

## Widget Template

Create a Twig template to render the widget data:

```twig
{# templates/widget/orders.html.twig #}
<div class="card">
    <div class="card-header">Orders — {{ period.label }}</div>
    <div class="card-body">
        <p>Total orders: <strong>{{ data.total }}</strong></p>
        <p>Revenue: <strong>{{ data.amount|number_format(2) }} €</strong></p>
    </div>
</div>
```

## Available Period Types

| Constant | Label |
|----------|-------|
| `Period::TYPE_TODAY` | Today |
| `Period::TYPE_YESTERDAY` | Yesterday |
| `Period::TYPE_LAST_7_DAYS` | Last 7 days |
| `Period::TYPE_LAST_30_DAYS` | Last 30 days |
| `Period::TYPE_THIS_MONTH` | This month |
| `Period::TYPE_LAST_MONTH` | Last month |
| `Period::TYPE_THIS_YEAR` | This year |
| `Period::TYPE_LAST_YEAR` | Last year |

## Period Object

The `Period` object passed to `getData()` provides:

| Method | Returns |
|--------|---------|
| `$period->getFrom()` | `DateTimeInterface` — start of period |
| `$period->getTo()` | `DateTimeInterface` — end of period |
| `$period->getType()` | `string` — one of the `TYPE_*` constants |
| `$period->getLabel()` | `string` — human-readable label |

## Widget Filters

To add filters, implement `getFilters()` returning an array of `SourceFilter` objects:

```php
use Spipu\DashboardBundle\Entity\SourceFilter;

public function getFilters(): array
{
    return [
        new SourceFilter(
            code: 'status',
            label: 'dashboard.filter.status',
            type: SourceFilter::TYPE_SELECT,
            options: ['pending', 'paid', 'shipped', 'cancelled']
        ),
    ];
}
```

The selected filter values are passed as the `$filters` array to `getData(array $filters)`.

[back](./README.md)
