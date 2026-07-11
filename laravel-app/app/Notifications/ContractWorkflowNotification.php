<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContractWorkflowNotification extends Notification
{
    use Queueable;

    protected $message;
    protected $link;
    protected $type;

    public function __construct($message, $link = null, $type = 'contract')
    {
        $this->message = $message;
        $this->link = $link;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
            'link' => $this->link,
            'type' => $this->type,
        ];
    }
}
