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

namespace Spipu\DashboardBundle\Service\Ui;

use Spipu\DashboardBundle\Entity\Period;
use Spipu\DashboardBundle\Entity\Widget\Widget;
use Symfony\Component\HttpFoundation\RequestStack;

class WidgetRequest extends AbstractRequest
{
    public const KEY_FILTERS = 'fl';

    private Widget $definition;
    private ?Period $period = null;
    private array $filters = [];

    public function __construct(
        RequestStack $requestStack,
        Widget $definition
    ) {
        parent::__construct($requestStack);
        $this->definition = $definition;
    }

    public function prepare(): void
    {
        $this->setSessionPrefixKey('widget.' . $this->definition->getId());
        $this->prepareFilters();
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    private function prepareFilters(): void
    {
        $this->filters = $this->definition->getFilters();
        $this->filters = $this->getSessionValue('filters', $this->filters);
        if ($this->getCurrentRequest()->query->has(self::KEY_FILTERS)) {
            $this->filters = $this->getCurrentRequest()->query->all(self::KEY_FILTERS);
        }

        foreach ($this->filters as $key => $value) {
            $filter = $this->definition->getSource()->getFilter($key);
            if (!$filter) {
                unset($this->filters[$key]);
                continue;
            }

            if ($value === null) {
                unset($this->filters[$key]);
                continue;
            }

            if (is_array($value)) {
                if (!array_filter($value)) {
                    unset($this->filters[$key]);
                    continue;
                }
                $this->filters[$key] = $value;
                continue;
            }
            $this->filters[$key] = trim((string)$value);
            if ($this->filters[$key] === '') {
                unset($this->filters[$key]);
            }
        }

        $this->setSessionValue('filters', $this->filters);
    }

    public function getFilterValueString(string $key, ?string $subKey = null): string
    {
        if (!array_key_exists($key, $this->filters)) {
            return '';
        }

        if ($subKey === null) {
            $value = $this->filters[$key];
            if (!is_string($value)) {
                return '';
            }
            return $value;
        }

        if (!is_array($this->filters[$key])) {
            return '';
        }

        if (!array_key_exists($subKey, $this->filters[$key]) || !is_string($this->filters[$key][$subKey])) {
            return '';
        }

        return $this->filters[$key][$subKey];
    }

    public function getFilterValueArray(string $key, ?string $subKey = null): array
    {
        if (!array_key_exists($key, $this->filters)) {
            return [];
        }

        if ($subKey === null) {
            $value = $this->filters[$key];
            if (!is_array($value)) {
                return [];
            }
            return $value;
        }

        if (!is_array($this->filters[$key])) {
            return [];
        }

        if (!array_key_exists($subKey, $this->filters[$key]) || !is_array($this->filters[$key][$subKey])) {
            return [];
        }

        return $this->filters[$key][$subKey];
    }

    public function getPeriod(): ?Period
    {
        if (!$this->period) {
            return $this->definition->getPeriod();
        }

        return $this->period;
    }

    public function setPeriod(?Period $period): WidgetRequest
    {
        $this->period = $period;

        return $this;
    }
}
