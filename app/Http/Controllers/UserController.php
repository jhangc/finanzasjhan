<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
