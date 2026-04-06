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

use DateTime;
use Spipu\DashboardBundle\Entity\Period;
use Spipu\DashboardBundle\Service\PeriodService;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

class DashboardRequest extends AbstractRequest
{
    public const KEY_PERIOD = 'dp';

    private PeriodService $periodService;
    private int $dashboardId;
    private ?Period $period = null;

    public function __construct(
        RequestStack $requestStack,
        PeriodService $periodService,
        int $dashboardId
    ) {
        parent::__construct($requestStack);
        $this->periodService = $periodService;
        $this->dashboardId = $dashboardId;
    }

    public function prepare(): void
    {
        $this->setSessionPrefixKey('dashboard.' . $this->dashboardId);
        $this->preparePeriod();
    }

    /**
     * @return void
     * @SuppressWarnings(PMD.CyclomaticComplexity)
     */
    private function preparePeriod(): void
    {
        try {
            $this->period = null;
            $this->period = $this->getSessionValue('period', $this->period);
            $requestPeriod = $this->getCurrentRequest()->query->all(self::KEY_PERIOD);

            // Keep session.
            if (empty($requestPeriod)) {
                return;
            }

            // Reset period.
            if (empty($requestPeriod['type']) && empty($requestPeriod['from']) && empty($requestPeriod['to'])) {
                $this->period = null;
                $this->setSessionValue('period', $this->period);
                return;
            }

            $type = $requestPeriod['type'] !== '' ? $requestPeriod['type'] : PeriodService::PERIOD_CUSTOM;
            $dateFrom = $requestPeriod['from'] ?? null;
            $dateTo = $requestPeriod['to'] ?? null;
            if ($type !== PeriodService::PERIOD_CUSTOM || ($dateFrom && $dateTo)) {
                $this->period = $this->periodService->create(
                    $type,
                    $dateFrom ? new DateTime($dateFrom) : null,
                    $dateTo ? new DateTime($dateTo) : null
                );
            }
        } catch (Throwable $throwable) {
            $this->period = null;
        }
        $this->setSessionValue('period', $this->period);
    }

    public function getPeriod(): ?Period
    {
        return $this->period;
    }
}
