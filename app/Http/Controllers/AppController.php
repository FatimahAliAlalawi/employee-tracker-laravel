<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** صفحة الموظفين (بديل الشورت كود [employee_tracker]) + خدمة المرفقات المحمية (?et_file=...) */
class AppController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query->has(\ET_Files::QUERY_VAR)) {
            return \ET_Files::serve((string) $request->query(\ET_Files::QUERY_VAR));
        }

        $settings = \ET_Settings::get();

        return response()
            ->view('tracker.app', [
                'system_name' => (string) $settings['system_name'],
                'rest_url'    => rest_url(\ET_REST_API::NS),
                'nonce'       => csrf_token(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
