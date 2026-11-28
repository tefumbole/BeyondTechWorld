<?php

namespace App\Services\Mai;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class MaiCustomerAuth
{
    protected static $testingToken = '';

    public static function testingToken()
    {
        return app()->runningUnitTests() ? self::$testingToken : '';
    }

    public function register($name, $email, $password)
    {
        $this->ready();
        $email = strtolower(trim((string) $email));
        $name = trim((string) $name);
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 8) {
            throw new \InvalidArgumentException('Enter your name, a valid email, and a password of at least 8 characters.');
        }
        $user = User::where('email', $email)->first();
        if ($user && ! Hash::check($password, $user->password)) {
            throw new \InvalidArgumentException('That email is already registered.');
        }
        if (! $user) {
            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->password = Hash::make($password);
            if (Schema::hasColumn('users', 'role_id')) {
                $user->role_id = 5;
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $user->is_active = 1;
            }
            if (Schema::hasColumn('users', 'company_name')) {
                $user->company_name = $name;
            }
            if (Schema::hasColumn('users', 'phone')) {
                $user->phone = '';
            }
            $user->save();
        }
        $this->issue($user);

        return $user;
    }

    public function login($email, $password)
    {
        $this->ready();
        $user = User::where('email', strtolower(trim((string) $email)))->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            throw new \InvalidArgumentException('Those sign-in details do not match.');
        }
        if (! $this->verified($user)) {
            $this->issue($user);
            throw new \InvalidArgumentException('Confirm the email sent to you before opening Whats MAI.');
        }
        Auth::login($user);
        $this->rememberSignIn($user);

        return $user;
    }

    public function confirm($plain)
    {
        $this->ready();
        $hash = hash('sha256', (string) $plain);
        $row = DB::table('mai_email_verifications')->where('token_hash', $hash)->first();
        if (! $row || ($row->expires_at && strtotime($row->expires_at) < time())) {
            throw new \InvalidArgumentException('That confirmation link has expired.');
        }
        DB::table('mai_email_verifications')->where('id', $row->id)->update([
            'verified_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($row->user_id);
        if (! $user) {
            throw new \InvalidArgumentException('That confirmation link has expired.');
        }
        Auth::login($user);
        $this->rememberSignIn($user);

        return $user;
    }

    public function verified(User $user)
    {
        return DB::table('mai_email_verifications')
            ->where('user_id', $user->id)
            ->whereNotNull('verified_at')
            ->exists();
    }

    protected function issue(User $user)
    {
        $plain = bin2hex(random_bytes(16));
        DB::table('mai_email_verifications')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (app()->runningUnitTests()) {
            self::$testingToken = $plain;
        }
        $link = url('/mai/verify/'.$plain);
        try {
            Mail::raw("Confirm your Whats MAI email:\n".$link, function ($message) use ($user) {
                $message->to($user->email)->subject('Confirm your Whats MAI email');
            });
        } catch (\Throwable $e) {
        }
    }

    protected function rememberSignIn(User $user)
    {
        if (! Schema::hasTable('mai_signins')) {
            return;
        }
        DB::table('mai_signins')->insert([
            'user_id' => $user->id,
            'signed_in_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function ready()
    {
        if (! config('cloud.mai_customer.enabled')) {
            throw new \RuntimeException('Whats MAI customer signup is not open.');
        }
    }
}
