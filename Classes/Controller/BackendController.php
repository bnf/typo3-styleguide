<?php

declare(strict_types=1);

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
namespace TYPO3\CMS\Styleguide\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Styleguide\Service\KauderwelschService;
use TYPO3\CMS\Styleguide\TcaDataGenerator\Generator;
use TYPO3\CMS\Styleguide\TcaDataGenerator\GeneratorFrontend;
use TYPO3\CMS\Styleguide\TcaDataGenerator\RecordFinder;

/**
 * Backend module for Styleguide
 */
class BackendController extends ActionController
{
    protected ModuleTemplate $moduleTemplate;
    protected string $languageFilePrefix = 'LLL:EXT:styleguide/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly FlashMessageService $flashMessageService,
    ) {
    }

    /**
     * Method is called before each action and sets up the doc header.
     */
    protected function initializeView(): void
    {
        $this->pageRenderer->addJsFile('EXT:styleguide/Resources/Public/JavaScript/prism.js');
        $this->pageRenderer->addCssFile('EXT:styleguide/Resources/Public/Css/backend.css');

        // Hand over flash message queue to module template
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
        $this->moduleTemplate->assign('actions', ['index', 'typography', 'tca', 'trees', 'tab', 'tables', 'avatar', 'buttons',
            'infobox', 'flashMessages', 'icons', 'debug', 'modal', 'accordion', 'pagination', ]);
        $this->moduleTemplate->assign('currentAction', $this->request->getControllerActionName());

        // Shortcut button
        $arguments = $this->request->getArguments();
        $shortcutArguments = [];
        if (!empty($arguments['controller']) && !empty($arguments['action'])) {
            $shortcutArguments['tx_styleguide_help_styleguidestyleguide'] = [
                'controller' => $arguments['controller'],
                'action' => $arguments['action'],
            ];
        }
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setDisplayName(sprintf(
                '%s - %s',
                LocalizationUtility::translate($this->languageFilePrefix . 'styleguide', 'styleguide'),
                LocalizationUtility::translate($this->languageFilePrefix . ($arguments['action'] ?? 'index'), 'styleguide')
            ))
            ->setRouteIdentifier('help_StyleguideStyleguide')
            ->setArguments($shortcutArguments);
        $buttonBar->addButton($shortcutButton);
    }

    protected function buttonsAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Buttons');
    }

    protected function indexAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Index');
    }

    protected function typographyAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Typography');
    }

    protected function treesAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Trees');
    }

    protected function tablesAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Tables');
    }

    protected function tcaAction(): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Styleguide/ProcessingIndicator');
        $finder = GeneralUtility::makeInstance(RecordFinder::class);
        $demoExists = count($finder->findUidsOfStyleguideEntryPages());
        $demoFrontendExists = count($finder->findUidsOfFrontendPages());
        $this->moduleTemplate->assignMultiple([
            'demoExists' => $demoExists,
            'demoFrontendExists' => $demoFrontendExists,
        ]);
        return $this->moduleTemplate->renderResponse('Backend/Tca');
    }

    protected function tcaCreateAction(): ResponseInterface
    {
        $finder = GeneralUtility::makeInstance(RecordFinder::class);
        if (count($finder->findUidsOfStyleguideEntryPages())) {
            // Tell something was done here
            $json = [
                'title' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaCreateActionFailedTitle', 'styleguide'),
                'body' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaCreateActionFailedBody', 'styleguide'),
                'status' => AbstractMessage::ERROR,
            ];
        } else {
            $generator = GeneralUtility::makeInstance(Generator::class);
            $generator->create();
            // Tell something was done here
            $json = [
                'title' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaCreateActionOkTitle', 'styleguide'),
                'body' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaCreateActionOkBody', 'styleguide'),
                'status' => AbstractMessage::OK,
            ];
        }
        // And redirect to display action
        return new JsonResponse($json);
    }

    protected function tcaDeleteAction(): ResponseInterface
    {
        $generator = GeneralUtility::makeInstance(Generator::class);
        $generator->delete();
        // Tell something was done here
        $json = [
            'title' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaDeleteActionOkTitle', 'styleguide'),
            'body' => LocalizationUtility::translate($this->languageFilePrefix . 'tcaDeleteActionOkBody', 'styleguide'),
            'status' => AbstractMessage::OK,
        ];
        return new JsonResponse($json);
    }

    protected function debugAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Debug');
    }

    protected function iconsAction(): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Styleguide/FindIcons');
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $allIcons = $iconRegistry->getAllRegisteredIconIdentifiers();
        $overlays = array_filter(
            $allIcons,
            function ($key) {
                return str_starts_with($key, 'overlay');
            }
        );
        $this->moduleTemplate->assignMultiple([
            'allIcons' => $allIcons,
            'deprecatedIcons' => $iconRegistry->getDeprecatedIcons(),
            'overlays' => $overlays,
        ]);
        return $this->moduleTemplate->renderResponse('Backend/Icons');
    }

    protected function infoboxAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Infobox');
    }

    protected function flashMessagesAction(): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Styleguide/RenderNotifications');
        $loremIpsum = GeneralUtility::makeInstance(KauderwelschService::class)->getLoremIpsum();
        // We're writing to an own queue here to position the messages within the body.
        // Normal modules wouldn't usually do this and would let ModuleTemplate layout take care of rendering
        // at some appropriate positions.
        $flashMessageQueue = $this->flashMessageService->getMessageQueueByIdentifier('styleguide.demo');
        $flashMessageQueue->enqueue(GeneralUtility::makeInstance(FlashMessage::class, $loremIpsum, 'Info - Title for Info message', AbstractMessage::INFO, true));
        $flashMessageQueue->enqueue(GeneralUtility::makeInstance(FlashMessage::class, $loremIpsum, 'Notice - Title for Notice message', AbstractMessage::NOTICE, true));
        $flashMessageQueue->enqueue(GeneralUtility::makeInstance(FlashMessage::class, $loremIpsum, 'Error - Title for Error message', AbstractMessage::ERROR, true));
        $flashMessageQueue->enqueue(GeneralUtility::makeInstance(FlashMessage::class, $loremIpsum, 'Ok - Title for OK message', AbstractMessage::OK, true));
        $flashMessageQueue->enqueue(GeneralUtility::makeInstance(FlashMessage::class, $loremIpsum, 'Warning - Title for Warning message', AbstractMessage::WARNING, true));
        return $this->moduleTemplate->renderResponse('Backend/FlashMessages');
    }

    protected function avatarAction(): ResponseInterface
    {
        $this->moduleTemplate->assign(
            'backendUser',
            $GLOBALS['BE_USER']->user
        );
        return $this->moduleTemplate->renderResponse('Backend/Avatar');
    }

    protected function tabAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Tab');
    }

    protected function modalAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Modal');
    }

    protected function accordionAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Accordion');
    }

    protected function paginationAction(int $page = 1): ResponseInterface
    {
        // Prepare example data for pagination list
        $itemsToBePaginated = [
            'Warty Warthog',
            'Hoary Hedgehog',
            'Breezy Badger',
            'Dapper Drake',
            'Edgy Eft',
            'Feisty Fawn',
            'Gutsy Gibbon',
            'Hardy Heron',
            'Intrepid Ibex',
            'Jaunty Jackalope',
            'Karmic Koala',
            'Lucid Lynx',
            'Maverick Meerkat',
            'Natty Narwhal',
            'Oneiric Ocelot',
            'Precise Pangolin',
            'Quantal Quetzal',
            'Raring Ringtail',
            'Saucy Salamander',
            'Trusty Tahr',
            'Utopic Unicorn',
            'Vivid Vervet',
            'Wily Werewolf',
            'Xenial Xerus',
            'Yakkety Yak',
            'Zesty Zapus',
            'Artful Aardvark',
            'Bionic Beaver',
            'Cosmic Cuttlefish',
            'Disco Dingo',
            'Eoan Ermine',
            'Focal Fossa',
            'Groovy Gorilla',
        ];
        $itemsPerPage = 10;

        if ($this->request->hasArgument('page')) {
            $page = (int)$this->request->getArgument('page');
        }

        // Prepare example data for dropdown
        $userGroupArray = [
            0 => '[All users]',
            -1 => 'Self',
            'gr-7' => 'Group styleguide demo group 1',
            'gr-8' => 'Group styleguide demo group 2',
            'us-9' => 'User _cli_',
            'us-1' => 'User admin',
            'us-10' => 'User styleguide demo user 1',
            'us-11' => 'User styleguide demo user 2',
        ];

        $paginator = new ArrayPaginator($itemsToBePaginated, $page, $itemsPerPage);
        $this->moduleTemplate->assignMultiple([
            'paginator' => $paginator,
            'pagination' => new SimplePagination($paginator),
            'userGroups' => $userGroupArray,
            'dateTimeFormat' => 'h:m d-m-Y',
        ]);

        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Styleguide/Pagination');

        return $this->moduleTemplate->renderResponse('Backend/Pagination');
    }

    protected function frontendCreateAction(): ResponseInterface
    {
        $recordFinder = GeneralUtility::makeInstance(RecordFinder::class);
        if (count($recordFinder->findUidsOfFrontendPages())) {
            $json = [
              'title' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendCreateActionFailedTitle', 'styleguide'),
              'body' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendCreateActionFailedBody', 'styleguide'),
              'status' => AbstractMessage::ERROR,
            ];
        } else {
            $frontend = GeneralUtility::makeInstance(GeneratorFrontend::class);
            $frontend->create();

            $json = [
                'title' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendCreateActionOkTitle', 'styleguide'),
                'body' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendCreateActionOkBody', 'styleguide'),
                'status' => AbstractMessage::OK,
            ];
        }
        return new JsonResponse($json);
    }

    protected function frontendDeleteAction(): ResponseInterface
    {
        $frontend = GeneralUtility::makeInstance(GeneratorFrontend::class);
        $frontend->delete();
        $json = [
            'title' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendDeleteActionOkTitle', 'styleguide'),
            'body' => LocalizationUtility::translate($this->languageFilePrefix . 'frontendDeleteActionOkBody', 'styleguide'),
            'status' => AbstractMessage::OK,
        ];
        return new JsonResponse($json);
    }
}
