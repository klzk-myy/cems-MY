<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Livewire\BaseComponent;
use App\Services\UserService;
use Illuminate\View\View;

class Create extends BaseComponent
{
    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $role = '';

    public array $roles = [];

    public function mount(): void
    {
        $this->roles = [
            UserRole::Teller->value => UserRole::Teller->description(),
            UserRole::Manager->value => UserRole::Manager->description(),
            UserRole::ComplianceOfficer->value => UserRole::ComplianceOfficer->description(),
            UserRole::Admin->value => UserRole::Admin->description(),
        ];
    }

    protected function rules(): array
    {
        return [
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'password_confirmation' => 'required',
            'role' => 'required|in:teller,manager,compliance_officer,admin',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            $userService = app(UserService::class);
            $user = $userService->createUser([
                'username' => $this->username,
                'email' => $this->email,
                'password' => $this->password,
                'role' => $this->role,
            ], auth()->id());

            $this->success("User {$user->username} created successfully!");

            return $this->redirect(route('users.index'));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.users.create');
    }
}
