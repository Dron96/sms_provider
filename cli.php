<?php

use DB\Repositories\UserSmsRepository;
use SmsSenders\Classes\SmsNavigator;
use SmsSenders\Classes\SmsZator;

require_once('DB/Repositories/UserSmsRepository.php');
require_once('DB/DB.php');
require_once('SmsSenders/Classes/SmsZator.php');
require_once('SmsSenders/Classes/SmsNavigator.php');


if (in_array('send', $argv)) {
    $smsRepository = new UserSmsRepository;
    $count = 0;

    $zator = new SmsZator();
    $usersSms = $smsRepository->getAllUnsentSms($zator->provider);
    foreach ($usersSms as $sms) {
        $count += $zator->send([$sms['phone']], $sms['text']);
    }


    $navigator = new SmsNavigator();
    $usersSms = $smsRepository->getAllUnsentSms($navigator->provider);
    foreach ($usersSms as $sms) {
        $count += $navigator->send([$sms['phone']], $sms['text']);
    }

    echo "Отправлено $count сообщений";
    return true;
}

if (in_array('checkstatus', $argv)) {
    $smsRepository = new UserSmsRepository;
    $count = 0;

    $zator = new SmsZator();
    $smsIds = $smsRepository->getSmsIdsForCheckStatus($zator->provider);
    $count += $zator->checkMessages($smsIds);

    $navigator = new SmsNavigator();
    $smsIds = $smsRepository->getSmsIdsForCheckStatus($navigator->provider);
    $count += $navigator->checkMessages($smsIds);

    echo "Для $count сообщений обновлены статусы";
    return true;
}
