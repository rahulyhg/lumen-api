<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use Dingo\Api\Contract\Http\Request;
use App\Event;
use Illuminate\Http\JsonResponse;
use App\Appointment;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;

class gCalendarController extends Controller
{
    public function getMeetings()
    {
        $optParams = ['q' => 'Meeting'];
        $events = Event::get(Carbon::now(), Carbon::now()->addMonth(3), $optParams);
        return JsonResponse::create($events);
    }

    public function updateMeeting(Request $request)
    {
        if($request->header('Authorization')!= "") {
            $user = JWTAuth::parseToken()->authenticate();
        }else{
            $user = null;
        }

        $meeting = json_decode($request->input('formValues'))->formvalues;

        self::sendMail($meeting);

        $event = Event::find($meeting->eventId);
        $event->name = 'Tax Advice meeting with ' . $meeting->firstName ." " . $meeting->lastName ;
        $event->colorId = 11;
        $event->description = "Name: ".$meeting->firstName ." ". $meeting->lastName. " || Service: ".$meeting->service . " || Phonenumber: ". $meeting->phoneNumber." || ". "Socialname: ". $meeting->socialName." || Type " .$meeting->type." || Comments: " . $meeting->comments ;
        $event->save();

        $appointment = new Appointment();
        $appointment->startDate = $meeting->startDate;
        $appointment->endDate = $meeting->endDate;
        if($user!=null) {
            $appointment->person_id =$user->person_id;
        }
        $appointment->save();

        return self::getMeetings();
    }

    public function sendMail($meeting)
    {
        $emailData = [
            'name' => $meeting->firstName ." ".$meeting->lastName,
            'email' => $meeting->email,
            'service' => $meeting->service,
            'comments' => $meeting->comments,
            'startDate' => $meeting->startDate,
            'endDate' => $meeting->endDate,
        ];

        Mail::send('mails.appointment.appointmentMade', $emailData, function ($message) use ($emailData) {
            $message->to($emailData['email'], '')->subject('Do-not-reply:Afspraak');
            $message->from('info@kcps.nl', 'Info || KCPSoftware');
        });
    }

}