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
    /**
     * @var string
     */
    private string $code;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $entityField;

    /**
     * @var OptionsInterface
     */
    private OptionsInterface $options;

    /**
     * @var bool
     */
    private bool $multiple = false;

    /**
     * @var bool
     */
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

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getEntityField(): string
    {
        return $this->entityField;
    }

    /**
     * @return OptionsInterface
     */
    public function getOptions(): OptionsInterface
    {
        return $this->options;
    }

    /**
     * @return bool
     */
    public function isTranslate(): bool
    {
        return $this->translate;
    }

    /**
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @param bool $multiple
     * @return SourceFilter
     */
    public function setMultiple(bool $multiple): SourceFilter
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @param mixed $inputValue
     * @param mixed $value
     * @return bool
     */
    public function isSelected($inputValue, $value): bool
    {
        if (is_array($inputValue) && in_array($value, $inputValue)) {
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
