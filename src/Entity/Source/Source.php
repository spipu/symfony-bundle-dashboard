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

namespace Spipu\DashboardBundle\Entity\Source;

use Spipu\DashboardBundle\Source\SourceDefinitionInterface;

abstract class Source
{
    private string $code;
    private ?string $entityName;
    private string $dataProviderServiceName;
    private string $type = SourceDefinitionInterface::TYPE_INT;
    private string $suffix = '';
    private ?string $dateField;
    private string $valueExpression;
    private bool $lowerBetter = false;
    private ?string $specificDisplayIcon = null;
    private ?string $specificDisplayTemplate = null;
    private bool $donutDisplay = false;

    /**
     * @var string[]
     */
    private array $conditions = [];

    /**
     * @var SourceFilter[]
     */
    private array $filters = [];

    public function __construct(string $code, ?string $entityName = null)
    {
        $this->code = $code;
        $this->entityName = $entityName;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): self
    {
        $this->entityName = $entityName;

        return $this;
    }

    public function getDataProviderServiceName(): string
    {
        return $this->dataProviderServiceName;
    }

    protected function setDataProviderServiceName(string $dataProviderServiceName): self
    {
        $this->dataProviderServiceName = $dataProviderServiceName;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function setSuffix(string $suffix): self
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getDateField(): ?string
    {
        return $this->dateField;
    }

    public function setDateField(?string $dateField): self
    {
        $this->dateField = $dateField;

        return $this;
    }

    public function getValueExpression(): string
    {
        return $this->valueExpression;
    }

    public function setValueExpression(string $valueExpression): self
    {
        $this->valueExpression = $valueExpression;

        return $this;
    }

    public function isLowerBetter(): bool
    {
        return $this->lowerBetter;
    }

    public function setLowerBetter(bool $lowerBetter): self
    {
        $this->lowerBetter = $lowerBetter;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @param string[] $conditions
     * @return $this
     */
    public function setConditions(array $conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function addCondition(string $condition): self
    {
        $this->conditions[] = $condition;

        return $this;
    }

    /**
     * @return SourceFilter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function addFilter(SourceFilter $filter): self
    {
        $this->filters[$filter->getCode()] = $filter;

        return $this;
    }

    public function removeFilter(string $code): self
    {
        if (array_key_exists($code, $this->filters)) {
            unset($this->filters[$code]);
        }

        return $this;
    }

    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    public function getFilter(string $code): ?SourceFilter
    {
        return $this->filters[$code] ?? null;
    }

    public function setSpecificDisplay(string $icon, string $template): self
    {
        $this->specificDisplayIcon = $icon;
        $this->specificDisplayTemplate = $template;

        return $this;
    }

    public function hasSpecificDisplay(): bool
    {
        return $this->specificDisplayIcon !== null;
    }

    public function getSpecificDisplayIcon(): ?string
    {
        return $this->specificDisplayIcon;
    }

    public function getSpecificDisplayTemplate(): ?string
    {
        return $this->specificDisplayTemplate;
    }

    public function setDonutDisplay(): self
    {
        $this->donutDisplay = true;

        return $this;
    }

    public function hasDonutDisplay(): bool
    {
        return $this->donutDisplay;
    }

    public function needPeriod(): bool
    {
        return ($this->getDateField() !== null);
    }
}
