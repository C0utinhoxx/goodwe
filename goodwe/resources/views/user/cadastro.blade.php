<!DOCTYPE html>
<html>

<head>
    <title>Cadastro</title>
    <!--<link rel="stylesheet" href="{{asset('css/cadastro.css')}}">-->
</head>
<body>
    <div class="container">
        <div class="left">
            <h2>Cadastro</h2>

            <form action="{{ route('user-store') }}" method="POST" class="form">
                @csrf
                @method('POST')

                <!-- Nome -->

                <label for="name">Nome</label>
                <input type="text" name="name" class="name @error('name') is-invalid @enderror" id="name" placeholder="Nome completo" value="{{ old('name') }}">
                @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <!-- E-mail -->

                <label for="email">E-mail</label>
                <input type="email" name="email" class="email @error('email') is-invalid @enderror" id="email" placeholder="Insira seu e-mail" value="{{ old('email') }}">
                @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror


                <!-- Senha -->

                <label for="password">Senha</label>
                <input type="password" name="password" class="senha @error('password') is-invalid @enderror" id="password" placeholder="Senha com no mínimo 6 caracteres">
                @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror


                <!-- Botão de envio -->

                <button type="submit" class="cadastrar">Cadastrar</button>


            </form>
        </div>

        <div class="right">
            <h1>Seja Bem-Vindo</h1>
            <p>Já possui uma conta? </p>
            <a href="{{ route('user.login') }}" class="logar" id="btn-login">Login</a>

        </div>

    </div>
    <script>
        document.getElementById('btn-login').addEventListener('click', function(event) {
            event.preventDefault();

            document.body.style.transition = "opacity 0.5s";
            document.body.style.opacity = 0;

            setTimeout(() => {
                window.location.href = this.href;
            }, 300);
        });
    </script>
</body>
</html>