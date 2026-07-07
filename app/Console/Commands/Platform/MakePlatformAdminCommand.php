<?php

namespace App\Console\Commands\Platform;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakePlatformAdminCommand extends Command
{
    protected $signature = 'platform:make-admin
        {email : E-mailadres van een bestaande gebruiker}
        {--set-password : Vraag tweemaal een nieuw wachtwoord en stel het in voor deze gebruiker}';

    protected $description = 'Promoveer een bestaande gebruiker tot platformbeheerder.';

    public function handle(AuditLogger $auditLogger): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if ($email === '') {
            $this->error('Geef een geldig e-mailadres op.');

            return self::FAILURE;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            $this->error("Er bestaat geen gebruiker met e-mailadres [{$email}].");

            return self::FAILURE;
        }

        $shouldSetPassword = (bool) $this->option('set-password');

        if ($user->is_platform_admin && ! $shouldSetPassword) {
            $this->info("Gebruiker [{$email}] is al platformbeheerder.");

            return self::SUCCESS;
        }

        $attributes = [
            'is_platform_admin' => true,
        ];

        if ($shouldSetPassword) {
            $password = $this->askConfirmedPassword();

            if ($password === null) {
                return self::FAILURE;
            }

            $attributes['password'] = Hash::make($password);
        }

        $user->forceFill($attributes)->save();

        $auditLogger->success(
            action: 'platform.admin.promote',
            module: 'platform',
            subjectType: 'user',
            subjectKey: (string) $user->id,
            message: 'Gebruiker gepromoveerd tot platformbeheerder.',
            meta: ['email' => $email],
        );

        $this->info("Gebruiker [{$email}] is nu platformbeheerder.");

        if ($shouldSetPassword) {
            $this->info('Het wachtwoord werd bijgewerkt.');
        }

        return self::SUCCESS;
    }

    private function askConfirmedPassword(): ?string
    {
        if (! $this->input->isInteractive()) {
            $this->error('Een wachtwoord instellen kan alleen interactief. Laat --set-password weg of voer het commando interactief uit.');

            return null;
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $password = (string) $this->secret('Nieuw wachtwoord');
            $confirmation = (string) $this->secret('Herhaal nieuw wachtwoord');

            if (mb_strlen($password) < 8) {
                $this->warn('Het wachtwoord moet minstens 8 tekens bevatten.');

                continue;
            }

            if (! hash_equals($password, $confirmation)) {
                $this->warn('De wachtwoorden komen niet overeen.');

                continue;
            }

            return $password;
        }

        $this->error('Wachtwoord niet ingesteld na 3 mislukte pogingen.');

        return null;
    }
}
