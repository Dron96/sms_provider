<?php

namespace SmsSenders\Classes;

use DB\Repositories\UserSmsRepository;
use SmsSenders\Interfaces\SmsSenderInterface;

require_once('SmsSenders/Interfaces/SmsSenderInterface.php');

class SmsZator implements SmsSenderInterface
{
    const URL = 'https://smszator.ru/multi.php';
    const LOGIN = 'login';
    const PASSWORD = 'pass';

    public string $provider = 'zator';

    public function send(array $phones, string $message): int
    {
        return self::sendRequestForSendMessages($phones, $message);
    }

    public function checkMessages(array $smsIds): int
    {
        $smsIds = array_column($smsIds, 'sms_id');

        return self::sendRequestForCheckMessages($smsIds);
    }

    private function sendRequestForSendMessages(array $phones, string $message): int
    {
        $url = self::URL
            .'?login='.self::LOGIN
            .'&password='.self::PASSWORD
            .'&want_sms_ids=1'
            .'&message='.$message
            .'&phones='.implode(',', $phones);

        $response = self::sendGetRequest($url);

        return self::parseResponse($response, $message);
    }

    private function saveResponse(array $response, string $message): int
    {
        $repository = new UserSmsRepository();
        $sms = $repository->getAllUnsentSmsByMessage($message);

        $dataForInsert = [];
        foreach ($response as $smsInfo) {
            $smsId = array_values(array_filter($sms, function ($value) use ($smsInfo) {
                return $value['phone'] === $smsInfo['phone'];
            }));

            if ($smsId) {
                if ($smsId[0]['id'] && $smsInfo['sms_id']) {
                    $dataForInsert = array_merge([
                        $smsId[0]['id'],
                        $smsInfo['sms_id'],
                    ], $dataForInsert);
                }
            }
        }

        return $repository->saveSentSmsInfo($dataForInsert);
    }

    private function saveStatusResponse(array $response): int
    {
        $repository = new UserSmsRepository();
        $sms = $repository->getSmsIdsWithUserSmsId();

        $statusTrueIds = [];
        $statusFalseIds = [];

        foreach ($response as $data) {
            if (array_key_exists($data['sms_id'], $sms)) {
                if (mb_strtolower($data['status']) === "delivered") {
                    $statusTrueIds[] = $sms[$data['sms_id']];
                } elseif (mb_strtolower($data['status']) === "undelivered") {
                    $statusFalseIds[] = $sms[$data['sms_id']];
                }
            }
        }

        return $repository->saveSmsStatus($statusTrueIds, $statusFalseIds);
    }

    private function sendRequestForCheckMessages(array $smsIds): int
    {
        $url = self::URL
            .'?login='.self::LOGIN
            .'&password='.self::PASSWORD
            .'&operation=status'
            .'&sms_id='.implode(',', $smsIds);

        $response = self::sendGetRequest($url);

        return self::parseStatusResponse($response);
    }

    private static function sendGetRequest(string $url): bool|string
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => false
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    private function parseResponse(string $response, string $message): int
    {
        $responseXml = simplexml_load_string($response);
        $responseJson = json_encode($responseXml);
        $responseArray = json_decode($responseJson, true);

        if ($responseArray['code'] === "0" || $responseArray['code'] === 0) {
            $responseArray = $responseArray['message_infos']['message_info'];

            return $this->saveResponse($responseArray, $message);
        }

        return 0;
    }

    private function parseStatusResponse(string $response): int
    {
        $responseXml = simplexml_load_string($response);
        $responseJson = json_encode($responseXml);
        $responseArray = json_decode($responseJson, true);

        return $this->saveStatusResponse($responseArray['sms']);
    }
}