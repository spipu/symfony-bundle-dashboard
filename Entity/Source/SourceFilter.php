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

use Closure;
use Spipu\UiBundle\Form\Options\OptionsInterface;

class SourceFilter
{
    private string $code;
    private string $name;
    private string $entityField;
    private OptionsInterface $options;
    private bool $multiple = false;
    private bool $translate;

    /**
     * @var Closure|null
     */
    private ?Closure $specificSqlQueryFilterClosure = null;

    /**
     * @var Closure|null
     */
    private ?Closure $specificDqlQueryFilterClosure = null;

    /**
     * @param string $code
     * @param string $name
     * @param string $entityField
     * @param OptionsInterface $options
     * @param bool $translate
     * @SuppressWarnings(PMD.BooleanArgumentFlag)
     */
    public function __construct(
        string $code,
        string $name,
        string $entityField,
        OptionsInterface $options,
        bool $translate = false
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->entityField = $entityField;
        $this->options = $options;
        $this->translate = $translate;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEntityField(): string
    {
        return $this->entityField;
    }

    public function getOptions(): OptionsInterface
    {
        return $this->options;
    }

    public function isTranslate(): bool
    {
        return $this->translate;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function setMultiple(bool $multiple): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isSelected($inputValue, $value): bool
    {
        if (is_array($inputValue) && in_array($value, $inputValue, true)) {
            return true;
        }
        if (is_string($inputValue) && $inputValue === $value) {
            return true;
        }

        return false;
    }

    /**
     * @return Closure|null
     */
    public function getSpecificSqlQueryFilterClosure(): ?Closure
    {
        return $this->specificSqlQueryFilterClosure;
    }

    /**
     * Format: function(callable $quote, SourceFilter $filter, string $entityField, $value): string
     * @param Closure $specificSqlQueryFilterClosure
     * @return $this
     */
    public function setSpecificSqlQueryFilterClosure(Closure $specificSqlQueryFilterClosure): SourceFilter
    {
        $this->specificSqlQueryFilterClosure = $specificSqlQueryFilterClosure;
        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getSpecificDqlQueryFilterClosure(): ?Closure
    {
        return $this->specificDqlQueryFilterClosure;
    }

    /**
     * Format: function(QueryBuilder $qb, Expr\Andx $where, SourceFilter $filter, string $entityField, $value): array
     * @param Closure $specificDqlQueryFilterClosure
     * @return $this
     */
    public function setSpecificDqlQueryFilterClosure(Closure $specificDqlQueryFilterClosure): SourceFilter
    {
        $this->specificDqlQueryFilterClosure = $specificDqlQueryFilterClosure;
        return $this;
    }
}
