<?
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (!check_bitrix_sessid()) {
    return;
}
?>

<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="beeralex.reviews">
    <input type="hidden" name="install" value="Y">
    <input type="hidden" name="step" value="2">
    <p>Внените ид инфоблоков</p>
    <p><input type="text" name="catalog" id="catalog" value="0" checked><label for="catalog">Каталог ид</label></p>
    <p><input type="text" name="offer" id="offer" value="0" checked><label for="offer">Каталог оффер ид</label></p>
    <input type="submit" name="" value="<?= Loc::getMessage("MOD_INSTALL") ?>">
</form>