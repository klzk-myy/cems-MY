<?php

namespace App\Livewire\Users;

use App\Enums\UserRole;
use App\Livewire\BaseComponent;
use App\Models\User;
use App\Services\UserService;
use Illuminate\View\View;

class Edit extends BaseComponent
{
    public User $user;

    public string $username = '';

    public string $email = '';

    public string $role = '';

    public bool $isActive = false;

    public array $roles = [];

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->isActive = $user->is_active;
        $this->roles = [
            UserRole::Teller->value => UserRole::Teller->label(),
            UserRole::Manager->value => UserRole::Manager->label(),
            UserRole::ComplianceOfficer->value => UserRole::ComplianceOfficer->label(),
            UserRole::Admin->value => UserRole::Admin->label(),
        ];
    }

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username,'.$this->user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$this->user->id],
            'role' => 'required|in:teller,manager,compliance_officer,admin',
            'isActive' => 'boolean',
        ];
    }

    public function save(): mixed
    {
        $this->validate();

        try {
            $userService = app(UserService::class);
            $user = $userService->updateUser($this->user, [
                'username' => $this->username,
                'email' => $this->email,
                'role' => $this->role,
                'is_active' => $this->isActive,
            ], auth()->id());

            $this->success("User {$user->username} updated successfully!");

            return $this->redirect(route('users.index'));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.users.edit');
    }
}
