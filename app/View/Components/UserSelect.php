<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use App\Models\User;

class UserSelect extends Component
{
    public $users;
    public $selectedUsers;

    /**
     * Create a new component instance.
     */
    public function __construct($selectedUsers = [])
    {
        $this->users = User::all(); // Obtener todos los usuarios
        $this->selectedUsers = $selectedUsers;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.user-select');
    }
}
