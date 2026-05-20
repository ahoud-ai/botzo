<?php

namespace App\Contracts;

interface QueueProfileContract
{
    public function getName(): string;

    public function getConnection(): string;

    public function getCacheStore(): string;

    public function getSessionDriver(): string;

    /**
     * @return array<int, string>
     */
    public function getQueues(): array;

    /**
     * @return array<int, array{name:string,queues:array<int,string>,sleep:int,tries:int,timeout:int}>
     */
    public function getWorkers(): array;

    public function getQueueList(): string;
}
