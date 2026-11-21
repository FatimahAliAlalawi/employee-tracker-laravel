<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** حسابات المديرين: كل مدير يدخل ببريده وكلمة مروره (لوحة /admin وتبويب الإدارة بصفحة الموظفين) */
class AdminUsersController extends Controller
{
    public function index()
    {
        $html = view('admin.users', [
            'users' => User::orderBy('name')->get(),
            'me'    => Auth::id(),
        ])->render();

        return AdminController::render($html, 'et-admins', 'المديرون');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'email'    => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);

        return redirect('/admin/et-admins')->with('status', 'تمت إضافة المدير ' . $data['name'] . '.');
    }

    public function updatePassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        $user->update(['password' => $data['password']]);

        // تغيير كلمة مرور المدير الحالي يجدد جلسته حتى لا يُطرد من اللوحة
        if ($user->id === Auth::id()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect('/admin/et-admins')->with('status', 'تم تغيير كلمة مرور ' . $user->name . '. جلساته المفتوحة في تبويب الإدارة أُنهيت.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect('/admin/et-admins')->withErrors(['delete' => 'لا يمكنك حذف حسابك وأنت مسجّل الدخول به.']);
        }
        if (User::count() <= 1) {
            return redirect('/admin/et-admins')->withErrors(['delete' => 'يجب أن يبقى مدير واحد على الأقل.']);
        }

        $name = $user->name;
        $user->delete();

        return redirect('/admin/et-admins')->with('status', 'تم حذف حساب ' . $name . '.');
    }

    private function messages(): array
    {
        return [
            'name.required'      => 'الاسم مطلوب.',
            'email.required'     => 'البريد الإلكتروني مطلوب.',
            'email.email'        => 'البريد الإلكتروني غير صحيح.',
            'email.unique'       => 'هذا البريد مستخدم لمدير آخر.',
            'password.required'  => 'كلمة المرور مطلوبة.',
            'password.min'       => 'كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق.',
        ];
    }
}
