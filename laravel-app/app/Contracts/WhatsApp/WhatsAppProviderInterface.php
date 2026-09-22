<?php

namespace App\Contracts\WhatsApp;

interface WhatsAppProviderInterface
{
    /**
     * @return array{success:bool,error?:string,msg_id?:mixed,http?:int,status?:mixed}
     */
    public function sendText($phone, $message);

    /**
     * @return array{success:bool,error?:string,msg_id?:mixed,http?:int,publicUrl?:string}
     */
    public function sendDocument($phone, $localPath, $fileName = null, $caption = null);

    /**
     * @return array{success:bool,error?:string,msg_id?:mixed,http?:int,publicUrl?:string}
     */
    public function sendImage($phone, $localPath, $caption = null);

    /**
     * @return array{connected:bool,status:string,session_name:?string,error?:string,configured:bool}
     */
    public function sessionStatus();

    public function isConfigured();
}
