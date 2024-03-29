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

use DateTime;
use Exception;
use Spipu\DashboardBundle\Entity\Period;
use Spipu\DashboardBundle\Exception\PeriodException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PeriodService
{
    public const PERIOD_HOUR        = 'hour';
    public const PERIOD_DAY_CURRENT = 'day-current';
    public const PERIOD_DAY_FULL    = 'day-full';
    public const PERIOD_WEEK        = 'week';
    public const PERIOD_MONTH       = 'month';
    public const PERIOD_YEAR        = 'year';
    public const PERIOD_CUSTOM      = 'custom';

    protected array $types = [
        self::PERIOD_HOUR,
        self::PERIOD_DAY_CURRENT,
        self::PERIOD_DAY_FULL,
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];

    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function getDefinitions(): array
    {
        $definition = [];

        foreach ($this->types as $type) {
            $definition[$type] = [
                'code' => $type,
                'label' => $this->translator->trans('spipu.dashboard.period.' . $type),
            ];
        }

        return $definition;
    }

    public function create(string $type, ?DateTime $dateFrom = null, ?DateTime $dateTo = null): Period
    {
        $period = new Period();
        $period->setType($type);

        try {
            switch ($type) {
                case self::PERIOD_YEAR:
                    return $this->preparePeriodYear($period);

                case self::PERIOD_MONTH:
                    return $this->preparePeriodMonth($period);

                case self::PERIOD_WEEK:
                    return $this->preparePeriodWeek($period);

                case self::PERIOD_DAY_FULL:
                    return $this->preparePeriodDayFull($period);

                case self::PERIOD_DAY_CURRENT:
                    return $this->preparePeriodDayCurrent($period);

                case self::PERIOD_HOUR:
                    return $this->preparePeriodHour($period);

                case self::PERIOD_CUSTOM:
                    return $this->preparePeriodCustom($period, $dateFrom, $dateTo);
            }
        } catch (Exception $e) {
            throw new PeriodException($e->getMessage());
        }

        throw new PeriodException('unknown period type code');
    }

    private function preparePeriodYear(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom((new DateTime(date('Y-m-d 00:00:00', $time)))->modify("-1 year"))
            ->setDateTo((new DateTime(date('Y-m-d 00:00:00', $time))))
            ->setStep(3600 * 24 * 365 / 24);

        return $period;
    }

    private function preparePeriodMonth(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom((new DateTime(date('Y-m-d 00:00:00', $time)))->modify("-1 month"))
            ->setDateTo((new DateTime(date('Y-m-d 00:00:00', $time))))
            ->setStep(3600 * 24);

        return $period;
    }

    private function preparePeriodWeek(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom((new DateTime(date('Y-m-d 00:00:00', $time)))->modify("-1 week"))
            ->setDateTo((new DateTime(date('Y-m-d 00:00:00', $time))))
            ->setStep(3600 * 24);

        return $period;
    }

    private function preparePeriodDayFull(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom((new DateTime(date('Y-m-d 00:00:00', $time)))->modify("-1 day"))
            ->setDateTo((new DateTime(date('Y-m-d 00:00:00', $time))))
            ->setStep(3600);

        return $period;
    }

    private function preparePeriodDayCurrent(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom(new DateTime(date('Y-m-d 00:00:00', $time)))
            ->setDateTo((new DateTime(date('Y-m-d H:00:00', $time)))->modify("+1 hour"))
            ->setStep(3600);

        return $period;
    }

    private function preparePeriodHour(Period $period): Period
    {
        $time = time();

        $period
            ->setDateFrom((new DateTime(date('Y-m-d H:i:00', $time)))->modify("-1 hour"))
            ->setDateTo(new DateTime(date('Y-m-d H:i:00', $time)))
            ->setStep(60);

        return $period;
    }

    private function preparePeriodCustom(Period $period, DateTime $dateFrom, DateTime $dateTo): Period
    {
        if ($dateFrom > $dateTo) {
            $tempDateFrom = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $tempDateFrom;
        }

        $interval = $dateTo->getTimestamp() - $dateFrom->getTimestamp();
        $step = $this->calculateStep($interval);

        $period
            ->setDateFrom($dateFrom)
            ->setDateTo($dateTo)
            ->setStep($step);

        return $period;
    }

    /**
     * @param int $interval
     * @return int
     */
    public function calculateStep(int $interval): int
    {
        // 1 hour => 1 minute.
        if ($interval < 3600) {
            return 60;
        }

        // 3 hour => 15 minutes.
        if ($interval < 3 * 3600) {
            return 60 * 15;
        }

        // 2 days => 60 minutes.
        if ($interval < 2 * 24 * 3600) {
            return 3600;
        }

        // 7 days => 2 hours.
        if ($interval < 7 * 24 * 3600 + 1) {
            return 3600 * 2;
        }

        // 1 month => 1 day.
        if ($interval < (3600 * 24 * 365 / 12)) {
            return 3600 * 24;
        }

        // 6 months => 1 week.
        if ($interval < (3600 * 24 * 365 / 2)) {
            return 3600 * 24 * 7;
        }

        // Default : 1 month.
        return 3600 * 24 * 365 / 12;
    }
}
