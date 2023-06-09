<?php

/**
 * This file is part of a Spipu Bundle
 *
 * (c) Laurent Minguet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spipu\DashboardBundle\Service;

use Spipu\DashboardBundle\Entity\Dashboard\Column;
use Spipu\DashboardBundle\Entity\Dashboard\Row;
use Spipu\DashboardBundle\Entity\Dashboard\Screen;
use Spipu\DashboardBundle\Entity\DashboardInterface;
use Spipu\DashboardBundle\Exception\WidgetException;

class DashboardViewerService
{
    private WidgetService $widgetService;

    public function __construct(
        WidgetService $widgetService
    ) {
        $this->widgetService = $widgetService;
    }

    public function buildScreen(DashboardInterface $dashboard): Screen
    {
        $definition = $dashboard->getContent();

        try {
            $screen = new Screen();
            foreach ($definition['rows'] as $definitionRow) {
                $this->buildRow($screen, $definitionRow);
            }
        } catch (WidgetException $e) {
            $screen = new Screen();
        }

        return $screen;
    }

    private function buildRow(Screen $screen, array $definitionRow): void
    {
        $row = $screen->addRow(
            $definitionRow['title'],
            $definitionRow['nbCol']
        );

        foreach ($definitionRow['cols'] as $definitionCol) {
            $this->buildCol($row, $definitionCol['widgets']);
        }
    }

    private function buildCol(Row $row, array $widgets): void
    {
        $width = $widgets[0]['width'] ?? 0;
        $col = $row->addCol($width);
        foreach ($widgets as $definitionWidget) {
            $this->buildWidget($col, $definitionWidget);
        }
    }

    private function buildWidget(Column $col, array $definitionWidget): void
    {
        $widget = $this->widgetService->buildWidget($definitionWidget);
        if ($widget) {
            $col->addWidget($widget);
        }
    }
}
