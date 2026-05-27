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

namespace Spipu\DashboardBundle\Service\Ui\Source\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Spipu\CoreBundle\Exception\ConnectionQuoterException;
use Spipu\CoreBundle\Service\ConnectionQuoterFactoryInterface;
use Spipu\CoreBundle\Service\ConnectionQuoterInterface;
use Spipu\DashboardBundle\Exception\SourceException;

class DoctrineSql extends AbstractDataProvider
{
    protected EntityManagerInterface $entityManager;
    protected ConnectionQuoterFactoryInterface $quoterFactory;
    private ?ConnectionQuoterInterface $quoter = null;

    public function __construct(
        EntityManagerInterface $entityManager,
        ConnectionQuoterFactoryInterface $quoterFactory
    ) {
        $this->entityManager = $entityManager;
        $this->quoterFactory = $quoterFactory;
    }

    private function getQuoter(): ConnectionQuoterInterface
    {
        if ($this->quoter === null) {
            $this->quoter = $this->quoterFactory->create($this->entityManager->getConnection());
        }
        return $this->quoter;
    }

    public function getValue(): float
    {
        $query = $this->prepareQuery();
        $rows = $this->executeQuery($query);

        if (!array_key_exists(0, $rows) || !array_key_exists('v', $rows[0])) {
            throw new NoResultException();
        }
        if (count($rows) !== 1) {
            throw new NonUniqueResultException();
        }

        return (float) $rows[0]['v'];
    }

    public function getPreviousValue(): float
    {
        list($dateFrom, $dateTo) = $this->getPreviousPeriodDate();

        $query = $this->prepareQuery($dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'));
        $rows = $this->executeQuery($query);

        if (!array_key_exists(0, $rows) || !array_key_exists('v', $rows[0])) {
            throw new NoResultException();
        }
        if (count($rows) !== 1) {
            throw new NonUniqueResultException();
        }

        return (float) $rows[0]['v'];
    }

    public function getValues(): array
    {
        $dateField = $this->definition->getDateField();
        if ($dateField === null) {
            throw new SourceException('The dateField can\'t be null');
        }
        $period = $this->request->getPeriod();
        $dateFrom = $period->getDateFrom();
        $dateTo = $period->getDateTo();

        $timeFrom = $dateFrom->getTimestamp();
        $timeTo = $dateTo->getTimestamp();
        $timeStep = $period->getStep();

        $values = [];
        for ($time = $timeFrom; $time < $timeTo; $time += $timeStep) {
            $values[] = [
                't' => $time,
                'd' => date('Y-m-d H:i:s', $time),
                'v' => null,
            ];
        }

        $dateField = $this->getSqlFieldName($dateField);
        $formattedDate = $dateFrom->format('Y-m-d H:i:s');
        $dateExpression = "FLOOR(TIMESTAMPDIFF(SECOND, '" . $formattedDate . "', " . $dateField . ") / $timeStep)";

        $query = $this->prepareQuery(null, null, $dateExpression) . " GROUP BY t ORDER BY t ASC ";

        $rows = $this->executeQuery($query);
        foreach ($rows as $row) {
            if (isset($values[$row['t']])) {
                $values[$row['t']]['v'] = (float) $row['v'];
            }
        }

        return $values;
    }

    protected function prepareQuery(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $dateExpression = null
    ): string {
        $tableName = $this->getQuoter()->quoteIdentifier($this->definition->getEntityName());
        $conditions = $this->definition->getConditions();

        $conditions = array_merge($conditions, $this->prepareQueryConditionPeriod($dateFrom, $dateTo));

        foreach ($this->getFilters() as $code => $value) {
            $conditions[] = $this->prepareQueryConditionFilter($code, $value);
        }

        $select = [];
        $select[] = $this->definition->getValueExpression() . ' AS `v`';
        if ($dateExpression !== null) {
            $select[] = $dateExpression . ' AS `t`';
        }

        $query = 'SELECT ' . implode(',', $select) . " FROM $tableName AS `main`";
        if (count($conditions) > 0) {
            $query .= ' WHERE (' . implode(') AND (', $conditions) . ')';
        }

        return $query;
    }

    protected function prepareQueryConditionPeriod(
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if ($this->definition->getDateField() === null) {
            return [];
        }

        $connection = $this->entityManager->getConnection();

        $dateField = $this->getSqlFieldName($this->definition->getDateField());
        $period    = $this->request->getPeriod();
        $dateFrom  = $connection->quote($dateFrom ?? $period->getDateFrom()->format('Y-m-d H:i:s'));
        $dateTo    = $connection->quote($dateTo ?? $period->getDateTo()->format('Y-m-d H:i:s'));

        $conditions = [];
        $conditions[] = "$dateField >= $dateFrom";
        $conditions[] = "$dateField < $dateTo";

        return $conditions;
    }

    protected function prepareQueryConditionFilter(
        string $code,
        mixed $value
    ): string {
        $filter = $this->definition->getFilter($code);
        $entityField = $this->getSqlFieldName($filter->getEntityField());

        if ($filter->isMultiple() && !is_array($value)) {
            $value = [$value];
        }

        $closure = $filter->getSpecificSqlQueryFilterClosure();
        if ($closure) {
            $quoteFn = fn($rawValue) => $this->quoteValue($rawValue);
            return $closure($quoteFn, $filter, $entityField, $value);
        }

        $operator = ($filter->isMultiple() ? ' IN ' : ' = ');
        return $entityField . $operator . $this->quoteValue($value);
    }

    protected function executeQuery(string $query): array
    {
        return $this->entityManager->getConnection()->executeQuery($query)->fetchAllAssociative();
    }

    /**
     * @throws ConnectionQuoterException
     */
    protected function quoteValue(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                throw new ConnectionQuoterException('Cannot quote an empty list of values');
            }
            return '(' . implode(
                ',',
                array_map(fn(mixed $subValue): string => $this->quoteValue($subValue), $value)
            ) . ')';
        }

        return $this->getQuoter()->quoteValue($value);
    }

    protected function getSqlFieldName(string $field): string
    {
        $prefix = '';
        if (!str_contains($field, '.')) {
            $prefix = 'main.';
        }

        return $prefix . $field;
    }
}
