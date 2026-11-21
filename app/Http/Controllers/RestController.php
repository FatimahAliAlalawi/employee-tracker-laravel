<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * يمرّر كل طلبات /api/employee-tracker/v1/* إلى الدالة المقابلة في ET_REST_API
 * (المسارات نفسها مسجّلة من register_routes() في routes/api.php)، ويحوّل الناتج إلى JSON
 * بنفس صيغة ووردبريس REST حتى تعمل الواجهة دون أي تعديل على منطقها.
 */
class RestController extends Controller
{
    public function handle(Request $request)
    {
        $route    = $request->route();
        $callback = (string) $route->parameter('et_callback');
        $path     = (string) $route->parameter('et_route');

        $api = \ET_REST_API::instance();
        abort_unless($callback !== '' && method_exists($api, $callback), 404);

        $result = $api->{$callback}(new \WP_REST_Request($request, $path));

        if ($result instanceof \WP_Error) {
            $data   = $result->get_error_data();
            $status = is_array($data) && isset($data['status']) ? (int) $data['status'] : 500;

            return response()->json([
                'code'    => $result->get_error_code(),
                'message' => $result->get_error_message(),
                'data'    => is_array($data) ? $data : ['status' => $status],
            ], $status)->withHeaders($this->noCache());
        }

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        return response()->json($result)->withHeaders($this->noCache());
    }

    /** يمنع أي تخزين مؤقت (متصفح/وسيط/استضافة) لبيانات حيّة */
    private function noCache(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ];
    }
}
