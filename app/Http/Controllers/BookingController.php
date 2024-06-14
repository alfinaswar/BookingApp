<?php

namespace App\Http\Controllers;

use App\Mail\BookingStatusMail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Roomtype;
use Carbon\Carbon;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{

    public function index(Request $request)
    {
        $type = Roomtype::all();
        Return view("Booking.index",compact('type'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($id)
    {
        $getData = Room::with('roomtypes')->where('id', $id)->first();
        // dd($getData);
        $today = Carbon::today();
        $isWeekend = $today->isSaturday() || $today->isSunday();
        return view('Booking.create',compact('getData','isWeekend'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // dd($request->Email);
        $tarifTotal = str_replace('Rp. ', '', $request->tarifTotal);
        $tarifTotal = str_replace('.', '', $tarifTotal);
        $data = $request->all();
        $data['Total'] = $tarifTotal;
        $data['Status'] = "1"; //0 = Menggungu Pembayaran, 1=Dibayar, 2=Pending, 3=cancer Order
        $data = Booking::create($data);

        //update status room
        $query = Room::find($request->roomId);
        $data1['status'] = "1"; //0 = Available, 1=Booked
        $query->update($data1);

        Mail::to($request->Email)->send(new BookingStatusMail($data));
        return response()->json($data);
    }

    /**
     * Display the specified resource.
     */
    public function show(Booking $booking)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Booking $booking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Booking $booking)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Booking $booking)
    {
        //
    }
}
