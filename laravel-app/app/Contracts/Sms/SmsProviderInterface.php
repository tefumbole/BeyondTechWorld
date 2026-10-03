<?php

namespace App\Contracts\Sms;

interface SmsProviderInterface
{
    public function name();

    /**
     * @param  array{to:string,from:?string,body:string,reference:string}  $message
     * @return array{accepted:bool,status:string,provider_message_id:?string,failure_class:?string,failure_code:?string,segments:?int}
     */
    public function send(array $message);

    /**
     * @return array{status:string,raw:?string}|null
     */
    public function getStatus($providerMessageId);

    /**
     * @param  array<string,string>  $headers
     */
    public function verifyWebhook(array $headers, $rawBody);
}
