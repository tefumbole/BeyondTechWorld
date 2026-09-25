<?php

namespace App\Contracts\Calendar;

interface CalendarProviderInterface
{
    public function isConfigured();

    /**
     * @param array $event
     * @return array{success:bool,configured:bool,event_id?:string,error?:string}
     */
    public function createEvent(array $event);

    /**
     * @param array $event
     * @return array{success:bool,configured:bool,event_id?:string,error?:string}
     */
    public function updateEvent($eventId, array $event);

    /**
     * @return array{success:bool,configured:bool,error?:string}
     */
    public function cancelEvent($eventId);

    /**
     * @return array{success:bool,configured:bool,event?:array,error?:string}
     */
    public function getEvent($eventId);
}
