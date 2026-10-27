@extends('layouts.guest')

@section('title', 'Crear cuenta')
@section('subtitle', 'Empieza a controlar tus gastos, gastos fijos y deudas en minutos.')

@section('content')
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        @include('auth.partials.errors')

        <div>
            <label for="name" class="label">Nombre</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" class="input">
        </div>

        <div>
            <label for="email" class="label">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" class="input">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="password" class="label">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
            </div>
            <div>
                <label for="password_confirmation" class="label">Repítela</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
        </div>

        <button type="submit" class="btn-primary w-full py-3">Crear cuenta</button>

        <p class="text-center text-sm text-slate-500">
            ¿Ya tienes cuenta? <a href="{{ route('login') }}" class="font-semibold text-emerald-700 hover:underline">Ingresa</a>
        </p>
    </form>
@endsection
