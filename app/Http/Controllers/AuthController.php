<?php

namespace App\Http\Controllers;

use DB;
use App\Helpers\Email;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\StoreUser;
use App\Http\Requests\StoreUserInvite;
use App\Http\Requests\PasswordValidateResetRequest;
use App\Services\AuthService;
use App\Services\CompanyWorkforceService;
use App\Services\OrganizationSessionService;
use App\Services\PasswordResetService;
use App\Services\SocialIdentityResolverService;
use App\Services\UserService;
use App\Models\Organization;
use App\Models\PasswordResetToken;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\SocialLoginService;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Str;
use Throwable;

class AuthController extends BaseController
{
    protected $userService;
    protected $role;

    public function __construct($role = 'user')
    {
        $this->userService = new UserService($role);
        $this->role = $role;
    }

    public function showLoginForm(){
        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period', 'allow_facebook_login', 'allow_google_login'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Login', $data);
    }

    public function login(LoginRequest $request){
        $user = User::where('email', $request->email)->where('deleted_at', null)->first();
        $remember = $request->remember;

        return $this->doLogin($request, $user, $remember);
    }

    private function doLogin(Request $request, $user, $remember)
    {
        $guard = $user->role == 'user' ? 'user' : 'admin';

        if ($request->email || $request->password) {
            Auth::guard($guard)->attempt(['email' => $request->email, 'password' => $request->password], $remember);
        } else {
            Auth::guard($guard)->login($user, $remember);
        }

        //Check number of organizations
        $this->syncCurrentOrganizationSession($user);

        // Check if user's language differs from session locale and add refresh parameter
        $userLanguage = $user->language ?? 'en';
        $sessionLocale = session('locale', 'en');
        $needsRefresh = $userLanguage !== $sessionLocale;

        $redirectUrl = $this->authenticatedHomePath($user);
        if ($needsRefresh) {
            $redirectUrl .= '?refresh_lang=1';
        }

        return redirect($redirectUrl);
    }

    public function handleLogin(StoreUser $request)
    {
        $user = $this->userService->store($request);
        $authService = (new AuthService($user))->authenticateSession($request);

        return redirect('/dashboard');
    }

    public function socialLogin(Request $request, $type){
        if($type === 'google'){
            return $this->socialLoginService()->makeGoogleDriver()->redirect();
        } else if($type === 'facebook'){
            return $this->socialLoginService()->makeFacebookDriver()->redirect();
        }
    }

    public function handleFacebookCallback(Request $request){
        if ($request->has('error')) {
            $this->logSocialLoginProviderError('facebook', $request);

            return Redirect::route('login')->with(
                'status', [
                    'type' => 'error',
                    'message' => __('There was an error with Facebook login!')
                ]
            );
        }

        try {
            $facebookUser = $this->socialLoginService()
                ->makeFacebookDriver()
                ->fields(['id', 'name', 'first_name', 'last_name', 'email', 'gender', 'verified'])
                ->user();
            $user = $this->resolveOrCreateSocialUser('facebook', $facebookUser);

            return $this->loginSocialUser($user);
        } catch (ValidationException $exception) {
            $this->logSocialLoginValidationFailure('facebook', $request, $exception);

            return Redirect::route('login')->with(
                'status',
                [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first() ?? __('Registration failed, please try again.'),
                ]
            );
        } catch (Throwable $e) {
            $this->logSocialLoginException('facebook', $request, $e);

            return $this->socialLoginFailureResponse();
        }
    }

    public function googleCallback(Request $request){
        if ($request->has('error')) {
            $this->logSocialLoginProviderError('google', $request);

            return Redirect::route('login')->with(
                'status', [
                    'type' => 'error',
                    'message' => __('There was an error with Google login!')
                ]
            );
        }

        try {
            $googleUser = $this->socialLoginService()->makeGoogleDriver()->user();
            $user = $this->resolveOrCreateSocialUser('google', $googleUser);

            return $this->loginSocialUser($user);
        } catch (ValidationException $exception) {
            $this->logSocialLoginValidationFailure('google', $request, $exception);

            return Redirect::route('login')->with(
                'status',
                [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first() ?? __('Registration failed, please try again.'),
                ]
            );
        } catch (Throwable $e) {
            $this->logSocialLoginException('google', $request, $e);

            return $this->socialLoginFailureResponse();
        }
    }

    public function showRegistrationForm()
    {
        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period', 'allow_facebook_login', 'allow_google_login'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();
        $data['signupPhoneCountries'] = config('formats.phone_countries', []);

        return Inertia::render('Auth/Register', $data);
    }

    public function handleRegistration(SignupRequest $request)
    {
        $config = Setting::where('key', 'verify_email')->first();
        $verifyEmailEnabled = isset($config->value) && $config->value == '1';
        $user = $this->userService->store($request, [
            'send_registration_email' => ! $verifyEmailEnabled,
        ]);
        $authService = (new AuthService($user))->authenticateSession($request);

        if ($verifyEmailEnabled) {
            $user->sendEmailVerificationNotification();
        }

        return redirect($this->authenticatedHomePath($user));
    }

    public function viewInvite($uuid)
    {
        $invite = TeamInvite::where('code', $uuid)->first();
        $companyEmployeeInvite = $invite ? null : app(CompanyWorkforceService::class)->findInviteByCode($uuid);

        if(!$invite && !$companyEmployeeInvite){
            return Redirect::route('login')->with(
                'status', [
                    'type' => 'success', 
                    'message' => __('That page does not exist!')
                ]
            );
        } else {
            if ($invite) {
                $data['organization'] = Organization::where('id', $invite->organization_id)->first();
                $data['user'] = User::where('email', $invite->email)->where('role', 'user')->first();
                $data['invite'] = $invite;
            } else {
                $data['organization'] = $companyEmployeeInvite->mainOrganization;
                $data['user'] = User::where('email', $companyEmployeeInvite->email)->where('role', 'user')->first();
                $data['invite'] = (object) [
                    'email' => $companyEmployeeInvite->email,
                    'expire_at' => optional($companyEmployeeInvite->invite_expires_at)?->toDateTimeString(),
                ];
            }
            $data['code'] = $uuid;

            $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period', 'allow_facebook_login', 'allow_google_login'];
            $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

            return Inertia::render('Auth/Invite', $data);
        }
    }

    public function invite(StoreUserInvite $request, $inviteCode)
    {
        $invite = TeamInvite::where('code', $inviteCode)->first();

        if ($invite) {
            $result = app(TeamService::class)->store($request, $inviteCode);

            if ($result instanceof \Illuminate\Http\RedirectResponse) {
                return $result;
            }
        } else {
            app(CompanyWorkforceService::class)->acceptInvite($request, $inviteCode);
        }

        return Redirect::route('dashboard');
    }

    public function showForgotForm(Request $request)
    {
        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Forgot', $data);
    }

    public function createPasswordResetToken(PasswordResetRequest $request)
    {
        (new PasswordResetService)->generateResetLink($request->input('email'));

        return redirect('/forgot-password')->with(
            'status', [
                'type' => 'success', 
                'message' => __('We\'ve sent you a password reset link to your email!')
            ]
        );
    }

    public function showResetForm(Request $request)
    {
        $email = $request->input('email');
        $token = $request->input('token');

        if(!(new PasswordResetService)->verifyResetCode($email, $token)){
            return redirect('/login');
        }

        $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period'];
        $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        return Inertia::render('Auth/Reset', $data);
    }

    public function resetPassword(PasswordValidateResetRequest $request)
    {
        (new PasswordResetService)->resetPassword($request);

        return redirect('/login')->with(
            'status', [
                'type' => 'success', 
                'message' => __('Password reset successful!')
            ]
        );
    }

    public function verifyEmail()
    {
        if(auth()->user()->email_verified_at != NULL){
            return redirect('dashboard');
        } else {
            $keys = ['logo', 'company_name', 'address', 'email', 'phone', 'socials', 'trial_period'];
            $data['companyConfig'] = Setting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

            return Inertia::render('Auth/VerifyEmail', $data);
        }
    }

    public function sendEmailVerification(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return back()->with(
            'status', [
                'type' => 'success', 
                'message' => __('Verification link sent!')
            ]
        );
    }

    public function logout(Request $request)
    {
        Auth::guard('user')->logout();
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('login');
    }

    private function syncCurrentOrganizationSession(User $user): void
    {
        if ($user->role !== 'user') {
            return;
        }

        $organizationIds = app(OrganizationSessionService::class)->organizationIdsForUser($user->id);

        if (count($organizationIds) === 1) {
            session()->put('current_organization', $organizationIds[0]);

            return;
        }

        session()->forget('current_organization');
    }

    private function socialLoginService(): SocialLoginService
    {
        return app(SocialLoginService::class);
    }

    private function loginSocialUser(User $user)
    {
        $guard = $user->role === 'admin' ? 'admin' : 'user';

        Auth::guard($guard)->login($user, true);
        $this->syncCurrentOrganizationSession($user);

        return redirect($this->authenticatedHomePath($user));
    }

    private function authenticatedHomePath(User $user): string
    {
        if ($user->role === 'admin') {
            return '/admin/dashboard';
        }

        return session()->has('current_organization')
            ? '/dashboard'
            : route('user.organization.index');
    }

    private function resolveOrCreateSocialUser(string $provider, object $socialUser): User
    {
        return app(SocialIdentityResolverService::class)->resolveOrCreateUser($provider, $socialUser);
    }

    private function splitSocialDisplayName(object $socialUser): array
    {
        $fullName = trim((string) (
            $socialUser->name
            ?? data_get((array) ($socialUser->user ?? []), 'name')
            ?? ''
        ));

        if ($fullName === '') {
            $firstName = trim((string) (
                $socialUser->first_name
                ?? data_get((array) ($socialUser->user ?? []), 'given_name')
                ?? __('User')
            ));
            $lastName = trim((string) (
                $socialUser->last_name
                ?? data_get((array) ($socialUser->user ?? []), 'family_name')
                ?? ''
            ));

            return [$firstName !== '' ? $firstName : __('User'), $lastName !== '' ? $lastName : null];
        }

        $nameParts = preg_split('/\s+/', $fullName) ?: [];
        $firstName = $nameParts[0] ?? __('User');
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;

        return [$firstName, $lastName ?: null];
    }

    private function socialLoginFailureResponse()
    {
        return Redirect::route('login')->with(
            'status',
            [
                'type' => 'error',
                'message' => __('Registration failed, please try again.'),
            ]
        );
    }

    private function logSocialLoginProviderError(string $provider, Request $request): void
    {
        Log::warning('Social login provider returned an error.', [
            'provider' => $provider,
            'error' => $request->query('error'),
            'error_reason' => $request->query('error_reason'),
            'error_description' => $request->query('error_description'),
            'has_code' => $request->filled('code'),
            'has_state' => $request->filled('state'),
        ]);
    }

    private function logSocialLoginValidationFailure(string $provider, Request $request, ValidationException $exception): void
    {
        Log::warning('Social login validation failed.', [
            'provider' => $provider,
            'errors' => $exception->errors(),
            'has_code' => $request->filled('code'),
            'has_state' => $request->filled('state'),
        ]);
    }

    private function logSocialLoginException(string $provider, Request $request, Throwable $exception): void
    {
        Log::error('Social login failed.', [
            'provider' => $provider,
            'exception_class' => $exception::class,
            'message' => $exception->getMessage(),
            'has_code' => $request->filled('code'),
            'has_state' => $request->filled('state'),
        ]);
    }
}
