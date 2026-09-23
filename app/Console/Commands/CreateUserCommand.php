<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
                            {login? : Логин пользователя}
                            {--password= : Пароль (если не указан — запросит интерактивно)}
                            {--admin : Создать администратора}';

    protected $description = 'Создать пользователя (для первого входа используйте с --admin)';

    public function handle(): int
    {
        $login = $this->argument('login') ?: $this->ask('Логин');

        if (! is_string($login) || trim($login) === '') {
            $this->error('Логин обязателен.');

            return self::FAILURE;
        }

        $login = trim($login);

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = $this->secret('Пароль');
            $passwordConfirmation = $this->secret('Повтор пароля');

            if ($password !== $passwordConfirmation) {
                $this->error('Пароли не совпадают.');

                return self::FAILURE;
            }
        }

        $isAdmin = (bool) $this->option('admin');
        $isFirstUser = User::query()->doesntExist();

        if ($isFirstUser) {
            $isAdmin = true;
            $this->info('В системе ещё нет пользователей — будет создан администратор.');
        }

        $validator = Validator::make(
            [
                'login' => $login,
                'password' => $password,
            ],
            [
                'login' => ['required', 'string', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::defaults()],
            ],
            [
                'login.unique' => 'Пользователь с таким логином уже существует.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $login,
            'email' => $login,
            'password' => $password,
            'is_admin' => $isAdmin,
        ]);

        $role = $user->is_admin ? 'администратор' : 'пользователь';
        $this->info("Создан {$role}: {$user->email}");

        return self::SUCCESS;
    }
}
