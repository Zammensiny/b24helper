<?php
include_once(__DIR__.'/crest.php');

class CRestExt extends CRest
{
    protected static $currentBitrix24 = [];

    static function setCurrentBitrix24($memberId)
    {
        static::$currentBitrix24 = $memberId;
    }

    static function getCurrentBitrix24()
    {
        return static::$currentBitrix24;
    }

    protected static function getSettingsDir()
    {

        $dir = __DIR__ . '/settings/';

        if(!file_exists($dir))
        {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    protected static function getSettingData()
    {
        if (static::$currentBitrix24 != '') {
            $return = static::expandData(file_get_contents(static::getSettingsDir().static::$currentBitrix24.'.json'));
        }

        return $return;
    }

    protected static function setSettingData($arSettings)
    {
        if (static::$currentBitrix24 != '')
            return  (boolean)file_put_contents(static::getSettingsDir().static::$currentBitrix24.'.json', static::wrapData($arSettings));
        else return false;
    }

    public static function installApp()
    {
        if (isset($_REQUEST['event'], $_REQUEST['auth']) && $_REQUEST['event'] === 'ONAPPINSTALL' && !empty($_REQUEST['auth'])) {
            static::setCurrentBitrix24($_REQUEST['auth']['member_id'] ?? null);
        }
        elseif (isset($_REQUEST['PLACEMENT']) && $_REQUEST['PLACEMENT'] === 'DEFAULT') {
            static::setCurrentBitrix24($_REQUEST['member_id'] ?? null);
        }
        return parent::installApp();
    }

    public static function logToFile($data, $member_id)
    {
        $log = "\n------------------------\n";
        $log .= date("Y.m.d G:i:s") . "\n";
        $log .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $log .= "\n------------------------\n";
        $logFile = __DIR__ . '/log/' . $member_id . '.txt';
        file_put_contents($logFile, $log, FILE_APPEND);
    }

}