<?php

use Illuminate\Http\Request;

/**
 * غلاف رفيع حول طلب Laravel بنفس واجهة WP_REST_Request التي يستخدمها ET_REST_API.
 * ترتيب أولوية المعاملات مثل ووردبريس: JSON ثم POST ثم GET.
 */
class WP_REST_Request
{
    public function __construct(private Request $request, private string $route = '')
    {
    }

    public function laravel(): Request
    {
        return $this->request;
    }

    public function get_param($key)
    {
        $json = $this->get_json_params();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }
        $post = $this->request->request->all();
        if (array_key_exists($key, $post)) {
            return $post[$key];
        }
        $query = $this->request->query->all();
        if (array_key_exists($key, $query)) {
            return $query[$key];
        }
        return null;
    }

    public function get_params(): array
    {
        return array_merge(
            $this->request->query->all(),
            $this->request->request->all(),
            (array) $this->get_json_params()
        );
    }

    public function get_json_params()
    {
        if (! $this->request->isJson()) {
            return null;
        }
        $decoded = json_decode((string) $this->request->getContent(), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function get_body_params(): array
    {
        return $this->request->request->all();
    }

    public function get_query_params(): array
    {
        return $this->request->query->all();
    }

    /** مصفوفة بصيغة $_FILES الأصلية (name/type/tmp_name/error/size) كما يتوقعها الكود */
    public function get_file_params(): array
    {
        return $_FILES;
    }

    /** ووردبريس يطبّع اسم الهيدر: x_et_token ⇔ X-ET-Token */
    public function get_header($key)
    {
        $value = $this->request->headers->get(str_replace('_', '-', (string) $key));
        return $value === null ? null : (string) $value;
    }

    public function get_method(): string
    {
        return $this->request->getMethod();
    }

    public function get_route(): string
    {
        return $this->route;
    }
}
