<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\RedirectResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Crear otro usuario desde Ajustes sin cerrar la sesión actual. Cada usuario ve solo sus datos.
 */
class UserController extends Controller
{
    public function store(Request $request, CreateNewUser $creator): RedirectResponse
    {
        try {
            $user = $creator->create($request->only('name', 'email', 'password', 'password_confirmation'));
        } catch (ValidationException $e) {
            throw $e->errorBag('newUser');
        }

        return back()->with('status', "Usuario {$user->email} creado. Ya puede ingresar con su correo y contraseña.");
    }

    /** Cambiar el nombre, correo o contraseña propios. Pide la contraseña actual. */
    public function updateMe(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('me', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'current_password' => ['required', 'current_password'],
        ], [], ['current_password' => 'contraseña actual']);

        $user->update(array_filter([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
        ]));

        return back()->with('status', 'Tus datos de acceso se actualizaron.');
    }
}
