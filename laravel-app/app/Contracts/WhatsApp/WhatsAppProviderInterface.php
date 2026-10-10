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
    public function sendDocument($phone, $localPath, $fileName = null, $caption = null, $plain = false);

    /**
     * @return array{success:bool,error?:string,msg_id?:mixed,http?:int,publicUrl?:string}
     */
    public function sendImage($phone, $localPath, $caption = null, $plain = false);

    /**
     * @return array{connected:bool,status:string,session_name:?string,error?:string,configured:bool}
     */
    public function sessionStatus();

    public function isConfigured();

    /**
     * @return array{success:bool,groups?:array}
     */
    public function listGroups();

    /**
     * @return array{success:bool,error?:string,msg_id?:mixed}
     */
    public function sendGroupText($groupJid, $message);
}
