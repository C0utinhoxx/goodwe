<!DOCTYPE html>
<html lang="pt-BR">
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login</title>
    <link rel="stylesheet" href="{{asset('css/login.css')}}">
    <link rel="icon" href="{{ asset('imagens/logo.png') }}" type="image/x-icon">
</head>

<body>
    
    <div class="container-login">
        <div class="lado-visual">
            <div class="conteudo-visual">
                <h1>Energia inteligente para uma mobilidade mais sustentável.</h1>

                <p>
                    Gestão inteligente de recargas, mais eficiência para hoje
                    e mais sustentabilidade para o amanhã.
                </p>
            </div>
        </div>

        <div class="lado-formulario">
            <div class="conteudo-formulario">

                <h2>Bem-vindo!</h2>

                <p>
                    Acesse sua conta para continuar.
                </p>

                <form class="form" action="{{ route('login-user') }}" method="POST">
                    @csrf
                    @method('POST')

                    <label for="email">E-mail</label>

                    <input type="email" name="email" class="email @error('email') is-invalid @enderror" id="email" placeholder="Insira seu e-mail" value="{{ old('email') }}">

                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <label for="password">Senha</label>

                    <input type="password" name="password" class="senha @error('password') is-invalid @enderror" id="password" placeholder="Digite sua senha">
                    @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror

                    <div class="opcoes-login">
                        <label>
                            <input type="checkbox" name="lembrar">
                            Lembrar de mim
                        </label>

                        <a href="#">Esqueci minha senha</a>
                    </div>

                    <button type="submit" class="logar">Entrar</button>
                </form>

                <p class="texto-cadastro">
                    Não possui uma conta?
                    <a href="{{ route('user.cadastro') }}">Cadastre-se</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>