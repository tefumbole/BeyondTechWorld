<?php

namespace App\Travel;

class TravelCopy
{
    public static function line($key)
    {
        $lang = session('travel_lang') === 'fr' ? 'fr' : 'en';
        $lines = [
            'en' => [
                'book' => 'Book a bus',
                'trips' => 'My trips',
                'track' => 'Track parcel',
                'desk' => 'Station desk',
                'dispatch' => 'Dispatch',
                'hero_book' => 'Your next stop starts here.',
                'hero_book_sub' => 'Book a seat, choose your departure and travel across Cameroon.',
                'find' => 'Find buses',
                'from' => 'From',
                'to' => 'To',
                'date' => 'Travel date',
                'seat' => 'Choose seat',
                'pay' => 'Pay with MoMo',
                'sandbox' => 'Simulate sandbox payment',
            ],
            'fr' => [
                'book' => 'Réserver',
                'trips' => 'Mes trajets',
                'track' => 'Suivre un colis',
                'desk' => 'Guichet',
                'dispatch' => 'Dispatch',
                'hero_book' => 'Votre prochain arrêt commence ici.',
                'hero_book_sub' => 'Choisissez un siège et voyagez à travers le Cameroun.',
                'find' => 'Voir les bus',
                'from' => 'Départ',
                'to' => 'Arrivée',
                'date' => 'Date',
                'seat' => 'Choisir un siège',
                'pay' => 'Payer par MoMo',
                'sandbox' => 'Simuler le paiement sandbox',
            ],
        ];
        if (isset($lines[$lang][$key])) {
            return $lines[$lang][$key];
        }

        return $lines['en'][$key];
    }
}
