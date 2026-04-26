<?php

namespace App\Livewire\Users;

use App\Livewire\BaseComponent;
use App\Models\User;
use App\Services\UserService;
use Illuminate\View\View;

class ResetPassword extends BaseComponent
{
    public User $user;

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    protected function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
        ];
    }

    public function resetPasswordAction(): mixed
    {
        $this->validate();

        try {
            $userService = app(UserService::class);
            $userService->resetPassword($this->user, $this->password, auth()->id());

            $this->success("Password for {$this->user->username} has been reset!");

            return $this->redirect(route('users.index'));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    public function render(): View
    {
        return view('livewire.users.reset-password');
    }
}
