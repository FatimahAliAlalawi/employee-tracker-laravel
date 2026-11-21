<?php

/**
 * كائن خطأ بنفس واجهة WP_Error — منطق النظام الأصلي يعيده من دواله، ويحوّله
 * App\Http\Controllers\RestController إلى رد JSON بنفس صيغة ووردبريس:
 * {"code": "...", "message": "...", "data": {"status": 4xx}}
 */
class WP_Error
{
    public array $errors = [];
    public array $error_data = [];

    public function __construct($code = '', $message = '', $data = '')
    {
        if ($code === '' || $code === null) {
            return;
        }
        $this->add($code, $message, $data);
    }

    public function add($code, $message, $data = ''): void
    {
        $this->errors[$code][] = $message;
        if ($data !== '' && $data !== null) {
            $this->error_data[$code] = $data;
        }
    }

    public function get_error_code()
    {
        $codes = array_keys($this->errors);
        return $codes[0] ?? '';
    }

    public function get_error_codes(): array
    {
        return array_keys($this->errors);
    }

    public function get_error_message($code = '')
    {
        if ($code === '') {
            $code = $this->get_error_code();
        }
        return $this->errors[$code][0] ?? '';
    }

    public function get_error_messages($code = ''): array
    {
        if ($code === '') {
            $all = [];
            foreach ($this->errors as $messages) {
                $all = array_merge($all, $messages);
            }
            return $all;
        }
        return $this->errors[$code] ?? [];
    }

    public function get_error_data($code = '')
    {
        if ($code === '') {
            $code = $this->get_error_code();
        }
        return $this->error_data[$code] ?? null;
    }

    public function has_errors(): bool
    {
        return ! empty($this->errors);
    }
}
