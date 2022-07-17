<?php

namespace SmsSenders\Interfaces;

interface SmsSenderInterface
{
    public function send(array $phones, string $message): int;

    public function checkMessages(array $smsIds): int;
}