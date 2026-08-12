<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\ConfirmAccountDeletion;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicAccountDeletionController
{
    public function show(Request $request): View
    {
        return view('public.delete-account', ['locale' => $this->locale($request)]);
    }

    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'lang' => ['nullable', 'in:en,ar'],
        ]);
        $locale = $data['lang'] ?? 'en';
        $user = User::query()->where('email', mb_strtolower($data['email']))->first();

        if ($user) {
            $url = URL::temporarySignedRoute(
                'account-deletion.confirm',
                now()->addMinutes(60),
                ['user' => $user->id, 'lang' => $locale],
            );
            $user->notify(new ConfirmAccountDeletion($url, $locale));
        }

        return back()->with('status', $locale === 'ar'
            ? 'إذا كان البريد مرتبطاً بحساب نشط، أرسلنا إليه رابط تأكيد صالحاً لمدة 60 دقيقة.'
            : 'If the email belongs to an active account, we sent a confirmation link valid for 60 minutes.');
    }

    public function confirm(Request $request, User $user): View
    {
        return view('public.delete-account-confirm', [
            'locale' => $this->locale($request),
            'user' => $user,
        ]);
    }

    public function destroy(Request $request, User $user, AccountDeletionService $deletion): RedirectResponse
    {
        $locale = $this->locale($request);
        $deletion->request($user);

        return redirect()->route('account-deletion.show', ['lang' => $locale])->with(
            'status',
            $locale === 'ar'
                ? 'تم تعطيل الحساب وبدأت عملية حذف البيانات. تُحذف البيانات المتبقية وفق مدة الاحتفاظ المنشورة.'
                : 'The account is disabled and deletion has started. Remaining data is removed under the published retention schedule.',
        );
    }

    private function locale(Request $request): string
    {
        return in_array($request->query('lang', $request->input('lang', 'en')), ['en', 'ar'], true)
            ? (string) $request->query('lang', $request->input('lang', 'en'))
            : 'en';
    }
}
