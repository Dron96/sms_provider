<?php

namespace DB\Repositories;

use DB\DB;
use PDO;

class UserSmsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connection();
    }

    public function getAllUnsentSms(string $provider): array
    {
        $stmt = $this->db->prepare("
            SELECT user_sms.text, user_sms.phone
            FROM user_sms 
            LEFT JOIN sent_sms as ss ON ss.user_sms_id = user_sms.id
            LEFT JOIN users as u on u.id = user_sms.user_id 
            WHERE status is null AND ss.sms_id is null AND u.sms_provider = '{$provider}';
        ");
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function getAllUnsentSmsByMessage(string $message): array
    {
        $stmt = $this->db->prepare("
            SELECT user_sms.phone, user_sms.id
            FROM user_sms 
            LEFT JOIN sent_sms as ss ON ss.user_sms_id = user_sms.id
            WHERE text = '$message' AND status is null AND ss.sms_id is null;");
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function getSmsIdsWithUserSmsId(): array
    {
        $stmt = $this->db->prepare("
            SELECT sent_sms.sms_id, sent_sms.user_sms_id
            FROM sent_sms
            LEFT JOIN user_sms as us ON sent_sms.user_sms_id = us.id
            WHERE us.status is null;");
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['sms_id']] = $row['user_sms_id'];
        }

        return $result;
    }

    public function getSmsIdsForCheckStatus(string $provider): array
    {
        $stmt = $this->db->prepare("
            SELECT sent_sms.sms_id
            FROM sent_sms
            LEFT JOIN user_sms as us ON sent_sms.user_sms_id = us.id
            LEFT JOIN users as u on u.id = us.user_id 
            WHERE us.status is null AND u.provider = '{$provider}';");
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function saveSentSmsInfo(array $dataForInsert): int
    {
        $sql = "INSERT INTO sent_sms (user_sms_id, sms_id) VALUES ";
        $count = 0;

        var_dump('$$$$$$');
        var_dump($dataForInsert);

        if ($dataForInsert) {
            for ($i = 0; $i < count($dataForInsert); $i += 2) {
                $sql .= $count === 0
                    ? '(?, ?)'
                    : ', (?, ?)';

                $count++;
            }

            $sql .= ' ON CONFLICT (user_sms_id, sms_id) DO NOTHING;';

            var_dump($sql);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($dataForInsert);

            return $count;
        }

        return 0;
    }

    public function saveSmsStatus(array $statusTrueIds, array $statusFalseIds): int
    {
        if ($statusTrueIds) {
            $sql = 'UPDATE user_sms '
                .'SET status = true '
                .'WHERE id in ('.implode(', ', $statusTrueIds).');';

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        if ($statusFalseIds) {
            $sql = 'UPDATE user_sms '
                .'SET status = false '
                .'WHERE id in ('.implode(', ', $statusFalseIds).');';

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        return count($statusTrueIds) + count($statusFalseIds);
    }
}