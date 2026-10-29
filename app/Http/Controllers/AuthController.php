<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Plano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('personagens.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Informe o e-mail.',
            'email.email'       => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ]);

        if (Auth::attempt($credenciais, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('personagens.index'));
        }

        return back()
            ->withErrors(['email' => 'E-mail ou senha incorretos.'])
            ->onlyInput('email');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('personagens.index');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required'      => 'Informe seu nome.',
            'name.max'           => 'O nome pode ter no máximo 100 caracteres.',
            'email.required'     => 'Informe o e-mail.',
            'email.email'        => 'Informe um e-mail válido.',
            'email.unique'       => 'Já existe uma conta com esse e-mail.',
            'password.required'  => 'Informe uma senha.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min'       => 'A senha precisa ter pelo menos 8 caracteres.',
        ]);

        $user = User::create([
            'name'     => $dados['name'],
            'email'    => $dados['email'],
            'password' => Hash::make($dados['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // iniciante: primeiro pergunta se veio comprar os livros ou jogar
        return Plano::temColunas() ? redirect()->route('boas-vindas') : redirect()->route('personagens.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}