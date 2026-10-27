@extends('layouts.guest')

@section('title', 'Ingresar')
@section('subtitle', 'Bienvenido de vuelta. Revisa cuánto puedes gastar hoy.')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        @include('auth.partials.errors')

        <div>
            <label for="email" class="label">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
        </div>

        <div>
            <label for="password" class="label">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"> Recordarme
        </label>

        <button type="submit" class="btn-primary w-full py-3">Ingresar</button>

        <p class="text-center text-sm text-slate-500">
            ¿No tienes cuenta? <a href="{{ route('register') }}" class="font-semibold text-emerald-700 hover:underline">Regístrate</a>
        </p>
    </form>
@endsection
