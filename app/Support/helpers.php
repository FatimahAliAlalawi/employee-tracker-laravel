<?php

/*
|--------------------------------------------------------------------------
| طبقة التوافق — دوال بنفس أسماء دوال ووردبريس التي يستخدمها منطق النظام الأصلي،
| لكنها منفّذة بالكامل فوق Laravel (DB / Cache / Mail / Http / Auth / Hash).
| لا يوجد أي اعتماد على ووردبريس. الهدف: نقل منطق العمل (17 ألف سطر) كما هو حرفيًا
| بدل إعادة كتابته، لتجنّب إدخال أخطاء في حسابات الحضور والإجازات والأداء.
|--------------------------------------------------------------------------
*/

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/* ---------------- ثوابت ---------------- */
foreach ([
    'MINUTE_IN_SECONDS' => 60,
    'HOUR_IN_SECONDS'   => 3600,
    'DAY_IN_SECONDS'    => 86400,
    'WEEK_IN_SECONDS'   => 604800,
    'MONTH_IN_SECONDS'  => 2592000,
    'YEAR_IN_SECONDS'   => 31536000,
    'KB_IN_BYTES'       => 1024,
    'MB_IN_BYTES'       => 1048576,
    'ET_VERSION'        => '3.1.0',
    'ET_DB_VERSION'     => '3.0.0',
] as $__name => $__value) {
    if (! defined($__name)) {
        define($__name, $__value);
    }
}
unset($__name, $__value);

/* ---------------- الخطافات (Actions / Filters) — سجل بسيط داخل الطلب ---------------- */
function et_hooks_registry(): array
{
    return $GLOBALS['et_hooks'] ??= [];
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['et_hooks'][$hook][$priority][] = [$callback, (int) $accepted_args];
    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    return add_action($hook, $callback, $priority, $accepted_args);
}

function remove_action($hook, $callback, $priority = 10)
{
    if (empty($GLOBALS['et_hooks'][$hook][$priority])) {
        return false;
    }
    foreach ($GLOBALS['et_hooks'][$hook][$priority] as $i => $entry) {
        if ($entry[0] === $callback) {
            unset($GLOBALS['et_hooks'][$hook][$priority][$i]);
            return true;
        }
    }
    return false;
}

function remove_filter($hook, $callback, $priority = 10)
{
    return remove_action($hook, $callback, $priority);
}

function do_action($hook, ...$args)
{
    $GLOBALS['et_actions_done'][$hook] = ($GLOBALS['et_actions_done'][$hook] ?? 0) + 1;
    $hooks = $GLOBALS['et_hooks'][$hook] ?? [];
    ksort($hooks);
    foreach ($hooks as $entries) {
        foreach ($entries as [$cb, $accepted]) {
            call_user_func_array($cb, array_slice($args, 0, max(1, $accepted)));
        }
    }
}

function did_action($hook)
{
    return $GLOBALS['et_actions_done'][$hook] ?? 0;
}

function apply_filters($hook, $value, ...$args)
{
    $hooks = $GLOBALS['et_hooks'][$hook] ?? [];
    ksort($hooks);
    foreach ($hooks as $entries) {
        foreach ($entries as [$cb, $accepted]) {
            $value = call_user_func_array($cb, array_slice(array_merge([$value], $args), 0, max(1, $accepted)));
        }
    }
    return $value;
}

/* ---------------- الأخطاء والردود ---------------- */
function is_wp_error($thing)
{
    return $thing instanceof WP_Error;
}

function rest_ensure_response($response)
{
    return $response;
}

function wp_die($message = '', $title = '', $args = [])
{
    abort(is_array($args) && isset($args['response']) ? (int) $args['response'] : 403, is_string($message) ? $message : '');
}

function wp_safe_redirect($location, $status = 302)
{
    throw new HttpResponseException(redirect()->to($location, $status));
}

function wp_redirect($location, $status = 302)
{
    wp_safe_redirect($location, $status);
}

function nocache_headers()
{
    // الردود تُبنى عبر Laravel وتضيف ترويسات منع التخزين بنفسها
}

function status_header($code)
{
    http_response_code((int) $code);
}

/* ---------------- الخيارات (wp_options ⇒ جدول et_options) ---------------- */
function et_options_table(): string
{
    return config('tracker.table_prefix', '') . 'et_options';
}

function get_option($name, $default = false)
{
    $cache = &$GLOBALS['et_option_cache'];
    if (is_array($cache) && array_key_exists($name, $cache)) {
        return $cache[$name] === null ? $default : $cache[$name];
    }
    try {
        $raw = DB::table(et_options_table())->where('option_name', $name)->value('option_value');
    } catch (\Throwable $e) {
        return $default;
    }
    $value = $raw === null ? null : maybe_unserialize($raw);
    $cache[$name] = $value;
    return $value === null ? $default : $value;
}

function update_option($name, $value, $autoload = null)
{
    $GLOBALS['et_option_cache'][$name] = $value;
    DB::table(et_options_table())->updateOrInsert(
        ['option_name' => $name],
        ['option_value' => maybe_serialize($value)]
    );
    return true;
}

function add_option($name, $value = '', $deprecated = '', $autoload = null)
{
    if (get_option($name, null) !== null) {
        return false;
    }
    return update_option($name, $value);
}

function delete_option($name)
{
    unset($GLOBALS['et_option_cache'][$name]);
    DB::table(et_options_table())->where('option_name', $name)->delete();
    return true;
}

function maybe_serialize($data)
{
    if (is_array($data) || is_object($data)) {
        return serialize($data);
    }
    return $data;
}

function maybe_unserialize($data)
{
    if (is_string($data) && is_serialized($data)) {
        $value = @unserialize(trim($data), ['allowed_classes' => false]);
        return $value === false && $data !== 'b:0;' ? $data : $value;
    }
    return $data;
}

function is_serialized($data)
{
    if (! is_string($data)) {
        return false;
    }
    $data = trim($data);
    if ($data === 'N;') {
        return true;
    }
    return (bool) preg_match('/^([adObis]):/', $data) && in_array(substr($data, -1), [';', '}'], true);
}

/* ---------------- Transients / Object cache ⇒ Laravel Cache ---------------- */
function get_transient($key)
{
    $v = Cache::get('et_t_' . $key);
    return $v === null ? false : $v;
}

function set_transient($key, $value, $expiration = 0)
{
    $expiration = (int) $expiration;
    $expiration > 0 ? Cache::put('et_t_' . $key, $value, $expiration) : Cache::forever('et_t_' . $key, $value);
    return true;
}

function delete_transient($key)
{
    Cache::forget('et_t_' . $key);
    return true;
}

function wp_using_ext_object_cache()
{
    return false;
}

function wp_cache_get($key, $group = '')
{
    $v = Cache::get('et_c_' . $group . '_' . $key);
    return $v === null ? false : $v;
}

function wp_cache_add($key, $value, $group = '', $ttl = 0)
{
    return Cache::add('et_c_' . $group . '_' . $key, $value, $ttl > 0 ? (int) $ttl : null);
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
    return Cache::increment('et_c_' . $group . '_' . $key, $offset);
}

function wp_cache_delete($key, $group = '')
{
    return Cache::forget('et_c_' . $group . '_' . $key);
}

/* ---------------- الوقت ---------------- */
function wp_timezone()
{
    return new DateTimeZone((string) config('tracker.timezone', 'UTC'));
}

function current_time($type, $gmt = false)
{
    if ($type === 'timestamp' || $type === 'U') {
        if ($gmt) {
            return time();
        }
        $now = new DateTime('now', wp_timezone());
        return time() + $now->getOffset();
    }
    if ($type === 'mysql') {
        $type = 'Y-m-d H:i:s';
    }
    $dt = new DateTime('now', $gmt ? new DateTimeZone('UTC') : wp_timezone());
    return $dt->format($type);
}

function wp_date($format, $timestamp = null, $timezone = null)
{
    $dt = new DateTime('@' . ($timestamp ?? time()));
    $dt->setTimezone($timezone ?: wp_timezone());
    return $dt->format($format);
}

function date_i18n($format, $timestamp = false, $gmt = false)
{
    return wp_date($format, $timestamp ?: null);
}

function mysql2date($format, $date, $translate = true)
{
    if (empty($date)) {
        return false;
    }
    try {
        $dt = new DateTime($date, wp_timezone());
    } catch (\Throwable $e) {
        return false;
    }
    if ($format === 'G' || $format === 'U') {
        return $dt->getTimestamp();
    }
    return $dt->format($format);
}

function human_time_diff($from, $to = 0)
{
    $to = $to ?: time();
    $diff = (int) abs($to - $from);
    if ($diff < HOUR_IN_SECONDS) {
        $n = max(1, (int) round($diff / MINUTE_IN_SECONDS));
        return $n . ' دقيقة';
    }
    if ($diff < DAY_IN_SECONDS) {
        return max(1, (int) round($diff / HOUR_IN_SECONDS)) . ' ساعة';
    }
    if ($diff < WEEK_IN_SECONDS) {
        return max(1, (int) round($diff / DAY_IN_SECONDS)) . ' يوم';
    }
    if ($diff < MONTH_IN_SECONDS) {
        return max(1, (int) round($diff / WEEK_IN_SECONDS)) . ' أسبوع';
    }
    if ($diff < YEAR_IN_SECONDS) {
        return max(1, (int) round($diff / MONTH_IN_SECONDS)) . ' شهر';
    }
    return max(1, (int) round($diff / YEAR_IN_SECONDS)) . ' سنة';
}

/* ---------------- تنظيف المدخلات والتهريب ---------------- */
function _et_sanitize_text_fields($str, $keep_newlines = false)
{
    if (is_object($str) || is_array($str)) {
        return '';
    }
    $str = (string) $str;
    if (! mb_check_encoding($str, 'UTF-8')) {
        return '';
    }
    if (str_contains($str, '<')) {
        $str = wp_strip_all_tags($str, false);
    }
    if (! $keep_newlines) {
        $str = preg_replace('/[\r\n\t ]+/', ' ', $str);
    }
    $str = trim($str);
    $found = false;
    while (preg_match('/%[a-f0-9]{2}/i', $str, $match)) {
        $str = str_replace($match[0], '', $str);
        $found = true;
    }
    if ($found) {
        $str = trim(preg_replace('/ +/', ' ', $str));
    }
    return $str;
}

function sanitize_text_field($str)
{
    return _et_sanitize_text_fields($str, false);
}

function sanitize_textarea_field($str)
{
    return _et_sanitize_text_fields($str, true);
}

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function sanitize_email($email)
{
    $email = trim((string) $email);
    $clean = filter_var($email, FILTER_SANITIZE_EMAIL);
    return is_email($clean) ? $clean : '';
}

function is_email($email)
{
    return filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

function sanitize_hex_color($color)
{
    return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color) ? $color : null;
}

function sanitize_file_name($filename)
{
    $filename = (string) $filename;
    $special = ['?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr(0)];
    $filename = str_replace($special, '', $filename);
    $filename = preg_replace('/[\r\n\t -]+/', '-', $filename);
    return trim($filename, '.-_');
}

function absint($value)
{
    return abs((int) $value);
}

function wp_unslash($value)
{
    // Laravel لا يضيف علامات الهروب (magic quotes) كما يفعل ووردبريس، فلا شيء لإزالته.
    return $value;
}

function wp_strip_all_tags($text, $remove_breaks = false)
{
    if ($text === null) {
        return '';
    }
    $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
    $text = strip_tags($text);
    if ($remove_breaks) {
        $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
    }
    return trim($text);
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
}

function esc_textarea($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url_raw($url, $protocols = null)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $url = str_replace(' ', '%20', $url);
    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', $url);
    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $url, $m)) {
        if (! in_array(strtolower($m[1]), $protocols ?: ['http', 'https', 'mailto', 'tel'], true)) {
            return '';
        }
    }
    return $url;
}

function esc_url($url, $protocols = null)
{
    $clean = esc_url_raw($url, $protocols);
    return str_replace(['&amp;', '&', "'"], ['&#038;', '&#038;', '&#039;'], $clean);
}

function wp_json_encode($data, $options = 0, $depth = 512)
{
    return json_encode($data, $options | JSON_PARTIAL_OUTPUT_ON_ERROR, $depth);
}

function wp_parse_args($args, $defaults = [])
{
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (! is_array($args)) {
        parse_str((string) $args, $args);
    }
    return is_array($defaults) && $defaults ? array_merge($defaults, $args) : $args;
}

function wp_list_pluck($list, $field, $index_key = null)
{
    $out = [];
    foreach ((array) $list as $key => $item) {
        $value = is_object($item) ? ($item->$field ?? null) : ($item[$field] ?? null);
        if ($index_key === null) {
            $out[$key] = $value;
        } else {
            $idx = is_object($item) ? ($item->$index_key ?? null) : ($item[$index_key] ?? null);
            $idx === null ? $out[] = $value : $out[$idx] = $value;
        }
    }
    return $out;
}

function wp_parse_url($url, $component = -1)
{
    return parse_url((string) $url, $component);
}

function wp_http_validate_url($url)
{
    $url = (string) $url;
    if (! filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : false;
}

function trailingslashit($value)
{
    return rtrim((string) $value, '/\\') . '/';
}

function untrailingslashit($value)
{
    return rtrim((string) $value, '/\\');
}

function wp_normalize_path($path)
{
    $path = str_replace('\\', '/', (string) $path);
    $path = preg_replace('|(?<=.)/+|', '/', $path);
    if (preg_match('|^[a-z]:/|', $path)) {
        $path = ucfirst($path);
    }
    return $path;
}

function wp_mkdir_p($target)
{
    return is_dir($target) || @mkdir($target, 0755, true);
}

function wp_check_filetype($filename, $mimes = null)
{
    $mimes = $mimes ?: [
        'jpg|jpeg|jpe' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png', 'webp' => 'image/webp',
        'pdf' => 'application/pdf', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv', 'txt' => 'text/plain',
    ];
    foreach ($mimes as $exts => $mime) {
        if (preg_match('!\.(' . $exts . ')$!i', (string) $filename, $m)) {
            return ['ext' => strtolower($m[1]), 'type' => $mime];
        }
    }
    return ['ext' => false, 'type' => false];
}

/* ---------------- عشوائية وكلمات مرور ---------------- */
function wp_rand($min = 0, $max = 0)
{
    $max = $max ?: mt_getrandmax();
    return random_int((int) min($min, $max), (int) max($min, $max));
}

function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($special_chars) {
        $chars .= '!@#$%^&*()';
    }
    if ($extra_special_chars) {
        $chars .= '-_ []{}<>~`+=,.;:/?|';
    }
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function wp_hash_password($password)
{
    return Hash::make((string) $password);
}

/** يتحقق من كل صيغ التشفير: bcrypt/argon (Laravel)، ورموز ووردبريس القديمة ($P$ و $wp$) المستوردة */
function wp_check_password($password, $hash, $user_id = '')
{
    $hash = (string) $hash;
    if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
        return et_phpass_check((string) $password, $hash);
    }
    if (str_starts_with($hash, '$wp')) {
        $pre = base64_encode(hash_hmac('sha384', trim((string) $password), 'wp-sha384', true));
        return password_verify($pre, substr($hash, 3));
    }
    return password_verify((string) $password, $hash);
}

function et_phpass_check(string $password, string $stored): bool
{
    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $count_log2 = strpos($itoa64, $stored[3] ?? '');
    if ($count_log2 === false || $count_log2 < 7 || $count_log2 > 30) {
        return false;
    }
    $count = 1 << $count_log2;
    $salt = substr($stored, 4, 8);
    if (strlen($salt) !== 8) {
        return false;
    }
    $hash = md5($salt . $password, true);
    do {
        $hash = md5($hash . $password, true);
    } while (--$count);

    $encode = function ($input, $count) use ($itoa64) {
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $itoa64[$value & 0x3f];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $count) {
                break;
            }
            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $count);
        return $output;
    };

    return hash_equals($stored, substr($stored, 0, 12) . $encode($hash, 16));
}

/* ---------------- المستخدم الحالي (مدير النظام عبر Laravel Auth) ---------------- */
function current_user_can($capability = 'manage_options', ...$args)
{
    return Auth::check();
}

function get_current_user_id()
{
    return (int) (Auth::id() ?? 0);
}

function wp_get_current_user()
{
    $user = Auth::user();
    return (object) [
        'ID'           => $user ? (int) $user->id : 0,
        'display_name' => $user ? (string) $user->name : 'مدير النظام',
        'user_email'   => $user ? (string) $user->email : '',
    ];
}

function get_userdata($id)
{
    $user = \App\Models\User::find($id);
    return $user ? (object) ['ID' => (int) $user->id, 'display_name' => (string) $user->name, 'user_email' => (string) $user->email] : false;
}

function is_ssl()
{
    return request()->isSecure();
}

/* ---------------- الروابط ---------------- */
function home_url($path = '')
{
    return url($path === '' ? '/' : $path);
}

function site_url($path = '')
{
    return home_url($path);
}

function rest_url($path = '')
{
    return url('/api/' . ltrim((string) $path, '/'));
}

/** admin.php?page=et-employees&edit=5  ⇒  /admin/et-employees?edit=5 */
function admin_url($path = '')
{
    $path = (string) $path;
    $query = [];
    $qpos = strpos($path, '?');
    if ($qpos !== false) {
        parse_str(substr($path, $qpos + 1), $query);
    }
    $page = $query['page'] ?? 'employee-tracker';
    unset($query['page']);
    $url = url('/admin/' . $page);
    return $query ? $url . '?' . http_build_query($query) : $url;
}

function set_url_scheme($url, $scheme = null)
{
    if (! $scheme) {
        return $url;
    }
    return preg_replace('#^\w+://#', $scheme . '://', (string) $url);
}

function add_query_arg(...$args)
{
    if (is_array($args[0])) {
        $params = $args[0];
        $url = $args[1] ?? request()->getRequestUri();
    } else {
        $params = [$args[0] => $args[1] ?? null];
        $url = $args[2] ?? request()->getRequestUri();
    }
    $url = (string) $url;
    $frag = '';
    if (($h = strpos($url, '#')) !== false) {
        $frag = substr($url, $h);
        $url = substr($url, 0, $h);
    }
    $base = $url;
    $query = [];
    if (($q = strpos($url, '?')) !== false) {
        $base = substr($url, 0, $q);
        parse_str(substr($url, $q + 1), $query);
    }
    foreach ($params as $k => $v) {
        if ($v === false || $v === null) {
            unset($query[$k]);
        } else {
            $query[$k] = $v;
        }
    }
    $qs = http_build_query($query);
    return $base . ($qs !== '' ? '?' . $qs : '') . $frag;
}

function remove_query_arg($key, $url = false)
{
    $keys = (array) $key;
    return add_query_arg(array_fill_keys($keys, false), $url === false ? request()->getRequestUri() : $url);
}

/* ---------------- النماذج (CSRF بدل nonce) ---------------- */
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true)
{
    $html = '<input type="hidden" name="_token" value="' . esc_attr(csrf_token()) . '">';
    if ($display) {
        echo $html;
    }
    return $html;
}

function wp_create_nonce($action = -1)
{
    return csrf_token();
}

function wp_verify_nonce($nonce, $action = -1)
{
    return 1;
}

/** حماية CSRF يتكفّل بها Laravel تلقائيًا (VerifyCsrfToken) قبل الوصول لهذه النقطة */
function check_admin_referer($action = -1, $query_arg = '_wpnonce')
{
    return 1;
}

function selected($selected, $current = true, $echo = true)
{
    $out = ((string) $selected === (string) $current) ? " selected='selected'" : '';
    if ($echo) {
        echo $out;
    }
    return $out;
}

function checked($checked, $current = true, $echo = true)
{
    $out = ((string) $checked === (string) $current) ? " checked='checked'" : '';
    if ($echo) {
        echo $out;
    }
    return $out;
}

/* ---------------- البريد والـ HTTP ---------------- */
/** نفس واجهة wp_mail: يرجع true/false، وعند الفشل يطلق خطاف wp_mail_failed بكائن WP_Error */
function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
{
    try {
        $isHtml = false;
        foreach ((array) $headers as $h) {
            if (stripos((string) $h, 'text/html') !== false) {
                $isHtml = true;
            }
        }
        $send = function ($m) use ($to, $subject) {
            $m->to(array_map('trim', is_array($to) ? $to : explode(',', (string) $to)))->subject((string) $subject);
        };
        if ($isHtml) {
            Mail::html((string) $message, $send);
        } else {
            Mail::raw((string) $message, $send);
        }
        return true;
    } catch (\Throwable $e) {
        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $e->getMessage()));
        Log::warning('[wp_mail] ' . $e->getMessage());
        return false;
    }
}

function wp_remote_post($url, $args = [])
{
    try {
        $req = Http::timeout((int) ($args['timeout'] ?? 5))
            ->withHeaders((array) ($args['headers'] ?? []));
        if (isset($args['sslverify']) && ! $args['sslverify']) {
            $req = $req->withoutVerifying();
        }
        $body = $args['body'] ?? '';
        $response = is_array($body)
            ? $req->asForm()->post($url, $body)
            : $req->withBody((string) $body, $args['headers']['Content-Type'] ?? 'application/json')->post($url);
        return ['response' => ['code' => $response->status()], 'body' => $response->body()];
    } catch (\Throwable $e) {
        return new WP_Error('http_request_failed', $e->getMessage());
    }
}

function wp_safe_remote_post($url, $args = [])
{
    return wp_remote_post($url, $args);
}

function wp_remote_retrieve_response_code($response)
{
    return is_array($response) ? (int) ($response['response']['code'] ?? 0) : '';
}

function wp_remote_retrieve_body($response)
{
    return is_array($response) ? (string) ($response['body'] ?? '') : '';
}

/* ---------------- تسجيل مسارات REST (يستدعيها ET_REST_API::register_routes من routes/api.php) ---------------- */
function register_rest_route($namespace, $route, $args = [], $override = false)
{
    $methods = $args['methods'] ?? 'GET';
    $methods = is_array($methods) ? $methods : array_map('trim', explode(',', (string) $methods));
    $callback = $args['callback'] ?? null;
    $name = is_array($callback) ? (string) $callback[1] : (string) $callback;
    $uri = trim((string) $namespace, '/') . '/' . ltrim((string) $route, '/');

    \Illuminate\Support\Facades\Route::match(array_map('strtoupper', $methods), $uri, [\App\Http\Controllers\RestController::class, 'handle'])
        ->defaults('et_callback', $name)
        ->defaults('et_route', '/' . $uri);
    return true;
}

/* ---------------- متفرقات ---------------- */
function get_bloginfo($show = 'name')
{
    return (string) config('tracker.site_name', config('app.name'));
}

function wp_doing_cron()
{
    return app()->runningInConsole();
}

require __DIR__ . '/tracker-functions.php';
