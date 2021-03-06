<?php
namespace App;

use Google_Client;
use Google_Service_Calendar;


class GoogleCalendarFactory
{
    public static function createForCalendarId($calendarId)
    {
        $config = config('google-calendar');
        $client = self::createAuthenticatedGoogleClient($config);
        $service = new Google_Service_Calendar($client);
        return self::createCalendarClient($service, $calendarId);
    }
    public static function createAuthenticatedGoogleClient(array $config)
    {
        $client = new Google_Client;
        $client->setScopes([
            Google_Service_Calendar::CALENDAR,
        ]);
        $client->setAuthConfig($config['service_account_credentials_json']);
        return $client;
    }
    protected static function createCalendarClient(Google_Service_Calendar $service, $calendarId)
    {
        return new GoogleCalendar($service, $calendarId);
    }
}