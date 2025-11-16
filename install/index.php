<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class beeralex_reviews extends CModule
{

    public function __construct()
    {
        if (is_file(__DIR__ . '/version.php')) {
            include_once(__DIR__ . '/version.php');
            $this->MODULE_ID           = 'beeralex.reviews';
            $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->MODULE_NAME         = Loc::getMessage('REVIEWS_NAME');
            $this->MODULE_DESCRIPTION  = Loc::getMessage('REVIEWS_DESCRIPTION');
            $this->PARTNER_NAME = 'Beeralex';
            $this->PARTNER_URI = '#';
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('REVIEWS_FILE_NOT_FOUND') . ' version.php'
            );
        }
    }

    public function doInstall()
    {
        if ($this->checkRequirements()) {
            $context = Application::getInstance()->getContext();
            $request = $context->getRequest();
            $step = $request->get('step');
            global $APPLICATION;
            if ($step < 2) {
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage('INSTALL_TITLE_STEP_1'),
                    __DIR__ . '/instalInfo-step1.php'
                );
            }
            if ($step == 2) {
                ModuleManager::registerModule($this->MODULE_ID);
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage('REVIEWS_INSTALL_TITLE') . ' «' . Loc::getMessage('REVIEWS_NAME') . '»',
                    __DIR__ . '/instalInfo-step2.php'
                );
            }
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('REVIEWS_INSTALL_ERROR')
            );
            return;
        }
        return true;
    }

    public function checkRequirements(): bool
    {
        return version_compare(ModuleManager::getVersion('main'), '23.00.00') >= 0 && Loader::includeModule('beeralex.core');
    }

    public function doUninstall()
    {
        global $APPLICATION;

        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->includeAdminFile(
            Loc::getMessage('REVIEWS_UNINSTALL_TITLE') . ' «' . Loc::getMessage('REVIEWS_NAME') . '»',
            __DIR__ . '/unstep.php'
        );
    }
}
