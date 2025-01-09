<?php
namespace IDangerous\NetgsmIYS\Api;

interface WebhookInterface
{
    /**
     * Process webhook request
     *
     * @return mixed
     */
    public function execute();
}