<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\Request;

class InquiryModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = Inquiry::with([
            'sender:id,student_id,email,first_name,middle_name,last_name',
            'recipient:id,student_id,email,first_name,middle_name,last_name',
            'listing:id,title,listing_status',
        ])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $inquiries = $query->paginate(10)->withQueryString();

        $counts = [
            'all'      => Inquiry::count(),
            'pending'  => Inquiry::where('status', 'pending')->count(),
            'accepted' => Inquiry::where('status', 'accepted')->count(),
            'declined' => Inquiry::where('status', 'declined')->count(),
        ];

        return view('admin.inquiries.index', compact('inquiries', 'status', 'counts'));
    }
}