<?php

namespace SmsSenders\Classes;

use DB\Repositories\UserSmsRepository;
use SmsSenders\Interfaces\SmsSenderInterface;

require_once('SmsSenders/Interfaces/SmsSenderInterface.php');

class SmsNavigator implements SmsSenderInterface
{
    const URL = 'http://smsnavi.ru/send/';
    const SERVICE_ID = 1;
    const PASSWORD = 'pass';

    public string $provider = 'navigator';

    public function send(array $phones, string $message): int
    {
        return self::sendRequestForSendMessages($phones, $message);
    }

    public function checkMessages(array $smsIds): int
    {
        return self::sendRequestForCheckMessages($smsIds);
    }

    private function sendRequestForSendMessages(array $phones, string $message): int
    {
        $params = [
            'serviceId' => self::SERVICE_ID,
            'pass' => self::PASSWORD,
            'data' => json_encode([
                'message' => $message,
                'clientIds' => $phones,
            ])
        ];

        $response = self::sendPostRequest($params);

        return self::parseResponse($response, $message);
    }

    private function parseResponse(array $response, $message): int
    {
        if ($response['code'] === 200) {
            return $this->saveResponse($response['response'], $message);
        }

        return 0;
    }

    private function saveResponse(string $responseData, string $message): int
    {
        $responseData = json_decode($responseData, true);

        $repository = new UserSmsRepository();
        $sms = $repository->getAllUnsentSmsByMessage($message);

        $dataForInsert = [];
        foreach ($responseData as $key => $messageInfo) {
            if (mb_strtolower($messageInfo['status']) === 'ok') {
                $smsId = array_values(array_filter($sms, function ($value) use ($key) {
                    return $value['phone'] === (string) $key;
                }));

                if ($smsId) {
                    if ($smsId[0]['id'] && $messageInfo['track_id']) {
                        $dataForInsert = array_merge([
                            $smsId[0]['id'],
                            $messageInfo['track_id'],
                        ], $dataForInsert);
                    }
                }
            }
        }

        return $repository->saveSentSmsInfo($dataForInsert);
    }

    private function sendRequestForCheckMessages(array $smsId): int
    {
        $params = [
            'serviceId' => self::SERVICE_ID,
            'pass' => self::PASSWORD,
            'data' => json_encode([
                'trackingIds' => $smsId,
            ])
        ];

        $response = self::sendPostRequest($params);

        return self::parseStatusResponse($response);
    }

    private function parseStatusResponse(array $response): int
    {
        if ($response['code'] === 200) {
            return $this->saveStatusResponse($response['response']);
        }

        return 0;
    }

    private function saveStatusResponse(string $response): int
    {
        $repository = new UserSmsRepository();

        $response = json_decode($response, true);

        $sms = $repository->getSmsIdsWithUserSmsId();

        $statusTrueIds = [];
        $statusFalseIds = [];

        foreach ($response as $key => $status) {
            if (array_key_exists($key, $sms)) {
                if ($status === 2 || $status === "2") {
                    $statusTrueIds[] = $sms[$key];
                } elseif ($status === 5 || $status === "5") {
                    $statusFalseIds[] = $sms[$key];
                }
            }
        }

        return $repository->saveSmsStatus($statusTrueIds, $statusFalseIds);
    }

    private static function sendPostRequest(array $params): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => self::URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return ['response' => $response, 'code' => $httpCode];
    }
}