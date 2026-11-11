<?php

namespace App\Http\Controllers;

use App\Services\LegacyAuthService;
use App\Services\NetworkBlockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function index()
    {
        if (session()->has('usertype')) {
            return $this->redirectForRole(session('usertype'), null);
        }

        return view('login');
    }

    public function processLogin(Request $request, LegacyAuthService $auth, NetworkBlockService $block)
    {
        // Old behaviour: empty/missing fields simply fail as "Invalid Account".
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        $key = 'login:'.$request->ip().'|'.mb_strtolower(mb_substr($username, 0, 150));
        $max = config('cablebill.login_max_attempts');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            Log::warning('login throttled', ['ip' => $request->ip(), 'username' => $username]);

            return response()->view('login', [
                'error' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ], 429);
        }

        if ($username === '' || $password === ''
            || mb_strlen($username) > 150 || mb_strlen($password) > 200
            || ! $auth->attempt($username, $password)) {
            RateLimiter::hit($key, 60);
            Log::notice('login failed', ['ip' => $request->ip(), 'username' => mb_substr($username, 0, 150)]);

            return view('login', ['error' => 'Invalid Account']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();   // session fixation protection
        $block->notifications();
        Log::info('login ok', ['userid' => session('userid'), 'usertype' => session('usertype')]);

        return $this->redirectForRole(session('usertype'), $block);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(LegacyAuthService::SESSION_KEYS);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('Login');
    }

    private function redirectForRole($usertype, ?NetworkBlockService $block)
    {
        $usertype = (string) $usertype;
        if ($usertype === '1') {
            return redirect('master');
        }
        if ($usertype === '2') {
            $block ??= app(NetworkBlockService::class);

            return $block->blockNetwork(session('userid')) === 'false'
                ? redirect('master')
                : redirect('Transaction/monthly_bills');
        }

        return redirect('agent');
    }
}
