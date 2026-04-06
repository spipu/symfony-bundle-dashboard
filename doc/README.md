# Spipu Dashboard Bundle

The **DashboardBundle** provides a widget-based dashboard system. Each widget is a "source" that produces a chart or data display for a configurable time period. Widgets are arranged in a responsive grid on the admin dashboard.

## Documentation

- [Installation](./install.md)
- [Creating Widgets](./widgets.md)

## Features

- **Widget sources** — any PHP class can provide dashboard data by implementing two interfaces
- **Period selector** — built-in support for today, yesterday, last 7 days, last 30 days, this month, last month, this year, last year
- **Filters** — widgets can expose filter parameters (dropdowns, date pickers, etc.)
- **Admin UI** — dashboard available at `/admin/dashboard/`
- **Twig rendering** — widgets are rendered using their own Twig templates
- **AJAX refresh** — individual widgets can be refreshed without a full page reload

## Requirements

- PHP >= 8.3
- Symfony >= 7.4
- `spipu/core-bundle`
- `spipu/ui-bundle`

## Quick Start

```bash
composer require spipu/dashboard-bundle
```

See [Installation](./install.md), then [Creating Widgets](./widgets.md).
