<?php

namespace App\Console\Commands;

use App\Services\BeyondWasenderService;
use App\Services\SystemTestAccountService;
use Illuminate\Console\Command;

class ProvisionSystemTestAccount extends Command
{
    protected $signature = 'system-test:provision {name} {phone} {--send : Send the login on WhatsApp}';

    protected $description = 'Create a system-test login that opens the admin';

    public function handle(SystemTestAccountService $accounts, BeyondWasenderService $whatsapp)
    {
        try {
            $result = $accounts->provision($this->argument('name'), $this->argument('phone'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $sent = false;
        if ($this->option('send')) {
            $sent = $this->sendLogin($whatsapp, $this->argument('name'), $this->argument('phone'), $result);
        }

        $this->line(json_encode([
            'username' => $result['username'],
            'password' => $result['password'],
            'created' => $result['created'],
            'sent' => $sent,
        ]));

        return 0;
    }

    protected function sendLogin(BeyondWasenderService $whatsapp, $name, $phone, array $result)
    {
        $login = rtrim((string) config('app.url'), '/').'/login';
        $text = "Test login\n\nHello ".$name.",\n\nYour test account is ready. Use it to sign in while you test the website.\nUsername: ".$result['username']."\nPassword: ".$result['password']."\n\nOpen this link to sign in:\n".$login."\n\nThis account opens the admin. It is not a customer account.";
        $sent = $whatsapp->sendText($phone, $text);

        return ! empty($sent['success']);
    }
}
