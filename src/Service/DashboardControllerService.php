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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Spipu\DashboardBundle\Entity\DashboardAcl;
use Spipu\DashboardBundle\Exception\DashboardAclException;
use Spipu\DashboardBundle\Service\Ui\DashboardRequestFactory;
use Spipu\DashboardBundle\Service\Ui\DashboardShowFactory;
use Spipu\DashboardBundle\Service\Ui\Definition\DashboardDefinitionInterface;
use Spipu\DashboardBundle\Service\Ui\WidgetFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Throwable;

/**
 * @SuppressWarnings(PMD.ExcessiveParameterList)
 * @SuppressWarnings(PMD.CouplingBetweenObjects)
 * @SuppressWarnings(PMD.ExcessiveClassComplexity)
 */
class DashboardControllerService extends AbstractController
{
    private TranslatorInterface $translator;
    private DashboardService $dashboardService;
    private WidgetService $widgetService;
    private RequestStack $requestStack;
    private SourceList $sourceList;
    private PeriodService $periodService;
    private WidgetTypeService $widgetTypeService;
    private EntityManagerInterface $entityManager;
    private DashboardConfiguratorService $dashboardConfiguratorService;
    private DashboardShowFactory $dashboardShowFactory;
    private WidgetFactory $widgetFactory;
    private DashboardDefinitionInterface $dashboardDefinition;
    private DashboardAcl $dashboardAcl;
    private DashboardRequestFactory $dashboardRequestFactory;

    public function __construct(
        TranslatorInterface $translator,
        DashboardService $dashboardService,
        WidgetService $widgetService,
        RequestStack $requestStack,
        SourceList $sourceList,
        PeriodService $periodService,
        WidgetTypeService $widgetTypeService,
        EntityManagerInterface $entityManager,
        DashboardConfiguratorService $dashboardConfiguratorService,
        DashboardShowFactory $dashboardShowFactory,
        WidgetFactory $widgetFactory,
        DashboardRequestFactory $dashboardRequestFactory
    ) {
        $this->dashboardService = $dashboardService;
        $this->translator = $translator;
        $this->widgetService = $widgetService;
        $this->requestStack = $requestStack;
        $this->sourceList = $sourceList;
        $this->periodService = $periodService;
        $this->widgetTypeService = $widgetTypeService;
        $this->entityManager = $entityManager;
        $this->dashboardConfiguratorService = $dashboardConfiguratorService;
        $this->dashboardShowFactory = $dashboardShowFactory;
        $this->widgetFactory = $widgetFactory;
        $this->dashboardRequestFactory = $dashboardRequestFactory;
    }

    public function dispatch(
        DashboardDefinitionInterface $dashboardDefinition,
        string $routeName,
        string $action,
        ?int $id = null,
        ?DashboardAcl $dashboardAcl = null
    ): Response {
        $this->dashboardDefinition = $dashboardDefinition;
        $this->dashboardDefinition->getDefinition()->setRoute($routeName);

        $this->setDashboardAcl($dashboardAcl);
        $user = $this->getDashboardUser();
        $id = $this->getDashboardAllowedId($user, $id);

        switch ($action) {
            case 'create':
                return $this->actionCreate($user);

            case 'configure':
                return $this->actionConfigure($user, $id);

            case 'delete':
                return $this->actionDelete($user, $id);

            case 'duplicate':
                return $this->actionDuplicate($user, $id);

            case 'save':
                return $this->actionSave($user, $id);

            case '':
            case 'show':
                return $this->actionShow($user, $id);

            case 'refresh_widget':
                return $this->actionRefreshWidget($user, $id);
        }

        throw $this->createNotFoundException('Unknown action');
    }

    protected function actionCreate(UserInterface $user): RedirectResponse
    {
        if (!$this->dashboardAcl->isCanCreate()) {
            throw $this->createAccessDeniedException('Action Create is not allowed');
        }
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $dashboardName = (string) $this->requestStack->getCurrentRequest()->request->get('dashboard_name');
        $dashboardName = trim(strip_tags($dashboardName));
        if (!$dashboardName) {
            $this->addFlashTrans('danger', 'spipu.dashboard.error.name_missing');
            return $this->redirect($this->buildUrl('show'));
        }

        try {
            $dashboard = $this->dashboardService->createDashboard($user, $dashboardName);
        } catch (UniqueConstraintViolationException $e) {
            $this->addFlashTrans('danger', 'spipu.dashboard.error.name_already_used');
            return $this->redirect($this->buildUrl('show'));
        }

        return $this->redirect($this->buildUrl('configure', $dashboard->getId()));
    }

    protected function actionConfigure(UserInterface $user, ?int $id): Response
    {
        if (!$this->dashboardAcl->isCanConfigure()) {
            throw $this->createAccessDeniedException('Action Configure is not allowed');
        }

        if ($id === null) {
            throw $this->createNotFoundException('Id is required');
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $dashboard = $this->dashboardService->getDashboard($user, $id);
        if ($dashboard === null) {
            throw $this->createNotFoundException('Unknown dashboard');
        }

        return $this->render(
            $this->dashboardDefinition->getDefinition()->getTemplateConfigureMain(),
            [
                'definition'    => $this->dashboardDefinition->getDefinition(),
                'dashboard'     => $dashboard,
                'dashboard_acl' => $this->dashboardAcl,
                'sources'       => $this->sourceList->getDefinitions(),
                'periods'       => $this->periodService->getDefinitions(),
                'types'         => $this->widgetTypeService->getDefinitions(),
                'save_url'      => $this->buildUrl('save', $id),
                'delete_url'    => $this->buildUrl('delete', $id),
                'back_url'      => $this->buildUrl('show', $id),
            ]
        );
    }

    protected function actionDelete(UserInterface $user, ?int $id): Response
    {
        if (!$this->dashboardAcl->isCanDelete()) {
            throw $this->createAccessDeniedException('Action Delete is not allowed');
        }

        if ($id === null) {
            throw $this->createNotFoundException('Id is required');
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $dashboard = $this->dashboardService->getDashboard($user, $id);
        if ($dashboard === null) {
            throw $this->createNotFoundException('Unknown dashboard');
        }

        try {
            $this->dashboardService->deleteDashboard($dashboard, $user);
        } catch (AccessDeniedException $e) {
            $this->addFlashTrans('danger', $e->getMessage());
        }

        return $this->redirect($this->buildUrl('show'));
    }

    protected function actionDuplicate(UserInterface $user, ?int $id): Response
    {
        if (!$this->dashboardAcl->isCanCreate()) {
            throw $this->createAccessDeniedException('Action Create is not allowed');
        }

        if ($id === null) {
            throw $this->createNotFoundException('Id is required');
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $dashboard = $this->dashboardService->getDashboard($user, $id);
        if ($dashboard === null) {
            throw new NotFoundHttpException('Unknown dashboard');
        }

        $dashboardName = (string) $this->requestStack->getCurrentRequest()->request->get('dashboard_name');
        $dashboardName = trim(strip_tags($dashboardName));
        if (empty($dashboardName)) {
            $this->addFlashTrans('danger', 'spipu.dashboard.error.name_missing');
            return $this->redirect($this->buildUrl('show', $id));
        }

        try {
            $dashboard = $this->dashboardService->duplicateDashboard($dashboard, $user, $dashboardName);
        } catch (UniqueConstraintViolationException $e) {
            $this->addFlashTrans('danger', 'spipu.dashboard.error.name_already_used');
            return $this->redirect($this->buildUrl('show', $id));
        }

        return $this->redirect($this->buildUrl('show', $dashboard->getId()));
    }

    protected function actionSave(UserInterface $user, ?int $id): Response
    {
        if (!$this->dashboardAcl->isCanConfigure()) {
            throw $this->createAccessDeniedException('Action Configure is not allowed');
        }

        if ($id === null) {
            throw $this->createNotFoundException('Id is required');
        }

        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            $configurations = $this->dashboardConfiguratorService->validateAndPrepareConfigurations(
                $this->requestStack->getCurrentRequest()
            );

            $dashboard = $this->dashboardService->getDashboard($user, $id);
            if ($dashboard === null) {
                return new JsonResponse(['status' => 'ko', 'message' => 'Unknown dashboard']);
            }

            if ($this->dashboardService->canUpdateDashboard($dashboard, $user)) {
                $name = trim(strip_tags($this->requestStack->getCurrentRequest()->request->get('name')));
                $dashboard->setName($name);
            }

            $dashboard->setContent($configurations);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse([
                'status' => 'ko',
                'message' => $this->trans('spipu.dashboard.error.name_already_used')
            ]);
        } catch (Throwable $exception) {
            return new JsonResponse([
                'status' => 'ko',
                'message' => $exception->getMessage()
            ]);
        }

        return new JsonResponse(['status' => 'ok']);
    }

    protected function actionShow(UserInterface $user, ?int $id = null): Response
    {
        $dashboard = $this->dashboardService->getDashboard($user, $id, $this->dashboardDefinition);
        if ($dashboard === null) {
            throw new NotFoundHttpException('Unknown dashboard');
        }
        $id = $dashboard->getId();

        $dashboards = $this->dashboardService->getUserDashboards($user);

        $manager = $this->dashboardShowFactory->create($this->dashboardDefinition, $dashboard, $dashboards);

        $manager->setUrl('main', $this->buildUrl('show'));
        $manager->setUrl('create', $this->buildUrl('create'));
        $manager->setUrl('reset', $this->buildUrl('show', $id));
        $manager->setUrl('delete', $this->buildUrl('delete', $id));
        $manager->setUrl('duplicate', $this->buildUrl('duplicate', $id));
        $manager->setUrl('configure', $this->buildUrl('configure', $id));
        $manager->setUrl('refresh_widget', $this->buildUrl('refresh_widget', $id));
        $manager->setAcl($this->dashboardAcl);
        $manager->validate();

        return $this->render(
            $this->dashboardDefinition->getDefinition()->getTemplateShowMain(),
            [
                'manager' => $manager
            ]
        );
    }

    protected function actionRefreshWidget(UserInterface $user, int $id): Response
    {
        $identifier = $this->requestStack->getCurrentRequest()->query->get('identifier');

        $dashboard = $this->dashboardService->getDashboard($user, $id);
        if ($dashboard === null) {
            throw new NotFoundHttpException('Unknown dashboard');
        }
        if ($identifier === null) {
            throw new NotFoundHttpException('Unknown widget');
        }

        $widgetDefinition = $this->dashboardService->getWidgetDefinition($dashboard, $identifier);
        $widget = $this->widgetService->buildWidget($widgetDefinition);
        try {
            $widgetManager = $this->widgetFactory->create($widget);
            $widgetManager->setUrl(
                'refresh',
                $this->buildUrl('refresh_widget', $dashboard->getId(), ['identifier' => $identifier])
            );
            $dashboardRequest = $this->dashboardRequestFactory->get($dashboard);
            $widgetManager->getRequest()->setPeriod($dashboardRequest->getPeriod());
            $widgetManager->validate();
        } catch (Exception $exception) {
            $widgetManager = $this->widgetFactory->createError($exception->getMessage(), $widget);
        }

        return $this->render(
            $this->dashboardDefinition->getDefinition()->getTemplateWidgetAll(),
            ['manager' => $widgetManager]
        );
    }

    protected function addFlashTrans(string $type, string $message, array $params = []): void
    {
        $this->addFlash($type, $this->trans($message, $params));
    }

    protected function trans(string $message, array $params = []): string
    {
        return $this->translator->trans($message, $params);
    }

    protected function buildUrl(string $action, ?int $id = null, array $parameters = []): string
    {
        return $this->generateUrl(
            $this->dashboardDefinition->getDefinition()->getRoute(),
            [
                'action' => $action,
                'id' => $id,
            ] + $parameters
        );
    }

    private function setDashboardAcl(?DashboardAcl $dashboardAcl): void
    {
        if ($dashboardAcl === null) {
            $dashboardAcl = new DashboardAcl();
        }
        $this->dashboardAcl = $dashboardAcl;
    }

    private function getDashboardAllowedId(UserInterface $user, ?int $id): ?int
    {
        if ($this->dashboardAcl->isCanSelect()) {
            return $id;
        }

        return $this->dashboardService->getDashboard($user, null, $this->dashboardDefinition)->getId();
    }

    private function getDashboardUser(): UserInterface
    {
        $user = $this->getUser();
        if ($user !== null) {
            return $user;
        }

        $user = $this->dashboardAcl->getDefaultUser();
        if ($user !== null) {
            return $user;
        }

        throw new DashboardAclException('You must define a default user');
    }
}
