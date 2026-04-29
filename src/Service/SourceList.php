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

use Spipu\DashboardBundle\Entity\Source\SourceFilter;
use Spipu\DashboardBundle\Exception\SourceException;
use Spipu\DashboardBundle\Source\SourceDefinitionInterface;
use Spipu\DashboardBundle\Source\SourceProxy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class SourceList
{
    private Security $security;
    private TranslatorInterface $translator;
    private WidgetTypeService $widgetTypeService;

    /**
     * @var SourceDefinitionInterface[]
     */
    private array $sources = [];

    public function __construct(
        Security $security,
        TranslatorInterface $translator,
        WidgetTypeService $widgetTypeService,
        iterable $sources
    ) {
        $this->translator = $translator;
        $this->security = $security;
        $this->widgetTypeService = $widgetTypeService;

        foreach ($sources as $source) {
            $this->addSource($source);
        }
    }

    private function addSource(SourceDefinitionInterface $source): void
    {
        if ($this->isUserGranted($source)) {
            $this->sources[$source->getDefinition()->getCode()] = (new SourceProxy())->setSource($source);
        }
    }

    /**
     * @return SourceDefinitionInterface[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @return string[]
     */
    public function getSourceLabels(): array
    {
        $labels = [];
        foreach ($this->sources as $source) {
            $labels[$source->getDefinition()->getCode()] = $this->getSourceLabel($source);
        }
        asort($labels);

        return $labels;
    }

    public function getSource(string $code): SourceDefinitionInterface
    {
        if (!array_key_exists($code, $this->sources)) {
            throw new SourceException('Unknown source code');
        }

        return $this->sources[$code];
    }

    public function getSourceLabel(SourceDefinitionInterface $source): string
    {
        $code = 'spipu.dashboard.source.' . $source->getDefinition()->getCode() . '.label';

        $value = $this->translator->trans($code);
        if ($value === $code) {
            $value = ucwords(str_replace('-', ' ', $source->getDefinition()->getCode()));
        }

        return $value;
    }

    public function getDefinitions(): array
    {
        $definition = [];

        foreach ($this->sources as $source) {
            $definition[$source->getDefinition()->getCode()] = [
                'code'            => $source->getDefinition()->getCode(),
                'label'           => $this->getSourceLabel($source),
                'filters'         => $this->getSourceFilters($source),
                'needPeriod'      => ($source->getDefinition()->needPeriod() ? 1 : 0),
                'specificDisplay' => $source->getDefinition()->getSpecificDisplayIcon(),
                'availableTypes'  => $this->widgetTypeService->getAvailableWidgetTypes($source->getDefinition()),
            ];
        }

        uasort(
            $definition,
            function (array $rowA, array $rowB) {
                return $rowA['label'] <=> $rowB['label'];
            }
        );

        return $definition;
    }

    public function getSourceFilters(
        SourceDefinitionInterface $source
    ): array {
        if (!$source->getDefinition()->hasFilters()) {
            return [];
        }

        return array_map(
            function (SourceFilter $filter): array {
                $values = $filter->getOptions()->getOptions();
                if ($filter->isTranslate()) {
                    foreach ($values as $key => $value) {
                        $values[$key] = $this->translator->trans($value);
                    }
                }

                return [
                    'name'      => $this->translator->trans($filter->getName()),
                    'options'   => $values,
                    'multiple'  => $filter->isMultiple(),
                ];
            },
            $source->getDefinition()->getFilters()
        );
    }

    private function isUserGranted(SourceDefinitionInterface $sourceDefinition): bool
    {
        if (empty($sourceDefinition->getRolesNeeded())) {
            return true;
        }
        foreach ($sourceDefinition->getRolesNeeded() as $role) {
            if ($this->security->isGranted($role)) {
                return true;
            }
        }
        return false;
    }
}
