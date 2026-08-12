<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class LoginRequest extends FormRequest
{
    /**
     * Determine se o usuário está autorizado a fazer esta solicitação.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Obtenha as regras de validação que devem ser aplicadas à solicitação.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6',
        ];
    }

    /**
     * Mensagens de erro personalizadas para validação.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'email.exists' => 'Este e-mail não está registrado em nosso sistema.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 6 caracteres.',
        ];
    }

    /**
     * Configuração de validação adicional depois das regras básicas.
     */
    protected function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if (!$this->attemptLogin()) {
                $validator->errors()->add('password', 'Senha incorreta.');
            }
        });
    }

    /**
     * Tenta autenticar o usuário com os dados fornecidos.
     *
     * @return bool
     */
    protected function attemptLogin()
    {
        return Auth::attempt(['email' => $this->email, 'password' => $this->password]);
    }
}