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
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Spipu\DashboardBundle\Exception\SourceException;

class DoctrineDql extends AbstractDataProvider
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getValue(): float
    {
        $queryBuilder = $this->prepareQueryBuilder();

        return (float) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function getPreviousValue(): float
    {
        $queryBuilder = $this->prepareQueryBuilder();

        list($dateFrom, $dateTo) = $this->getPreviousPeriodDate();

        $queryBuilder->setParameter('from', $dateFrom);
        $queryBuilder->setParameter('to', $dateTo);

        return (float) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array
     * @throws SourceException
     */
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

        $dateField = $this->getDqlFieldName($dateField);
        $formattedDate = $dateFrom->format('Y-m-d H:i:s');
        $dateExpression = "FLOOR(TIMESTAMPDIFF(SECOND, '" . $formattedDate . "', " . $dateField . ") / $timeStep)";

        $queryBuilder = $this->prepareQueryBuilder();
        $queryBuilder->addSelect($dateExpression . ' AS t');
        $queryBuilder->groupBy('t');
        $queryBuilder->orderBy('t', 'ASC');

        $rows = $queryBuilder->getQuery()->getArrayResult();
        foreach ($rows as $row) {
            if (isset($values[$row['t']])) {
                $values[$row['t']]['v'] = (float) $row['v'];
            }
        }

        return $values;
    }

    protected function prepareQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $queryBuilder
            ->select($this->definition->getValueExpression() . ' AS v')
            ->from($this->definition->getEntityName(), 'main');

        $where = $queryBuilder->expr()->andX();
        foreach ($this->definition->getConditions() as $condition) {
            $where->add($condition);
        }

        $parameters = [];
        $parameters += $this->prepareQueryBuilderPeriod($where);

        foreach ($this->getFilters() as $code => $value) {
            $parameters += $this->prepareQueryBuilderFilter($queryBuilder, $where, $code, $value);
        }

        if (count($parameters) > 0 || count($this->definition->getConditions()) > 0) {
            $queryBuilder->where($where);
            foreach ($parameters as $key => $value) {
                $queryBuilder->setParameter($key, $value);
            }
        }

        return $queryBuilder;
    }

    protected function prepareQueryBuilderPeriod(
        Andx $where
    ): array {
        $parameters = [];
        if ($this->definition->getDateField() !== null) {
            $dateField = $this->definition->getDateField();
            $period = $this->request->getPeriod();
            $where->add($this->getDqlFieldName($dateField) . ' >= :from');
            $where->add($this->getDqlFieldName($dateField) . ' < :to');
            $parameters['from'] = $period->getDateFrom();
            $parameters['to'] = $period->getDateTo();
        }

        return $parameters;
    }

    private function prepareQueryBuilderFilter(
        QueryBuilder $queryBuilder,
        Andx $where,
        string $code,
        $value
    ): array {
        $parameters = [];
        $filter = $this->definition->getFilter($code);
        $entityField = $this->getDqlFieldName($filter->getEntityField());

        if ($filter->isMultiple()) {
            $expression = $queryBuilder->expr()->in($entityField, ':' . $code);
            $where->add($expression);
            $parameters[':' . $code] = $value;
            return $parameters;
        }
        $expression = $queryBuilder->expr()->eq($entityField, ':' . $code);
        $where->add($expression);
        $parameters[':' . $code] = $value;
        return $parameters;
    }


    protected function getDqlFieldName(string $field): string
    {
        $prefix = '';
        if (!str_contains($field, '.')) {
            $prefix = 'main.';
        }

        return $prefix . $field;
    }
}
